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
   class DemoCron extends Command
   {
       protected $signature = 'process:order';
       protected $description = 'Process order for users';
   
       public function __construct()
       {
           parent::__construct();
       }
   
       public function handle()
       {
           try {
            //    $this->addSubmissionData();
            //    $this->stickyproductadd();//this is add sticky product
            //    $this->updateproductdata();//this is add  sticky product update
            //    $this->createOrSearchPatients();//this use to create or search pation
            //    $this->createCase();
            //    $this->addcasesimage();
            //    $this->addFormQuestion();
            //    $this->sendNextCheckInLink();
            //    $this->create_tokens();
               $this->clearOldLogs();
               
            //    $this->customfieldnextcheckinlink();
       
               $submissions = DB::table('submissions')->get();
               if ($submissions->isEmpty()) {
                   Log::error('No submissions found');
                   return;
               }
       
               foreach ($submissions as $submission) {
                $user_id = $submission->user_id;
                $submissions_id = $submission->submissions_id;
                $form_id = $submission->form_id;
        
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
    // dd($site_data);
                // $response = Http::withBasicAuth('wlmd_mensrx', 'MTHeCRtqXWBaU')
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
                    $order->submissions_id = $submissions_id;
                    $order->form_id = $form_id;
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
                    return response()->json(['status' => 'error', 'message' => $response->json()], $response->status());
                }
            }
       
               Log::info('Orders processed successfully');
           } catch (\Exception $e) {
               Log::error('Error processing orders', ['exception' => $e->getMessage()]);
           }
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


       protected function addSubmissionData()
       {
           // Get all form IDs from the 'product' table
        //    $formIds = DB::table('product')->select('form_id')->get();
           $formIds = DB::table('product')->select('form_id')->where('form_id','!=',"")->get();
       
           // Your API key
           $apiKey = '06dce4a4f955cdcb79aa14bedb5350d5';

           if(count($formIds) > 0)
           {
            foreach ($formIds as $form) {
                // Construct the API URL for each form ID
                $formId = $form->form_id;
                $url = "https://forms.whitelabelmd.com/API/form/{$formId}/submissions?apiKey={$apiKey}";
        
                // Fetch the submission data
                // $response = file_get_contents($url);

                $response = Http::get($url, [
                    'apiKey' => $apiKey,
                ]); 
        
                // Decode the JSON response
                $data = json_decode($response, true);
        
                if ($data['responseCode'] == 200 && isset($data['content']) && is_array($data['content'])) {
                    $newSubmissions = 0;
        
                    foreach ($data['content'] as $submission) {
                        $submissionId = $submission['id'];
        
                        // Check if the submission already exists
                        $exists = DB::table('submissions')->where('submissions_id', $submissionId)->exists();
        
                        if (!$exists) {
                            // Extract submission details
                            $formId = $submission['form_id'];
                            $ip = $submission['ip'];
                            $new = $submission['new'];
                            $flag = $submission['flag'];
                            $notes = $submission['notes'];
                            $status = $submission['status'];
                            $createdAt = $submission['created_at'];
                            $updatedAt = $submission['updated_at'];
        
                            $answers = json_encode($submission['answers']);
        
                            $userId = null;
                            if (isset($submission['answers']) && is_array($submission['answers'])) {
                                foreach ($submission['answers'] as $answer) {
                                    if (isset($answer['name']) && $answer['name'] === 'user_id' && isset($answer['answer'])) {
                                        $userId = $answer['answer'];
                                        break;
                                    }
                                }
                            }
        
                            // Insert the submission into the database
                            DB::table('submissions')->insert([
                                'submissions_id' => $submissionId,
                                'form_id' => $formId,
                                'ip' => $ip,
                                'new' => $new,
                                'flag' => $flag,
                                'notes' => $notes,
                                'answers' => $answers,
                                'status' => $status,
                                'user_id' => $userId,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);
        
                            $newSubmissions++;
                        }
                    }
        
                    \Log::info($newSubmissions . ' new submissions added successfully for form ID ' . $formId);
                } else {
                    \Log::error('Failed to fetch submissions or no submissions available for form ID ' . $formId);
                }
            }
           }
       
          
       }
       


   

    protected function createOrSearchPatients()
    {
        $submissions = DB::table('submissions')->get();
        if ($submissions->isEmpty()) {
            \Log::error('No submissions found');
            return;
        }

        foreach ($submissions as $submission) {
            $user_id = $submission->user_id;

            

            // Fetch necessary data
            $is_user_data = DB::table('user')->where('user_id', $user_id)->where('completed',1)->first();
            if (!$is_user_data) {
                \Log::error('User data not found in the database', ['user_id' => $user_id]);
                continue;
            }

            $email = $is_user_data->email;

            if (empty($email)) {
                \Log::error('Email not found for user', ['user_id' => $user_id]);
                continue;
            }

            // Step 1: Get the Bearer Token
            $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
            $tokenResponse = Http::post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => 'ad6c1f2b-6c82-4ffc-ab9d-e4c3f7bcfd78',
                'client_secret' => 'bmZ40l61CgPL2OhUk0voKFb0uMWRmxsHDqGhDwVU',
                'scope' => '*',
            ]);

            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();

                if (isset($tokenData['access_token'])) {
                    $accessToken = $tokenData['access_token'];

                    // Step 2: Use the Bearer Token in the Second Request
                    $searchUrl = "https://api.mdintegrations.com/v1/partner/patients/search";
                    $searchResponse = Http::withToken($accessToken)->post($searchUrl, [
                        'search' => $email,
                        'is_sandbox' => true,
                    ]);

                    $is_patient = DB::table('patients')->where('email', $email)->count();

                    if ($is_patient > 0) {
                        if ($searchResponse->successful()) {
                            \Log::info('Patient found', ['data' => $searchResponse->json()]);
                        } else {
                            \Log::error('Failed to search patients', ['error' => $searchResponse->body()]);
                        }
                    } else {
                        $user_data = DB::table('user')->where('email', $email)->first();
                        if (!$user_data) {
                            \Log::error('User data not found for email', ['email' => $email]);
                            continue;
                        }
                        $submissions_tb_data = DB::table('submissions')->whereNotNull('user_id')->where('user_id', $user_id)->first();
                        // dd($submissions_tb_data);
                        // Decode the JSON data
                        $decodedData = json_decode($submissions_tb_data->answers, true);
                        $medicalConditionsAnswer = null;
                        $currentMedicationsAnswer = null;
                        $currentHeightFiInches = null;
                        $currentWeightLbs = null;
                        $currentBmi = null;
                        $currentAllergies = null;
                        
                        // Loop through the decoded data to find the answers for "medical_conditions" and "current_medications"
                        foreach ($decodedData as $data) {
                            if (isset($data['name']) && $data['name'] == 'medical_conditions') {
                                $medicalConditionsAnswer = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        
                            if (isset($data['name']) && $data['name'] == 'current_medications') {
                                $currentMedicationsAnswer = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        
                            if (isset($data['name']) && $data['name'] == 'height_fi_inches') {
                                $currentHeightFiInches = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        
                            if (isset($data['name']) && $data['name'] == 'weight_lbs') {
                                $currentWeightLbs = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        
                            if (isset($data['name']) && $data['name'] == 'bmi') {
                                $currentBmi = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        
                            if (isset($data['name']) && $data['name'] == 'pleaseListAllergies') {
                                $currentAllergies = isset($data['answer']) ? $data['answer'] : null; // Check if 'answer' exists
                            }
                        }

                        $user_address = DB::table('user_address')->where('user_id', $user_data->user_id)->first();
                        $createPatientUrl = "https://api.mdintegrations.com/v1/partner/patients";

                        $patientData = [
                            "bmi" => $currentBmi,
                            "height" => $currentHeightFiInches,
                            "weight" => $currentWeightLbs,
                            "allergies" => $currentAllergies,
                            "medical_conditions" => $medicalConditionsAnswer,
                            "current_medications" => $currentMedicationsAnswer,
                            "prefix" => $user_data->prefix,
                            "ssn" => $user_data->ssn,
                            "first_name" => $user_data->first_name,
                            "last_name" => $user_data->last_name,
                            "gender" => $user_data->gender,
                            "date_of_birth" => $user_data->date_of_birth,
                            "phone_number" => $user_data->phone_number,
                            "phone_type" => $user_data->phone_type,
                            "metadata" => $user_data->metadata,
                            "email" => $user_data->email,
                            'driver_license_id' => $user_data->file_id,
                            "address" => [
                                "address" => $user_address->address,
                                "address2" => $user_address->address,
                                "zip_code" => $user_address->zip_code,
                                "city_name" => $user_address->city_name,
                                "state_name" => $user_address->state_name
                            ],
                            "pregnancy" => false
                        ];

                        $createPatientResponse = Http::withToken($accessToken)->post($createPatientUrl, $patientData);

                        if ($createPatientResponse->successful()) {
                            $patientData = $createPatientResponse->json();
                            $partnerData = $patientData['partner'];

                            DB::table('patients')->insert([
                                'patient_id' => $patientData['patient_id'],
                                'partner_id' => $patientData['partner_id'],
                                'partner_name' => $patientData['partner']['name'],
                                'prefix' => $patientData['prefix'],
                                'first_name' => $patientData['first_name'],
                                'last_name' => $patientData['last_name'],
                                'metadata' => $patientData['metadata'],
                                'email' => $patientData['email'],
                                'weight' => $patientData['weight'],
                                'height' => $patientData['height'],
                                'date_of_birth' => $patientData['date_of_birth'],
                                'gender' => $patientData['gender'],
                                'active' => $patientData['active'],
                                'phone_number' => $patientData['phone_number'],
                                'phone_type' => $patientData['phone_type'],
                                'ssn' => $patientData['ssn'],
                                'created_at' => $patientData['created_at'],
                                'is_live' => $patientData['is_live'],
                                'partner' => json_encode($partnerData),
                                'user_id' => $user_data->user_id,
                                'driver_license' => $user_data->file_id
                            ]);

                            \Log::info('Patient created successfully', ['data' => $createPatientResponse->json()]);
                        } else {
                            \Log::error('Failed to create patient', ['error' => $createPatientResponse->body()]);
                        }
                    }
                } else {
                    \Log::error('Failed to fetch token: invalid response structure', ['error' => $tokenData]);
                }
            } else {
                \Log::error('Failed to fetch token', ['error' => $tokenResponse->body()]);
            }
        }
    }

    private function isJson($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    protected function createCase()
    {
        // Get patients with non-null user_id, ordered by latest entries
        $table_data = DB::table('patients')->whereNotNull('user_id')->latest()->get();
    
        foreach ($table_data as $is_data) {
            $patient_id = $is_data->patient_id;
    
            if ($patient_id != "") {
                $is_patient = DB::table('patients')->where('patient_id', $patient_id)->count();
    
                if ($is_patient > 0) {
                    $p_tb_data = DB::table('patients')->where('patient_id', $patient_id)->first();
                    $submissions_tb_data = DB::table('submissions')->where('user_id', $p_tb_data->user_id)->first();
    
                    if ($submissions_tb_data) {
                        $form_id = $submissions_tb_data->form_id;
                        $user_id = $submissions_tb_data->user_id;
    
                        $exists = DB::table('cases')->where('user_id', $user_id)->where('form_id', $form_id)->exists();
                        if (!$exists) {
                            $is_question = DB::table('form_questions')
                                ->where('options', '!=', "")
                                ->where('form_id', $form_id)
                                ->get()
                                ->keyBy('name');  // Index by name for quick lookup
    
                            $decodedData = json_decode($submissions_tb_data->answers, true);
                            $case_questions = [];
    
                            foreach ($decodedData as $data) {
                                try {
                                    if (isset($data['name'])) {
                                        $name = $data['name'];
                                        // Used strip_tags to remove HTML tags from both the questionText and data['text'] fields
                                        $questionText = isset($is_question[$name]) ? strip_tags($is_question[$name]->text) : (isset($data['text']) ? strip_tags($data['text']) : '');
                                        
                                        // Skip unwanted questions
                                        if (stripos($questionText, 'Page Break') !== false || stripos($questionText, 'user_id') !== false || stripos($questionText, 'Unique ID') !== false || (is_string($questionText) && strpos($questionText, '__flags') === 0 ) || (is_string($questionText) && strpos($questionText, 'none_') === 0 ) ) {
                                            continue;
                                        }
                            
                                        $options = isset($is_question[$name]) ? $is_question[$name]->options : null;
                                        $answer = isset($data['answer']) ? $data['answer'] : '';
                            
                                        // Replace empty string answer with "false"
                                        if ($answer === '' || $answer === ' ') {
                                            $answer = 'false';
                                        }
                            
                                        // Skip inserting answers that are "false" or start with "https://"
                                        if ($answer === [] || $answer === 'false' || (is_string($answer) && strpos($answer, 'https://') === 0)) {
                                            continue;
                                        }
                            
                                        // Handle case where the answer is an array (like for full names)
                                        if (is_array($answer)) {
                                            if (isset($answer['first']) && isset($answer['last'])) {
                                                // Concatenate first and last names
                                                $formattedAnswer = $answer['first'] . ' ' . $answer['last'];
                                            } else {
                                                // If it's a different type of array, convert it to string as before
                                                $formattedAnswer = implode(',', array_map(
                                                    fn($key, $value) => "$key:$value",
                                                    array_keys($answer),
                                                    $answer
                                                ));
                                            }
                                        } else {
                                            // Handle non-array answers
                                            $formattedAnswer = (string) $answer;
                                        }
                            
                                        // If options exist, append them to the answer
                                        if (!empty($options)) {
                                            $optionsStr = is_array($options) ? json_encode($options) : $options;
                                            $formattedAnswer .= ' --- Options: ' . $optionsStr;
                                        }
                            
                                        $questionData = [
                                            'question' => $questionText,
                                            'answer' => $formattedAnswer,
                                            'type' => 'string' // Assuming answer is always stored as string
                                        ];
                            
                                        // Ensure question is not empty
                                        if (!empty($questionData['question'])) {
                                            $case_questions[] = $questionData;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Error processing question data: ' . $e->getMessage());
                                }
                            }
                            
    
                            $accessToken = $this->create_tokens();
    
                            if ($accessToken) {
                                $caseUrl = "https://api.mdintegrations.com/v1/partner/cases";
                                $caseResponse = Http::withToken($accessToken)->post($caseUrl, [
                                    'patient_id' => $patient_id,
                                    'case_files' => [],
                                    'case_prescriptions' => [],
                                    'case_services' => [],
                                    'case_questions' => $case_questions,
                                    'diseases' => [],
                                    'tags' => [],
                                    'hold_status' => false
                                ]);
    
                                if ($caseResponse->successful()) {
                                    $CaseData = $caseResponse->json();
    
                                    DB::table('cases')->insert([
                                        'case_id'    => $CaseData['case_id'],
                                        'patient_id' => $patient_id,
                                        'user_id' => $user_id,
                                        'form_id'    => $form_id,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ]);
    
                                    Log::info('Case created successfully for patient ID: ' . $patient_id);
                                } else {
                                    $errorMessage = $caseResponse->body();
                                    Log::error('Failed to create case: ' . $errorMessage);
                                }
                            } else {
                                Log::error('Failed to fetch access token.');
                            }
                        } else {
                            Log::info('Case already exists for user ID: ' . $user_id . ' and form ID: ' . $form_id);
                        }
                    } else {
                        Log::error('No submission data found for user ID: ' . $p_tb_data->user_id);
                    }
                } else {
                    Log::error('Patient ID not found: ' . $patient_id);
                }
            }
        }
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

 


    // protected function customfieldnextcheckinlink(Request $request)
    // {
    //     $table_data = DB::table('orders')->get();
        
    //     if(count($table_data) > 0)
    //     {   
    //         foreach($table_data as $data)
    //         {
    //             $order_id = $data->order_id; // Assuming the column name is 'order_id'
                
    //             $exists = DB::table('orders_next_check_in_link')->where('order_id', $order_id)->exists();
    //                     if (!$exists) {
                            
    //                     $response = Http::withBasicAuth('levelupmeds_bg', 'ZbTXGH89qaBvpP')
    //                     ->put("https://whitelabelmd.sticky.io/api/v2/orders/{$order_id}/custom_fields", [
    //                         'custom_fields' => [
    //                             [
    //                                 'id' => 6,
    //                                 'field_name' => 'next_checkin_link',
    //                                 'value' => "https://forms.whitelabelmd.com/241993580322863?continuation_id=10"
    //                             ]
    //                         ]
    //                     ]);
                    
    //                 if ($response->successful()) {

    //                     DB::table('orders_next_check_in_link')->insert([
    //                             'order_id' => $order_id,
    //                             'created_at' =>now(),
    //                     ]);
    //                     // The request was successful
    //                     $result = $response->json();
    //                     // Process the result as needed
    //                     Log::info("Custom field updated for order {$order_id}", $result);
    //                 } else {
    //                     // The request failed
    //                     $error = $response->body();
    //                     Log::error("Failed to update custom field for order {$order_id}: {$error}");
    //                 }
    //             }


    //         }
            
    //         return response()->json(['message' => 'Custom fields updated successfully']);
    //     }
        
    //     return response()->json(['message' => 'No orders found'], 404);
    // }

 
    
    protected function sendNextCheckInLink()
    {
        // Fetch orders that need to be processed
        $orderData = DB::table('orders')
            ->whereNotNull('form_id')
            ->where('status', 5)
            ->orderBy('created_at', 'desc')
            ->get();
    
        if ($orderData->isEmpty()) {
            Log::info("No orders found for check-in processing.");
            return;
        }
    
        foreach ($orderData as $data) {
            $today = now();  // Get current date/time using Carbon
    
            // Check if next billing date is due and check-in hasn't occurred
            if ($data->next_billing_date <= $today && $data->is_check_in == 0) {
                Log::info("Processing order: {$data->order_id} for user: {$data->user_id}");
    
                // Fetch product details from the user cart
                $product = DB::table('user_cart_product')
                    ->where('form_id', $data->form_id)
                    ->where('user_id', $data->user_id)
                    ->first();
    
                if ($product) {
                    $currentProductId = $product->product_id;
    
                    // Get the current product record and determine the next product
                    $currentRecord = DB::table('category_product_data')
                        ->where('product_id', $currentProductId)
                        ->first();
    
                    $nextProductId = null;
    
                    if ($currentRecord) {
                        $nextRecord = DB::table('category_product_data')
                            ->where('index', '>', $currentRecord->index)
                            ->where('category_product_id', $currentRecord->category_product_id)
                            ->orderBy('index', 'asc')
                            ->first();
    
                        if ($nextRecord) {
                            $nextProductId = $nextRecord->product_id;
                        }
                    }
    
                    // Get the URL for the next form if available
                    $nextFormData = DB::table('product')
                        ->select('form_url', 'form_id')
                        ->where('product_id', $nextProductId)
                        ->first();
    
                    $nextFormUrl = $nextFormData->form_url ?? '';
                    $nextFormId = $nextFormData->form_id ?? '';
    
                    if (!empty($nextFormUrl)) {
                        // Get user data
                        $userData = DB::table('user')
                            ->where('user_id', $data->user_id)
                            ->first();
    
                        if ($userData) {
                            $emailFrom = 'bgwhitelabel@gmail.com';
                            $senderEmail = 'info@levelupmeds.com';
                            $url = "https://gokulnair.com/jalpesh/memberportal_weightloss/";
    
                            // Send the email with check-in link
                            try {
                                Mail::send('Mail.CheckInLink', [
                                    'email_sub' => $emailFrom,
                                    'next_form_url' => $nextFormUrl,
                                    'first_name' => $userData->first_name,
                                    'sender_email' => $senderEmail,
                                    'url' => $url,
                                    'email' => $userData->email
                                ], function ($message) use ($userData, $emailFrom) {
                                    $message->to($userData->email)
                                        ->from($emailFrom)
                                        ->subject('Next Check-In Link');
                                });
    
                                // Log successful email
                                Log::info("Check-in link email sent to user: {$userData->email} for order: {$data->order_id}");
    
                                // Update the order status as checked-in
                                DB::table('orders')
                                    ->where('order_id', $data->order_id)
                                    ->update([
                                        'is_check_in' => 1,
                                        'updated_at' => now()
                                    ]);
                            } catch (\Exception $e) {
                                Log::error("Failed to send check-in email for user: {$userData->email} with error: {$e->getMessage()}");
                            }
                        }
                    }
                }
            } else {
                Log::info("Order: {$data->order_id} does not meet check-in criteria.");
            }
        }
    
        Log::info("Cron job completed: sendNextCheckInLink.");
    }



    protected function addFormQuestion()
    {
        // Fetch all form IDs from the 'product' table
        $formIds = DB::table('product')->select('form_id')->get();

        // API key
        $apiKey = '06dce4a4f955cdcb79aa14bedb5350d5';

        $newQuestions = 0;

        // Loop through each form ID
        foreach ($formIds as $form) {
            $formId = $form->form_id;

            // Construct the API URL for each form ID
            $url = "https://whitelabelmd.jotform.com/API/form/{$formId}/questions?apiKey={$apiKey}";

            // Fetch the question data from the API
            $response = file_get_contents($url);

            // Decode the JSON response
            $data = json_decode($response, true);

            if ($data['responseCode'] == 200 && isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $question) {
                    $qName = $question['name'];

                    // Check if the question already exists in the database
                    $exists = DB::table('form_questions')->where('name', $qName)->where('form_id', $formId)->exists();

                    if (!$exists) {
                        $options = isset($question['options']) ? $question['options'] : "";
                        $name = $question['name'];
                        $text = $question['text'];

                        // Insert into the database
                        DB::table('form_questions')->insert([
                            'options' => $options,
                            'form_id' => $formId,
                            'name' => $name,
                            'text' => $text,
                        ]);

                        $newQuestions++;
                    }
                }
            } else {
                Log::error('Failed to fetch questions or no questions available for form ID ' . $formId);
            }
        }

        // Output the result to the console
        $this->info($newQuestions . ' new question(s) added successfully!');
    }

    protected function updateproductdata()
    {
        try {
            // Retrieve all product IDs from the database
            $product_ids = DB::table('product')->where('taxable','=',null)->latest()->pluck('sticky_product_id')->toArray();
            // dd($product_ids);
            if (empty($product_ids)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No products found in the database'
                ], 404);
            }
    
            $total_updated_products = 0;
            $failed_updates = 0;

         
    
            foreach ($product_ids as $product_id) {
                $curl = curl_init();

                $product_data = DB::table('product')->where('sticky_product_id', $product_id)->first();
                // dd($product_data);
                $site_data = DB::table('site')->where('site_key','!=',"")->where('id', $product_data->site_id)->first();
                $site_key    = $site_data->site_key;
                $site_secret = $site_data->site_secret; 
                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://whitelabelmd.sticky.io/api/v1/product_index",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode(['product_id' => [$product_id]]),
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        // "Authorization: Basic " . base64_encode("levelupmeds_bg:ZbTXGH89qaBvpP")
                        "Authorization: Basic " . base64_encode($site_key . ':' . $site_secret)
                    ],
                ]);
    
                $response = curl_exec($curl);
                $err = curl_error($curl);
    
                curl_close($curl);
    
                if ($err) {
                    $failed_updates++;
                    continue;
                }
    
                $data = json_decode($response, true);
    // dd($data);
                if (!isset($data['response_code']) || $data['response_code'] !== '100') {
                    $failed_updates++;
                    continue;
                }
    
                foreach ($data['products'] as $productId => $productData) {
                    DB::table('product')->updateOrInsert(
                        ['sticky_product_id' => $productId],
                        [
                            'product_name' => $productData['product_name'] ?? '',
                            'product_description' => $productData['product_description'] ?? '',
                            'product_sku' => $productData['product_sku'] ?? '',
                            'product_price' => $productData['product_price'] ?? 0,
                            'product_category_name' => $productData['product_category_name'] ?? '',
                            'vertical_name' => $productData['vertical_name'] ?? '',
                            'product_is_trial' => $productData['product_is_trial'] ?? 0,
                            'product_is_shippable' => $productData['product_is_shippable'] ?? 0,
                            'product_rebill_product' => $productData['product_rebill_product'] ?? 0,
                            'product_rebill_days' => $productData['product_rebill_days'] ?? 0,
                            'product_max_quantity' => $productData['product_max_quantity'] ?? 0,
                            'preserve_recurring_quantity' => $productData['preserve_recurring_quantity'] ?? 0,
                            'subscription_type' => $productData['subscription_type'] ?? 0,
                            'subscription_week' => $productData['subscription_week'] ?? '',
                            'subscription_day' => $productData['subscription_day'] ?? '',
                            'cost_of_goods_sold' => $productData['cost_of_goods_sold'] ?? 0,
                            'taxable' => $productData['taxable'] ?? 0,
                            'updated_at' => now(),
                        ]
                    );
    
                    $total_updated_products++;
                }
    
                // Add a small delay to avoid overwhelming the API
                usleep(100000); // 100ms delay
            }
    
            return response()->json([
                'status' => 'success',
                'message' => $total_updated_products . ' product(s) updated successfully!',
                'failed_updates' => $failed_updates
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function stickyproductadd()
    {
        $site_data = DB::table('site')->where('campaign_id','!=',null)->where('is_add',0)->get();
        // dd($site_data);
        foreach ($site_data as $s_data)
        {
            $campaign_id = $s_data->campaign_id;
            $site_key    = $s_data->site_key;
            $site_secret = $s_data->site_secret;

            $new_products = 0;
        
            try {
                // $response = Http::withBasicAuth('levelupmeds_bg', 'ZbTXGH89qaBvpP')
                $response = Http::withBasicAuth($site_key,$site_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/campaign_view', ['campaign_id' => $campaign_id]);
        
                    // dd($response);
                if (!$response->successful()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $response->json() ?? 'Failed to fetch data from API'
                    ], $response->status());
                }
        
                $data = $response->json();
                // dd($data);
                if ($data['response_code'] !== '100') {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'API returned an error: ' . ($data['response_code'] ?? 'Unknown error')
                    ], 400);
                }
        
                // Insert or update campaign data
                DB::table('campaign')->updateOrInsert(
                    ['campaign_id' => $campaign_id],
                    [
                        'campaign_name' => $data['campaign_name'] ?? '',
                        'campaign_description' => $data['campaign_description'] ?? '',
                        'gateway_id' => $data['gateway_id'] ?? '',
                        'is_payment_routed' => $data['is_payment_routed'] ?? '0',
                        'payment_router_id' => $data['payment_router_id'] ?? '',
                        'offer_id' => $data['offer_id'] ?? '',
                        'countries' => $data['countries'] ?? '',
                        'payment_name' => json_encode($data['payment_name'] ?? [])
                    ]
                );

               
        
                // Insert or update shipping data
                if (isset($data['shipping']) && is_array($data['shipping'])) {
                    foreach ($data['shipping'] as $shipping) {
                        DB::table('shipping')->updateOrInsert(
                            ['shipping_id' => $shipping['shipping_id'], 'campaign_id' => $campaign_id],
                            [
                                'shipping_name' => $shipping['shipping_name'] ?? '',
                                'shipping_description' => $shipping['shipping_description'] ?? '',
                                'shipping_initial_price' => $shipping['shipping_initial_price'] ?? '0.00',
                                'shipping_recurring_price' => $shipping['shipping_recurring_price'] ?? '0.00'
                            ]
                        );
                    }
                }
        
                // Insert product data
                if (isset($data['products']) && is_array($data['products'])) {
                    foreach ($data['products'] as $product) {
                        $sticky_product_id = $product['product_id'] ?? null;
                        $product_name = $product['product_name'] ?? null;
        
                        if (!$sticky_product_id || !$product_name) {
                            continue; // Skip this product if required fields are missing
                        }
        
                        $exists = DB::table('product')->where('sticky_product_id', $sticky_product_id)->exists();
        
                        if (!$exists) {
                            

                            $product_id = DB::table('product')->insertGetId([
                                'sticky_product_id' => $sticky_product_id,
                                'product_name'      => $product_name,
                                // 'uniq_id'           => $uniq_id,
                                'campaign_id'       => $campaign_id
                            ]);

                            DB::table('site')->where('campaign_id',$campaign_id)->update([
                                'site_name' => $data['campaign_name'] ?? '',
                                'is_add'    =>1,
                            ]);
                            // site_id
                              // Construct the checkout URL
                              $checkout_url = $s_data->site_url . $product_id;
       
                              // Check if the uniq_id is unique before inserting
                              $uniq_id = $product_id; // Assuming you want to use product_id as uniq_id
      
                              // Ensure uniq_id is unique
                              while (DB::table('product')->where('uniq_id', $uniq_id)->exists()) {
                                  // If the uniq_id is not unique, generate a new one
                                  $uniq_id++; // Simple increment for uniqueness; adjust as necessary for your use case
                              }
                            if ($s_data->is_site == 1) {
                                DB::table('product')->where('product_id', $product_id)->update([
                                    'checkout_url' => $checkout_url,
                                    'site_id'             => $s_data->id,
                                    'main_site_id'         => $s_data->site_id,
                                    'uniq_id'              => $uniq_id,
                                ]);
                            }
                            else
                            {
                             DB::table('product')->where('product_id', $product_id)->update([
                                 // 'checkout_url' => $checkout_url,
                                 'site_id'                 => $s_data->id,
                                 'main_site_id'            => $s_data->site_id,
                                 // 'uniq_id'                 => $uniq_id,
                             ]);
                            }
        
                            $new_products++;
                        }
                    }
                }
        
                return response()->json([
                    'status' => 'success',
                    'message' => $new_products . ' new product(s) added successfully!'
                ]);
        
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'An error occurred: ' . $e->getMessage()
                ], 500);
            }
        }
      
    }

    
    


   }
   