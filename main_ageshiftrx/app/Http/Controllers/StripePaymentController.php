<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class StripePaymentController extends Controller
{


public function storePaymentIntent(Request $request)
{

    DB::table('stripe_payment_intents')->insert([
        'email' => $request->email,
        'payment_intent_id' => $request->payment_intent_id,
        'amount' => $request->amount,
        'currency' => 'USD',
        'created_at' => now(),
        'updated_at' => now()
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Payment intent stored'
    ]);
}


public function stripeWebhook(Request $request)
{

    $payload = $request->getContent();
    $data = json_decode($payload,true);

    $paymentIntent = $data['data']['object']['id'] ?? null;
    $status = $data['data']['object']['status'] ?? null;

    $email = $data['data']['object']['metadata']['customer_email'] 
          ?? $data['data']['object']['receipt_email'] 
          ?? null;

    $paymentMethod = $data['data']['object']['payment_method_types'][0] ?? null;

    /* SAVE WEBHOOK JSON */

  $webhookId =     DB::table('stripe_webhook_logs')->insert([
        'payload' => $payload,
	'email' => $email,    
	'created_at' => now()
    ]);

    $orderId = null;

    /* CALL EXTERNAL API USING EMAIL */

    if($email){

        $apiUrl = "https://panel.ravinimavat.com/ageshiftrx/api/get_user_conform_cart_product";

        $postData = json_encode([
            "email" => $email
        ]);

        $ch = curl_init($apiUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $apiData = json_decode($response,true);

        if(isset($apiData['product'])){

            foreach($apiData['product'] as $product){

                if($product['offline_transaction_id'] == $paymentIntent){

                    $orderId = $product['order_id'];
                    break;
                }

            }

        }

    }

    /* UPDATE PAYMENT TABLE */

    DB::table('stripe_payment_intents')
        ->where('payment_intent_id',$paymentIntent)
        ->update([
            'payment_status'=>$status,
            'payment_method'=>$paymentMethod,
            'order_id'=>$orderId,
            'webhook_payload'=>$payload,
            'updated_at'=>now()
        ]);

     if($orderId){
        DB::table('stripe_webhook_logs')
            ->where('id',$webhookId)
            ->update([
                'order_id'=>$orderId
            ]);
    }

    return response()->json([
        'status'=>true,
        'message'=>"Webhook received"
    ]);
}

public function stripeWebhook23723(Request $request)
{

    $payload = $request->getContent();
    $data = json_decode($payload, true);

    $paymentIntent = $data['data']['object']['id'] ?? null;
    $status = $data['data']['object']['status'] ?? null;

    // payment method (success case)
    $paymentMethod = $data['data']['object']['payment_method'] ?? null;

    // payment method type (klarna/card/etc)
    $paymentMethodType = $data['data']['object']['payment_method_types'][0] ?? null;

    // failure message
    $failureMessage = $data['data']['object']['last_payment_error']['message'] ?? null;

    /* SAVE FULL WEBHOOK JSON */

    DB::table('stripe_webhook_logs')->insert([
        'payload' => $payload,
        'created_at' => now()
    ]);

    /* UPDATE PAYMENT INTENT TABLE */

    DB::table('stripe_payment_intents')
        ->where('payment_intent_id', $paymentIntent)
        ->update([
            'payment_status' => $status,
            'payment_method' => $paymentMethodType,
            'webhook_payload' => $payload,
            'updated_at' => now()
        ]);

    return response()->json([
        'status' => true,
        'message' => "Webhook received"
    ]);
}

public function stripeWebhook237723(Request $request)
{

    $payload = $request->getContent();
    $data = json_decode($payload,true);

    $paymentIntent = $data['data']['object']['id'] ?? null;
    $status = $data['data']['object']['status'] ?? null;

    $paymentMethod = $data['data']['object']['payment_method_types'][0] ?? null;

    /* SAVE FULL WEBHOOK JSON */

    DB::table('stripe_webhook_logs')->insert([
        'payload' => $payload,
        'created_at' => now()
    ]);

    /* UPDATE PAYMENT TABLE */

    DB::table('stripe_payment_intents')
        ->where('payment_intent_id',$paymentIntent)
        ->update([
            'payment_status'=>$status,
            'payment_method'=>$paymentMethod,
            'webhook_payload'=>$payload,
            'updated_at'=>now()
        ]);

    return response()->json([
        'status'=>true,
        'message'=>"Webhook received"
    ]);
}

public function stripeWebhook2323(Request $request)
{

    $payload = $request->getContent();
    $data = json_decode($payload,true);

    $paymentIntent = $data['data']['object']['id'] ?? null;
    $status = $data['data']['object']['status'] ?? null;

    $paymentMethod = $data['data']['object']['payment_method_types'][0] ?? null;

    DB::table('stripe_payment_intents')
        ->where('payment_intent_id',$paymentIntent)
        ->update([
            'payment_status'=>$status,
            'payment_method'=>$paymentMethod,
            'webhook_payload'=>$payload,
            'updated_at'=>now()
        ]);

    return response()->json([
        'status'=>true,
        'message'=>"Webhook received"
    ]);
}

private $stripeSecret;
	private $apiKey;

	 public function __construct()
    {
	     $this->apiKey = env('AGESHIFT_API_KEY');
		    $this->stripeSecret = env('STRIPE_SECRET_KEY');
    }

	private function validateApiKey(Request $request)
    {
        $clientKey = $request->header('api-key');

        if (!$clientKey || $clientKey !== $this->apiKey) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized API access'
            ], 401);
        }

        return null;
    }



    public function checkPaymentStatus(Request $request)
    {

	     if ($error = $this->validateApiKey($request)) {
            return $error;
        }

	    $request->validate([
            'order_id' => 'required|string'
	    ]);

	     $orderId = $request->order_id;

$order = DB::table('orders')
    ->where('order_id', $orderId)
    ->first();

if (!$order || empty($order->transactionID)) {
    return response()->json([
        'status' => false,
        'message' => 'Payment intent not found for this order'
    ], 404);
}

$piId = $order->transactionID;

       // $piId = $request->payment_intent_id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents/$piId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->stripeSecret . ":");
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid PaymentIntent ID',
                'stripe_response' => $data
            ], 400);
        }

        return response()->json([
            'status' => true,
            'payment_intent_id' => $data['id'],
            'payment_status' => $data['status'],
            'amount' => $data['amount'] / 100,
            'currency' => strtoupper($data['currency']),
            'charge_id' => $data['charges']['data'][0]['id'] ?? null,
            'raw_response' => $data
        ]);
    }

    /**
     * 💰 Refund Payment
     */



    private function OrderRefund($order_id, $amount)
    {
        if (!empty($order_id) && !empty($amount)) {

            $app_key_data = DB::table('app_keys')->first();

            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // Correct payload
            $payload = [
                "order_id"       => $order_id,
                "amount"         => $amount,
                "keep_recurring" => 1
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_refund', $payload);

            $responseData = $response->json();

            if ($response->successful()) {
                return true;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }



    public function refundPayment(Request $request)
{
    // 🔐 API KEY CHECK
    if ($error = $this->validateApiKey($request)) {
        return $error;
    }

    $request->validate([
        'order_id' => 'required|string',
        'amount' => 'nullable|numeric' // in dollars
    ]);

       $request->validate([
            'order_id' => 'required|string'
            ]);

             $orderId = $request->order_id;

$order = DB::table('orders')
    ->where('order_id', $orderId)
    ->first();

if (!$order || empty($order->transactionID)) {
    return response()->json([
        'status' => false,
        'message' => 'Payment intent not found for this order'
    ], 404);
}

$piId = $order->transactionID;

    $payload = [
        'payment_intent' => $piId
    ];

    // Partial refund
    if ($request->amount) {
        $payload['amount'] = $request->amount * 100;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/refunds");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_USERPWD, $this->stripeSecret . ":");
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['id'])) {
        return response()->json([
            'status' => false,
            'message' => 'Refund failed',
            'stripe_response' => $data
        ], 400);
    }else{
$this->OrderRefund($orderId,$request->amount);
    }

    return response()->json([
        'status' => true,
        'refund_id' => $data['id'],
        'refund_status' => $data['status'],
        'amount_refunded' => $data['amount'] / 100,
        'currency' => strtoupper($data['currency']),
        'payment_intent_id' => $data['payment_intent'],
        'raw_response' => $data
    ]);
}


    public function refundPayment_old(Request $request)
    {
        if ($error = $this->validateApiKey($request)) {
            return $error;
        }

        $request->validate([
            'charge_id' => 'required|string',
            'amount' => 'nullable|numeric'
        ]);

        $payload = [
            'charge' => $request->charge_id
        ];

        if ($request->amount) {
            $payload['amount'] = $request->amount * 100;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/refunds");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_USERPWD, $this->stripeSecret . ":");
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return response()->json([
            'status' => true,
            'refund_id' => $data['id'] ?? null,
            'refund_status' => $data['status'] ?? null,
            'amount_refunded' => isset($data['amount']) ? $data['amount'] / 100 : null,
            'currency' => $data['currency'] ?? null,
            'raw_response' => $data
        ]);
    }

	public function createPaymentIntent(Request $request)
    {
        $secretKey = env('STRIPE_SECRET_KEY');

        $amount = $request->input('amount'); // amount in smallest currency unit (e.g., cents)
        $currency = $request->input('currency', 'usd');

        // Prepare data for PaymentIntent creation based on Stripe API docs:
        $postData = [
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        // you can use CURLOPT_POSTFIELDS with array to auto-form encode
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_USERPWD, $secretKey . ":");

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return response()->json(['error' => curl_error($ch)], 500);
        }
        curl_close($ch);

        $responseObj = json_decode($result, true);

        if (isset($responseObj['error'])) {
            return response()->json(['error' => $responseObj['error']['message']], 400);
        }

        return response()->json([
            'clientSecret' => $responseObj['client_secret'],
            'paymentIntentId' => $responseObj['id'],
        ]);
    }
}

