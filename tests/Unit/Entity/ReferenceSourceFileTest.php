<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SourceKind;
use App\Entity\ReferenceRelease;
use App\Entity\ReferenceSourceFile;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceSourceFile::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(SourceFile::class)]
final class ReferenceSourceFileTest extends TestCase
{
    private const string URL = 'https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt';

    public function testADownloadRecordsWhereTheFileCameFromAndWhereItIs(): void
    {
        $release = EntityFactory::release();
        $file = new ReferenceSourceFile($release, SourceKind::ReleaseDate, self::URL);

        self::assertNull($file->getId());
        self::assertSame($release, $file->getRelease());
        self::assertSame(SourceKind::ReleaseDate, $file->getKind());
        self::assertSame(self::URL, $file->getUrl());
        self::assertFalse($file->isDownloaded());
        self::assertNull($file->getLocalPath());
        self::assertNull($file->getBytes());
        self::assertNull($file->getSha256());
        self::assertNull($file->getDownloadedAt());

        $file->recordExtraction('/data/old');
        $mirror = str_replace('ftp.uniprot.org/pub', 'ftp.ebi.ac.uk/pub', self::URL);
        $file->recordDownload(new SourceFile($mirror, '/data/reldate.txt', 151, str_repeat('a', 64), false), EntityFactory::now());

        self::assertTrue($file->isDownloaded());
        self::assertSame($mirror, $file->getUrl());
        self::assertSame('/data/reldate.txt', $file->getLocalPath());
        self::assertSame(151, $file->getBytes());
        self::assertSame(str_repeat('a', 64), $file->getSha256());
        self::assertSame(EntityFactory::NOW, $file->getDownloadedAt()?->format('Y-m-d H:i:s'));
        self::assertNull($file->getExtractedPath(), 'a new download invalidates the previous extraction');

        $file->recordExtraction('/data/reldate');
        self::assertSame('/data/reldate', $file->getExtractedPath());
    }
}
