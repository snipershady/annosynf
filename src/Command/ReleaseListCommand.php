<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ReferenceRelease;
use App\Repository\ReferenceReleaseRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'annosynf:release:list',
    description: 'Lists the reference releases with their status',
)]
final readonly class ReleaseListCommand
{
    public function __construct(
        private ReferenceReleaseRepository $releases,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $releases = $this->releases->findAllById();

        if ($releases === []) {
            $io->writeln('No release yet: create one with <info>bin/console annosynf:release:create</info>.');

            return Command::SUCCESS;
        }

        ConsoleTable::render($io, ['Id', 'Type', 'Version', 'UniRef', 'CDD', 'Status', 'Created', 'Ready'], array_map(
            static fn (ReferenceRelease $release): array => [
                (int) $release->getId(),
                $release->getType()->value,
                $release->getVersion(),
                $release->getUnirefVariant()->value ?? '–',
                $release->getCddName(),
                $release->getStatus()->value,
                $release->getCreatedAt()->format('Y-m-d H:i'),
                $release->getReadyAt()?->format('Y-m-d H:i') ?? '–',
            ],
            $releases,
        ));

        return Command::SUCCESS;
    }
}
