<?php

namespace App\Exceptions;

use RuntimeException;

class AnalysisQuotaExceededException extends RuntimeException
{
    /**
     * @param  array{limit: int|null, used: int, remaining: int|null, resets_at: string|null, premium: bool}  $quota
     */
    public function __construct(
        public readonly array $quota,
        string $message = 'Kuota analisis Anda untuk 7 hari terakhir telah habis.'
    ) {
        parent::__construct($message);
    }
}
