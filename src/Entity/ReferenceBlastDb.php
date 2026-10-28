<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Reference\BlastDbKind;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Database BLAST di una release, completo o ristretto a un insieme di organismi (ex `SP_<file>.fasta`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'reference_blast_db')]
#[ORM\UniqueConstraint(name: 'uq_reference_blast_db', columns: ['release_id', 'name'])]
class ReferenceBlastDb
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * @param string $name         nome del database, unico nella release (`uniprot_sprot`, `Cdd`, `sp_set12`)
     * @param string $path         percorso da passare a `-db`, senza estensione
     * @param string $blastVersion versione di BLAST+ che l'ha costruito o verificato
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly ReferenceRelease $release,
        #[ORM\Column(type: Types::STRING, length: 16, enumType: BlastDbKind::class)]
        private readonly BlastDbKind $kind,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $name,
        #[ORM\Column(type: Types::STRING, length: 512)]
        private readonly string $path,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private readonly int $numSequences,
        #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
        private readonly int $numLetters,
        #[ORM\Column(type: Types::STRING, length: 32)]
        private readonly string $blastVersion,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $builtAt,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private readonly ?OrganismSet $organismSet = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelease(): ReferenceRelease
    {
        return $this->release;
    }

    public function getKind(): BlastDbKind
    {
        return $this->kind;
    }

    /**
     * `prot`, `nucl` o `rps`: dipende solo dal tipo, quindi non si salva.
     */
    public function getDbType(): string
    {
        return $this->kind->dbType();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getNumSequences(): int
    {
        return $this->numSequences;
    }

    public function getNumLetters(): int
    {
        return $this->numLetters;
    }

    public function getBlastVersion(): string
    {
        return $this->blastVersion;
    }

    public function getBuiltAt(): DateTimeImmutable
    {
        return $this->builtAt;
    }

    public function getOrganismSet(): ?OrganismSet
    {
        return $this->organismSet;
    }
}
