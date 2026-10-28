<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reference;

use App\Domain\Reference\BlastDbKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlastDbKind::class)]
final class BlastDbKindTest extends TestCase
{
    public function testTheDatabaseTypeFollowsTheKind(): void
    {
        $types = [];

        foreach (BlastDbKind::cases() as $kind) {
            $types[$kind->value] = $kind->dbType();
        }

        self::assertSame(['sp' => 'prot', 'tr' => 'prot', 'uniref' => 'prot', 'ncrna' => 'nucl', 'cdd' => 'rps'], $types);
    }
}
