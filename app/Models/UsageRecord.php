<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class UsageRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'usage_type',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ImageAnalysis, $this>
     */
    public function imageAnalysis(): BelongsTo
    {
        return $this->belongsTo(ImageAnalysis::class);
    }

    public function setQuantityAttribute(string|int $quantity): void
    {
        if (! ctype_digit((string) $quantity)) {
            throw new InvalidArgumentException('The usage quantity must be a non-negative integer.');
        }

        $this->attributes['quantity'] = $quantity;
    }
}
