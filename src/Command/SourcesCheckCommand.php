<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceKind;
use App\Domain\Reference\SourceMirrors;
use App\Domain\Reference\UnirefVariant;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Ripete la verifica dello step 5.2: una richiesta HEAD per ogni sorgente di `annosynf.sources` (ogni variante
 * UniRef) e per i suoi mirror. Fallisce se un URL configurato non risponde 200; un mirror irraggiungibile è solo
 * segnalato. Da lanciare quando un download fallisce o prima di creare una release.
 */
#[AsCommand(
    name: 'annosynf:sources:check',
    description: 'Checks with a HEAD request that every reference source and its mirrors are reachable over HTTPS',
)]
final readonly class SourcesCheckCommand
{
    public function __construct(
        private SourceCatalogue $catalogue,
        private SourceMirrors $mirrors,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        // le richieste partono tutte insieme: HttpClient le esegue in parallelo e le risposte si leggono dopo
        $checks = [];

        foreach ($this->sources() as $label => $url) {
            foreach ($this->mirrors->candidates($url) as $position => $candidate) {
                $checks[] = [$position === 0 ? $label : '  mirror', $candidate, $position === 0, $this->httpClient->request('HEAD', $candidate, ['headers' => ['Accept-Encoding' => 'identity']])];
            }
        }

        $rows = [];
        $failed = 0;

        foreach ($checks as [$label, $url, $primary, $response]) {
            [$status, $size, $validator] = $this->inspect($response);
            $rows[] = [$label, $url, $status, $size, $validator];

            if ($primary && $status !== '200') {
                ++$failed;
            }
        }

        ConsoleTable::render($io, ['Source', 'URL', 'Status', 'Size', 'Validator'], $rows);

        if ($failed > 0) {
            $io->error(\sprintf('%d source(s) did not answer 200: update annosynf.sources in config/packages/annosynf.yaml.', $failed));

            return Command::FAILURE;
        }

        $io->success('Every configured source is reachable.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> etichetta → URL configurato
     */
    private function sources(): array
    {
        $sources = [];

        foreach (SourceKind::cases() as $kind) {
            $url = $this->catalogue->url($kind, UnirefVariant::UniRef90);

            if ($url === $this->catalogue->url($kind, UnirefVariant::UniRef50)) {
                $sources[$kind->value] = $url;

                continue;
            }

            foreach (UnirefVariant::cases() as $variant) {
                $sources[$kind->value . ' (' . $variant->value . ')'] = $this->catalogue->url($kind, $variant);
            }
        }

        return $sources;
    }

    /**
     * @return array{string, string, string} stato, dimensione, validatore usato per riprendere i download
     */
    private function inspect(ResponseInterface $response): array
    {
        try {
            $headers = $response->getHeaders(false);
            $status = (string) $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            // l'URL è già nella sua colonna: `Idle timeout reached for "https://…".` diventa `Idle timeout reached`
            return [preg_replace('/ for "[^"]*"\.?$/', '', $exception->getMessage()) ?? '', '–', '–'];
        }

        $length = $headers['content-length'][0] ?? '';
        $etag = $headers['etag'][0] ?? '';

        return [
            $status,
            ctype_digit($length) ? Helper::formatMemory((int) $length) : '–',
            match (true) {
                $etag !== '' && !str_starts_with($etag, 'W/') => 'ETag',
                isset($headers['last-modified']) => 'Last-Modified',
                default => '–',
            },
        ];
    }
}
