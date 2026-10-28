<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\OrganismSet;
use App\Message\BuildOrganismSetDatabases;
use App\Release\OrganismSetDatabaseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Costruisce i database ristretti di un insieme di organismi. Il lock impedisce che due worker scrivano gli stessi
 * file; un errore passa al transport dei messaggi falliti (il transport `import` non ritenta: la scansione di TrEMBL
 * dura ore).
 */
#[AsMessageHandler]
final readonly class BuildOrganismSetDatabasesHandler
{
    private const float LOCK_TTL = 12 * 3600.0;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrganismSetDatabaseBuilder $builder,
        private LockFactory $locks,
    ) {
    }

    public function __invoke(BuildOrganismSetDatabases $message): void
    {
        $lock = $this->locks->createLock('annosynf-organism-set-' . $message->organismSetId, self::LOCK_TTL);

        if (!$lock->acquire()) {
            return;
        }

        try {
            $set = $this->entityManager->find(OrganismSet::class, $message->organismSetId);

            if ($set instanceof OrganismSet) {
                $this->builder->build($set);
            }
        } finally {
            $lock->release();
        }
    }
}
