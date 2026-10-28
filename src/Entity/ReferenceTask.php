<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Reference\TaskStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Task di import di una release (§B.5.1). Sostituisce la tabella `goodTables` di Annocript, che segnava solo le
 * tabelle completate: qui ogni task ha stato, tentativi, durata e righe caricate, e si riprende dal primo non
 * completato.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reference_task')]
#[ORM\UniqueConstraint(name: 'uq_reference_task', columns: ['release_id', 'task'])]
class ReferenceTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::Pending;

    #[ORM\Column(type: Types::SMALLINT, options: ['unsigned' => true])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private ?int $rowsLoaded = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    /**
     * @param string $task `import_go`, `import_proteins:sp`, `download:trembl`, …
     *
     * @throws InvalidArgumentException se il nome non è nella forma `nome` o `nome:variante`
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly ReferenceRelease $release,
        #[ORM\Column(type: Types::STRING, length: 48)]
        private readonly string $task,
    ) {
        if (\strlen($task) > 48 || preg_match('/^[a-z][a-z_]*(:[a-z0-9][a-z0-9_]*)?$/', $task) !== 1) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid task name.', $task));
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRelease(): ReferenceRelease
    {
        return $this->release;
    }

    public function getTask(): string
    {
        return $this->task;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Nuovo tentativo: azzera l'esito di quello precedente.
     */
    public function start(DateTimeImmutable $at): void
    {
        $this->status = TaskStatus::Running;
        ++$this->attempts;
        $this->startedAt = $at;
        $this->finishedAt = null;
        $this->rowsLoaded = null;
        $this->message = null;
    }

    public function complete(DateTimeImmutable $at, ?int $rowsLoaded = null, ?string $message = null): void
    {
        $this->status = TaskStatus::Done;
        $this->finishedAt = $at;
        $this->rowsLoaded = $rowsLoaded;
        $this->message = $message;
    }

    public function fail(DateTimeImmutable $at, string $message): void
    {
        $this->status = TaskStatus::Failed;
        $this->finishedAt = $at;
        $this->message = $message;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getRowsLoaded(): ?int
    {
        return $this->rowsLoaded;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
