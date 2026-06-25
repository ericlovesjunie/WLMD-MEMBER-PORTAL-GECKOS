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
   class LogsDelete extends Command
   {
       protected $signature = 'process:logsdelete';
       protected $description = 'Process logsdelete for users';
   
       public function __construct()
       {
           parent::__construct();
       }
   
       public function handle()
       {
               \Log::info('Cron job started: clearOldLogs');
            
            // $this->create_tokens();
            $this->clearOldLogs();
           

           
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

    
  

    
   





}