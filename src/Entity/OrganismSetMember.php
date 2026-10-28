<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un organismo di un {@see OrganismSet}, con il numero di proteine trovate nella release.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organism_set_member')]
#[ORM\UniqueConstraint(name: 'uq_organism_set_member', columns: ['organism_set_id', 'value'])]
class OrganismSetMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $matchedProteins = null;

    /**
     * @param string $value nome dell'organismo o taxon id; collation binaria, perché `name_exact` distingue
     *                      maiuscole e accenti
     */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly OrganismSet $organismSet,
        #[ORM\Column(type: Types::STRING, length: 512, options: ['collation' => 'utf8mb4_bin'])]
        private readonly string $value,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganismSet(): OrganismSet
    {
        return $this->organismSet;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getMatchedProteins(): ?int
    {
        return $this->matchedProteins;
    }

    public function recordMatches(int $proteins): void
    {
        $this->matchedProteins = $proteins;
    }
}
