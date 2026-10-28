<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Reference\ReleaseStatus;
use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\UnirefVariant;
use App\Repository\ReferenceReleaseRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Release di riferimento (ex database MySQL `<db_type>_<YYYY_MM>` di Annocript): i suoi dati stanno nelle
 * partizioni `p<id>` delle tabelle `ref_*`.
 */
#[ORM\Entity(repositoryClass: ReferenceReleaseRepository::class)]
#[ORM\Table(name: 'reference_release')]
#[ORM\UniqueConstraint(name: 'uq_reference_release', columns: ['type', 'version', 'uniref_variant'])]
class ReferenceRelease
{
    /** Versione del formato dei dati di riferimento (ex `dbTablesVersion` 0.3 di Annocript). */
    public const string SCHEMA_VERSION = '1';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /**
     * Stringa vuota fuori dalla modalità uniref: con NULL il vincolo di unicità non varrebbe per le release
     * uniprotkb (MySQL considera distinti due NULL).
     */
    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $unirefVariant;

    #[ORM\Column(type: Types::STRING, length: 24, enumType: ReleaseStatus::class)]
    private ReleaseStatus $status = ReleaseStatus::Pending;

    #[ORM\Column(type: Types::STRING, length: 8)]
    private string $schemaVersion = self::SCHEMA_VERSION;

    /**
     * Versioni dei file sorgente che non seguono la release UniProt (ex `db_versions.txt`).
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $sourceVersions = [];

    /**
     * @var array<string, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $counts = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $readyAt = null;

    /**
     * @param string $version `YYYY_MM`, letta da `reldate.txt` o dalla release note UniRef
     *
     * @throws InvalidArgumentException se la versione non è `YYYY_MM` o la variante UniRef non corrisponde al tipo
     */
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 16, enumType: ReleaseType::class)]
        private readonly ReleaseType $type,
        #[ORM\Column(type: Types::STRING, length: 7, options: ['fixed' => true])]
        private readonly string $version,
        ?UnirefVariant $unirefVariant,
        #[ORM\Column(type: Types::STRING, length: 32)]
        private readonly string $cddName,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt,
    ) {
        if (preg_match('/^\d{4}_(0[1-9]|1[0-2])$/', $version) !== 1) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a release version (YYYY_MM).', $version));
        }

        if (($type === ReleaseType::UniRef) !== $unirefVariant instanceof UnirefVariant) {
            throw new InvalidArgumentException('A UniRef variant is required for uniref releases and only for them.');
        }

        $this->unirefVariant = $unirefVariant->value ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ReleaseType
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getUnirefVariant(): ?UnirefVariant
    {
        return UnirefVariant::tryFrom($this->unirefVariant);
    }

    public function getCddName(): string
    {
        return $this->cddName;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function getStatus(): ReleaseStatus
    {
        return $this->status;
    }

    /**
     * Marking store del workflow `reference_release` (config/packages/workflow.yaml): lo stato cambia solo con
     * `WorkflowInterface::apply()`, che passa qui il contesto della transizione. La prima uscita da `pending` segna
     * l'inizio, `ready` la fine; un nuovo stato cancella l'errore di un tentativo precedente.
     *
     * @param array<string, mixed> $context `at` (DateTimeImmutable) sempre, `error` (string) solo per `failed`
     *
     * @throws InvalidArgumentException se il contesto non porta l'ora o l'errore non accompagna `failed`
     */
    public function setStatus(ReleaseStatus $status, array $context = []): void
    {
        $at = $context['at'] ?? null;
        $error = $context['error'] ?? null;

        if (!$at instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('A release status change needs its time ("at" in the workflow context).');
        }

        if ($error !== null && !\is_string($error) || ($status === ReleaseStatus::Failed) !== ($error !== null)) {
            throw new InvalidArgumentException('An error message goes with the failed status, and only with it.');
        }

        $this->status = $status;
        $this->error = $error;

        if ($status !== ReleaseStatus::Pending) {
            $this->startedAt ??= $at;
        }

        $this->readyAt = $status === ReleaseStatus::Ready ? $at : null;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return array<string, string>
     */
    public function getSourceVersions(): array
    {
        // MySQL riordina le chiavi delle colonne JSON (prima per lunghezza): l'ordine alfabetico si ricrea qui
        $versions = $this->sourceVersions;
        ksort($versions);

        return $versions;
    }

    /**
     * @param string $source  `enzyme`, `pfam2go`, `go`, `rnacentral`, …
     * @param string $version come la riporta il file
     */
    public function recordSourceVersion(string $source, string $version): void
    {
        $this->sourceVersions[$source] = $version;
    }

    /**
     * @return array<string, int>|null
     */
    public function getCounts(): ?array
    {
        $counts = $this->counts;

        if ($counts !== null) {
            ksort($counts);
        }

        return $counts;
    }

    /**
     * @param array<string, int> $counts righe per tabella o tipo, per la UI e il confronto tra release
     */
    public function recordCounts(array $counts): void
    {
        $this->counts = $counts;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getReadyAt(): ?DateTimeImmutable
    {
        return $this->readyAt;
    }
}
