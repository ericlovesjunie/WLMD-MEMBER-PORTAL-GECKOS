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

	public function createOrder_offline_api(Request $request)
    {
        if (empty($request->product_id) ||empty($request->email) ||empty($request->first_name) ||empty($request->last_name) ||empty($request->phone) ||empty($request->address) ||empty($request->city_name) ||empty($request->state_name) ||empty($request->zip_code) ||empty($request->start_url) ||empty($request->payment_token))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details before submitting.",
            ], 400);
        }

        // ✅ Ensure product_id is array
        $productIds = is_array($request->product_id) ? $request->product_id : [$request->product_id];

        $createdOrders = [];
        $failedOrders = [];

        foreach ($productIds as $index => $product_id) {

            $product_data = DB::table('product')->where('uniq_id', $product_id)->first();

            if (!$product_data) {
                $failedOrders[] = [
                    'product_id' => $product_id,
                    'message' => 'Product not found'
                ];
                continue;
            }

            // 🔹 BM Extract
            $bm = 3;
            if (preg_match('/BM:(\d+)/', $product_data->product_description, $matches)) {
                $bm = (int) $matches[1];
            }

            $event_data = DB::table('send_mail_events')
                ->where('email_type', 'order_confirm')
                ->first();

            $pro_id = $product_data->sticky_product_id;

            $paymentMethod = "offline";
            $paymentToken = $request->payment_token;

            // 🔥 Price always DB se lo
            $productPrices = $request->product_price ?? [];

            $price = $product_data->product_price;

            if (is_array($productPrices) && isset($productPrices[$index])) {
                $price = $productPrices[$index];
            }
            // dd($price);
            $campaign_data = DB::table('campaign')->where('campaign_id', $product_data->campaign_id)->first();

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
                        "offer_id" => $campaign_data->offer_id,
                        "product_id" => $pro_id,
                        "billing_model_id" => $bm,
                        "quantity" => 1,
                        "price" => $price
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

            // 🔹 API Call
            $site_data = DB::table('site')->where('campaign_id', $product_data->campaign_id)->first();

            $response = Http::withBasicAuth($site_data->site_key, $site_data->site_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);

            $responseData = $response->json();

            DB::table('log_data_v2')->insert([
                'email' => $request->email,
                'request_data' => json_encode($request->all()),
                'data' => json_encode($responseData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!$response->successful() || $responseData['error_found'] == "1") {
                $failedOrders[] = [
                    'product_id' => $product_id,
                    'message' => $responseData['decline_reason'] ?? $responseData['error_message'] ?? 'Declined'
                ];
                continue;
            }

            // ✅ Payment update
            $this->update_order_payment_received($responseData['order_id'], 1);

            // ✅ Save Order
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

            // ✅ Line Items
            foreach ($responseData['line_items'] as $item) {
                $lineItem = new OrderLineItem();
                $lineItem->order_id = $order->id;
                $lineItem->product_id = $item['product_id'];
                $lineItem->variant_id = $item['variant_id'];
                $lineItem->quantity = $item['quantity'];
                $lineItem->subscription_id = $item['subscription_id'];
                $lineItem->save();
            }

            // ✅ Subscriptions
            foreach ($responseData['subscription_id'] as $productId => $subscriptionId) {
                $subscription = new Subscription();
                $subscription->order_id = $order->id;
                $subscription->product_id = $productId;
                $subscription->subscription_id = $subscriptionId;
                $subscription->save();
            }

            // ✅ Member Create
            $this->memberCreate($responseData['customerId'] ?? null, $request->email);

            $createdOrders[] = $responseData['order_id'];
        }

        return response()->json([
            'status' => 1,
            'message' => 'Orders processed',
            'success_orders' => $createdOrders,
            'failed_orders' => $failedOrders
        ]);
    }


	public function getRefillLogs(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->whereIn('event_type', ['Pay & Check-in']);

    // TODAY FILTER
    if ($request->today == 1) {
        $query->whereDate('created_at', now()->toDateString());
    }

    // SINGLE DATE FILTER
    if ($request->date) {
        $query->whereDate('created_at', $request->date);
    }

    // DATE RANGE FILTER
    if ($request->from_date && $request->to_date) {
        $query->whereBetween('created_at', [
            $request->from_date . ' 00:00:00',
            $request->to_date . ' 23:59:59'
        ]);
    }

    $totalRecords = $query->count();
    $totalPages = ceil($totalRecords / $perPage);

    $records = $query
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    foreach ($records as $row) {

        $row->check_in_completed = false;

        if ($row->event_type == 'check_in_click') {

            $exists = DB::table('webhook')
                ->where('email', $row->email)
                ->whereDate('created_at', \Carbon\Carbon::parse($row->created_at)->toDateString())
                ->exists();

            if ($exists) {
                $row->check_in_completed = true;
            }
        }
    }

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $records
    ]);
}


	 public function createOrder_test_parent_order_12month_rebill(Request $request)
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
                "billingState" => $request->billingStateCode,
                "billingZip" => $request->billingZip,
                "billingCountry" => 'US',
                'phone' => $request->phone,
                'email' => $request->email,
                "creditCardType" => $paymentMethod,
                'shippingId' => 2,
                'tranType' => "Sale",
//                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
'campaignId' => $product_data->campaign_id,
               //           'campaignId' => 226,
                // 'billingSameAsShipping' => "YES",
                'shippingAddress1' => $request->address,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_code,
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
	'price' => 0,
				"product_id" => $pro_id,
                       // "offer_id" => 477,
                        //"product_id" => 22190,
                        "billing_model_id" => 2,
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
                                ],

                                [
                                    "id" => 20,
                                    "field_name" => 'parent_order',
                                    "value" => !empty($request->parent_order) ? $request->parent_order : ""
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
   $site_secret = $site_data->site_secret;
$site_key    = $site_data->site_key;
                  $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
                  DB::table('log_data')->insert([
                    'email' => $request->email,
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
//                dd($responseData);


                  if (empty($request->apple_pay_token) && empty($request->google_pay_token)) {
                  }else{
                  $payment_received = 1;
                $this->update_order_payment_received($responseData['order_id'],$payment_received);
                  }

                DB::table('log_data_v2')->insert([
                    'email' => $request->email,
                    'request_data' => json_encode($request->all()),
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

//              dd($responseData);
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
                  $order->gateway_id = $responseData['gateway_id'];
                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $request->apple_pay_token;
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


	private function getSingleProductPrice($email, $product_id)
    {
        // 🔹 Step 1: Get product from DB
    //    $product = DB::table('product')->where('uniq_id', $product_id)->first();
$product = DB::table('product')->where('sticky_product_id', $product_id)->first();
        if (!$product) {
            return 0;
        }

        $defaultPrice = $product->product_price;
        $stickyProductIdDB = $product->sticky_product_id;

        // 🔹 Step 2: If no email → return default price
        if (empty($email)) {
            return $defaultPrice;
        }

        // 🔹 Step 3: Get API keys
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return $defaultPrice;
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        // 🔹 Step 4: Loop all sites
        $sites = DB::table('site')->get();

        foreach ($sites as $site) {

            $payload = [
                "campaign_id" => $site->campaign_id,
                "start_date"  => "01/01/2024",
                "end_date"    => date('m/d/Y'),
                "criteria"    => [
                    "email" => $email
                ],
                "return_type" => "order_view"
            ];

            $response = Http::withBasicAuth($app_key, $app_secret)
                ->timeout(10)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);

            if (!$response->successful()) continue;

            $data = $response->json();

            if (!isset($data['data']) || empty($data['data'])) {
                continue;
            }

            // ❌ Remove status 7
            $orders = array_filter($data['data'], function ($order) {
                return $order['order_status'] !== "7";
            });

            if (empty($orders)) continue;

            // 🔹 Latest order
            usort($orders, function ($a, $b) {
                return strtotime($b['time_stamp'] ?? '') - strtotime($a['time_stamp'] ?? '');
            });

            $latestOrder = $orders[0];
            $order_total = $latestOrder['order_total'] ?? 0;

            // 🔹 Check product match
            foreach ($latestOrder['products'] as $p) {

                if ($p['product_id'] == $stickyProductIdDB) {
                    return $order_total; // ✅ MATCH → return order price
                }
            }
        }

        // 🔹 fallback
        return $defaultPrice;
    }

	public function createOrder_test_parent_order(Request $request)
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




$price =$product_data->product_price;
        if($request->product_price != "")
        {
            $price = $request->product_price;
        }else{
            $price = $product_data->product_price;
	}

	$price = $this->getSingleProductPrice($request->email,$pro_id);
$patientData = [
                "firstName" => $request->first_name,
                "lastName" => $request->last_name,
                "currency" => "USD",
                "billingFirstName" => $request->billingFirstName,
                "billingLastName" => $request->billingLastName,
                "billingAddress1" => $request->billingAddress,
                "billingCity" => $request->billingCity,
                "billingState" => $request->billingStateCode,
                "billingZip" => $request->billingZip,
                "billingCountry" => 'US',
                'phone' => $request->phone,
                'email' => $request->email,
                "creditCardType" => $paymentMethod,
                'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
'campaignId' => $product_data->campaign_id,
               //           'campaignId' => 226,
                // 'billingSameAsShipping' => "YES",
                'shippingAddress1' => $request->address,
                'shippingCity' => $request->city_name,
                'shippingState' => $request->state_code,
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
	'price' => $price,
				"product_id" => $pro_id,
                       // "offer_id" => 477,
                        //"product_id" => 22190,
                        "billing_model_id" => 2,
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
                                ],

                                [
                                    "id" => 20,
                                    "field_name" => 'parent_order',
                                    "value" => !empty($request->parent_order) ? $request->parent_order : ""
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
   $site_secret = $site_data->site_secret;
$site_key    = $site_data->site_key;
                  $response = Http::withBasicAuth($site_key, $site_secret)
                  ->post('https://whitelabelmd.sticky.io/api/v1/new_order', $patientData);
                  $responseData = $response->json();
		  
		  
		  
		  
		  
		  $response1234 = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post(
        'https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/73GIDlWHNcHXVOzV0Lzw',
        [
            'email' =>  $request->email
        ]
    );
		  
		  
		  DB::table('log_data')->insert([
			  'email' => $request->email,
			  'webhook_url' => 'https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/73GIDlWHNcHXVOzV0Lzw',
			  'webhook_response' => json_encode($response1234),
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
//                 dd($responseData);


                  if (empty($request->apple_pay_token) && empty($request->google_pay_token)) {
                  }else{
                  $payment_received = 1;
                $this->update_order_payment_received($responseData['order_id'],$payment_received);
                  }

                DB::table('log_data_v2')->insert([
                    'email' => $request->email,
                    'request_data' => json_encode($request->all()),
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

//              dd($responseData);
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
                  $order->gateway_id = $responseData['gateway_id'];
                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $request->apple_pay_token;
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
  

    public function createOrder_test(Request $request)
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

	  'AFFID' => $request->AFFID,
                'C1' => $request->C1,
                'C2' => $request->C2,
		'C3' => $request->C3,

                "offers" => [
                    [
                        "offer_id" => $campaign_data->offer_id,
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

    public function updateOrderPaymentReceived(Request $request)
{
    $order_id = $request->order_id;
    $payment_received = $request->payment_received;

    $app_key_data = DB::table('app_keys')->first();

    $app_key    = $app_key_data->app_key;
    $app_secret = $app_key_data->app_secret;

    // Prepare payload
    $payload = [
        "order_id" => [
            $order_id => [
                "payment_received" => $payment_received
            ]
        ]
    ];

    $response = Http::withBasicAuth($app_key, $app_secret)
        ->post('https://whitelabelmd.sticky.io/api/v1/order_update', $payload);

    if ($response->successful()) {
        return response()->json([
            "status" => true,
            "message" => "Payment status updated",
            "response" => $response->json()
        ]);
    } else {
        return response()->json([
            "status" => false,
            "message" => "API request failed",
            "error" => $response->body()
        ], 500);
    }
}

public function getWebhookLogs(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->whereIn('event_type', ['Pay & Check-in']);

    $totalRecords = $query->count();
    $totalPages = ceil($totalRecords / $perPage);

    $records = $query
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    foreach ($records as $row) {

        $row->check_in_completed = false;
        $row->new_order_id_same_date = null;

        // Check webhook table
        if ($row->event_type == 'check_in_click') {

            $exists = DB::table('webhook')
                ->where('email', $row->email)
                ->whereDate('created_at', \Carbon\Carbon::parse($row->created_at)->toDateString())
                ->exists();

            if ($exists) {
                $row->check_in_completed = true;
            }
        }

        // Check second DB orders table
        $order = DB::connection('second_db')
            ->table('orders')
            ->where('email_address', $row->email)
            ->whereDate('inserted', \Carbon\Carbon::parse($row->created_at)->toDateString())
            ->first();

        if ($order) {
            $row->new_order_id_same_date = $order->order_id ?? null;
        }
    }

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $records
    ]);
}




public function getRefillLogsWithOrders(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->where('event_type', 'Pay & Check-in');

    // Single date filter
    if ($request->date) {
        $query->whereDate('created_at', $request->date);
    }

    // Date range filter
    if ($request->from_date && $request->to_date) {
        $query->whereBetween('created_at', [
            $request->from_date . ' 00:00:00',
            $request->to_date . ' 23:59:59'
        ]);
    }

    $records = $query
        ->orderBy('id', 'desc')
        ->get();

    $filtered = [];

    foreach ($records as $row) {

        $row->new_order_id_same_date = null;

        $order = DB::connection('second_db')
            ->table('orders')
            ->where('email_address', $row->email)
            ->whereDate('inserted', \Carbon\Carbon::parse($row->created_at)->toDateString())
            ->first();

        if ($order) {
            $row->new_order_id_same_date = $order->order_id ?? null;
            $filtered[] = $row;
        }
    }

    $totalRecords = count($filtered);
    $totalPages = ceil($totalRecords / $perPage);

    $data = array_slice($filtered, ($page - 1) * $perPage, $perPage);

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => array_values($data)
    ]);
}


public function getRefillLogsWithOrders2323(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->where('event_type', 'Pay & Check-in');

    $records = $query
        ->orderBy('id', 'desc')
        ->get();

    $filtered = [];

    foreach ($records as $row) {

        $row->new_order_id_same_date = null;

        $order = DB::connection('second_db')
            ->table('orders')
            ->where('email_address', $row->email)
            ->whereDate('inserted', \Carbon\Carbon::parse($row->created_at)->toDateString())
            ->first();

        if ($order) {
            $row->new_order_id_same_date = $order->order_id ?? null;
            $filtered[] = $row;
        }
    }

    $totalRecords = count($filtered);
    $totalPages = ceil($totalRecords / $perPage);

    $data = array_slice($filtered, ($page - 1) * $perPage, $perPage);

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => array_values($data)
    ]);
}



public function getWebhookLogs_old_order(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->whereIn('event_type', ['Pay & Check-in']);

    $totalRecords = $query->count();
    $totalPages = ceil($totalRecords / $perPage);

    $records = $query
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    foreach ($records as $row) {

        $row->check_in_completed = false;

        if ($row->event_type == 'check_in_click') {

            $exists = DB::table('webhook')
                ->where('email', $row->email)
//                ->where('event_type', 'Pay & Check-in')
                ->whereDate('created_at', \Carbon\Carbon::parse($row->created_at)->toDateString())
                ->exists();

            if ($exists) {
                $row->check_in_completed = true;
            }
        }
    }

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $records
    ]);
}

public function getCheckInClicks(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->where('event_type', 'check_in_click');

    $totalRecords = $query->count();
    $totalPages = ceil($totalRecords / $perPage);

    $records = $query
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    foreach ($records as $row) {

        $exists = DB::table('webhook')
            ->where('email', $row->email)
//            ->where('event_type', 'Pay & Check-in')
            ->whereDate('created_at', date('Y-m-d', strtotime($row->created_at)))
            ->exists();

        $row->check_in_completed = $exists ? true : false;
    }

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $records
    ]);
}

public function getCheckInClicksFilter(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $query = DB::table('refill_logs')
        ->where('event_type', 'check_in_click');

    // SINGLE DATE FILTER
    if ($request->date) {
        $query->whereDate('created_at', $request->date);
    }

    // DATE RANGE FILTER
    if ($request->from_date && $request->to_date) {
        $query->whereBetween('created_at', [
            $request->from_date . ' 00:00:00',
            $request->to_date . ' 23:59:59'
        ]);
    }

    $totalRecords = $query->count();
    $totalPages = ceil($totalRecords / $perPage);

    $records = $query
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    foreach ($records as $row) {

        $exists = DB::table('webhook')
            ->where('email', $row->email)
            ->whereDate('created_at', date('Y-m-d', strtotime($row->created_at)))
            ->exists();

        $row->check_in_completed = $exists ? true : false;
    }

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $records
    ]);
}

public function getWebhookLogs12(Request $request)
{
    $page = $request->page ?? 1;
    $perPage = 50;

    $totalRecords = DB::table('refill_logs')
        ->whereIn('event_type', ['Pay & Check-in', 'check_in_click'])
        ->count();

    $totalPages = ceil($totalRecords / $perPage);

    $data = DB::table('refill_logs')
        ->whereIn('event_type', ['Pay & Check-in', 'check_in_click'])
        ->orderBy('id', 'desc')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

    return response()->json([
        'current_page' => (int)$page,
        'per_page' => $perPage,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'data' => $data
    ]);
}

 public function createOrder_test_2345(Request $request)
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
                "billingState" => $request->billingStateCode,
                "billingZip" => $request->billingZip,
                "billingCountry" => 'US',
                'phone' => $request->phone,
                'email' => $request->email,
		"creditCardType" => $paymentMethod,
		'shippingId' => 2,
                'tranType' => "Sale",
                "event_id" => $event_data->event_id,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
'campaignId' => $product_data->campaign_id,	       
       	       //           'campaignId' => 226,
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
                        "product_id" => $pro_id,
                       // "offer_id" => 477,
                        //"product_id" => 22190,
                        "billing_model_id" => 2,
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
//	$patientData["stripe_token"] =
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
//                   dd($responseData);
		 

		  if (empty($request->apple_pay_token) && empty($request->google_pay_token)) {
		  }else{
		  $payment_received = 1;
                $this->update_order_payment_received($responseData['order_id'],$payment_received);
		  }

		DB::table('log_data_v2')->insert([
                    'email' => $request->email,
                    'request_data' => json_encode($request->all()),
                    'data' => json_encode($responseData),
                    'created_at' => now(),
                    'updated_at' => now(),
		]);

//		dd($responseData); 
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
                  $order->gateway_id = $responseData['gateway_id'];

                  $order->response_code = $responseData['response_code'];
                  $order->error_found = $responseData['error_found'];
                  $order->order_id = $responseData['order_id'];
                  $order->transactionID = $request->apple_pay_token;
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
                          "offer_id" => $campaignData->offer_id,
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
