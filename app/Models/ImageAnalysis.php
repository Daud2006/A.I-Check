<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ImageAnalysis extends Model
{
    public const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    protected $fillable = [
        'original_filename',
        'image_path',
        'mime_type',
        'file_size',
        'model_name',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'confidence_score' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setStatusAttribute(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('The image analysis status is invalid.');
        }

        $this->attributes['status'] = $status;
    }

    public function setFileSizeAttribute(string|int $fileSize): void
    {
        if (! ctype_digit((string) $fileSize)) {
            throw new InvalidArgumentException('The image file size must be a non-negative integer.');
        }

        $this->attributes['file_size'] = $fileSize;
    }
}
