<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Scrive una sequenza in formato FASTA come Bio::SeqIO: header ricostruito da id e descrizione con un solo
 * spazio, sequenza a capo ogni 60 caratteri, riga vuota per una sequenza vuota.
 */
final readonly class FastaFormatter
{
    public const int LINE_WIDTH = 60;

    public function __construct(
        private int $width = self::LINE_WIDTH,
    ) {
    }

    public function format(FastaRecord $record, FastaHeaderStyle $style = FastaHeaderStyle::Full): string
    {
        $description = $style === FastaHeaderStyle::Full ? $record->description() : '';
        $header = '>' . $record->id() . ($description === '' ? '' : ' ' . $description) . "\n";

        if ($record->sequence === '') {
            return $header . "\n";
        }

        return $header . chunk_split($record->sequence, max(1, $this->width), "\n");
    }
}
