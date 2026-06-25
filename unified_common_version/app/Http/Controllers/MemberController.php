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

    public function getOrderTimeline(Request $request)
    {
        $orderId = $request->order_id;
        $email   = $request->email;
        $site_id = $request->site_id;

        if (empty($site_id)) {
            return response()->json([
                'status' => 0,
                'message' => 'site_id required'
            ]);
        }

        if (empty($orderId)) {
            return response()->json([
                'status' => 0,
                'message' => 'order_id required'
            ]);
        }

        if (empty($email)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please provide a valid email.'
            ]);
        }

        // Verify Order + Email from Sticky
        $app_key_data = DB::table('app_keys')->first();

        $payload = [
            "order_id" => [$orderId],
            "return_variants" => 1
        ];

        $stickyResponse = Http::withBasicAuth(
            $app_key_data->app_key,
            $app_key_data->app_secret
        )->post(
            'https://whitelabelmd.sticky.io/api/v1/order_view',
            $payload
        );

        if (!$stickyResponse->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Unable to verify order.'
            ]);
        }

        $stickyData = $stickyResponse->json();

        $orderEmail = $stickyData['email_address'] ?? '';

        if (strtolower(trim($orderEmail)) !== strtolower(trim($email))) {
            return response()->json([
                'status' => 0,
                'message' => 'Email not matched.'
            ]);
        }

        // Timeline API Call
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode('whitelabelmd_test_qfBktNLL04XslmzVSXVm201qC3eKT498:XpNVgsZW1ZBrW9WZVMqvOoQGheMM2Ek0'),
            'authtoken'     => 'geckos-api-key=gokul428use'
        ])->get("https://stage-api.whitelabelmd.com/v1/site/{$site_id}/order/{$orderId}/order-timeline");

        if (!$response->successful()) {
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get order timeline'
            ]);
        }

        $apiData = $response->json()['data'] ?? [];

        // Aapka existing timeline code yaha rahega...
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'site_id'     => 'required|string',
            'order_id'    => 'required|string',
            'user'        => 'required|string',
            'email'       => 'required|string',
            'upload_name' => 'required|string',
            'file'        => 'required|file|mimes:png,jpg,jpeg,pdf'
        ]);

        $site_id = $request->site_id;
        if (empty($site_id)) {
            return response()->json([
                'status' => 0,
                'message' => 'site_id required'
            ]);
        }
        $email = $request->email;
        if (empty($email)) {
            return response()->json([
                'status' => 0,
                'message' => 'email required'
            ]);
        }

        try {

            /** 🔹 File from frontend */
            $file = $request->file('file');

            /** 🔹 File info */
            $fileName = $file->getClientOriginalName();
            $fileExt  = $file->getClientOriginalExtension();

            /** 🔹 Convert file → base64 */
            $fileContents = base64_encode(
                file_get_contents($file->getRealPath())
            );

            /** 🔹 Payload */
            $payload = [
                "user"          => $request->user,
                "upload_name"   => $request->upload_name,
                "file_name"     => $fileName,
                "file_ext"      => $fileExt,
                "file_contents" => $fileContents
            ];

            /** 🔹 API URL */
            $url = "https://api.whitelabelmd.com/client/site/{$request->site_id}/order/{$request->order_id}/upload-file";

            /** 🔹 API Call */
            $response = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'authtoken'    => 'geckos-api-key=gokul428use',
            ])->post($url, $payload);

            /** 🔹 Response JSON */
            $response_data = $response->json();

            // Optional Debug
            // dd($response->body());

            /** 🔹 If API response is null */
            if (!$response_data) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid API response',
                    'error'   => $response->body()
                ], 400);
            }

            /** 🔹 Failed response */
            if (
                isset($response_data['status']) &&
                $response_data['status'] == 'failed'
            ) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Upload failed',
                    'error'   => $response_data['message'] ?? 'Unknown error'
                ], 400);
            }

            /** 🔹 Success response */
            if ($response->successful()) {



                return response()->json([
                    'status'  => true,
                    'message' => 'Prescription uploaded successfully',
                    'data'    => $response_data
                ]);
            }

            /** 🔹 Other errors */
            return response()->json([
                'status'  => false,
                'message' => 'Upload failed',
                'error'   => $response->body()
            ], 400);

        } catch (\Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    private function order_find($email)
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
                    $product_description = $product_data->product_description;

                    // Remove the "BM:..." part (including any whitespace before it)
                    $clean_description = preg_replace('/\s*BM:\d+/', '', $product_description);
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
                        'product_description'   => $clean_description,
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

	$email = $request->email;

        if(empty($email))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please provide a valid email."
            ]);
        }

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
                $totalsBreakdown = $responseData['totals_breakdown'] ?? [];
                $coupon_discount_amount = $responseData['coupon_discount_amount'] ?? null;
                $order_status = $responseData['on_hold'] ?? [];

                $tracking_number = $responseData['tracking_number'] ?? null;
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

			$email = $responseData['email_address'] ?? null;

			if($email != $request->email)
                {
                    return response()->json([
                        'status' => 0,
                        'message' => "Email not matched."
                    ]);
                }

                        
                        $productDetails[] = [
                            'product_id' => $product['product_id'] ?? null,
                            'product_category_name' => $product_data->product_category_name,
                            'product_name' => $product['name'] ?? null,
                            'product_price' => $product['price'] ?? null,
                            'recurring_date' => $product['recurring_date'] ?? null,
                            'next_billing_date' => "0000-00-00",
                            // 'next_billing_date' => $product['recurring_date'] ?? null,
                            'sku' => $product['sku'] ?? null, // Example of adding more product fields
                            'quantity' => $product['product_qty'] ?? 0, // Ensure to capture the quantity as well
                            'time_stamp' => $time,
                            'totalsBreakdown' => $totalsBreakdown,
                            'coupon_discount_amount' => $coupon_discount_amount,
                            'time' => $time,
                            'order_id' => $order_id,
                            'order_status' => $order_status,
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
                            // 'data' => $responseData
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

	    $email = $request->email;
        if(empty($email))
        {
            return response()->json([
                'status' => 0,
                'message' => "Please provide a valid email."
            ]);
        }

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

	    if($email != $email_address)
            {
                return response()->json([
                    'status' => 0,
                    'message' => "Email not matched."
                ]);
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

    private function sendmagicEmail($email, $firstName)
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
    
    public function magic_link(Request $request)
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
    
    public function get_user_cart_products_v2(Request $request)
    {
        $email = $request->email;
        $order_id = $request->order_id;

$site_id = $request->site_id;
        if (empty($site_id)) {
            return response()->json([
                'status' => 0,
                'message' => 'site_id required'
            ]);
	}

        if (!empty($email)) {
//            $order_data = $this->order_find_v2($email);
  $order_data = $this->order_find_v2($email, $site_id);          
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

    private function order_find_v2($email, $site_id)
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
    
			$formData = $this->get_intake_form_id($order['order_id'], $site_id);
			// dd($formData);
                            $intakeFormId = $formData['intake_form_id'] ?? null;
                            $intakeStage = $formData['stage'] ?? null;
                            $continuationLink = $formData['continuation_link'] ?? null;

                            if($continuationLink != null)
                            {
                                $continuationLink = explode('?', $continuationLink);
                                $continuationLink = $continuationLink[1] ?? null;
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
    
                        //id newFormId is exit then check $intakeFormId this id else 
                        if($newFormId)
                        {
                            $formId = $newFormId;
                        }
                        else
                        {
                            $formId = $intakeFormId;
                        }
    
                            $webhookExists = DB::table('webhook')->where('order_id', $order['order_id'])
                                                                ->where('formId', $formId)
                                                                ->exists();
                                                            if ($webhookExists) {
                                                                $is_sub = 1; // If webhook exists, set is_sub to 0
                                                            }
                             $states_data = DB::table('states')->where('abbreviation',  $order['billing_state'])->first();
                             $state_name =  $states_data->name;
                             if($continuationLink == null)
                             {
                                $newFormId = null;
                             }
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
                                'form_id'               => $intakeFormId,
                                'form_id_new'           => $newFormId,
                                'form_url_new'          => $newFormId ? "https://forms.whitelabelmd.com/" . $newFormId."/?".$continuationLink : null,
                                'continuation_link'     => $continuationLink,
                                'therapy_group'         => $therapyGroup,
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

    private function get_intake_form_id($orderId, $site_id)
    {
        if (!$orderId) {
        return null;
        }

        // API call
        $response = Http::get("https://panel.whitelabelmd.com/wlmdbackend/api/client/site/{$site_id}/order-op/test/{$orderId}");
        $responseData = $response->json();
        // dd($responseData);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['data']['intake_form_id'])) {
                return $responseData['data']; // return only useful data as array
            }
        }

        return null;
    }
   
    










}
