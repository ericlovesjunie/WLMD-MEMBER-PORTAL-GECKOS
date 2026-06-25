<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Str::macro('thineselfInsert',function($table,$array){
            DB::table($table)->insert($array);
        });

        Str::macro('thineselfUpdate',function($condition,$table,$array){
            DB::table($table)->where($condition,$condition)->update($array);
        });

        Str::macro('thineselfDelete',function($condition,$table){
            DB::table($table)->where($condition,$condition)->delete();
        });

        Str::macro('thineselfView',function($array,$table){
            DB::table($table)->select($array)->get();
        });
    }
}
