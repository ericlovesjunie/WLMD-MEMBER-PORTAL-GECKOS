<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions'; // Specify your table name if it's not the default 'subscriptions'

    protected $fillable = [
        'order_id',
        'product_id',
        'subscription_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
