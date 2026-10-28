<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Reference\OrganismMatchMode;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;

/**
 * Insieme di organismi su cui restringere BLAST (ex file `blastedOrganism`, §A.7.10).
 */
#[ORM\Entity]
#[ORM\Table(name: 'organism_set')]
#[ORM\UniqueConstraint(name: 'uq_organism_set', columns: ['release_id', 'name'])]
class OrganismSet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * @var Collection<int, OrganismSetMember>
     */
    #[ORM\OneToMany(targetEntity: OrganismSetMember::class, mappedBy: 'organismSet', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => SortDirection::Ascending])]
    private Collection $members;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly ReferenceRelease $release,
        #[ORM\Column(type: Types::STRING, length: 128)]
        private readonly string $name,
        #[ORM\Column(type: Types::STRING, length: 16, enumType: OrganismMatchMode::class)]
        private readonly OrganismMatchMode $matchMode,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private readonly ?User $createdBy = null,
    ) {
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelease(): ReferenceRelease
    {
        return $this->release;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMatchMode(): OrganismMatchMode
    {
        return $this->matchMode;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * Aggiunge un organismo (nome o taxon id, secondo la modalità); un valore già presente si ignora.
     */
    public function addMember(string $value): void
    {
        foreach ($this->members as $member) {
            if ($member->getValue() === $value) {
                return;
            }
        }

        $this->members->add(new OrganismSetMember($this, $value));
    }

    /**
     * @return list<OrganismSetMember> nell'ordine di inserimento
     */
    public function getMembers(): array
    {
        return array_values($this->members->toArray());
    }
}
