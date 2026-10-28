<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Entity\ReferenceRelease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReferenceRelease>
 */
final class ReferenceReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReferenceRelease::class);
    }

    /**
     * La release con la stessa identità (tipo, versione, variante UniRef), che non si può importare due volte.
     */
    public function findOneByIdentity(ReleaseType $type, string $version, ?UnirefVariant $unirefVariant): ?ReferenceRelease
    {
        return $this->findOneBy(['type' => $type, 'version' => $version, 'unirefVariant' => $unirefVariant->value ?? '']);
    }
}
