<?php

declare(strict_types=1);

namespace App\Infrastructure\Process;

use App\Application\Port\BlastDbBuilder;
use App\Domain\Blast\BlastDbInfo;
use App\Domain\Blast\BlastDbInfoParser;
use App\Infrastructure\Storage\GzipReader;
use InvalidArgumentException;
use Override;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Adapter di {@see BlastDbBuilder} su `makeblastdb` e `blastdbcmd`. Il FASTA arriva da stdin, decompresso al volo da
 * {@see GzipReader}: TrEMBL non si espande mai su disco (40 GB compressi). Come nel legacy, senza `-parse_seqids`.
 */
final readonly class BlastPlusDbBuilder implements BlastDbBuilder
{
    public function __construct(
        private BlastBinaryLocator $locator,
        private BlastProcessExecutor $executor,
        private BlastVersionReader $versionReader,
        private GzipReader $gzip,
    ) {
    }

    #[Override]
    public function build(string $fastaPath, string $databasePath, string $title, string $dbType): BlastDbInfo
    {
        $directory = \dirname($databasePath);
        new Filesystem()->mkdir($directory);

        $this->executor->execute(
            BlastTool::MakeBlastDb->value,
            [$this->locator->path(BlastTool::MakeBlastDb), '-in', '-', '-out', $databasePath, '-title', $title, '-dbtype', $dbType],
            $directory,
            input: $this->gzip->chunks($fastaPath),
        );

        return $this->inspect($databasePath, $dbType);
    }

    #[Override]
    public function inspect(string $databasePath, string $dbType): BlastDbInfo
    {
        $path = $this->locator->path(BlastTool::BlastDbCmd);
        $result = $this->executor->execute(BlastTool::BlastDbCmd->value, [$path, '-db', $databasePath, '-dbtype', $dbType, '-info'], \dirname($databasePath));

        try {
            $info = BlastDbInfoParser::parse($result->output);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw InvalidBlastDatabaseException::at($databasePath, $invalidArgumentException);
        }

        return new BlastDbInfo($info->sequences, $info->letters, $this->versionReader->read(BlastTool::BlastDbCmd));
    }
}
