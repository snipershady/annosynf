<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Server equivalenti per le sorgenti di riferimento: se il principale non risponde, lo stesso file si cerca sugli
 * altri (download_from_diff_sources del legacy, che sostituiva il dominio tra ftp.uniprot.org, ftp.ebi.ac.uk e
 * ftp.expasy.org). Ogni gruppo elenca i prefissi di URL che puntano allo stesso albero di file: Expasy pubblica
 * UniProt senza `/pub`, che il legacy toglieva anche dagli URL di EBI, dove invece serve.
 */
final readonly class SourceMirrors
{
    /** @var list<non-empty-list<string>> */
    public const array UNIPROT = [[
        'https://ftp.uniprot.org/pub/databases/uniprot/',
        'https://ftp.ebi.ac.uk/pub/databases/uniprot/',
        'https://ftp.expasy.org/databases/uniprot/',
    ]];

    /**
     * @param list<non-empty-list<string>> $groups prefissi equivalenti, ciascuno terminato da "/"
     */
    public function __construct(
        private array $groups = self::UNIPROT,
    ) {
    }

    /**
     * L'URL richiesto seguito dallo stesso file sugli altri server del suo gruppo, nell'ordine del gruppo.
     *
     * @return non-empty-list<string>
     */
    public function candidates(string $url): array
    {
        foreach ($this->groups as $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($url, $prefix)) {
                    $path = substr($url, \strlen($prefix));
                    $others = array_filter($prefixes, static fn (string $other): bool => $other !== $prefix);

                    return [$url, ...array_map(static fn (string $other): string => $other . $path, array_values($others))];
                }
            }
        }

        return [$url];
    }
}
