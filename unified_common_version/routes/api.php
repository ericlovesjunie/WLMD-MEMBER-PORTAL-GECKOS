<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\OrderApicontroller;

/*

|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|

*/

Route::middleware('auth:api')->get('/user', function (Request $request) {

    return $request->user();
    

    
});

Route::post('user_logout',[ApiController::class,'user_logout']);
    // Route::middleware('auth.token')->group(function () {
        Route::post('get_product_details', [ApiController::class, 'get_product_details']);
        // Route::post('/createOrder_test', [OrderController::class, 'createOrder_test']);
        // Route::post('/anotherApi', [OrderController::class, 'anotherApiMethod']);
    // });
Route::prefix('v1')->middleware(['auth.key'])->group(function () {
    Route::get('Get_Products',         [OrderApicontroller::class, 'get_products_api']);
    Route::post('getOrderTimeline', [OrderApicontroller::class, 'getOrderTimeline']);
    Route::post('createOrder_recurring_offline_api', [OrderApicontroller::class, 'createOrder_recurring_offline_api']);

    Route::post('Get_Product_Details', [OrderApicontroller::class, 'get_product_details_new_api']);
    Route::post('Member_View',         [OrderApicontroller::class, 'member_view_new_api']);
    Route::post('Create_Order',        [OrderApicontroller::class, 'createOrder_api']);
    Route::post('Check_Coupons',       [OrderApicontroller::class, 'check_coupons_v2']);
    Route::post('Create_Order_Offline', [OrderApicontroller::class, 'createOrder_offline_api']);
});

     Route::post('add_submission_data',[ApiController::class,'add_submission_data']);

     Route::post('add_new_user',[ApiController::class,'add_new_user']);

     Route::post('user_login',[ApiController::class,'user_login']);

     Route::post('verify_email',[ApiController::class,'verify_email']);

     Route::post('fetchAndStoreStates',[ApiController::class,'fetchAndStoreStates']);


     
Route::post('get_product_details_v3', [ApiController::class, 'get_product_details_v3']);

     Route::post('cheke_state',[ApiController::class,'cheke_state']);

     Route::post('create_or_search_patients',[ApiController::class,'create_or_search_patients']);


     Route::post('chekelin',[ApiController::class,'chekelin']);

     Route::post('jason_encode',[ApiController::class,'jason_encode']);

     Route::post('add_form_question',[ApiController::class,'add_form_question']);

     Route::post('create_case',[ApiController::class,'create_case']);


    

    //  Route::post('handleWebhook',[ApiController::class,'handleWebhook']);

     Route::post('send-webhook/{site_id}', [ApiController::class, 'sendWebhook']);

     Route::post('createOrder', [ApiController::class, 'createOrder']);

     Route::post('add_user_check_product', [ApiController::class, 'add_user_check_product']);

     Route::post('add_user_address', [ApiController::class, 'add_user_address']);

     Route::post('add_use_payment_card_and_address', [ApiController::class, 'add_use_payment_card_and_address']);

     

     Route::post('sticky_product_add', [ApiController::class, 'sticky_product_add']);

     Route::post('update_product_data', [ApiController::class, 'update_product_data']);

     Route::post('testing', [ApiController::class, 'testing']);

     

     

    

     Route::post('custom_field_next_check_in_link', [ApiController::class, 'custom_field_next_check_in_link']);

     

     Route::post('add_cat_pro_data', [ApiController::class, 'add_cat_pro_data']);

     Route::post('add_new_cat', [ApiController::class, 'add_new_cat']);

     Route::post('add_new_cat_pro', [ApiController::class, 'add_new_cat_pro']);

     

     Route::post('send_next_check_in_link', [ApiController::class, 'send_next_check_in_link']);

     Route::post('add_cases_images', [ApiController::class, 'add_cases_images']);

     

     

     



     




// contact_details

Route::post('get_contact_details', [ApiController::class, 'get_contact_details']);
Route::post('contact_details_step_1', [ApiController::class, 'contact_details_step_1']);
Route::post('contact_details_step_2', [ApiController::class, 'contact_details_step_2']);
Route::post('contact_details_step_3', [ApiController::class, 'contact_details_step_3']);
Route::post('contact_details_step_4', [ApiController::class, 'contact_details_step_4']);
Route::post('contact_details_step_5', [ApiController::class, 'contact_details_step_5']);
Route::post('contact_details_step_6', [ApiController::class, 'contact_details_step_6']);
Route::post('contact_details_step_7', [ApiController::class, 'contact_details_step_7']);
Route::post('contact_details_step_8', [ApiController::class, 'contact_details_step_8']);
Route::post('contact_details_step_9', [ApiController::class, 'contact_details_step_9']);
Route::post('contact_details_step_10', [ApiController::class, 'contact_details_step_10']);
Route::post('contact_details_step_11', [ApiController::class, 'contact_details_step_11']);
Route::post('contact_details_step_12', [ApiController::class, 'contact_details_step_12']);
Route::post('contact_details_step_13', [ApiController::class, 'contact_details_step_13']);
Route::post('contact_details_step_14', [ApiController::class, 'contact_details_step_14']);
Route::post('contact_details_step_15', [ApiController::class, 'contact_details_step_15']);
Route::post('contact_details_step_16', [ApiController::class, 'contact_details_step_16']);
Route::post('contact_details_step_17', [ApiController::class, 'contact_details_step_17']);
Route::post('contact_details_step_18', [ApiController::class, 'contact_details_step_18']);
Route::post('contact_details_step_19', [ApiController::class, 'contact_details_step_19']);
Route::post('contact_details_step_20', [ApiController::class, 'contact_details_step_20']);
Route::post('contact_details_step_25', [ApiController::class, 'contact_details_step_25']);


Route::post('send_dummey_mail', [ApiController::class, 'send_dummey_mail']);

Route::post('check_form', [ApiController::class, 'check_form']);



Route::post('createOrder_2', [ApiController::class, 'createOrder_2']);

Route::post('add_user_id_in_submission', [ApiController::class, 'add_user_id_in_submission']);

Route::post('add_user_uniqid', [ApiController::class, 'add_user_uniqid']);

Route::post('get_unique_id_data', [ApiController::class, 'get_unique_id_data']);

Route::post('processPayment', [ApiController::class, 'processPayment']);




//Member data
Route::post('edit_full_body_file', [MemberController::class, 'edit_full_body_file']);

Route::post('re_full_body_file_req', [MemberController::class, 're_full_body_file_req']);

Route::post('re_fileUpload_req', [MemberController::class, 're_fileUpload_req']);

Route::post('chack_user_submitted_form',[MemberController::class,'chack_user_submitted_form']);

Route::post('edit_file', [MemberController::class, 'edit_file']);

Route::post('get_user_cart_products', [MemberController::class, 'get_user_cart_products']);

Route::post('cancel_user_cart_product', [MemberController::class, 'cancel_user_cart_product']);



Route::post('complete_user_data', [MemberController::class, 'complete_user_data']);



Route::post('next_month_order_canceled', [MemberController::class, 'next_month_order_canceled']);

Route::post('check_user_data_completed', [MemberController::class, 'check_user_data_completed']);

Route::post('update_payment_token', [MemberController::class, 'update_payment_token']);



Route::post('update_user_skip_month', [MemberController::class, 'update_user_skip_month']);


Route::post('forgot_password', [MemberController::class, 'forgot_password']);

Route::post('verify_email_otp', [MemberController::class, 'verify_email_otp']);


Route::get('get_products',[MemberController::class,'get_products']);

Route::post('member_create',[ApiController::class,'memberCreate_2']);

Route::post('order_view',[MemberController::class,'order_view_2']);

Route::post('reset_password',[MemberController::class,'reset_password']);



Route::post('get_submission_data',[ApiController::class,'get_submission_data']);

Route::post('add_full_body_file',[ApiController::class,'add_full_body_file']);

Route::post('update_case_image_live',[ApiController::class,'update_case_image_live']);

Route::post('password_change',[ApiController::class,'password_change']);

Route::post('member_login',[ApiController::class,'member_login']);

Route::post('customer_view',[ApiController::class,'customer_view']);

Route::post('order_data_get',[ApiController::class,'order_data_get']);


Route::post('magic_link',[MemberController::class,'magic_link']);

Route::post('customer_find',[ApiController::class,'customer_find']);

Route::post('order_find',[ApiController::class,'order_find']);

Route::post('get_promo_codes',[ApiController::class,'get_promo_codes']);

Route::post('check_coupons',[ApiController::class,'check_coupons']);

Route::post('createOrSearchPatients',[ApiController::class,'createOrSearchPatients']);

Route::post('get_url_data',[ApiController::class,'get_url_data']);

Route::post('change_password_new',[MemberController::class,'change_password_new']);

Route::post('generateHash',[ApiController::class,'generateHash']);

Route::get('add_campaign',[ApiController::class,'add_campaign']);








// AgentController
Route::post('super_admin_login',[AgentController::class,'super_admin_login']);

Route::post('add_new_sub_admin',[AgentController::class,'add_new_sub_admin']);

Route::get('get_all_campaign',[AgentController::class,'get_all_campaign']);

Route::get('shipping_method_find',[AgentController::class,'shipping_method_find']);

Route::post('add_coupons',[AgentController::class,'add_coupons']);

Route::get('billing_model_view',[AgentController::class,'billing_model_view']);

Route::get('get_gateway',[AgentController::class,'get_gateway']);


Route::post('add_user_check_product_agent', [OrderController::class, 'add_user_check_product_agent']);

Route::get('get_all_coupons',[AgentController::class,'get_all_coupons']);

Route::post('check_coupons_agent',[AgentController::class,'check_coupons_agent']);


//testing



Route::post('order_find_test',[TestController::class,'order_find_test']);



Route::post('createOrSearchPatients_test',[TestController::class,'createOrSearchPatients_test']);

Route::post('check_coupons_test',[TestController::class,'check_coupons_test']);


Route::get('get_products_test',[TestController::class,'get_products_test']);

Route::post('add_user_check_product_agent_test',[OrderController::class,'add_user_check_product_agent_test']);

Route::post('createOrder_test',[OrderController::class,'createOrder_test']);
Route::post('createOrder_test_v2',[OrderController::class,'createOrder_test_v2']);

Route::post('createOrder_agent',[OrderController::class,'createOrder_agent']);

Route::post('get_all_orders',[AgentController::class,'get_all_orders']);

Route::get('get_instance_pixel',[ApiController::class,'get_instance_pixel']);

Route::post('subscription_stop',[ApiController::class,'subscription_stop']);

Route::post('get_user_orders',[AgentController::class,'get_user_orders']);

Route::post('offer_view',[ApiController::class,'offer_view']);



Route::post('handleWebhook',[WebhookController::class,'handleWebhook']);
Route::post('get_jotform_data', [WebhookController::class, 'get_jotform_data']);
Route::post('get_user_web_data', [WebhookController::class, 'get_user_web_data']);




Route::post('member_view',[ApiController::class,'member_view']);
Route::post('password_change_new',[ApiController::class,'password_change_new']);

Route::get('get_all_sema',[ApiController::class,'get_all_sema']);
Route::get('get_all_tirz',[ApiController::class,'get_all_tirz']);

Route::middleware(['user.auth'])->group(function () {
	Route::post('client/site/{site_id}/order/{order_id}/upload-file', [MemberController::class, 'uploadFile']);
	//Route::post('uploadFile', [MemberController::class, 'uploadFile']);
    Route::post('get_user_cart_product_details', [MemberController::class, 'get_user_cart_product_details']);

    Route::post('get_order_timeline',[MemberController::class,'getOrderTimeline']);
Route::post('getOrderOpTest/{site_id}/{order_id}', [ApiController::class, 'getOrderOpTest']);
    Route::post('user_profile_data', [MemberController::class, 'user_profile_data']);
    Route::post('get_user_cart_products_v2', [MemberController::class, 'get_user_cart_products_v2']);
    Route::post('user_details', [MemberController::class, 'user_details']);

    Route::post('getSingleProductPrice', [TestController::class, 'getSingleProductPrice']);
    Route::post('get_weight_loss_products_v2', [TestController::class, 'get_weight_loss_products_v2']);
 Route::post('get_user_conform_cart_product', [MemberController::class, 'get_user_conform_cart_product']);
    Route::post('get_user_cart_product_details_test', [TestController::class, 'get_user_cart_product_details_test']);
    Route::post('get_billing_address', [MemberController::class, 'get_billing_address']);
    Route::post('get_tokens', [ApiController::class, 'get_tokens']);
    Route::post('patient_message', [ApiController::class, 'patient_message']);
    Route::post('billing_update_order_contact_and_address', [MemberController::class, 'billing_update_order_contact_and_address']);
    Route::post('update_order_payment_token', [MemberController::class, 'update_order_payment_token']);
    Route::post('get_user_cart_products_v5', [MemberController::class, 'get_user_cart_products_v5']);
    Route::post('slack_webhook', [MemberController::class, 'slack_webhook']);
    Route::post('update_order_contact_and_address', [MemberController::class, 'update_order_contact_and_address']);
    Route::post('update_user_details', [MemberController::class, 'update_user_details']);
    Route::post('get_case_id_by_email_api', [AgentController::class, 'get_case_id_by_email_api']);
    Route::post('change_password', [MemberController::class, 'change_password']);
    Route::post('send_patient_message', [ApiController::class, 'send_patient_message']);
 
    Route::get('/update-login-count', [MemberController::class, 'updateLoginCount']);
    Route::post('createOrder_test_parent_order', [OrderController::class, 'createOrder_test_parent_order']);
    Route::post('createOrder_member', [OrderController::class, 'createOrder_member']);


});


Route::post('/expire-token', [ApiController::class, 'expireToken']);
































































