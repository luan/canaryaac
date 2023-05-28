<?php
/**
 * NotifyStripe Class
 *
 * @package   CanaryAAC
 * @author    Lucas Giovanni <lucasgiovannidesigner@gmail.com>
 * @copyright 2022 CanaryAAC
 */

namespace App\Payment\Stripe;

use App\Model\Entity\Account as EntityAccount;
use App\Model\Entity\Payments as EntityPayments;

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

class NotifyStripe {

    public static function ReturnStripe($request)
    {
        $paymentIntentId = filter_input(INPUT_GET, 'payment_intent', FILTER_SANITIZE_SPECIAL_CHARS);

        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

        if($paymentIntent->status == 'succeeded') {
            $dbPayment = EntityPayments::getPayment('reference = "'.$paymentIntent->id.'"')->fetchObject();
            if (!$dbPayment || $dbPayment->status != 0) {
                $request->getRouter()->redirect('/payment');
            }

            $dbAccount = EntityAccount::getAccount('id = "'.$dbPayment->account_id.'"')->fetchObject();

            EntityPayments::updatePayment('reference = "'.$paymentIntent->id.'"', [
                'status' => 4,
            ]);
            EntityAccount::addCoins('id = "'.$dbPayment->account_id.'"', $dbPayment->total_coins);

            $request->getRouter()->redirect('/payment/summary?reference='.$paymentIntent->id);
        }
        return $arrayResponse;
    }
}