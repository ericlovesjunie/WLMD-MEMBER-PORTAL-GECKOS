<?php 
namespace App\Http\Controllers;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RefillWebhookController extends Controller
{






	public function store(Request $request)
{
    $data = $request->all();

    $button_name = $request->button_name;
    $responseBody = null;

    if ($button_name === 'Pay & Check-In') {

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post(
            "https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/92d8f29f-6d4a-44e1-8aba-d4306fcefe5e?number=13109514419",
            [
                'site_id' => '265',
                'email'   => $request->email,
                'order_id'=> $request->order_id // optional but useful
            ]
        );

        $responseBody = $response->body();
    }

    DB::table('refill_logs')->insert([
        'email' => $request->email,
        'webhook_start' => $responseBody,
        'webhook_start_url' => "https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/92d8f29f-6d4a-44e1-8aba-d4306fcefe5e?number=13109514419",
        'event_type' => $request->event_type ?? 'click',
        'button_name' => $button_name,
        'order_id' => $request->order_id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'payload' => json_encode($data),
        'created_at' => now(),
        'updated_at' => now()
    ]);

    return response()->json([
        'status' => 1,
        'message' => 'Log saved'
    ]);
	}

    public function store_old(Request $request)
    {
        $data = $request->all();

if (isset($button_name) && $button_name === 'Pay & Check-In') {	
	
	$response = Http::withHeaders([
    'Content-Type' => 'application/json'
])->post(
	"https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/92d8f29f-6d4a-44e1-8aba-d4306fcefe5e?number=13109514419",
	[
        'site_id' => '265',
        'email'   => $request->email
    ]
);
	
}
	
	DB::table('refill_logs')->insert([
            'email' => $request->email,
	    'webhook_start' => $response->body(),
	    'webhook_start_url' => "https://services.leadconnectorhq.com/hooks/PzFP7g66Iv7nw8oMDLfE/webhook-trigger/92d8f29f-6d4a-44e1-8aba-d4306fcefe5e?number=13109514419",
	    'event_type' => $request->event_type ?? 'click',
            'button_name' => $request->button_name,
            'order_id' => $request->order_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now()
        ]);



//    return $response->body();


        return response()->json([
            'status' => 1,
            'message' => 'Log saved'
        ]);
    }

    public function logs(Request $request)
    {
        $query = DB::table('refill_logs');

        if ($request->email) {
            $query->where('email', $request->email);
        }

        return response()->json([
            'status' => 1,
            'data' => $query->latest()->get()
        ]);
    }
}
