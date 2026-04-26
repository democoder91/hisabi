<?php

namespace App\Domains\Search\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchableRecord extends Model
{
    protected $table = 'searchable_records';

    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
        'embedding_dimensions' => 'integer',
        'embedded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
