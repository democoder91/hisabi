<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemory extends Model
{
    public const TYPE_ACCOUNTING_STANDARD = 'accounting_standard';

    public const TYPE_APP_INSTRUCTION = 'app_instruction';

    public const TYPE_USER_TERMINOLOGY = 'user_terminology';

    public const TYPE_USER_CONTEXT = 'user_context';

    protected $fillable = [
        'user_id',
        'type',
        'content',
        'embedding',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ACCOUNTING_STANDARD,
            self::TYPE_APP_INSTRUCTION,
            self::TYPE_USER_TERMINOLOGY,
            self::TYPE_USER_CONTEXT,
        ];
    }
}
