<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaHeaderStyle;
use App\Domain\Sequence\FastaRecord;

/**
 * Scrive FASTA su disco in streaming (sostituisce Bio::SeqIO in scrittura e faSomeRecords). Ogni file nasce
 * con un nome temporaneo e prende il nome definitivo solo quando è completo: un processo interrotto non lascia
 * mai un FASTA troncato al posto giusto.
 */
final readonly class FastaFileWriter
{
    public function __construct(
        private FastaFormatter $formatter = new FastaFormatter(),
    ) {
    }

    /**
     * @param iterable<FastaRecord> $records
     *
     * @throws FileNotWritableException
     *
     * @return int sequenze scritte
     */
    public function write(string $path, iterable $records, FastaHeaderStyle $style = FastaHeaderStyle::Full): int
    {
        $file = $this->open($path);
        $written = 0;

        try {
            foreach ($records as $record) {
                $file->write($this->formatter->format($record, $style));
                ++$written;
            }

            $file->commit();
        } finally {
            $file->discard();
        }

        return $written;
    }

    /**
     * Una slice per file, `<name>_<numero>.fasta` come create_slices del legacy.
     *
     * @param iterable<int, FastaRecord> $sliced chiave = numero della slice (vedi {@see \App\Domain\Sequence\FastaSlicer})
     *
     * @throws FileNotWritableException
     *
     * @return array<int, string> numero della slice → percorso del file
     */
    public function writeSlices(string $directory, string $name, iterable $sliced, FastaHeaderStyle $style = FastaHeaderStyle::Full): array
    {
        $paths = [];
        $file = null;
        $current = null;

        try {
            foreach ($sliced as $slice => $record) {
                if ($slice !== $current) {
                    $file?->commit();
                    $current = $slice;
                    $paths[$slice] = \sprintf('%s/%s_%d.fasta', rtrim($directory, '/'), $name, $slice);
                    $file = $this->open($paths[$slice]);
                }

                $file?->write($this->formatter->format($record, $style));
            }

            $file?->commit();
        } finally {
            $file?->discard();
        }

        return $paths;
    }

    private function open(string $path): PendingFile
    {
        $directory = \dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw FileNotWritableException::at($path);
        }

        return PendingFile::create($path);
    }
}
