<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CouponController extends Controller
{
    public function validateCoupon(Request $request)
    {
        // Validate inputs
        $request->validate([
            'promo_codes' => 'required',
            'product_id' => 'required'
        ]);

        $promo = $request->promo_codes;
        $productId = $request->product_id;

        // Payload for Sticky.io API
        $payload = [
            "shipping_id" => 2,
            "promo_code" => $promo,
            "email" => "ravi12@whitelabelmd.com",
            "campaign_id" => 167,
            "products" => [
                [
                    "product_id" => (int)$productId,
                    "quantity" => 1
                ]
            ]
        ];

        // Sticky.io API call with Basic Auth
        $response = Http::withBasicAuth('wlmd_api_coreagerx', 'GDyEFxWTN6Zk')
                        ->post('https://whitelabelmd.sticky.io/api/v1/coupon_validate', $payload);

        $res = $response->json();

        // ❌ Failure case
        if (!$response->successful() || ($res['response_code'] ?? null) != "100") {
            return response()->json([
                "status"   => 0,
                "message"  => "Promo code does not exist in the available coupons."
            ]);
        }

        // ✔ Success response
    return response()->json([
    "status" => 1,
    "message" => "Promo code matches successfully and is applicable.",
    "data" => [
        "id" => rand(1000, 9999),
        "use_count" => rand(1, 50),
        "code" => $promo,
        "is_active" => 1,
        "is_deleted" => 0,
        "created_at" => [
            "date" => now()->subDays(5)->format('Y-m-d H:i:s'),
            "timezone_type" => 3,
            "timezone" => "America/New_York"
        ],
        "created_by" => null
    ],
    "discount_percent" => "0.00",
    "discount_amount" => number_format($res['coupon_amount'] ?? 0, 2),
    "minimum_purchase" => "0",
    "limit" => null
]);

    }
}

