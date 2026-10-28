<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Reference\OrganismMatchMode;
use App\Entity\OrganismSet;
use App\Entity\OrganismSetMember;
use App\Entity\ReferenceRelease;
use App\Entity\User;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganismSet::class)]
#[CoversClass(OrganismSetMember::class)]
#[UsesClass(ReferenceRelease::class)]
#[UsesClass(User::class)]
final class OrganismSetTest extends TestCase
{
    public function testMembersKeepTheirOrderAndAreNotRepeated(): void
    {
        $release = EntityFactory::release();
        $user = EntityFactory::user();
        $set = new OrganismSet($release, 'mammals', OrganismMatchMode::CaseInsensitiveName, EntityFactory::now(), $user);

        $set->addMember('Homo sapiens');
        $set->addMember('Mus musculus');
        $set->addMember('Homo sapiens');
        $set->addMember('homo sapiens');

        self::assertNull($set->getId());
        self::assertSame($release, $set->getRelease());
        self::assertSame('mammals', $set->getName());
        self::assertSame(OrganismMatchMode::CaseInsensitiveName, $set->getMatchMode());
        self::assertSame(EntityFactory::NOW, $set->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame($user, $set->getCreatedBy());
        self::assertSame(
            ['Homo sapiens', 'Mus musculus', 'homo sapiens'],
            array_map(static fn (OrganismSetMember $member): string => $member->getValue(), $set->getMembers()),
        );

        $member = $set->getMembers()[0];

        self::assertNull($member->getId());
        self::assertSame($set, $member->getOrganismSet());
        self::assertNull($member->getMatchedProteins());

        $member->recordMatches(20_421);
        self::assertSame(20_421, $member->getMatchedProteins());
    }
}
