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
class OrderController extends Controller
{

      // =========> statrt create order code<========
      private function generateRandomPassword() 
      {
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $specialChars = '!@#$%^&*()-_+=<>?';
        $numbers = '0123456789';
  
        $password = '';
  
        // Add 5 random letters
        for ($i = 0; $i < 5; $i++) {
            $password .= $letters[rand(0, strlen($letters) - 1)];
        }
  
        // Add 1 special character
        $password .= $specialChars[rand(0, strlen($specialChars) - 1)];
  
        // Add 2 numbers
        for ($i = 0; $i < 2; $i++) {
            $password .= $numbers[rand(0, strlen($numbers) - 1)];
        }
  
        // Shuffle the password to make it more random
        return str_shuffle($password);
      }
  
      // private function generateRandomPassword()
      // {
      //     // Implement your password generation logic here
      //     return str_random(10);
      // }
  
      private function validateInputs($request)
      {
          $required = [
              'last_name', 'email', 'product_id', 'state_name', 
              'zip_code', 'first_name', 'city_name'
          ];
  
          foreach ($required as $field) {
              if (empty($request->$field)) {
                  return false;
              }
          }
  
          return filter_var($request->email, FILTER_VALIDATE_EMAIL);
      }
  
      private function createUserData($request, $password, $encryptedPassword)
      {
          return [
              'email' => $request->email,
              'contact_details_id' => $request->contact_details_id,
              'phone_number' => $request->phone,
              'first_name' => $request->first_name,
              'last_name' => $request->last_name,
              'password' => Hash::make($password),
              'encrypted_password' => $encryptedPassword,
              'payment_token' => $request->payment_token,
              'created_at' => now(),
          ];
      }
  
      private function createAddressData($userId, $request, $productId)
      {
          return [
              'user_id' => $userId,
              'product_id' => $productId,
              'address' => $request->address,
              'state_name' => $request->state_name,
              'city_name' => $request->city_name,
              'zip_code' => $request->zip_code,
              'created_at' => now(),
          ];
      }
  
      private function createCardData($userId, $request)
      {
          return [
              'user_id' => $userId,
              'card_type' => $request->card_type,
              'card_no' => $request->card_no,
              'ex_month' => str_pad($request->ex_month, 2, '0', STR_PAD_LEFT),
              'ex_year' => $request->ex_year,
              'cvv_no' => $request->cvv_no,
              'card_holder_name' => $request->card_holder_name,
              'created_at' => now(),
          ];
      }
  
      private function preparePatientData($userData, $addressData, $productData, $shippingData, $campaignData, $paymentToken,$cardData,$promoCode,$billingAddress,$billingCity,
              $billingState,$billingZip,$billingCountry,$billingSameAsShipping,$billingFirstName,$billingLastName,$gateway_id,$billing_model_ids)
      {
          $event_data = DB::table('send_mail_events')->where('email_type','=','order_confirm')->first();
          if($billingSameAsShipping == "NO")
          {
              // $billingAddress,$billingCity,$billingState,$billingZip,$billingCountry
              $billing_Address = $billingAddress;
              $billing_City = $billingCity;
              $billing_State = $billingState;
              $billing_Zip = $billingZip;
              $billing_Country = $billingCountry;
              $billing_FirstName = $billingFirstName;
              $billing_LastName = $billingLastName;
              // $billing_Address = $billingAddress;
          }
          else
          {
              $billing_Address = $addressData->address;
              $billing_City = $addressData->city_name;
              $billing_State = $addressData->state_name;
              $billing_Country = $addressData->country;
              $billing_Zip = $addressData->zip_code;
              $billing_FirstName = $userData->first_name;
              $billing_LastName = $userData->last_name;
          }
          if ($paymentToken === null) {
              return [
                  "firstName" => $userData->first_name,
                  "lastName" => $userData->last_name,
                  "currency" => "JPY",
                  "billingFirstName" => $billing_FirstName,
                  "billingLastName" => $billing_LastName,
                  "billingAddress1" => $billing_Address,
                  "billingCity" => $billing_City,
                  "billingState" => $billing_State,
                  "billingZip" => $billing_Zip,
                  "billingCountry" => $billing_Country,
                  'phone' => $userData->phone_number,
                  'email' => $userData->email,
                  'creditCardType' => "Discover",
                  'creditCardNumber' => $cardData->card_no,
                  'expirationDate' => $cardData->ex_month . $cardData->ex_year,
                  'CVV' => $cardData->cvv_no,
                  'shippingId' => $shippingData->shipping_id,
                  'tranType' => "Sale",
                //   "event_id" => $event_data->event_id,
                  'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                  'campaignId' => $productData->campaign_id,
                  // 'billingSameAsShipping' => "YES",
                  'shippingAddress1' => $addressData->address,
                  'shippingCity' => $addressData->city_name,
                  'shippingState' => $addressData->state_name,
                  'shippingZip' => $addressData->zip_code,
                  'shippingCountry' => $addressData->country ?? 'JP',
                //   'preserve_force_gateway' => 1,
                  'promoCode' => $promoCode,
                  'billingSameAsShipping' => $billingSameAsShipping,
                  'forceGatewayId' => $gateway_id,
                  'preserve_force_gateway' => $gateway_id,
                  "offers" => [
                      [
                          "offer_id" => $campaignData->offer_id,
                          "product_id" => $productData->sticky_product_id,
                        //   "billing_model_ids" => 3,
                          "billing_model_ids" => $billing_model_ids,
                          "quantity" => 1,
                      ]
                  ]
              ];
          } else {
              return [
                  "firstName" => $userData->first_name,
                  "lastName" => $userData->last_name,
                  "currency" => "JPY",
                  "billingFirstName" => $billing_FirstName,
                  "billingLastName" => $billing_LastName,
                  "billingAddress1" => $billing_Address,
                  "billingCity" => $billing_City,
                  "billingState" => $billing_State,
                  "billingZip" => $billing_Zip,
                  "billingCountry" => $billing_Country,
                  'phone' => $userData->phone_number,
                  'email' => $userData->email,
                  'payment_token' => $paymentToken,
                //   "event_id" => $event_data->event_id,
                  'shippingId' => $shippingData->shipping_id,
                  'tranType' => "Sale",
                  'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                  'campaignId' => $productData->campaign_id,
                  // 'billingSameAsShipping' => "YES",
                  'shippingAddress1' => $addressData->address,
                  'shippingCity' => $addressData->city_name,
                  'shippingState' => $addressData->state_name,
                  'shippingZip' => $addressData->zip_code,
                  'shippingCountry' => $addressData->country ?? 'JP',
                //   'preserve_force_gateway' => 1,
                  'promoCode' => $promoCode,
                  'billingSameAsShipping' => $billingSameAsShipping,
                  'forceGatewayId' => $gateway_id,
                  'preserve_force_gateway' => $gateway_id,
                  "offers" => [
                      [
                          "offer_id" => $campaignData->offer_id,
                          "product_id" => $productData->sticky_product_id,
                          "billing_model_ids" => $billing_model_ids,
                          "quantity" => 1,
                      ]
                  ]
              ];
          }
      }
  
      private function processOrder($responseData, $userId, $productData,$paymentToken,$form_data)
      {
          // dd($form_data);
          DB::beginTransaction();
          try {
  
              if (isset($form_data->submissions_id) && !empty($form_data->submissions_id)) {
                  // Update cart status
                  $updateCount = DB::table('user_cart_product')
                      ->where('user_id', $userId)
                      ->where('product_id', $productData->product_id)
                      ->update(['status' => 2]);
      
                  if ($updateCount === 0) {
                      throw new \Exception("Failed to update cart status: No matching records found.");
                  }
              }
  
  
              // Create order
              $order = new Order();
              $order->gateway_id = $responseData['gateway_id'] ?? null;
              $order->sticky_product_id = $productData->sticky_product_id;
              $order->payment_token = $paymentToken;
              $order->user_id = $userId;
              $order->submissions_id = $form_data->submissions_id ?? null;
              $order->form_id = $form_data->form_id ?? null;
              $order->response_code = $responseData['response_code'] ?? null;
              $order->error_found = $responseData['error_found'] ?? null;
              $order->order_id = $responseData['order_id'] ?? null;
              $order->transactionID = $responseData['transactionID'] ?? null;
              $order->customerId = $responseData['customerId'] ?? null;
              $order->authId = $responseData['authId'] ?? null;
              $order->orderTotal = $responseData['orderTotal'] ?? null;
              $order->orderSalesTaxPercent = $responseData['orderSalesTaxPercent'] ?? null;
              $order->orderSalesTaxAmount = $responseData['orderSalesTaxAmount'] ?? null;
              $order->test = $responseData['test'] ?? null;
              $order->prepaid_match = $responseData['prepaid_match'] ?? null;
              $order->resp_msg = $responseData['resp_msg'] ?? null;
              $order->next_billing_date = Carbon::now()->addMonth();
              $order->save();
  
              $user_data = DB::table('user')->where('user_id', $userId)->first();
              $customerId = $responseData['customerId'] ?? null;
              $email = $user_data->email;
  
              $this->memberCreate($customerId,$email);
  
              // Process line items
              if (!empty($responseData['line_items'])) {
                  foreach ($responseData['line_items'] as $item) {
                      $lineItem = new OrderLineItem();
                      $lineItem->order_id = $order->id;
                      $lineItem->product_id = $item['product_id'] ?? null;
                      $lineItem->variant_id = $item['variant_id'] ?? null;
                      $lineItem->quantity = $item['quantity'] ?? 1;
                      $lineItem->subscription_id = $item['subscription_id'] ?? null;
                      $lineItem->save();
                  }
              }
  
              // Process subscriptions
              if (!empty($responseData['subscription_id'])) {
                  foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                      $subscription = new Subscription();
                      $subscription->order_id = $order->id;
                      $subscription->product_id = $productId;
                      $subscription->subscription_id = $subscriptionId;
                      $subscription->save();
                  }
              }
  
              DB::commit();
              return true;
          } catch (\Exception $e) {
              DB::rollBack();
              \Log::error('Order Processing Error: ' . $e->getMessage());
              return false;
          }
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
  
  
      private function sendWelcomeEmail($email, $password, $firstName)
      {
          $emailData = [
              'frommail' => 'bgwhitelabel@gmail.com',
              'tomail' => $email,
              'subject' => 'Contact Information'
          ];
  
          $mailData = [
              'email_sub' => $emailData['frommail'],
              'password' => $password,
              'first_name' => $firstName,
              'url' => "https://gokulnair.com/jalpesh/memberportal_weightloss/",
              'email' => $email
          ];
  
          try {
              Mail::send('Mail.Formsubmit', $mailData, function ($message) use ($emailData) {
                  $message->to($emailData['tomail']);
                  $message->from($emailData['frommail']);
                  $message->subject($emailData['subject']);
              });
              return true;
          } catch (\Exception $e) {
              \Log::error('Email Send Error: ' . $e->getMessage());
              return false;
          }
      }
  
      public function add_user_check_product_agent(Request $request)
      {
          // dd($request);
                // Validate inputs
                if (!$this->validateInputs($request)) {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Please fill out all the required details in the form before submitting it.'
                    ], 400);
                }
            
                try {
                    DB::beginTransaction();
            
                    $password = $this->generateRandomPassword();
                    $encryptedPassword = Crypt::encryptString($password);
            
                    // Check if user exists
                    $existingUser = DB::table('user')->where('email', $request->email)->first();
            
                    // Ensure product_id and gateway_id are arrays
                    $productIds = is_array($request->product_id) ? $request->product_id : [$request->product_id];
                    $gatewayIds = is_array($request->gateway_id) ? $request->gateway_id : [$request->gateway_id];
                    $promo_codes = is_array($request->promoCodes) ? $request->promoCodes : [$request->promoCodes];
                    $billingmodelids = is_array($request->billing_model_ids) ? $request->billing_model_ids : [$request->billing_model_ids];
            
                    // Validate number of products matches number of gateways
                    if (count($productIds) !== count($gatewayIds)) {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Number of products must match number of gateways.'
                        ], 400);
                    }
            
                    // Create or update user
                    if (!$existingUser) {
                        // Create new user
                        $userId = DB::table('user')->insertGetId(
                            $this->createUserData($request, $password, $encryptedPassword)
                        );
                    } else {
                        // Update existing user
                        DB::table('user')
                            ->where('email', $request->email)
                            ->update($this->createUserData($request, $password, $encryptedPassword));
                        $userId = $existingUser->user_id;
                    }
            
                    // Create address
                    $addressId = DB::table('user_address')->insertGetId(
                        $this->createAddressData($userId, $request, $productIds[0])
                    );
            
                    // Create payment card
                    $cardId = DB::table('user_payment_card_details')->insertGetId(
                        $this->createCardData($userId, $request)
                    );
            
                    // Prepare to store results
                    $orderResults = [];
            
                    // Process each product
                    foreach ($productIds as $index => $productId) {
                        // Get product data
                        $product_data = DB::table('product')->where('uniq_id', $productId)->first();
                        
                        if (!$product_data) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 0,
                                'message' => "Product with ID {$productId} not found."
                            ], 404);
                        }
            
                        // Create cart
                        DB::table('user_cart_product')->insert([
                            'user_id' => $userId,
                            'user_card_id' => $cardId,
                            'address_id' => $addressId,
                            'product_id' => $product_data->product_id,
                            'form_id' => $request->form_id,
                            'created_at' => now(),
                        ]);
            
                        $form_data = DB::table('webhook')->where('unique_id', $request->unique_id)->first();
                        
                        // Use the corresponding gateway ID or the first one if not enough are provided
                        $gateway_id = $gatewayIds[$index] ?? $gatewayIds[0];
                        $promoCode = $promo_codes[$index] ?? $promo_codes[0];
                        $billing_model_ids = $billingmodelids[$index] ?? $billingmodelids[0];
            
                        // Process order with Sticky.io
                        $result = $this->processStickyOrder(
                            $userId, 
                            $product_data, 
                            $request->payment_token, 
                            $form_data,
                            $promoCode ?? null,
                            $request->billingAddress,
                            $request->billingCity,
                            $request->billingState,
                            $request->billingZip,
                            $request->billingCountry,
                            $request->billingSameAsShipping,
                            $request->billingFirstName,
                            $request->billingLastName,
                            $gateway_id,
                            $billing_model_ids ?? null
                        );
            
                        // Store the result for this product
                        $orderResults[] = $result;
            
                        // Check for errors in the result
                        if ($result['status'] !== 1) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 0,
                                'message' => $result['error_details']['decline_reason'] ?? 'Order could not be created.',
                                'error_details' => $result
                            ], 200);
                        }
            
                        // Optional: Add start URL to custom fields
                        if (isset($result['order_id']) && $request->start_url) {
                            $this->customFieldNextCheckInLink($result['order_id'], $request->start_url);
                        }
                    }
            
                    DB::commit();
            
                    // Return response with all order details
                    return response()->json([
                        'status' => 1,
                        'message' => 'Success',
                        'orders' => array_map(function($result) {
                            return [
                                'order_id' => $result['order_id'] ?? null,
                                'status' => $result['status']
                            ];
                        }, $orderResults)
                    ]);
            
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Log::error('User Registration Error: ' . $e->getMessage());
                    return response()->json([
                        'status' => 0,
                        'message' => 'An error occurred while processing your request.',
                        'error' => $e->getMessage()
                    ], 500);
                }
      }
      
  
      private function processStickyOrder($userId, $productData, $paymentToken,$form_data,$promoCode,$billingAddress,$billingCity,$billingState,
                  $billingZip,$billingCountry,$billingSameAsShipping,$billingFirstName,$billingLastName,$gateway_id,$billing_model_ids)
      {
          // Get required data
          $cartData = DB::table('user_cart_product')->where('user_id', $userId)->first();
          if (!$cartData) {
              return ['status' => 0, 'message' => 'No cart data found for the user.'];
          }
  
          $shippingData = DB::table('shipping')->where('campaign_id', $productData->campaign_id)->first();
          $campaignData = DB::table('campaign')->where('campaign_id', $productData->campaign_id)->first();
          $userData = DB::table('user')->where('user_id', $userId)->first();
          $cardData = DB::table('user_payment_card_details')->where('user_id', $userId)->orderBy('created_at', 'desc')->first();
      // dd($cardData);
          $addressData = DB::table('user_address')->where('address_id', $cartData->address_id)->first();
  
          if (!$shippingData || !$campaignData || !$userData || !$addressData) {
              return ['status' => 0, 'message' => 'Incomplete data, unable to process the request.'];
          }
  
          // Check for existing order
          $exists = DB::table('orders')
              ->where('sticky_product_id', $productData->sticky_product_id)
              ->where('user_id', $userId)
              ->exists();
  
          if ($exists) {
              return ['status' => 0, 'message' => 'Order already exists for this user.'];
          }
  
          // Get site credentials
          $siteData = DB::table('site')->where('campaign_id', $productData->campaign_id)->first();
          if (!$siteData) {
              return ['status' => 0, 'message' => 'Site configuration not found.'];
          }
  
          // Prepare and send order to Sticky.io
          $patientData = $this->preparePatientData(
              $userData, 
              $addressData, 
              $productData, 
              $shippingData, 
              $campaignData, 
                              $paymentToken,
              $cardData,
              $promoCode,$billingAddress,$billingCity,$billingState,$billingZip,$billingCountry,$billingSameAsShipping,$billingFirstName,
              $billingLastName,$gateway_id,$billing_model_ids
          );
  
          try {
              $response = Http::withBasicAuth($siteData->site_key, $siteData->site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
              
              $responseData = $response->json();
  
              if (!$response->successful() || 
                  ($responseData['response_code'] == 10920 && $responseData['error_found'] == "1")) {
                  return [
                      'status' => 4,
                      'message' => 'Error with payment token or order processing.',
                      'error_details' => $responseData,
                      'user_id' =>$userId,
                  ];
              }
              $order_id = $responseData['order_id'] ?? null;
              // dd($order_id);
              if ($this->processOrder($responseData, $userId, $productData,$paymentToken,$form_data)) {
                  return [
                      'status' => 1, 
                      'order_id' => $order_id, 
                      'message' => 'Success'
                  ];
              }
  
              return [
                  'status' => 4,
                  'message' => 'Error processing order in local database.',
                  'error_details' => $responseData,
                  'user_id' =>$userId,
              ];
  
          } catch (\Exception $e) {
              \Log::error('Sticky.io API Error: ' . $e->getMessage());
              return [
                  'status' => 4,
                  'message' => 'Error connecting to payment service.',
                  'error' => $e->getMessage(),
                  'user_id' =>$userId,
              ];
          }
      }

      private function customFieldNextCheckInLink($order_id,$start_url)
    {
        $table_data = DB::table('orders')->get();
        // $order_id  = $request ->order_id;
        // $start_url  = $request ->start_url;
        // if(count($table_data) > 0)
        // {   
            // foreach($table_data as $data)
            // {
                // $order_id = $request->order_id; // Assuming the column name is 'order_id'
                
                $exists = DB::table('orders_next_check_in_link')->where('order_id', $order_id)->exists();
                        if (!$exists) {
                            $app_key_data = DB::table('app_keys')->first();
    
                            $app_key    = $app_key_data->app_key;
                            $app_secret = $app_key_data->app_secret;
                        $response = Http::withBasicAuth($app_key, $app_secret)
                        ->put("https://whitelabelmd.sticky.io/api/v2/orders/{$order_id}/custom_fields", [
                            'custom_fields' => [
                                [
                                    'id' => 2,
                                    'field_name' => 'start_url',
                                    'value' => $start_url
                                ]
                            ]
                        ]);
                    
                    if ($response->successful()) {

                        DB::table('orders_next_check_in_link')->insert([
                                'order_id' => $order_id,
                                'created_at' =>now(),
                        ]);
                        // The request was successful
                        $result = $response->json();
                        // Process the result as needed
                        Log::info("Custom field updated for order {$order_id}", $result);
                    } else {
                        // The request failed
                        $error = $response->body();
                        Log::error("Failed to update custom field for order {$order_id}: {$error}");
                    }
                }


            // }
            
            return response()->json(['message' => 'Custom fields updated successfully']);
        // }
        
        // return response()->json(['message' => 'No orders found'], 404);
    }
  

      public function createOrder_test_v2(Request $request)
    {
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
      
        // if ($paymentToken === null) {
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
                "address2" => $request->address2,
                "billingAddress2"  => $request->billingAddress2,
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
        
        

              $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
              $site_key    = $site_data->site_key;
              $site_secret = $site_data->site_secret;
              
              $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();

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

    public function createOrder_test(Request $request)
    {
        $product_ids = is_array($request->product_id) ? $request->product_id : explode(',', $request->product_id);
        $product_ids = array_map('trim', $product_ids);
        // dd($product_ids);
        $event_data = DB::table('send_mail_events')->where('email_type', 'order_confirm')->first();
        $results = [];

        foreach ($product_ids as $uniq_id) {
            $product = DB::table('product')->where('uniq_id', $uniq_id)->first();
            if (!$product) continue;

         

            $bm = 3;
            // Check if product_description has 'BM:' and extract number after it
            if (preg_match('/BM:(\d+)/', $product->product_description, $matches)) {
                $bm = (int) $matches[1];
            }
           
            $offer_id = $product->offer_id;
            $email_1 = $this->member_view($request->email);
            if ($email_1 == 0) 
            {
                if ($product->is_price == 1) 
                {
                  $price = $product->custom_price;
                }
                else
                {
                  $price = $product->product_price;
                }
            }
            else
            {
                $price = $product->product_price;
            }

         
            $offers = [[
                "offer_id"         => $product->offer_id,
                "product_id"       => $product->sticky_product_id,
                "billing_model_id" => $bm,
                "quantity"         => 1,
                "price" => $price,
            ]];

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
                "address2 " => $request->address2,
                "billingAddress2" => $request->billingAddress2,
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
                'campaignId' => $product->campaign_id,
                'shippingAddress1' => $request->address,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_name,
                'shippingZip' => $request->zip_code,
                'shippingCountry' => $request->country ?? 'US',
                'preserve_force_gateway' => 1,
                "AllowDuplicateSignup" => 1,
                'promoCode' => $request->promo_codes,
                'billingSameAsShipping' => $request->billingSameAsShipping,
                "offers" => $offers,
                "custom_fields" => [[
                    "id" => 2,
                    "field_name" => 'start_url',
                    "value" => $request->start_url
                ]]
            ];

            $site_data = DB::table('site')->where('campaign_id', $product->campaign_id)->first();
            $site_key    = $site_data->site_key;
            $site_secret = $site_data->site_secret;

            $response = Http::withBasicAuth($site_key, $site_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);

            $responseData = $response->json();

            if ($responseData['error_found'] == "1") {
                $results[] = [
                    'status' => 5,
                    'message' => $responseData['decline_reason'],
                    'order_id' => $responseData['order_id'] ?? null
                ];
                continue;
            }

            if ($response->successful()) {
                $order = new Order();
                $order->gateway_id = $responseData['gateway_id'];
                // $order->uniq_id = $uniq_id;
                // $order->email = $request->email ?? null;
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
                $order->next_billing_date = Carbon::now('America/New_York')->addDays(25)->toDateTimeString();
                $order->created_at = Carbon::now()->timezone('America/New_York')->toDateTimeString();
                $order->save();

                foreach ($responseData['line_items'] as $item) {
                    $lineItem = new OrderLineItem();
                    $lineItem->order_id = $order->id;
                    $lineItem->product_id = $item['product_id'];
                    $lineItem->variant_id = $item['variant_id'];
                    $lineItem->quantity = $item['quantity'];
                    $lineItem->subscription_id = $item['subscription_id'];
                    $lineItem->save();
                }

                foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                    $subscription = new Subscription();
                    $subscription->order_id = $order->id;
                    $subscription->product_id = $productId;
                    $subscription->subscription_id = $subscriptionId;
                    $subscription->save();
                }

        
                $customerId = $responseData['customerId'] ?? null;
                $this->memberCreate($customerId, $request->email);

                $results[] = [
                    'status' => 1,
                    'message' => 'Success',
                    'order_id' => $responseData['order_id']
                ];
            } else {
                $results[] = [
                    'status' => 'error',
                    'message' => $response->json()
                ];
            }
        }

        return response()->json($results);
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



      public function add_user_check_product_agent_test(Request $request)
      {
          $productIds = is_array($request->product_id) ? $request->product_id : [$request->product_id];
          $gatewayIds = is_array($request->gateway_id) ? $request->gateway_id : [$request->gateway_id];
          $promoCodes = is_array($request->promoCodes) ? $request->promoCodes : [$request->promoCodes];
          $billing_model_ids = is_array($request->billing_model_id) ? $request->billing_model_id : [$request->billing_model_id];
      
          $orderResponses = []; // To store responses for all products
          $orders = []; // To store responses for all products
      
          foreach ($productIds as $index => $productId) {
              $gatewayId = $gatewayIds[$index] ?? $gatewayIds[0]; // Fallback to first gateway ID
              $promoCode = $promoCodes[$index] ?? $promoCodes[0]; // Fallback to first promo code
              $billingmodelids = $billing_model_ids[$index] ?? $billing_model_ids[0]; // Fallback to first promo code
      
              $productData = DB::table('product')->where('uniq_id', $productId)->first();
              if (!$productData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Product not found for ID: $productId"
                  ];
                  continue;
              }
      
              $campaignData = DB::table('campaign')->where('campaign_id', $productData->campaign_id)->first();
              if (!$campaignData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Campaign not found for product ID: $productId"
                  ];
                  continue;
              }
      
              $eventData = DB::table('send_mail_events')->where('email_type', '=', 'order_confirm')->first();
              if (!$eventData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Event not found for product ID: $productId"
                  ];
                  continue;
              }
      
                $proId = $productData->sticky_product_id;
                // $offer_id = 397;
                $offer_id = $productData->offer_id;
                // dd($offer_id);
                if($offer_id)
                {
                    $offer_id = $productData->offer_id;
                }
              $patientData = [
                  "firstName" => $request->first_name,
                  "lastName" => $request->last_name,
                  "currency" => "USD",
                  "billingFirstName" => $request->billing_first_name,
                  "billingLastName" => $request->billing_last_name,
                  "billingAddress1" => $request->billing_address,
                  "billingCity" => $request->billing_city,
                  "billingState" => $request->billing_state,
                  "billingZip" => $request->billing_zip,
                  "billingCountry" => $request->billing_country,
                  "phone" => $request->phone,
                  "email" => $request->email,
                  "creditCardType" => "Discover",
                  "creditCardNumber" => $request->card_no,
                  "expirationDate" => $request->ex_month . $request->ex_year,
                  "CVV" => $request->cvv_no,
                  "shippingId" => 2,
                  "tranType" => "Sale",
                  "event_id" => $eventData->event_id,
                  "ipAddress" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                  "campaignId" => $productData->campaign_id,
                  "shippingAddress1" => $request->address,
                  "shippingCity" => $request->city_name,
                  "shippingState" => $request->state_name,
                  "shippingZip" => $request->zip_code,
                  "shippingCountry" => $request->country ?? 'US',
                //   "preserve_force_gateway" => $gatewayId,
                  "preserve_force_gateway" => $gatewayId,
                  'forceGatewayId' => $gatewayId,
                  "promoCode" => $promoCode,
                  "AllowDuplicateSignup" => 1,
                  "billingSameAsShipping" => $request->billingSameAsShipping,
                  "offers" => [
                      [
                          "offer_id" => $offer_id,
                          "product_id" => $proId,
                          "billing_model_id" => $billingmodelids,
                          "quantity" => 1,
                      ]
                  ]
              ];
      
              $siteData = DB::table('site')->where('campaign_id', $productData->campaign_id)->first();
              if (!$siteData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Site data not found for campaign ID: {$productData->campaign_id}"
                  ];
                  continue;
              }
         
              $siteKey = $siteData->site_key;
              $siteSecret = $siteData->site_secret;
      
              $admin_id = $request->admin_id;
              $app_key_data = DB::table('super_admin_login')->where('id',$admin_id)->first();
              $app_key     = $app_key_data->client_key;
              $api_ext     = $app_key_data->client_secret;
  
            $response = Http::withBasicAuth($app_key, $api_ext)
              ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
      
              if ($response->failed()) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => $response->json()
                  ];
                  continue;
              }
      
              $responseData = $response->json();
              if ($responseData['error_found'] == "1") {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => $responseData['decline_reason']
                  ];
                  continue;
              }
              $admin_id  = $request->admin_id;
              $order_id  = $responseData['order_id'];
              $is_call  = $request->call_center_value;
              $custom_fields_order = $this->custom_fields($order_id,$is_call);
              $order = new Order();
              $order->admin_id = $admin_id;
              $order->gateway_id = null;
            //   $order->user_id = $request->user_id ?? null;
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
              $order->prepaid_match = $responseData['prepaid_match']?? null;
              $order->resp_msg = $responseData['resp_msg'] ?? null;
              $order->next_billing_date = Carbon::now()->addMonth();
              $order->save();
      
              foreach ($responseData['line_items'] as $item) {
                  $lineItem = new OrderLineItem();
                  $lineItem->order_id = $order->id;
                  $lineItem->product_id = $item['product_id'];
                  $lineItem->variant_id = $item['variant_id'];
                  $lineItem->quantity = $item['quantity'];
                  $lineItem->subscription_id = $item['subscription_id'];
                  $lineItem->save();
              }
      
              foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                  $subscription = new Subscription();
                  $subscription->order_id = $order->id;
                  $subscription->product_id = $productId;
                  $subscription->subscription_id = $subscriptionId;
                  $subscription->save();
              }
                // Call memberCreate to handle customer data
                $customerId = $responseData['customerId'] ?? null;
                $email = $request->email;
                $this->memberCreate($customerId, $email);
                $orders[] = [
                    'order_id' => $responseData['order_id'],
                    'status' => 1, // Assuming 1 indicates success
                ];
         
          }
      
            // Final response structure
          // Final response structure
            return response()->json([
                'status' => 1, // Overall status
                'message' => 'Order processing completed.',
                'orders' => $orders, // Successful orders
                'errors' => $orderResponses, // Errors for failed orders
            ]);
      }


      private function custom_fields($order_id,$is_call)
    {
        // $order_id = $request->order_id;
        // Fetch site and app key data
        $site_data = DB::table('site')->first();
        $app_key_data = DB::table('app_keys')->first();
    
        if (!$site_data || !$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API keys or site data not found.',
            ], 500);
        }
    
        if (!$order_id) {
            return response()->json([
                'status' => 0,
                'message' => 'order_id is required.',
            ], 400);
        }
    
        // Fetch credentials
        $app_key = $app_key_data->app_key;
        $api_secret = $app_key_data->app_secret;
        $domain = "whitelabelmd.sticky.io"; // Assuming 'domain' exists in the 'site' table
    
        // API URL with dynamic values
        $api_url = "https://{$app_key}.{$domain}/api/v2/orders/{$order_id}/custom_fields";
        if($is_call == "true")
        {
            $is_val = 1;
        }
        else
        {
            $is_val = 2;

        }
        // Request payload
        $payload = [
            'custom_fields' => [
                [
                    'id' => 11,
                    'field_name' => 'callcenter_sale',
                    'value' => $is_val,
                ]
            ]
        ];
        $response = Http::withBasicAuth($app_key, $api_secret)
        ->post("https://whitelabelmd.sticky.io/api/v2/orders/{$order_id}/custom_fields", $payload);
    
        $data = $response->json();
        // dd($data);
     
        if ($response->successful()) {
            return response()->json([
                'status' => 1,
                'message' => 'Custom fields retrieved successfully.',
                'data' => $response->json(),
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve custom fields.',
                'error' => $response->json(),
            ], $response->status());
        }
    }


      public function createOrder_agent(Request $request)
      {
          $productIds = is_array($request->product_id) ? $request->product_id : [$request->product_id];
          $gatewayIds = is_array($request->gateway_id) ? $request->gateway_id : [$request->gateway_id];
          $promoCodes = is_array($request->promoCodes) ? $request->promoCodes : [$request->promoCodes];
          $billing_model_ids = is_array($request->billing_model_id) ? $request->billing_model_id : [$request->billing_model_id];
      
          $orderResponses = []; // To store responses for all products
          $orders = []; // To store responses for all products
      
          foreach ($productIds as $index => $productId) {
              $gatewayId = $gatewayIds[$index] ?? $gatewayIds[0]; // Fallback to first gateway ID
              $promoCode = $promoCodes[$index] ?? $promoCodes[0]; // Fallback to first promo code
              $billingmodelids = $billing_model_ids[$index] ?? $billing_model_ids[0]; // Fallback to first promo code
      
              $productData = DB::table('product')->where('uniq_id', $productId)->first();
              if (!$productData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Product not found for ID: $productId"
                  ];
                  continue;
              }
      
              $campaignData = DB::table('campaign')->where('campaign_id', $productData->campaign_id)->first();
              if (!$campaignData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Campaign not found for product ID: $productId"
                  ];
                  continue;
              }
      
              $eventData = DB::table('send_mail_events')->where('email_type', '=', 'order_confirm')->first();
              if (!$eventData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Event not found for product ID: $productId"
                  ];
                  continue;
              }
      
              $proId = $productData->sticky_product_id;
      
              $patientData = [
                  "firstName" => $request->first_name,
                  "lastName" => $request->last_name,
                  "currency" => "USD",
                  "billingFirstName" => $request->billing_FirstName,
                  "billingLastName" => $request->billing_LastName,
                  "billingAddress1" => $request->billing_Address,
                  "billingCity" => $request->billing_City,
                  "billingState" => $request->billing_State,
                  "billingZip" => $request->billing_Zip,
                  "billingCountry" => $request->billing_Country,
                  "phone" => $request->phone,
                  "email" => $request->email,
                  "creditCardType" => "Discover",
                  "creditCardNumber" => $request->card_no,
                  "expirationDate" => $request->ex_month . $request->ex_year,
                  "CVV" => $request->cvv_no,
                  "shippingId" => 2,
                  "tranType" => "Sale",
                  "event_id" => $eventData->event_id,
                  "ipAddress" => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                  "campaignId" => $productData->campaign_id,
                  "shippingAddress1" => $request->address,
                  "shippingCity" => $request->city_name,
                  "shippingState" => $request->state_name,
                  "shippingZip" => $request->zip_code,
                  "shippingCountry" => $request->country ?? 'US',
                //   "preserve_force_gateway" => $gatewayId,
                  "preserve_force_gateway" => $gatewayId,
                  'forceGatewayId' => $gatewayId,
                  "promoCode" => $promoCode,
                  "AllowDuplicateSignup" => 1,
                  "billingSameAsShipping" => $request->billingSameAsShipping,
                  "offers" => [
                      [
                          "offer_id" => $campaignData->offer_id,
                          "product_id" => $proId,
                          "billing_model_id" => $billingmodelids,
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
      
              $exists = DB::table('user')
              ->where('email', $request->email)
            //   ->where('user_id', $user_id)
              ->exists();

              if(!$exists)
              {
                DB::table('user')->insert([
                    'email' => $request->email,
                ]);
              }
              
              $siteData = DB::table('site')->where('campaign_id', $productData->campaign_id)->first();
              if (!$siteData) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => "Site data not found for campaign ID: {$productData->campaign_id}"
                  ];
                  continue;
              }
      
              $siteKey = $siteData->site_key;
              $siteSecret = $siteData->site_secret;
      
              $response = Http::withBasicAuth($siteKey, $siteSecret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
      
              if ($response->failed()) {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => $response->json()
                  ];
                  continue;
              }
      
              $responseData = $response->json();
              if ($responseData['error_found'] == "1") {
                  $orderResponses[] = [
                      'status' => 'error',
                      'message' => $responseData['decline_reason']
                  ];
                  continue;
              }
      
              $order = new Order();
              $order->gateway_id = $responseData['gateway_id'];
            //   $order->user_id = $request->user_id ?? null;
              $order->response_code = $responseData['response_code'];
              $order->error_found = $responseData['error_found'];
              $order->order_id = $responseData['order_id'];
              $order->transactionID = $responseData['transactionID'];
              $order->customerId = $responseData['customerId'];
              $order->authId = $responseData['authId'];
              $order->orderTotal = $responseData['orderTotal'];
              $order->orderSalesTaxPercent = $responseData['orderSalesTaxPercent'];
              $order->orderSalesTaxAmount = $responseData['orderSalesTaxAmount'];
              $order->test = $responseData['test'];
              $order->prepaid_match = $responseData['prepaid_match']?? null;
              $order->resp_msg = $responseData['resp_msg'] ?? null;
              $order->next_billing_date = Carbon::now()->addMonth();
              $order->save();
      
              foreach ($responseData['line_items'] as $item) {
                  $lineItem = new OrderLineItem();
                  $lineItem->order_id = $order->id;
                  $lineItem->product_id = $item['product_id'];
                  $lineItem->variant_id = $item['variant_id'];
                  $lineItem->quantity = $item['quantity'];
                  $lineItem->subscription_id = $item['subscription_id'];
                  $lineItem->save();
              }
      
              foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                  $subscription = new Subscription();
                  $subscription->order_id = $order->id;
                  $subscription->product_id = $productId;
                  $subscription->subscription_id = $subscriptionId;
                  $subscription->save();
              }
                // Call memberCreate to handle customer data
                $customerId = $responseData['customerId'] ?? null;
                $email = $request->email;
                $this->memberCreate($customerId, $email);
                $orders[] = [
                    'order_id' => $responseData['order_id'],
                    'status' => 1, // Assuming 1 indicates success
                ];
         
          }
      
            // Final response structure
          // Final response structure
            return response()->json([
                'status' => 1, // Overall status
                'message' => 'Order processing completed.',
                'orders' => $orders, // Successful orders
                'errors' => $orderResponses, // Errors for failed orders
            ]);
      }
  
      // ==============>end create order code<======
}