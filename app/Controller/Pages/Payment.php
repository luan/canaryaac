<?php
/**
 * Payment Class
 *
 * @package   CanaryAAC
 * @author    Lucas Giovanni <lucasgiovannidesigner@gmail.com>
 * @copyright 2022 CanaryAAC
 */

namespace App\Controller\Pages;

use App\Model\Entity\Account as EntityAccount;
use App\Payment\PagSeguro\ApiPagSeguro;
use App\Payment\MercadoPago\ApiMercadoPago;
use App\Payment\PayPal\ApiPayPal;
use \App\Utils\View;
use App\Session\Admin\Login as SessionAdminLogin;
use App\Model\Entity\Payments as EntityPayments;
use App\Model\Entity\ServerConfig as EntityServerConfig;

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

function calculatePrice($coins) {
    $priceMap = [
        100 => 2,
        250 => 5,
        600 => 10,
        1600 => 25,
        3400 => 50,
        7000 => 100
    ];

    return $priceMap[$coins] ?? null;
}

class Payment extends Base{

    public static function viewPayment()
    {
        $idLogged = SessionAdminLogin::idLogged();
        $dbAccount = EntityAccount::getAccount([ 'id' => $idLogged])->fetchObject();
        $donateConfigs = EntityServerConfig::getInfoWebsite([ 'id' => 1])->fetchObject();

        $select_products = EntityServerConfig::getProducts(null, 'id ASC');
        $product_web_id = 192;
        while ($product = $select_products->fetchObject()) {
            $product_web_id++;
            $final_price = calculatePrice($product->coins);
            $arrayProducts[] = [
                'id' => $product->id,
                'coins' => $product->coins,
                'web_id' => $product_web_id,
                'final_price' => $final_price
            ];
        }

        $content = View::render('pages/shop/payment', [
            'email' => $dbAccount->email ?? null,
            'products' => $arrayProducts,
            'active_mercadopago' => $donateConfigs->mercadopago,
            'active_pagseguro' => $donateConfigs->pagseguro,
            'active_paypal' => $donateConfigs->paypal,
            'active_stripe' => $donateConfigs->stripe,
        ]);
        return parent::getBase('Webshop', $content, 'donate');
    }

    public static function viewPaymentData($request)
    {
        $idLogged = SessionAdminLogin::idLogged();
        $dbAccount = EntityAccount::getAccount([ 'id' => $idLogged])->fetchObject();
        $postVars = $request->getPostVars();

        if(!isset($postVars['payment_country'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!isset($postVars['payment_method'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!isset($postVars['payment_coins'])){
            $request->getRouter()->redirect('/payment');
        }


        $content = View::render('pages/shop/paymentdata', [
            'country' => $postVars['payment_country'],
            'coins' => $postVars['payment_coins'],
            'method' => $postVars['payment_method'],
            'email' => $dbAccount->email ?? null,
        ]);
        return parent::getBase('Webshop', $content, 'donate');
    }

    public static function viewPaymentConfirm($request)
    {
        $donateConfigs = EntityServerConfig::getInfoWebsite([ 'id' => 1])->fetchObject();
        $idLogged = SessionAdminLogin::idLogged();
        $postVars = $request->getPostVars();

        if(!isset($postVars['payment_email'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!filter_var($postVars['payment_email'], FILTER_VALIDATE_EMAIL)){
            $request->getRouter()->redirect('/payment');
        }

        $filter_coins = filter_var($postVars['payment_coins'], FILTER_SANITIZE_NUMBER_INT);
        $final_price = calculatePrice($filter_coins);

        $stripeClientSecret = null;
        $filter_method = filter_var($postVars['payment_method'], FILTER_SANITIZE_SPECIAL_CHARS);
        if ($filter_method == 'stripe') {
            if (!$final_price) {
                $request->getRouter()->redirect('/payment');
            }

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $final_price * 100,
                'currency' => 'usd',
                'description' => 'Purchase of ' . $filter_coins . ' Elysiera coins',
                'statement_descriptor' => $filter_coins . ' Elysiera coins',
                'receipt_email' => $dbAccount->email ?? null,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            $order = [
                'account_id' => $idLogged,
                'method' => 'stripe',
                'reference' => $paymentIntent->id,
                'total_coins' => $filter_coins,
                'final_price' => $final_price,
                'status' => 0,
                'date' => strtotime(date('Y-m-d h:i:s')),
            ];
            EntityPayments::insertPayment($order);

            $stripeClientSecret = $paymentIntent->client_secret;
        }
        
        $content = View::render('pages/shop/paymentconfirm', [
            'method' => $postVars['payment_method'],
            'coins' => $filter_coins,
            'country' => $postVars['payment_country'],
            'email' => $postVars['payment_email'],
            'price' => $final_price,
            'clientSecret' => $stripeClientSecret,
        ]);
        return parent::getBase('Webshop', $content, 'donate');
    }

    public static function viewPaymentSummary($request)
    {
        // if we have the reference GET param we can just render the summary
        if (isset($_GET['reference'])) {
            $payment = EntityPayments::getPayment('reference = "' . $_GET['reference'] . '"')->fetchObject();
            if (!$payment) {
                $request->getRouter()->redirect('/payment');
            }
            $content = View::render('pages/shop/paymentsummary', [
                'payment' => $payment,
                'coins' => $payment->total_coins,
            ]);
            return parent::getBase('Webshop', $content, 'donate');
        }

        $idLogged = SessionAdminLogin::idLogged();
        $dbAccount = EntityAccount::getAccount([ 'id' => $idLogged])->fetchObject();
        $donateConfigs = EntityServerConfig::getInfoWebsite([ 'id' => 1])->fetchObject();
        $postVars = $request->getPostVars();

        if(!isset($postVars['payment_coins'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!isset($postVars['payment_method'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!isset($postVars['payment_country'])){
            $request->getRouter()->redirect('/payment');
        }
        if(!isset($postVars['payment_email'])){
            $request->getRouter()->redirect('/payment');
        }

        if(!filter_var($postVars['payment_email'], FILTER_VALIDATE_EMAIL)){
            $request->getRouter()->redirect('/payment');
        }
        $filter_email = filter_var($postVars['payment_email'], FILTER_SANITIZE_EMAIL);

        $filter_method = filter_var($postVars['payment_method'], FILTER_SANITIZE_SPECIAL_CHARS);
        switch($filter_method)
        {
            case 'paypal':
                $url_method = 1;
                if ($donateConfigs->paypal == 0) {
                    $request->getRouter()->redirect('/payment');
                }
                break;
            case 'pagseguro':
                $url_method = 2;
                if ($donateConfigs->pagseguro == 0) {
                    $request->getRouter()->redirect('/payment');
                }
                break;
            case 'pix':
                $url_method = 3;
                break;
            case 'mercadopago':
                $url_method = 4;
                if ($donateConfigs->mercadopago == 0) {
                    $request->getRouter()->redirect('/payment');
                }
                break;
            case 'stripe':
                $url_method = 5;
                if ($donateConfigs->stripe == 0) {
                    $request->getRouter()->redirect('/payment');
                }
                break;
            default:
                $url_method = 0;
        }
        if($url_method == 0){
            $request->getRouter()->redirect('/payment');
        }

        if(!filter_var($postVars['payment_coins'], FILTER_VALIDATE_INT)){
            $request->getRouter()->redirect('/payment');
        }
        $filter_coins = filter_var($postVars['payment_coins'], FILTER_SANITIZE_NUMBER_INT);
        $price = calculatePrice($filter_coins);

        // METHOD PAGSEGURO
        if($url_method == 2) {
            $reference = uniqid();
            $checkout = [
                'reference' => $reference,
                'item' => [
                    'id' => '0001',
                    'title' => $filter_coins.' Coins',
                    'amount' => $final_price,
                    'quantity' => $filter_coins,
                ],
            ];
            $code_payment = ApiPagSeguro::createPaymentLightBox($checkout, $filter_email);
            $order = [
                'account_id' => $idLogged,
                'method' => 'pagseguro',
                'reference' => $reference,
                'total_coins' => $filter_coins,
                'final_price' => $price,
                'status' => 0,
                'date' => strtotime(date('Y-m-d h:i:s')),
            ];
            EntityPayments::insertPayment($order);
        }

        // METHOD PAYPAL
        if($url_method == 1) {
            $reference = uniqid();
            $checkout = [
                'reference' => $reference,
                'item' => [
                    'id' => '0001',
                    'title' => $filter_coins.' Coins',
                    'amount' => $final_price,
                    'quantity' => $filter_coins,
                ],
            ];
            $code_payment = ApiPayPal::createPayment($checkout, $filter_email);
            $order = [
                'account_id' => $idLogged,
                'method' => 'paypal',
                'reference' => $reference,
                'total_coins' => $filter_coins,
                'final_price' => $price,
                'status' => 0,
                'date' => strtotime(date('Y-m-d h:i:s')),
            ];
            EntityPayments::insertPayment($order);
        }

        // METHOD PIX
        if($url_method == 3) {}

        // METHOD MERCADO PAGO
        if($url_method == 4) {
            $reference = uniqid();
            $checkout = [
                'reference' => $reference,
                'item' => [
                    'id' => '0001',
                    'title' => $filter_coins.' Coins',
                    'amount' => $final_price,
                    'quantity' => $filter_coins,
                ],
            ];
            $code_payment = ApiMercadoPago::createPaymentSandbox($checkout, $filter_email);
            $order = [
                'account_id' => $idLogged,
                'method' => 'mercadopago',
                'reference' => $reference,
                'total_coins' => $filter_coins,
                'final_price' => $price,
                'status' => 0,
                'date' => strtotime(date('Y-m-d h:i:s')),
            ];
            EntityPayments::insertPayment($order);
        }

        $content = View::render('pages/shop/paymentsummary', [
            'email' => $filter_email,
            'method' => $url_method,
            'code_payment' => $code_payment ?? null,
            'coins' => $filter_coins,
        ]);
        return parent::getBase('Webshop', $content, 'donate');
    }

}
