<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Domain\Shared\AnnosynfException;
use App\Release\ReleaseCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crea una release di riferimento e ne accoda l'import (ex `--db_type`, `--uniref_db_ver` e `doDbCreation=YES`).
 */
#[AsCommand(
    name: 'annosynf:release:create',
    description: 'Creates a reference release from the current UniProt sources and queues its import (run a worker on the "import" transport)',
)]
final readonly class ReleaseCreateCommand
{
    public function __construct(
        private ReleaseCreator $creator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Release type: uniprotkb (Swiss-Prot + TrEMBL) or uniref (Swiss-Prot + UniRef)')]
        string $type = '',
        #[Option('UniRef variant for uniref releases: UniRef50, UniRef90 or UniRef100')]
        string $unirefVariant = '',
        #[Option('Name of the CDD database')]
        string $cdd = 'Cdd',
        #[Option('Fail unless the current sources hold this release (YYYY_MM)')]
        string $expectVersion = '',
    ): int {
        $releaseType = ReleaseType::tryFrom($type);
        $variant = UnirefVariant::tryFrom($unirefVariant);

        if (!$releaseType instanceof ReleaseType || ($releaseType === ReleaseType::UniRef) !== $variant instanceof UnirefVariant || ($unirefVariant !== '' && !$variant instanceof UnirefVariant)) {
            $io->error('Use --type=uniprotkb, or --type=uniref with --uniref-variant=UniRef50|UniRef90|UniRef100.');

            return Command::INVALID;
        }

        try {
            $release = $this->creator->create($releaseType, $variant, $cdd, $expectVersion === '' ? null : $expectVersion);
        } catch (AnnosynfException $annosynfException) {
            $io->error($annosynfException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Release %d (%s %s%s) created: its import is queued.', (int) $release->getId(), $releaseType->value, $release->getVersion(), $variant instanceof UnirefVariant ? ' ' . $variant->value : ''));
        $io->writeln('Run a worker with <info>bin/console messenger:consume import</info> and follow it with <info>bin/console annosynf:release:show ' . (int) $release->getId() . '</info>.');

        return Command::SUCCESS;
    }
}
