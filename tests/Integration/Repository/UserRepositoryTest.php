<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserRole;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(UserRepository::class)]
#[UsesClass(User::class)]
final class UserRepositoryTest extends DatabaseTestCase
{
    public function testUsersAreListedByEmailAndPasswordsUpgraded(): void
    {
        $zed = EntityFactory::user('zed@example.org', 'zed');
        $ada = EntityFactory::user('ada@example.org', 'ada');
        $this->persistAndReload($zed, $ada);
        $users = self::getContainer()->get(UserRepository::class);

        self::assertSame(['ada@example.org', 'zed@example.org'], array_map(static fn (User $user): string => $user->getEmail(), $users->findAllByEmail()));

        $loaded = $users->findOneBy(['email' => 'ada@example.org']) ?? self::fail('user not saved');
        $users->upgradePassword($loaded, 'new-hash');
        $this->entityManager->clear();

        self::assertSame('new-hash', $users->findOneBy(['email' => 'ada@example.org'])?->getPassword());
    }

    public function testAdministratorsAreOnlyTheEnabledOnes(): void
    {
        $admin = EntityFactory::user('admin@example.org', 'admin');
        $admin->grantRole(UserRole::Admin);

        $suspended = EntityFactory::user('suspended@example.org', 'suspended');
        $suspended->grantRole(UserRole::Admin);
        $suspended->deactivate();
        $this->persistAndReload($admin, $suspended, EntityFactory::user('plain@example.org', 'plain'));

        $emails = array_map(static fn (User $user): string => $user->getEmail(), self::getContainer()->get(UserRepository::class)->findAdministrators());

        self::assertSame(['admin@example.org'], $emails);
    }

    public function testOnlyApplicationUsersCanBeUpgraded(): void
    {
        $this->expectException(UnsupportedUserException::class);

        self::getContainer()->get(UserRepository::class)->upgradePassword(new InMemoryUser('memory', null), 'hash');
    }
}
