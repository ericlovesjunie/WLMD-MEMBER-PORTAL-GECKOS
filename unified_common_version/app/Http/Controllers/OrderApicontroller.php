<?php

namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\Subscription;
use Carbon;
class OrderApicontroller extends Controller
{

    public function createOrder_recurring_offline_api(Request $request)
    {

        if(empty($request->product_id) || empty($request->email) || empty($request->first_name) || empty($request->last_name) || empty($request->phone) || empty($request->address) || empty($request->city_name) || empty($request->state_name) || empty($request->zip_code) || 
        empty($request->start_url) || empty($request->payment_token) || empty($request->parent_order))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details before submitting.",
            ], 400);
        }
        $product_id = $request->product_id;
        if (empty($product_id)) {
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

        $paymentMethod = "offline";
        $paymentToken = $request->payment_token;
      
        $offer_id = $product_data->offer_id;

        $email_1 = $this->member_view($request->email);
            if ($email_1 == 0) 
            {
                if ($product_data->is_price == 1) 
                {
                  $price = $product_data->custom_price;
                }
                else
                {
                  $price = $product_data->product_price;
                }
            }
            else
            {
                $price = $product_data->product_price;
            }

        
      
        
            $patientData = [
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "currency" => "USD",
                "billingAddress1" => $request->billing_address,
                "billingAddress2" => $request->billing_address2,
                "billingCity" => $request->billing_city_name,
                "billingState" => $request->billing_state_name,
                "billingZip" => $request->billing_zip_code,
                "billingCountry" => $request->billing_Country ?? 'US',
                "address2" => $request->address2,
                'phone' => $request->phone,
                'email' => $request->email,
                'creditCardType' => $paymentMethod,
                'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'campaignId' => $product_data->campaign_id,
                'shippingAddress1' => $request->address,
                'shippingAddress2' => $request->address2,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_name,
                'shippingZip' => $request->zip_code,
                'shippingCountry' => $request->country ?? 'US',
                'preserve_force_gateway' => 1,
                "AllowDuplicateSignup" => 1,
                'promoCode' => $request->promo_codes,
                'billingSameAsShipping' => $request->billingSameAsShipping,
                
                "offers" => [
                    [
                        "offer_id" => $offer_id,
                        "product_id" => $pro_id,
                        "billing_model_id" => $bm,
                        "price" => $price,
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
                                    "value" => !empty($paymentToken) ? $paymentToken : ""
                                ],
                                [
                                    "id" => 20,
                                    "field_name" => 'parent_order',
                                    "value" => !empty($request->parent_order) ? $request->parent_order : ""
                                ]
                  ]
            ];
        
            // dd($patientData);
        

              $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
              $site_key    = $site_data->site_key;
              $site_secret = $site_data->site_secret;
              
              $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
               
                //   dd($responseData);
                  if ($responseData['error_found'] == "1") {
                    return response()->json([
                        'status' => 5,
                        'message' => $responseData['decline_reason'] ?? $responseData['error_message'] ?? 'Declined',
                        'order_id' => $responseData['order_id'] ?? null // Include order_id in the response
                    ]);
                  }
           
                    $payment_received = 1;
                    $this->update_order_payment_received($responseData['order_id'],$payment_received);
                
              if ($response->successful()) {
               
      
                  $responseData = $response->json();
      
                  // Save the order
                  $order = new Order();
                  $order->gateway_id = null;
            
                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $responseData['transactionID'];
                  $order->customerId = $responseData['customerId'];
                  $order->authId = null;
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

    public function createOrder_offline_api(Request $request)
    {

        if(empty($request->product_id) || empty($request->email) || empty($request->first_name) || empty($request->last_name) || empty($request->phone) || empty($request->address) || empty($request->city_name) || empty($request->state_name) || empty($request->zip_code) || 
        empty($request->start_url) || empty($request->payment_token))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details before submitting.",
            ], 400);
        }
        $product_id = $request->product_id;
        if (empty($product_id)) {
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

        $paymentMethod = "offline";
        $paymentToken = $request->payment_token;
      
        $offer_id = $product_data->offer_id;

        $email_1 = $this->member_view($request->email);
            if ($email_1 == 0) 
            {
                if ($product_data->is_price == 1) 
                {
                  $price = $product_data->custom_price;
                }
                else
                {
                  $price = $product_data->product_price;
                }
            }
            else
            {
                $price = $product_data->product_price;
            }

        
      
        
            $patientData = [
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "currency" => "USD",
                "billingAddress1" => $request->billing_address,
                "billingAddress2" => $request->billing_address2,
                "billingCity" => $request->billing_city_name,
                "billingState" => $request->billing_state_name,
                "billingZip" => $request->billing_zip_code,
                "billingCountry" => $request->billing_Country ?? 'US',
                "address2" => $request->address2,
                'phone' => $request->phone,
                'email' => $request->email,
                'creditCardType' => $paymentMethod,
                'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'campaignId' => $product_data->campaign_id,
                'shippingAddress1' => $request->address,
                'shippingAddress2' => $request->address2,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_name,
                'shippingZip' => $request->zip_code,
                'shippingCountry' => $request->country ?? 'US',
                'preserve_force_gateway' => 1,
                "AllowDuplicateSignup" => 1,
                'promoCode' => $request->promo_codes,
                'billingSameAsShipping' => $request->billingSameAsShipping,
                
                "offers" => [
                    [
                        "offer_id" => $offer_id,
                        "product_id" => $pro_id,
                        "billing_model_id" => $bm,
                        "price" => $price,
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
                                    "value" => !empty($paymentToken) ? $paymentToken : ""
                                ]
                  ]
            ];
        
            // dd($patientData);
        

              $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
              $site_key    = $site_data->site_key;
              $site_secret = $site_data->site_secret;
              
              $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
               
                //   dd($responseData);
                  if ($responseData['error_found'] == "1") {
                    return response()->json([
                        'status' => 5,
                        'message' => $responseData['decline_reason'] ?? $responseData['error_message'] ?? 'Declined',
                        'order_id' => $responseData['order_id'] ?? null // Include order_id in the response
                    ]);
                  }
           
                    $payment_received = 1;
                    $this->update_order_payment_received($responseData['order_id'],$payment_received);
                
              if ($response->successful()) {
               
      
                  $responseData = $response->json();
      
                  // Save the order
                  $order = new Order();
                  $order->gateway_id = null;
            
                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $responseData['transactionID'];
                  $order->customerId = $responseData['customerId'];
                  $order->authId = null;
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

    private function update_order_payment_received($order_id,$payment_received)
    {

                $app_key_data = DB::table('app_keys')->first();

                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;

                // Prepare the data for the API request
                $payload = [
                    "order_id" => [
                        $order_id => [
                           'payment_received' => $payment_received
                        ]
                    ]
                ];

                // Make the API request
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);

                // Check if the response is successful
                if ($response->successful()) {
                    return true;
                } else {
                    return false;
                }
    }
    public function createOrder_api(Request $request)
    {

        if(empty($request->product_id) || empty($request->email) || empty($request->first_name) || empty($request->last_name) || empty($request->phone) || empty($request->address) || empty($request->city_name) || empty($request->state_name) || empty($request->zip_code) || empty($request->card_no) || empty($request->ex_month) || empty($request->ex_year) || empty($request->cvv_no) || empty($request->card_holder_name) || empty($request->start_url))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details before submitting.",
            ], 400);
        }
        $product_id = $request->product_id;
        if (empty($product_id)) {
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
      
        $offer_id = $product_data->offer_id;

        $email_1 = $this->member_view($request->email);
        if ($email_1 == 0) 
        {
            if ($product_data->is_price == 1) 
            {
              $price = $product_data->custom_price;
            }
            else
            {
              $price = $product_data->product_price;
            }
        }
        else
        {
            $price = $product_data->product_price;
        }
      
        // if ($paymentToken === null) {
            $patientData = [
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "currency" => "USD",
                // "billingFirstName" => $request->billing_FirstName,
                // "billingLastName" => $request->billing_LastName,
                "billingAddress1" => $request->billing_address,
                "billingAddress2" => $request->billing_address2,
                "billingCity" => $request->billing_city_name,
                "billingState" => $request->billing_state_name,
                "billingZip" => $request->billing_zip_code,
                "billingCountry" => $request->billing_Country ?? 'US',
                "address2" => $request->address2,
                'phone' => $request->phone,
                'email' => $request->email,
                'creditCardType' => "Discover",
                'creditCardNumber' => $request->card_no,
                'expirationDate' => $request->ex_month . $request->ex_year,
                'CVV' => $request->cvv_no,
                'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'campaignId' => $product_data->campaign_id,
                // 'billingSameAsShipping' => "YES",
                'shippingAddress1' => $request->address,
                'shippingAddress2' => $request->address2,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_name,
                'shippingZip' => $request->zip_code,
                'shippingCountry' => $request->country ?? 'US',
                'preserve_force_gateway' => 1,
                "AllowDuplicateSignup" => 1,
                'promoCode' => $request->promo_codes,
                'billingSameAsShipping' => $request->billingSameAsShipping,
                
                "offers" => [
                    [
                        "offer_id" => $offer_id,
                        "product_id" => $pro_id,
                        "billing_model_id" => $bm,
                        "price" => $price,
                        "quantity" => 1,
                    ]
                    ],
                    "custom_fields" => [
                                [
                                    "id" => 2,
                                    "field_name" => 'start_url',
                                    "value" => $request->start_url
                                ]
                  ]
            ];
        
            // dd($patientData);
        

              $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
              $site_key    = $site_data->site_key;
              $site_secret = $site_data->site_secret;
              
              $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
                //   dd($responseData);
                  if ($responseData['error_found'] == "1") {
                    return response()->json([
                        'status' => 5,
                        'message' => $responseData['decline_reason'],
                        'order_id' => $responseData['order_id'] // Include order_id in the response
                    ]);
                  }
              if ($response->successful()) {
               
      
                  $responseData = $response->json();
      
                  // Save the order
                  $order = new Order();
                  $order->gateway_id = null;
            
                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $responseData['transactionID'];
                  $order->customerId = $responseData['customerId'];
                  $order->authId = null;
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
            // "event_id" => 155,
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

    private function member_view($email)
    {
        $site_data = DB::table('site')->first();
        $app_key_data = DB::table('app_keys')->first();
    
        if (!$site_data || !$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API keys or site data not found.',
            ], 500);
        }
    
        // $email = $request->email;
    
        if (!$email) {
            return response()->json([
                'status' => 0,
                'message' => 'Email is required.',
            ], 400);
        }
    
        $app_key = $app_key_data->app_key;
        $api_ext = $app_key_data->app_secret;
    
        $payload = [
            "email" => $email,
        ];
    
        $response = Http::withBasicAuth($app_key, $api_ext)
            ->post('https://whitelabelmd.sticky.io/api/v1/member_view', $payload);
    
        $data = $response->json();
    
        // Check if response is successful
        if ($response->successful()) {
            
            if($data['response_code'] == 100)
            {
                $patientId = 1;
            }
            else
            {
                $patientId = 0;
            }
            return $patientId;
        } 
         else 
         {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get member data.',
                'error' => $data,
            ], $response->status());
        }
    }


    public function get_products_api(Request $request)
    {
        $table_data = DB::table('product')->where('status',1)->orderBy('created_at', 'desc')->get();

        if(count($table_data) != 0)
        {
            $product = array();

            foreach($table_data as $data)
            {
                $product_img = array();

                $product_image = DB::table('product_image')->where('product_id',$data->product_id)->get();

                if(count($product_image) != 0)
                {
                    foreach($product_image as $iv_img)
                    {
                        $product_img[] = array(
                            'img_video'         => URL("/public/assets/product_img/" . $iv_img->img_video),
                        );
                    }
                  
                }

                $product[] = array(
                    'product_id'                  => $data->uniq_id,
                    'uniq_id'                     => $data->uniq_id,
                    'product_name'                => $data->product_name,
                    'product_description'         => $data->product_description,
                    'product_price'               => $data->product_price,
                    'form_url'                    => $data->form_url,
                    'form_id'                     => $data->form_id,
                    'product_sku'                 => $data->product_sku,
                    'product_category_name'       => $data->product_category_name,
                    'vertical_name'               => $data->vertical_name,
                    'product_is_trial'            => $data->product_is_trial,
                    'product_is_shippable'        => $data->product_is_shippable,
                    'product_rebill_product'      => $data->product_rebill_product,
                    'product_rebill_days'         => $data->product_rebill_days,
                    'product_max_quantity'        => $data->product_max_quantity,
                    'preserve_recurring_quantity' => $data->preserve_recurring_quantity,
                    'subscription_type'           => $data->subscription_type,
                    'subscription_week'           => $data->subscription_week,
                    'subscription_day'            => $data->subscription_day,
                    'cost_of_goods_sold'          => $data->cost_of_goods_sold,
                    'taxable'                     => $data->taxable,
                    'product_img'                 => $product_img,
                );
            }

            $result['product']          = $product;
            $result['status']           = 1;
            $result['message']          = "Great! You have Successfully get all record.";
        }
        else
        {
            $result['status']  = 0;
            $result['message'] = "Somting With Wrong";
        }

        return response()->json($result);
    }

    public function member_view_new_api(Request $request)
    {
        $email      = $request->email;
        $product_id = $request->product_id;

        if(empty($email) || empty($product_id))
        {
            return response()->json([
                'status' => 0,
                'message' => 'Email and product ID are required.',
            ], 400);
        }
      


        $is_db = DB::table('product')->where('uniq_id', $product_id)->first();

        if ($is_db) {
            $site_data = DB::table('site')->first();
            $app_key_data = DB::table('app_keys')->first();

            if (!$site_data || !$app_key_data) {
                return response()->json([
                    'status' => 0,
                    'message' => 'API keys or site data not found.',
                ], 500);
            }

            if (!$email) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Email is required.',
                ], 400);
            }

            $app_key = $app_key_data->app_key;
            $api_ext = $app_key_data->app_secret;

            $payload = [
                "email" => $email,
            ];

            $response = Http::withBasicAuth($app_key, $api_ext)
                ->post('https://whitelabelmd.sticky.io/api/v1/member_view', $payload);

            $data = $response->json();

            if ($response->successful()) {
                $patientId = ($data['response_code'] == 100) ? 1 : 0;

                // $m_price = $is_db->custom_price; // Default

                if ($is_db->is_price == 1) {
                     if ($patientId == 0) 
                     {
                        $m_price = $is_db->custom_price;
              
                    }
                    else 
                    {
                        $m_price = $is_db->product_price;     
                    }
                }
                 else {
                    $m_price = $is_db->product_price;
                }
               

                return response()->json([
                    'status' => 1,
                    'message' => 'Successfully retrieved data.',
                    'data' => $patientId,
                    'm_price' => $m_price,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to get member data.',
                    'error' => $data,
                ], $response->status());
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Record not found!',
            ]);
        }
    } 

    public function check_coupons_v2(Request $request)
    {
    

        $promo = $request->promo_code;
        $product_id = $request->product_id;

        if(empty($promo) || empty($product_id))
        {
            return response()->json([
                'status' => 0,
                'message' => 'Promo code and product ID are required.',
            ], 400);
        }

        // 2️⃣ Get product
        $product = DB::table('product')
            ->where('uniq_id', $request->product_id)
            ->first();

        if (!$product) {
            return response()->json([
                'status' => 0,
                'message' => 'Product not found'
            ]);
        }

        $stickyProductId = (int) $product->sticky_product_id;

        // 3️⃣ GET ALL SITES
        $sites = DB::table('site')->get();

        if ($sites->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => 'No site found'
            ]);
        }

        // 4️⃣ API Keys
        $app_key_data = DB::table('app_keys')->first();

        if (!$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API configuration not found'
            ]);
        }

        // 5️⃣ Loop each site & check coupon
        foreach ($sites as $site) {

            if (empty($site->campaign_id)) {
                continue;
            }

            $payload = [
                "shipping_id" => 2,
                "promo_code"  => $promo,
                "email"       => "ravi12@whitelabelmd.com",
                "campaign_id" => (int) $site->campaign_id,
                "products"    => [
                    [
                        "product_id" => $stickyProductId,
                        "quantity"   => 1
                    ]
                ]
            ];

            $response = Http::withBasicAuth(
                $app_key_data->app_key,
                $app_key_data->app_secret
            )->post(
                'https://whitelabelmd.sticky.io/api/v1/coupon_validate',
                $payload
            );

            $res = $response->json();
            // dd($res);
            // ✅ Coupon matched for this site
            if ($response->successful() && ($res['response_code'] ?? '') === "100") {
                return response()->json([
                    "status"  => 1,
                    "message" => "Promo code applied successfully.",
                    "site_id" => $site->id,
                    "campaign_id" => $site->campaign_id,
                    "data" => [
                        'response_code' => $res['response_code'],
                        "code" => $promo,
                        "discount_amount" => number_format($res['coupon_amount'] ?? 0, 2),
                    ]
                ]);
            }
        }

        return response()->json([
            "status"  => 0,
            "message" => $res['response_message'] ?? 'Invalid promo code',
        ]);
    }

    public function get_product_details_new_api(Request $request)
    {
        $product_id = $request-> product_id;

        if(empty($product_id))
        {
            return response()->json([
                'status' => 0,
                'message' => 'Product ID is required'
            ]);
        }
        // $product_sku = $request-> product_sku;

        if($product_id!="")
        {
            $is_db = DB::table('product')->where('uniq_id',$product_id)->first();

            if($is_db)
            {

                $product_img1 = array();

                $product_image = DB::table('product_image')->where('product_id',$is_db->product_id)->get();
            // dd($product_image);
                if(count($product_image) != 0)
                {
                    foreach($product_image as $iv_img)
                    {
                        $product_img1[] = array(
                            'img_video'         => URL("/public/assets/product_img/" . $iv_img->img_video),
                        );
                    }

                }
                $table_data = DB::table('product')->where('status',1)->where('site_id',$is_db->site_id)->orderBy('created_at', 'desc')->get();

                if(count($table_data) != 0)
                {
                    $product = array();

                    foreach($table_data as $data)
                    {
                        $product_img = array();

                        $product_image = DB::table('product_image')->where('product_id',$data->product_id)->get();

                        if(count($product_image) != 0)
                        {
                            foreach($product_image as $iv_img)
                            {
                                $product_img[] = array(
                                    'img_video'         => URL("/public/assets/product_img/" . $iv_img->img_video),
                                );
                            }

                        }
                        $site_data     = DB::table('site')->where('id', '=',$data->site_id)->first();
                        $campaign_data = DB::table('campaign')->where('campaign_id', '=',$data->campaign_id)->first();
                        $payment_name = json_decode($campaign_data->payment_name, true); // Decode the JSON string

                        if($data->is_price == 1)
                        {
                            $price = $data->custom_price;
                        }
                        else
                        {
                            $price = $data->product_price;
                        }

                        $product[] = array(
                            'product_id'                  => $data->uniq_id,
                            'product_name'                => $data->product_name,
                            'product_description'         => $data->product_description,
                            'product_price'               => $price,
                            'form_url'                    => $data->form_url,
                            'form_id'                     => $data->form_id,
                            'product_sku'                 => $data->product_sku,
                            'product_category_name'       => $data->product_category_name,
                            'vertical_name'               => $data->vertical_name,
                            'product_is_trial'            => $data->product_is_trial,
                            'product_is_shippable'        => $data->product_is_shippable,
                            'product_rebill_product'      => $data->product_rebill_product,
                            'product_rebill_days'         => $data->product_rebill_days,
                            'product_max_quantity'        => $data->product_max_quantity,
                            'preserve_recurring_quantity' => $data->preserve_recurring_quantity,
                            'subscription_type'           => $data->subscription_type,
                            'subscription_week'           => $data->subscription_week,
                            'subscription_day'            => $data->subscription_day,
                            'cost_of_goods_sold'          => $data->cost_of_goods_sold,
                            'taxable'                     => $data->taxable,
                            'product_img'                 => $product_img,
                            'site_name'                   => $site_data->site_name,
                            'site_logo'                   => URL("/public/assets/site_logo/" . $site_data->site_logo),


                        );
                    }


                }
                        if($is_db->is_price == 1)
                        {
                            $price1 = $is_db->custom_price;
                        }
                        else
                        {
                            $price1 = $is_db->product_price;
                        }
                $data =[
                    'product_id'           => $is_db->uniq_id,
                    'product_name'         => $is_db->product_name,
                    'product_price'        => $price1,
                    'product_description'  => $is_db->product_description,
		            'product_category_name'  => $is_db->product_category_name,
                    'product_img'          => $product_img1,
                    'product_sku'          => $is_db->product_sku,
                    // 'product'              => $product,
                    'payment_name'         => $payment_name,
                    'countries'            => $campaign_data->countries,
                ];

                $result['product_data']     = $data;
                $result['status']           = 1;
                $result['message']          = "Great! You have Successfully get all record!";
            }
            else
            {
                $result['status']           = 0;
                $result['message']          = "record not found!";
            }

        }
        else
        {
            $result['status'] = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }

        return response()->json($result);
    }

    public function getOrderTimeline(Request $request)
    {
        $orderId = $request->order_id;

        if (empty($orderId)) {
            return response()->json([
                'status' => 0,
                'message' => 'order_id required'
            ]);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode('whitelabelmd_test_qfBktNLL04XslmzVSXVm201qC3eKT498:XpNVgsZW1ZBrW9WZVMqvOoQGheMM2Ek0'),
            'authtoken'     => 'geckos-api-key=gokul428use'
        ])->get("https://api.whitelabelmd.com/v1/site/188/order/{$orderId}/order-timeline");

        // dd($response->json());
        if (!$response->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get order timeline'
            ]);
        }

        $apiData = $response->json()['data'] ?? [];

        // Labels we want
        $allowedLabels = [
            'Order Posted',
            'Intake Completed',
            'Approval Of Prescription',
            'Order Shipped',
            'Order Sent to Doctor'
        ];

        // dd($apiData);
        // Label → UI Mapping
        $map = [
            'Order Posted' => [
                'icon' => '✅',
                'title' => 'Order Received',
                'description' => 'Thank you for your order. We’ve received it successfully and are preparing the next step in your care.'
            ],
            'Intake Completed' => [
                'icon' => '📝',
                'title' => 'Medical Intake Completed',
                'description' => 'Your medical intake has been completed and securely submitted for clinical review.'
            ],
            'Approval Of Prescription' => [
                'icon' => '✔️',
                'title' => 'Prescription Approved',
                'description' => 'Your prescription has been approved. Your order is now being prepared for shipment.'
            ],
            'Order Shipped' => [
                'icon' => '🚚',
                'title' => 'Order Shipped',
                'description' => 'Your order is on the way.'
            ],
            'Order Sent to Doctor' => [
                'icon' => '🩺',
                'title' => 'Sent to Provider for Review',
                'description' => 'Your information has been sent to a licensed medical provider for evaluation.'
            ]
        ];

        $timeline = [];

        foreach ($apiData as $item) {

            if (!in_array($item['label'], $allowedLabels)) {
                continue;
            }

            $trackingText = '';
            if ($item['label'] === 'Order Shipped' && $item['data']['tracking_number'] != "") {
                $trackingText = ' Tracking Number: ' . $item['data']['tracking_number'];
            }

            $timeline[] = [
                'icon'        => $map[$item['label']]['icon'],
                'title'       => $map[$item['label']]['title'],
                'date'        => date('M d, Y', strtotime($item['time_stamp'])),
                'description' => $map[$item['label']]['description'] . $trackingText
            ];
        }

        return response()->json([
            'status'   => 1,
            'timeline' => $timeline
        ]);
    }



}