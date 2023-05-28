<?php

use App\Http\Response;

use App\Controller\Pages\Payment;
use App\Controller\Pages\PremiumFeatures;
use App\Payment\MercadoPago\NotifyMercadoPago;
use App\Payment\PagSeguro\NotifyPagSeguro;
use App\Payment\PayPal\NotifyPayPal;
use App\Payment\Stripe\NotifyStripe;

$obRouter->get('/payment', [
    'middlewares' => [
        'required-login'
    ],
    function($request){
        return new Response(200, Payment::viewPayment($request));
    }
]);
$obRouter->post('/payment/data', [
    'middlewares' => [
        'required-login'
    ],
    function($request){
        return new Response(200, Payment::viewPaymentData($request));
    }
]);
$obRouter->post('/payment/confirm', [
    'middlewares' => [
        'required-login'
    ],
    function($request){
        return new Response(200, Payment::viewPaymentConfirm($request));
    }
]);
$obRouter->get('/payment/summary', [
    'middlewares' => [
        'required-login'
    ],
    function($request){
        return new Response(200, Payment::viewPaymentSummary($request));
    }
]);
$obRouter->post('/payment/summary', [
    'middlewares' => [
        'required-login'
    ],
    function($request){
        return new Response(200, Payment::viewPaymentSummary($request));
    }
]);

$obRouter->get('/payment/mercadopago/return', [
    function($request){
        return new Response(200, NotifyMercadoPago::ReturnMercadoPago($request));
    }
]);
$obRouter->post('/payment/mercadopago/return', [
    function($request){
        return new Response(200, NotifyMercadoPago::ReturnMercadoPago($request));
    }
]);

$obRouter->get('/payment/pagseguro/return', [
    function($request){
        return new Response(200, NotifyPagSeguro::ReturnPagSeguro($request));
    }
]);
$obRouter->post('/payment/pagseguro/return', [
    function($request){
        return new Response(200, NotifyPagSeguro::ReturnPagSeguro($request));
    }
]);

$obRouter->get('/payment/paypal/return', [
    function($request){
        return new Response(200, NotifyPayPal::ReturnPayPal($request));
    }
]);
$obRouter->post('/payment/paypal/return', [
    function($request){
        return new Response(200, NotifyPayPal::ReturnPayPal($request));
    }
]);
$obRouter->get('/payment/stripe/return', [
    function($request){
        return new Response(200, NotifyStripe::ReturnStripe($request));
    }
]);

$obRouter->get('/premiumfeatures', [
    function($request){
        return new Response(200, PremiumFeatures::viewPremiumFeatures($request));
    }
]);