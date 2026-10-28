<?php

declare(strict_types=1);

namespace App\Release;

use App\Application\Port\OrganismLookup;
use App\Domain\Reference\OrganismMatchMode;
use App\Domain\Reference\ReleaseStatus;
use App\Domain\Shared\AnnosynfException;
use App\Entity\OrganismSet;
use App\Entity\ReferenceRelease;
use App\Entity\User;
use App\Message\BuildOrganismSetDatabases;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Crea un insieme di organismi di una release (ex file `blastedOrganism`, §A.7.10), conta le proteine di ogni
 * organismo e accoda la costruzione dei database BLAST ristretti.
 */
final readonly class OrganismSetCreator
{
    private const int MAX_NAME_LENGTH = 128;

    public function __construct(
        private OrganismLookup $lookup,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param list<string> $values nome o taxon id, secondo `$mode`; spazi ai bordi, righe vuote e doppioni si ignorano
     *
     * @throws AnnosynfException se la release non è pronta, il nome non è valido o già usato, la lista è vuota o
     *                           nessun organismo corrisponde a una proteina
     */
    public function create(ReferenceRelease $release, string $name, OrganismMatchMode $mode, array $values, ?User $createdBy = null): OrganismSet
    {
        $releaseId = (int) $release->getId();
        $name = trim($name);

        if ($release->getStatus() !== ReleaseStatus::Ready) {
            throw OrganismSetNotCreatedException::releaseNotReady($releaseId, $release->getStatus()->value);
        }

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw OrganismSetNotCreatedException::invalidName($name);
        }

        if ($this->entityManager->getRepository(OrganismSet::class)->findOneBy(['release' => $release, 'name' => $name]) instanceof OrganismSet) {
            throw OrganismSetNotCreatedException::exists($name, $releaseId);
        }

        $members = $this->members($mode, $values);
        $set = new OrganismSet($release, $name, $mode, $this->clock->now(), $createdBy);
        $total = 0;

        foreach ($members as $value) {
            $set->addMember($value);
        }

        foreach ($set->getMembers() as $member) {
            $matches = $this->lookup->countProteins($releaseId, $mode, $member->getValue());
            $member->recordMatches($matches);
            $total += $matches;
        }

        if ($total === 0) {
            throw OrganismSetNotCreatedException::noProteins();
        }

        $this->entityManager->persist($set);
        $this->entityManager->flush();

        $this->bus->dispatch(new BuildOrganismSetDatabases((int) $set->getId()));

        return $set;
    }

    /**
     * @param list<string> $values
     *
     * @return non-empty-list<string>
     */
    private function members(OrganismMatchMode $mode, array $values): array
    {
        $members = array_values(array_unique(array_filter(array_map(trim(...), $values), static fn (string $value): bool => $value !== '')));

        if ($members === []) {
            throw OrganismSetNotCreatedException::noOrganisms();
        }

        foreach ($members as $value) {
            if ($mode === OrganismMatchMode::TaxonId && !ctype_digit($value)) {
                throw OrganismSetNotCreatedException::notTaxonIds($value);
            }
        }

        return $members;
    }
}
