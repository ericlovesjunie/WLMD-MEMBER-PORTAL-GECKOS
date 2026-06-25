<?php
   
   namespace App\Console\Commands;
   use Illuminate\Support\Facades\Log;
   use Illuminate\Console\Command;
   use Illuminate\Support\Facades\DB;
   use Illuminate\Support\Facades\Http;
   use App\Models\Order;
   use App\Models\OrderLineItem;
   use App\Models\Subscription;
   use Illuminate\Support\Facades\Mail;

   use Carbon;
   class MainCron extends Command
   {
       protected $signature = 'process:mainorder';
       protected $description = 'Process mainorder for users';
   
       public function __construct()
       {
           parent::__construct();
       }
   
       public function handle()
       {
        \Log::info('Cron job started: creating orders');
            // $this->sendNextCheckInLink();
            $this->clearOldLogs();
            // $this->addcasesimage();// jyare case add  kari tyare aa unhide karva nu che karnke ama cronjob  na karne table ma image na hoy te mote all image folderma ave che and spzs roke te mate te hide rakhel che
            // $this->createOrder_2();//this is add  direct add order
            // $this->clearOldLogs();
            // $this->create_tokens();
            // $this->add_user_uniqid();
            
           
            
       }

       protected function clearOldLogs()
       {
           $logFile = storage_path('logs/laravel.log');
   
           if (file_exists($logFile)) {
               $fileModifiedTime = Carbon::createFromTimestamp(filemtime($logFile));
               $now = Carbon::now();
   
               // Check if the log file is older than 1 day
            //    if ($fileModifiedTime->diffInDays($now) >= 1) {
                file_put_contents($logFile, '');
                \Log::info('Laravel log file cleaned up');
            //    }
           }
       }

    //    protected function clearOldLogs()
    //    {
    //        $logFile = storage_path('logs/laravel.log');
   
    //        if (file_exists($logFile)) {
    //            $fileModifiedTime = Carbon::createFromTimestamp(filemtime($logFile));
    //            $now = Carbon::now();
   
    //            // Check if the log file is older than 1 day
    //            if ($fileModifiedTime->diffInDays($now) >= 1) {
    //             file_put_contents($logFile, '');
    //             \Log::info('Laravel log file cleaned up');
    //            }
    //        }
    //    }

    //    protected function sendNextCheckInLink()
    //    {
    //        // Fetch orders that need to be processed
    //        $orderData = DB::table('orders')
    //            ->whereNotNull('form_id')
    //            ->where('status', 5)
    //            ->orderBy('created_at', 'desc')
    //            ->get();
       
    //        if ($orderData->isEmpty()) {
    //            Log::info("No orders found for check-in processing.");
    //            return;
    //        }
       
    //        foreach ($orderData as $data) {
    //            $today = now();  // Get current date/time using Carbon
       
    //            // Check if next billing date is due and check-in hasn't occurred
    //            if ($data->next_billing_date <= $today && $data->is_check_in == 0) {
    //                Log::info("Processing order: {$data->order_id} for user: {$data->user_id}");
       
    //                // Fetch product details from the user cart
    //                $product = DB::table('user_cart_product')
    //                    ->where('form_id', $data->form_id)
    //                    ->where('user_id', $data->user_id)
    //                    ->first();
       
    //                if ($product) {
    //                    $currentProductId = $product->product_id;
       
    //                    // Get the current product record and determine the next product
    //                    $currentRecord = DB::table('category_product_data')
    //                        ->where('product_id', $currentProductId)
    //                        ->first();
       
    //                    $nextProductId = null;
       
    //                    if ($currentRecord) {
    //                        $nextRecord = DB::table('category_product_data')
    //                            ->where('index', '>', $currentRecord->index)
    //                            ->where('category_product_id', $currentRecord->category_product_id)
    //                            ->orderBy('index', 'asc')
    //                            ->first();
       
    //                        if ($nextRecord) {
    //                            $nextProductId = $nextRecord->product_id;
    //                        }
    //                    }
       
    //                    // Get the URL for the next form if available
    //                    $nextFormData = DB::table('product')
    //                        ->select('form_url', 'form_id')
    //                        ->where('product_id', $nextProductId)
    //                        ->first();
       
    //                    $nextFormUrl = $nextFormData->form_url ?? '';
    //                    $nextFormId = $nextFormData->form_id ?? '';
       
    //                    if (!empty($nextFormUrl)) {
    //                        // Get user data
    //                        $userData = DB::table('user')
    //                            ->where('user_id', $data->user_id)
    //                            ->first();
       
    //                        if ($userData) {
    //                            $emailFrom = 'bgwhitelabel@gmail.com';
    //                            $senderEmail = 'info@levelupmeds.com';
    //                            $url = "https://gokulnair.com/jalpesh/memberportal_weightloss/";
       
    //                            // Send the email with check-in link
    //                            try {
    //                                Mail::send('Mail.CheckInLink', [
    //                                    'email_sub' => $emailFrom,
    //                                    'next_form_url' => $nextFormUrl,
    //                                    'first_name' => $userData->first_name,
    //                                    'sender_email' => $senderEmail,
    //                                    'url' => $url,
    //                                    'email' => $userData->email
    //                                ], function ($message) use ($userData, $emailFrom) {
    //                                    $message->to($userData->email)
    //                                        ->from($emailFrom)
    //                                        ->subject('Next Check-In Link');
    //                                });
       
    //                                // Log successful email
    //                                Log::info("Check-in link email sent to user: {$userData->email} for order: {$data->order_id}");
       
    //                                // Update the order status as checked-in
    //                                DB::table('orders')
    //                                    ->where('order_id', $data->order_id)
    //                                    ->update([
    //                                        'is_check_in' => 1,
    //                                        'updated_at' => now()
    //                                    ]);
    //                            } catch (\Exception $e) {
    //                                Log::error("Failed to send check-in email for user: {$userData->email} with error: {$e->getMessage()}");
    //                            }
    //                        }
    //                    }
    //                }
    //            } else {
    //                Log::info("Order: {$data->order_id} does not meet check-in criteria.");
    //            }
    //        }
       
    //        Log::info("Cron job completed: sendNextCheckInLink.");
    //    }

    
   
    protected function addcasesimage()
    {
       // Retrieve the data from the 'submissions' table
       $existing_user_ids = DB::table('cases_images')->pluck('user_id')->unique()->toArray();

       // Retrieve the data from the 'submissions' table, excluding users already in cases_images
       $table_data = DB::table('submissions')
           ->whereNotNull('user_id')
           ->whereNotIn('user_id', $existing_user_ids)
           // ->select('id')
           ->get();
        
       // Initialize an array to store the results
       $results = [];
   
       // Loop through each record in the table data
       foreach ($table_data as $record) {
           // Decode the 'answers' JSON field
           $answers = json_decode($record->answers, true);
           
           // Iterate over each answer to find the one with name 'photo_fullbody'
           foreach ($answers as $value) {
               // Check if 'answer' and 'name' keys are present
               if (isset($value['name']) && $value['name'] === 'photo_fullbody' && isset($value['answer'])) {
   
                   $user_id = $record->user_id;
                   $form_id = $record->form_id;
   
                   // Check if the submission already exists in the database
                   $exists = DB::table('cases_images')
                       ->where('user_id', $user_id)
                       ->where('form_id', $form_id)
                       ->exists();
   
                   if (!$exists) {
                       // Define the image URL with API key
                       $imageUrl = $value['answer'] . '?apiKey=06dce4a4f955cdcb79aa14bedb5350d5';
   
                       // Get the image content
                       $response = Http::get($imageUrl);
   
                       if ($response->ok()) {
                           // Generate a unique filename for the image
                           $filename = time() . '_' . basename(parse_url($imageUrl, PHP_URL_PATH));
                           $filePath = public_path('assets/cases_images/' . $filename);
   
                           // Save the image to the file system
                           try {
                               file_put_contents($filePath, $response->body());
   
                               // Upload the image to the external API
                               $accessToken = $this->create_tokens(); // Ensure this method returns a valid token
   
                               if ($accessToken) {
                                   // Upload the image
                                   $uploadResponse = Http::withToken($accessToken)
                                       ->attach('file', file_get_contents($filePath), $filename)
                                       ->post('https://api.mdintegrations.com/v1/partner/files', [
                                           'name' => $filename,
                                       ]);
   
                                   if ($uploadResponse->ok()) {
                                       $file_id = $uploadResponse->json('file_id'); // Extract file_id from the API response
   
                                       // Fetch case data
                                       $case_data = DB::table('cases')
                                           ->where('user_id', $user_id)
                                           ->where('form_id', $form_id)
                                           ->first();
   // dd($case_data);
                                       if ($case_data) {

                                          // Construct the case URL with case_id and file_id
                                          $caseUrl = "https://api.mdintegrations.com/v1/partner/cases/{$case_data->case_id}/files/{$file_id}";
                                       
                                          // Link the file to the case
                                          $case_uploadResponse = Http::withToken($accessToken)
                                              ->post($caseUrl);
                                        
   // dd($case_uploadResponse);
                                           if ($case_uploadResponse->ok()) {
                                               // Update the database with the file_id
                                               DB::table('cases_images')->insert([
                                                   'submissions_id' => $record->submissions_id,
                                                   'form_id'        => $record->form_id,
                                                   'img_video'      => $filename,
                                                   'user_id'        => $record->user_id,
                                                   'file_id'        => $file_id,
                                                   'created_at'     => now(),
                                               ]);
   
                                               // Store the form_id, user_id, and image URL in the results array
                                               $results[] = [
                                                   'form_id' => $record->form_id,
                                                   'user_id' => $record->user_id,
                                                   'image_url' => $filename,
                                               ];
                                           }
                                       }
                                   }
                               }
                           } catch (FileNotFoundException $e) {
                               // Handle file not found error
                               // Log error or handle as necessary
                               \Log::error('File not found: ' . $e->getMessage());
                           }
                       }
                   }
                   
                   break; // Exit the loop once we find the photo_fullbody entry
               }
           }
       }
   
       // Output the results
       // dd($results);
    }

       protected function create_tokens()
       {
           $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
           $tokenResponse = Http::post($tokenUrl, [
               'grant_type' => 'client_credentials',
               'client_id' => 'ad6c1f2b-6c82-4ffc-ab9d-e4c3f7bcfd78',
               'client_secret' => 'bmZ40l61CgPL2OhUk0voKFb0uMWRmxsHDqGhDwVU',
               'scope' => '*',
           ]);
   
           if ($tokenResponse->successful()) {
               return $tokenResponse['access_token'];
           } else {
               Log::error('Failed to fetch access token: ' . $tokenResponse->body());
               return null;
           }
       }


       //this function now hide 
       protected function createOrder_2()
       {
           // Fetch all user_ids from the submissions table
           $submissions = DB::table('user')->get();
           if ($submissions->isEmpty()) {
               return response()->json(['status' => 'error', 'message' => 'No User found'], 404);
           }
       
           foreach ($submissions as $submission) {
               $user_id = $submission->user_id;
               // $submissions_id = $submission->submissions_id;
               // $form_id = $submission->form_id;
       
               // Fetch necessary data
               $cart_data = DB::table('user_cart_product')->where('user_id', $user_id)->first();
               if (!$cart_data) {
                   continue; // Skip if cart data is not found
               }
       
               $product_data = DB::table('product')->where('product_id', $cart_data->product_id)->first();
               $shipping_data = DB::table('shipping')->where('campaign_id', $product_data->campaign_id)->first();
               $campaign_data = DB::table('campaign')->where('campaign_id', $product_data->campaign_id)->first();
               // dd($campaign_data);
               $user_data = DB::table('user')->where('user_id', $cart_data->user_id)->first();
               $user_tocken = DB::table('user')->select('payment_token')->where('user_id', $cart_data->user_id)->first();
               $card_data = DB::table('user_payment_card_details')->where('user_card_id', $cart_data->user_card_id)->first();
               $address_data = DB::table('user_address')->where('address_id', $cart_data->address_id)->first();
      
              // Ensure all necessary data is available
                 // if (!$product_data || !$shipping_data || !$user_data || !$card_data || !$address_data) {
               if (!$product_data || !$shipping_data || !$user_data || !$address_data) {
                   continue; // Skip if any data is incomplete
               }
               if($user_tocken ==null)
               {
                   continue;
               }
               // dd($submissions_id);
               $pro_id = $product_data->sticky_product_id;
            
               if($user_data->payment_token == null)
               {
                   $patientData = [
                       "firstName" => $user_data->first_name,
                       "lastName" => $user_data->last_name,
                       "currency" => "JPYY",
                       "billingFirstName" => $user_data->first_name,
                       "billingLastName" => $user_data->last_name,
                       "billingAddress1" => $address_data->address,
                       "billingCity" => $address_data->city_name,
                       "billingState" => $address_data->state_name,
                       "billingZip" => $address_data->zip_code,
                       "billingCountry" => $address_data->country,
                       'phone' => $user_data->phone_number,
                       'email' => $user_data->email,
                       // 'payment_token' => $user_data->payment_token,
                       'creditCardType' => "Discover",
                       'creditCardNumber' => $card_data->card_no,
                       'expirationDate' => $card_data->ex_month . $card_data->ex_year,
                       'CVV' => $card_data->cvv_no,
                       'shippingId' => $shipping_data->shipping_id,
                       'tranType' => "Sale",
                       'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                       'campaignId' => $product_data->campaign_id,
                       'billingSameAsShipping' => "YES",
                       'shippingAddress1' => $address_data->address,
                       'shippingCity' => $address_data->city_name,
                       'shippingState' => $address_data->state_name,
                       'shippingZip' => $address_data->zip_code,
                       'shippingCountry' => $address_data->country,
                       'preserve_force_gateway' => 1,
                       "offers" => [
                           [
                               "offer_id" => $campaign_data->offer_id,
                               "product_id" => $pro_id,
                               "billing_model_id" => 3,
                               "quantity" => 1,
                           ]
                       ]
                   ];
               }
               else
               {
                   $patientData = [
                       "firstName" => $user_data->first_name,
                       "lastName" => $user_data->last_name,
                       "currency" => "JPYY",
                       "billingFirstName" => $user_data->first_name,
                       "billingLastName" => $user_data->last_name,
                       "billingAddress1" => $address_data->address,
                       "billingCity" => $address_data->city_name,
                       "billingState" => $address_data->state_name,
                       "billingZip" => $address_data->zip_code,
                       "billingCountry" => $address_data->country,
                       'phone' => $user_data->phone_number,
                       'email' => $user_data->email,
                       'payment_token' => $user_data->payment_token,
                       'creditCardType' => "Discover",
                       // 'creditCardNumber' => $card_data->card_no,
                       // 'expirationDate' => $card_data->ex_month . $card_data->ex_year,
                       // 'CVV' => $card_data->cvv_no,
                       'shippingId' => $shipping_data->shipping_id,
                       'tranType' => "Sale",
                       'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                       'campaignId' => $product_data->campaign_id,
                       'billingSameAsShipping' => "YES",
                       'shippingAddress1' => $address_data->address,
                       'shippingCity' => $address_data->city_name,
                       'shippingState' => $address_data->state_name,
                       'shippingZip' => $address_data->zip_code,
                       'shippingCountry' => $address_data->country,
                       'preserve_force_gateway' => 1,
                       "offers" => [
                           [
                               "offer_id" => $campaign_data->offer_id,
                               "product_id" => $pro_id,
                               "billing_model_id" => 3,
                               "quantity" => 1,
                           ]
                       ]
                   ];
               }
       
               // Check if order already exists
               $exists = DB::table('orders')
                   ->where('sticky_product_id', $product_data->sticky_product_id)
                   ->where('user_id', $user_id)
                   ->exists();
       
               if ($exists) {
                   continue; // Skip if order already exists
               }
               // dd($patientData);
               // Create new order
               $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
               $site_key    = $site_data->site_key;
               $site_secret = $site_data->site_secret;  
   
               // $response = Http::withBasicAuth('levelupmeds_bg', 'ZbTXGH89qaBvpP')
               $response = Http::withBasicAuth($site_key, $site_secret)
                   ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                   $responseData = $response->json();
                   // dd($responseData);
                if ($responseData['response_code'] == 10920 && $responseData['error_found'] == "1") {
                    continue; // Skip user if the payment token is invalid or expired
                }
               if ($response->successful()) {
                   DB::table('user_cart_product')
                       ->where('user_id', $user_id)
                       ->where('product_id', $product_data->product_id)
                       ->update(['status' => 2]);
       
                   $responseData = $response->json();
       
                   // Save the order
                   $order = new Order();
                   $order->gateway_id = $responseData['gateway_id'];
                   $order->sticky_product_id = $product_data->sticky_product_id;
                   $order->user_id = $user_id;
                   // $order->submissions_id = $submissions_id;
                   // $order->form_id = $form_id;
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
                   $order->prepaid_match = $responseData['prepaid_match'];
                   $order->resp_msg = $responseData['resp_msg'];
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
               } else {
                    Log::error('Order creation failed', ['response' => $response->json()]);
               }
           }
       
        //    return response()->json(['status' => 'success', 'message' => 'Orders processed successfully']);
           Log::error('Orders processed successfully ');
       }

      

    //    protected function stickyproductadd()
    //    {
    //     $site_data = DB::table('site')->where('campaign_id','!=',null)->get();
    //     // dd($site_data);
    //     foreach ($site_data as $s_data)
    //     {
    //         $campaign_id = $s_data->campaign_id;
    //         $new_products = 0;
        
    //         try {
    //             $response = Http::withBasicAuth('levelupmeds_bg', 'ZbTXGH89qaBvpP')
    //                 ->post('https://whitelabelmd.sticky.io/api/v1/campaign_view', ['campaign_id' => $campaign_id]);
        
    //                 // dd($response);
    //             if (!$response->successful()) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => $response->json() ?? 'Failed to fetch data from API'
    //                 ], $response->status());
    //             }
        
    //             $data = $response->json();
    //             // dd($data);
    //             if ($data['response_code'] !== '100') {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'API returned an error: ' . ($data['response_code'] ?? 'Unknown error')
    //                 ], 400);
    //             }
        
    //             // Insert or update campaign data
    //             DB::table('campaign')->updateOrInsert(
    //                 ['campaign_id' => $campaign_id],
    //                 [
    //                     'campaign_name' => $data['campaign_name'] ?? '',
    //                     'campaign_description' => $data['campaign_description'] ?? '',
    //                     'gateway_id' => $data['gateway_id'] ?? '',
    //                     'is_payment_routed' => $data['is_payment_routed'] ?? '0',
    //                     'payment_router_id' => $data['payment_router_id'] ?? '',
    //                     'offer_id' => $data['offer_id'] ?? '',
    //                     'countries' => $data['countries'] ?? '',
    //                     'payment_name' => json_encode($data['payment_name'] ?? [])
    //                 ]
    //             );

    //             DB::table('site')->where('campaign_id',$campaign_id)->update([
    //                 'site_name' => $data['campaign_name'] ?? '',
    //            ]);
        
    //             // Insert or update shipping data
    //             if (isset($data['shipping']) && is_array($data['shipping'])) {
    //                 foreach ($data['shipping'] as $shipping) {
    //                     DB::table('shipping')->updateOrInsert(
    //                         ['shipping_id' => $shipping['shipping_id'], 'campaign_id' => $campaign_id],
    //                         [
    //                             'shipping_name' => $shipping['shipping_name'] ?? '',
    //                             'shipping_description' => $shipping['shipping_description'] ?? '',
    //                             'shipping_initial_price' => $shipping['shipping_initial_price'] ?? '0.00',
    //                             'shipping_recurring_price' => $shipping['shipping_recurring_price'] ?? '0.00'
    //                         ]
    //                     );
    //                 }
    //             }
        
    //             // Insert product data
    //             if (isset($data['products']) && is_array($data['products'])) {
    //                 foreach ($data['products'] as $product) {
    //                     $sticky_product_id = $product['product_id'] ?? null;
    //                     $product_name = $product['product_name'] ?? null;
        
    //                     if (!$sticky_product_id || !$product_name) {
    //                         continue; // Skip this product if required fields are missing
    //                     }
        
    //                     $exists = DB::table('product')->where('sticky_product_id', $sticky_product_id)->exists();
        
    //                     if (!$exists) {
                            

    //                         $product_id = DB::table('product')->insertGetId([
    //                             'sticky_product_id' => $sticky_product_id,
    //                             'product_name'      => $product_name,
    //                             // 'uniq_id'           => $uniq_id,
    //                             'campaign_id'       => $campaign_id
    //                         ]);
    //                         // site_id
    //                           // Construct the checkout URL
    //                           $checkout_url = $s_data->site_url . $product_id;
       
    //                           // Check if the uniq_id is unique before inserting
    //                           $uniq_id = $product_id; // Assuming you want to use product_id as uniq_id
      
    //                           // Ensure uniq_id is unique
    //                           while (DB::table('product')->where('uniq_id', $uniq_id)->exists()) {
    //                               // If the uniq_id is not unique, generate a new one
    //                               $uniq_id++; // Simple increment for uniqueness; adjust as necessary for your use case
    //                           }
    //                         if ($s_data->is_site == 1) {
    //                             DB::table('product')->where('product_id', $product_id)->update([
    //                                 'checkout_url' => $checkout_url,
    //                                 'site_id'             => $s_data->id,
    //                                 // 'main_site_id'         => $s_data->site_id,
    //                                 'uniq_id'              => $uniq_id,
    //                             ]);
    //                         }
    //                         else
    //                         {
    //                          DB::table('product')->where('product_id', $product_id)->update([
    //                              // 'checkout_url' => $checkout_url,
    //                              'site_id'                 => $s_data->id,
    //                             //  'main_site_id'            => $s_data->site_id,
    //                              // 'uniq_id'                 => $uniq_id,
    //                          ]);
    //                         }
        
    //                         $new_products++;
    //                     }
    //                 }
    //             }
        
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => $new_products . ' new product(s) added successfully!'
    //             ]);
        
    //         } catch (\Exception $e) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => 'An error occurred: ' . $e->getMessage()
    //             ], 500);
    //         }
    //     }
    //    }
       

    protected function add_user_uniqid()
    {
        $webhook_data = DB::table('webhook')
                        ->select('submissions_id','form_id','unique_id','user_id')
                        ->where('unique_id','!=',null)
                        ->get();

        if(count($webhook_data) > 0)
        {
                foreach($webhook_data as $data)
                {
                    $is_sub = DB::table('submissions')
                                ->where('submissions_id',$data->submissions_id)
                                ->where('form_id',$data->form_id)
                                ->first();
                    if($is_sub)
                    {
                        DB::table('submissions')
                                ->where('submissions_id',$data->submissions_id)
                                ->where('form_id',$data->form_id)
                                ->update([
                                    'user_id'   => $data->user_id,
                                    'unique_id' => $data->unique_id,
                                ]);

                            $result['status']           = 1;
                            $result['message']          = "Great!Successfully add record!";
                    }
                }
        }
        return response()->json($result);
     
    }

      

    
   





}