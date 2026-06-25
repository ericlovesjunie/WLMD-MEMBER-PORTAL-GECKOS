<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('/', function () {
    if (!(session()->has('AdminEmail') && session()->has('AdminPassword'))) {
        return view('admin_login');
    } else {
        return redirect()->route('home');
    }

});

Route::get('reset_password/{email}', [AdminController::class, 'reset_password'])->name('reset_password');
Route::get('not_found', [AdminController::class, 'not_found'])->name('not_found');

Route::get('logoutadmin', [AdminController::class, 'logoutadmin']);

Route::post('admin_login', [AdminController::class, 'admin_login'])->name('admin_login');
Route::post('password_change', [AdminController::class, 'password_change'])->name('password_change');

Route::middleware(['AdminAuth'])->group(function () {
    
Route::get('home', [AdminController::class, 'home'])->name('home');

Route::get('product', [AdminController::class, 'product'])->name('product');

Route::post('add_sub_admin', [AdminController::class, 'add_sub_admin']);

Route::get('site', [AdminController::class, 'site'])->name('site');

Route::post('add_site_by_ad', [AdminController::class, 'add_site_by_ad']);

Route::get('get_template', [AdminController::class, 'get_template'])->name('get_template');

Route::post('add_product', [AdminController::class, 'add_product']);

Route::get('add_new_template', [AdminController::class, 'add_new_template']);

Route::get('add_new_product', [AdminController::class, 'add_new_product']);


Route::get('sub_admin_details/{sub_admin_id}', [AdminController::class, 'sub_admin_details']);


Route::get('user', [AdminController::class, 'user']);
Route::get('webhook', [AdminController::class, 'webhook']);

Route::get('submissions', [AdminController::class, 'submissions']);

Route::get('user_details/{id}', [AdminController::class, 'user_details']);


Route::get('category', [AdminController::class, 'category']);

Route::post('add_category', [AdminController::class, 'add_category']);

Route::get('patients', [AdminController::class, 'patients']);

Route::get('patients_details/{id}', [AdminController::class, 'patients_details']);

Route::get('edit_product/{id}', [AdminController::class, 'edit_product']);

Route::post('update_product/{id}', [AdminController::class, 'update_product']);

Route::post('add_product_image', [AdminController::class, 'add_product_image'])->name('add_product_image');

Route::post('edit_product_img/{id}', [AdminController::class, 'edit_product_img'])->name('edit_product_img');

Route::post('delete_product_img/{id}', [AdminController::class, 'delete_product_img'])->name('delete_product_img');


Route::get('order', [AdminController::class, 'order']);

Route::get('cases', [AdminController::class, 'cases']);

Route::get('cases_details/{id}', [AdminController::class, 'getCaseQuestions']);

Route::get('add_category_product/{id}', [AdminController::class, 'add_category_product']);

Route::post('add_new_category_product', [AdminController::class, 'add_new_category_product'])->name('add_new_category_product');


Route::get('add_main_category_product/{id}', [AdminController::class, 'add_main_category_product']);


Route::post('add_new_category_product_data', [AdminController::class, 'add_new_category_product_data'])->name('add_new_category_product_data');

Route::post('send_update_file_re', [AdminController::class, 'send_update_file_re'])->name('send_update_file_re');

Route::post('send_update_full_bodey_img', [AdminController::class, 'send_update_full_bodey_img'])->name('send_update_full_bodey_img');

Route::post('edit_main_category_product', [AdminController::class, 'edit_main_category_product']);


Route::get('site', [AdminController::class, 'site'])->name('site');

Route::post('add_site', [AdminController::class, 'add_site'])->name('add_site');

Route::get('site_product/{id}', [AdminController::class, 'site_product'])->name('site_product');


Route::post('update_site', [AdminController::class, 'update_site'])->name('update_site');


Route::get('add_order', [AdminController::class, 'add_order'])->name('add_order');

Route::get('error_page', [AdminController::class, 'error_page'])->name('error_page');



Route::get('add_new_order/{id}', [AdminController::class, 'add_new_order'])->name('add_new_order');

Route::get('card_declined/{id}/{user_id}', [AdminController::class, 'card_declined'])->name('card_declined');


Route::get('theme_colore', [AdminController::class, 'theme_colore'])->name('theme_colore');

Route::post('add_theme_colore', [AdminController::class, 'add_theme_colore'])->name('add_theme_colore');


Route::get('new_order', [AdminController::class, 'new_order'])->name('new_order');

});
