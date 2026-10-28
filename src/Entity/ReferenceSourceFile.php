<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SourceKind;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * File sorgente di una release: da dove è stato scaricato, dove sta e con quale sha256.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reference_source_file')]
#[ORM\UniqueConstraint(name: 'uq_reference_source_file', columns: ['release_id', 'kind'])]
class ReferenceSourceFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $localPath = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private ?int $bytes = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['fixed' => true])]
    private ?string $sha256 = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $downloadedAt = null;

    /** File o directory ottenuti dalla decompressione, se il file si usa decompresso. */
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $extractedPath = null;

    /**
     * @param string $url URL configurato; dopo il download, quello da cui è arrivato davvero (un mirror)
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly ReferenceRelease $release,
        #[ORM\Column(type: Types::STRING, length: 32, enumType: SourceKind::class)]
        private readonly SourceKind $kind,
        #[ORM\Column(type: Types::STRING, length: 1024)]
        private string $url,
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

    public function getKind(): SourceKind
    {
        return $this->kind;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function recordDownload(SourceFile $file, DateTimeImmutable $at): void
    {
        $this->url = $file->url;
        $this->localPath = $file->path;
        $this->bytes = $file->bytes;
        $this->sha256 = $file->sha256;
        $this->downloadedAt = $at;
        $this->extractedPath = null;
    }

    public function isDownloaded(): bool
    {
        return $this->downloadedAt instanceof DateTimeImmutable;
    }

    public function getLocalPath(): ?string
    {
        return $this->localPath;
    }

    public function getBytes(): ?int
    {
        return $this->bytes;
    }

    public function getSha256(): ?string
    {
        return $this->sha256;
    }

    public function getDownloadedAt(): ?DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function recordExtraction(string $path): void
    {
        $this->extractedPath = $path;
    }

    public function getExtractedPath(): ?string
    {
        return $this->extractedPath;
    }
}
