<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Http;

use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SourceMirrors;
use App\Infrastructure\Http\HttpSourceDownloader;
use App\Infrastructure\Http\SourceDownloadFailedException;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\FileNotWritableException;
use App\Tests\Support\TemporaryDirectory;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Filesystem reale in una directory temporanea, server simulati con MockHttpClient, attese con MockClock.
 */
#[CoversClass(HttpSourceDownloader::class)]
#[CoversClass(SourceDownloadFailedException::class)]
#[CoversClass(FileNotWritableException::class)]
#[CoversClass(FileNotReadableException::class)]
#[CoversClass(SourceFile::class)]
#[UsesClass(SourceMirrors::class)]
final class HttpSourceDownloaderTest extends TestCase
{
    private const string UNIPROT = 'https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt';

    private const string EBI = 'https://ftp.ebi.ac.uk/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt';

    private const string EXPASY = 'https://ftp.expasy.org/databases/uniprot/current_release/knowledgebase/complete/reldate.txt';

    private const string CONTENT = "UniProt Knowledgebase Release 2026_03\n";

    private TemporaryDirectory $directory;

    private string $destination;

    private MockClock $clock;

    /** @var list<array{url: string, headers: array<string, list<string>>}> */
    private array $requests = [];

    /** @var list<MockResponse> */
    private array $responses = [];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
        $this->destination = $this->directory->path . '/2026_03/reldate.txt';
        $this->clock = new MockClock('2026-10-27 10:00:00');
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->chmod($this->directory->path, 0o755, 0o000, true);
        $this->directory->remove();
    }

    public function testAFileIsStreamedToDiskWithItsSha256(): void
    {
        $this->responses = [new MockResponse(self::CONTENT, ['response_headers' => ['ETag' => '"v1"', 'Content-Length' => (string) \strlen(self::CONTENT)]])];

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([self::UNIPROT, $this->destination, \strlen(self::CONTENT), hash('sha256', self::CONTENT), false], [$file->url, $file->path, $file->bytes, $file->sha256, $file->reused]);
        self::assertSame(self::CONTENT, file_get_contents($this->destination));
        self::assertSame(['reldate.txt'], $this->files(), 'no .part or .validator is left behind');
        self::assertSame(['identity'], $this->header(0, 'accept-encoding'), 'the body must not be decoded: Range counts the bytes on the server');
        self::assertSame([], $this->header(0, 'range'));
    }

    public function testAFileAlreadyPresentIsNotDownloadedAgain(): void
    {
        mkdir(\dirname($this->destination));
        file_put_contents($this->destination, self::CONTENT);

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([\strlen(self::CONTENT), hash('sha256', self::CONTENT), true], [$file->bytes, $file->sha256, $file->reused]);
        self::assertSame([], $this->requests);
    }

    public function testAnInterruptedTransferResumesWithRangeAndIfRange(): void
    {
        $this->responses = [
            // senza Content-Length: con MockResponse l'errore arriverebbe prima degli header
            new MockResponse($this->brokenBody('UniProt '), ['response_headers' => ['ETag' => '"v1"']]),
            new MockResponse(substr(self::CONTENT, 8), ['http_code' => 206, 'response_headers' => ['Content-Range' => \sprintf('bytes 8-%d/%d', \strlen(self::CONTENT) - 1, \strlen(self::CONTENT))]]),
        ];

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame(self::CONTENT, file_get_contents($this->destination));
        self::assertSame(hash('sha256', self::CONTENT), $file->sha256, 'the hash covers the bytes already on disk');
        self::assertSame(['bytes=8-'], $this->header(1, 'range'));
        self::assertSame(['"v1"'], $this->header(1, 'if-range'));
        self::assertSame('10:00:05', $this->clock->now()->format('H:i:s'), 'one pause before the second attempt');
        self::assertSame(['reldate.txt'], $this->files());
    }

    public function testAPartLeftByAnotherProcessIsResumedWithLastModifiedWhenTheEtagIsWeak(): void
    {
        $this->responses = [
            new MockResponse($this->brokenBody('Uni'), ['response_headers' => ['ETag' => 'W/"weak"', 'Last-Modified' => 'Wed, 02 Sep 2026 10:00:00 GMT']]),
        ];

        try {
            $this->downloader(attempts: 1, mirrors: new SourceMirrors([]))->download(self::UNIPROT, $this->destination);
            self::fail('the only attempt is interrupted');
        } catch (SourceDownloadFailedException) {
        }

        $this->requests = [];
        $this->responses = [new MockResponse(substr(self::CONTENT, 3), ['http_code' => 206, 'response_headers' => ['Content-Range' => 'bytes 3-37/*']])];

        $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame(['Wed, 02 Sep 2026 10:00:00 GMT'], $this->header(0, 'if-range'));
        self::assertSame(self::CONTENT, file_get_contents($this->destination), 'with an unknown total the transfer ends with the body');
    }

    public function testAFileChangedOnTheServerIsDownloadedFromTheStart(): void
    {
        $this->givenAPart('stale', '"v0"');
        $this->responses = [new MockResponse(self::CONTENT, ['response_headers' => ['ETag' => '"v1"']])];

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame(['bytes=5-'], $this->header(0, 'range'));
        self::assertSame(self::CONTENT, file_get_contents($this->destination), 'HTTP 200 to an If-Range request: the part is replaced');
        self::assertSame(hash('sha256', self::CONTENT), $file->sha256);
    }

    public function testAPartWithoutValidatorIsNotTrusted(): void
    {
        $this->givenAPart('stale', null);
        $this->responses = [new MockResponse(self::CONTENT)];

        $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([], $this->header(0, 'range'));
        self::assertSame(self::CONTENT, file_get_contents($this->destination));
    }

    public function testAServerWithoutValidatorsMakesTheNextAttemptStartAgain(): void
    {
        $this->givenAPart('', '"old"');
        $this->responses = [new MockResponse($this->brokenBody('Uni')), new MockResponse(self::CONTENT)];

        $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([], $this->header(1, 'range'));
        self::assertSame(self::CONTENT, file_get_contents($this->destination));
    }

    public function testACompletePartIsOnlyRenamed(): void
    {
        $this->givenAPart(self::CONTENT, '"v1"');
        $this->responses = [new MockResponse('', ['http_code' => 416, 'response_headers' => ['Content-Range' => \sprintf('bytes */%d', \strlen(self::CONTENT))]])];

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([hash('sha256', self::CONTENT), false], [$file->sha256, $file->reused]);
        self::assertSame(['reldate.txt'], $this->files());
    }

    public function testAnInconsistentResumeIsRestartedFromZero(): void
    {
        $this->givenAPart('UniProt ', '"v1"');
        $this->responses = [
            new MockResponse('', ['http_code' => 416, 'response_headers' => ['Content-Range' => 'bytes */4']]),
            new MockResponse(self::CONTENT),
        ];

        $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([], $this->header(1, 'range'));
        self::assertSame(self::CONTENT, file_get_contents($this->destination));

        unlink($this->destination);
        $this->requests = [];
        $this->givenAPart('UniProt ', '"v1"');
        $this->responses = [
            new MockResponse('xx', ['http_code' => 206, 'response_headers' => ['Content-Range' => 'bytes 0-1/38']]),
            new MockResponse(self::CONTENT),
        ];

        $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([], $this->header(1, 'range'), 'a range that does not start at the part size');
        self::assertSame(self::CONTENT, file_get_contents($this->destination));
    }

    public function testAMissingFileIsLookedForOnTheMirrorsWithoutRetrying(): void
    {
        $this->responses = [new MockResponse('', ['http_code' => 404]), new MockResponse(self::CONTENT)];

        $file = $this->downloader()->download(self::UNIPROT, $this->destination);

        self::assertSame([self::UNIPROT, self::EBI], array_column($this->requests, 'url'));
        self::assertSame(self::EBI, $file->url);
        self::assertSame('10:00:00', $this->clock->now()->format('H:i:s'));
    }

    public function testEveryServerIsTriedBeforeGivingUp(): void
    {
        $this->responses = [
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('', ['error' => 'Could not resolve host']),
            new MockResponse('', ['http_code' => 403]),
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 500]),
            new MockResponse('', ['http_code' => 500]),
        ];

        try {
            $this->downloader()->download(self::UNIPROT, $this->destination);
            self::fail('no server has the file');
        } catch (SourceDownloadFailedException $sourceDownloadFailedException) {
            self::assertStringContainsString(\sprintf('Unable to download %s, %s, %s. Last error: "%s" answered HTTP 500.', self::UNIPROT, self::EBI, self::EXPASY, self::EXPASY), $sourceDownloadFailedException->getMessage());
            self::assertFalse($sourceDownloadFailedException->retryable);
        }

        self::assertSame([self::UNIPROT, self::UNIPROT, self::UNIPROT, self::EBI, self::EXPASY, self::EXPASY, self::EXPASY], array_column($this->requests, 'url'));
        self::assertSame('10:00:30', $this->clock->now()->format('H:i:s'), '5 + 10 seconds on each server that keeps failing');
    }

    public function testATruncatedBodyIsAnError(): void
    {
        $this->responses = [new MockResponse('short', ['http_code' => 206, 'response_headers' => ['Content-Range' => 'bytes 0-37/38']])];

        $this->expectException(SourceDownloadFailedException::class);
        $this->expectExceptionMessageIsOrContains('Unable to download ' . self::UNIPROT . '. Last error: "' . self::UNIPROT . '": received 5 bytes of 38.');

        $this->downloader(attempts: 1, mirrors: new SourceMirrors([]))->download(self::UNIPROT, $this->destination);
    }

    public function testTheDirectoryMustBeWritable(): void
    {
        chmod($this->directory->path, 0o555);

        $this->expectException(FileNotWritableException::class);

        $this->downloader()->download(self::UNIPROT, $this->destination);
    }

    public function testThePartMustBeWritable(): void
    {
        mkdir($this->destination . '.part', 0o775, true);
        $this->responses = [new MockResponse(self::CONTENT)];

        $this->expectException(FileNotWritableException::class);
        $this->expectExceptionMessageIsOrContains($this->destination . '.part');

        $this->downloader()->download(self::UNIPROT, $this->destination);
    }

    public function testADiskFullIsAnError(): void
    {
        mkdir(\dirname($this->destination));
        symlink('/dev/full', $this->destination . '.part');
        $this->responses = [new MockResponse(self::CONTENT)];

        try {
            $this->downloader()->download(self::UNIPROT, $this->destination);
            self::fail('/dev/full refuses every write');
        } catch (FileNotWritableException $fileNotWritableException) {
            self::assertStringContainsString('.part', $fileNotWritableException->getMessage());
        } finally {
            unlink($this->destination . '.part');
        }
    }

    public function testTheValidatorMustBeWritable(): void
    {
        mkdir($this->destination . '.part.validator', 0o775, true);
        $this->responses = [new MockResponse(self::CONTENT, ['response_headers' => ['ETag' => '"v1"']])];

        $this->expectException(FileNotWritableException::class);
        $this->expectExceptionMessageIsOrContains('.part.validator');

        $this->downloader()->download(self::UNIPROT, $this->destination);
    }

    public function testTheDestinationMustAcceptTheRename(): void
    {
        mkdir($this->destination . '/occupied', 0o775, true);
        $this->responses = [new MockResponse(self::CONTENT)];

        $this->expectException(FileNotWritableException::class);

        $this->downloader()->download(self::UNIPROT, $this->destination);
    }

    public function testAnUnreadableFileCannotBeHashed(): void
    {
        mkdir(\dirname($this->destination));
        file_put_contents($this->destination, self::CONTENT);
        chmod($this->destination, 0o000);

        $this->expectException(FileNotReadableException::class);

        $this->downloader()->download(self::UNIPROT, $this->destination);
    }

    /**
     * @param positive-int $attempts
     */
    private function downloader(int $attempts = 3, SourceMirrors $mirrors = new SourceMirrors()): HttpSourceDownloader
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $headers = [];

            /** @var list<string> $lines */
            $lines = $options['headers'] ?? [];

            foreach ($lines as $line) {
                [$name, $value] = explode(': ', $line, 2);
                $headers[strtolower($name)][] = $value;
            }

            $this->requests[] = ['url' => $url, 'headers' => $headers];
            $response = array_shift($this->responses);

            return $response ?? throw new RuntimeException('Unexpected request to ' . $url);
        });

        return new HttpSourceDownloader($client, $this->clock, $mirrors, $attempts);
    }

    /**
     * @return Generator<int, string>
     */
    private function brokenBody(string $sent): Generator
    {
        yield $sent;

        throw new RuntimeException('Connection reset by peer');
    }

    private function givenAPart(string $content, ?string $validator): void
    {
        mkdir(\dirname($this->destination));
        file_put_contents($this->destination . '.part', $content);

        if ($validator !== null) {
            file_put_contents($this->destination . '.part.validator', $validator);
        }
    }

    /**
     * @return list<string>
     */
    private function header(int $request, string $name): array
    {
        return $this->requests[$request]['headers'][$name] ?? [];
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $entries = scandir(\dirname($this->destination));
        self::assertIsArray($entries);

        return array_values(array_diff($entries, ['.', '..']));
    }
}
