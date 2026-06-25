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
use Illuminate\Support\Str;
class MemberController extends Controller
{

	private function order_find_email($email)
    {
        if (empty($email)) {
            return null;
        }

        $app_key_data = DB::table('app_keys')->first();
        $app_key = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        $sites = DB::table('site')->get();

        if ($sites->isEmpty()) {
            return null;
        }

        // 🔹 preload products (FAST)
        $productsDB = DB::table('product')->get()->keyBy('sticky_product_id');

        // 🔹 preload webhook
        $webhooks = DB::table('webhook')->get()->groupBy('order_id');

        // BM → months
        $bmMonthMap = [
            3 => 1,
            4 => 3,
            5 => 6,
            6 => 12,
            13 => 2,
            7 => 9,
            12 => 'TRACK-21'
        ];

        $orderDetails = [];

        foreach ($sites as $site) {

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date" => "01/01/2024",
                "end_date" => date('m/d/Y'),
                "criteria" => [
                    "email" => $email
                ],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            $responseData = $response->json();

            if (!$response->successful() || !isset($responseData['data'])) {
                continue;
            }

            foreach ($responseData['data'] as $order) {

                foreach ($order['products'] ?? [] as $product) {

                    $stickyProductId = $product['product_id'];

                    if (!isset($productsDB[$stickyProductId])) {
                        continue;
                    }

                    $productData = $productsDB[$stickyProductId];

                    $product_description = $productData->product_description;

                    preg_match('/BM:(\d+)/', $product_description, $bmMatch);
                    $bm_value = $bmMatch[1] ?? null;

                    $month = $bmMonthMap[$bm_value] ?? null;

                    // webhook data
                    $webhook_data_array = [];

                    if (isset($webhooks[$order['order_id']])) {
                        foreach ($webhooks[$order['order_id']] as $webhook) {

                            $webhook_data_array[] = [
                                'form_id' => $webhook->formId,
                                'submission_id' => $webhook->submission_id,
                                'created_at' => $webhook->created_at
                            ];
                        }
                    }

                    $orderDetails[] = [
                        'order_id' => $order['order_id'] ?? null,
                        'product_name' => $productData->product_name,
                        'product_price' => $product['price'] ?? null,
                        'product_sku' => $productData->product_sku,
                        'product_category_name' => $productData->product_category_name,
                        'bm' => $bm_value,
                        'month' => $month,
                        'time_stamp' => $order['time_stamp'] ?? null,
                        'webhook_data' => $webhook_data_array,
                    ];
                }
            }
        }

        usort($orderDetails, function ($a, $b) {
            return strtotime($b['time_stamp'] ?? 0) <=> strtotime($a['time_stamp'] ?? 0);
        });

        return $orderDetails;
    }

    public function get_user_cart_products_email238823(Request $request)
    {
	     ini_set('memory_limit', '-1');
	    $email = $request->email;

        if (!empty($email)) {
            $order_data = $this->order_find_email($email);

            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

	public function changeUserPassword(Request $request)
    {
        if (empty($request->email) || empty($request->new_password)) {
            return response()->json([
                'status' => 0,
                'message' => 'Email and new password are required.'
            ]);
        }

        // Get app keys (NORMAL DB)
        $appKey = DB::table('app_keys')->first();

        if (!$appKey) {
            return response()->json([
                'status' => 0,
                'message' => 'App keys not found.'
            ]);
        }

        /**
         * STEP 1: Request temporary password
         */
        $forgotResponse = Http::withBasicAuth($appKey->app_key, $appKey->app_secret)
            ->post('https://whitelabelmd.sticky.io/api/v1/member_forgot_password', [
                'email' => $request->email
            ]);

        if (!$forgotResponse->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Password reset request failed.',
                'error' => $forgotResponse->json()
            ]);
        }

        $forgotData = $forgotResponse->json();

        if (!isset($forgotData['data']['temp_password'])) {
            return response()->json([
                'status' => 0,
                'message' => $forgotData['response_message'] ?? 'Temporary password not received.',
                'error' => $forgotData
            ]);
        }

        $tempPassword = $forgotData['data']['temp_password'];

        /**
         * STEP 2: Reset password using temp password
         */
        $resetResponse = Http::withBasicAuth($appKey->app_key, $appKey->app_secret)
            ->post('https://whitelabelmd.sticky.io/api/v1/member_reset_password', [
                'email' => $request->email,
                'member_temp_password' => $tempPassword,
                'member_new_password' => $request->new_password
            ]);

        $resetData = $resetResponse->json();

        if ($resetResponse->successful() && ($resetData['response_code'] ?? null) == 100) {
            return response()->json([
                'status' => 1,
                'message' => 'Password successfully updated.'
            ]);
        }

        if (($resetData['response_code'] ?? null) == 4009) {
            return response()->json([
                'status' => 0,
                'message' => 'New password must be different from old password.'
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Password update failed.',
            'error' => $resetData
        ]);
    }


	public function update_credit_card(Request $request)
    {
        $email = $request->email;
        $creditCardNumber = $request->creditCardNumber;
        $ex_month = $request->ex_month;
        $ex_year = $request->ex_year;
        $cvv = $request->CVV;

        if (empty($email) || empty($creditCardNumber) || empty($ex_month) || empty($ex_year) || empty($cvv)) {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }

        $app_key_data = DB::table('app_keys')->first();
        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        $order_data = $this->order_find($email);

        if (empty($order_data)) {
            return response()->json([
                'status' => 0,
                'message' => "No orders found with this email."
            ]);
        }

        // 🔴 STEP 1: Check offline order FIRST
        foreach ($order_data as $order) {
            if (($order['cc_type'] ?? '') == 'offline') {
                return response()->json([
                    'status' => 0,
                    'message' => "This is an offline order. Credit card update is not allowed."
                ]);
            }
        }

        // ✅ STEP 2: All orders are online → update all
        $updated_orders = [];

        foreach ($order_data as $order) {

            $order_id = $order['order_id'];

            $payload = [
                "order_id" => [
                    $order_id => [
                        "cc_number"          => $creditCardNumber,
                        "cc_payment_type"    => "Discover",
                        "cc_expiration_date" => $ex_month . $ex_year,
                        "CVV"                => $cvv,
                    ]
                ]
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);

            if ($response->successful()) {
                $updated_orders[] = $order_id;
            }
        }

        return response()->json([
            'status' => 1,
            'message' => "Credit card updated successfully for all orders.",
            'updated_orders' => $updated_orders
        ]);
	}


public function user_details(Request $request)
    {
        $email = $request->email;
    
        if($email == "")
        {
            return response()->json([
                'status'  => 0,
                'message' => 'Email is required'
            ]);
        }
        // 🔍 Fetch member data
        $member_data = $this->Customerfind($email);
    
        if (
            empty($member_data) ||
            !isset($member_data['data'])
        ) {
            return response()->json([
                'status'  => 0,
                'message' => 'Member data not found'
            ]);
        }
    
        // ✅ Update if exists, Insert if not
        DB::table('user_details')->updateOrInsert(
            ['email' => $email], // where condition
            [
                'first_name'   => $member_data['data']['first_name'] ?? null,
                'last_name'    => $member_data['data']['last_name'] ?? null,
                'phone_number' => $member_data['data']['phone_number'] ?? null,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );
    
        // 🔁 Fetch updated data
        $user_details = DB::table('user_details')
            ->where('email', $email)
            ->first();
    
        return response()->json([
            'status'       => 1,
            'message'      => 'User details saved successfully',
            'user_details' => $user_details
        ]);
    }

	private function order_find_user_details($email)
    {
        if (!empty($email)) {

		  ini_set('memory_limit', '1024M');
            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                // $filteredOrders = array_filter($orders, function ($order) {
                //     return $order['order_status'] !== "7";
                // });

                // Loop orders
                foreach ($orders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];
                        $cc_type = $order['cc_type'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";


                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();


                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/",
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'product_id'            => $productData->product_id,
                            'cc_type'               => $cc_type,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
	}

    public function update_user_details(Request $request)
    {
        $email = $request->email;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $phone_number = $request->phone_number;
        $gender = $request->gender;
        $dob = $request->dob;

        if($email == "" || $first_name == "" || $last_name == "" || $phone_number == "")
        {
            return response()->json([
                'status'  => 0,
                'message' => 'All fields are required'
            ]);
        }

        // $dob_age = \Carbon\Carbon::parse($dob)->age;
        // dd($dob_age);
        // if($dob_age < 18)
        // {
        //     return response()->json([
        //         'status'  => 0,   
        //         'message' => 'You must be at least 18 years old'
        //     ]);
        // }   

        // ✅ 2. Check user exists
        $userExists = DB::table('user_details')
            ->where('email', $email)
            ->exists();

        if (!$userExists) {
            return response()->json([
                'status'  => 0,
                'message' => 'User not found'
            ]);
        }

        // ✅ 3. API credentials
        $appKeyData = DB::table('app_keys')->first();
        if (!$appKeyData) {
            return response()->json([
                'status'  => 0,
                'message' => 'API credentials not found'
            ]);
        }

        // ✅ 4. Get order_id from email
        $order_data = $this->order_find_user_details($email);
        if (empty($order_data)) {
            return response()->json([
                'status'  => 0,
                'message' => 'No orders found with this email'
            ]);
        }

        $order_id = $order_data[0]['order_id'];

        // ✅ 5. Sticky payload (FIXED keys)
        $payload = [
            "order_id" => [
                $order_id => [
                    "first_name" => $first_name,
                    "last_name"  => $last_name,
                    "phone"     => $phone_number,
                    "sync_all"  => 1
                ]
            ]
        ];

        // ✅ 6. Sticky API call
        $response = Http::withBasicAuth(
            $appKeyData->app_key,
            $appKeyData->app_secret
        )->post(
            'https://whitelabelmd.sticky.io/api/v1/order_update',
            $payload
        );

        if (!$response->successful()) {
            return response()->json([
                'status'  => 0,
                'message' => 'Sticky order update failed',
                'error'   => $response->json()
            ]);
        }

        // ✅ 7. Update local DB
        DB::table('user_details')
            ->where('email', $email)
            ->update([
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'phone_number' => $phone_number,
                'gender'       => $gender,
                'dob'          => $dob,
                'updated_at'   => now(),
            ]);

        // ✅ 8. Fetch updated user
        $userDetails = DB::table('user_details')
            ->where('email', $email)
            ->first();

        return response()->json([
            'status'       => 1,
            'message'      => 'User details updated successfully',
            'user_details' => $userDetails
        ]);
    }



public function get_user_cart_products_v6(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

        if (!empty($email)) {
            $order_data = $this->order_find_v6($email);

            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

    private function order_find_v6($email)
    {   
        if (!empty($email)) {
    
            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
    
            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();
    
            if ($site_data->isEmpty()) {
                return null; // No sites found
            }
    
            $orderDetails = [];
    
            // 🔹 Loop through all sites
            foreach ($site_data as $site) {
    
                $campaign_id = $site->campaign_id;
    
                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);
    
                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);
    
                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }
    
                $orders = $responseData['data'];
    
                // Filter out status = 7
                //remove this condition to get all orders
                // $filteredOrders = array_filter($orders, function ($order) {
                //     return $order['order_status'] !== "7";
                // });
    
                // Loop orders
                foreach ($orders as $order) {
                    $products = $order['products'] ?? [];
    
                    foreach ($products as $product) {
    
                        $stickyProductId = $product['product_id'];
                        $on_hold = $product['on_hold'];
    
                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }
    
                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();
    
                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";
    
                        // Check webhook
                      
    
                        // Intake form
                        $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;
                        $continuationLink = $formData['continuation_link'] ?? null;
                        if($continuationLink)
                        {
                            $continuationLink = explode('?', $continuationLink)[1];
                        }
                        else
                        {
                            $continuationLink = null;
                        }
                        // $therapyGroup = $formData['therapy_group'] ?? null;
                        $newFormId = null;
                        $therapyGroup = null;
                        if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                            $newFormId = $formData['checkin_info']['form_id']; //
                        }
                        if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                            $therapyGroup = $formData['therapy']['therapy_group']; // 
                        }
    
                        if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                            $is_sub = 0;
                        } else {
                            $is_sub = 1;
                        }
                        if ($intakeStage === null) {
                            $is_sub = 2;
                        }
    
                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();

                            if($newFormId)
                            {
                                $formId = $newFormId;
                            }
                            else
                            {
                                $formId = $intakeFormId;
                            }
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->where('formId', $formId)
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }
    
                        $state_name = $states_data->name ?? null;

                        if($productData->product_category_name == "Weight Loss")
                        {
                            $form_url = "https://www.coreagerx.com/onboarding";
                        }
                        else
                        {
                            $form_url = "https://forms.whitelabelmd.com/" . $intakeFormId;
                        }
                        $shipping_city = $order['shipping_city'] ?? null;
                        $shipping_country = $order['shipping_country'] ?? null;
                        $shipping_postcode = $order['shipping_postcode'] ?? null;

                        $bm = 3;
                        
                        // BM extract
                        if (preg_match('/BM:(\d+)/', $productData->product_description, $matches)) {
                            $bm = (int) $matches[1];
                        }

                        // BM → total days
                        $bmDaysMap = [
                            3 => 25,
                            4 => 80,
                            5 => 180,
                            6 => 360,
                            7 => 270,
                        ];

                        $totalDays = $bmDaysMap[$bm] ?? 30;

                        // 5 din kam
                        $allowedDays = $totalDays - 5;

                        // Order time
                        $orderTime = $order['time_stamp'] ?? null;

                        $formUrlNew = null;
                        if ($orderTime) {

                            $orderDate = \Carbon\Carbon::parse($orderTime);
                            $eligibleDate = $orderDate->copy()->addDays($allowedDays);

                            // agar aaj ki date eligible date se aage ho chuki hai
                            if (now()->greaterThanOrEqualTo($eligibleDate)) {
                                $formUrlNew = $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null;
                                $continuationLink =$continuationLink;
                                $therapyGroup = $therapyGroup;
                            }
                            else
                            {
                                $formUrlNew = "";
                                $continuationLink = "";
                                $therapyGroup = "";
                            }
                        }


			if($is_sub == 1 && $formUrlNew != null)
                        {
                            $this->storeNotification(
                                $order['email_address'],
                                $order['order_id'],
                                'CHECKIN_PENDING',
                                'Check-In Required for Refill',
                                'To continue your treatment and receive your next refill, please complete your check-in form.
                                 This helps your care team review your progress and avoid delays.',
                                now()
                            );
                        }
                        //store tracking_number
                        if($order['tracking_number'] != null)
                        {

                            $this->storeNotification(
                                $order['email_address'],
                                $order['order_id'],
                                'TRACKING_NUMBER_UPDATED',
                                'Your Order Has Shipped',
                                'Your order has been shipped and is on the way.
                                 You can track your delivery using tracking number '.$order['tracking_number'].' for Order #'.$order['order_id'].'.',
                                now()
                            );
                        }
                    
                        // dd($bm);
                   

		$product_description = $productData->product_description;

                        // 🔹 Clean BM tag
			$clean_description = preg_replace('/\s*BM:\d+/', '', $product_description);



                        // Final push
                        $orderDetails[] = [
                            'form_url'              => $form_url,
                            'bm'                    => $bm,
                            'shipping_city'         => $shipping_city,
                            'shipping_country'      => $shipping_country,
                            'shipping_postcode'     => $shipping_postcode,
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'state_name'            => $state_name,
                            'on_hold'               => $on_hold,
                            'decline_reason'        => $order['decline_reason'] ?? null,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
			    'product_name' => $this->cleanProductName($productData->product_name),
			    //       'product_name'          => $productData->product_name,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
			    'product_description'   => $clean_description,
			    //            'product_description'   => $productData->product_description,
                            'stage'                 => $intakeStage,
                            'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                            'form_id'               => $intakeFormId,
                            'form_id_new'           => $newFormId,
                            'form_url_new'          => $formUrlNew,
                            // 'form_url_new'          => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
                            // 'form_id_new' =>"",
                            // 'form_url_new' =>"",
                            'continuation_link'     => $continuationLink,
                            'therapy_group'         => $therapyGroup,
                            // 'therapy_group' =>"",
                            // 'data'                  => $order,
                        ];
                    }
                }
            }
    
            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });
    
            return $orderDetails;
        }
    
        return null;
    }

public function uploadFile(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|string',
            'order_id'    => 'required|string',
            'user'        => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);

        
        /** 🔹 Image file frontend se */
        $file = $request->file('file');

        /** 🔹 File info */
        $fileName = $file->getClientOriginalName(); // test.png
        $fileExt  = $file->getClientOriginalExtension(); // png

        /** 🔹 Convert file → base64 */
        $fileContents = base64_encode(file_get_contents($file->getRealPath()));

        /** 🔹 Payload for WhiteLabelMD API */
        $payload = [
            "user"          => $request->user,
            "upload_name"   => $request->upload_name, // PRESCRIPTION
            "file_name"     => $fileName,
            "file_ext"      => $fileExt,
            "file_contents" => $fileContents
        ];

        $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
	'authtoken'     => 'geckos-api-key=gokul428use',    
        ])->post($url, $payload);
        //store all response data in database
        DB::table('upload_files')->insert([
            'site_id'     => $request->site_id,
            'order_id'    => $request->order_id,
            'user'        => $request->user,
            'upload_name' => $request->upload_name,
            'file_name'   => $fileName,
            'file_ext'    => $fileExt,
            'file_contents' => $fileContents,
            'response' => json_encode($response->json()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response_data = $response->json();
        if ($response_data['status'] == 'failed') {
            return response()->json([
                'status' => false,
                'message' => 'Upload failed',
                'error' => $response_data['message']
            ], 400);
        }

        if ($response->successful()) {
            return response()->json([
                'status' => true,
                'message' => 'Prescription uploaded successfully',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Upload failed',
            'error' => $response->body()
        ], 400);
    }

	public function uploadFile111wwe324213(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|string',
            'order_id'    => 'required|string',
            'user'        => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);


        /** 🔹 Image file frontend se */
        $file = $request->file('file');

        /** 🔹 File info */
        $fileName = $file->getClientOriginalName(); // test.png
        $fileExt  = $file->getClientOriginalExtension(); // png

        /** 🔹 Convert file → base64 */
        $fileContents = base64_encode(file_get_contents($file->getRealPath()));

        /** 🔹 Payload for WhiteLabelMD API */
        $payload = [
            "user"          => $request->user,
            "upload_name"   => $request->upload_name, // PRESCRIPTION
            "file_name"     => $fileName,
            "file_ext"      => $fileExt,
            "file_contents" => $fileContents
        ];

        $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            // 'Authorization' => 'Bearer TOKEN'
        ])->post($url, $payload);
        $response_data = $response->json();
        if ($response_data['status'] == 'failed') {
            return response()->json([
                'status' => false,
                'message' => 'Upload failed',
                'error' => $response_data['message']
            ], 400);
        }

        if ($response->successful()) {
            return response()->json([
                'status' => true,
                'message' => 'Prescription uploaded successfully',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Upload failed',
            'error' => $response->body()
        ], 400);
    }

	public function uploadFile234878324(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|string',
            'order_id'    => 'required|string',
            'user'        => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);


        /** 🔹 Image file frontend se */
        $file = $request->file('file');

        /** 🔹 File info */
        $fileName = $file->getClientOriginalName(); // test.png
        $fileExt  = $file->getClientOriginalExtension(); // png

        /** 🔹 Convert file → base64 */
        $fileContents = base64_encode(file_get_contents($file->getRealPath()));

        /** 🔹 Payload for WhiteLabelMD API */
        $payload = [
            "user"          => $request->user,
            "upload_name"   => $request->upload_name, // PRESCRIPTION
            "file_name"     => $fileName,
            "file_ext"      => $fileExt,
            "file_contents" => $fileContents
        ];

        $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            // 'Authorization' => 'Bearer TOKEN'
        ])->post($url, $payload);

        if ($response->successful()) {
            return response()->json([
                'status' => true,
                'message' => 'Prescription uploaded successfully',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Upload failed',
            'error' => $response->body()
        ], 400);
    }

	public function uploadFile3773(Request $request)
    {
        $request->validate([
            'site_id'     => 'required',
            'order_id'    => 'required',
            'user'        => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);

        try {

            /** 🔹 Frontend se file */
            $file = $request->file('file');

            /** 🔹 File details */
            $fileName = $file->getClientOriginalName();
            $fileExt  = $file->getClientOriginalExtension();

            /** 🔹 File → Base64 */
            $fileContents = base64_encode(file_get_contents($file->getRealPath()));

            /** 🔹 Payload (IMPORTANT: exact keys) */
            $payload = [
                "user"          => (string) $request->user,
                "upload_name"   => (string) $request->upload_name,
                "file_name"     => (string) $fileName,
                "file_ext"      => (string) $fileExt,
                "file_contents" => (string) $fileContents
            ];

            $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

            $response = Http::withHeaders([
                'Accept' => 'application/json'
            ])->post($url, $payload);

            $responseData = $response->json();

            /** ❌ API level failure */
            if (!$response->successful() || ($responseData['status'] ?? '') === 'failed') {
                return response()->json([
                    'status'  => false,
                    'message' => $responseData['message'] ?? 'Upload failed',
                    'api_response' => $responseData
                ], 400);
            }

            /** ✅ Save log in DB */
            DB::table('upload_files')->insert([
                'site_id'     => $request->site_id,
                'order_id'    => $request->order_id,
                'user'        => $request->user,
                'upload_name' => $request->upload_name,
                'file_name'   => $fileName,
                'file_ext'    => $fileExt,
                'created_at'  => now()
            ]);

            /** ✅ Success */
            return response()->json([
                'status'  => true,
                'message' => 'Prescription uploaded successfully',
                'data'    => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

	public function uploadFile23848234(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|string',
            'order_id'    => 'required|string',
            'user'        => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);


        /** 🔹 Image file frontend se */
        $file = $request->file('file');

        /** 🔹 File info */
        $fileName = $file->getClientOriginalName(); // test.png
        $fileExt  = $file->getClientOriginalExtension(); // png

        /** 🔹 Convert file → base64 */
        $fileContents = base64_encode(file_get_contents($file->getRealPath()));

        /** 🔹 Payload for WhiteLabelMD API */
        $payload = [
            "user"          => $request->user,
            "upload_name"   => $request->upload_name, // PRESCRIPTION
            "file_name"     => $fileName,
            "file_ext"      => $fileExt,
            "file_contents" => $fileContents
        ];

        $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

        $response = Http::withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            // 'Authorization' => 'Bearer TOKEN'
        ])->post($url, $payload);

        if ($response->successful()) {
            return response()->json([
                'status' => true,
                'message' => 'Prescription uploaded successfully',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Upload failed',
            'error' => $response->body()
        ], 400);
	}



    public function slack_webhook23747324(Request $request)
    {
        $data = $request->all();
        $customer_name = $data['customer_name'] ?? null;
        $case_id = $data['case_id'] ?? null;
        $message = $data['message'] ?? null;
        $response = Http::post('https://hooks.slack.com/triggers/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', [
            'Alert' => "Customer Name: $customer_name, Case ID: $case_id, Message: $message"
        ]);
        return $response;
    }

	public function slack_webhook(Request $request)
    {
        $data = $request->all();
       $customer_name = $data['customer_name'] ?? null;
        //$case_url = $data['case_id'] ?? null;
$case_url = "https://app.mdintegratinons.com/{$case_id}";       
	$message = $data['message'] ?? null;
        $response = Http::post('https://hooks.slack.com/triggers/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', [
            'Alert' => "Case Id: $case_url  Message: $message"
	]);

	DB::table('slack_webhook_logs')->insert([
        'request_payload' => json_encode($data),
        'slack_response'  => $response->body(),
        'status_code'     => $response->status(),
        'created_at'      => now(),
        'updated_at'      => now(),
	]);


        return $response;
    }

	public function mdi_doctor_message(Request $request)
    {
        $data = $request->all();

        // Only message_created event
        if (!isset($data['event_type']) && $data['event_type'] == 'message_created' &&    !isset($data['user_type']) ||
    $data['user_type'] == 'clinician') {
            //return response()->json([
              //  'status'  => 0,
                //'message' => 'Invalid event type'
            //]);
       // }

        $patient_id = $data['patient_id'] ?? null;

        if (empty($patient_id)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Patient ID missing'
            ]);
        }

        /* ================= Client Credentials ================= */
        $client_data = DB::table('client_key')->where('is_wl', 1)->first();

        if (!$client_data) {
            return response()->json([
                'status'  => 0,
                'message' => 'Client data not found'
            ]);
        }

        /* ================= Get Access Token ================= */
        $tokenResponse = Http::post(
            'https://api.mdintegrations.com/v1/partner/auth/token',
            [
                'grant_type'    => $client_data->grant_type,
                'client_id'     => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope'         => $client_data->scope,
            ]
        );

        if (!$tokenResponse->successful()) {
            return response()->json(['status' => 0, 'message' => 'Token fetch failed']);
        }

        $accessToken = $tokenResponse->json()['access_token'] ?? null;

        if (!$accessToken) {
            return response()->json(['status' => 0, 'message' => 'Access token missing']);
        }

        /* ================= Get Patient Details ================= */
        $patientResponse = Http::withToken($accessToken)
            ->get("https://api.mdintegrations.com/v1/partner/patients/{$patient_id}");

        if (!$patientResponse->successful()) {
            return response()->json(['status' => 0, 'message' => 'Patient fetch failed']);
        }

        $patientData = $patientResponse->json();
        $email = $patientData['email'] ?? null;

        if (!$email) {
            return response()->json(['status' => 0, 'message' => 'Email not found']);
        }

	$response = Http::post('https://panel.ravinimavat.com/ageshiftrx/api/get_user_cart_products_v6', [
    'email' => $email
]);

$datanew = $response->json();

$latestOrderId = null;

if (!empty($datanew['product']) && isset($datanew['product'][0]['order_id'])) {
    $latestOrderId = $datanew['product'][0]['order_id'];
}


        /* ================= Call n8n Webhook ================= */
        $webhookPayload = [
            'siteId'      => '265',
            'orderStatus' => 'DCTMSGSENT',
            'orderId'     =>  $latestOrderId,
	    //'email'       => 'ravi@whitelabelmd.com',
	    'email'       => $email,
        ];

        $webhookResponse = Http::post(
            'https://n8n.whitelabelmd.com/webhook/c8d83310-f173-401d-8555-08795624cc92',
            $webhookPayload
        );

        /* ================= Store Webhook Data + Response ================= */
        DB::table('mdi_doctor_message_webhook')->insert([
            'payload'          => json_encode($data),
            'email'            => $email,
            'webhook_request'  => json_encode($webhookPayload),
            'webhook_response' => $webhookResponse->body(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Message created & webhook fired successfully'
	]);
	}
    }

	private function get_case_id_by_email($email)
    {
        if (empty($email)) {
            return null;
        }

        /* Sticky credentials */
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return null;
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        /* All sites */
        $sites = DB::table('site')->get();
        if ($sites->isEmpty()) {
            return null;
        }

        $allOrders = [];

        foreach ($sites as $site) {
            if (empty($site->campaign_id)) {
                continue;
            }

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date"  => "01/01/2024",
                "end_date"    => date('m/d/Y'),
                "criteria"    => ["email" => $email],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            if ($response->successful() && isset($response['data'])) {
                $allOrders = array_merge($allOrders, $response['data']);
            }
        }

        if (empty($allOrders)) {
            return null;
        }

        // Oldest order first
        usort($allOrders, fn($a, $b) =>
            strtotime($a['time_stamp']) <=> strtotime($b['time_stamp'])
        );

        // 🔥 FIRST available case_id
        foreach ($allOrders as $order) {
            if (!empty($order['custom_fields'])) {
                foreach ($order['custom_fields'] as $field) {
                    if (
                        ($field['name'] ?? '') === 'prescription_info' &&
                        isset($field['values'][0]['value'])
                    ) {
                        $value = json_decode($field['values'][0]['value'], true);
                        if (!empty($value['case_id'])) {
                            return $value['case_id'];
                        }
                    }
                }
            }
        }

        return null;
    }

	public function mdi_doctor_message8318432(Request $request)
    {
        DB::table('mdi_doctor_message_webhook')->insert([
            'payload'    => json_encode($request->all()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Webhook data stored successfully'
        ]);
    }

public function leadconnectorhq_webhook(Request $request)
    {
        $data = $request->all();

        $order_id     = $data['orderId'] ?? null;
        $order_status = $data['orderStatus'] ?? null;
        $email        = $data['email'] ?? null;
        $timestamp    = $data['timestamp'] ?? null;

        if (!$email || !$timestamp) {
            return response()->json([
                'status' => 0,
                'message' => 'Invalid payload'
            ]);
        }

        // Convert ISO timestamp → MySQL datetime
        $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));

        // Check duplicate (email + timestamp)
        $existing_data = DB::table('leadconnectorhq_data')
            ->where('email', $email)
            ->where('timestamp', $timestamp)
            ->first();

        if ($existing_data) {
            return response()->json([
                'status' => 0,
                'message' => 'Data already exists.'
            ]);
        }

        // Call external webhook
        $response = Http::post(
            "https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/vgnaf5zJaSNdT8amyWli",
            $data
        );

        if (!$response->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to update order status.'
            ]);
        }

        // ✅ FIX HERE
        DB::table('leadconnectorhq_data')->insert([
            'order_id'     => $order_id,
            'order_status' => $order_status,
            'email'        => $email,
            'timestamp'    => $timestamp,
            'response'     => json_encode($response->json()), // <-- IMPORTANT
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Data stored successfully.'
        ]);
    }


public function get_user_cart_products_v3(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

        if (!empty($email)) {
            $order_data = $this->order_find_v3($email);

            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

    private function order_find_v338($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                //remove this condition to get all orders
                // $filteredOrders = array_filter($orders, function ($order) {
                //     return $order['order_status'] !== "7";
                // });

                // Loop orders
                foreach ($orders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];
                        $on_hold = $product['on_hold'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook


                        // Intake form
                        $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;
                        $continuationLink = $formData['continuation_link'] ?? null;
                        if($continuationLink)
                        {
                            $continuationLink = explode('?', $continuationLink)[1];
                        }
                        else
                        {
                            $continuationLink = null;
                        }
                        // $therapyGroup = $formData['therapy_group'] ?? null;
                        $newFormId = null;
                        $therapyGroup = null;
                        if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                            $newFormId = $formData['checkin_info']['form_id']; //
                        }
                        if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                            $therapyGroup = $formData['therapy']['therapy_group']; //
                        }

                        if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                            $is_sub = 0;
                        } else {
                            $is_sub = 1;
                        }
                        if ($intakeStage === null) {
                            $is_sub = 2;
                        }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();

                            if($newFormId)
                            {
                                $formId = $newFormId;
                            }
                            else
                            {
                                $formId = $intakeFormId;
                            }
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->where('formId', $formId)
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }

                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'on_hold'               => $on_hold,
                            'decline_reason'        => $order['decline_reason'] ?? null,
                            'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/" . $intakeFormId,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'stage'                 => $intakeStage,
                            'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                            'form_id'               => $intakeFormId,
                            'form_id_new'           => $newFormId,
                            'form_url_new'          => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
                            'continuation_link'     => $continuationLink,
                            'therapy_group'         => $therapyGroup,

                            // 'data'                  => $order,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }

	public function update_order_payment_received(Request $request)
    {
        $order_id     = $request->order_id;
        // $payment_received = $request->payment_received;


        // Check if all required fields are provided
        if (!empty($order_id)) {

                $app_key_data = DB::table('app_keys')->first();

                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;

                // Prepare the data for the API request
                $payload = [
                    "order_id" => [
                        $order_id => [
                            //send in boolean value
                           'payment_received' => 1
                        ]
                    ]
                ];

                // Make the API request
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);
                // $response = $response->json();
                // dd($response);
                // Check if the response is successful
                if ($response->successful()) {

                    return response()->json([

                        'status' => 1,
                        'message' => "Order payment received updated successfully.",

                    ]);
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => "Failed to update order payment received.",
                        'error' => $response->json()
                    ]);
                }

        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

	public function member_portal_geckos(Request $request)
    {

        $data = $request->all();
        // dd($data);
        $site_id = $request->site_id;
        $response = Http::withHeaders([
            'Authorization' => 'erictemp',
            'Content-Type' => 'application/json'
        ])->post("https://api.whitelabelmd.com/webhook/member-portal-geckos/265/", $data);
        
	
	
	
	
	 $email = $request->email ?? null;

    if (!$email) {
        return response()->json(['status' => 0, 'message' => 'Email not found']);
    }

    // 🔹 Call API to get user cart products
    $response = Http::post('https://panel.ravinimavat.com/ageshiftrx/api/get_user_cart_products_v6', [
        'email' => $email
    ]);

    $datanew = $response->json();

    // 🔹 Get latest order ID
    $latestOrderId = null;
    if (!empty($datanew['product']) && isset($datanew['product'][0]['order_id'])) {
        $latestOrderId = $datanew['product'][0]['order_id'];
    }

    if (!$latestOrderId) {
        return response()->json(['status' => 0, 'message' => 'Latest order ID not found']);
    }

    // 🔹 Prepare payload for n8n webhook
    $webhookPayload = [
        'siteId'      => '265',
        'orderStatus' => 'DCTMSGREAD',
        'orderId'     => $latestOrderId,
        'email'       => $email,
    ];

    // 🔹 Send payload to n8n webhook
    $webhookResponse = Http::post(
        'https://n8n.whitelabelmd.com/webhook/c8d83310-f173-401d-8555-08795624cc92',
        $webhookPayload
    );


/* ================= Store Webhook Data + Response ================= */
        DB::table('mdi_doctor_message_webhook')->insert([
            'payload'          => json_encode($data),
            'email'            => $email,
            'webhook_request'  => json_encode($webhookPayload),
            'webhook_response' => $webhookResponse->body(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

return response()->json([
        'status' => 1,
        'message' => 'Webhook sent successfully',
        'n8n_response' => $webhookResponse->json()
    ]);
	

	// dd($response);
      //  return $response;
    }

	public function get_user_prescription_info(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

        if (!empty($email)) {
            $order_data = $this->order_find_v3($email);

            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

    private function order_find_v3($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                //remove this condition to get all orders
                // $filteredOrders = array_filter($orders, function ($order) {
                //     return $order['order_status'] !== "7";
                // });

		$count = 0;

                // Loop orders
                foreach ($orders as $order) {

//			 if ($count == 5) {
  //      break;
   // }

    // your code here
    //$count++;

			$products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];
                        $on_hold = $product['on_hold'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook


                        // Intake form
                        $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;
                        $continuationLink = $formData['continuation_link'] ?? null;
                        if($continuationLink)
                        {
                            $continuationLink = explode('?', $continuationLink)[1];
                        }
                        else
                        {
                            $continuationLink = null;
                        }
                        // $therapyGroup = $formData['therapy_group'] ?? null;
                        $newFormId = null;
                        $therapyGroup = null;
                        if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                            $newFormId = $formData['checkin_info']['form_id']; //
                        }
                        if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                            $therapyGroup = $formData['therapy']['therapy_group']; //
                        }

                        if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                            $is_sub = 0;
                        } else {
                            $is_sub = 1;
                        }
                        if ($intakeStage === null) {
                            $is_sub = 2;
                        }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();

                            if($newFormId)
                            {
                                $formId = $newFormId;
                            }
                            else
                            {
                                $formId = $intakeFormId;
                            }
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->where('formId', $formId)
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }

                        $state_name = $states_data->name ?? null;

                        if($productData->product_category_name == "Weight Loss")
                        {
                           $form_url = "https://www.coreagerx.com/onboarding" . "&order_id=" . $order['order_id'];
                        }
                        else
                        {
                            $form_url = "https://forms.whitelabelmd.com/" . $intakeFormId . "&order_id=" . $order['order_id'];
                        }
                        $shipping_city = $order['shipping_city'] ?? null;
                        $shipping_country = $order['shipping_country'] ?? null;
                        $shipping_postcode = $order['shipping_postcode'] ?? null;


                        // Final push
                        $orderDetails[] = [
                            'form_url'              => $form_url,
                            'shipping_city'         => $shipping_city,
                            'shipping_country'      => $shipping_country,
                            'shipping_postcode'     => $shipping_postcode,
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'state_name'            => $state_name,
                            'on_hold'               => $on_hold,
                            'decline_reason'        => $order['decline_reason'] ?? null,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'stage'                 => $intakeStage,
                            'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                     //       'form_id'               => $intakeFormId,
                       //     'form_id_new'           => $newFormId,
                            'form_url_new'          => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
			   'form_id_new' =>"",
 'form_url_new' =>"",
'therapy_group' =>"",
			    'continuation_link'     => $continuationLink,
                         //   'therapy_group'         => $therapyGroup,

                            // 'data'                  => $order,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }

    public function get_user_cart_products_v5(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

        if (!empty($email)) {
            $order_data = $this->order_find_v5($email);
//dd($order_data);
            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }


    public function syncordercheckin(Request $request)
{
    $order_id = $request->order_id;

    // Step 1: Call external API
//    $apiUrl = "https://api.whitelabelmd.com/client/site/265/order-op/test/".$order_id;

  //  $response = Http::get($apiUrl);

    $apiUrl = "https://api.whitelabelmd.com/client/site/265/order-op/test/" . $order_id;

$response = Http::withHeaders([
    'authtoken' => 'geckos-api-key=gokul428use',
])->get($apiUrl);

    if (!$response->successful()) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch order data'
        ]);
    }

    $data = $response->json();

    if (!isset($data['data']['continuation_stamp'])) {
        return response()->json([
            'status' => false,
            'message' => 'Continuation stamp not found'
        ]);
    }

    // Step 2: Extract unique_id
    $stamp = $data['data']['continuation_stamp'];
    $parts = explode('-', $stamp);
    $unique_id = $stamp;

    // Step 3: Find record in second database
    $record = DB::connection('second_db')
        ->table('submissions')
        ->where('unique_id', $unique_id)
        ->first();

    if (!$record) {
        return response()->json([
            'status' => false,
            'message' => 'Submission not found in second DB'
        ]);
    }

    // Step 4: Check if submission already exists in webhook table
    $exists = DB::table('webhook')
        ->where('submission_id', $record->id)
        ->first();
    if ($exists) {

        // Update order_id only
        DB::table('webhook')
            ->where('submission_id', (string) $record->id)
            ->update([
                'order_id' => $order_id,
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => true,
		'submission_id' => $record->id,
	    'message' => 'Order updated successfully'
        ]);
    }


    // Step 5: Insert new record
    DB::table('webhook')->insert([
        'submission_id' => $record->id,
        'order_id' => $order_id,
	'formId' => $record->form_id,    
	'webhook_data' => $record->answers,
        'email' => $record->email,
        'created_at' => $record->updated,
        'updated_at' => now()
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Order synced successfully'
    ]);
}

public function syncOrder(Request $request)
{
    $order_id = $request->order_id;

    // Step 1: Check in local webhook table
    $exists = DB::table('webhook')
        ->where('order_id', $order_id)
        ->exists();

    if ($exists) {
        return response()->json([
            'status' => true,
            'message' => 'Order already exists in webhook table'
        ]);
    }

    // Step 2: Check in second database intake table
    $record = DB::connection('second_db')
        ->table('intakes')
        ->where('last_order_id', $order_id)
        ->first();

    if (!$record) {
        return response()->json([
            'status' => false,
            'message' => 'Order not found in intake table'
        ]);
    }

    // Step 3: Insert into webhook table
$string = $record->notes;
    $formId = explode('-', explode('Form: ', $string)[1])[0];

//echo $formId;

     DB::table('webhook')->insert([
        'submission_id' => $record->id,
        'order_id' => $record->last_order_id,
        'webhook_data' => $record->answers,
	'formId'   => $formId,  
	'email' => $record->email,
        'created_at' => $record->inserted,
        'updated_at' => now()
    ]);

    return response()->json([
        'status' => true,
        'message' => 'Order synced successfully',
        'data' => $record
    ]);
}
    private function order_find_v5($email)
    {
        if (empty($email)) {
            return null;
        }

        /* ================= Sticky Credentials ================= */
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return null;
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        /* ================= Get ALL Sites ================= */
        $sites = DB::table('site')->get();
        if ($sites->isEmpty()) {
            return null;
        }

        $allOrders = [];

        foreach ($sites as $site) {
            if (empty($site->campaign_id)) {
                continue;
            }

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date"  => "01/01/2024",
                "end_date"    => "11/11/2029",
                "criteria"    => ["email" => $email],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            if ($response->successful() && isset($response['data'])) {
                $allOrders = array_merge($allOrders, $response['data']);
            }
        }

//	dd(json_encode($allOrders, JSON_PRETTY_PRINT));

        if (empty($allOrders)) {
            return null;
        }

	$orders = $allOrders;

	
$orders = array_filter($orders, function ($order) {
            return $order['order_status'] !== "7";
        });	








	if (!empty($orders)) {

            $validOrder = null;

            foreach ($orders as $order) {

                $productId = $order['products'][0]['product_id'] ?? null;
                $acquisitionDate = $order['acquisition_date'] ?? null;

                if (!$productId || !$acquisitionDate) {
                    continue;
                }

                $product = DB::table('product')
                    ->where('sticky_product_id', $productId)
                    ->first();

                if ($product) {

                    $orderDate = Carbon::parse($acquisitionDate);
                    $daysDiff = $orderDate->diffInDays(Carbon::now());

                    // 12 month check
                    if (str_contains($product->product_description, 'BM:6') && $daysDiff <= 360) {
                        $validOrder = $order;
                        break;
                    }

                    // 6 month check
                    if (str_contains($product->product_description, 'BM:5') && $daysDiff <= 180) {
                        $validOrder = $order;
                        break;
                    }
                }
            }

            if ($validOrder) {
                $orders = [$validOrder];
            }
        }


	
	//dd(json_encode($orders, JSON_PRETTY_PRINT));

//	$order_new_id = $orders[0]['order_id'];
	//:wq!
	//dd($order_new_id);
	 // Call sync-order API
  //  $response1 = Http::post('https://panel.ravinimavat.com/ageshiftrx/api/sync-order', [
    //    'order_id' => $order_new_id
    //]);

    // Call syncordercheckin API
   // $response2 = Http::post('https://panel.ravinimavat.com/ageshiftrx/api/syncordercheckin', [
     //   'order_id' => $order_new_id
    //]);

	//remove this condition to get all orders
        $filteredOrders = array_filter($orders, function ($order) {
            return $order['order_status'] !== "7";
        });

        $orders = $filteredOrders;
//dd($orders);
        /* ================= Token Cache (WL / NON-WL) ================= */
        $clientTokenCache = [];

        /* ================= Process Orders ================= */
        $orderDetails = array_map(function ($order) use ($orders, &$clientTokenCache) {

            $main_product_id = $order['main_product_id'] ?? null;
            if (!$main_product_id) {
                return null;
            }

	      $order_new_id = $order['order_id'];
        //:wq!
        //dd($order_new_id);
         // Call sync-order API
    $response1 = Http::post('https://mppanel.wlmd.dev/ageshiftrx/api/sync-order', [
        'order_id' => $order_new_id
    ]);

    // Call syncordercheckin API
    $response2 = Http::post('https://mppanel.wlmd.dev/ageshiftrx/api/syncordercheckin', [
        'order_id' => $order_new_id
    ]);

            /* ---------- Product ---------- */
            $product_data = DB::table('product')
                ->where('sticky_product_id', $main_product_id)
                ->first();

            if (!$product_data) {
                return null;
            }

	    $product_name = strtolower($product_data->product_name);

                /**
                 * 1️⃣ Medicine code
                 */
                if (str_contains($product_name, 'semaglutide')) {
                    $med_name = 'S';
                } elseif (str_contains($product_name, 'tirzepatide')) {
                    $med_name = 'T';
                } else {
                    $med_name = null;
                }

                preg_match('/(\d+)\s*month/i', $product_data->product_name, $monthMatch);
		$month = $monthMatch[1] ?? null;

            $product_image = DB::table('product_image')
                ->where('product_id', $product_data->product_id)
                ->first();

            $img_video = $product_image
                ? URL("/public/assets/product_img/" . $product_image->img_video)
                : "";

            /* ================= is_wl FROM PRODUCT CATEGORY ================= */
            $is_wl = ($product_data->product_category_name === "Weight Loss") ? 1 : 0;

            /* ================= Get Token ================= */
            if (!isset($clientTokenCache[$is_wl])) {

                $client_list = DB::table('client_key')
                    ->where('is_wl', $is_wl)
                    ->get();

                if ($client_list->isEmpty()) {
                    return null;
                }

                foreach ($client_list as $client) {
                    $tokenResponse = Http::post(
                        "https://api.mdintegrations.com/v1/partner/auth/token",
                        [
                            'grant_type'    => $client->grant_type,
                            'client_id'     => $client->client_id,
                            'client_secret' => $client->client_secret,
                            'scope'         => $client->scope,
                        ]
                    );

                    if ($tokenResponse->successful() && isset($tokenResponse['access_token'])) {
                        $clientTokenCache[$is_wl] = $tokenResponse['access_token'];
                        break;
                    }
                }
            }

            $accessToken = $clientTokenCache[$is_wl] ?? null;
            if (!$accessToken) {
                return null;
            }

            /* ================= BM LOGIC ================= */
            $product_description = $product_data->product_description;

            $bm = 0;
            if (preg_match('/BM:(\d+)/', $product_description, $matches)) {
                $bm = (int)$matches[1];
            }

            switch ($bm) {
                case 2:
                case 3:
                case 12:
                    $total_deliveries = 12;
                    break;
                case 4:
                    $total_deliveries = 4;
                    break;
                case 5:
                    $total_deliveries = 2;
                    break;
                case 6:
                case 7:
                    $total_deliveries = 1;
                    break;
                default:
                    $total_deliveries = 0;
            }

            $completed_deliveries = collect($orders)
                ->where('main_product_id', $main_product_id)
                ->count();

            if ($bm === 0) {
                $remaining_product = "Unlimited";
                $total_display = "Unlimited";
            } else {
                $remaining_product = max(0, $total_deliveries - $completed_deliveries);
                $total_display = $total_deliveries;
            }

            /* ================= Case ID ================= */
            //$case_id = "";
            //if (!empty($order['custom_fields'])) {
             //   foreach ($order['custom_fields'] as $field) {
              //      if (
              //          ($field['name'] ?? '') === 'prescription_info' &&
               //         isset($field['values'][0]['value'])
                 //   ) {
                   //     $value = json_decode($field['values'][0]['value'], true);
                 //       $case_id = $value['case_id'] ?? "";
                //    }
               // }
           // }

	    $global_case_id = "";

            // Orders time ke hisaab se ASC (oldest first)
            usort($orders, fn($a, $b) =>
                strtotime($a['time_stamp']) <=> strtotime($b['time_stamp'])
            );

            foreach ($orders as $o) {
                if (!empty($o['custom_fields'])) {
                    foreach ($o['custom_fields'] as $field) {
                        if (
                            ($field['name'] ?? '') === 'prescription_info' &&
                            isset($field['values'][0]['value'])
                        ) {
                            $value = json_decode($field['values'][0]['value'], true);
                            if (!empty($value['case_id'])) {
                                $global_case_id = $value['case_id'];
                                break 2; // 🔥 mil gaya, stop
                            }
                        }
                    }
                }
            }
            $case_id = "";

            // Pehle current order se try karo
            if (!empty($order['custom_fields'])) {
                foreach ($order['custom_fields'] as $field) {
                    if (
                        ($field['name'] ?? '') === 'prescription_info' &&
                        isset($field['values'][0]['value'])
                    ) {
                        $value = json_decode($field['values'][0]['value'], true);
                        $case_id = $value['case_id'] ?? "";
                    }
                }
            }

            // ❗ Agar latest order me nahi mila → FIRST order ka case_id use karo
            if (empty($case_id)) {
                $case_id = $global_case_id;
            }

            /* ================= Clinician + Prescription ================= */
            $clinician_first = "";
            $clinician_last  = "";
            $prescriptions  = [];

	    $current_level = "";

            if (!empty($case_id)) {
                try {
                    $caseResponse = Http::withToken($accessToken)
                        ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}");

                    if ($caseResponse->successful()) {
                        $clinician = $caseResponse['case_assignment']['clinician'] ?? [];
                        $clinician_first = $clinician['first_name'] ?? "";
                        $clinician_last  = $clinician['last_name'] ?? "";
                    }

                    $prescriptionResponse = Http::withToken($accessToken)
                        ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}/prescriptions");

                    if ($prescriptionResponse->successful()) {
                        foreach ($prescriptionResponse->json() as $p) {

				//$name = $p['name'] ?? '';
$title = $p['title'] ?? '';
                            preg_match('/\b([TS]\d+)\b/i', $title, $match);

				$current_level = $match[1] ?? null;


				$prescriptions[] = [
                                'title'       => $p['title'] ?? "",
                                'name'        => $p['name'] ?? "",
                                'refills'     => $p['refills'] ?? "",
                                'quantity'    => $p['quantity'] ?? "",
                                'days_supply' => $p['days_supply'] ?? "",
                            ];
                        }
                    }
                } catch (\Exception $e) {}
            }

            /* ================= Form Data ================= */
            $formData = $this->get_intake_form_id($order['order_id']);
            $intakeFormId = $formData['intake_form_id'] ?? null;
            $intakeStage  = $formData['stage'] ?? null;

            // Extract continuation link
            $continuationLink = $formData['continuation_link'] ?? null;
  $continuation_stamp = $formData['continuation_stamp'] ?? null;


	      $continuation_date_utc = $formData['continuation_date_utc'] ?? null;

	    $formData22 = $this->get_intake_form_id22($order['order_id']);
	          $msg22 = $formData22;

//	    dd($formData22);


	    if (!empty($continuationLink) && strpos($continuationLink, '?') !== false) {
                $parts = explode('?', $continuationLink);
                $continuationLink = $parts[1] ?? null;
            } else {
                $continuationLink = null;
            }

            // Get check-in form ID and therapy group
            $newFormId = null;
            $therapyGroup = null;

            if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                $newFormId = $formData['checkin_info']['form_id'];
            }

            if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                $therapyGroup = $formData['therapy']['therapy_group'];
            }

            /* ================= is_sub Logic ================= */
            if ($intakeStage === "RECORDED" || $intakeStage === "NEW" || $intakeStage === "REQUIRED-CHECKIN") {

                $is_sub = 0;
            } else {
                $is_sub = 1;
            }

            if ($intakeStage === null) {
                $is_sub = 2;
            }

            $formId = $newFormId ?: $intakeFormId;

            $webhookExists = DB::table('webhook')
                ->where('order_id', $order['order_id'])
                ->where('formId', $formId)
                ->exists();

            if ($webhookExists) {
                $is_sub = 1;
            }

            /* ================= State Name ================= */
            $states_data = DB::table('states')
                ->where('abbreviation', $order['billing_state'])
                ->first();
            $state_name = $states_data->name ?? null;

            /* ================= Form URL ================= */
            if ($product_data->product_category_name == "Weight Loss") {
                $form_url = "https://www.coreagerx.com/onboarding";
            } else {
                $form_url = "https://forms.whitelabelmd.com/" . $intakeFormId;
            }

            /* ================= Check-in Logic ================= */
          /* ================= Check-in Logic ================= */
            $orderTime = $order['time_stamp'] ?? null;
            $formUrlNew = "";
            $intervalDays = null;
            $maxCheckins = null;

            $filledCheckins = DB::table('webhook')
                ->where('order_id', $order['order_id'])
		->where('status',1)
		->count();


	    $current_level = strtoupper($current_level); // safety

            $levelMgMap = [
                // Semaglutide
                'S1' => '0.25',
                'S2' => '1',
                'S4' => '1.5',
                'S5' => '2',
                'S6' => '2.5',

                // Tirzepatide
                'T1' => '2.5',
                'T2' => '5',
                'T3' => '7.5',
                'T4' => '10',
                'T5' => '15',
            ];

	    $level_mg1 = $levelMgMap[$current_level] ?? null;

	    $level_mg = $current_level;


            // ========== BM:5 (6-month) aur BM:6 (12-month) ke liye MULTIPLE CHECK-INS ==========
            if ($bm == 5 || $bm == 6) {

		    $intervalDays = 90;
		   // dd($orderTime);
                $maxCheckins = ($bm == 5) ? 2 : 4;  // BM:5 = 2 check-ins, BM:6 = 4 check-ins

                if ($filledCheckins < $maxCheckins && $orderTime) {

                    $orderDate = \Carbon\Carbon::parse($orderTime);

                    // Next check-in = filled_checkins * 90 - 5
                    $nextCheckinDate = $orderDate
                        ->copy()
                        ->addDays($filledCheckins * $intervalDays)
                        ->subDays(20);

                    if (now()->greaterThanOrEqualTo($nextCheckinDate)) {
                        $formUrlNew = $newFormId
                            ? "https://www.coreagerx.com/weight-loss-checkin" . "/?" . $continuationLink . "&order_id=" . $order['order_id'] . "&med=" . $med_name . "&current_level=" . $level_mg . "&term=" . $month
                            : "";
                    }
                }
	    }

	    else if ($bm == 3 || $bm == 4) {
                $formUrlNew = $newFormId
                    ? "https://www.coreagerx.com/weight-loss-checkin/" . "?" . $continuationLink . "&order_id=" . $order['order_id'] . "&med=" . $med_name . "&current_level=" . $level_mg . "&term=" . $month
                   : "";
            }
            // ========== BM:3, 4, 7, 12 ke liye SINGLE CHECK-IN ==========
            else if (in_array($bm, [ 7, 12]) && $orderTime) {

                $bmDaysMap = [
//                    3 => 25,
                    4 => 80,
                    7 => 270,
                    12 => 360,
                ];


                $totalDays = $bmDaysMap[$bm] ?? 30;
                $allowedDays = $totalDays - 5;

                $orderDate = \Carbon\Carbon::parse($orderTime);
                $eligibleDate = $orderDate->copy()->addDays($allowedDays);

                if (now()->greaterThanOrEqualTo($eligibleDate)) {
                    $formUrlNew = $newFormId
                        ? "https://www.coreagerx.com/weight-loss-checkin/" . $newFormId . "/?" . $continuationLink . "&order_id=" . $order['order_id'] . "&med=" . $med_name . "&current_level=" . $level_mg . "&term=" . $month
			: "";
		}
            }

            //max_checkins and filled_checkins same to  interval_days show null
            if($maxCheckins == $filledCheckins) {
                $intervalDays = null;
            }
            /* ================= Clean Description ================= */
            $clean_description = preg_replace('/\s*BM:\d+/', '', $product_description);

	    $createdAtArray = DB::table('webhook')
    ->where('order_id', $order['order_id'])
    ->orderBy('created_at', 'desc')
    ->pluck('created_at')
    ->toArray();

	    $createdAtArray = array_reverse($createdAtArray);

	//      if ($order['customers_telephone'] === "4152369870") {
        //$form_url = "https://www.coreagerx.com/weight-loss-checkin/?refvalue=johdue12@gmail.com&continuation_id=C2-231990-v4n1RX12&order_id=231990&med=T&current_level=T3&term=12";
//	      }

            return [
                'order_id'             => $order['order_id'] ?? null,
		'stage'                 => $formData['stage'] ?? null,       
		'intakes_dates' 	=> $createdAtArray,
		'unique_id'            => $product_data->uniq_id,
                'time'                 => $order['time_stamp'] ?? null,
		'cc_type'     	       => $order['cc_type'] ?? null, 
		'order_status'         => $order['order_status'] ?? null,
                'tracking_number'      => $order['tracking_number'] ?? null,
                'recurring_date'       => $order['recurring_date'] ?? null,
                'product_img'          => $img_video,
		'product_name' => $this->cleanProductName($product_data->product_name),
		//  'product_name'         => $product_data->product_name,
                'product_price'        => $order['order_total'] ?? null,
                'product_sku'          => $product_data->product_sku,
                'product_category_name'=> $product_data->product_category_name,
                'product_description'  => $clean_description,
                'bm'                   => $bm,
                'total_deliveries'     => $total_display,
                'completed_deliveries' => $completed_deliveries,
                'remaining_product'    => $remaining_product,
                'case_id'              => $case_id,
                'clinician_first_name' => $clinician_first,
                'clinician_last_name'  => $clinician_last,
                'form_url'             => $form_url,
                'is_sub'               => $is_sub,
                'product_id'           => $product_data->product_id,
                'therapy_group'        => $therapyGroup,
		'prescriptions'    => [],
		// 'prescriptions'        => $prescriptions,
                'first_name'           => $order['billing_first_name'] ?? null,
                'last_name'            => $order['billing_last_name'] ?? null,
                'phone'                => $order['customers_telephone'] ?? null,
                'state_name'           => $order['billing_state'] ?? null,
                'billing_postcode'     => $order['billing_postcode'] ?? null,
                'billing_city'         => $order['billing_city'] ?? null,
                'billing_address'      => $order['billing_street_address'] ?? null,
                'billing_state'        => $order['billing_state'] ?? null,
                'country_name'         => $order['billing_country'] ?? null,
                'form_id'              => $intakeFormId,
                'form_id_new'          => $newFormId,
                'form_url_new'         => $formUrlNew,
                'continuation_link'    => $continuationLink,
		'continuation_stamp'   => $continuation_stamp,
		'continuation_date_utc' => $continuation_date_utc,
		// 'form_id_new'          => "",
                // 'form_url_new'         => "",
                // 'continuation_link'    => "",
		'msg22' 		=> $msg22,       
		'interval_days'        => $intervalDays,
                'max_checkins'         => $maxCheckins,
                'filled_checkins'      => $filledCheckins,
            ];

        }, $orders);

        $orderDetails = array_values(array_filter($orderDetails));

        if (empty($orderDetails)) {
            return null;
        }

        usort($orderDetails, fn($a, $b) =>
            strtotime($b['time']) <=> strtotime($a['time'])
        );

        // ✅ ONLY LATEST RECORD
        return $orderDetails[0];
    }

    private function order_find_v582348823431241234($email)
    {
        if (empty($email)) {
            return null;
        }

        /* ================= Sticky Credentials ================= */
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return null;
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        /* ================= Get ALL Sites ================= */
        $sites = DB::table('site')->get();
        if ($sites->isEmpty()) {
            return null;
        }

        $allOrders = [];

        foreach ($sites as $site) {
            if (empty($site->campaign_id)) {
                continue;
            }

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date"  => "01/01/2024",
                "end_date"    => "11/11/2029",
                "criteria"    => ["email" => $email],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            if ($response->successful() && isset($response['data'])) {
                $allOrders = array_merge($allOrders, $response['data']);
            }
        }

        if (empty($allOrders)) {
            return null;
        }

        $orders = $allOrders;

        /* ================= Token Cache (WL / NON-WL) ================= */
        $clientTokenCache = [];

        /* ================= Process Orders ================= */
        $orderDetails = array_map(function ($order) use ($orders, &$clientTokenCache) {

            $main_product_id = $order['main_product_id'] ?? null;
            if (!$main_product_id) {
                return null;
            }

            /* ---------- Product ---------- */
            $product_data = DB::table('product')
                ->where('sticky_product_id', $main_product_id)
                ->first();

            if (!$product_data) {
                return null;
            }

            $product_image = DB::table('product_image')
                ->where('product_id', $product_data->product_id)
                ->first();

            $img_video = $product_image
                ? URL("/public/assets/product_img/" . $product_image->img_video)
                : "";

            /* ================= is_wl FROM PRODUCT CATEGORY ================= */
            $is_wl = ($product_data->product_category_name === "Weight Loss") ? 1 : 0;

            /* ================= Get Token ================= */
            if (!isset($clientTokenCache[$is_wl])) {

                $client_list = DB::table('client_key')
                    ->where('is_wl', $is_wl)
                    ->get();

                if ($client_list->isEmpty()) {
                    return null;
                }

                foreach ($client_list as $client) {
                    $tokenResponse = Http::post(
                        "https://api.mdintegrations.com/v1/partner/auth/token",
                        [
                            'grant_type'    => $client->grant_type,
                            'client_id'     => $client->client_id,
                            'client_secret' => $client->client_secret,
                            'scope'         => $client->scope,
                        ]
                    );

                    if ($tokenResponse->successful() && isset($tokenResponse['access_token'])) {
                        $clientTokenCache[$is_wl] = $tokenResponse['access_token'];
                        break;
                    }
                }
            }

            $accessToken = $clientTokenCache[$is_wl] ?? null;
            if (!$accessToken) {
                return null;
            }

            /* ================= BM LOGIC ================= */
            $product_description = $product_data->product_description;
            $clean_description = preg_replace('/\s*BM:\d+/', '', $product_description);

            $bm = 0;
            if (preg_match('/BM:(\d+)/', $product_description, $matches)) {
                $bm = (int)$matches[1];
            }

            switch ($bm) {
                case 2:
                case 3:
                case 12:
                    $total_deliveries = 12;
                    break;
                case 4:
                    $total_deliveries = 4;
                    break;
                case 5:
                    $total_deliveries = 2;
                    break;
                case 6:
                case 7:
                    $total_deliveries = 1;
                    break;
                default:
                    $total_deliveries = 0;
            }

            $completed_deliveries = collect($orders)
                ->where('main_product_id', $main_product_id)
                ->count();

            if ($bm === 0) {
                $remaining_product = "Unlimited";
                $total_display = "Unlimited";
            } else {
                $remaining_product = max(0, $total_deliveries - $completed_deliveries);
                $total_display = $total_deliveries;
            }

            /* ================= Case ID ================= */
            $case_id = "";
            if (!empty($order['custom_fields'])) {
                foreach ($order['custom_fields'] as $field) {
                    if (
                        ($field['name'] ?? '') === 'prescription_info' &&
                        isset($field['values'][0]['value'])
                    ) {
                        $value = json_decode($field['values'][0]['value'], true);
                        $case_id = $value['case_id'] ?? "";
                    }
                }
            }

            /* ================= Clinician + Prescription ================= */
            $clinician_first = "";
            $clinician_last  = "";
            $prescriptions  = [];

            if (!empty($case_id)) {
                try {
                    $caseResponse = Http::withToken($accessToken)
                        ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}");

                    if ($caseResponse->successful()) {
                        $clinician = $caseResponse['case_assignment']['clinician'] ?? [];
                        $clinician_first = $clinician['first_name'] ?? "";
                        $clinician_last  = $clinician['last_name'] ?? "";
                    }

                    $prescriptionResponse = Http::withToken($accessToken)
                        ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}/prescriptions");

                    if ($prescriptionResponse->successful()) {
                        foreach ($prescriptionResponse->json() as $p) {
                            $prescriptions[] = [
                                'title'       => $p['name'] ?? "",
				'name'        => $p['title'] ?? "",
				'directions'	=>$p['directions'] ?? "",
                                'refills'     => $p['refills'] ?? "",
                                'quantity'    => $p['quantity'] ?? "",
                                'days_supply' => $p['days_supply'] ?? "",
                            ];
                        }
                    }
                } catch (\Exception $e) {}
            }

            $formData = $this->get_intake_form_id($order['order_id']);
            $intakeFormId = $formData['intake_form_id'] ?? null;
            $intakeStage  = $formData['stage'] ?? null;
            $continuationLink = $formData['continuation_link'] ?? null;
            // if($continuationLink)
            // {
            //     $continuationLink = explode('?', $continuationLink)[1];
            // }
            // else
            // {
            //     $continuationLink = null;
            // }
            if (!empty($continuationLink) && strpos($continuationLink, '?') !== false) {
                $parts = explode('?', $continuationLink);
                $continuationLink = $parts[1] ?? null;
            } else {
                $continuationLink = null;
            }
            // $therapyGroup = $formData['therapy_group'] ?? null;
            $newFormId = null;
            $therapyGroup = null;
            if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                $newFormId = $formData['checkin_info']['form_id']; //
            }
            if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                $therapyGroup = $formData['therapy']['therapy_group']; //
            }

            if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                $is_sub = 0;
            } else {
                $is_sub = 1;
            }
            if ($intakeStage === null) {
                $is_sub = 2;
            }
            if($newFormId)
            {
                $formId = $newFormId;
            }
            else
            {
                $formId = $intakeFormId;
            }
            $webhookExists = DB::table('webhook')
            ->where('order_id', $order['order_id'])
            ->where('formId', $formId)
            ->exists();
            if($webhookExists) {
                $is_sub = 1;
            }

            // $state_name = $states_data->name ?? null;

            if($product_data->product_category_name == "Weight Loss")
            {
                $form_url = "https://www.coreagerx.com/onboarding";
            }
            else
            {
                $form_url = "https://forms.whitelabelmd.com/" . $intakeFormId;
            }

            return [
                'order_id'             => $order['order_id'] ?? null,
                'time'                 => $order['time_stamp'] ?? null,
                'order_status'         => $order['order_status'] ?? null,
                'tracking_number'      => $order['tracking_number'] ?? null,
                'recurring_date'       => $order['recurring_date'] ?? null,
                'product_img'          => $img_video,
                'product_name'         => $product_data->product_name,
                'product_price'        => $order['order_total'] ?? null,
                'product_sku'          => $product_data->product_sku,
                'product_category_name'=> $product_data->product_category_name,
                'product_description'  => $clean_description,
                'bm'                   => $bm,
                'total_deliveries'     => $total_display,
                'completed_deliveries' => $completed_deliveries,
                'remaining_product'    => $remaining_product,
                'case_id'              => $case_id,
                'clinician_first_name' => $clinician_first,
                'clinician_last_name'  => $clinician_last,
                'form_id'              => $formId,
                'form_url'             => $form_url,
                'is_sub'               => $is_sub,
                'product_id'           => $product_data->product_id,
                'form_id_new'          => $newFormId,
                'form_url_new'         => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
                'continuation_link'    => $continuationLink,
                'therapy_group'        => $therapyGroup,
                'prescriptions'        => $prescriptions,

		'first_name'           => $order['billing_first_name'] ?? null,
                'last_name'            => $order['billing_last_name'] ?? null,
                'phone'                => $order['customers_telephone'] ?? null,
                'state_name'           => $order['billing_state'] ?? null,
                'billing_postcode'     => $order['billing_postcode'] ?? null,
                'billing_city'         => $order['billing_city'] ?? null,
                'billing_address'      => $order['billing_street_address'] ?? null,
                'billing_state'        => $order['billing_state'] ?? null,
                'country_name'         => $order['billing_country'] ?? null,

            ];

        }, $orders);

        $orderDetails = array_values(array_filter($orderDetails));

        usort($orderDetails, fn($a, $b) =>
            strtotime($b['time']) <=> strtotime($a['time'])
        );

        return $orderDetails;
    }

    private function order_find_v323898412349839281($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                //remove this condition to get all orders
                // $filteredOrders = array_filter($orders, function ($order) {
                //     return $order['order_status'] !== "7";
                // });

                // Loop orders
                foreach ($orders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];
                        $on_hold = $product['on_hold'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook


                        // Intake form
                        $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;
                        $continuationLink = $formData['continuation_link'] ?? null;
                        if($continuationLink)
                        {
                            $continuationLink = explode('?', $continuationLink)[1];
                        }
                        else
                        {
                            $continuationLink = null;
                        }
                        // $therapyGroup = $formData['therapy_group'] ?? null;
                        $newFormId = null;
                        $therapyGroup = null;
                        if (isset($formData['checkin_info']['form_id']) && !empty($formData['checkin_info']['form_id'])) {
                            $newFormId = $formData['checkin_info']['form_id']; //
                        }
                        if (isset($formData['therapy']['therapy_group']) && !empty($formData['therapy']['therapy_group'])) {
                            $therapyGroup = $formData['therapy']['therapy_group']; //
                        }

                        if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                            $is_sub = 0;
                        } else {
                            $is_sub = 1;
                        }
                        if ($intakeStage === null) {
                            $is_sub = 2;
                        }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();

                            if($newFormId)
                            {
                                $formId = $newFormId;
                            }
                            else
                            {
                                $formId = $intakeFormId;
                            }
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->where('formId', $formId)
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }

                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'on_hold'               => $on_hold,
                            'decline_reason'        => $order['decline_reason'] ?? null,
                            'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/" . $intakeFormId,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'stage'                 => $intakeStage,
                            'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                            'form_id'               => $intakeFormId,
                            'form_id_new'           => $newFormId,
                            'form_url_new'          => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
                            'continuation_link'     => $continuationLink,
                            'therapy_group'         => $therapyGroup,

                            // 'data'                  => $order,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }

    private function order_find_v3283823($email)
    {
        if (!empty($email)) {
            $app_key_data = DB::table('app_keys')->first();
            $site_data = DB::table('site')->first();

            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
            $campaign_id = $site_data->campaign_id;

            $payload = [
                "campaign_id" => $campaign_id,
                "start_date" => "01/01/2024",
                "end_date" => "11/11/2029",
                "criteria" => [
                    "email" => $email
                ],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            $responseData = $response->json();
            // dd($responseData);
            if ($response->successful() && isset($responseData['data'])) {
                $orders = $responseData['data'];
                // dd($orders);
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7"; // Exclude status 7
                });

                $orderDetails = [];

                foreach ($filteredOrders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {
                        $stickyProductId = $product['product_id'];

                        // Get product info from DB
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();

                        if (!$productData) {
                            continue; // skip if product not found
                        }

                        $productImage = DB::table('product_image')->where('product_id', $productData->product_id)->first();
                        $img_video = $productImage ? URL("/public/assets/product_img/" . $productImage->img_video) : '';

                         $formData = $this->get_intake_prescription_info($order['order_id']);

                             $states_data = DB::table('states')->where('abbreviation',  $order['billing_state'])->first();
                             $state_name =  $states_data->name;
                              // dd($order['order_id']);
                              $orderDetails[] = [
                                'order_id'          => $order['order_id'] ?? null,
                                'prescription_info' => $formData,
                                'time'              => $order['date_created'] ?? null,  // 🔥 important
                            ];



                    }
                }

                // // Sort by 'time' descending
                // usort($orderDetails, function ($a, $b) {
                //     return strtotime($b['time']) <=> strtotime($a['time']);
                // });
                usort($orderDetails, function ($a, $b) {
                    return strtotime($b['time'] ?? 0) <=> strtotime($a['time'] ?? 0);
                });


                return $orderDetails;
            }

            return null;
        }

        return null;
    }



    private function get_intake_prescription_info($orderId)
    {
        if (!$orderId) {
            return null;
        }

        $url = "https://api.whitelabelmd.com/v1/site/265/order/{$orderId}/prescription-info";
        // $url = "https://stage-api.whitelabelmd.com/v1/site/239/order/136088/prescription-info";


        // Same credentials as Postman
        $username = "whitelabelmd_test_qfBktNLL04XslmzVSXVm201qC3eKT498";
        $password = "XpNVgsZW1ZBrW9WZVMqvOoQGheMM2Ek0";

        // Manual Basic Auth string
        $basicToken = base64_encode($username . ":" . $password);

        $response = Http::withHeaders([
                "Authorization" => "Basic {$basicToken}",
                "authtoken" => "geckos-api-key=gokul428use",
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])
            ->get($url, [     // 🔥🔥 GET — same as Postman
                "debug" => 0  // 🔥 body pass allowed
            ]);
            // "prescription_info": {
            //     "status": "success",

            // if($response->json()['status'] == "success")
            if($response->successful())
            {
                return $response->json();
            }
            else
            {
                return null;
            }
        // dd($response->json());
    }

    public function update_address(Request $request)
    {
        $email = $request->email;
        $address = $request->address;
        $city = $request->city;
        $state = $request->state;
        $postcode = $request->postcode;
        $country = $request->country;
        $phone = $request->phone;
        $shipping_address = $request->shipping_address;
        $shipping_city = $request->shipping_city;
        $shipping_state = $request->shipping_state;
        $shipping_postcode = $request->shipping_postcode;
        $shipping_country = $request->shipping_country;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $creditCardNumber = $request->creditCardNumber;
        $ex_month = $request->ex_month;
        $ex_year = $request->ex_year;
        $cvv = $request->CVV;
        // $order_id = $request->order_id;
        // $user_id = $request->user_id;
        if(!empty($email) && !empty($address) && !empty($city) && !empty($state) && !empty($postcode) && !empty($country) && !empty($phone) && !empty($shipping_address) && !empty($shipping_city) && !empty($shipping_state) && !empty($shipping_postcode) && !empty($shipping_country)
        && !empty($first_name) && !empty($last_name))
        {
            $address_data = DB::table('address')->where('email',$email)->first();
            if($address_data)
            {
                DB::table('address')->where('email',$email)->update([
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'postcode' => $postcode,
                    'country' => $country,
                    'phone' => $phone,
                    'shipping_address' => $shipping_address,
                    'shipping_city' => $shipping_city,
                    'shipping_state' => $shipping_state,
                    'shipping_postcode' => $shipping_postcode,
                    'shipping_country' => $shipping_country,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    // 'order_id' => $order_id,
                ]);
                $app_key_data = DB::table('app_keys')->first();

                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;

                $order_data = $this->order_find($email);
                // dd($order_data);
                if (empty($order_data)) {
                    return response()->json([
                        'status' => 0,
                        'message' => "No orders found with this email."
                    ]);
                }
               foreach($order_data as $order)
               {
                    $order_id = $order['order_id'];

                    // Prepare the data for the API request
                    $payload = [
                        "order_id" => [
                            $order_id => [
                                "billing_address1"  => $address,
                                "billing_city"      => $city,
                                "billing_state"     => $state,
                                "billing_country"   => $country,
                                "billing_zip"       => $postcode,
                                "shipping_address1" => $shipping_address,
                                "shipping_city" => $shipping_city,
                                "shipping_state" => $shipping_state,
                                "shipping_zip" => $shipping_postcode,
                                "shipping_country" => $shipping_country,
                                "first_name" => $first_name,
                                "last_name" => $last_name,
                                "phone" => $phone,
                                "cc_number" => $creditCardNumber,
                                "cc_payment_type" => "Discover",
                                "cc_expiration_date" => $ex_month . $ex_year,
                                "CVV" => $cvv,
                            ]
                        ]
                    ];
                    $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);
                    if($response->successful())
                    {
                        return response()->json([
                            'status' => 1,
                            'message' => "Address updated successfully.",
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "Failed to update address.",
                            'error' => $response->json()
                        ]);
                    }
               }


            }
            else
            {
                return response()->json([
                    'status' => 0,
                    'message' => "Address not found.",
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it.",
            ]);
        }
    }



	public function get_user_cart_products_v2(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

        if (!empty($email)) {
            $order_data = $this->order_find_v2($email);

            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }

            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }


	private function order_find_v2($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
              //  $filteredOrders = array_filter($orders, function ($order) {
                //    return $order['order_status'] !== "7";
               // });

                // Loop orders
                foreach ($orders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();

                        $is_sub = $webhookExists ? 1 : 0;

                        // Intake form
                        $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;

                        if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                            $is_sub = 0;
                        } else {
                            $is_sub = 1;
                        }
                        if ($intakeStage === null) {
                            $is_sub = 2;
                        }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();

			$webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
			    }

                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
			  'decline_reason'            => $order['decline_reason'] ?? null,
			    'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/" . $intakeFormId,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'stage'                 => $intakeStage,
                            'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }

    private function order_find_v299292929($email)
    {
        if (!empty($email)) {
            $app_key_data = DB::table('app_keys')->first();
            $site_data = DB::table('site')->first();

            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
            $campaign_id = $site_data->campaign_id;

            $payload = [
                "campaign_id" => $campaign_id,
                "start_date" => "01/01/2024",
                "end_date" => "11/11/2029",
                "criteria" => [
                    "email" => $email
                ],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            $responseData = $response->json();
            // dd($responseData);
            if ($response->successful() && isset($responseData['data'])) {
                $orders = $responseData['data'];
                // dd($orders);
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7"; // Exclude status 7
                });

                $orderDetails = [];

                foreach ($filteredOrders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {
                        $stickyProductId = $product['product_id'];

                        // Get product info from DB
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();

                        if (!$productData) {
                            continue; // skip if product not found
                        }

                        $productImage = DB::table('product_image')->where('product_id', $productData->product_id)->first();
                        $img_video = $productImage ? URL("/public/assets/product_img/" . $productImage->img_video) : '';

                        //check this order['order_id'] is exit in order table or not
                        $orderExists = DB::table('orders')->where('order_id', $order['order_id'])->exists();
                        // $is_sub = 1;

                        // dd($productData);
                        // if (is_null($productData->form_url) || strtoupper($productData->form_url) === 'NULL') {
                        //     $is_sub = 1;
                        // }
                        // else
                        // {
                            // if (!$orderExists)
                            // {
                            //     $is_sub = 1;
                            // }
                            // else
                            // {
                                //check order exit in webhook table using order_id and $productData->product_id
                              $webhookExists = DB::table('webhook')->where('order_id', $order['order_id'])
                                    // ->where('product_id', $productData->product_id)
                                    ->exists();
                                if ($webhookExists) {
                                    $is_sub = 1; // If webhook exists, set is_sub to 0
                                } else {
                                    // If webhook does not exist, set is_sub to 1
                                    $is_sub = 0;
                                }

                            // }
                        // }
                         $formData = $this->get_intake_form_id($order['order_id']);
                            // dd($formData);
                            $intakeFormId = $formData['intake_form_id'] ?? null;
                            $intakeStage = $formData['stage'] ?? null;
                            // if($intakeStage == "IDENTITY" || $intakeStage =="TRACKED")
                            // {
                            //     $is_sub = 1;
                            // }
                            if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                                $is_sub = 0;
                            } else {
                                $is_sub = 1;
                            }


			    if($intakeStage == null)
                            {
                                $is_sub = 2;
                            }

                            if($intakeStage == null)
                            {
                                $is_sub = 1;
                            }


                            $webhookExists = DB::table('webhook')->where('order_id', $order['order_id'])
                                                                // ->where('product_id', $productData->product_id)
                                                                ->exists();
                                                            if ($webhookExists) {
                                                                $is_sub = 1; // If webhook exists, set is_sub to 0
                                                            }
                             $states_data = DB::table('states')->where('abbreviation',  $order['billing_state'])->first();
                             $state_name =  $states_data->name;
                              // dd($order['order_id']);
                            $orderDetails[] = [
                                'order_id'              => $order['order_id'] ?? null,
                                'time'                  => $order['time_stamp'] ?? null,
                                'order_status'          => $order['order_status'] ?? null,
                                'tracking_number'       => $order['tracking_number'] ?? null,
                                'state_code'            => $order['billing_state'] ?? null,
                                'state_name'            => $state_name,
                                'first_name'            => $order['first_name'] ?? null,
                                'email'                 => $order['email_address'] ?? null,
                                'last_name'             => $order['last_name'] ?? null,
                                'phone'                 => $order['customers_telephone'] ?? null,
                                'product_img'           => $img_video,
                                'product_name'          => $productData->product_name,
                                'form_url'              => "https://forms.whitelabelmd.com/".$intakeFormId,
                                'product_price'         => $product['price'] ?? null,
                                'product_sku'           => $productData->product_sku,
                                'product_category_name' => $productData->product_category_name,
                                'product_description'   => $productData->product_description,
                                'stage'                 => $intakeStage,
                                'is_sub'                => $is_sub,
                                'product_id'            => $productData->product_id,
                            ];


                    }
                }

                // Sort by 'time' descending
                usort($orderDetails, function ($a, $b) {
                    return strtotime($b['time']) <=> strtotime($a['time']);
                });

                return $orderDetails;
            }

            return null;
        }

        return null;
    }

    private function get_intake_form_id($orderId)
    {
        if (!$orderId) {
        return null;
        }

        // API call
       // $response = Http::get("https://api.whitelabelmd.com/client/site/265/order-op/test/{$orderId}");
	
	$apiUrl = "https://api.whitelabelmd.com/client/site/265/order-op/test/" . $orderId;

$response = Http::withHeaders([
    'authtoken' => 'geckos-api-key=gokul428use',
])->get($apiUrl);

	$responseData = $response->json();
        // dd($responseData);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['data']['intake_form_id'])) {
//		      return [
  //      'message' => $responseData['message'] ?? '',
    //    'data'    => $responseData['data']
    //];
		    return $responseData['data']; // return only useful data as array
            }
        }

        return null;
    }


        private function get_intake_form_id22($orderId)
    {
        if (!$orderId) {
        return null;
        }

        // API call
        //$response = Http::get("https://api.whitelabelmd.com/client/site/265/order-op/test/{$orderId}");
	
$apiUrl = "https://api.whitelabelmd.com/client/site/265/order-op/test/" . $orderId;

$response = Http::withHeaders([
    'authtoken' => 'geckos-api-key=gokul428use',
])->get($apiUrl);

	$responseData = $response->json();
        // dd($responseData);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['data']['intake_form_id'])) {
//                    return [
  //      'message' => $responseData['message'] ?? '',
    //    'data'    => $responseData['data']
    //];
                    return $responseData['message']; // return only useful data as array
            }
        }

        return null;
    }


    private function resizeImageIfNeeded($filePath)
    {
        $maxFileSize = 1024 * 1024; // 1 MB
        $fileSize = filesize($filePath);
    
        if ($fileSize > $maxFileSize) {
            // Check the file type
            $imageInfo = getimagesize($filePath);
            $mime = $imageInfo['mime']; // Get the MIME type
    
            // Check if the file is a JPEG image
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                list($width, $height) = $imageInfo;
                $newWidth = 1024;
                $newHeight = ($height / $width) * $newWidth;
    
                $source = imagecreatefromjpeg($filePath);
                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resizedImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
                $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.jpg';
                imagejpeg($resizedImage, $tempPath, 85);
    
                imagedestroy($source);
                imagedestroy($resizedImage);
    
                return $tempPath;
            } else {
                // Handle case where file is not a JPEG (optional based on your application logic)
                // For example, log the error or return the original file path
                Log::error('Unsupported image type: ' . $mime);
                return $filePath; // Return original file path
            }
        }
    
        return $filePath;
    }

    private function create_tokens()
    {
        $client_data = DB::table('client_key')->where('status',1)->first();
        $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
        // $tokenResponse = Http::post($tokenUrl, [
        //     'grant_type' => 'client_credentials',
        //     'client_id' => 'ad6c1f2b-6c82-4ffc-ab9d-e4c3f7bcfd78',
        //     'client_secret' => 'bmZ40l61CgPL2OhUk0voKFb0uMWRmxsHDqGhDwVU',
        //     'scope' => '*',
        // ]);

        $tokenResponse = Http::post($tokenUrl, [
            'grant_type' => $client_data->grant_type,
            'client_id' => $client_data->client_id,
            'client_secret' => $client_data->client_secret,
            'scope' => $client_data->scope,
        ]);

        if ($tokenResponse->successful()) {
            return $tokenResponse['access_token'];
        } else {
            Log::error('Failed to fetch access token: ' . $tokenResponse->body());
            return null;
        }
    }
    private function isJson($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    
    public function edit_full_body_file(Request $request)
    {
        // Validate the input
        $user_id = $request->input('user_id');
        $file = $request->file('file');
        $face = $request->input('face');
        
        // Check if the file and user ID are present
        if (!$file || !$user_id) {
            return response()->json([
                'status' => 0,
                'message' => "File or user ID is missing.",
            ], 400);
        }
    
        // Fetch access token
        $accessToken = $this->create_tokens(); // Ensure this method returns a valid token
        
        if (!$accessToken) {
            Log::error('Failed to fetch access token');
            return response()->json([
                'status' => 0,
                'message' => "Failed to fetch access token.",
            ], 500);
        }
    
        // Handle file upload to local storage
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('cases_images', 'public');
        $fileAbsolutePath = Storage::disk('public')->path($filePath);
    
        // Resize image if necessary
        $resizedImagePath = $this->resizeImageIfNeeded($fileAbsolutePath);
    
        try {
            // Step 1: Upload the file to the external API
            $fileUploadUrl = "https://api.mdintegrations.com/v1/partner/files";
            $fileResponse = Http::withToken($accessToken)->attach(
                'file',
                file_get_contents($resizedImagePath),
                $fileName
            )->post($fileUploadUrl);
    
            if (!$fileResponse->successful()) {
                Log::error('Failed to upload file to API: ' . $fileResponse->body());
                return response()->json([
                    'status' => 0,
                    'message' => "Failed to upload file to API.",
                ], 500);
            }
    
            $fileData = $fileResponse->json();
            $fileId = $fileData['file_id'] ?? null;
    
            if (!$fileId) {
                Log::error('Invalid API response, file_id missing: ' . json_encode($fileData));
                return response()->json([
                    'status' => 0,
                    'message' => "Invalid file upload response from API.",
                ], 500);
            }
    
            // Step 2: Store the file data in the database
            $submissionData = DB::table('submissions')->where('user_id', $user_id)->first();
            if (!$submissionData) {
                return response()->json([
                    'status' => 0,
                    'message' => "Submission data not found.",
                ], 404);
            }
    
            DB::table('cases_images')->insert([
                'submissions_id' => $submissionData->submissions_id,
                'form_id'        => $submissionData->form_id,
                'img_video'      => basename($fileAbsolutePath),
                'user_id'        => $submissionData->user_id,
                'file_id'        => $fileId,
                'created_at'     => now(),
            ]);
    
            // Step 3: Update patient data with the file ID
            $caseData = DB::table('cases')->where('user_id', $user_id)->first();
            if (!$caseData) {
                return response()->json([
                    'status' => 0,
                    'message' => "Case data not found.",
                ], 404);
            }
    
            $caseId = $caseData->case_id;
            $updatePatientUrl = "https://api.mdintegrations.com/v1/partner/cases/{$caseId}/files/{$fileId}";
            $patientResponse = Http::withToken($accessToken)->post($updatePatientUrl);
    
            if (!$patientResponse->successful()) {
                Log::error('Failed to update patient data: ' . $patientResponse->body());
                return response()->json([
                    'status' => 0,
                    'message' => "Failed to update patient data.",
                ], 500);
            }
    
            // Step 4: Update request status in the database
            DB::table('re_full_body_file_request')->where('user_id', $user_id)->update([
                'status'      => 2,
                'updated_at'  => now(),
            ]);
    
            // Success response
            return response()->json([
                'status' => 1,
                'message' => "Successfully updated patient with driver_license_id.",
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error occurred during file processing: ' . $e->getMessage());
            return response()->json([
                'status' => 0,
                'message' => "An error occurred during the process.",
            ], 500);
        }
    }

    public function getUserNotifications(Request $request)
    {
        $email = $request->email;

        if (!$email) {
            return response()->json([
                'status' => 0,
                'message' => 'Email required'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP A: DOCTOR notifications STORE (INLINE, no extra function)
        |--------------------------------------------------------------------------
        */
        $doctorData = DB::table('leadconnectorhq_data')
            ->where('email', $email)
            ->orderBy('timestamp', 'DESC')
            ->get();

        foreach ($doctorData as $row) {

            // duplicate check (order_id + type)
            $exists = DB::table('notifications')
                ->where('order_id', $row->order_id)
                ->where('type', 'DOCTOR_MESSAGE')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notifications')->insert([
                'user_email' => $email,
                'order_id'   => $row->order_id,
                'type'       => 'DOCTOR_MESSAGE',
                'title'      => 'Doctor Message',
                'message'    => 'You have received a new message from your doctor.',
                'timestamp'  => $row->timestamp,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP B: FINAL notifications FETCH (doctor + checkin)
        |--------------------------------------------------------------------------
        */
        $notifications = DB::table('notifications')
            ->where('user_email', $email)
            ->orderBy('timestamp', 'DESC')
            ->get();

        return response()->json([
            'status' => 1,
            'data'   => $notifications
        ]);
    }


    private function storeNotification($email,$orderId,$type,$title,$message,$timestamp)
    {
        if (!$email || !$type || !$timestamp) {
            return;
        }
        $exists = DB::table('notifications')
            ->where('order_id', $orderId)
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return; // ❌ duplicate
        }

        DB::table('notifications')->insert([
            'user_email' => $email,
            'order_id'   => $orderId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'timestamp'  => $timestamp,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function readNotification(Request $request)
    {
        $notificationId = $request->notification_id;

        if (!$notificationId) {
            return response()->json([
                'status' => 0,
                'message' => 'notification_id required'
            ]);
        }

        $updated = DB::table('notifications')
            ->where('id', $notificationId)
            ->update([
                'is_read'    => 1,
                'updated_at'=> now()
            ]);

        if (!$updated) {
            return response()->json([
                'status' => 0,
                'message' => 'Notification not found'
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Notification marked as read'
        ]);
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
        ])->get("https://stage-api.whitelabelmd.com/v1/site/265/order/{$orderId}/order-timeline");

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
            if ($item['label'] === 'Order Shipped' && !empty($item['data']['tracking_number'])) {
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

    public function readAllNotifications(Request $request)
    {
        $email = $request->email;

        if (!$email) {
            return response()->json([
                'status' => 0,
                'message' => 'Email required'
            ]);
        }

        DB::table('notifications')
            ->where('user_email', $email)
            ->where('is_read', 0)
            ->update([
                'is_read'    => 1,
                'updated_at'=> now()
            ]);

        return response()->json([
            'status' => 1,
            'message' => 'All notifications marked as read'
        ]);
    }

    public function re_full_body_file_req(Request $request)
    {
        $user_id = $request->user_id;
        // dd($user_id);
        if($user_id !="")
        {
            $is_data = DB::table('re_full_body_file_request')->where('status',1)->where('user_id',$user_id)->count();
            if($is_data > 0)
            {
                $result['is_upolad']       = 1;
            }   
            else
            {
                $result['is_upolad']       = 0;
            }
        }
        else
        {
            $result['status']       = 0;
            $result['message']         = "Please fill out all the required details in the form before submitting it.";        
        }
        return response()->json($result);

    }

    public function re_fileUpload_req(Request $request)
    {
        $user_id = $request->user_id;
        // dd($user_id);
        if($user_id !="")
        {
            $is_data = DB::table('re_fileupload_request')->where('status',1)->where('user_id',$user_id)->count();
            if($is_data > 0)
            {
                $result['is_upolad']       = 1;
            }   
            else
            {
                $result['is_upolad']       = 0;
            }
        }
        else
        {
            $result['status']       = 0;
            $result['message']         = "Please fill out all the required details in the form before submitting it.";        
        }
        return response()->json($result);

    }

    public function chack_user_submitted_form(Request $request)
    {
        $user_id = $request-> user_id;
        $form_id = $request-> form_id;

        if($user_id !='' && $form_id !='')
        {
            $is_user =  DB::table('submissions')->where('user_id',$user_id)->where('form_id',$form_id)->count();

            if($is_user > 0)
            {
                $result['status']           = 0;
                $result['message']          = "fail";
            }
            else
            {
                $result['status']           = 1;
                $result['message']          = "Success";
       
            }
        }
        else
        {
            $result['status'] = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }
        return response()->json($result);

    }

    public function edit_file(Request $request)
    {
        $user_id = $request->user_id;
        $file = $request->file('file');
        $face = $request->face;

        if ($file != null && $user_id != "") {
            $accessToken = $this->create_tokens(); // Ensure this method returns a valid token
        
            if($accessToken) {
                $fileName = $file->getClientOriginalName();
                $filePath = $file->store('uploads', 'public');
                $fileAbsolutePath = Storage::disk('public')->path($filePath);

                $test = basename($fileAbsolutePath);
                // Check and resize image if necessary
                $resizedImagePath = $this->resizeImageIfNeeded($fileAbsolutePath);

                // Step 1: Upload the file using the API
                $fileUploadUrl = "https://api.mdintegrations.com/v1/partner/files";
                $fileResponse = Http::withToken($accessToken)->attach(
                    'file',
                    file_get_contents($resizedImagePath),
                    $fileName
                )->post($fileUploadUrl, [
                    'name' => $face,
                ]);

                if ($fileResponse->successful()) {
                    $fileData = $fileResponse->json();

                    // Step 2: Store user data with file_id
                    DB::table('user')->where('user_id', $user_id)->update([
                        'file_name'     => $test,
                        'file_id'       => $fileData['file_id'], // Store the file_id from API response
                        'face'          => $face,
                        'updated_at'    => now(),
                    ]);

                    $table_data = DB::table('patients')->where('user_id', $user_id)->first();
                    
                    if ($table_data) {
                        $patient_id = $table_data->patient_id;

                        // Step 3: Make the second API call to update the patient with the driver_license_id
                        $updatePatientUrl = "https://api.mdintegrations.com/v1/partner/patients/{$patient_id}";
                        $patientResponse = Http::withToken($accessToken)->patch($updatePatientUrl, [
                            'driver_license_id' => $fileData['file_id'], // Use file_id from the first API call
                        ]);

                        // dd($patientResponse);
                        if ($patientResponse->successful()) {
                            
                            DB::table('re_fileupload_request')->where('user_id', $user_id)->update([
                                'status'          => 2,
                                'updated_at'    => now(),
                            ]);
                            $result['status'] = 1;
                            $result['message'] = "Successfully updated patient with driver_license_id.";
                        } else {
                            Log::error('Failed to update patient data: ' . $patientResponse->body());
                            $result['status'] = 0;
                            $result['message'] = "Failed to update patient data.";
                        }
                    } else {
                        $result['status'] = 0;
                        $result['message'] = "Patient not found.";
                    }
                } else {
                    Log::error('Failed to upload file to API: ' . $fileResponse->body());
                    $result['status'] = 0;
                    $result['message'] = "Failed to upload file to API.";
                }
            } else {
                Log::error('Failed to fetch access token: ' . $accessToken->body());
                $result['status'] = 0;
                $result['message'] = "Failed to fetch access token.";
            }
        } else {
            $result['status'] = 0;
            $result['message'] = "File or user_id is missing.";
        }

        return response()->json($result);
    }

    public function check_user_data_completed(Request $request)
    {
        $user_id = $request->user_id;

        if($user_id !='')
        {
            $is_user = DB::table('user')->where('user_id',$user_id)->count();

            if($is_user > 0)
            {
                $user_data = DB::table('user')->where('user_id',$user_id)->first();

                if($user_data->completed == 1)
                {
                    $result['is_completed']     = 1;
                    $result['status']           = 1;
                    $result['message']          = "Success!";
                }
                else
                {
                    $result['is_completed']     = 0;
                    $result['status']           = 1;
                    $result['message']          = "Success!";
                }
            }
            else
            {
                $result['status']  = 0;
                $result['message'] = "Somting With Wrong";
            }
        }
        else
        {
            $result['status'] = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }
        return response()->json($result);

    }

    public function complete_user_data(Request $request)
    {
        $prefix         = $request->prefix;
        $ssn            = $request->ssn;
        $gender         = $request->gender;
        $date_of_birth  = $request->date_of_birth;
        $phone_type     = $request->phone_type;
        $metadata       = $request->metadata;
        $user_id        = $request->user_id;
        $pregnancy      = $request->pregnancy;
        $file           = $request->file('file');
        $face           = $request->face;
    
        if ($file != null && $user_id != "") 
        {
            $is_user = DB::table('user')->where('user_id',$user_id)->count();
    
            if ($is_user > 0)
            {
                // Step 1: Log Access Token and Ensure Token is Valid
                $accessToken_2 = $this->create_tokens();
                Log::info("Access Token: $accessToken_2");
    
                // Step 2: Store the file locally
                $fileName = $file->getClientOriginalName();
                $filePath = $file->store('uploads', 'public');
                $fileAbsolutePath = Storage::disk('public')->path($filePath);
                Log::info("File stored at: $fileAbsolutePath");
    
                // Resize the image if necessary
                $resizedImagePath = $this->resizeImageIfNeeded($fileAbsolutePath);
                Log::info("Resized Image Path: $resizedImagePath");
    
                // Step 3: Upload the file to the API
                try {
                    $fileUploadUrl = "https://api.mdintegrations.com/v1/partner/files";
    
                    // Log file upload data
                    Log::info("Uploading file: $fileName to API");
    
                    $fileResponse = Http::withToken($accessToken_2)->attach(
                        'file', 
                        file_get_contents($resizedImagePath), 
                        $fileName
                    )->post($fileUploadUrl, [
                        'name' => $face, // 'face' should be set properly
                    ]);
    
                    // Log the full response body
                    Log::info("API Response: " . $fileResponse->body());
    
                    if ($fileResponse->successful()) {
                        $fileData = $fileResponse->json();
    
                        // Step 4: Update the user's data with the file ID from API response
                        DB::table('user')->where('user_id', $user_id)->update([
                            'prefix'        => $prefix,
                            'ssn'           => $ssn,
                            'gender'        => $gender,
                            'date_of_birth' => $date_of_birth,
                            'phone_type'    => $phone_type,
                            'metadata'      => $metadata,
                            'pregnancy'     => $pregnancy,
                            'email_otp'     => 1234,
                            'file_name'     => $fileName,
                            'file_id'       => $fileData['file_id'], // Store the file_id from API response
                            'face'          => $face,
                            'completed'     => 1,
                            'updated_at'    => now(),
                        ]);
                        if($gender == 1)
                        {
                            $male = "Male";
                        }
                        else
                        {
                            $male = "Female";

                        }
                            // Assuming $date_of_birth is in '1998-02-25' format
                        $dateOfBirth = Carbon::parse($date_of_birth);
                        // $result['date_of_birth'] = $dateOfBirth->format('d-m-Y');
                        $result['month']  = $dateOfBirth->format('n');
                        $result['day']  = $dateOfBirth->format('j');
                        $result['year']  = $dateOfBirth->format('Y');
                        $result['gender']  = $male;
                        // $result['date_of_birth']  = $date_of_birth;
                        $result['status']  = 1;
                        $result['message'] = "Congratulations! Your data was successfully added.";
                    } else {
                        // Log error response
                        Log::error('Failed to upload file to API: ' . $fileResponse->body());
                        $result['status'] = 0;
                        $result['message'] = "Failed to upload file to API.";
                    }
                } catch (\Exception $e) {
                    // Log any exceptions during file upload
                    Log::error("File Upload Error: " . $e->getMessage());
                    $result['status'] = 0;
                    $result['message'] = "Error during file upload: " . $e->getMessage();
                }
            } else {
                $result['status']  = 0;
                $result['message'] = "User not found.";
            }
        } else {
            $result['status'] = 0;
            $result['message'] = "Please fill out all required details in the form before submitting it.";
        }
    
        return response()->json($result);
    }

    public function get_user_cart_products(Request $request)
    {
        $email = $request->email;
    
        if (!empty($email)) {
            $order_data = $this->order_find($email);
            
            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }
    
            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }

    // public function get_user_conform_cart_product(Request $request)
    // {
    //     $customer_id = $request->customer_id;
    
    //     if (!empty($customer_id)) {
    //         // Fetch app keys
    //         $app_key_data = DB::table('app_keys')->first();
    //         $app_key    = $app_key_data->app_key;
    //         $app_secret = $app_key_data->app_secret;
    
    //         // Set the payload
    //         $payload = [
    //             "customer_id" => $customer_id,
    //             // "member_password" => $password
    //         ];
    
    //         // Make the request with basic authentication
    //         $response = Http::withBasicAuth($app_key, $app_secret)
    //             ->post('https://whitelabelmd.sticky.io/api/v1/customer_view', $payload);
    
    //         $responseData = $response->json();
    
    //         // Check if the response is successful and contains 'data'
    //         if ($response->successful()) {
    //             $order_list = $responseData['order_list'];
    //             // $order_data = $this->order_data_get($order_list);
    //             $order_data = $this->order_data_get($order_list);
    //             return response()->json([
    //                 // 'order_list' => $order_list,
    //                 'product' => $order_data,
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => 'Failed to retrieve User data from the external service.',
    //                 'error' => $responseData
    //             ]);
    //         }
    //     } else {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => "Please fill out all the required details."
    //         ]);
    //     }
    // }

    
    public function get_user_conform_cart_product(Request $request)
    {
        $email = $request->email;
    
        if (!empty($email)) {
            $order_data = $this->order_find($email);
            
            // If $order_data is null or empty, return a failure message
            if (empty($order_data)) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }
    
            return response()->json([
                'product' => $order_data,
                'status' => 1,
                'message' => "Great! You have Successfully retrieved all records."
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }


    private function order_find3883($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });

                // Loop orders
                foreach ($filteredOrders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook
                         $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();

                        // $is_sub = $webhookExists ? 1 : 0;

                        // Intake form
                        // $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;

                        // if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                        //     $is_sub = 0;
                        // } else {
                        //     $is_sub = 1;
                        // }
                        // if ($intakeStage === null) {
                        //     $is_sub = 2;
                        // }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }

                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/" . $intakeFormId,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            // 'stage'                 => $intakeStage,
                            // 'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }

    private function order_finddfjdjfjndf($email)
    {
        if (!empty($email)) {

            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();

            if ($site_data->isEmpty()) {
                return null; // No sites found
            }

            $orderDetails = [];

            // 🔹 Loop through all sites
            foreach ($site_data as $site) {

                $campaign_id = $site->campaign_id;

                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);

                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

                $responseData = $response->json();
                // dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }

                $orders = $responseData['data'];

                // Filter out status = 7
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });

                // Loop orders
                foreach ($filteredOrders as $order) {
                    $products = $order['products'] ?? [];

                    foreach ($products as $product) {

                        $stickyProductId = $product['product_id'];

                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }

                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();

                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";

                        // Check webhook
                         $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();

                        // $is_sub = $webhookExists ? 1 : 0;

                        // Intake form
                        // $formData = $this->get_intake_form_id($order['order_id']);
                        $intakeFormId = $formData['intake_form_id'] ?? null;
                        $intakeStage  = $formData['stage'] ?? null;

                        // if ($intakeStage === "RECORDED" || $intakeStage === "NEW") {
                        //     $is_sub = 0;
                        // } else {
                        //     $is_sub = 1;
                        // }
                        // if ($intakeStage === null) {
                        //     $is_sub = 2;
                        // }

                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();
                        $webhookExists = DB::table('webhook')
                            ->where('order_id', $order['order_id'])
                            ->exists();
                            if($webhookExists) {
                                $is_sub = 1;
                            }

                        $state_name = $states_data->name ?? null;

                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
                            'order_status'          => $order['order_status'] ?? null,
                            'tracking_number'       => $order['tracking_number'] ?? null,
                            'state_code'            => $order['billing_state'] ?? null,
                            'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
                            'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/" . $intakeFormId,
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            // 'stage'                 => $intakeStage,
                            // 'is_sub'                => $is_sub,
                            'product_id'            => $productData->product_id,
                        ];
                    }
                }
            }

            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });

            return $orderDetails;
        }

        return null;
    }


private function order_find($email)
    {
        if (!empty($email)) {
    
            // Get app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
    
            // 🔹 Get ALL sites instead of first()
            $site_data = DB::table('site')->get();
    
            if ($site_data->isEmpty()) {
                return null; // No sites found
            }
    
            $orderDetails = [];
    
            // 🔹 Loop through all sites
            foreach ($site_data as $site) {
    
                $campaign_id = $site->campaign_id;
    
                // Payload
                $payload = [
                    "campaign_id" => $campaign_id,
                    "start_date"  => "01/01/2024",
                    "end_date"    => "11/11/2029",
                    "criteria"    => [
                        "email" => $email
                    ],
                    "return_type" => "order_view"
                ];
                // dd($payload);
    
                // API Call
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);
    
                $responseData = $response->json();
               //  dd($responseData);
                if (!$response->successful() || !isset($responseData['data'])) {
                    continue;
                }
    
                $orders = $responseData['data'];
  // 		dd($orders); 
                // Filter out status = 7
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });
    
                // Loop orders
                foreach ($filteredOrders as $order) {
                    $products = $order['products'] ?? [];
    
                    foreach ($products as $product) {
    
                        $stickyProductId = $product['product_id'];
    
                        // Fetch product
                        $productData = DB::table('product')->where('sticky_product_id', $stickyProductId)->first();
                        if (!$productData) {
                            continue;
                        }
    
                        $productImage = DB::table('product_image')
                            ->where('product_id', $productData->product_id)
                            ->first();
    
                        $img_video = $productImage
                            ? URL("/public/assets/product_img/" . $productImage->img_video)
                            : "";
    
                  
                        // State
                        $states_data = DB::table('states')
                            ->where('abbreviation', $order['billing_state'])
                            ->first();
                   
    
    
			$cc_type = $order['cc_type'];
			$state_name = $states_data->name ?? null;

			  $product_main_id = DB::table('product')
                            ->where('product_id', $productData->product_id)
                            ->first();


			$offline_transaction_id = collect($order['custom_fields'])
				->firstWhere('token_key', 'offline_stripe_order_id')['values'][0]['value'] ?? null;


                        // Final push
                        $orderDetails[] = [
                            'order_id'              => $order['order_id'] ?? null,
                            'time'                  => $order['time_stamp'] ?? null,
			   'bm'                    => $product_main_id->product_description,
			'offline_transaction_id'   => $offline_transaction_id,
			   'order_status'          => $order['order_status'] ?? null,
'cc_type'               => $cc_type,       
				    'tracking_number'       => $order['tracking_number'] ?? null,
			    'shipping_street_address'  => $order['shipping_street_address'] ?? null,
			    'state_code'            => $order['shipping_state'] ?? null,
			    'shipping_city'         => $order['shipping_city'] ?? null,
			    'shipping_postcode'    => $order['shipping_postcode'] ?? null,
			    'customers_telephone'   => $order['customers_telephone'] ?? null,
			    'state_name'            => $state_name,
                            'first_name'            => $order['first_name'] ?? null,
                            'email'                 => $order['email_address'] ?? null,
                            'last_name'             => $order['last_name'] ?? null,
                            'phone'                 => $order['customers_telephone'] ?? null,
                            'product_img'           => $img_video,
			    'product_name' => $this->cleanProductName($productData->product_name),
			    // 'product_name'          => $productData->product_name,
                            'form_url'              => "https://forms.whitelabelmd.com/",
                            'product_price'         => $product['price'] ?? null,
                            'product_sku'           => $productData->product_sku,
                            'product_category_name' => $productData->product_category_name,
                            'product_description'   => $productData->product_description,
                            'product_id'            => $productData->product_id,
                        ];
                    }
                }
            }
    
            // Sort all order details
            usort($orderDetails, function ($a, $b) {
                return strtotime($b['time']) <=> strtotime($a['time']);
            });
    
            return $orderDetails;
        }
    
        return null;
    }

private    function cleanProductName($name) {

    // 1. Remove anything inside brackets ( )
    $name = preg_replace('/\s*\(.*?\)\s*/', ' ', $name);

    // 2. Remove mg/ml/units patterns
    $name = preg_replace('/\b\d+(\.\d+)?\s*(mg|ml|units|mg\/ml|mg\/week|mg\/weekly|mg\/wk)\b/i', '', $name);

    // 3. Remove "Any dose..." text
    $name = preg_replace('/-?\s*Any dose.*$/i', '', $name);

    // 4. Remove duplicate spaces
    $name = preg_replace('/\s+/', ' ', $name);

    // 5. Fix spacing around dash
    $name = preg_replace('/\s*-\s*/', ' - ', $name);

    return trim($name);
}

    private function order_find_old($email)
    {
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
                "end_date" => "11/11/2027",
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


                // $main_product_id =  $order['main_product_id'];
                // $main_product_id =  $responseData['main_product_id'];
                // Filter out orders with "order_status": "7"
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });

                // Map filtered orders to required structure
                $orderDetails = array_map(function ($order) {
                    $main_product_id =  $order['main_product_id'];

                    $img_video =  "";
                    // $main_product_id =  $product['product_id'];
                    // $main_product_id =  $product['product_id'];
                    $product_data = DB::table('product')->where('sticky_product_id',$main_product_id)->first();
                    $product_image = DB::table('product_image')->where('product_id',$product_data->product_id)->first();
                // dd($main_product_id);
                    if($product_image)
                    {
                        $img_video = URL("/public/assets/product_img/" . $product_image->img_video);

                    }
                    $order_total = $order['order_total'] ?? null;
                    // dd($main_product_id);
                    return [
                        'order_id'              => $order['order_id'] ?? null,
                        'time'                  => $order['time_stamp'] ?? null,
                        'order_status'          => $order['order_status'] ?? null,
                        'tracking_number'       => $order['tracking_number'] ?? null,
                        'product_img'           => $img_video,
                        'product_name'          => $product_data->product_name,
                        'product_price'         => $order_total,
                        'product_sku'           => $product_data->product_sku,
                        'product_category_name' => $product_data->product_category_name,
                        'product_description'   => $product_data->product_description,
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

    private function order_find_1233($email)
    {
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
                "end_date" => "11/11/2027",
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


                // $main_product_id =  $order['main_product_id'];
                // $main_product_id =  $responseData['main_product_id'];
                // Filter out orders with "order_status": "7"
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });

                // Map filtered orders to required structure
                $orderDetails = array_map(function ($order) {
                    $main_product_id =  $order['main_product_id'];

                    $img_video =  "";
                    // $main_product_id =  $product['product_id'];
                    // $main_product_id =  $product['product_id'];
                    $product_data = DB::table('product')->where('sticky_product_id',$main_product_id)->first();
                    $product_image = DB::table('product_image')->where('product_id',$product_data->product_id)->first();
                // dd($main_product_id);
                    if($product_image)
                    {
                        $img_video = URL("/public/assets/product_img/" . $product_image->img_video);

                    }
                    $order_total = $order['order_total'] ?? null;
                    // dd($main_product_id);
                    return [
                        'order_id'              => $order['order_id'] ?? null,
                        'time'                  => $order['time_stamp'] ?? null,
                        'order_status'          => $order['order_status'] ?? null,
                        'tracking_number'       => $order['tracking_number'] ?? null,
                        'product_img'           => $img_video,
                        'product_name'          => $product_data->product_name,
                        'product_price'         => $order_total,
                        'product_sku'           => $product_data->product_sku,
                        'product_category_name' => $product_data->product_category_name,
                        'product_description'   => $product_data->product_description,
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

    private function order_finddjnnjdfnjdf($email)
    {
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
                "end_date" => "11/11/2027",
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
               
                
                // $main_product_id =  $order['main_product_id'];
                // $main_product_id =  $responseData['main_product_id'];
                // Filter out orders with "order_status": "7"
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });
    
                // Map filtered orders to required structure
                $orderDetails = array_map(function ($order) {
                    $main_product_id =  $order['main_product_id'];

                    $img_video =  "";
                    // $main_product_id =  $product['product_id'];
                    // $main_product_id =  $product['product_id'];
                    $product_data = DB::table('product')->where('sticky_product_id',$main_product_id)->first();
                    $product_image = DB::table('product_image')->where('product_id',$product_data->product_id)->first();
                // dd($main_product_id);
                    if($product_image)
                    {
                        $img_video = URL("/public/assets/product_img/" . $product_image->img_video);
                            
                    }
                    $order_total = $order['order_total'] ?? null;
                    // dd($main_product_id);
                    return [
                        'order_id'              => $order['order_id'] ?? null,
                        'time'                  => $order['time_stamp'] ?? null,
                        'order_status'          => $order['order_status'] ?? null,
                        'tracking_number'       => $order['tracking_number'] ?? null,
                        'product_img'           => $img_video,
                        'product_name'          => $product_data->product_name,
                        'product_price'         => $order_total,
                        'product_sku'           => $product_data->product_sku,
                        'product_category_name' => $product_data->product_category_name,
                        'product_description'   => $product_data->product_description,
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

    
    
    
    private function order_data_get($order_ids)
    {
        if (!empty($order_ids)) {
            // Fetch app keys again
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
    
             // Ensure $order_ids is an array (in case it's a string or something else)
            if (!is_array($order_ids)) {
                $order_ids = explode(',', $order_ids); // Convert to array if it's a comma-separated string
            }
            // Iterate over each order ID and fetch details
            $order_data = [];
            foreach ($order_ids as $order_id) {
                $payload = [
                    "order_id" => $order_id,  // Pass order_id as a single value (not an array)
                    "return_variants" => 1      // Include return_variants as required
                ];
    
                // Make the request for each order ID
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_view', $payload);
    
                $responseData = $response->json();
    
                if ($response->successful()) {
                    // Extract time_stamp and order_status or set to null
                    $order_data[] = [
                        'order_id' => $order_id,
                        'time'      => $responseData['time_stamp'] ?? null,
                        'order_status' => $responseData['order_status'] ?? null,
                        'tracking_number' => $responseData['tracking_number'] ?? null,
                    ];
                } else {
                    // Return error for the specific order if failed
                    $order_data[] = [
                        'order_id' => $order_id,
                        'status' => 'failed',
                        'error' => $responseData
                    ];
                }
            }
    
            return $order_data;
    
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please provide valid order IDs."
            ]);
        }
    }
    

    public function get_user_cart_product_details(Request $request)
    {
        $order_id = $request->order_id;
 //  dd($order_id); 
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
           //  dd($responseData);
    
            if ($response->successful()) {
                // Extract product details from the response
                $productDetails = [];
                $time = $responseData['time_stamp'] ?? null;
                $totalsBreakdown = $responseData['totals_breakdown'] ?? [];
                $coupon_discount_amount = $responseData['coupon_discount_amount'] ?? null;

		$firstname = $responseData['first_name'] ?? null;
		$shipping_street_address = $responseData['shipping_street_address'] ?? null;
$state_code = $responseData['shipping_state'] ?? null;
$shipping_city = $responseData['shipping_city'] ?? null;
$shipping_postcode = $responseData['shipping_postcode'] ?? null;
$customers_telephone = $responseData['customers_telephone'] ?? null;
$email = $responseData['email_address'] ?? null;
$last_name = $responseData['last_name'] ?? null;
$first_name = $responseData['first_name'] ?? null;
		$tracking_number = $responseData['tracking_number'] ?? null;
                if (isset($responseData['products']) && is_array($responseData['products'])) {
                    foreach ($responseData['products'] as $product) {
//foreach ($responseData['products'] as $index => $product) {
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

		 $product_main_id = DB::table('product')
                            ->where('sticky_product_id', $product['product_id'])
                            ->first();

	//		dd($product);

			$productDetails[] = [
		'system_product_id' => $product_main_id->product_id,      
				'bm'                    => $product_main_id->product_description,
'shipping_street_address'  => $shipping_street_address,
'state_code' => $state_code,
'shipping_city' => $shipping_city,
'shipping_postcode' => $shipping_postcode,
'customers_telephone' => $customers_telephone,
'email' => $email,
'last_name' => $last_name,
'first_name' => $first_name,
'product_id' => $product['product_id'] ?? null,
'product_name' => $this->cleanProductName($product['name']),
//        'product_name' => $product['name'] ?? null,
                            'product_price' => $product['price'] ?? null,
                            'recurring_date' => $product['recurring_date'] ?? null,
                    //        'next_billing_date' => $product['recurring_date'] ?? null,
		'next_billing_date' => "0000-00-00",      
			    'sku' => $product['sku'] ?? null, // Example of adding more product fields
                            'quantity' => $product['product_qty'] ?? 0, // Ensure to capture the quantity as well
                            'time_stamp' => $time,
                            'totalsBreakdown' => $totalsBreakdown,
		//	    'coupon_discount_amount' => ($index == 0) ? $coupon_discount_amount : 0,
			     'coupon_discount_amount' => $coupon_discount_amount,
                            'time' => $time,
                            'order_id' => $order_id,
                            'product_img'  => $img_video,
                            'trackingNumber' => $tracking_number ?? null,
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

    


    public function cancel_user_cart_product(Request $request)
    {
        $user_cart_product_id = $request->user_cart_product_id;

        if($user_cart_product_id !="")
        {
            $is_cart = DB::table('user_cart_product')->where('user_cart_product_id',$user_cart_product_id)->count();

            if($is_cart)
            {
                DB::table('user_cart_product')
                    ->where('user_cart_product_id',$user_cart_product_id)
                    ->update([
                        'status'        => 4,
                        'updated_at'    => now(),
                    ]);

                $result['status'] = 1;
                $result['message'] = "Congratulations! Product cancel successfully.";
            }
            else
            {
                $result['status']  = 0;
                $result['message'] = "Something went wrong";
            }
        }
        else
        {
            $result['status'] = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }

        return response()->json($result);

    }


    public function get_products(Request $request)
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

    public function next_month_order_canceled(Request $request)
    {

        $user_id = $request->user_id;

        $orderData = DB::table('orders')
            ->whereNotNull('form_id')
            ->where('user_id', $user_id)
            // ->where('is_check_in','!=',"")
            ->orderBy('created_at', 'desc')

            ->first();
            // dd($orderData);
        if($orderData)
        {
            $orderData_2 = DB::table('orders')
                        ->whereNotNull('form_id')
                        ->where('user_id', $user_id)
                        // ->where('is_check_in','!=',"")
                        ->orderBy('created_at', 'desc')
                        ->first();
                    //   dd($orderData_2);  
            $today = now(); // Use Laravel's now() helper for current date and time
            // if ($orderData_2->next_billing_date <= $today && $orderData_2->is_check_in == 0) 
            // {
                DB::table('orders')
                    ->where('orders_id', $orderData_2->orders_id)
                    ->update([
                        'next_billing_date' => Carbon::now()->addMonth(),
                        'updated_at'        => now(),

                ]);
                $result['status']           = 1;
                $result['message']          = "Success!";
            // }
            // else
            // {
            //     $result['status']       = 0;
            //     $result['message']         = "Time not valid. Please try again.";      
              
            // }

                // dd($orderData);
               
        }
        else
        {
            $result['status']  = 0;
            $result['message'] = "Somting With Wrong";
        }

        return response()->json($result);


    }


   


    public function update_payment_token(Request $request)
    {
        $user_id       = $request->user_id;
        $payment_token = $request->payment_token;
    
        if (empty($user_id) || empty($payment_token)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please fill out all the required details in the form before submitting it.'
            ], 200);
        }
    
        $is_user = DB::table('user')->where('user_id', $user_id)->first();
        if (!$is_user) {
            return response()->json([
                'status' => 0,
                'message' => 'User not found.'
            ], 200);
        }
    
        $cart_data = DB::table('user_cart_product')->where('user_id', $user_id)->first();
        if (!$cart_data) {
            return response()->json([
                'status' => 0,
                'message' => 'No cart data found for the user.'
            ], 200);
        }
    
        $product_data = DB::table('product')->where('product_id', $cart_data->product_id)->first();
        $shipping_data = DB::table('shipping')->where('campaign_id', $product_data->campaign_id)->first();
        $campaign_data = DB::table('campaign')->where('campaign_id', $product_data->campaign_id)->first();
        $user_data = DB::table('user')->where('user_id', $cart_data->user_id)->first();
        $user_token = DB::table('user')->select('payment_token')->where('user_id', $cart_data->user_id)->first();
        $card_data = DB::table('user_payment_card_details')->where('user_card_id', $cart_data->user_card_id)->first();
        $address_data = DB::table('user_address')->where('address_id', $cart_data->address_id)->first();
    
        if (!$product_data || !$shipping_data || !$user_data || !$address_data) {
            return response()->json([
                'status' => 0,
                'message' => 'Incomplete data, unable to process the request.'
            ], 200);
        }
    
        $pro_id = $product_data->sticky_product_id;
        $patientData = [
            "firstName" => $user_data->first_name,
            "lastName" => $user_data->last_name,
            "currency" => "JPY",
            "billingFirstName" => $user_data->first_name,
            "billingLastName" => $user_data->last_name,
            "billingAddress1" => $address_data->address,
            "billingCity" => $address_data->city_name,
            "billingState" => $address_data->state_name,
            "billingZip" => $address_data->zip_code,
            "billingCountry" => $address_data->country,
            'phone' => $user_data->phone_number,
            'email' => $user_data->email,
            'payment_token' => $payment_token,
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
    
        $exists = DB::table('orders')
            ->where('sticky_product_id', $product_data->sticky_product_id)
            ->where('user_id', $user_id)
            ->exists();
    
        if ($exists) {
            return response()->json([
                'status' => 0,
                'message' => 'Order already exists for this user.'
            ], 200);
        }
    
        $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();
        $site_key = $site_data->site_key;
        $site_secret = $site_data->site_secret;
    
        $response = Http::withBasicAuth($site_key, $site_secret)
        ->timeout(10) // Set a timeout to prevent long waits
        ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
        $responseData = $response->json();
    // dd($responseData);
        if (!$response->successful() || ($responseData['response_code'] == 10920 && $responseData['error_found'] == "1")) {
            return response()->json([
                'status' => 4,
                'message' => 'Error with payment token or order processing.',
                'error_details' => $responseData
            ], 400);
        }
        if ($response->successful()) {
        DB::table('user')->where('user_id', $user_id)->update([
            'payment_token' => $payment_token,
            'created_at' => now(),
        ]);
        
        DB::table('user_cart_product')
            ->where('user_id', $user_id)
            ->where('product_id', $product_data->product_id)
            ->update(['status' => 2]);
    
            $order = new Order();
            $order->gateway_id = $responseData['gateway_id'] ?? null;
            $order->sticky_product_id = $product_data->sticky_product_id;
            $order->user_id = $user_id;
            $order->payment_token = $payment_token;
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

            foreach ($responseData['line_items'] ?? [] as $item) {
                $lineItem = new OrderLineItem();
                $lineItem->order_id = $order->id;
                $lineItem->product_id = $item['product_id'] ?? null;
                $lineItem->variant_id = $item['variant_id'] ?? null;
                $lineItem->quantity = $item['quantity'] ?? 1;
                $lineItem->subscription_id = $item['subscription_id'] ?? null;
                $lineItem->save();
            }

            foreach ($responseData['subscription_id'] ?? [] as $productId => $subscriptionId) {
                $subscription = new Subscription();
                $subscription->order_id = $order->id;
                $subscription->product_id = $productId;
                $subscription->subscription_id = $subscriptionId;
                $subscription->save();
            }
            return response()->json([
                'status' => 1,
                'message' => 'Success',
            ]);
        }
        else
        {
            return response()->json([
                'status' => 4,
                'message' => 'Error with payment token or order processing.',
                'error_details' => $responseData
            ], 400);
        }
    
       
    }

    public function update_order_payment_token(Request $request)
    {
        $order_id          = $request->order_id;
        $creditCardType    = $request->creditCardType;
        $creditCardNumber  = $request->creditCardNumber;
        $ex_month          = $request->ex_month;
        $ex_year           = $request->ex_year;
        $CVV               = $request->CVV;
        
        if (!empty($creditCardNumber) && !empty($order_id) && !empty($ex_month) && !empty($ex_year) && !empty($CVV)) {
            // Check if the order exists in the database
            // $order_data = DB::table('orders')->where('order_id', $order_id)->first();

            // if ($order_data) {
                // API credentials
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
                
                // Prepare the data for the API request
                $payload = [
                    "order_id" => [
                        $order_id => [
                            "cc_number" => $creditCardNumber,
                            "cc_payment_type" => "American express",
                            "cc_expiration_date" => $ex_month . $ex_year,
                            // "payment_token" => $payment_token,
                            // "creditCardType"   => "Discover",
                            // "creditCardNumber" => $creditCardNumber,
                            // "expirationDate"   => $ex_month . $ex_year,
                            // "CVV"              => $CVV,
                            // 'creditCardType' => "Discover",
                            // 'creditCardNumber' => $card_data->card_no,
                            // 'expirationDate' => $card_data->ex_month . $card_data->ex_year,
                            // 'CVV' => $card_data->cvv_no,
                        ]
                    ]
                ];

                // Make the API request
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);

                if ($response->successful()) {

                    // DB::table('orders')->where('order_id', $order_id)->update([
                    //     "payment_token" => $payment_token,
                    // ]);
                    return response()->json([
                        'status' => 1,
                        'message' => "Order payment Details updated successfully.",
                        // 'order_data' => $order_data
                    ]);
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => "Failed to update order payment token.",
                        'error' => $response->json()
                    ]);
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Order record not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    public function update_order_contact_and_address(Request $request)
    {
        $order_id     = $request->order_id;
        // $first_name   = $request->first_name;
        // $last_name    = $request->last_name;
        $address      = $request->address;
        $city_name    = $request->city_name;
        $zip_code     = $request->zip_code;
        $state_name   = $request->state_name;
        $country      = $request->country;
        $phone_number = $request->phone_number;
        $email        = $request->email;
        $user_id      = $request->user_id;
    
        // Check if all required fields are provided
        if (!empty($order_id)  && !empty($address) && !empty($city_name) && !empty($zip_code) && !empty($state_name) && !empty($country)) {
            
            // Check if the order exists
            // $order_data = DB::table('orders')->where('order_id', $order_id)->first();
            // if ($order_data) {
                // API credentials
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
    
                // Prepare the data for the API request
                $payload = [
                    "order_id" => [
                        $order_id => [
                            // "first_name" => $first_name,
                            // "last_name" => $last_name,
                            "shipping_address1" => $address,
                            "shipping_city" => $city_name,
                            "shipping_state" => $state_name,
                            "shipping_zip" => $zip_code,
                            "shipping_country" => $country,
                            // "phone" => $phone_number,
                            // "email" => $email
                        ]
                    ]
                ];
    
                // Make the API request
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);
    
                // Check if the response is successful
                if ($response->successful()) {
                   
                    return response()->json([
                
                        'status' => 1,
                        'message' => "Order contact and address details updated successfully.",
                        // 'order_data' => $order_data
                    ]);
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => "Failed to update order contact and address details.",
                        'error' => $response->json()
                    ]);
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Order record not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    public function update_user_skip_month(Request $request)
    {
        $order_id           = $request->order_id;
        $next_billing_date = Carbon::now()->addMonths(2)->format('m/d/Y');
        // dd("wef");
        
             // Check if all required fields are provided
             if (!empty($order_id)) 
             {
            
                // Check if the order exists
                $order_data = DB::table('orders')->where('order_id', $order_id)->first();
                if ($order_data) {
                    // API credentials
                    $app_key_data = DB::table('app_keys')->first();
                    
                    $app_key    = $app_key_data->app_key;
                    $app_secret = $app_key_data->app_secret;
        

                    $product = DB::table('product')->where('product_id', '=',$order_data->sticky_product_id)->first();
                    // dd($product);
                    // Prepare the data for the API request
                    $payload = [
                        "order_id" => [
                            $order_id => [
                                "next_rebill_product" => $order_data->sticky_product_id,
                                "recurring_date"      =>"$next_billing_date",
                                "allow_product_swap"  => true
                               
                            ]
                        ]
                    ];
        
                    // Make the API request
                    $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);
        
                    // Check if the response is successful
                    if ($response->successful()) {
                             // $user_data = 
                             DB::table('orders')->where('order_id', $order_id)->update([
                                'sticky_next_billing_date'    => $next_billing_date,
                                'next_billing_product' => $order_data->sticky_product_id,
                                'next_billing_status'  => 1,
                            ]);

                        return response()->json([
                    
                            'status' => 1,
                            'message' => "Order contact and address details updated successfully.",
                            'order_data' => $order_data
                        ]);
                    } else {
                        return response()->json([
                            'status' => 0,
                            'message' => "Failed to update order contact and address details.",
                            'error' => $response->json()
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => "Order record not found!"
                    ]);
                }
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => "Please fill out all the required details in the form before submitting it."
                ]);
            }
    }

    public function user_profile_data(Request $request)
    {   
        $user_id = $request->user_id;

        if ($user_id != "") {
            $user_data = DB::table('user')->where('user_id', $user_id)->first();

            if ($user_data) {
                $gender = ($user_data->gender == 2) ? "Female" : "Male";

                $user_address = DB::table('user_address')->where('user_id', $user_id)->get();
                $user_address_data = [];

                if ($user_address->isNotEmpty()) {
                    foreach ($user_address as $iv_add) {
                        $user_address_data[] = [
                            'address'    => $iv_add->address,
                            'zip_code'   => $iv_add->zip_code,
                            'city_name'  => $iv_add->city_name,
                            'state_name' => $iv_add->state_name,
                            'country'    => $iv_add->country,
                        ];
                    }
                }

                // Fetch member data
                $member_data = $this->Customerfind($user_data->email);

                // Merge user data with member data
                $user_details[] = [
                    'first_name'        => $member_data['data']['first_name'] ?? $user_data->first_name,
                    'last_name'         => $member_data['data']['last_name'] ?? $user_data->last_name,
                    'phone_number'      => $member_data['data']['phone_number'] ?? $user_data->phone_number,
                    'email'             => $member_data['data']['email'] ?? $user_data->email,
                    'gender'            => $gender,
                    'user_address_data' => $user_address_data,
                ];

                return response()->json([
                    'user_details' => $user_details,
                    'status'       => 1,
                    'message'      => "Great! Successfully retrieved user record."
                ]);
            } else {
                return response()->json([
                    'status'  => 0,
                    'message' => "User record not found!"
                ]);
            }
        } else {
            return response()->json([
                'status'  => 0,
                'message' => "Please provide all required details."
            ]);
        }
    }

    private function Customerfind($email)
    {
        if (empty($email)) {
            return [
                'status'  => 0,
                'message' => 'Email is required.',
                'data'    => []
            ];
        }

        $app_key_data = DB::table('app_keys')->first();
        $site_data    = DB::table('site')->get();  // <-- ALL SITE DATA

        if (!$app_key_data || $site_data->isEmpty()) {
            return [
                'status'  => 0,
                'message' => 'API keys or site data not found.',
                'data'    => []
            ];
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        $finalCustomer = null;

        foreach ($site_data as $site) {

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date"  => "11/11/2023",
                "end_date"    => "11/11/2027",
                "criteria"    => [
                    "email" => $email
                ],
                "return_type" => "customer_view"
            ];

            // API Request
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/customer_find', $payload);

            $data = $response->json();

            // If valid response found then break loop
            if ($response->successful() &&
                isset($data['response_code']) &&
                $data['response_code'] == "100" &&
                !empty($data['data']))
            {
                $customer_id = array_key_first($data['data']);
                $customer    = $data['data'][$customer_id];

                $finalCustomer = [
                    'status'  => 1,
                    'message' => 'Successfully retrieved customer data.',
                    'data'    => [
                        'first_name'   => $customer['first_name'] ?? null,
                        'last_name'    => $customer['last_name'] ?? null,
                        'phone_number' => $customer['phone'] ?? null,
                        'email'        => $customer['email'] ?? null,
                        'date_created' => $customer['date_created'] ?? null,
                        'contact_id'   => $customer['contact_id'] ?? null,
                        'order_count'  => $customer['order_count'] ?? 0,
                        'order_list'   => explode(',', $customer['order_list'] ?? ''),
                    ]
                ];

                break; // STOP loop because data mil gaya
            }
        }

        if ($finalCustomer) {
            return $finalCustomer;
        }

        return [
            'status'  => 0,
            'message' => 'Failed to retrieve customer data.',
            'data'    => []
        ];
    }

    public function processOrder(Request $request)
    {
        $order_id   = $request->order_id;
        $product_id = $request->product_id;

        if (empty($order_id) || empty($product_id)) {
            return response()->json([
                'status'  => 0,
                'message' => 'order_id and product_id are required.'
            ]);
        }

        $product_data = DB::table('product')->where('product_id', $product_id)->first();
        if (!$product_data) {
            return response()->json([
                'status'  => 0,
                'message' => 'Product not found.'
            ]);
        }
        $sticky_product_id = $product_data->sticky_product_id;
        $bm = 3;
        // Check if product_description has 'BM:' and extract number after it
        if (preg_match('/BM:(\d+)/', $product_data->product_description, $matches)) {
            $bm = (int) $matches[1];
        }
        // dd($sticky_product_id);

        // Fetch keys
        $app_key_data = DB::table('app_keys')->first();

        // if (!$app_key_data || !$site_data) {
        //     return response()->json([
        //         'status' => 0,
        //         'message' => 'API keys or site data not found.'
        //     ]);
        // }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        // 🔸 STEP 1 - order_update
        $orderUpdatePayload = [
            "order_id" => [
                $order_id => [
                    // "recurring_date"      => date("m/d/Y", strtotime("+30 days")),
                    "next_rebill_product" => $sticky_product_id,
                    'billing_model_id' => $bm,
                    "allow_product_swap"  => true
                ]
            ]
        ];

        // $orderUpdateURL = "https://{$app_key}.{$site_data->domain}{$site_data->api_ext}order_update";

        $orderUpdateResponse = Http::withBasicAuth($app_key, $app_secret)
            ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $orderUpdatePayload);

        $orderUpdateData = $orderUpdateResponse->json();


        // If step 1 fail → stop
        if (!$orderUpdateResponse->successful()) {
            return response()->json([
                'status'  => 0,
                'message' => 'order_update failed.',
                'step1'   => $orderUpdateData
            ]);
        }


        // 🔸 STEP 2 - order_force_bill
        $forceBillPayload = [
            "order_id"   => $order_id,
            "product_id" => (int)$sticky_product_id
        ];


        $forceBillResponse = Http::withBasicAuth($app_key, $app_secret)
            ->post('https://whitelabelmd.sticky.io/api/v1/order_force_bill', $forceBillPayload);



        $forceBillData = $forceBillResponse->json();


        if (!$forceBillResponse->successful()) {
            return response()->json([
                'status'  => 0,
                'message' => 'order_force_bill failed.',
                'step2'   => $forceBillData
            ]);
        }


        // FINAL RESPONSE
        return response()->json([
            'status'  => 1,
            'message' => "Order updated & force bill successful.",
            'step1'   => $orderUpdateData,
            'step2'   => $forceBillData
        ]);
    }

    private function Customerfind22($email)
    {
        if (empty($email)) {
            return [
                'status'  => 0,
                'message' => 'Email is required.',
                'data'    => []
            ];
        }

        $app_key_data = DB::table('app_keys')->first();
        $site_data    = DB::table('site')->first();

        if (!$app_key_data || !$site_data) {
            return [
                'status'  => 0,
                'message' => 'API keys or site data not found.',
                'data'    => []
            ];
        }

        $app_key     = $app_key_data->app_key;
        $app_secret  = $app_key_data->app_secret;
        $campaign_id = $site_data->campaign_id;

        $payload = [
            "campaign_id" => $campaign_id,
            "start_date"  => "11/11/2023",
            "end_date"    => "11/11/2027",
            "criteria"    => [
                "email" => $email
            ],
            "return_type" => "customer_view"
        ];

        // Make API request
        $response = Http::withBasicAuth($app_key, $app_secret)
            ->post('https://whitelabelmd.sticky.io/api/v1/customer_find', $payload);

        $data = $response->json();

        if ($response->successful() && isset($data['response_code']) && $data['response_code'] == "100") {
            if (!empty($data['data'])) {
                $customer_id = array_key_first($data['data']); // Get first customer ID
                $customer    = $data['data'][$customer_id]; // Extract customer data

                return [
                    'status'  => 1,
                    'message' => 'Successfully retrieved customer data.',
                    'data'    => [
                        'first_name'   => $customer['first_name'] ?? null,
                        'last_name'    => $customer['last_name'] ?? null,
                        'phone_number' => $customer['phone'] ?? null,
                        'email'        => $customer['email'] ?? null,
                        'date_created' => $customer['date_created'] ?? null,
                        'contact_id'   => $customer['contact_id'] ?? null,
                        'order_count'  => $customer['order_count'] ?? 0,
                        'order_list'   => explode(',', $customer['order_list'] ?? ''),
                    ],
                ];
            }
        }

        return [
            'status'  => 0,
            'message' => 'Failed to retrieve customer data.',
            'data'    => []
        ];
    }

    public function forgot_password2(Request $request)
    {
        $email = $request->email;

        if($email !="")
        {
            $user_data = DB::table('user')->where('email',$email)->first();

            if($user_data)
            {
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
            
                $payload = [
                    "email" => $email,
                    // "event_id" => 1034,
                ];
            
                $response = Http::withBasicAuth($app_key, $app_secret)
                                ->post('https://whitelabelmd.sticky.io/api/v1/member_forgot_password', $payload);
                $responseData = $response->json();
                if ($response->successful()) 
                {
                    return response()->json([
                        'status' => 1,
                        'user_id' => $user_data->user_id,
                        'message' => 'Password get successfully.',
                        'data' => $responseData
                    ]);
                }
                else
                {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Failed to create member.',
                        'data' => $responseData
                    ]);
                }
                    
            }
            else
            {
                return response()->json([
                    'status' => 0,
                    'message' => "User record not found!"
                ]);
            }
        }
        else
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }


    public function forgot_password(Request $request)
    {
        $email = $request->email;

        if ($email != "") {
            $event_data = DB::table('send_mail_events')->where('email_type','=','forgot_password')->first();

                $app_key_data = DB::table('app_keys')->first();

                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;

                $payload = [
                    "email" => $email,
                    'event_id' => $event_data->event_id,
                    // "event_id" => 1006,
                ];

                $response = Http::withBasicAuth($app_key, $app_secret)
                                ->post('https://whitelabelmd.sticky.io/api/v1/member_forgot_password', $payload);
                $responseData = $response->json();

                // dd($responseData);
                if ($response->successful()) {

                    if($responseData['response_code'] == 100)
                    {
                        // $temp_password = $responseData['data']['temp_password'];

                        return response()->json([
                            'status' => 1,
                            'message' => "We've sent a link to reset your password to the email associated with your account. Please check your inbox.",
    //                        'data' => $responseData
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => $responseData['response_message'],
                        ]);
                    }
                  
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Failed to send password reset request.',
                        'data' => $responseData
                    ]);
                }


        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }


    
    // public function forgot_password_2(Request $request)
    // {
    //     $email = $request->email;

    //     if($email !="")
    //     {
    //         $user_data = DB::table('user')->where('email',$email)->first();

    //         if($user_data)
    //         {
    //             $user_otp = random_int(1000, 9999);
    //             $token = Str::random(30);
    //             $ctime = date("Y-m-d H:i:s");

    //             DB::table('user')
    //                 ->where('email',$email)
    //                 ->update([
    //                         'user_otp'          => $user_otp,
    //                         'email_token'       => $token,
    //                         'email_token_date'  => date("Y-m-d H:i:s",strtotime($ctime.'+10 minutes'))
                                   
    //                 ]);

    //                 $emailData = [
    //                     'frommail' => 'bgwhitelabel@gmail.com',
    //                     'tomail' => $email,
    //                     'subject' => 'Contact Information'
    //                 ];
    //                 $mail_heading = "Forgot Password Otp";

    //                 Mail::send('Mail.ForgotPasswordOtp',['user_otp' => $user_otp,'heading'=>$mail_heading], function ($message) use ($emailData)
    //                 {
    //                     $message->to($emailData['tomail']);
    //                     $message->from($emailData['frommail']);
    //                     $message->subject($emailData['subject']);
                        
    //                 });

    //                 return response()->json([
    //                     'user_id' => $user_data->user_id,
    //                     'status' => 1,
    //                     'message' => "Password reset instructions sent to your email. Please check your inbox (including spam folder) and follow the steps provided."
    //                 ]);
                    
    //         }
    //         else
    //         {
    //             return response()->json([
    //                 'status' => 0,
    //                 'message' => "User record not found!"
    //             ]);
    //         }
    //     }
    //     else
    //     {
    //         return response()->json([
    //             'status' => 0,
    //             'message' => "Please fill out all the required details in the form before submitting it."
    //         ]);
    //     }
    // }

    public function verify_email_otp(Request $request)
    {
       $user_id   = $request->user_id;
       $otp       = $request->otp;

       if($user_id !="" && $otp !="")
       {
            $user_data = DB::table('user')->where('user_id',$user_id)->first();

            if($user_data)
            {
                if($otp == $user_data->	user_otp)
                {
                    $current_date = date("Y-m-d H:i:s");
                    $date = DB::table('user')->where('user_id',$user_id)->get()->first()->email_token_date;
                    $email_token_date = date('Y-m-d H:i:s',strtotime($date."+10 minute"));
// dd($email_token_date);
                    if($current_date < $email_token_date)
                    {
                        return response()->json([
                            'status'  => 1,
                            'message' => "Otp match successfully."
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status'  => 0,
                            'message' => "Otp expired."
                        ]);
                    }
                }
                else
                {
                    return response()->json([
                        'status'  => 0,
                        'message' => "Please enter a valid otp."
                    ]);
                }
            }
            else
            {
                return response()->json([
                    'status'  => 0,
                    'message' => "User record not found!"
                ]);
            }
       }
       else
       {
            return response()->json([
                'status'  => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
       }
    }

    public function change_password(Request $request)
    {
        $user_id       = $request->user_id;
        $old_password  = $request->old_password;
        $new_password  = $request->password;
        $email  = $request->email;
        if ($user_id != "" && $new_password != "" && $old_password != "") {
            // Retrieve the user's current data
           // $user_data = DB::table('user')->where('user_id', $user_id)->first();
    
            // Check if user exists and the old password is correct
            //if ($user_data && Hash::check($old_password, $user_data->password)) {
                // Update the password locally
             
    
                // Prepare data for the external API request
    
                $payload = [
                    "email"                   => $email,
                    "current_member_password" => $old_password,
                    "new_member_password"     => $new_password,
                ];
    
                // Call the external API to update the member password
               

                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
            
              
                $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/member_update', $payload);
                $responseData = $response->json();
                if ($response->successful() && $response->json('response_code') == 100) 
                {

                    // DB::table('user')->where('user_id', $user_id)->update([
                    //     'password'   => Hash::make($new_password),
                    //     'updated_at' => now(),
                    // ]);
                    return response()->json([
                        'status' => 1,
                        'message' => "Your password has been successfully changed and updated on the external service."
                    ]);
                    
                  
                } 
                else 
                {
                    if (isset($responseData['response_code']) && $responseData['response_code'] === "4009") 
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "The new password cannot be the same as the old password. Please choose a different password.",
                            'error' => $response->json()
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "Password updated locally, but failed to update on the external service.",
                            'error' => $response->json()
                        ]);
                    }
                   
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Incorrect current password or user not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }
    
    public function change_password_old(Request $request)
    {
        $user_id       = $request->user_id;
        $old_password  = $request->old_password;
        $new_password  = $request->password;
    
        if ($user_id != "" && $new_password != "" && $old_password != "") {
            // Retrieve the user's current data
            $user_data = DB::table('user')->where('user_id', $user_id)->first();
    
            // Check if user exists and the old password is correct
            if ($user_data && Hash::check($old_password, $user_data->password)) {
                // Update the password locally
             
    
                // Prepare data for the external API request
    
                $payload = [
                    "email"                   => $user_data->email,
                    "current_member_password" => $old_password,
                    "new_member_password"     => $new_password,
                ];
    
                // Call the external API to update the member password
               

                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
            
              
                $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/member_update', $payload);
                $responseData = $response->json();
                if ($response->successful() && $response->json('response_code') == 100) 
                {

                    DB::table('user')->where('user_id', $user_id)->update([
                        'password'   => Hash::make($new_password),
                        'updated_at' => now(),
                    ]);
                    return response()->json([
                        'status' => 1,
                        'message' => "Your password has been successfully changed and updated on the external service."
                    ]);
                    
                  
                } 
                else 
                {
                    if (isset($responseData['response_code']) && $responseData['response_code'] === "4009") 
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "The new password cannot be the same as the old password. Please choose a different password.",
                            'error' => $response->json()
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "Password updated locally, but failed to update on the external service.",
                            'error' => $response->json()
                        ]);
                    }
                   
                }
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => "Incorrect current password or user not found!"
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    public function reset_password(Request $request)
    {
        $user_id       = $request->user_id;
        $old_password  = $request->old_password;
        $new_password  = $request->password;
    
        if ($user_id != "" && $new_password != "" && $old_password != "") {
            // Retrieve the user's current data
            $user_data = DB::table('user')->where('user_id', $user_id)->first();
    
            // Check if user exists and the old password is correct
            // if ($user_data && Hash::check($old_password, $user_data->password)) {
                // Update the password locally
             
    
                // Prepare data for the external API request
    
                $payload = [
                    "email"                   => $user_data->email,
                    "member_temp_password"    => $old_password,
                    "member_new_password"     => $new_password,
                ];
    
                // Call the external API to update the member password
               

                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
            
              
                $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/member_reset_password', $payload);
                $responseData = $response->json();
                if ($response->successful() && $response->json('response_code') == 100) 
                {

                    DB::table('user')->where('user_id', $user_id)->update([
                        'password'   => Hash::make($new_password),
                        'updated_at' => now(),
                    ]);
                    return response()->json([
                        'status' => 1,
                        'message' => "Your password has been successfully changed and updated on the external service."
                    ]);
                    
                  
                } 
                else 
                {
                    if (isset($responseData['response_code']) && $responseData['response_code'] === "4009") 
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "The new password cannot be the same as the old password. Please choose a different password.",
                            'error' => $response->json()
                        ]);
                    }
                    else
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "Password updated locally, but failed to update on the external service.",
                            'error' => $response->json()
                        ]);
                    }
                   
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Incorrect current password or user not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    private function order_view($order_id)
    {
        // $order_id = $request->order_id;
    
        if (!empty($order_id)) 
        {
            // $order_data = DB::table('orders')->where('order_id', $order_id)->first();
    
            // if ($order_data) 
            // {
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
    
                $payload = [
                    "order_id" => [$order_id],  // Pass order_id as an array
                    "return_variants" => 1      // Include return_variants as required
                ];
    
                $response = Http::withBasicAuth($app_key, $app_secret)
                                ->post('https://whitelabelmd.sticky.io/api/v1/order_view', $payload);
    
                $responseData = $response->json();
    
                if ($response->successful()) 
                {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Order data retrieved successfully.',
                        'data' => $responseData
                    ]);
                } 
                else 
                {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Failed to retrieve order data from the external service.',
                        'error' => $responseData
                    ]);
                }
            // } 
            // else 
            // {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Order record not found!"
            //     ]);
            // }
        } 
        else 
        {
            return response()->json([
                'status' => 0,
                'message' => "Please provide a valid order ID."
            ]);
        }
    }

    public function order_view_2(Request $request)
    {
        $order_id = $request->order_id;
    
        if (!empty($order_id)) 
        {
            $order_data = DB::table('orders')->where('order_id', $order_id)->first();
    
            if ($order_data) 
            {
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
    
                $payload = [
                    "order_id" => [$order_id],  // Pass order_id as an array
                    "return_variants" => 1      // Include return_variants as required
                ];
    
                $response = Http::withBasicAuth($app_key, $app_secret)
                                ->post('https://whitelabelmd.sticky.io/api/v1/order_view', $payload);
    
                $responseData = $response->json();
    
                if ($response->successful()) 
                {
                    return response()->json([
                        'status' => 1,
                        'message' => 'Order data retrieved successfully.',
                        'data' => $responseData
                    ]);
                } 
                else 
                {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Failed to retrieve order data from the external service.',
                        'error' => $responseData
                    ]);
                }
            } 
            else 
            {
                return response()->json([
                    'status' => 0,
                    'message' => "Order record not found!"
                ]);
            }
        } 
        else 
        {
            return response()->json([
                'status' => 0,
                'message' => "Please provide a valid order ID."
            ]);
        }
    }

    public function billing_update_order_contact_and_address(Request $request)
    {
        $order_id           = $request->order_id;
        $billing_address    = $request->billing_address;
        $billing_city_name  = $request->billing_city_name;
        $billing_state_name = $request->billing_state_name;
        $billing_country    = $request->billing_country;
        $billing_zip_code   = $request->billing_zip_code;
        $user_id            = $request->user_id;
    
        // Check if all required fields are provided
        if (!empty($order_id) && !empty($billing_address) && !empty($billing_city_name) && !empty($billing_state_name) && !empty($billing_country) && !empty($billing_zip_code)) {
            
            // Check if the order exists
            // $order_data = DB::table('orders')->where('order_id', $order_id)->first();
            // if ($order_data) {
                // API credentials
                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
    
                // Prepare the data for the API request
                $payload = [
                    "order_id" => [
                        $order_id => [
                            "billing_address1"  => $billing_address,
                            "billing_city"      => $billing_city_name,
                            "billing_state"     => $billing_state_name,
                            "billing_country"   => $billing_country,
                            "billing_zip"       => $billing_zip_code,
                        ]
                    ]
                ];
    
                // Make the API request
                $response = Http::withBasicAuth($app_key, $app_secret)
                    ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);
    
                // Check if the response is successful
                if ($response->successful()) {
                
                    return response()->json([
                
                        'status' => 1,
                        'message' => "Order billing address updated successfully.",
                        // 'order_data' => $order_data
                    ]);
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => "Failed to update billing address details.",
                        'error' => $response->json()
                    ]);
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Order record not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    public function get_billing_address(Request $request)
    {
        $order_id = $request->order_id;
        
        if($order_id !="")
        {
            // $order_data = DB::table('orders')->where('order_id',$order_id)->first();
            // $user_data = DB::table('user')->where('user_id',$order_data->user_id)->first();


            $orderResponse = $this->order_view($order_id);
            $orderData = json_decode($orderResponse->getContent(), true);
            // dd($orderData);
              // Check if the order was successfully retrieved
            if ($orderData['status'] == 1) 
            {
                $billing_city            = $orderData['data']['billing_city'] ?? null;
                $billing_country         = $orderData['data']['billing_country'] ?? null;
                $billing_postcode        = $orderData['data']['billing_postcode'] ?? null;
                $billing_state           = $orderData['data']['billing_state'] ?? null;
                $billing_street_address  = $orderData['data']['billing_street_address'] ?? null;
                $shipping_city           = $orderData['data']['shipping_city'] ?? null;
                $shipping_country        = $orderData['data']['shipping_country'] ?? null;
                $shipping_postcode       = $orderData['data']['shipping_postcode'] ?? null;
                $shipping_state          = $orderData['data']['shipping_state'] ?? null;
                $shipping_street_address = $orderData['data']['shipping_street_address'] ?? null;
                $email_address           = $orderData['data']['email_address'] ?? null;
                $customers_telephone     = $orderData['data']['customers_telephone'] ?? null;
               
            } 
            else 
            {
               $billing_city ="";
               $billing_country ="";
               $billing_postcode ="";
               $billing_state ="";
               $billing_street_address ="";
               $shipping_city ="";
               $shipping_country ="";
               $shipping_postcode ="";
               $shipping_state ="";
               $shipping_street_address ="";
               $email_address ="";
               $customers_telephone ="";
            }

            return response()->json([
                'status'             => 1,
                'message'            => 'Order billing data retrieved successfully.',
                'billing_address'    => $billing_street_address,
                'billing_city_name'  => $billing_city,
                'billing_state_name' => $billing_state,
                'billing_postcode'   => $billing_postcode,
                'billing_country'    => $billing_country,
                'shipping_city'      => $shipping_city,
                'shipping_country'   => $shipping_country,
                'shipping_postcode'  => $shipping_postcode,
                'shipping_state'     => $shipping_state,
                'shipping_address'   => $shipping_street_address,
                'phone'              =>$email_address,
                'email'              => $customers_telephone,
            ]);
        } 
        else 
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
    }

    public function magic_link(Request $request)
    {
        $email = $request->email;

        if (empty($email)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please provide a valid email address.',
            ]);
        }

        // Check local DB first
        $user = DB::table('user')->where('email', $email)->first();

        if (!$user) {
            // Try to get user data from external API
            $customerData = $this->Customerfind($email);

            if ($customerData['status'] === 1 && !empty($customerData['data'])) {
                $data = $customerData['data'];

                // Insert new user in DB
                $userId = DB::table('user')->insertGetId([
                    'first_name' => $data['first_name'] ?? '',
                    'last_name'  => $data['last_name'] ?? '',
                    'email'      => $data['email'] ?? $email,
                    'phone'      => $data['phone'] ?? '',
                    // 'contact_id' => $data['contact_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Fetch newly inserted user
                $user = DB::table('user')->where('user_id', $userId)->first();
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Email not found.',
                ]);
            }
        }

        // If user exists (local or API inserted)
        $password = $this->generateRandomPassword();
        $encryptedPassword = Crypt::encryptString($password);

        $this->sendmagicEmail($email);

        DB::table('user')->where('email', $email)->update([
            'encrypted_password' => $encryptedPassword,
            'is_magic' => 1,
        ]);

        return response()->json([
            // 'password' => $password, // Debug only
            'status' => 1,
            'message' => 'Magic link sent successfully!',
        ]);
    }


    private function sendmagicEmail($email)
    {
         $app_key_data = DB::table('app_keys')->first();

          $app_key    = $app_key_data->app_key;
          $app_secret = $app_key_data->app_secret;

          $event_data = DB::table('send_mail_events')->where('email_type','=','magic_link')->first();



          $payload = [
            //   "customer_id" => $customer_id,
              "email" => $email,
              "event_id" => $event_data->event_id,
            //   "event_id" => $event_data->event_id,
          ];

          $response = Http::withBasicAuth($app_key, $app_secret)
                          ->post('https://whitelabelmd.sticky.io/api/v1/member_forgot_password', $payload);

          if ($response->successful()) {
              $responseData = $response->json();


                  return response()->json([
                      'status' => 1,
                      'message' => 'Member created successfully.',
                      'data' => $responseData
                  ]);

          } else {
              return response()->json([
                  'status' => 0,
                  'message' => 'Failed to. Server error.',
                  'error' => $response->json()
              ], $response->status());
          }
    }

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

    private function sendmagicEmail3883($email, $firstName)
    {
        $emailData = [
           'frommail' => 'bgwhitelabel@gmail.com',
            'tomail' => $email,
            'subject' => 'Login Magic Link',
            'ccmail' => 'ravi@whitelabelmd.com', // Add CC email here
        ];
    
        
        $mailData = [
            'email_sub' => $emailData['subject'],
            'first_name' => $firstName,
            'url' => "https://member.priderx.com/login?email=" . $email, // Directly append the email
            'email' => $email
        ];
    
        try {
            // Mail::send('Mail.magiclink', $mailData, function ($message) use ($emailData) {
            //     $message->to($emailData['tomail'])
            //         ->from($emailData['frommail'], 'Your Company')
            //         ->subject($emailData['subject']);
            // });
            Mail::send('Mail.magiclink', $mailData, function ($message) use ($emailData) {
                $message->to($emailData['tomail']);
                $message->from($emailData['frommail']);
                $message->subject($emailData['subject']);
                $message->cc($emailData['ccmail']); // Add CC here
            });
            return true;
        } catch (\Exception $e) {
            \Log::error('Email Send Error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function magic_link1111(Request $request)
    {
        $email = $request->email;
    
        if (!empty($email)) {
            $user = DB::table('user')->where('email', $email)->first();
    
            if ($user) {
                $password = $this->generateRandomPassword();
                $encryptedPassword = Crypt::encryptString($password);
    
                $this->sendmagicEmail($email, $user->first_name);
    
                DB::table('user')->where('email', $email)->update([
                    'encrypted_password' => $encryptedPassword,
                    'is_magic' => 1,
                ]);
    
                return response()->json([
                    'password' => $password, // For debugging purposes only
                    'status' => 1,
                    'message' => 'Magic link sent successfully!',
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Email address not found.',
                ]);
            }
        }
    
        return response()->json([
            'status' => 0,
            'message' => 'Please provide a valid email address.',
        ]);
    }
    
    public function get_user_password(Request $request)
    {
        $email = $request-> email;

        if($email !="")
        {
            $is_tab = DB::table('user')->where('email',$email)->count();
            if($is_tab > 0)
            {
                $is_data = DB::table('user')->where('email',$email)->first();
                $decryptedPassword = Crypt::decryptString($is_data->encrypted_password);
                // dd($decryptedPassword);
                $result['password']         = $decryptedPassword;
                $result['status']           = 1;
                $result['message']          = "Success!";
            }
            else
            {
                $result['status']  = 0;
                $result['message'] = "Somting With Wrong";
            }
        
        }
        else
        {
            $result['status']       = 0;
            $result['message']      = "Please fill out all the required details in the form before submitting it.";        
        }
        return response()->json($result);
    }

    public function change_password_new(Request $request)
    {
        $email       = $request->email;
        $old_password  = $request->old_password;
        $new_password  = $request->password;
    // dd($request);
        if ($email != "" && $new_password != "" && $old_password != "") {
            // Retrieve the user's current data
            // $user_data = DB::table('user')->where('user_id', $user_id)->first();
    
            // Check if user exists and the old password is correct
            // if ($user_data && Hash::check($old_password, $user_data->password)) {
                // Update the password locally
             
    
                // Prepare data for the external API request
    
                $payload = [
                    "email"                   => $email,
                    "current_member_password" => $old_password,
                    "new_member_password"     => $new_password,
                ];
    
                // Call the external API to update the member password
               

                $app_key_data = DB::table('app_keys')->first();
    
                $app_key    = $app_key_data->app_key;
                $app_secret = $app_key_data->app_secret;
            
              
                // $response = Http::withBasicAuth($app_key, $app_secret)
                //         ->post('https://whitelabelmd.sticky.io/api/v1/member_reset_password', $payload);
                        
                $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/member_update', $payload);
                $responseData = $response->json();
                if ($response->successful() && $response->json('response_code') == 100) 
                {

                    // DB::table('user')->where('user_id', $user_id)->update([
                    //     'password'   => Hash::make($new_password),
                    //     'updated_at' => now(),
                    // ]);
                    // return response()->json([
                    //     'status' => 1,
                    //     'message' => "Your password has been successfully changed and updated on the external service."
                    // ]);
                    $user = DB::table('user')->where('email', $email)->first();

                    $accessToken = $this->create_tokens(); // Ensure this method returns a valid token
                    $token="";
                    if ($accessToken) 
                    {
                        $token = $accessToken;
                    }
                    $patientId = $this->createOrSearchPatients($email);
                    if($patientId !="")
                    {
                        $result['patientId'] = $patientId['patient_id'];
                        $result['token']     = $token;
                        $first_name    = $patientId['first_name'];
                        $last_name     = $patientId['last_name'];
                        $gender        = $patientId['gender'];
                        $date_of_birth = $patientId['date_of_birth'];
                    }
                    else
                    {
                        $result['patientId'] = "";
                        $result['token']     = "";
                        $first_name    = "";
                        $last_name     = "";
                        $gender        = "";
                        $date_of_birth = "";
                    }
                    $email_1 = $this->member_login($email,$new_password);
                    $email1         = $email_1['email'];
                    $customer_id    = $email_1['customer_id'];

                    if (!empty($customer_id)) {
                        $customer_response = $this->customer_view_2($customer_id);
                        $customer_data = $customer_response->getData();
        
                        if (isset($customer_data->data->first_name)) {
                            $first_name = $customer_data->data->first_name;
                            $phone = $customer_data->data->phone;
                            $last_name = $customer_data->data->last_name ?? $last_name;
                        }
                    }
               
                    $user_id = DB::table('user')->insertGetId([
                        'email'         => $email1,
                        'phone_number'  => $phone,
                        'first_name'    => $first_name,
                        'last_name'     => $last_name,
                        'date_of_birth' => $date_of_birth,
                        'gender'        => $gender,
                        'password'      => Hash::make($new_password),
                        'created_at'    => now(),
                    ]);
                    // $gender = $user->gender == 1 ? "Male" : "Female";
                    // if ($user->date_of_birth) {
                    //     $dateOfBirth = Carbon::parse($user->date_of_birth);
                    //     $result['month'] = "";
                    //     $result['day'] = "";
                    //     $result['year'] = "";
                    //     $result['gender'] = "";
                    //     // $result['gender'] = "";
                    // } else {
                        $result['month'] = "";
                        $result['day'] = "";
                        $result['year'] = "";
                        $result['gender'] = "";
                    // }
                    $user_tb = DB::table('user')->where('email', $email)->first();

                        // $result['patientId2'] = $patientId;
                    $result['user_id']      = $user_tb->user_id;
                    $result['existing']     = 0;
                    $result['first_name']   = $user_tb->first_name;
                    $result['last_name']    = $user_tb->last_name;
                    $result['phone_number'] = $user_tb->phone_number;
                    $result['email']        = $user_tb->email;
                    $result['status']       = 1;
                    $result['token']        = $token;
                    $result['message']      = "Great! You have successfully logged in. Welcome!";
                    
                  
                } 
                else 
                {
                    if (isset($responseData['response_code']) && $responseData['response_code'] === "4009") 
                    {
                        return response()->json([
                            'status' => 0,
                            'message' => "The new password cannot be the same as the old password. Please choose a different password.",
                            'error' => $response->json()
                        ]);
                    }
                    else
                    {
                        return response()->json([
				'message' => $responseData['response_message'],
				        'status' => 0,
                            //'message' => "Password updated locally, but failed to update on the external service.",
                            'error' => $response->json()
                        ]);
                    }
                   
                }
            // } else {
            //     return response()->json([
            //         'status' => 0,
            //         'message' => "Incorrect current password or user not found!"
            //     ]);
            // }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details in the form before submitting it."
            ]);
        }
        return response()->json($result);
    }

    private function member_login($email, $password)
    {
        if (!empty($email) && !empty($password)) {
            // Fetch app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;
    
            // Set the payload
            $payload = [
                "email" => $email,
                "member_password" => $password
            ];
    
            // Make the request with basic authentication
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/member_login', $payload);
    
            $responseData = $response->json();
    
            // Handle different response scenarios
            if ($response->successful()) {
                // Check for temporary password scenario
                if (isset($responseData['response_code']) && $responseData['response_code'] == '4010') {
                    return [
                        'status' => 0,
                        'message' => $responseData['response_message'] ?? 'Temporary password requires change',
                        'error_code' => '4010'
                    ];
                }
    
                // Check if the response contains valid data
                if (isset($responseData['data']) && isset($responseData['data']['email'])) {
                    $m_email = $responseData['data']['email'];
                    $customer_id = $responseData['data']['customer_id'];
                    return [
                        'email'     => $m_email,
                        'customer_id' => $customer_id,
                        'status' => 1
                    ];
                } 
            }
    
            // Generic failure scenario
            return [
                'status' => 0,
                'message' => 'Failed to retrieve user information',
                'error' => $responseData
            ];
        } else {
            return [
                'status' => 0,
                'message' => "Please fill out all the required details."
            ];
        }
    }





    public function sendWebhook(Request $request)
    {
        $request->validate([
            'task'           => 'required',
            'customer_name'  => 'required',
            'customer_email' => 'required|email',
            'customer_phone' => 'required'
        ]);

        $payload = [
            'task'           => 'refill request static ' . $request->task,
            'customer_name'  => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
        ];

        // 🔹 Webhook API call
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post(
            'https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/db536a9f-8d1f-44a8-90d2-4b2fbfb47d11',
            $payload
        );

        DB::table('webhook_trigger_logs')->insert([
            'request_payload' => json_encode($payload),
            'response_body'   => $response->body(),
            'response_status' => $response->status(),
            'created_at'      => now(),
        ]);

        return response()->json([
            'status'   => true,
            'message'  => 'Webhook sent successfully',
            'response' => $response->json()
        ]);
    }


    private  function customer_view_2($customer_id)
    {
        // $customer_id = $request ->customer_id;

        if (!empty($customer_id)) {
            // Fetch app keys
            $app_key_data = DB::table('app_keys')->first();
            $app_key    = $app_key_data->app_key;
            $app_secret = $app_key_data->app_secret;

            // Set the payload
            $payload = [
                "customer_id" => $customer_id,
                // "member_password" => $password
            ];

            // Make the request with basic authentication
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/customer_view', $payload);

            $responseData = $response->json();
            // dd($responseData);
            // Check if the response is successful and contains 'data'
            if ($response->successful()) 
            {
                return response()->json([
                    // 'status' => 1,
                    // 'message' => 'data retrieved successfully.',
                    'data' => $responseData
                ]);
            } 
            else 
            {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve User data from the external service.',
                    'error' => $responseData
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }


    private function createOrSearchPatients($email)
    {
      
        if ($email != '') {
            // Step 1: Get the Bearer Token
            $client_data = DB::table('client_key')->where('status',1)->first();
            $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
        
            $tokenResponse = Http::post($tokenUrl, [
                'grant_type' => $client_data->grant_type,
                'client_id' => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope' => $client_data->scope,
            ]);

            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                
                // dd($tokenData);
                // Check if 'access_token' exists
                if (isset($tokenData['access_token'])) {
                    $accessToken = $tokenData['access_token'];

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
    

   
    










}
