<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\SourceDownloader;
use App\Domain\Reference\SourceFile;
use App\Domain\Shared\AnnosynfException;
use Override;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * {@see SourceDownloader} dei test: al posto di ogni URL copia il file omonimo di `tests/Fixtures/release/` (la
 * mini-release). `$failures` simula i server che non rispondono. Lo stato è statico perché i test non possono chiedere
 * il fake al container: PHPStan conosce solo quello di sviluppo; ReleaseTestCase lo azzera a ogni test.
 */
final class FakeSourceDownloader implements SourceDownloader
{
    public const string FIXTURES = __DIR__ . '/../Fixtures/release';

    /** @var array<string, string> nome del file → messaggio d'errore */
    public static array $failures = [];

    /** @var array<string, string> nome richiesto → file della mini-release servito al suo posto */
    public static array $aliases = [];

    public static function reset(): void
    {
        self::$failures = [];
        self::$aliases = [];
    }

    #[Override]
    public function download(string $url, string $destination): SourceFile
    {
        $name = basename($url);

        if (isset(self::$failures[$name])) {
            throw new class(self::$failures[$name]) extends RuntimeException implements AnnosynfException {
            };
        }

        $reused = is_file($destination);
        new Filesystem()->copy(self::FIXTURES . '/' . (self::$aliases[$name] ?? $name), $destination, true);

        return new SourceFile($url, $destination, (int) filesize($destination), (string) hash_file('sha256', $destination), $reused);
    }
}
