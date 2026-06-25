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
use Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
class AgentController extends Controller
{

	public function get_email_by_case_details(Request $request)
    {
        $email    = $request->email;
        $order_id = $request->order_id;

        if (empty($email) || empty($order_id)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Email and Order ID are required'
            ]);
        }

        /* Sticky credentials */
        $app_key_data = DB::table('app_keys')->first();

        if (!$app_key_data) {
            return response()->json([
                'status'  => 0,
                'message' => 'App keys not found'
            ]);
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        /* Get sites */
        $sites = DB::table('site')->get();

        if ($sites->isEmpty()) {
            return response()->json([
                'status'  => 0,
                'message' => 'No sites found'
            ]);
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

                $filteredOrders = array_filter($response['data'], function ($order) {
                    return ($order['order_status'] ?? null) != 7;
                });

                $allOrders = array_merge($allOrders, $filteredOrders);
            }
        }

        if (empty($allOrders)) {
            return response()->json([
                'status' => 0,
                'message' => 'No valid orders found'
            ]);
        }

        /* Latest order first */
        usort($allOrders, function ($a, $b) {
            return strtotime($b['time_stamp']) <=> strtotime($a['time_stamp']);
        });

        $latest_order_id = null;
        $latest_order_status = null;
        $case_id = null;

        foreach ($allOrders as $order) {

            if ($order['order_id'] == $order_id) {

                $latest_order_id = $order['order_id'];
                $latest_order_status = $order['order_status'] ?? null;

                if (!empty($order['custom_fields'])) {

                    foreach ($order['custom_fields'] as $field) {

                        if (
                            ($field['name'] ?? '') === 'prescription_info' &&
                            isset($field['values'][0]['value'])
                        ) {

                            $value = json_decode($field['values'][0]['value'], true);

                            if (!empty($value['case_id'])) {
                                $case_id = $value['case_id'];
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (!$latest_order_id) {
            return response()->json([
                'status' => 0,
                'message' => 'Order ID not found'
            ]);
        }

        if (!$case_id) {
            return response()->json([
                'status' => 0,
                'message' => 'Case ID not found'
            ]);
        }

        /* WL Client Credentials */
        $client_data = DB::table('client_key')->where('is_wl', 1)->first();

        if (!$client_data) {
            return response()->json([
                'status' => 0,
                'message' => 'Client credentials not found'
            ]);
        }

        /* Get Access Token */
        $tokenResponse = Http::post(
            "https://api.mdintegrations.com/v1/partner/auth/token",
            [
                'grant_type'    => $client_data->grant_type,
                'client_id'     => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope'         => $client_data->scope,
            ]
        );

        $accessToken = $tokenResponse['access_token'] ?? "";

        if (empty($accessToken)) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to generate access token'
            ]);
        }

        /* =========================
           Patient Search API
        ==========================*/

        $searchResponse = Http::withToken($accessToken)->post(
            "https://api.mdintegrations.com/v1/partner/patients/search",
            [
                'search'     => $email,
                'is_sandbox' => false,
            ]
        );

        if (!$searchResponse->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to search patients',
                'error'   => $searchResponse->body()
            ]);
        }

        $patientData = $searchResponse->json();
        $patient_dob = null;

        if (!empty($patientData) && isset($patientData[0]['date_of_birth'])) {
            $patient_dob = $patientData[0]['date_of_birth'];
        }

        /* =========================
           Prescription API
        ==========================*/

        $prescriptionResponse = Http::withToken($accessToken)
            ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}/prescriptions");

        $prescriptions = [];

        if ($prescriptionResponse->successful() && is_array($prescriptionResponse->json())) {

            foreach ($prescriptionResponse->json() as $p) {
                $prescriptions[] = $p;
            }
        }

        return response()->json([
            'status' => 1,
            'email' => $email,
            'case_id' => $case_id,
            'latest_order_id' => $latest_order_id,
            'latest_order_status' => $latest_order_status,
            'patient_dob' => $patient_dob,
            'prescriptions' => $prescriptions
        ]);
    }

public function get_email_by_case_details27323(Request $request)
    {
        $email = $request->email;
    
        if (empty($email)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Email is required'
            ]);
        }
    
        /* Sticky credentials */
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return response()->json([
                'status'  => 0,
                'message' => 'App keys not found'
            ]);
        }
    
        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;
    
        /* Get sites */
        $sites = DB::table('site')->get();
    
        if ($sites->isEmpty()) {
            return response()->json([
                'status'  => 0,
                'message' => 'No sites found'
            ]);
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
    
                /* remove order_status = 7 */
                $filteredOrders = array_filter($response['data'], function ($order) {
                    return ($order['order_status'] ?? null) != 7;
                });
    
                $allOrders = array_merge($allOrders, $filteredOrders);
            }
        }
    
        if (empty($allOrders)) {
            return response()->json([
                'status' => 0,
                'message' => 'No valid orders found'
            ]);
        }
    
        /* Latest order first */
        usort($allOrders, function ($a, $b) {
            return strtotime($b['time_stamp']) <=> strtotime($a['time_stamp']);
        });
    
        $latest_order_id = $allOrders[0]['order_id'] ?? null;
        $latest_order_status = $allOrders[0]['order_status'] ?? null;
    
        $case_id = null;
    
        foreach ($allOrders as $order) {
    
            if (!empty($order['custom_fields'])) {
    
                foreach ($order['custom_fields'] as $field) {
    
                    if (
                        ($field['name'] ?? '') === 'prescription_info' &&
                        isset($field['values'][0]['value'])
                    ) {
    
                        $value = json_decode($field['values'][0]['value'], true);
    
                        if (!empty($value['case_id'])) {
                            $case_id = $value['case_id'];
                            break 2;
                        }
                    }
                }
            }
        }
    
        if (!$case_id) {
            return response()->json([
                'status' => 0,
                'message' => 'Case ID not found'
            ]);
        }
    
        /* WL Client Credentials */
        $client_data = DB::table('client_key')->where('is_wl', 1)->first();
    
        if (!$client_data) {
            return response()->json([
                'status' => 0,
                'message' => 'Client credentials not found'
            ]);
        }
    
        /* Get Access Token */
        $tokenResponse = Http::post(
            "https://api.mdintegrations.com/v1/partner/auth/token",
            [
                'grant_type'    => $client_data->grant_type,
                'client_id'     => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope'         => $client_data->scope,
            ]
        );
    
        $accessToken = $tokenResponse['access_token'] ?? "";
    
        if (empty($accessToken)) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to generate access token'
            ]);
        }
    
        /* =========================
           Patient Search API
        ==========================*/
    
        $searchUrl = "https://api.mdintegrations.com/v1/partner/patients/search";
    
        $searchResponse = Http::withToken($accessToken)->post($searchUrl, [
            'search'     => $email,
            'is_sandbox' => false,
        ]);
    
        if (!$searchResponse->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to search patients',
                'error' => $searchResponse->body()
            ]);
        }
    
        $patientData = $searchResponse->json();

        $patient_dob = null;
        
        if (!empty($patientData) && isset($patientData[0]['date_of_birth'])) {
            $patient_dob = $patientData[0]['date_of_birth'];
        }
    
        /* =========================
           Prescription API
        ==========================*/
    
        $prescriptionResponse = Http::withToken($accessToken)
            ->get("https://api.mdintegrations.com/v1/partner/cases/{$case_id}/prescriptions");
    
        $prescriptions = [];
    
        if ($prescriptionResponse->successful() && is_array($prescriptionResponse->json())) {
    
            foreach ($prescriptionResponse->json() as $p) {
                $prescriptions[] = $p;
            }
        }
    
        return response()->json([
            'status' => 1,
            'email' => $email,
            'case_id' => $case_id,
            'latest_order_id' => $latest_order_id,
            'latest_order_status' => $latest_order_status,
            // 'patient_details' => $patientData,
            'patient_dob' => $patient_dob,
            'prescriptions' => $prescriptions
        ]);
    }

public function login_timeline(Request $request)
    {
        // ✅ pagination inputs
        $perPage = (int) $request->get('per_page', 10); // default 10
        $page    = (int) $request->get('page', 1);
    
        if ($perPage <= 0) {
            $perPage = 10;
        }
    
        $query = DB::table('api_logs')
            ->whereIn('url', [
                'https://panel.ravinimavat.com/ageshiftrx/api/user_login',
                'https://panel.ravinimavat.com/ageshiftrx/api/phone_verification',
                'https://panel.ravinimavat.com/ageshiftrx/api/verify_verification_code',
	    ])
	    ->whereRaw("JSON_EXTRACT(response_body, '$.status') = 0")
            ->orderBy('id', 'desc');
    
        // ✅ pagination (DB level)
        $logs = $query->paginate($perPage, ['*'], 'page', $page);
    
        $timeline = [];
    
        foreach ($logs->items() as $log) {
    
            $headers = json_decode($log->request_headers, true);
            $ua = $headers['user-agent'][0] ?? '';
    
            /* ===== Device ===== */
            if (preg_match('/tablet|ipad/i', $ua)) {
                $device = 'Tablet';
            } elseif (preg_match('/mobile|android|iphone/i', $ua)) {
                $device = 'Mobile';
            } elseif ($ua) {
                $device = 'Desktop';
            } else {
                $device = 'Unknown';
            }
    
            /* ===== Browser ===== */
            if (stripos($ua, 'Edg') !== false) {
                $browser = 'Edge';
            } elseif (stripos($ua, 'Chrome') !== false) {
                $browser = 'Chrome';
            } elseif (stripos($ua, 'Firefox') !== false) {
                $browser = 'Firefox';
            } elseif (stripos($ua, 'Safari') !== false) {
                $browser = 'Safari';
            } else {
                $browser = substr($ua, 0, 40);
            }
    
            /* ===== Response Status ===== */
            $response = json_decode($log->response_body, true);
    
            // ✅ sirf status = 0 (fail)
            if (!is_array($response) || !isset($response['status']) || $response['status'] != 0) {
                continue;
            }
    
            /* ===== Request ===== */
            $req = json_decode($log->request_body, true);
    
            // email > phone (+1)
            $identifier = $req['email']
                ?? (isset($req['phone']) ? '+1' . $req['phone'] : null);
    
            $loginType = 'password';
            if (isset($req['otp']) || isset($req['verification_code'])) {
                $loginType = 'otp';
            }
            if (($req['is_magic'] ?? 0) == 1) {
                $loginType = 'magic_link';
            }
    
            $timeline[] = [
                'identifier'          => $identifier,
                'identifier_message'  => $response['message'] ?? null,
                'status'              => 'failed',
                'login_type'          => $loginType,
                'device'              => $device,
                'browser'             => $browser,
                'ip_address'          => $log->ip_address,
                'login_time'          => $log->execution_time,
                'created_at'          => Carbon::parse($log->created_at)
                                            ->setTimezone('America/New_York')
                                            ->format('Y-m-d H:i:s'),
            ];
        }
    
        return response()->json([
            'status' => 1,
            'meta'   => [
                'current_page' => $logs->currentPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'last_page'    => $logs->lastPage(),
            ],
            'data' => $timeline
        ]);
    }

    public function get_case_id_by_email_api(Request $request)
    {
        $email = $request->email;

        if (empty($email)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Email is required'
            ]);
        }

        /* Sticky credentials */
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return response()->json([
                'status'  => 0,
                'message' => 'App keys not found'
            ]);
        }

        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;

        /* All sites */
        $sites = DB::table('site')->get();
        if ($sites->isEmpty()) {
            return response()->json([
                'status'  => 0,
                'message' => 'No sites found'
            ]);
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
            return response()->json([
                'status'  => 0,
                'message' => 'No orders found'
            ]);
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
                            return response()->json([
                                'status'  => 1,
                                'email'   => $email,
                                'case_id' => $value['case_id']
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json([
            'status'  => 0,
            'message' => 'Case ID not found'
        ]);
    }

    public function agent_login(Request $request)
    {
        $email    = $request->email;
        $password = $request->password;

        if (empty($email) || empty($password)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Email and password required'
            ]);
        }

        $agent = DB::table('agent_login')
            ->where('email', $email)
            ->where('status', 1)
            ->first();

        if (!$agent) {
            return response()->json([
                'status'  => 0,
                'message' => 'Invalid credentials'
            ]);
        }

        if (!Hash::check($password, $agent->password)) {
            return response()->json([
                'status'  => 0,
                'message' => 'Invalid credentials'
            ]);
        }

        // update last login
        DB::table('agent_login')
            ->where('id', $agent->id)
            ->update(['last_login_at' => now()]);

        return response()->json([
            'status' => 1,
            'message' => 'Login successful',
            'data' => [
                'id'    => $agent->id,
                'name'  => $agent->name,
                'email' => $agent->email,
                'role'  => $agent->role
            ]
        ]);
    }

private function order_find_new_v1($email)
    {
        if (empty($email)) {
            return null;
        }
    
        // Sticky.io credentials
        $app_key_data = DB::table('app_keys')->first();
        $site_data_list = DB::table('site')->get(); // GET ALL SITES
    
        if (!$app_key_data || $site_data_list->isEmpty()) {
            return null;
        }
    
        $app_key    = $app_key_data->app_key;
        $app_secret = $app_key_data->app_secret;
    
        $all_orders = [];
    
        // 🔹 LOOP THROUGH EACH SITE
        foreach ($site_data_list as $site_data) {
    
            $payload = [
                "campaign_id" => $site_data->campaign_id,
                "start_date"  => "01/01/2024",
                "end_date"    => "11/11/2029",
                "criteria"    => ["email" => $email],
                "return_type" => "order_view"
            ];
    
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);
    
            $responseData = $response->json();
            // dd($responseData);
            if ($response->successful() && isset($responseData['data'])) {
                $all_orders = array_merge($all_orders, $responseData['data']);
            }
        }
    
        // 🔹 NO ORDERS FOUND
        if (empty($all_orders)) {
            return [];
        }
    
        $orders = $all_orders;
        // dd($orders);

    
        // 🔹 PROCESS ORDERS
        $orderDetails = array_map(function ($order) use ($orders) {
    
            // Product data
            $main_product_id = $order['main_product_id'];
            $product_data = DB::table('product')->where('sticky_product_id', $main_product_id)->first();
            $product_image = DB::table('product_image')->where('product_id', $product_data->product_id)->first();
    
            $img_video = $product_image ? URL("/public/assets/product_img/" . $product_image->img_video) : "";
            $order_total = $order['order_total'] ?? null;
            $product_description = $product_data->product_description;
    

            // 🔹 Get client data for MD Integrations
            $is_wl = ($product_data->product_category_name == "Weight Loss") ? 1 : 0;
            // $is_wl = 1;
            $client_data = DB::table('client_key')->where('is_wl', $is_wl)->first();
        
            if (!$client_data) {
                return response()->json(['status' => 0, 'message' => 'Client data not found']);
            }
        
            // 🔹 Fetch Access Token
            $tokenUrl = "https://api.mdintegrations.com/v1/partner/auth/token";
            $tokenResponse = Http::post($tokenUrl, [
                'grant_type'    => $client_data->grant_type,
                'client_id'     => $client_data->client_id,
                'client_secret' => $client_data->client_secret,
                'scope'         => $client_data->scope,
            ]);
        
            if (!$tokenResponse->successful()) {
                return response()->json(['status' => 0, 'message' => 'Failed to retrieve access token']);
            }
        
            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? "";
            // dd($accessToken);
            if (!$accessToken) {
                return response()->json(['status' => 0, 'message' => 'Access token missing']);
            }
            // CLEAN BM TAG
            $clean_description = preg_replace('/\s*BM:\d+/', '', $product_description);
    
            // Extract BM
            $bm = 0;
            if (preg_match('/BM:(\d+)/', $product_description, $matches)) {
                $bm = (int) $matches[1];
            }
    
            // TOTAL DELIVERIES
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
    
            // COMPLETED DELIVERIES
            $completed_deliveries = collect($orders)
                ->filter(fn($o) => $o['main_product_id'] == $main_product_id)
                ->count();
    
            if ($bm === 0) {
                $remaining_product = "Unlimited";
                $total_display = "Unlimited";
            } else {
                $remaining_product = max(0, $total_deliveries - $completed_deliveries);
                $total_display = $total_deliveries;
            }
    
            // 🔹 Extract case_id
            $case_id = "";
            if (isset($order['custom_fields']) && is_array($order['custom_fields'])) {
                foreach ($order['custom_fields'] as $field) {
                    if (
                        isset($field['name']) &&
                        $field['name'] === 'prescription_info' &&
                        isset($field['values'][0]['value'])
                    ) {
                        $value = json_decode($field['values'][0]['value'], true);
                        if (isset($value['case_id'])) {
                            $case_id = $value['case_id'];
                        }
                    }
                }
            }
    
            // 🔹 Fetch clinician + prescription details
            $clinician_first = "";
            $clinician_last = "";
            $prescriptions = [];
            $caseData = [];
            // dd($case_id);
            if (!empty($case_id)) {
                try {
                    // CLINICIAN INFO
                    $caseUrl = "https://api.mdintegrations.com/v1/partner/cases/{$case_id}";
                    $caseResponse = Http::withToken($accessToken)->get($caseUrl);
                    // dd($caseData);
                    if ($caseResponse->successful()) {
                    $caseData = $caseResponse->json();

                        // dd($caseData);
                        if (isset($caseData['case_assignment']['clinician'])) {
                            $clinician = $caseData['case_assignment']['clinician'];
                            $clinician_first = $clinician['first_name'] ?? "";
                            $clinician_last = $clinician['last_name'] ?? "";
                        }
                    }
    
                    // PRESCRIPTION INFO
                    // $prescriptionUrl = "https://api.mdintegrations.com/v1/partner/cases/{$case_id}/prescriptions";
                    // $prescriptionResponse = Http::withToken($accessToken)->get($prescriptionUrl);
    
                    // if ($prescriptionResponse->successful()) {
                    //     $prescriptionData = $prescriptionResponse->json();
    
                    //     if (is_array($prescriptionData)) {
                    //         $prescriptions = array_map(function ($p) {
                    //             return [
                    //                 'title'       => $p['title'] ?? "",
                    //                 'name'        => $p['name'] ?? "",
                    //                 'refills'     => $p['refills'] ?? "",
                    //                 'quantity'    => $p['quantity'] ?? "",
                    //                 'days_supply' => $p['days_supply'] ?? ""
                    //             ];
                    //         }, $prescriptionData);
                    //     }
                    // }
    
                } catch (\Exception $e) {
                    Log::error("MDI API error for case_id {$case_id}: " . $e->getMessage());
                }
            }
    
            return [
                'order_id'              => $order['order_id'] ?? null,
                'case_id'               => $case_id,
                'case_data'             => $caseData,
                'clinician_first_name'  => $clinician_first,
                'clinician_last_name'   => $clinician_last,
                // 'prescriptions'         => $prescriptions
            ];
    
        }, $orders);
    
        // 🔹 SORT BY DESCENDING TIME
        // usort($orderDetails, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
        usort($orderDetails, function ($a, $b) {
            $timeA = isset($a['time']) ? strtotime($a['time']) : 0;
            $timeB = isset($b['time']) ? strtotime($b['time']) : 0;
            return $timeB <=> $timeA;
        });
        
    
        return $orderDetails;
    }
    

    public function get_user_cart_products_new_v1(Request $request)
    {
        $email = $request->email;
        
        if (!empty($email)) {
            $order_data = $this->order_find_new_v1($email);
            
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


	public function super_admin_login(Request $request)
    {
        $email    = $request-> email;
        $password = $request-> password;

        if($email !== '' && $password !='')
        {
            $user_email = DB::table('super_admin_login')->Where('email',$email)->first();

            if($user_email)
            {
                if(Hash::check($password,DB::table('super_admin_login')->Where('email',$email)->get()->first()->password))
                {
                    $getResponse = DB::table('super_admin_login')->where('email',$email)->get()->first();


                    $result['admin_id']   = $getResponse->id;
                    $result['name']       = $getResponse->name;
                    $result['email']      = $getResponse->email;
                    $result['is_permission']      = $getResponse->is_permission;
                    $result['is_admin']      = $getResponse->is_admin;
                  
                    $result['status']           = 1;
                    $result['message']          = "Great! You have Successfully logged in. Welcome!";
                }
                else
                {
                    $result['status']  = 0;
                    $result['message'] = "The password you entered is invalid. Please enter a valid password and try again.";
                }
            }
            else
            {
                $result['status']  = 0;
                $result['message'] = "This email address does not exist. ";
            }

        }
        else
        {
            $result['status']  = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }

        return response()->json($result);
    }


    public function add_new_sub_admin(Request $request)
    {
        $name     = $request-> name;
        $email    = $request-> email;
        $password = $request-> password;
        $phone    = $request-> phone;

        if(!empty($name) && !empty($email) && !empty($password) && !empty($phone))
        {
            if(filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                if(DB::table("super_admin_login")->where('email',$email)->count() == 0)
                {
                    if(intval($phone) && strlen($phone) >= 10)
                    {
                        if(DB::table("super_admin_login")->where('phone',$phone)->count() == 0)
                        {
                            DB::table("super_admin_login")->insertGetId([
                                'password'       => Hash::make($password),
                                'email'          => $email,
                                'phone'          => $phone,
                                'name'           => $name,
                                'is_admin'       => 0,
                                'created_at'     => date("Y-m-d H:i:s"),
                            ]);

                            $result['status']  = 1;
                            $result['message'] = "Congratulations! Admin Add Successfully";   
                        }
                        else
                        {
                            $result['status']  = 0;
                            $result['message'] = "The phone number you entered is already registered. Please try logging in or using a different phone number."; 
                        }
                    }
                    else
                    {
                        $result['status']  = 0;
                        $result['message'] = "The Phone Number you entered is invalid. Please enter a valid Phone Numbe and try again.";
                    }
                }
                else
                {
                    $result['status']  = 0;
                    $result['message'] =  "The email you entered is already registered. Please try logging in or using a different email.";
                }
            }
            else
            {
                $result['status']  = 0;
                $result['message'] = "Your email is incorrect. Please try again with the correct email."; 
            }

        }
        else
        {
            $result['status']  = 0;
            $result['message'] = "Please fill out all the required details in the form before submitting it.";
        }
        return response()->json($result);

    }

    
    public function get_all_campaign(Request $request)
    {
        // Fetch API keys from the database
        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return response()->json([
                'status' => 'error',
                'message' => 'API keys not found in the database.',
            ], 500);
        }
    
        $site_key = "levelupmeds_bg";
        $site_secret = "ZbTXGH89qaBvpP";
    
        try {
            // Fetch active campaigns from the API
            $response = Http::withBasicAuth($site_key, $site_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/campaign_find_active');
    
            // Check if the request was successful
            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $response->json()['error_message'] ?? 'Failed to fetch data from the API',
                ], $response->status());
            }
    
            // Decode response data
            $data = $response->json();
    
            // Check if the "campaigns" key exists and is not empty
            if (empty($data['campaigns'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No campaigns found in the API response.',
                ], 404);
            }
    
            // Initialize an array to store all campaigns
            $campaigns = [];
    
            // Iterate over each campaign and collect required details
            foreach ($data['campaigns'] as $campaign) {
                $campaigns[] = [
                    'campaign_id' => $campaign['campaign_id'] ?? null,
                    'campaign_name' => $campaign['campaign_name'] ?? 'Unknown',
                ];
            }
    
            // Return the response with all campaigns
            return response()->json([
                'status' => 'success',
                'message' => 'Campaigns fetched successfully.',
                'campaigns' => $campaigns,
            ]);
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function shipping_method_find(Request $request)
    {
        // Fetch API keys and site data from the database
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

        // Prepare the payload for the API request
        $payload = [
            "campaign_id" => $campaign_id,
            "search_type" => "all",
            "criteria" => [],
            "return_type" => "shipping_method_view"
        ];

        // Make the request with basic authentication
        $response = Http::withBasicAuth($app_key, $api_ext)
            ->post('https://whitelabelmd.sticky.io/api/v1/shipping_method_find', $payload);

        $responseData = $response->json();

        // Check if the response was successful
        if ($response->successful()) {
            // Process the response data
            $shippingMethods = [];

            // Iterate over shipping methods
            foreach ($responseData['data'] ?? [] as $id => $method) {
                $shippingMethods[] = [
                    'id' => $id,
                    'name' => $method['name'] ?? 'Unknown',
                    'description' => $method['description'] ?? 'No description provided.',
                    'group_name' => $method['group_name'] ?? 'N/A',
                    'code' => $method['code'] ?? 'N/A',
                    'initial_amount' => $method['initial_amount'] ?? '0.00',
                    'subscription_amount' => $method['subscription_amount'] ?? '0.00',
                ];
            }

            // Return a structured response
            return response()->json([
                'status' => 1,
                'message' => 'Shipping methods retrieved successfully.',
                'total_shipping_methods' => $responseData['total_shipping_methods'] ?? 0,
                'shipping_methods' => $shippingMethods,
            ]);
        } else {
            // Handle API errors
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve shipping methods.',
                'error' => $responseData,
            ], $response->status());
        }
    }

    public function ShippingMethodFind(Request $request)
    {
        // Fetch API keys and site data from the database
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

        // Prepare the payload for the API request
        $payload = [
            "campaign_id" => $campaign_id,
            "search_type" => "all",
            "criteria" => [],
            "return_type" => "shipping_method_view"
        ];

        // Make the request with basic authentication
        $response = Http::withBasicAuth($app_key, $api_ext)
            ->post('https://whitelabelmd.sticky.io/api/v1/shipping_method_find', $payload);

        $responseData = $response->json();

        // Check if the response was successful
        if ($response->successful()) {
            // Process the response data
            $shippingMethods = [];

            // Iterate over shipping methods
            foreach ($responseData['data'] ?? [] as $id => $method) {
                $shippingMethods[] = [
                    'id' => $id,
                    'name' => $method['name'] ?? 'Unknown',
                    'description' => $method['description'] ?? 'No description provided.',
                    'group_name' => $method['group_name'] ?? 'N/A',
                    'code' => $method['code'] ?? 'N/A',
                    'initial_amount' => $method['initial_amount'] ?? '0.00',
                    'subscription_amount' => $method['subscription_amount'] ?? '0.00',
                ];
            }

            // Return a structured response
            return response()->json([
                'status' => 1,
                'message' => 'Shipping methods retrieved successfully.',
                'total_shipping_methods' => $responseData['total_shipping_methods'] ?? 0,
                'shipping_methods' => $shippingMethods,
            ]);
        } else {
            // Handle API errors
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve shipping methods.',
                'error' => $responseData,
            ], $response->status());
        }
    }

    public function add_coupons(Request $request)
    {
        $name                   = $request->name;
        $description            = $request->description;
        $minimum_purchase       = $request->minimum_purchase;
        $code                   = $request->code;
        $percent                = $request->percent;
        $date                   = $request->date;

        $total                  = $request->total;
        $per_customer           = $request->per_customer;
        $per_code               = $request->per_code;
        $per_code_per_customer  = $request->per_code_per_customer;
        // dd($request);
        if (
            $name != "" && $description != "" && $minimum_purchase != "" &&
            $date != ""  &&
            $code != "" && $percent != ""
        ) 

        
        {
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

            $payload = [
                "name" => $name,
                "description" => $description,
                "type_id" => 1,
                "minimum_purchase" => $minimum_purchase,
                "is_lifetime" => 1,
                "is_free_shipping" => 1,
                "expiration" => [
                    "date" => $date,
                    "timezone" => "America/New_York"
                ],
                "limits" => [
                    "total" => $total,
                    "per_customer" => $per_customer,
                    "per_code" => $per_code,
                    "per_code_per_customer" => $per_code_per_customer,
                ],
                "promo_codes" => [
                    [
                        "code" => $code
                    ]
                ],
                "discount" => [
                    "type_id" => 34,
                    "behavior_type_id" => 36,
                    "percent" => $percent
                ]
            ];

            $response = Http::withBasicAuth($app_key, $api_ext)
                ->post('https://whitelabelmd.sticky.io/api/v2/coupons', $payload);
           
            if ($response->successful()) {
                $responseData = $response->json();
                // dd($responseData);
                DB::table('coupons')->insert([
                    'id' => $responseData['data']['id'],
                    'name' => $responseData['data']['name'],
                    'description' => $responseData['data']['description'],
                    'discount_percent' => $responseData['data']['discount_pct'],
                    'minimum_purchase' => $responseData['data']['minimum_purchase'],
                    'is_lifetime' => $responseData['data']['is_lifetime'],
                    'is_free_shipping' => $responseData['data']['is_free_shipping'],
                    'expiration_date' => $responseData['data']['expires_at']['date'],
                    'limits_total' => $responseData['data']['limits']['total'] ?? null,
                    'limits_per_customer' => $responseData['data']['limits']['per_customer']?? null,
                    'limits_per_code' => $responseData['data']['limits']['per_code']?? null,
                    'limits_per_code_per_customer' => $responseData['data']['limits']['per_code_per_customer']?? null,
                    'promo_code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $couponId = $responseData['data']['id'];

                // Retrieve existing coupons from GetAllCoupons
                $couponResponse = $this->GetAllCoupons();
                $couponsData = $couponResponse->getData(true);

                if (!empty($couponsData['data'])) {
                    // Extract all coupon IDs including the newly created one
                    $couponProfiles = collect($couponsData['data'])
                        ->pluck('coupons_id')
                        ->push($couponId)
                        ->unique()
                        ->toArray();

                    // Update campaign with all coupon profiles
                    $campaignPayload = [
                        "coupon_profiles" => $couponProfiles
                    ];

                    $campaignResponse = Http::withBasicAuth($app_key, $api_ext)
                        ->put("https://whitelabelmd.sticky.io/api/v2/campaigns/{$campaign_id}", $campaignPayload);

                    if ($campaignResponse->successful()) {
                        return response()->json([
                            'status' => 1,
                            'message' => 'Coupon created and associated with the campaign successfully.',
                            'data' => [
                                'coupon' => $responseData['data'],
                                'campaign' => $campaignResponse->json()
                            ],
                        ]);
                    } else {
                        return response()->json([
                            'status' => 0,
                            'message' => 'Coupon created, but failed to associate it with the campaign.',
                            'error' => $campaignResponse->json(),
                        ], $campaignResponse->status());
                    }
                } else {
                    return response()->json([
                        'status' => 0,
                        'message' => 'Failed to retrieve existing coupons.',
                        'error' => $couponResponse,
                    ]);
                }
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to create coupon.',
                    'error' => $response->json(),
                ], $response->status());
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Please fill out all the required details in the form before submitting it.',
            ], 200);
        }
    }


    public function get_all_coupons(Request $request)
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

    private function GetAllCoupons()
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
                        // 'name' => $coupon['name'],
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
    
    
    

    public function billing_model_view(Request $request)
    {
        $site_data = DB::table('site')->first();
        $app_key_data = DB::table('app_keys')->first();
        $campaign_data = DB::table('campaign')->where('campaign_id',$site_data->campaign_id)->first();

        if (!$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API keys or site data not found.',
            ], 500);
        }
    
        $app_key = $app_key_data->app_key;
        $api_ext = $app_key_data->app_secret;
        $offer_id = $campaign_data->offer_id;
       
        // dd($offer_id);
        // Prepare the payload for the API request
        $payload = [
            "offer_id" => $offer_id,
        ];
            $response = Http::withBasicAuth($app_key, $api_ext)
            ->post('https://whitelabelmd.sticky.io/api/v1/billing_model_view', $payload);
            // dd($response);
            // Handle the response
            if ($response->successful()) 
            {
                $responseData = $response->json();
                return response()->json([
                    'status' => 1,
                    'message' => 'Billing Data Get successfully.',
                    'data' => $responseData['data'],
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to  Get Billing Data.',
                    'error' => $response->json(),
                ], $response->status());
            }
    }

    public function get_gateway(Request $request)
    {
        $data = DB::table('gateway')->where('gateway_id','!=',0)->get();
    
        $gateway_data = [];
    
        foreach ($data as $gateway) {
            $gateway_data[] = [
                'name' => $gateway->name,
                'gateway_id' => $gateway->gateway_id,
            ];
        }
    
        return response()->json([
            'status' => 1,
            'message' => 'Gateway data retrieved successfully.',
            'gateway_data' => $gateway_data,
        ]);
    }
    
    public function check_coupons_agent(Request $request)
    {
        // Validate required inputs
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
    // dd($coupon_data);
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

    private $chunk_size = 10;
    private $per_page = 5;
    
    public function get_all_orders(Request $request)
    {
        $page     = $request->page;
        $admin_id = $request->admin_id;
        
        $total_orders = DB::table('orders')
            ->where('admin_id',$admin_id)
            ->select('order_id')
            ->count();
            
        $total_pages = ceil($total_orders / $this->per_page);
        
        // Get ordered order IDs
        $table_data = DB::table('orders')
            ->select('order_id')
            ->where('admin_id',$admin_id)
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $this->per_page)
            ->take($this->per_page)
            ->get();
            
        if ($table_data->isEmpty()) {
            return response()->json([
                'status' => 0,
                'message' => 'Records not found.',
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_records' => $total_orders,
                    'per_page' => $this->per_page
                ]
            ]);
        }

        $app_key_data = DB::table('app_keys')->first();
        if (!$app_key_data) {
            return response()->json([
                'status' => 0,
                'message' => 'API credentials not found.'
            ]);
        }

        // Store order IDs in the original order
        $order_sequence = $table_data->pluck('order_id')->toArray();
        $processed_orders = array_fill_keys($order_sequence, null);
        
        // Process orders in chunks
        $chunks = $table_data->chunk($this->chunk_size);
        
        foreach ($chunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $order) {
                $promises[$order->order_id] = $this->createOrderViewPromise(
                    $order->order_id, 
                    $app_key_data->app_key, 
                    $app_key_data->app_secret
                );
            }
            
            $responses = Promise\Utils::settle($promises)->wait();
            
            foreach ($responses as $order_id => $response) {
                if ($response['state'] === 'fulfilled') {
                    $response_data = json_decode($response['value']->getBody(), true);
                    if (isset($response_data['response_code']) && $response_data['response_code'] === '100') {
                        $processed_orders[$order_id] = $this->format_order_data($response_data);
                    }
                }
            }
        }

        // Filter out null values and maintain order
        $final_orders = array_values(array_filter($processed_orders));

        return response()->json([
            'status' => 1,
            'message' => 'Successfully retrieved order data.',
            'data' => $final_orders,
            'pagination' => [
                'current_page' => (int)$page,
                'total_pages' => $total_pages,
                'total_records' => $total_orders,
                'per_page' => $this->per_page,
                'has_next_page' => $page < $total_pages,
                'has_previous_page' => $page > 1
            ]
        ]);
    }
    private function createOrderViewPromise($order_id, $app_key, $app_secret)
    {
        $client = new Client([
            'base_uri' => 'https://whitelabelmd.sticky.io',
            'auth' => [$app_key, $app_secret]
        ]);

        return $client->postAsync('/api/v1/order_view', [
            'json' => [
                'order_id' => [$order_id],
                'return_variants' => 1
            ]
        ]);
    }

    private function format_order_data($data)
    {
        $formatted_data = [
            'customer_info' => [
                'order_id' => $data['order_id'] ?? '',
                'first_name' => $data['billing_first_name'] ?? '',
                'last_name' => $data['billing_last_name'] ?? '',
                'email_address' => $data['email_address'] ?? '',
            ],
            'products' => []
        ];

        if (!empty($data['products'])) {
            foreach ($data['products'] as $product) {
                $formatted_data['products'][] = [
                    'product_id' => $product['product_id'] ?? '',
                    'sku' => $product['sku'] ?? '',
                    'price' => $product['price'] ?? '',
                    'product_qty' => $product['product_qty'] ?? '',
                    'name' => $product['name'] ?? '',
                    'billing_model' => [
                        'id' => $product['billing_model']['id'] ?? '',
                        'name' => $product['billing_model']['name'] ?? '',
                        'description' => $product['billing_model']['description'] ?? ''
                    ]
                ];
            }
        }

        return $formatted_data;
    }

    public function get_user_orders(Request $request)
    {
        $email = $request->email;
        $page = $request->page;
    
        if (!empty($email) && !empty($page)) {
            $order_data = $this->order_find($email, $page);
    
            if (empty($order_data['data'])) {
                return response()->json([
                    'status' => 0,
                    'message' => "No orders found for the provided email."
                ]);
            }
    
            return response()->json([
                'status' => 1,
                'message' => "Successfully retrieved order data.",
                'data' => $order_data['data'],
                'pagination' => [
                    'current_page' => (int)$page,
                    'total_pages' => $order_data['total_pages'],
                    'total_records' => $order_data['total_orders'],
                    'per_page' => $this->per_page,
                    'has_next_page' => $page < $order_data['total_pages'],
                    'has_previous_page' => $page > 1
                ]
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => "Please fill out all the required details."
            ]);
        }
    }
    
    private function order_find($email, $page)
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
                "end_date" => "03/03/2029",
                "criteria" => ["email" => $email],
                "return_type" => "order_view"
            ];
    
            $response = Http::withBasicAuth($app_key, $app_secret)
                ->post('https://whitelabelmd.sticky.io/api/v1/order_find', $payload);
    
            $responseData = $response->json();
            
            if ($response->successful() && isset($responseData['data'])) {
                $orders = $responseData['data'];
    
                $filteredOrders = array_filter($orders, function ($order) {
                    return $order['order_status'] !== "7";
                });
    
                $total_orders = count($filteredOrders);
                $total_pages = ceil($total_orders / $this->per_page);
    
                usort($filteredOrders, function ($a, $b) {
                    return strtotime($b['time_stamp']) - strtotime($a['time_stamp']);
                });
                
                $paginatedOrders = array_slice($filteredOrders, ($page - 1) * $this->per_page, $this->per_page);
                
    
                $orderDetails = array_map(function ($order) {
                    $main_product_id = $order['main_product_id'];
                    $img_video = "";
                    $product_data = DB::table('product')->where('sticky_product_id', $main_product_id)->first();
                    
                    if ($product_data) {
                        $product_image = DB::table('product_image')->where('product_id', $product_data->product_id)->first();
                        if ($product_image) {
                            $img_video = URL("/public/assets/product_img/" . $product_image->img_video);
                        }
                    }
    
                    return [
                        'order_id' => $order['order_id'] ?? null,
                        'time' => $order['time_stamp'] ?? null,
                        'order_status' => $order['order_status'] ?? null,
                        'on_hold' => $order['on_hold'] ?? null,
                        'tracking_number' => $order['tracking_number'] ?? null,
                        'product_img' => $img_video,
                        'product_name' => $product_data->product_name ?? null,
                        'product_price' => $order['order_total'] ?? null,
                        'product_sku' => $product_data->product_sku ?? null,
                        'product_category_name' => $product_data->product_category_name ?? null,
                        'product_description' => $product_data->product_description ?? null,
                    ];
                }, $paginatedOrders);
    
                usort($orderDetails, function ($a, $b) {
                    return strtotime($b['time']) <=> strtotime($a['time']);
                });
    
                return [
                    'data' => $orderDetails,
                    'total_orders' => $total_orders,
                    'total_pages' => $total_pages
                ];
            }
        }
    
        return ['data' => [], 'total_orders' => 0, 'total_pages' => 0];
    }

    

    
}
