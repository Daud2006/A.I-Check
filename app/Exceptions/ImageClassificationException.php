<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ImageClassificationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 502,
        ?Throwable $previous = null,
        public readonly string $failureType = 'provider_error',
        public readonly ?int $upstreamStatus = null,
        public readonly string $stage = 'unknown',
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array{
     *     exception_class: class-string<Throwable>,
     *     source_file: string,
     *     source_line: int,
     *     stage: string,
     *     failure_type: string,
     *     application_status: int,
     *     upstream_status: int|null
     * }
     */
    public function diagnosticContext(): array
    {
        return self::contextFor(
            $this->getPrevious() ?? $this,
            $this->stage,
            $this->failureType,
            $this->httpStatus,
            $this->upstreamStatus,
        );
    }

    /**
     * @return array{
     *     exception_class: class-string<Throwable>,
     *     source_file: string,
     *     source_line: int,
     *     stage: string,
     *     failure_type: string,
     *     application_status: int,
     *     upstream_status: int|null
     * }
     */
    public static function contextFor(
        Throwable $exception,
        string $stage,
        string $failureType,
        int $applicationStatus,
        ?int $upstreamStatus,
    ): array {
        $sourceFile = str_replace('\\', '/', $exception->getFile());
        $projectRoot = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        if (str_starts_with($sourceFile, $projectRoot)) {
            $sourceFile = substr($sourceFile, strlen($projectRoot));
        } else {
            $sourceFile = 'external/'.basename($sourceFile);
        }

        return [
            'exception_class' => $exception::class,
            'source_file' => $sourceFile,
            'source_line' => $exception->getLine(),
            'stage' => $stage,
            'failure_type' => $failureType,
            'application_status' => $applicationStatus,
            'upstream_status' => $upstreamStatus,
        ];
    }
}
