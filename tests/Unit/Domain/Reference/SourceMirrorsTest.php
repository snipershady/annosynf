<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\SourceMirrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceMirrors::class)]
final class SourceMirrorsTest extends TestCase
{
    public function testUniProtFilesAreAlsoLookedForOnEbiAndExpasy(): void
    {
        $mirrors = new SourceMirrors();

        self::assertSame([
            'https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt',
            'https://ftp.ebi.ac.uk/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt',
            'https://ftp.expasy.org/databases/uniprot/current_release/knowledgebase/complete/reldate.txt',
        ], $mirrors->candidates('https://ftp.uniprot.org/pub/databases/uniprot/current_release/knowledgebase/complete/reldate.txt'));
        self::assertSame([
            'https://ftp.expasy.org/databases/uniprot/uniref/uniref90/uniref90.release_note',
            'https://ftp.uniprot.org/pub/databases/uniprot/uniref/uniref90/uniref90.release_note',
            'https://ftp.ebi.ac.uk/pub/databases/uniprot/uniref/uniref90/uniref90.release_note',
        ], $mirrors->candidates('https://ftp.expasy.org/databases/uniprot/uniref/uniref90/uniref90.release_note'), 'the requested server comes first');
    }

    public function testOtherSourcesHaveNoMirror(): void
    {
        self::assertSame(['https://ftp.expasy.org/databases/enzyme/enzyme.dat'], new SourceMirrors()->candidates('https://ftp.expasy.org/databases/enzyme/enzyme.dat'));
    }

    public function testGroupsAreConfigurable(): void
    {
        $mirrors = new SourceMirrors([['https://a.example/x/'], ['https://b.example/', 'https://c.example/y/']]);

        self::assertSame(['https://c.example/y/f.gz', 'https://b.example/f.gz'], $mirrors->candidates('https://c.example/y/f.gz'));
        self::assertSame(['https://a.example/x/f.gz'], $mirrors->candidates('https://a.example/x/f.gz'));
    }
}
