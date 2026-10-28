<?php

declare(strict_types=1);

namespace App\Domain\Reference;

use InvalidArgumentException;

/**
 * URL delle sorgenti di riferimento (ex chiavi `*Link` di `program_config.txt`), dalla configurazione
 * `annosynf.sources`. Negli URL UniRef `{uniref}` sta per la variante in minuscolo (`uniref90`).
 */
final readonly class SourceCatalogue
{
    private const string VARIANT = '{uniref}';

    /**
     * @param array<string, string> $urls valore di {@see SourceKind} → URL
     *
     * @throws InvalidArgumentException se manca una sorgente o un URL non è HTTPS
     */
    public function __construct(
        private array $urls,
    ) {
        foreach (SourceKind::cases() as $kind) {
            if (!str_starts_with($urls[$kind->value] ?? '', 'https://')) {
                throw new InvalidArgumentException(\sprintf('The "%s" source needs an HTTPS URL.', $kind->value));
            }
        }
    }

    /**
     * @throws InvalidArgumentException se l'URL dipende dalla variante UniRef e non è indicata
     */
    public function url(SourceKind $kind, ?UnirefVariant $variant = null): string
    {
        $url = $this->urls[$kind->value];

        if (!str_contains($url, self::VARIANT)) {
            return $url;
        }

        if (!$variant instanceof UnirefVariant) {
            throw new InvalidArgumentException(\sprintf('The "%s" source depends on the UniRef variant.', $kind->value));
        }

        return str_replace(self::VARIANT, strtolower($variant->value), $url);
    }

    /**
     * Sorgenti di una release: la versione si legge da `reldate.txt` (uniprotkb) o dalla release note UniRef, e il
     * secondo database proteico è TrEMBL o UniRef.
     *
     * @return non-empty-list<SourceKind>
     */
    public static function sourcesFor(ReleaseType $type): array
    {
        return match ($type) {
            ReleaseType::UniProtKb => [SourceKind::ReleaseDate, SourceKind::SwissProt, SourceKind::TrEmbl, ...self::common()],
            ReleaseType::UniRef => [SourceKind::UnirefReleaseNote, SourceKind::SwissProt, SourceKind::UniRef, ...self::common()],
        };
    }

    /**
     * File da cui si legge la versione della release.
     */
    public static function versionSource(ReleaseType $type): SourceKind
    {
        return self::sourcesFor($type)[0];
    }

    /**
     * @return list<SourceKind>
     */
    private static function common(): array
    {
        return [
            SourceKind::GeneOntology, SourceKind::Enzyme, SourceKind::Pathway, SourceKind::Speclist, SourceKind::CddTable,
            SourceKind::Pfam2Go, SourceKind::IdMapping, SourceKind::CddDatabase, SourceKind::RnaCentral,
        ];
    }
}
