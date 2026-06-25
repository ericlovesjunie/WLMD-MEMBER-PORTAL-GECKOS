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
class TestController extends Controller
{

    public function get_user_cart_product_details_test(Request $request)
    {
        $order_id = $request->order_id;
    
        if (!empty($order_id)) {
            // Fetching app keys from the database
            $app_key_data = DB::table('app_keys')->first();
            $app_key = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
    
            // Preparing payload with the order_id
            $payload = [
                "order_id" => [$order_id],  // Pass order_id as an array
                "return_variants" => 1      // Include return_variants as required
            ];
    
            // Sending the POST request to the external API
            $response = Http::withBasicAuth($app_key, $app_secret)
                            ->post('https://whitelabelmd.sticky.io/api/v1/order_view', $payload);
    
            // Decoding the response JSON
            $responseData = $response->json();
            // dd($responseData);
    
            if ($response->successful()) {
                // Extract product details from the response
                $productDetails = [];
                $time = $responseData['time_stamp'] ?? null;
                if (isset($responseData['products']) && is_array($responseData['products'])) {
                    foreach ($responseData['products'] as $product) {

                        $img_video =  "";
                        // $main_product_id =  $product['product_id'];
                        $main_product_id =  $product['product_id'];
                        $product_data = DB::table('product')->where('sticky_product_id',$main_product_id)->first();
                        $product_image = DB::table('product_image')->where('product_id',$product_data->product_id)->first();
                    // dd($main_product_id);
                        if($product_image)
                        {
                            $img_video = URL("/public/assets/product_img/" . $product_image->img_video);
                                
                        }
                        
                        
                        $productDetails[] = [
                            'product_id' => $product['product_id'] ?? null,
                            'product_name' => $product['name'] ?? null,
                            'product_price' => $product['price'] ?? null,
                            'recurring_date' => $product['recurring_date'] ?? null,
                            'next_billing_date' => $product['recurring_date'] ?? null,
                            'sku' => $product['sku'] ?? null, // Example of adding more product fields
                            'quantity' => $product['product_qty'] ?? 0, // Ensure to capture the quantity as well
                            'time_stamp' => $time,
                            'time' => $time,
                            'order_id' => $order_id,
                            'product_img'  => $img_video,
                        ];
                    }
                }
    
                // Get the timestamp from the response
                
    
                // return response()->json([
                //     'status' => 1,
                //     'message' => 'Order data retrieved successfully.',
                //     'data' => [
                //         'order_id' => $order_id,
                //         'products' => $productDetails
                //     ]
                // ]);
                $result['product']          = $productDetails;
                $result['status']           = 1;
                $result['message']          = "Order data retrieved successfully.";
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve order data from the external service.',
                    'error' => $responseData
                ]);
            }
    
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please provide a valid order ID."
            ]);
        }
        return response()->json($result);
    }

    public function order_find_test(Request $request)
    {
        $email = $request->email;
    
        if (!empty($email)) {
            $app_key_data = DB::table('app_keys')->first();
            $site_data = DB::table('site')->first();
    
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
            $campaign_id = $site_data->campaign_id;
            // Prepare the payload for the API request
            $payload = [
                "campaign_id" => $campaign_id,
                "start_date" => "01/01/2024",
                "end_date" => "03/03/2025",
                "criteria" => [
                    "email" => $email
                ],
                "return_type" => "order_view"
            ];
    
            // Make the request with basic authentication
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);
    
            $responseData = $response->json();
            // dd($responseData);
            if ($response->successful() && isset($responseData['data'])) {
                // Extract the required fields from the response
                $orders = $responseData['data'];
    
                // Filter out orders with "order_status": "7"
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });
    
                // Map filtered orders to required structure
                $orderDetails = array_map(function ($order) {
                    $main_product_id = $order['main_product_id'] ?? null;
                    $product_data = DB::table('product')->where('sticky_product_id',$main_product_id)->first();
                    $product_image = DB::table('product_image')->where('product_id',$product_data->product_id)->first();
                // dd($main_product_id);
                $img_video =  "";
                    if($product_image)
                    {
                        $img_video = URL("/public/assets/product_img/" . $product_image->img_video);
                            
                    }
                    return [
                        'order_id'        => $order['order_id'] ?? null,
                        'time'            => $order['time_stamp'] ?? null,
                        'order_status'    => $order['order_status'] ?? null,
                        'tracking_number' => $order['tracking_number'] ?? null,
                        'main_product_id' => $order['main_product_id'] ?? null,
                        'product_img'     => $img_video,
                    ];
                }, $filteredOrders);
    
                // Sort by 'time' in descending order
                usort($orderDetails, function ($a, $b) {
                    return strtotime($b['time']) <=> strtotime($a['time']);
                });
    
                return $orderDetails; // Directly return the filtered and sorted array
            } else {
                return null; // Return null if no data or error
            }
        }
    
        return null; // Return null for invalid email
    }

    public function createOrSearchPatients_test(Request $request)
    {
        $email = $request->email;
      
        if ($email != '') {
            // Step 1: Get the Bearer Token
            $client_data = DB::table('client_key')->where('status',1)->first();
            $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
        
            $tokenResponse = Http::post($tokenUrl, [
                'grant_type' => $client_data->grant_type,
                'client_id' => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope' => $client_data->scope,

                // 'grant_type' => "client_credentials",
                // 'client_id' => "23589fa0-5baf-41c0-96dc-1762a32df9cf",
                // 'client_secret' => "EOgEitQtDfpGGPvPrZMPY5CPbS0l753MVKiekJex",
                // 'scope' => "*",
            ]);
                // dd($tokenResponse);
            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                
                // dd($tokenData);
                // Check if 'access_token' exists
                if (isset($tokenData['access_token'])) {
                    $accessToken = $tokenData['access_token'];
// dd($accessToken);
                    // Step 2: Use the Bearer Token in the Second Request
                    $searchUrl = "https://api.mdintegrations.com/v1/partner/patients/search";
                    $searchResponse = Http::withToken($accessToken)->post($searchUrl, [
                        'search' => $email,
                        'is_sandbox' => false,
                    ]);
                    // dd($searchResponse);
                    $is_patient = DB::table('patients')->where('email', $email)->count();

                    // if ($is_patient > 0) {
                        if ($searchResponse->successful()) {
                            $patientData = $searchResponse->json();
                            // dd($patientData);
                                 // Extract patient_id from the first result
                            if (!empty($patientData[0]['patient_id'])) {

                                $patientId = $patientData[0]['patient_id'];
                                $first_name =  $patientData[0]['first_name'] ?? '';
                                $last_name =  $patientData[0]['last_name'] ?? '';
                                $email =  $patientData[0]['email'] ?? '';
                                $date_of_birth =  $patientData[0]['date_of_birth'] ?? '';
                                $gender =  $patientData[0]['gender'] ?? '';
                                return [
                                    'patient_id'     => $patientId,
                                    'first_name'     => $first_name,
                                    'last_name'      => $last_name,
                                    'email'          => $email,
                                    'date_of_birth'  => $date_of_birth,
                                    'gender'         => $gender,
                                    'patientData' => $patientData,
                                ];
                                // return response()->json([
                                //     'status' => 'success',
                                //     'patient_id' => $patientId,
                                //     'first_name' => $first_name,
                                //     'last_name' => $last_name,
                                //     'email' => $email,
                                //     'date_of_birth' => $date_of_birth,
                                // ]);
                            } else {
                                $patientId = "";
                                return $patientId;
                                // return response()->json([
                                //     'status' => 'error',
                                //     'message' => 'No patient ID found',
                                // ]);
                            }
                        } else {
                            return response()->json([
                                'status'  => 'error',
                                'message' => 'Failed to search patients',
                                'error'   => $searchResponse->body(),
                            ], $searchResponse->status());
                        }
                   
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to fetch token: invalid response structure',
                        'error' => $tokenData,
                    ], 500);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch token',
                    'error' => $tokenResponse->body(),
                ], $tokenResponse->status());
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Please fill out all the required details!',
            ]);
        }
    }

    public function check_coupons_test(Request $request)
    {
         // Validate required inputs
        $validator = Validator::make($request->all(), [
            'promo_codes' => 'required|string',
            'amount' => 'required|numeric',
            'product_count' => 'required|integer',
        ]);
    
        if ($validator->fails()) {
            // Return validation errors
            return response()->json([
                'status' => 0,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $promo_code = $request->promo_codes;
        $amount = $request->amount;
        $product_count = $request->product_count;
    
        // Fetch coupons using the GetAllCoupons2 method
        $coupon_check_response = $this->GetAllCoupons2();
        $coupon_data = $coupon_check_response->getData();
    
        // Check if coupons are available
        if (!isset($coupon_data->data) || !is_array($coupon_data->data)) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch coupon data from GetAllCoupons2 API.',
            ]);
        }
    
        // Find the matching promo code
        $matching_coupon = collect($coupon_data->data)->firstWhere('name', $promo_code);
    
        if (!$matching_coupon) {
            return response()->json([
                'status' => 0,
                'message' => 'Promo code does not exist in the available coupons.',
            ]);
        }
    
        $coupon_id = $matching_coupon->coupons_id;
    
        // Fetch app key and secret
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'App keys not found in the database.',
            ]);
        }
    
        $app_key = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;
        $domain = 'whitelabelmd.sticky.io';
        $url = "https://{$domain}/api/v2/coupons/{$coupon_id}";
    
        // Fetch coupon details from the external API
        $response = Http::withBasicAuth($app_key, $app_secret)
            ->withoutVerifying()
            ->get($url);
    
        if (!$response->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve coupon details from the external service.',
                'error' => $response->json(),
            ]);
        }
    
        $responseData = $response->json();
        $couponDetails = $responseData['data'] ?? [];
    
        // Validate coupon details
        if (empty($couponDetails)) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid coupon details received.',
            ]);
        }
    
        $discount_percent = $couponDetails['discount_pct'] ?? "0.00";
        $discount_amount = $couponDetails['discount_amt'] ?? "0.00";
        $minimum_purchase = $couponDetails['minimum_purchase'] ?? "0.00";
        $use_count = $couponDetails['use_count'] ?? 0;
        $limit = $couponDetails['limits']['total'] ?? null;
        $expires_at = $couponDetails['expires_at']['date'] ?? null;
    
        // Check if minimum purchase is satisfied
        if ($amount < $minimum_purchase) {
            return response()->json([
                'status' => 0,
                'message' => 'The entered amount does not meet the minimum purchase requirement.',
                'minimum_purchase' => $minimum_purchase,
            ]);
        }
    
        // Validate coupon usage limits
        if (!is_null($limit)) {
            if ($product_count > $limit) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Coupon limit exceeded.',
                    'limit' => $limit,
                ]);
            }
    
            if ($use_count >= $limit) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Coupon usage limit reached.',
                ]);
            }
        }
    
        // Return successful response
        $promo_code_data = [
            "id" => $couponDetails['id'] ?? null,
            "use_count" => $use_count,
            "code" => $promo_code,
            "is_active" => $couponDetails['is_active'] ?? 0,
            "is_deleted" => $couponDetails['is_deleted'] ?? 0,
            "created_at" => $couponDetails['created_at'] ?? null,
            "created_by" => $couponDetails['created_by'] ?? null,
        ];
    
        return response()->json([
            'status' => 1,
            'message' => 'Promo code matches successfully and is applicable.',
            'data' => $promo_code_data,
            'discount_percent' => $discount_percent,
            'discount_amount' => $discount_amount,
            'minimum_purchase' => $minimum_purchase,
            'limit' => $limit,
        ]);
    }
    

    private function GetAllCoupons2()
    {
        // Fetch API keys and site data
        $app_key_data = DB::table('app_keys')->first();
        $site_data = DB::table('site')->first();
    
        if (!$app_key_data || !$site_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API keys or site data not found.',
            ], 500);
        }
    
        $app_key = $app_key_data->app_key;
        $api_ext = $app_key_data->app_secret;
        $campaign_id = $site_data->campaign_id;
    
        try {
            // Make the API call
            $couponResponse = Http::withBasicAuth($app_key, $api_ext)
                ->get("https://whitelabelmd.sticky.io/api/v2/campaigns/{$campaign_id}");
    
            if ($couponResponse->successful()) {
                // Extract coupon_profiles from the response
                $coupons = $couponResponse->json()['data']['coupon_profiles'] ?? [];
    
                // Simplify the coupon data
                $formattedCoupons = array_map(function ($coupon) {
                    return [
                        'coupons_id' => $coupon['id'],
                        'name' => $coupon['name'],
                        // 'discount_percent' => $coupon['discount_percent'],
                        // 'expires_at' => $coupon['expires_at']['date'] ?? null,
                        // 'is_active' => $coupon['is_active'],
                    ];
                }, $coupons);
    
                return response()->json([
                    // 'status' => 1,
                    // 'message' => 'Coupon data retrieved successfully.',
                    'data' => $formattedCoupons,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve coupon data.',
                    'error' => $couponResponse->json(),
                ], $couponResponse->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'message' => 'An error occurred while retrieving coupon data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

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
                  $billingState,$billingZip,$billingCountry,$billingSameAsShipping,$billingFirstName,$billingLastName,$gateway_id,$billing_model_id)
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
                            //   "billing_model_id" => 3,
                              "billing_model_id" => $billing_model_id,
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
                              "billing_model_id" => $billing_model_id,
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
                //   "event_id" => $event_data->event_id,
              ];
          
              $response = Http::withBasicAuth($app_key, $app_secret)
                              ->post('https://whitelabelmd.sticky.io/api/v1/member_create', $payload);
          
              if ($response->successful()) {
                  $responseData = $response->json();
          
                  if (isset($responseData['response_code']) && $responseData['response_code'] === "100") {
                      $temp_password = $responseData['temp_password'] ?? null;
                      $user_data = DB::table('user')->where('email', $email)->first();
          
                      if ($user_data && $temp_password) {
                          // Update user's password
                          DB::table('user')->where('email', $email)->update([
                              'password' => Hash::make($temp_password),
                          ]);
          
                          // Send welcome email with temp password
                          //add email ma password mokl va mate thase
                          // $this->sendWelcomeEmail($email, $temp_password, $user_data->first_name);
      
                      }
          
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
      
    public function add_user_check_product_agent_test(Request $request)
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
                          $request->billing_model_id ?? null
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
                      $billingZip,$billingCountry,$billingSameAsShipping,$billingFirstName,$billingLastName,$gateway_id,$billing_model_id)
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
                  $billingLastName,$gateway_id,$billing_model_id
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
      
      
          // ==============>end create order code<======

          public function get_products_test(Request $request)
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

    
}