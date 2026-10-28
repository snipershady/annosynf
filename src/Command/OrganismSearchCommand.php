<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Port\OrganismLookup;
use App\Domain\Reference\OrganismSearchHit;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cerca un organismo nel catalogo di una release (ex "Search organism" del menu `--utils`).
 */
#[AsCommand(
    name: 'annosynf:organism:search',
    description: 'Searches the organism catalogue (speclist) of a release by name, species code or taxon id',
)]
final readonly class OrganismSearchCommand
{
    public function __construct(
        private OrganismLookup $lookup,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $release,
        #[Argument('Part of a scientific or common name, a species code (HUMAN) or a taxon id')]
        string $text,
        #[Option('Maximum number of organisms shown')]
        int $limit = 50,
    ): int {
        $hits = $this->lookup->search($release, trim($text), $limit);

        if ($hits === []) {
            $io->warning('No organism found.');

            return Command::FAILURE;
        }

        ConsoleTable::render($io, ['Code', 'Taxon', 'Scientific name', 'Common name', 'Synonym'], array_map(
            static fn (OrganismSearchHit $hit): array => [$hit->code, $hit->taxonId, $hit->officialName, $hit->commonName ?? '–', $hit->synonym ?? '–'],
            $hits,
        ));

        return Command::SUCCESS;
    }
}
