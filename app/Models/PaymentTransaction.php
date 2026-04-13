<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'paymob_order_id',
        'product_slug',
        'product_name',
        'amount',
        'currency',
        'type',
        'credits_added',
        'renews_in_days',
        'status',
    ];

    protected $casts = [
        'paymob_order_id' => 'integer',
        'amount' => 'integer',
        'credits_added' => 'integer',
        'renews_in_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
