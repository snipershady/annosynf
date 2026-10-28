<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ConsoleTable;
use App\Command\SourcesCheckCommand;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceMirrors;
use App\Tests\Unit\Domain\Reference\SourceCatalogueTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SourcesCheckCommand::class)]
#[UsesClass(SourceCatalogue::class)]
#[UsesClass(SourceMirrors::class)]
#[UsesClass(ConsoleTable::class)]
final class SourcesCheckCommandTest extends TestCase
{
    public function testEverySourceVariantAndMirrorIsAsked(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $method . ' ' . $url;

            return match (true) {
                str_contains($url, 'mirror.example.org') => new MockResponse('', ['http_code' => 404]),
                str_ends_with($url, '/enzyme') => new MockResponse('', ['response_headers' => ['Content-Length' => '10066329', 'ETag' => '"abc"']]),
                str_ends_with($url, '/cdd') => new MockResponse('', ['response_headers' => ['Last-Modified' => 'Tue, 27 Oct 2026 10:00:00 GMT', 'ETag' => 'W/"weak"']]),
                default => new MockResponse(''),
            };
        });

        [$code, $display] = $this->check($client, new SourceMirrors([['https://example.org/', 'https://mirror.example.org/']]));

        self::assertSame(Command::SUCCESS, $code, $display);
        // 13 sorgenti, una con 3 varianti UniRef, ciascuna anche sul mirror
        self::assertCount(32, $requests);
        self::assertContains('HEAD https://example.org/uniref50/uniref50.fasta.gz', $requests);
        self::assertContains('HEAD https://mirror.example.org/pfam2go', $requests);
        self::assertMatchesRegularExpression('/enzyme\s+https:\/\/example\.org\/enzyme\s+200\s+9\.6 MiB\s+ETag/', $display);
        self::assertMatchesRegularExpression('/cdd\s+https:\/\/example\.org\/cdd\s+200\s+–\s+Last-Modified/', $display);
        self::assertMatchesRegularExpression('/mirror\s+https:\/\/mirror\.example\.org\/cdd\s+404/', $display);
        self::assertStringContainsString('Every configured source is reachable.', $display);
    }

    public function testAConfiguredSourceThatDoesNotAnswerFailsTheCheck(): void
    {
        $client = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_ends_with($url, '/speclist') => new MockResponse('', ['http_code' => 404]),
            str_ends_with($url, '/go') => new MockResponse('', ['error' => 'Idle timeout reached for "https://example.org/go".']),
            default => new MockResponse(''),
        });

        [$code, $display] = $this->check($client, new SourceMirrors([]));

        self::assertSame(Command::FAILURE, $code);
        self::assertMatchesRegularExpression('/go\s+https:\/\/example\.org\/go\s+Idle timeout reached\s+–/', $display);
        self::assertStringContainsString('2 source(s) did not answer 200', $display);
    }

    /**
     * @return array{int, string}
     */
    private function check(MockHttpClient $client, SourceMirrors $mirrors): array
    {
        $output = new BufferedOutput();
        $command = new SourcesCheckCommand(new SourceCatalogue(SourceCatalogueTest::urls()), $mirrors, $client);
        $code = $command(new SymfonyStyle(new ArrayInput([]), $output));

        return [$code, $output->fetch()];
    }
}
