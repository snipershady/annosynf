<?php

declare(strict_types=1);

namespace App\Tests\Support\Golden;

use UnexpectedValueException;

/**
 * Una riga dell'output tabellare di dna2pep 1.1 (`-O tab`):
 * `<id>_rframe<±f>_ORF  <peptide>  <annotazione>  /orf_mode="<mode>"; /dna="<dna>";`.
 */
final readonly class Dna2PepRow
{
    public function __construct(
        public string $id,
        public int $frame,
        public string $peptide,
        public string $annotation,
        public string $mode,
        public string $dna,
        public string $line,
    ) {
    }

    /**
     * @throws UnexpectedValueException se la riga non è nel formato di dna2pep
     */
    public static function fromLine(string $line): self
    {
        $fields = explode("\t", $line);

        if (\count($fields) !== 4
            || preg_match('/^(.+)_rframe(-?[1-3])_ORF$/', $fields[0], $name) !== 1
            || preg_match('/^\/orf_mode="(none|strict|any)"; \/dna="([A-Za-z]*)";$/', $fields[3], $qualifiers) !== 1) {
            throw new UnexpectedValueException(\sprintf('Not a dna2pep tab line: "%s".', mb_strimwidth($line, 0, 80, '…')));
        }

        return new self($name[1], (int) $name[2], $fields[1], $fields[2], $qualifiers[1], $qualifiers[2], $line);
    }

    /**
     * Strand come lo ricava Annocript: segno del frame.
     */
    public function strand(): string
    {
        return $this->frame > 0 ? '+' : '-';
    }

    /**
     * Frame come lo ricava Annocript (allineato a BLAST): |frame| − 1, cioè 0..2.
     */
    public function legacyFrame(): int
    {
        return abs($this->frame) - 1;
    }

    /**
     * Lunghezza dell'ORF in amminoacidi, come la colonna LongOrfLength di Annocript.
     */
    public function length(): int
    {
        return \strlen($this->peptide);
    }
}
