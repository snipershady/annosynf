<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Port\TextLines;
use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Shared\AnnosynfException;
use App\Entity\OrganismSet;
use App\Entity\OrganismSetMember;
use App\Entity\ReferenceRelease;
use App\Release\OrganismSetCreator;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crea un insieme di organismi da un file con un organismo per riga (ex `blastedOrganism`) e accoda la costruzione
 * dei database BLAST ristretti.
 */
#[AsCommand(
    name: 'annosynf:organism-set:create',
    description: 'Creates a set of organisms for a ready release and queues its restricted BLAST databases (run a worker on the "import" transport)',
)]
final readonly class OrganismSetCreateCommand
{
    public function __construct(
        private ReferenceReleaseRepository $releases,
        private OrganismSetCreator $creator,
        private TextLines $lines,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Release id')]
        int $release,
        #[Argument('Name of the set, unique in the release')]
        string $name,
        #[Option('File with one organism per line (scientific name as in the UniProt OS field, or taxon id)')]
        string $file = '',
        #[Option('How organisms match proteins: name_exact (case and accent sensitive, like Annocript), name_ci or taxon_id')]
        string $match = 'name_exact',
    ): int {
        $mode = OrganismMatchMode::tryFrom($match);
        $entity = $this->releases->find($release);

        if (!$mode instanceof OrganismMatchMode || $file === '') {
            $io->error('Use --file=<organisms.txt> and --match=name_exact|name_ci|taxon_id.');

            return Command::INVALID;
        }

        if (!$entity instanceof ReferenceRelease) {
            $io->error(\sprintf('Release %d does not exist.', $release));

            return Command::FAILURE;
        }

        try {
            $set = $this->creator->create($entity, $name, $mode, array_values([...$this->lines->lines($file)]));
        } catch (AnnosynfException $annosynfException) {
            $io->error($annosynfException->getMessage());

            return Command::FAILURE;
        }

        $this->report($io, $set);

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, OrganismSet $set): void
    {
        ConsoleTable::render($io, ['Organism', 'Proteins'], array_map(
            static fn (OrganismSetMember $member): array => [$member->getValue(), number_format($member->getMatchedProteins() ?? 0)],
            $set->getMembers(),
        ));

        foreach ($set->getMembers() as $member) {
            if ($member->getMatchedProteins() === 0) {
                $io->warning(\sprintf('"%s" matches no protein: it is ignored.', $member->getValue()));
            }
        }

        $io->success(\sprintf('Organism set %d created: its BLAST databases are queued.', (int) $set->getId()));
        $io->writeln('Run a worker with <info>bin/console messenger:consume import</info>; the databases appear in <info>annosynf:release:show ' . (int) $set->getRelease()->getId() . '</info>.');
    }
}
