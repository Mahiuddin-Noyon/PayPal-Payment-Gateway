<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use GrahamCampbell\ResultType\Success;
use Illuminate\Http\Request;
use Omnipay\Omnipay;

class PaymentController extends Controller
{
    private $gateway;
    public function __construct()
    {
        $this->gateway = Omnipay::create('PayPal_Rest');
        $this->gateway->setClientId(env('PAYPAL_CLIENT_ID'));
        $this->gateway->setSecret(env('PAYPAL_CLIENT_SECRET'));
        $this->gateway->setTestMode(true);
    }

    public function pay(Request $request)
    {
        try {
            $response = $this->gateway->purchase(array(

                'amount'    => $request->amount,
                'currency'  => env('PAYPAL_CURRENCY'),
                'returnUrl' => url('success'),
                'cancelUrl' => url('error'),

            ))->send();

            if ($response->isRedirect()) {
                $response->redirect();
            } else {
                $response->getMessage();
            }
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    public function success(Request $request)
    {
        if ($request->input('paymentId') && $request->input('PayerID')) {
            $transaction = $this->gateway->completePurchase(array(

                'payer_id'             => $request->input('PayerID'),
                'transactionReference' => $request->input('paymentId')

            ));
            $response =  $transaction->send();

            if ($response->isSuccessful()) {

                $transaction_data = $response->getData();

                $payment = new Payment();
                $payment->payment_id = $transaction_data['id'];
                $payment->payer_id = $transaction_data['payer']['payer_info']['payer_id'];
                $payment->payer_email = $transaction_data['payer']['payer_info']['email'];
                $payment->amount = $transaction_data['transactions']['0']['amount']['total'];
                $payment->currency = env('PAYPAL_CURRENCY');
                $payment->payment_status = $transaction_data['state'];

                $payment->save();

                return "Payment is successful. Transaction ID: ".$transaction_data['id'];
            }else{
                return $response->getMessage();
            }
            
        }else{
            return "Transaction Could not completed";
        }
    }

    public function error()
    {
        return "User declined the payment";
    }
}
