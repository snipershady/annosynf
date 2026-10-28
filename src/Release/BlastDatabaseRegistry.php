<?php

declare(strict_types=1);

namespace App\Release;

use App\Domain\Blast\BlastDbInfo;
use App\Domain\Reference\BlastDbKind;
use App\Entity\OrganismSet;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Registra i database BLAST di una release. Un solo record per nome: riesequire un task sostituisce il precedente.
 */
final readonly class BlastDatabaseRegistry
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function register(ReferenceRelease $release, BlastDbKind $kind, string $name, string $path, BlastDbInfo $info, ?OrganismSet $organismSet = null): void
    {
        $previous = $this->entityManager->getRepository(ReferenceBlastDb::class)->findOneBy(['release' => $release, 'name' => $name]);

        if ($previous instanceof ReferenceBlastDb) {
            $this->entityManager->remove($previous);
            $this->entityManager->flush();
        }

        $this->entityManager->persist(new ReferenceBlastDb($release, $kind, $name, $path, $info->sequences, $info->letters, $info->version ?? 'unknown', $this->clock->now(), $organismSet));
    }
}
