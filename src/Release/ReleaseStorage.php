<?php

declare(strict_types=1);

namespace App\Release;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * File delle release sotto `ANNOSYNF_STORAGE_DIR` (ex `data/<YYYY_MM>/` di Annocript): `releases/<id>/`, con un file
 * per sorgente, chiamato come sul server.
 */
final readonly class ReleaseStorage
{
    public function __construct(
        #[Autowire('%env(resolve:ANNOSYNF_STORAGE_DIR)%')]
        private string $root,
        #[Autowire('%env(resolve:BLAST_DB_DIR)%')]
        private string $blastDbRoot,
    ) {
    }

    public function directory(int $releaseId): string
    {
        return \sprintf('%s/releases/%d', $this->root, $releaseId);
    }

    /**
     * Dove stanno i database BLAST della release, sotto BLAST_DB_DIR: lo spazio è molto più grande di quello dei
     * dati (TrEMBL), quindi può stare su un volume a parte.
     */
    public function databasePath(int $releaseId, string $name): string
    {
        return $this->databaseDirectory($releaseId) . '/' . $name;
    }

    public function databaseDirectory(int $releaseId): string
    {
        return \sprintf('%s/release-%d', $this->blastDbRoot, $releaseId);
    }

    /**
     * Dove si salva la sorgente scaricata da `$url`.
     */
    public function sourcePath(int $releaseId, string $url): string
    {
        return $this->directory($releaseId) . '/' . basename((string) parse_url($url, \PHP_URL_PATH));
    }

    /**
     * Directory nuova e vuota per i file che servono prima che la release esista (la release note).
     */
    public function temporaryDirectory(): string
    {
        $directory = \sprintf('%s/tmp/release-%s', $this->root, bin2hex(random_bytes(6)));
        new Filesystem()->mkdir($directory);

        return $directory;
    }

    public function remove(string $path): void
    {
        new Filesystem()->remove($path);
    }

    public function move(string $from, string $to): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($to));
        $filesystem->rename($from, $to, true);
    }
}
