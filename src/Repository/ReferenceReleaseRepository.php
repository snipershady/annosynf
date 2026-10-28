<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Entity\ReferenceTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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

    /**
     * @return list<ReferenceRelease> in ordine di creazione
     */
    public function findAllById(): array
    {
        /** @var list<ReferenceRelease> $releases */
        $releases = $this->createQueryBuilder('r')->orderBy('r.id', SortDirection::Ascending)->getQuery()->getResult();

        return $releases;
    }

    /**
     * @return list<ReferenceTask> nell'ordine del piano, che è quello di inserimento
     */
    public function tasks(ReferenceRelease $release): array
    {
        /** @var list<ReferenceTask> $tasks */
        $tasks = $this->getEntityManager()->createQueryBuilder()->select('t')->from(ReferenceTask::class, 't')
            ->where('t.release = :release')->setParameter('release', $release)
            ->orderBy('t.id', SortDirection::Ascending)->getQuery()->getResult();

        return $tasks;
    }

    /**
     * @return list<ReferenceSourceFile> nell'ordine del catalogo delle sorgenti
     */
    public function sources(ReferenceRelease $release): array
    {
        /** @var list<ReferenceSourceFile> $sources */
        $sources = $this->getEntityManager()->createQueryBuilder()->select('s')->from(ReferenceSourceFile::class, 's')
            ->where('s.release = :release')->setParameter('release', $release)
            ->orderBy('s.id', SortDirection::Ascending)->getQuery()->getResult();

        return $sources;
    }
}
