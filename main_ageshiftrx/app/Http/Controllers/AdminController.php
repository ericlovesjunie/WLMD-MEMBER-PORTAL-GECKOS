<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Classes\Helper;
use App\Mail\ForgotPassword;
// use file;
use Illuminate\Support\Collection;
use Session;
use Stripe;
use Mail;
use Carbon;
use DateTime;
use DatePeriod;
use DateInterval;
use Http;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
class AdminController extends Controller
{

    public function admin_login(Request $request)
    {
        $email = $request->email;
        $password = $request->password;
    
        if (empty($email) || empty($password)) 
        {
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    
        // Check if email exists in either admin_login or sub_admin tables
        $adminLogin = DB::table('admin_login')->where('email', $email)->first();
    
        if ($adminLogin ) {
            // Check password for both tables
            if (($adminLogin && Hash::check($password, $adminLogin->password))) {
    
                // Determine which table the user belongs to
               
                $user = $adminLogin ;
                
                // dd($user);
                // Store necessary information in the session
                session()->put('AdminEmail', $user->email);
                session()->put('AdminName', $user->name);
                session()->put('IsAdmin', $user->is_admin);
                session()->put('AdminPassword', md5($password));  // Note: Storing passwords like this is not recommended
                session()->put('AdminID', $adminLogin->id);
            
    
                session()->flash('success', 'Welcome! You have Successfully logged in..!!');
                return redirect()->route('home');

            } 
            else 
            {
                session()->flash('error', 'Your password is incorrect. Please try again with the correct password.');
                return back();
            }
        } 
        else 
        {
            session()->flash('error', 'The email you entered is invalid. Please enter a valid email address and try again.');
            return back();
        }
    }
    
    public function logoutadmin(Request $request)
    {
        //  dd('d');
        // dd(session('AdminID'));

        session()->flush();

        return redirect('');

        Redirect::back();

        return Redirect::to('login');

    }

    public function home(Request $request)
    {
        // $table_data = DB::table('age')->where('status','=',1)->get();

        $product = DB::table('product')->where('status',1)->count();
        

        return view('home')->with('product',$product);
    }


    public function reset_password(Request $request,$email)
    {
        $data = DB::table('user')->where('email',$email)->first();

        // dd($data);
        // Check if the user exists
        if (!$data) {
            // If user doesn't exist, redirect back with an error message
            return view('not_found');
        }

        // If user exists, return the view with the user data
        return view('reset_password')->with('data', $data);
    }

    public function password_change(Request $request)
    {
        $user_id          = $request->user_id;
        $new_password     = $request->password;
        $confirm_password = $request->confirm_password;
    
        // dd($request);
        if ($user_id && $new_password && $confirm_password) {
            if ($new_password === $confirm_password) {
                $isValid = (strlen($new_password) >= 8);
    
                if ($isValid) {
                    // Retrieve user data from the database
                    $user_data = DB::table('user')->where('user_id', $user_id)->first();
                    if (!$user_data) {
                        session()->flash('error', 'User not found.');
                        return back();
                    }
    
                    // Prepare data for the external API request
                    $payload = [
                        "email" => $user_data->email,
                        "member_temp_password" => $user_data->temp_password,
                        "member_new_password" => $new_password,
                    ];
                    // dd($payload);
                    // Call the external API to update the member password
                    $app_key_data = DB::table('app_keys')->first();
    
                    $app_key    = $app_key_data->app_key;
                    $app_secret = $app_key_data->app_secret;
    
                    $response = Http::withBasicAuth($app_key, $app_secret)
                        ->post('https://whitelabelmd.sticky.io/api/v1/member_reset_password', $payload);
                    $responseData = $response->json();
    
                    if ($response->successful() && $responseData['response_code'] == 100) {
                        // Update password in the local database
                        DB::table('user')->where('user_id', $user_id)->update([
                            'password' => Hash::make($new_password),
                            'temp_status' =>0,
                            'updated_at' => now(),
                        ]);
    
                        session()->flash('success', 'Your password has been successfully changed and updated on the external service.');
                        return back();
                    } else {
                        if (isset($responseData['response_code']) && $responseData['response_code'] === "4009") {
                            session()->flash('error', 'The new password cannot be the same as the old password. Please choose a different password.');
                        } else {
                            session()->flash('error', 'Password updated locally, but failed to update on the external service.');
                        }
                        return back();
                    }
                } else {
                    session()->flash('error', 'Password must be at least 8 characters long.');
                    return back();
                }
            } else {
                session()->flash('error', 'Please ensure that both passwords match.');
                return back();
            }
        } else {
            session()->flash('error', 'Please fill out all the required details in the form before submitting it.');
            return back();
        }
    }
    
    public function product(Request $request)
    {
        $table_data = DB::table('product')
                        // ->join('category','category.category_id','=','product.category_id')
                        // ->select('category.category_id','category.category_name','product.*')

                        ->orderBy('product.created_at', 'desc')
                        // ->where('product.status','=',1)
                        ->get();


        if (!session()->has('SubAdminID'))
        {
            return view('product')->with('table_data',$table_data);
    
        }
        else
        {
            return back();
        }
        
    }

    public function add_sub_admin(Request $request)
    {
        $name      = $request->name;
        $email     = $request->email;
        $phone     = $request->phone;
        $alt_phone = $request->alt_phone;
        $password  = $request->password;
        $profile   = $request->file('profile');
        
        // dd($request);
        if($name !="" && $email !="" && $phone !="" && $alt_phone !="" && $password !="" )
        {
            if(DB::table("sub_admin")->where('phone',$phone)->count() == 0)
            {
                if(filter_var($email, FILTER_VALIDATE_EMAIL))
                {
                    if(DB::table("sub_admin")->where('email',$email)->count() == 0)
                    {
                        if ($request->hasFile('profile')) 
                        {
                          
                                $profile = $request->file('profile');
                                DB::table('sub_admin')->insertGetId([
                                    'name'      => $name,
                                    'email'     => $email,
                                    'phone'     => $phone,
                                    'alt_phone' => $alt_phone,
                                    'password'  => Hash::make($request->password),
                                    'profile'   => Helper::savesub_admin($profile),
                                    'created_at'=> date("Y-m-d H:i:s"),
                                ]);
                          
                            session()->flash('success', 'Adnin add Successfully..!!');
                            return back();
    
                        } 
                        else 
                        {
                            session()->flash('error', 'Please Add Admin Profile..!!');
                            return back();
                        }
                    }
                    else
                    {
                        session()->flash('error', 'The email you entered is already registered , Please enter different email..!!');
                        return back();
                    }
                }
                else
                {
                    session()->flash('error', 'Your email is incorrect. Please try again with the correct email..!!');
                    return back();
                }
            }
            else
            {
                session()->flash('error', 'The phone no you entered is already registered , Please enter different phone no..!');
                return back();
            }
        }
        else
        {
            // $result['status'] = 0;
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    // public function site(Request $request)
    // {
    //     if (session()->has('SubAdminID') )
    //     {
    //         $table_data = DB::table('site')
    //                     ->select('site.*','sub_admin.name')
    //                     ->join('sub_admin','sub_admin.sub_admin_id','=','site.sub_admin_id')
    //                     ->orderBy('site.created_at', 'desc')
    //                     ->where('site.sub_admin_id',session('SubAdminID'))
    //                     ->where('site.status','=',1)->get();
           
    //     }
    //     else
    //     {
    //         $table_data = DB::table('site')
    //                     ->select('site.*','sub_admin.name')
    //                     ->join('sub_admin','sub_admin.sub_admin_id','=','site.sub_admin_id')
    //                     ->orderBy('site.created_at', 'desc')
    //                     ->where('site.status','=',1)->get();
            
    //     }
     

    //     return view('site')->with('table_data',$table_data);
    // }

    public function add_new_product(Request $request)
    {
        $table_data = DB::table('category')->get();
        $site_data = DB::table('site')->get();
        //                 ->select('template.*','template_image.img_video')
        //                 ->join('template_image','template_image.template_id','=','template.template_id')
        //                 ->groupby('template.template_id')
        //                 ->where('template.status',1)->get();
// dd($table_data);
        return view('add_new_product')->with('table_data',$table_data)->with('site_data',$site_data);
    }

    public function add_site_by_ad(Request $request)
    {
        $site_name    = $request->site_name;
        $template_id  = $request->template_id;
        $sub_admin_id = $request->sub_admin_id;
        $heading      = $request->heading; // Assuming you meant 'heading' instead of 'heding'
        $logo         = $request->file('logo');
        dd($request);
        if ($site_name !='' && $heading !='') 
        {
            if (DB::table('site')->where('site_name', $site_name)->count() == 0) 
            {
                if ($request->hasFile('logo')) 
                {
                    // Save the logo using a hypothetical Helper method
                    $logoPath = Helper::savesite_logo($logo);

                    $slug = Str::slug($site_name);

                    $template  = DB::table('template')->select('template_name')->where('template_id', $template_id)->first();
                    $template_name = $template->template_name;
                    // dd($template_name);
                    if (DB::table('site')->where('slug', $slug)->exists()) {
                        session()->flash('error', 'The generated slug is already in use, please try a different site name.');
                        return back();
                    }

                    // Create a new folder in resources/views/$name
                    $newSitePath = public_path("site/$site_name");
                    if (File::exists($newSitePath)) 
                    {
                        session()->flash('error', 'The template name is already in use, please try a different template name.');
                        return back();
                    }
                    else
                    {
                        File::makeDirectory($newSitePath, 0755, true);

                    }
                    // Insert the new site into the database
                    $siteId = DB::table('site')->insertGetId([
                        'site_name'    => $site_name,
                        'sub_admin_id' => $sub_admin_id,
                        'template_id'  => $template_id,
                        'heading'      => $heading,
                        'slug'         => $slug,
                        'logo'         => $logoPath,
                        'created_at'   => now(),
                    ]);

                 

                    $templatePath = public_path("site/$template_name");
                    if (File::exists($templatePath)) {
                        File::copyDirectory($templatePath, $newSitePath);
                    }

                    session()->flash('success', 'Site added successfully..!!');
                    return redirect()->route('site');
                    // return back();
                } 
                else 
                {
                    session()->flash('error', 'Please add a site logo..!!');
                    return back();
                }
            } 
            else 
            {
                session()->flash('error', 'Then Site name you entered is already registered, please enter a different name..!');
                return back();
            }
        } 
        else 
        {
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    public function get_template(Request $request)
    {
        $table_data = DB::table('template')->orderBy('created_at', 'desc')->where('status',1)->get();

        return view('template')->with('table_data',$table_data);
    }

    public function add_new_template(Request $request)
    {
        $table_data = DB::table('template')->where('status',1)->get();

        return view('add_new_template')->with('table_data',$table_data);
    }
    public function add_product(Request $request)
    {
        $product_name        = $request->product_name;
        $product_description = $request->product_description;
        $product_price       = $request->product_price;
        $form_id             = $request->form_id;
        $form_url            = $request->form_url;
        $category_id         = $request->category_id;
        $id                  = $request->id;
        $site_id             = $request->site_id;
        $sticky_product_id   = $request->sticky_product_id;
        $img_video           = $request->file('img_video');


        // dd($request);
        if ($product_name != "" && $product_price !="" && $product_description != ""  && $sticky_product_id !="" && $id !="") {
            if ($request->hasFile('img_video')) {

                    $slug = Str::slug($product_name);

                    if (DB::table('product')->where('product_slug', $slug)->exists()) {
                        session()->flash('error', 'The generated slug is already in use, please try a different site name.');
                        return back();
                    }

                    $data_tb = DB::table('site')->where('id',$id)->first();
                    // dd($data_tb);
                    // $checkout_url ="";
                    if($data_tb->site_url)
                    {
                        $checkout_url = $data_tb->site_url.'/'.$sticky_product_id;
                         // dd($checkout_url);
                        $product_id = DB::table('product')->insertGetId([
                            'product_name'        => $product_name,
                            'category_id'         => $category_id,
                            'product_price'       => $product_price,
                            'form_id'             => $form_id,
                            'form_url'            => $form_url,
                            'site_id'             => $data_tb->id,
                            'main_site_id'        => $data_tb->site_id,
                            'checkout_url'        => $checkout_url,
                            'sticky_product_id'   => $sticky_product_id,
                            'product_description' => $product_description,
                            'product_slug'        => $slug,
                            'created_at'          => now(),
                        ]);
                        
                        $checkout_url = $data_tb->site_url.'/'.$product_id;

                        DB::table('product')->where('product_id',$product_id)->update([
                            'checkout_url'        => $checkout_url,
                        ]);


                        // Save the img_video files
                        foreach ($img_video as $image) {
                            $mimeType = $image->getMimeType();
                            $fileType = explode('/', $mimeType)[0];

                            DB::table('product_image')->insert([
                                'product_id' => $product_id,
                                'type'        => $fileType,
                                'img_video'   => Helper::product_img($image),
                                'created_at'  => now(),

                            ]);
                        }

                        session()->flash('success', 'Product added successfully..!!');
                        return redirect()->route('product');
                    }
                    else
                    {
                        session()->flash('error', 'This Site data not exit..!!');
                        return back();
                    }

                   

                // return redirect()->route('get_template');
       
            } else {
                session()->flash('error', 'Please select both product image and zip file..!!');
                return back();
            }
        } else {
            session()->flash('error', 'Please enter product name..!!');
            return back();
        }
    }

    public function sub_admin_details(Request $request,$sub_admin_id)
    {
        // $sub_admin_id = $request-> sub_admin_id;
        $admin_site = DB::table('site')
                    ->select('site.*','sub_admin.name')
                    ->join('sub_admin','sub_admin.sub_admin_id','=','site.sub_admin_id')
                    ->orderBy('site.created_at', 'desc')
                    ->where('site.sub_admin_id',$sub_admin_id)
                    ->where('site.status','=',1)
                    ->get();

        $admin_data = DB::table('sub_admin')->where('sub_admin_id',$sub_admin_id)->first();

        return view('sub_admin_details')->with('admin_site',$admin_site)->with('admin_data',$admin_data);

    }

    public function user(Request $request)
    {
        $table_data = DB::table('user')
                    ->join('user_address','user_address.user_id','=','user.user_id')
                    ->select('user.*','user_address.address','user_address.address2','user_address.zip_code','user_address.city_name','user_address.state_name')
                    ->orderBy('user.created_at', 'desc')
                    ->where('user.status',1)->get();

        return view('user')->with('table_data',$table_data);
    }

    public function submissions(Request $request)
    {
        $table_data = DB::table('submissions')
                    ->join('user','user.user_id','=','submissions.user_id')
                    ->select('submissions.*','user.user_id','user.first_name')
                    ->where('submissions.status','=','ACTIVE')->get();
// dd($table_data);
        return view('submissions')->with('table_data',$table_data);
    }

    public function user_details(Request $request,$id)
    {
        $table_data = DB::table('user')
                ->join('user_address','user_address.user_id','=','user.user_id')
                ->select('user.*','user_address.address','user_address.address2','user_address.zip_code','user_address.city_name','user_address.state_name')
                ->where('user.status',1)
                ->where('user.user_id','=',$id)
                ->first();
        $tab_data = DB::table('re_fileupload_request')->where('user_id', $id)->first();
        // dd($tab_data);
        if($tab_data)
        {
            if($tab_data->status == 1)
            {
                $is_upload = 0;
            }
            else
            {
                $is_upload = 1;

            }
        }
        else
        {
            $is_upload = 1;
        }
        $webhook = DB::table('webhook')->where('user_id',$id)->first();
            $submissions = DB::table('submissions')->where('user_id',$id)
                    // ->where('user_id','=',$id)
                    ->first();   
                    if ($submissions && !empty($submissions->answers)) {
                        $submissions->answers = json_decode($submissions->answers, true);
                    }
                    else
                    {
                        $webhook = DB::table('webhook')->where('user_id',$id)->first();

                        if($webhook)
                        {

                            $webhook->webhook_data = json_decode($webhook->webhook_data, true);
                        }
                        else
                        {
                            $webhook ="";
                        }

                    }

// dd($webhook);
                    return view('user_details')
                    ->with('table_data', $table_data)
                    ->with('submissions', $submissions)
                    ->with('is_upload', $is_upload)
                    ->with('webhook', $webhook);
    }

    public function category(Request $request)
    {
        $table_data = DB::table('category')
                   ->get();
        // dd($table_data);
        return view('category')->with('table_data',$table_data);
    }

    public function add_category(Request $request)
    {
        $category_name      = $request->category_name;
        // dd($request);
        if($category_name !="")
        {
            DB::table('category')->insertGetId([
                'category_name'      => $category_name,
                'created_at'         => date("Y-m-d H:i:s"),
            ]);
                          
            session()->flash('success', 'Category add Successfully..!!');
            return back();
        }
        else
        {
            // $result['status'] = 0;
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    public function patients(Request $request)
    {
        $table_data = DB::table('patients')
                    ->where('user_id','!=',"")
                    ->where('status',1)
                    ->latest()
                   ->get();
        // dd($table_data);    
        return view('patients')->with('table_data',$table_data);
    }

    public function patients_details(Request $request,$id)
    {

            $patients = DB::table('patients')->where('p_id',$id)
                    // ->where('user_id','=',$id)
                    ->first();   
                    if ($patients && !empty($patients->answers)) {
                        $patients->answers = json_decode($patients->answers, true);
                    }
        dd($patients);
        return view('user_details')->with('patients',$patients);
    }

    public function edit_product(Request $request,$id)
    {
        $table_data = DB::table('category')->get();
        $product_image = DB::table('product_image')->where('product_id',$id)->get();

        $table_data_pro = DB::table('product')->where('product_id',$id)->where('status',1)->first();
        $site_data = DB::table('site')->get();
        // dd($table_data_pro);
        return view('edit_product')->with('product_image',$product_image)->with('table_data',$table_data)->with('table_data_pro',$table_data_pro)->with('site_data',$site_data);

    }

    public function update_product(Request $request,$product_id)
    {
        $product_name        = $request->product_name;
        $product_description = $request->product_description;
        $product_price       = $request->product_price;
        $form_id             = $request->form_id;
        $form_url            = $request->form_url;
        $category_id         = $request->category_id;
        $id                  = $request->id;
        $sticky_product_id   = $request->sticky_product_id;
        $uniq_id             = $request->uniq_id;
        $sku                = $request->sku;


        // dd($request);
        if ($product_name != "" && $product_price !="" && $product_description != "" && $sticky_product_id !="") {
            if(DB::table("product")->where('uniq_id', $uniq_id)->where('product_id','!=',$product_id)->count() == 0)
            {
                // if ($request->hasFile('img_video')) {

                $slug = Str::slug($product_name);
                $data_tb = DB::table('site')->where('id',$id)->first();
                // if (DB::table('product')->where('product_slug', $slug)->exists()) {
                //     session()->flash('error', 'The generated slug is already in use, please try a different site name.');
                //     return back();
                // }
                $data_tb = DB::table('site')->where('id',$id)->first();
                // dd($data_tb);
                // $checkout_url ="";
                if($data_tb->site_url)
                {
                    $checkout_url = $data_tb->site_url.''.$uniq_id;
                    // dd($checkout_url);
                    $product_id = DB::table('product') 
                    ->where('product_id', $product_id)
                    ->update([
                        'product_name'        => $product_name,
                        'category_id'         => $category_id,
                        'product_price'       => $product_price,
                        'form_id'             => $form_id,
                        'form_url'            => $form_url,
                        'product_description' => $product_description,
                        'sticky_product_id'   => $sticky_product_id,
                        'checkout_url'        => $checkout_url,
                        'product_slug'        => $slug,
                        'uniq_id'             => $uniq_id,
                        'site_id'             => $id,
                        'product_sku'         => $sku,
                        'main_site_id'        => $data_tb->site_id,
                        'created_at'          => now(),
                    ]);

                   


                        session()->flash('success', 'Product Update successfully..!!');
                        return redirect()->route('product');
                }
                else
                {
                    session()->flash('error', 'This Site data not exit..!!');
                    return redirect()->route('product');
                }
            }
            else
            {
                session()->flash('error', 'This Uniq id is exit..!!');
                return redirect()->route('product');
            }
            
                 

       
         
        } else {
            session()->flash('error', 'Please enter product name..!!');
            return redirect()->route('product');
        }
    }

    public function add_product_image(Request $request)
    {
        $img_video    = $request->file('img_video');
        $product_id   = $request->product_id;

        if ($request->hasFile('img_video')) 
        {
            $mimeType = $img_video->getMimeType();
            $fileType = explode('/', $mimeType)[0];
        //   $img_video = $request->file('img_video');

                DB::table('product_image')->insert([
                    'product_id'    => $product_id,
                    'img_video'  => Helper::product_img($img_video),
                    'type'        => $fileType,
                    'created_at'  => now(),

                ]);

            session()->flash('success', 'Image add Successfully..!!');

        } 
        else 
        {
            session()->flash('error', 'Please Select Image..!!');
        }
        return redirect()->back();
    }

    public function edit_product_img(Request $request, $id)
    {

        $img_video = $request->file('img_video');
            // dd($request);
        if ($request->hasFile('img_video')) 
        {
            // if (Helper::isFileImage(Helper::getExtension($img_video)) == 1) 
            // {
                $mimeType = $img_video->getMimeType();
                $fileType = explode('/', $mimeType)[0];

                $img_video = $request->file('img_video');
                // dd($id);
                DB::table('product_image')->where('product_image_id',$id)->update([

                    'type'        => $fileType,
                    'img_video'   => Helper::product_img($img_video),
                    'updated_at'  => now(),

                ]);
            // }

            session()->flash('success', 'Image Update Successfully..!!');

        } 
        else 
        {

            session()->flash('error', 'Please Select Image..!!');
        }
        return redirect()->back();
    }

    public function delete_product_img(Request $request,$product_image_id)
    {
        // $product_id        = $request-> product_id;
        // dd($product_image_id);
        foreach(DB::table('product_image')->where('product_image_id',$product_image_id)->get() as $video)
        {
            unlink(public_path('assets/product_img/'.$video->img_video));
        }

        DB::table('product_image')->where('product_image_id', $product_image_id)->delete();

        session()->flash('success', 'product Image delete Successfully..!!');
        return redirect()->back();

    }

    public function webhook(Request $request)
    {
        $table_data = DB::table('webhook')

            ->latest()
            ->get()
            ->map(function ($item) {
                $webhookData = json_decode($item->webhook_data, true);
                $prettyData = $webhookData['pretty'] ?? '';
                
                // Extract Unique ID from the 'pretty' field
                preg_match('/Unique ID:(\w+)/', $prettyData, $matches);
                $uniqueId = $matches[1] ?? null;

                preg_match('/tracking_unid:(\w+)/', $prettyData, $tracking_unid_data);
                $tracking_unid = $tracking_unid_data[1] ?? null;

                preg_match('/What is your full name\?:([^,]+)/', $prettyData, $nameMatches);
                $fullName = trim($nameMatches[1] ?? '');

                preg_match('/continuation_id:([^,]+)/', $prettyData, $matches);
                $continuationId = trim($matches[1] ?? '');

                if($continuationId != '') 
                {
                    $u_id = $continuationId;
                }
                else
                {   
                    $u_id = $uniqueId;

                }
    
                return [
                    'webhook_id' => $item->webhook_id,
                    'unique_id' => $u_id,
                    // 'continuation_id' => $continuationId,
                    'fullName' => $fullName,
                    'form_id' => $webhookData['formID'] ?? null,
                    'submission_id' => $webhookData['submissionID'] ?? null,
                    'tracking_unid' => $tracking_unid,
                    'form_title' => $webhookData['formTitle'] ?? null,
                    'created_at' => $item->created_at,
                ];
            });
    // dd($table_data);
        return view('webhook')->with('table_data',$table_data);
    }
    
    // Helper function to safely get nested values
    protected function getValue($data, $key, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }


    public function order(Request $request)
    {
        $table_data = DB::table('orders')
                        ->join('user','user.user_id','=','orders.user_id')
                        ->select('user.first_name','user.last_name','orders.*')
                        ->where('orders.user_id','!=',"")
                        ->latest()
                        ->get();
        // dd($table_data);
        return view('order', compact('table_data'));

    }

    public function cases(Request $request)
    {
        $table_data = DB::table('cases')
                        ->join('patients','patients.patient_id','=','cases.patient_id')
                        ->select('patients.patient_id','patients.email','patients.first_name','cases.*')
                        ->latest()
                        ->get();
        // dd($table_data);
        return view('cases', compact('table_data'));

    }

    public function getCaseQuestions($case_id)
    {
        $accessToken = $this->create_tokens();
        $url = "https://api.mdintegrations.com/v1/partner/cases/{$case_id}/questions";

        $is_case = DB::table('cases')->where('case_id', $case_id)->first();
        $user_id = $is_case->user_id;

        $tab_data = DB::table('re_full_body_file_request')->where('user_id', $is_case->user_id)->first();
        if($tab_data)
        {
            if($tab_data->status == 1)
            {
                $is_upload = 0;
            }
            else
            {
                $is_upload = 1;

            }
        }
        else
        {
            $is_upload = 1;
        }
    
        $response = Http::withToken($accessToken)->get($url);
    
        if ($response->successful()) {
            $data = $response->json();
    
            // Process the data as needed
            $formattedData = collect($data)->map(function ($item) {
                return [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ];
            });
            // dd($user_id);
    
            return view('cases_details', [
                'questions' => $formattedData,
                'is_upload' => $is_upload,
                'is_case' => $is_case,
            ]);
        } else {
            return response()->json(['error' => 'Failed to fetch data'], 500);
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


    public function add_category_product(Request $request,$id)
    {
        // dd($id);
        $table_data = DB::table('category_product')->where('category_id',$id)->get();

        return view('add_category_product')->with('table_data',$table_data)->with('category_id',$id);

    }

    public function add_new_category_product(Request $request)
    {
        $category_product_name      = $request->category_product_name;
        $category_id                = $request->category_id;
        // dd($request);
        if($category_product_name !="")
        {
            DB::table('category_product')->insertGetId([
                'category_product_name' => $category_product_name,
                'category_id'           => $category_id,
                'created_at'            => date("Y-m-d H:i:s"),
            ]);
                          
            session()->flash('success', 'Category product add Successfully..!!');
            return back();
        }
        else
        {
            // $result['status'] = 0;
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    public function add_main_category_product(Request $request,$id)
    {

        // dd($id);

        $product_data = DB::table('category_product')
                    ->join('category','category.category_id','=','category_product.category_id')
                    ->join('product','product.product_category_name','=','category.category_name')
                    ->select('category.category_name','category_product.category_id','category_product.category_product_id','product.product_category_name',
                        'product.product_id','product.product_name')
                    ->where('category_product.category_product_id','=',$id)
                    ->get();

                    // dd($product_data);


        $table_data = DB::table('category_product_data')
                        ->join('product','product.product_id','=','category_product_data.product_id')
                        ->select('product.product_id','product.sticky_product_id','product.product_name','category_product_data.*')
                        ->where('category_product_data.category_product_id',$id)->get();
            // dd($table_data);
        return view('add_main_category_product')->with('table_data',$table_data)->with('category_product_id',$id)->with('product_data',$product_data);

    }

    public function edit_main_category_product(Request $request)
    {
        $category_product_data_id = $request ->category_product_data_id;
        $index                    = $request ->index;
// dd($request);
        if($index !="" && $category_product_data_id !="")
        {
            DB::table('category_product_data')->where('category_product_data_id',$category_product_data_id)->update([
                'index'      => $index,
                'updated_at' => now(),
            ]);

            session()->flash('success', 'Category product update Successfully..!!');
            return back();
        }
        else
        {
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }


        // $table_data = DB::table('category_product_data')->where('category_product_data_id',$id)->first();
        //     // dd($table_data);
        // return view('edit_main_category_product')->with('table_data',$table_data);

    }

    public function add_new_category_product_data(Request $request)
    {
        $category_product_id      = $request->category_product_id;
        $product_id               = $request->product_id;
        // dd($request);
        if($category_product_id !="" )
        {

            if(DB::table('category_product_data')->where('product_id', '=', $product_id)->count() == 0)
            {
                $is_db = DB::table('category_product_data')->where('category_product_id',$category_product_id)->count();

                if($is_db == 0)
                {
                    $index = 1 ;
                }
                else
                {
                    $index = $is_db + 1 ;

                }

                // dd($is_db);
                DB::table('category_product_data')->insertGetId([
                    'category_product_id'   => $category_product_id,
                    'product_id'            => $product_id,
                    'index'                 => $index,
                    'created_at'            => date("Y-m-d H:i:s"),
                ]);
                              
                session()->flash('success', 'Category product add Successfully..!!');
                return back();
                
            } 
            else 
            {
                session()->flash('error', 'The Product you entered is already exit..!!');
                return back();
               
            }

        }
        else
        {
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    public function send_update_file_re(Request $request)
    {
        $user_id = $request->user_id;

        $tab_data = DB::table('re_fileUpload_request')->where('user_id', $user_id)->first();

        if(DB::table('re_fileUpload_request')->where('user_id', $user_id)->count() == 0)
        {
            DB::table('re_fileUpload_request')->insert([
                'user_id'       => $user_id,
                'status'        => 1,
                'updated_at'    => now(),

            ]);
            $user_data = DB::table('user')->where('user_id', $user_id)->first();

            $email_sub = 'bgwhitelabel@gmail.com';
            $sender_email = 'info@levelupmeds.com';
            $data = ['frommail' => $email_sub, 'tomail' => $user_data->email, 'subject' => 'File Upload Request'];

            Mail::send('Mail.Re_upload',['email_sub' => $email_sub ,'sender_email' => $sender_email,'email' => $user_data->email], function ($message) use ($data)
            {
                $message->to($data['tomail']);
                $message->from($data['frommail']);
                $message->subject($data['subject']);
                       
            });
            session()->flash('success', 'File Request Send Successfully..!!');
            return back();
        }
        else
        {
            DB::table('re_fileUpload_request')->where('user_id', $user_id)->update([
                // 'user_id'       => $user_id,
                'status'        => 1,
                'updated_at'    => now(),

            ]);

            $user_data = DB::table('user')->where('user_id', $user_id)->first();

            $email_sub = 'bgwhitelabel@gmail.com';
            $sender_email = 'info@levelupmeds.com';
            $data = ['frommail' => $email_sub, 'tomail' => $user_data->email, 'subject' => 'File Upload Request'];

            Mail::send('Mail.Re_upload',['email_sub' => $email_sub,'sender_email' => $sender_email ,'email' => $user_data->email], function ($message) use ($data)
            {
                $message->to($data['tomail']);
                $message->from($data['frommail']);
                $message->subject($data['subject']);
                       
            });
            session()->flash('success', 'File Request Send Successfully..!!');
            return back();
        }
    }


    public function send_update_full_bodey_img(Request $request)
    {
        $user_id = $request->user_id;

        $tab_data = DB::table('re_full_body_file_request')->where('user_id', $user_id)->first();

        if(DB::table('re_full_body_file_request')->where('user_id', $user_id)->count() == 0)
        {
            DB::table('re_full_body_file_request')->insert([
                'user_id'       => $user_id,
                'status'        => 1,
                'updated_at'    => now(),

            ]);
            $user_data = DB::table('user')->where('user_id', $user_id)->first();

            $email_sub = 'bgwhitelabel@gmail.com';
            $sender_email = 'info@levelupmeds.com';
            $data = ['frommail' => $email_sub, 'tomail' => $user_data->email, 'subject' => 'File Upload Request'];

            Mail::send('Mail.Full_body_file',['email_sub' => $email_sub ,'sender_email' => $sender_email,'email' => $user_data->email], function ($message) use ($data)
            {
                $message->to($data['tomail']);
                $message->from($data['frommail']);
                $message->subject($data['subject']);
                       
            });
            session()->flash('success', 'File Request Send Successfully..!!');
            return back();
        }
        else
        {
            DB::table('re_full_body_file_request')->where('user_id', $user_id)->update([
                // 'user_id'       => $user_id,
                'status'        => 1,
                'updated_at'    => now(),

            ]);

            $user_data = DB::table('user')->where('user_id', $user_id)->first();

            $email_sub = 'bgwhitelabel@gmail.com';
            $sender_email = 'info@levelupmeds.com';
            $data = ['frommail' => $email_sub, 'tomail' => $user_data->email, 'subject' => 'File Upload Request'];

            Mail::send('Mail.Full_body_file',['email_sub' => $email_sub,'sender_email' => $sender_email ,'email' => $user_data->email], function ($message) use ($data)
            {
                $message->to($data['tomail']);
                $message->from($data['frommail']);
                $message->subject($data['subject']);
                       
            });
            session()->flash('success', 'File Request Send Successfully..!!');
            return back();
        }
    }

    public function site(Request $request)
    {
        $table_data = DB::table('site')->where('status','=',1)->get();

        // $table_data  = DB::table('site')->where('status',1)->count();
        

        return view('site')->with('table_data',$table_data);
    }

    public function error_page(Request $request)
    {
        return view('error_page');
    }

    public function card_declined(Request $request,$product_id,$user_id)
    {
        return view('card_declined')->with('product_id', $product_id)->with('user_id', $user_id);
    }
    public function site_product(Request $request,$id)
    {
        $table_data = DB::table('product')->orderBy('created_at', 'desc')->where('site_id',$id)->get();

        return view('site_product')->with('table_data', $table_data);
    }

    public function add_order(Request $request)
    {
        $table_data = DB::table('product')
        // ->join('category','category.category_id','=','product.category_id')
        // ->select('category.category_id','category.category_name','product.*')

        ->orderBy('product.created_at', 'desc')
        // ->where('product.status','=',1)
        ->get();
// dd($table_data);
        return view('add_order')->with('table_data', $table_data);
    }

    public function add_new_order(Request $request,$product_id)
    {
// dd($table_data);
        return view('add_new_order')->with('product_id', $product_id);
    }

    public function add_site(Request $request)
    {
        // $site_name      = $request->site_name;
        $site_url       = $request->site_url;
        $site_dns       = 111;
        $campaign_id    = $request->campaign_id;
        $is_site        = 1;
        $site_key       = $request->site_key;
        $site_secret    = $request->site_secret;
        $logo           = $request->file('logo');
        // $site_id         = $request->site_id;
        // dd($request);
           // Ensure the URL ends with a trailing slash

        if ($request->hasFile('logo')) 
        {
            // if (substr($site_url, -1) !== '/') {
            //     $site_url .= '/';
            // }
            if($site_url !="")
            {
                // if (!preg_match('/\?id=[^&]*$/', $site_url)) {
                //     session()->flash('error', 'The Site URL must contain "?id=" followed by a value. Please provide a valid URL.');
                //     return back();
                // }

                if (DB::table('site')->where('campaign_id', $campaign_id)->count() == 0) 
                {
                    $logoPath = Helper::savesite_logo($logo);
                    DB::table('site')->insertGetId([
                        // 'site_name'          => $site_name,
                        'site_url'           => $site_url,
                        'site_dns'           => $site_dns,
                        'campaign_id'        => $campaign_id,
                        'is_site'            => $is_site,
                        'site_key'           => $site_key,
                        'site_secret'        => $site_secret,
                        'site_logo'          => $logoPath,
                        // 'site_id'            => $site_id,
                        'created_at'         => date("Y-m-d H:i:s"),
                    ]);
                                  
                    session()->flash('success', 'site add Successfully..!!');
                    return back();
                }
                else
                {
                    session()->flash('error', 'Campaign Id you entered is already registered');
                    return back();
                }
            
            }
            else
            {
                // $result['status'] = 0;
                session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
                return back();
            }
        }
        else
        {
            session()->flash('error', 'Please Select Site logo..!!');
            return back();
        }

     
    }

    public function update_site(Request $request)
    {
        $site_name      = $request->site_name;
        $id             = $request->id;
        $site_url       = $request->site_url;
        $site_dns       = $request->site_dns;
        // dd($request);
        if($site_name !="" && $site_url !="")
        {
            DB::table('site')->where('id',$id)->update([
                'site_name'          => $site_name,
                'site_url'           => $site_url,
                'site_dns'           => $site_dns,
                'created_at'         => date("Y-m-d H:i:s"),
            ]);
                          
            session()->flash('success', 'site update Successfully..!!');
            return back();
        }
        else
        {
            // $result['status'] = 0;
            session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
            return back();
        }
    }

    public function checkout(Request $request)
    {
        // $table_data = DB::table('age')->where('status','=',1)->get();

        $checkout = DB::table('checkout')->where('status',1)->count();
        

        return view('checkout')->with('checkout',$checkout);
    }
    public function theme_colore(Request $request)
    {
        $table_data = DB::table('theme_colores')->get();
        

        return view('theme_colore')->with('table_data',$table_data);
    }

    public function add_theme_colore(Request $request)
    {
      
        $theme_colore    = $request->theme_colore;
        $theme_logo      = $request->file('theme_logo');
        $theme_favicon   = $request->file('theme_favicon');
   

        if ($request->hasFile('theme_logo')) 
        {
            if ($request->hasFile('theme_favicon')) 
            {
                if($theme_colore !="")
                {
                        $logoPath = Helper::savetheme_logo($theme_logo);
                        $favPath  = Helper::savetheme_favicon($theme_favicon);
                        DB::table('theme_colores')->insertGetId([
                            'theme_colore'       => $theme_colore,
                            'theme_logo'         => $logoPath,
                            'theme_favicon'      => $favPath,
                            'created_at'         => date("Y-m-d H:i:s"),
                        ]);
                                      
                        session()->flash('success', 'theme add Successfully..!!');
                        return back();
                 
                
                }
                else
                {
                    // $result['status'] = 0;
                    session()->flash('error', 'Please fill out all the required details in the form before submitting it..!!');
                    return back();
                }
            }
            else
            {
                session()->flash('error', 'Please Select Theme favicon..!!');
                return back();
            }
         
        }
        else
        {
            session()->flash('error', 'Please Select Theme logo..!!');
            return back();
        }

     
    }

    public function new_order(Request $request)
    {
        $product_data = DB::table('product')->get();
        

        return view('new_order', compact('product_data'));    
    }

   




    
}