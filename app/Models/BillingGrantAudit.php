<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingGrantAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'billing_product_id',
        'grant_type',
        'product_snapshot',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'product_snapshot' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function billingProduct(): BelongsTo
    {
        return $this->belongsTo(BillingProduct::class);
    }
}
