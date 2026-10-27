<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;
use Throwable;

final class SourceDownloadFailedException extends RuntimeException implements AnnosynfException
{
    /**
     * @param bool $retryable false quando riprovare lo stesso URL non serve (404, 403, …): si passa al mirror
     */
    private function __construct(
        string $message,
        public readonly bool $retryable,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function status(string $url, int $status): self
    {
        $retryable = $status >= 500 || \in_array($status, [408, 429], true);

        return new self(\sprintf('"%s" answered HTTP %d.', $url, $status), $retryable);
    }

    public static function incomplete(string $url, int $received, int $expected): self
    {
        return new self(\sprintf('"%s": received %d bytes of %d.', $url, $received, $expected), true);
    }

    public static function unexpectedRange(string $url, string $contentRange): self
    {
        return new self(\sprintf('"%s" answered the resume request with Content-Range "%s".', $url, $contentRange), true);
    }

    public static function transport(string $url, Throwable $previous): self
    {
        return new self(\sprintf('"%s": %s', $url, $previous->getMessage()), true, $previous);
    }

    /**
     * @param non-empty-list<string> $candidates
     */
    public static function exhausted(array $candidates, self $last): self
    {
        return new self(\sprintf('Unable to download %s. Last error: %s', implode(', ', $candidates), $last->getMessage()), false, $last);
    }
}
