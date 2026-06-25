<?php

namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Str;
class PaymentController extends Controller
{
    public function generate_token(Request $request)
    {
        // Static username and password for validation
        $validUsername = "admin";
        $validPassword = "MKJHRQFAR";

        // Get username and password from headers
        $username = $request->header('username');
        $password = $request->header('password');

        // Check if headers are missing
        if (empty($username) || empty($password)) {
            return response()->json([
                'status' => 0,
                'message' => 'Username and password are required in headers.',
            ], 400);
        }

        // Validate credentials against static values
        if ($username !== $validUsername || $password !== $validPassword) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid credentials.',
            ], 401);
        }


    
        // DB::table('api_tokens')->delete();
        // Generate a random token
        $randomString = Str::random(1048) . microtime() . uniqid();
        $token = base64_encode($randomString);

        // Store the token in the database with expiration time
        DB::table('api_tokens')->insert([
            'token' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addHour(),
            'created_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Token generated successfully',
            'token' => $token
        ]);
    }

    public function create_offline_order(Request $request)
    {
        
        $product_id = $request->product_id;

        if(empty($request->product_id) || empty($request->email) || empty($request->first_name)|| empty($request->country) || 
        empty($request->last_name) || empty($request->phone) || empty($request->address) || empty($request->city_name) || 
        empty($request->state_name) || empty($request->zip_code) || empty($request->start_url) || empty($request->apple_pay_token))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details before submitting.",
            ], 400);
        }
        // $product_data = DB::table('product')->where('uniq_id', $request->product_id)->first();
        $product_data = DB::table('product')->where('uniq_id', $product_id)->first();
        if (!$product_data) {
            return response()->json([
                'status' => 0,
                'message' => 'Product not found.',
            ], 200);
        }
        $bm = 3;
            // Check if product_description has 'BM:' and extract number after it
        if (preg_match('/BM:(\d+)/', $product_data->product_description, $matches)) {
            $bm = (int) $matches[1];
        }
        $campaign_data = DB::table('campaign')->where('campaign_id', $product_data->campaign_id)->first();
        $event_data = DB::table('send_mail_events')->where('email_type','=','order_confirm')->first();
        $pro_id = $product_data->sticky_product_id;
        // if ($paymentToken === null) {



            $paymentMethod = null;
            $appleToken = null;
            $googleToken = null;

            if ($request->apple_pay_token) {
                $paymentMethod = "applepay";
                $appleToken = $request->apple_pay_token;
            } elseif ($request->google_pay_token) {
                $paymentMethod = "googlePay";
                $googleToken = $request->google_pay_token;
            } else {
                $paymentMethod = $request->creditCardType ?? "Discover";
            }

            $patientData = [
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "currency" => "USD",
                "billingFirstName" => $request->billingFirstName,
                "billingLastName" => $request->billingLastName,
                "billingAddress1" => $request->billingAddress,
                "billingCity" => $request->billingCity,
                "billingState" => $request->billingState,
                "billingZip" => $request->billingZip,
                "billingCountry" => $request->billingCountry,
                'phone' => $request->phone,
                'email' => $request->email,
                "creditCardType" => $paymentMethod,
                'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'campaignId' => $product_data->campaign_id,
                // 'billingSameAsShipping' => "YES",
                'shippingAddress1' => $request->address,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_name,
                'shippingZip' => $request->zip_code,
                'shippingCountry' => $request->country ?? 'US',
                'preserve_force_gateway' => 1,
                "AllowDuplicateSignup" => 1,
                'promoCode' => $request->promo_codes,
                'billingSameAsShipping' => $request->billingSameAsShipping,

                'AFFID' => $request->AFFID,
                'C1' => $request->C1,
                'C2' => $request->C2,
                'C3' => $request->C3,

                "offers" => [
                    [
                        "offer_id" => $campaign_data->offer_id,
                        "product_id" => $product_data->sticky_product_id,
                        "billing_model_id" => $bm,
                        "quantity" => 1,
                    ]
                    ],
                    "custom_fields" => [
                                [
                                    "id" => 2,
                                    "field_name" => 'start_url',
                                    "value" => $request->start_url
				],
                                [
                                    "id" => 19,
                                    "field_name" => 'offline_transaction_id',
                                    "value" => !empty($request->apple_pay_token) ? $request->apple_pay_token : ""
                                ]
                  ]
            ];



            if (empty($request->apple_pay_token) && empty($request->google_pay_token)) {
                    $patientData["creditCardType"] = "Discover";
                    $patientData["creditCardNumber"] = $request->card_no;
                $patientData["expirationDate"] = $request->ex_month . $request->ex_year;
                $patientData["CVV"] = $request->cvv_no;
            }else{
            $patientData["creditCardType"] = "offline";
            //      $patientData["stripe_token"] =
            //  $request->apple_pay_token
                //?? $request->google_pay_token
            // ?? '';

            }
              // Check if order already exists
            //   dd($product_data);
              $exists = DB::table('orders')
                  ->where('sticky_product_id', $product_data->sticky_product_id)
                //   ->where('user_id', $user_id)
                  ->exists();

              $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
                  $site_key    = $site_data->site_key;
              $site_secret = $site_data->site_secret;


              $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
                  DB::table('log_data')->insert([
                    'email' => $request->email,
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($responseData['error_found'] == "1") {
                    return response()->json([
                        'status' => 5,
			'message' => $responseData['decline_reason'] ?? $responseData['error_message'] ?? 'Declined',
			// 'message' => $responseData['decline_reason'],
			'order_id' => $responseData['order_id'] ?? null // Include order_id in the response
			// 'order_id' => $responseData['order_id'] // Include order_id in the response
                    ]);
                  }
              if ($response->successful()) {


                  $responseData = $response->json();

                  // Save the order
                  $order = new Order();
                  $order->gateway_id = $responseData['gateway_id'];

                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $responseData['transactionID'];
                  $order->customerId = $responseData['customerId'];
                  $order->authId = "DUMMY";
                  $order->orderTotal = $responseData['orderTotal'];
                  $order->orderSalesTaxPercent = $responseData['orderSalesTaxPercent'];
                  $order->orderSalesTaxAmount = $responseData['orderSalesTaxAmount'];
                  $order->test = $responseData['test'];
                  $order->prepaid_match = $responseData['prepaid_match'];
                  $order->resp_msg = $responseData['resp_msg'] ?? null;
                  $order->next_billing_date = Carbon::now()->addMonth();
                  $order->save();

                  // Save line items
                  foreach ($responseData['line_items'] as $item) {
                      $lineItem = new OrderLineItem();
                      $lineItem->order_id = $order->id;
                      $lineItem->product_id = $item['product_id'];
                      $lineItem->variant_id = $item['variant_id'];
                      $lineItem->quantity = $item['quantity'];
                      $lineItem->subscription_id = $item['subscription_id'];
                      $lineItem->save();
                  }

                  // Save subscriptions
                  foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                      $subscription = new Subscription();
                      $subscription->order_id = $order->id;
                      $subscription->product_id = $productId;
                      $subscription->subscription_id = $subscriptionId;
                      $subscription->save();
                  }
                  $customerId = $responseData['customerId'] ?? null;
                  $email = $request->email;
                  $this->memberCreate($customerId,$email);
                  return response()->json([
                    'status' => 1,
                    'message' => 'Success',
                    'order_id' => $responseData['order_id'] // Include order_id in the response
                ]);
              } else {
                  return response()->json(['status' => 'error', 'message' => $response->json()], $response->status());
              }
            //   return response()->json(['status' => 'success', 
            //   'message' => 'Orders processed successfully
            //   ']);
    }

    private function memberCreate($customer_id, $email)
    {
         
  
          $app_key_data = DB::table('app_keys')->first();
      
          $app_key    = $app_key_data->app_key;
          $app_secret = $app_key_data->app_secret;
  
          $event_data = DB::table('send_mail_events')->where('email_type','=','create_member')->first();
  
          $payload = [
              "customer_id" => $customer_id,
              "email" => $email,
              "event_id" => $event_data->event_id,
          ];
      
          $response = Http::withBasicAuth($app_key, $app_secret)
                          ->post('https://whitelabelmd.sticky.io/api/v1/member_create', $payload);
      
          if ($response->successful()) {
              $responseData = $response->json();
      
              if (isset($responseData['response_code']) && $responseData['response_code'] === "100") {
           
      
                  return response()->json([
                      'status' => 1,
                      'message' => 'Member created successfully.',
                      'data' => $responseData
                  ]);
              } else {
                  return response()->json([
                      'status' => 0,
                      'message' => 'Failed to create member.',
                      'data' => $responseData
                  ]);
              }
          } else {
              return response()->json([
                  'status' => 0,
                  'message' => 'Failed to create member. Server error.',
                  'error' => $response->json()
              ], $response->status());
          }
    }

    

}
