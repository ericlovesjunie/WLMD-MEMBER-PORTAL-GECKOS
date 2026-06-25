<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLineItem extends Model
{
    use HasFactory;

    protected $table = 'order_line_items'; // Specify your table name if it's not the default 'order_line_items'

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'quantity',
        'subscription_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
