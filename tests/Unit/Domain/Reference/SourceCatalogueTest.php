<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\ReleaseType;
use App\Domain\Reference\SourceCatalogue;
use App\Domain\Reference\SourceKind;
use App\Domain\Reference\UnirefVariant;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceCatalogue::class)]
final class SourceCatalogueTest extends TestCase
{
    public function testUniRefUrlsNameTheVariant(): void
    {
        $catalogue = new SourceCatalogue(self::urls());

        self::assertSame('https://example.org/enzyme', $catalogue->url(SourceKind::Enzyme));
        self::assertSame('https://example.org/enzyme', $catalogue->url(SourceKind::Enzyme, UnirefVariant::UniRef50));
        self::assertSame('https://example.org/uniref100/uniref100.fasta.gz', $catalogue->url(SourceKind::UniRef, UnirefVariant::UniRef100));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The "uniref" source depends on the UniRef variant.');

        $catalogue->url(SourceKind::UniRef);
    }

    public function testEverySourceNeedsAnHttpsUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The "pfam2go" source needs an HTTPS URL.');

        new SourceCatalogue(['pfam2go' => 'ftp://ftp.geneontology.org/pfam2go'] + self::urls());
    }

    public function testAMissingSourceIsRejected(): void
    {
        $urls = self::urls();
        unset($urls['speclist']);

        $this->expectException(InvalidArgumentException::class);

        new SourceCatalogue($urls);
    }

    public function testEachReleaseTypeReadsItsVersionAndItsSecondDatabase(): void
    {
        $uniprot = SourceCatalogue::sourcesFor(ReleaseType::UniProtKb);
        $uniref = SourceCatalogue::sourcesFor(ReleaseType::UniRef);

        self::assertSame(SourceKind::ReleaseDate, SourceCatalogue::versionSource(ReleaseType::UniProtKb));
        self::assertSame(SourceKind::UnirefReleaseNote, SourceCatalogue::versionSource(ReleaseType::UniRef));
        self::assertContains(SourceKind::TrEmbl, $uniprot);
        self::assertNotContains(SourceKind::UniRef, $uniprot);
        self::assertContains(SourceKind::UniRef, $uniref);
        self::assertNotContains(SourceKind::TrEmbl, $uniref);
        self::assertCount(12, $uniprot);
        self::assertCount(12, $uniref);
    }

    /**
     * @return array<string, string>
     */
    public static function urls(): array
    {
        $urls = [];

        foreach (SourceKind::cases() as $kind) {
            $urls[$kind->value] = 'https://example.org/' . $kind->value;
        }

        return ['uniref' => 'https://example.org/{uniref}/{uniref}.fasta.gz'] + $urls;
    }
}
