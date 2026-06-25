<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;



class WebhookController extends Controller
{
    

    public function handle(Request $request)
    {
        return $this->processWebhook($request, 'WL');
    }


    public function handle1(Request $request)
    {
        return $this->processWebhook($request, 'Non-WL');
    }


    private function processWebhook(Request $request, $type)
    {
        $data = $request->all();

        // Save raw webhook into DB
        DB::table('webhooks')->insert([
            'payload'    => json_encode($data),
            'type'       => $type,
            'created_at' => now()
        ]);

        // Process only message_created event
        if (isset($data['event_type']) && $data['event_type'] === 'message_created') {

            $patientId = $data['patient_id'] ?? null;

            if ($patientId) {
                // Call Pharmazee API to get patient details
                $response = Http::post("https://panel.ravinimavat.com/ageshiftrx/api/get_patient_data", [
                    "patient_id" => $patientId,
                    "is_cat"     => $type
                ]);

                $patientEmail = null;

                if ($response->ok()) {
                    $apiData = $response->json();
                    if (!empty($apiData['email'])) {
                        $patientEmail = $apiData['email'];
                    }
                }

                // Construct chat URL
                $chatUrl = "https://member.ageshiftrx.com/chat/?patient_id={$patientId}&cat_id={$type}";

                // Prepare payload for LeadConnector
                $payload = [
                    "timestamp"   => $data['timestamp'] ?? now()->timestamp,
                    "event_type"  => $data['event_type'],
                    "patient_id"  => $patientId,
                    "message_id"  => $data['message_id'] ?? null,
                    "channel"     => $data['channel'] ?? null,
                    "user_type"   => $data['user_type'] ?? null,
                    "chat_url"    => $chatUrl,
                    "email"       => $patientEmail,  // pulled from Pharmazee API
                ];

                // Send to LeadConnector webhook
                //Http::post(
                  //  "https://services.leadconnectorhq.com/hooks/2rbJQX2MMUQ99Hh90Z2Y/webhook-trigger/0a8af8b8-b709-4f8d-84db-372884683d3a",
                    //$payload
                //);
            }
        }

        return response()->json(['status' => 'success']);
    }








	public function handleWebhook(Request $request)
    {
        try {
            $requestData = $request->all();
            $jsonData = json_encode($requestData);
            
            // \Log::info('Raw request data keys:', ['keys' => array_keys($requestData)]);
            
            // Find the actual request data - it might be in different keys
            $actualRequestData = null;
            $pretty = '';
            
            // Method 1: Check if 'request' key exists and decode it
            if (isset($requestData['request']) && !empty($requestData['request'])) {
                $actualRequestData = json_decode($requestData['request'], true);
                if ($actualRequestData && isset($actualRequestData['pretty'])) {
                    $pretty = $actualRequestData['pretty'];
                    // \Log::info('Pretty found in request key');
                }
            }
            
            // Method 2: Check if pretty is directly in the main request
            if (empty($pretty) && isset($requestData['pretty'])) {
                $pretty = $requestData['pretty'];
                // \Log::info('Pretty found directly in requestData');
            }
            
            // Method 3: Search through all keys for JSON strings containing 'pretty'
            if (empty($pretty)) {
                foreach ($requestData as $key => $value) {
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if ($decoded && isset($decoded['pretty'])) {
                            $pretty = $decoded['pretty'];
                            $actualRequestData = $decoded;
                            \Log::info('Pretty found in key: ' . $key);
                            break;
                        }
                    }
                }
            }
            
            // Method 4: Search for 'pretty' in the raw JSON string
            if (empty($pretty)) {
                $jsonString = json_encode($requestData);
                if (preg_match('/"pretty":"([^"]*(?:\\.[^"]*)*)"/', $jsonString, $prettyMatch)) {
                    $pretty = stripcslashes($prettyMatch[1]);
                    // \Log::info('Pretty found via regex search');
                }
            }
            
            // if (empty($pretty)) {
            //     $jsonString = json_encode($requestData);
            //     if (preg_match('/"pretty":"([^"]*(?:\\.[^"]*)*)"/', $jsonString, $prettyMatch)) {
            //         $pretty = stripcslashes($prettyMatch[1]);
            //     }
            // }
            // $decodedData = json_decode($requestData);
            $submission_id = $requestData['submissionID'] ?? null;
            $formId = $requestData['formID'] ?? null;
            // Insert initial record
            $id = DB::table('webhook')->insertGetId([
                'webhook_data' => $jsonData,
                'submission_id' => $submission_id,
                'formId' => $formId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // \Log::info('Webhook record created with ID: ' . $id);
            // \Log::info('Pretty data found:', [
            //     'length' => strlen($pretty),
            //     'first_300_chars' => substr($pretty, 0, 300)
            // ]);
            
            // Initialize variables
            $email = null;
            $phone = null;
            $fullName = null;
            $orderIdFromPretty = null;
            $productIdFromPretty = null;
            
            if (!empty($pretty)) {
                // Extract Email using your working pattern
                if (preg_match('/What is your email address\?:([^\s,]+)/', $pretty, $emailMatch)) {
                    $email = trim($emailMatch[1]);
                    // \Log::info('Email extracted: ' . $email);
                }
                
                // Extract Phone Number using your working pattern
                if (preg_match('/Please enter the best phone number.*?:\((\d{3})\)\s*(\d{3})-(\d{4})/', $pretty, $phoneMatch)) {
                    $phone = "({$phoneMatch[1]}) {$phoneMatch[2]}-{$phoneMatch[3]}";
                    // \Log::info('Phone extracted: ' . $phone);
                } else {
                    // Try alternative pattern for phone
                    if (preg_match('/phone number[^:]*:\(([^)]+)\)\s*([^,\s]+)/', $pretty, $phoneMatch2)) {
                        $phone = "({$phoneMatch2[1]}) {$phoneMatch2[2]}";
                        // \Log::info('Phone extracted (alt pattern): ' . $phone);
                    }
                }
                
                // Extract Full Name using your working pattern
                if (preg_match('/What is your full name\?:([^,]+)/', $pretty, $nameMatch)) {
                    $fullName = trim($nameMatch[1]);
                    // \Log::info('Full name extracted: ' . $fullName);
                }

                // Extract order_id from pretty string
                
                if (!empty($pretty)) {
                    // Match pattern like: order_id:1288888
                    if (preg_match('/order_id:(\d+)/', $pretty, $orderIdMatch)) {
                        $orderIdFromPretty = $orderIdMatch[1];
                        // \Log::info('Order ID extracted: ' . $orderIdFromPretty);
                    }
                }
                  if (!empty($pretty)) {
                    // Match pattern like: product_id:111
                    if (preg_match('/product_id:(\d+)/', $pretty, $orderIdMatch)) {
                        $productIdFromPretty = $orderIdMatch[1];
                        // \Log::info('Order ID extracted: ' . $orderIdFromPretty);
                    }
                }

                
            } else {
                // \Log::error('Pretty data is still empty after all methods');
                // \Log::info('Full request data for debugging:', ['full_data' => $requestData]);
            }
            
            // Clean extracted data
            $email = $email ? filter_var(trim($email), FILTER_SANITIZE_EMAIL) : null;
            $fullName = $fullName ? trim(strip_tags($fullName)) : null;
            $phone = $phone ? trim(strip_tags($phone)) : null;
            
            // Update the record with extracted data
            $updateData = [
                'email' => $email,
                'phone' => $phone,
                'full_name' => $fullName,
                'order_id' => $orderIdFromPretty,
                'product_id' => $productIdFromPretty,
                // 'submission_id' => $submission_id,
                // 'formId' => $formId,
                'updated_at' => now(),
            ];
            
            // \Log::info('Updating record with data:', $updateData);
            
            $updateResult = DB::table('webhook')->where('id', $id)->update($updateData);
            
            if ($updateResult) {
                \Log::info('Record updated successfully');
            } else {
                \Log::error('Failed to update record');
            }
            
            // Verify the update
            $updatedRecord = DB::table('webhook')->where('id', $id)->first();
            // \Log::info('Final record state:', [
            //     'id' => $updatedRecord->id,
            //     'email' => $updatedRecord->email,
            //     'phone' => $updatedRecord->phone,
            //     'full_name' => $updatedRecord->full_name
            // ]);

	    $orderId = $orderIdFromPretty;

	    $response = Http::post(
    'https://panel.ravinimavat.com/ageshiftrx/api/get_user_cart_product_details',
    ['order_id' => $orderId]
);

	    $data = $response->json();
//dd($data);

	    if (isset($data['product'][0])) {

    $product = $data['product'][0];

    // Get webhook rows
    $webhooks = DB::table('webhook')
        ->where('order_id', $orderId)
        ->orderBy('created_at', 'asc')
        ->get();

    $webhookCount = $webhooks->count();

    $firstWebhook = $webhooks->first();

//    $recentWebhook = false;

  //  if ($firstWebhook) {
    //    $recentWebhook = Carbon::parse($firstWebhook->created_at)
      //      ->greaterThanOrEqualTo(now()->subDays(2));
   // }

    // MAIN CONDITION
    //if ($product['bm'] == 'BM:6' && $webhookCount < 4 && !$recentWebhook) {


      $recentWebhook = false;
$days = 0;
$webhookLimit = 0;

if ($product['bm'] == 'BM:6') {
    $days = 2;
    $webhookLimit = 5;
}

if ($product['bm'] == 'BM:5') {
    $days = 10;
    $webhookLimit = 3;
}

if ($firstWebhook) {
    $recentWebhook = Carbon::parse($firstWebhook->created_at)
        ->greaterThanOrEqualTo(now()->subDays($days));
}

if (in_array($product['bm'], ['BM:6','BM:5']) && $webhookCount < $webhookLimit && !$recentWebhook) {

	    $email = $product['email'];

	    $response = Http::post('https://panel.ravinimavat.com/ageshiftrx/api/get_email_by_case_details', [
    'email' => $email,
    'order_id' => $orderId
]);

	    $data = $response->json();

	    /*
|--------------------------------------------------------------------------
| Step 2: Get Last Prescription Title
|--------------------------------------------------------------------------
*/
$title = collect($data['prescriptions'] ?? [])->last()['title'] ?? null;

/*
|--------------------------------------------------------------------------
| Step 3: Extract T1,T2,T3.. or S1,S2.. from title
|--------------------------------------------------------------------------
*/
$code = null;

if ($title && preg_match('/\b(T[1-6]|S[1-6])\b/', $title, $matches)) {
    $code = $matches[0];
}

/*
|--------------------------------------------------------------------------
| Step 4: Map Code to Product ID
|--------------------------------------------------------------------------
*/
$productMap = [
    'T1' => 132,
    'T2' => 133,
    'T3' => 134,
    'T4' => 135,
    'T5' => 136,
    'T6' => 137,
    'S1' => 164,
    'S2' => 181,
    'S3' => 182,
    'S4' => 183,
    'S5' => 184,
    'S6' => 185,
];

$new_product_id = $productMap[$code] ?? null;


        $payload = [
            "email" => $product['email'],
            "card_type" => 1,
            "card_no" => "",
            "ex_month" => "",
            "ex_year" => "",
            "cvv_no" => "",
            "card_holder_name" => $product['last_name'],
            "zip_code" => $product['shipping_postcode'],
            "state_code" => $product['state_code'],
            "first_name" => $product['first_name'],
            "last_name" => $product['last_name'],
            "product_id" => $new_product_id,
            "address" => $product['shipping_street_address'],
            "city_name" => $product['shipping_city'],
            "phone" => $product['customers_telephone'],
            "promo_codes" => "",
            "start_url" => "http://panel.ravinimavat.com/ageshiftrx/api/handlewebhook",
            "billingSameAsShipping" => "YES",
            "apple_pay_token" => "rebill_token",
            "parent_order" => $orderId,
            "AFFID" => "",
            "C1" => "",
            "C2" => "",
            "C3" => ""
        ];

        $rebillResponse = Http::post(
            'https://panel.ravinimavat.com/ageshiftrx/api/createOrder_test_parent_order_12month_rebill',
            $payload
        );

        $rebillData = $rebillResponse->json();
//dd($rebillData);
      //  DB::table('rebill_order')->insert([
	//	'order_id' => $orderId,
	//	'payload' => $payload,
         //   'response_order_id' => $rebillData ?? null,
           // 'created_at' => now(),
           // 'updated_at' => now(),
	// ]);
	//
	//
	//
	DB::table('rebill_order')->insert([
    'order_id' => $orderId,
    // Ensure payload is stored as JSON string
        'payload' => $orderId,
    //  'payload' => is_array($payload) || is_object($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload,
    'response_order_id' =>  null,
    'created_at' => now(),
    'updated_at' => now(),
]);
    }
	    }


            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'extracted_data' => [
                    'email' => $email,
                    'phone' => $phone,
                    'full_name' => $fullName,
                    // 'submission_id' => $submission_id,
                    // 'formId' => $formId,
                    'id' => $id
                ]
            ]);
            
        } catch (\Exception $e) {
            // \Log::error('Webhook processing failed: ' . $e->getMessage(), [
            //     'line' => $e->getLine(),
            //     'file' => $e->getFile(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Error: ' . $e->getMessage(),
           'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
        'request_data' => $request->all()
	    ], 500);
        }
    }

    public function get_jotform_data(Request $request)
    {
        $order_id = $request->order_id;
        $second_form_id = $request->second_form_id;

        if($order_id == "" || $second_form_id == "")
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }

        $data_tb = DB::table('webhook')->where('order_id', $order_id)->first();
        if (!$data_tb) {
            return response()->json(['error' => 'Order not found in webhook table'], 404);
        }

        $form_id_1 = $data_tb->formId;
        $submission_id_1 = $data_tb->submission_id;
        $key = "a89649b7236a3f59d3cef9de934fc82d";

        // ✅ Get answers for first form
        $url1 = "https://whitelabelmd.jotform.com/API/form/{$form_id_1}/submissions?apiKey={$key}";
        $res1 = json_decode(file_get_contents($url1), true);

        $answers1 = [];
        if ($res1['responseCode'] === 200 && isset($res1['content'])) {
            foreach ($res1['content'] as $submission) {
                if ($submission['id'] == $submission_id_1) {
                    foreach ($submission['answers'] as $ans) {
                        if (!isset($ans['answer'])) continue;
                        $label = trim($ans['text'] ?? $ans['name'] ?? '');
                        $value = is_array($ans['answer']) ? implode(' ', $ans['answer']) : trim($ans['answer']);
                        if ($label && $value) {
                            $answers1[$label] = [
                                'answer' => $value,
                                'name'   => $ans['name'] ?? ''
                            ];
                        }
                    }
                    break;
                }
            }
        }

        // ✅ Get questions for second form
        $url2 = "https://whitelabelmd.jotform.com/API/form/{$second_form_id}/questions?apiKey={$key}";
        $res2 = json_decode(file_get_contents($url2), true);

        $questions2 = []; // key = question text, value = name
        if ($res2['responseCode'] === 200 && isset($res2['content'])) {
            foreach ($res2['content'] as $q) {
                if (!empty($q['text'])) {
                    $questions2[trim($q['text'])] = $q['name'] ?? '';
                }
            }
        }

        // ✅ Compare and merge "name"
        $matching = [];
        foreach ($answers1 as $question => $data) {
            if (isset($questions2[$question])) {
                $matching[] = [
                    'name'     => $data['name'], // from form1
                    'question' => $question,
                    'answer'   => $data['answer']
                ];
            }
        }

        return response()->json([
            'matches' => $matching
        ]);
    }

    public function get_user_web_data(Request $request)
    {
        $order_id = $request->order_id;
        $data_tb = DB::table('webhook')->where('order_id', $order_id)->first();
        
        if($order_id == "" )
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }

        if (!$data_tb) {
            return response()->json(['error' => 'Order not found in webhook table'], 404);
        }

        return response()->json([
            'form_id' => $data_tb->formId,
            'submission_id' => $data_tb->submission_id, 
        ]);

    }



}
