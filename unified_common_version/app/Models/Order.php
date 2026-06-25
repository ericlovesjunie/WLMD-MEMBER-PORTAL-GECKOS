<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders'; // Specify your table name if it's not the default 'orders'

    protected $fillable = [
        'gateway_id',
        'response_code',
        'error_found',
        'order_id',
        'transactionID',
        'customerId',
        'authId',
        'orderTotal',
        'orderSalesTaxPercent',
        'orderSalesTaxAmount',
        'test',
        'prepaid_match',
        'resp_msg'
    ];

    public function lineItems()
    {
        return $this->hasMany(OrderLineItem::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
