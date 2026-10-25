<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

use Generator;

/**
 * Sequenze scelte per nome (primo token dell'header, confronto esatto), come faSomeRecords di UCSC usato dal
 * legacy per i database ristretti a un insieme di organismi: ordine del FASTA, nomi ripetuti o assenti nella
 * lista ininfluenti, tutte le sequenze con un nome scelto.
 */
final readonly class FastaSubset
{
    /** @var array<string, true> */
    private array $ids;

    /**
     * @param iterable<string> $ids
     * @param bool             $exclude true per tenere le sequenze che NON sono nella lista (-exclude)
     */
    public function __construct(
        iterable $ids,
        private bool $exclude = false,
    ) {
        $set = [];

        foreach ($ids as $id) {
            $set[$id] = true;
        }

        $this->ids = $set;
    }

    /**
     * @param iterable<FastaRecord> $records
     *
     * @return Generator<int, FastaRecord>
     */
    public function select(iterable $records): Generator
    {
        foreach ($records as $record) {
            if (isset($this->ids[$record->id()]) !== $this->exclude) {
                yield $record;
            }
        }
    }
}
