<?php

declare(strict_types=1);

namespace App\Domain\Sequence;

/**
 * Tipo di sequenze di un FASTA. Decide i programmi della pipeline (blastx o blastp, CPC2 e ORF solo sui
 * nucleotidi) e i caratteri ammessi.
 */
enum Alphabet: string
{
    case Dna = 'dna';
    case Rna = 'rna';
    case Protein = 'protein';

    /** Codici IUPAC ammessi per i nucleotidi (nuclIUPAC del program_config legacy). */
    public const string NUCLEOTIDE_CHARACTERS = 'ACGTURYSWKMBDHVN-';

    /** Codici IUPAC ammessi per le proteine (protIUPAC del program_config legacy: senza J e O). */
    public const string PROTEIN_CHARACTERS = 'ABCDEFGHIKLMNPQRSTUVWYZX*-';

    public function isNucleotide(): bool
    {
        return $this !== self::Protein;
    }

    /**
     * Caratteri ammessi, maiuscoli: il confronto ignora maiuscole e minuscole, come nel legacy.
     */
    public function allowedCharacters(): string
    {
        return $this->isNucleotide() ? self::NUCLEOTIDE_CHARACTERS : self::PROTEIN_CHARACTERS;
    }
}
