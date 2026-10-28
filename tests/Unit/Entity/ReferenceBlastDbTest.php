<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Reference\BlastDbKind;
use App\Domain\Reference\OrganismMatchMode;
use App\Entity\OrganismSet;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceRelease;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferenceBlastDb::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(BlastDbKind::class)]
#[UsesClass(OrganismSet::class)]
final class ReferenceBlastDbTest extends TestCase
{
    public function testADatabaseKnowsItsTypeFromItsKind(): void
    {
        $release = EntityFactory::release();
        $database = new ReferenceBlastDb($release, BlastDbKind::Cdd, 'Cdd', '/blastdb/1/Cdd', 71_136, 20_000_000, '2.17.0+', EntityFactory::now());

        self::assertNull($database->getId());
        self::assertSame($release, $database->getRelease());
        self::assertSame(BlastDbKind::Cdd, $database->getKind());
        self::assertSame('rps', $database->getDbType());
        self::assertSame('Cdd', $database->getName());
        self::assertSame('/blastdb/1/Cdd', $database->getPath());
        self::assertSame(71_136, $database->getNumSequences());
        self::assertSame(20_000_000, $database->getNumLetters());
        self::assertSame('2.17.0+', $database->getBlastVersion());
        self::assertSame(EntityFactory::NOW, $database->getBuiltAt()->format('Y-m-d H:i:s'));
        self::assertNull($database->getOrganismSet());

        $set = new OrganismSet($release, 'model organisms', OrganismMatchMode::ExactName, EntityFactory::now());
        $subset = new ReferenceBlastDb($release, BlastDbKind::SwissProt, 'sp_set1', '/blastdb/1/sp_set1', 10, 4_000, '2.17.0+', EntityFactory::now(), $set);

        self::assertSame($set, $subset->getOrganismSet());
        self::assertSame('prot', $subset->getDbType());
    }
}
