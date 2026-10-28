<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\ReferenceDataWriter;
use App\Domain\Reference\MappingResult;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\UnirefVariant;
use Override;

/**
 * {@see ReferenceDataWriter} in memoria: registra ogni chiamata con gli elementi ricevuti, che restituisce come
 * righe caricate (le associazioni risultano tutte trovate).
 */
final class RecordingReferenceDataWriter implements ReferenceDataWriter
{
    /** @var list<array{string, int, list<mixed>, mixed}> metodo, release, elementi, argomento extra */
    public array $calls = [];

    #[Override]
    public function prepare(int $releaseId): void
    {
        $this->calls[] = ['prepare', $releaseId, [], null];
    }

    #[Override]
    public function drop(int $releaseId): void
    {
        $this->calls[] = ['drop', $releaseId, [], null];
    }

    #[Override]
    public function counts(int $releaseId): array
    {
        return ['ref_protein' => \count($this->items('writeProteins'))];
    }

    #[Override]
    public function writeGoTerms(int $releaseId, iterable $terms): int
    {
        return $this->record('writeGoTerms', $releaseId, $terms);
    }

    #[Override]
    public function writeEnzymes(int $releaseId, iterable $entries): int
    {
        return $this->record('writeEnzymes', $releaseId, $entries);
    }

    #[Override]
    public function writePathways(int $releaseId, iterable $pathways): int
    {
        return $this->record('writePathways', $releaseId, $pathways);
    }

    #[Override]
    public function writeDomains(int $releaseId, iterable $domains): int
    {
        return $this->record('writeDomains', $releaseId, $domains);
    }

    #[Override]
    public function writeOrganismCatalog(int $releaseId, iterable $codes): int
    {
        return $this->record('writeOrganismCatalog', $releaseId, $codes);
    }

    #[Override]
    public function writeProteins(int $releaseId, ProteinSource $source, iterable $proteins): int
    {
        return $this->record('writeProteins', $releaseId, $proteins, $source);
    }

    #[Override]
    public function mapProteinGo(int $releaseId, iterable $entries, ?UnirefVariant $variant): MappingResult
    {
        return new MappingResult($this->record('mapProteinGo', $releaseId, $entries, $variant), 0);
    }

    #[Override]
    public function mapProteinEnzymes(int $releaseId, iterable $entries): MappingResult
    {
        return new MappingResult($this->record('mapProteinEnzymes', $releaseId, $entries), 0);
    }

    #[Override]
    public function mapProteinPathways(int $releaseId, iterable $pathways): MappingResult
    {
        return new MappingResult($this->record('mapProteinPathways', $releaseId, $pathways), 0);
    }

    #[Override]
    public function mapDomainGo(int $releaseId, iterable $mappings): MappingResult
    {
        return new MappingResult($this->record('mapDomainGo', $releaseId, $mappings), 1);
    }

    /**
     * Elementi ricevuti dall'ultima chiamata del metodo.
     *
     * @return list<mixed>
     */
    public function items(string $method): array
    {
        $items = [];

        foreach ($this->calls as [$called, , $received]) {
            if ($called === $method) {
                $items = $received;
            }
        }

        return $items;
    }

    /**
     * @param iterable<mixed> $items
     */
    private function record(string $method, int $releaseId, iterable $items, mixed $extra = null): int
    {
        $received = [];

        foreach ($items as $item) {
            $received[] = $item;
        }

        $this->calls[] = [$method, $releaseId, $received, $extra];

        return \count($received);
    }
}
