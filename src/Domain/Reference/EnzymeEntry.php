<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Voce di `enzyme.dat` (ENZYME, SIB): numero EC, righe `DE` e accession Swiss-Prot delle righe `DR`.
 */
final readonly class EnzymeEntry
{
    /**
     * @param string       $ecNumber         `1.1.1.1`, anche provvisorio (`1.1.1.n1`)
     * @param list<string> $descriptionLines righe `DE`, nell'ordine del file
     * @param list<string> $accessions       accession delle righe `DR`, nell'ordine del file
     */
    public function __construct(
        public string $ecNumber,
        public array $descriptionLines,
        public array $accessions = [],
    ) {
    }

    /**
     * Descrizione così come la scrive ENZYME, con le righe `DE` unite da uno spazio.
     */
    public function description(): string
    {
        return implode(' ', $this->descriptionLines);
    }

    /**
     * Descrizione della enzymeTable di Annocript, per gli export compatibili: righe `DE` unite da due spazi e
     * ogni carattere fuori da `[A-Za-z0-9-()_/.]` e dagli spazi sostituito da uno spazio (le due ripuliture di
     * extractEnzimeIdsFile e createEnzymeTable, che insieme ammettono questi soli caratteri).
     */
    public function legacyDescription(): string
    {
        return preg_replace('/[^A-Za-z0-9\-()\s_\/.]/', ' ', implode('  ', $this->descriptionLines)) ?? '';
    }
}
