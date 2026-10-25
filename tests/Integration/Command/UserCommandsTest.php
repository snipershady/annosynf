<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateUserCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserRole;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(CreateUserCommand::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserRole::class)]
final class UserCommandsTest extends DatabaseTestCase
{
    private const string PASSWORD = 'correct horse battery';

    public function testAdministratorIsCreatedWithAHashedPassword(): void
    {
        $result = self::runCommand('annosynf:user:create', ['email' => 'admin@example.org', 'username' => 'admin', '--admin' => true], [self::PASSWORD, self::PASSWORD]);

        self::assertSame(Command::SUCCESS, $result->statusCode, $result->getDisplay());
        self::assertStringContainsString('Administrator "admin@example.org" created.', $result->getDisplay());
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.org']) ?? self::fail('user not saved');
        self::assertTrue($user->isAdmin());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::PASSWORD));
        self::assertStringNotContainsString(self::PASSWORD, $result->getDisplay(), 'the password is never echoed');
    }

    public function testUsersAreEnabledViewersUnlessAnotherLevelIsAsked(): void
    {
        foreach (['viewer' => [[], UserRole::Viewer, 'Viewer'], 'analyst' => [['--analyst' => true], UserRole::Analyst, 'Analyst']] as $name => [$options, $level, $label]) {
            $result = self::runCommand('annosynf:user:create', ['email' => $name . '@example.org', 'username' => $name, ...$options], [self::PASSWORD, self::PASSWORD]);

            self::assertStringContainsString(\sprintf('%s "%s@example.org" created.', $label, $name), $result->getDisplay());
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $name . '@example.org']) ?? self::fail('user not saved');
            self::assertTrue($user->isActive());
            self::assertTrue($user->isEmailVerified());
            self::assertSame($level, $user->getAccessLevel());
        }
    }

    public function testInvalidInputIsRejected(): void
    {
        $this->entityManager->persist(EntityFactory::user('taken@example.org', 'taken'));
        $this->entityManager->flush();

        $cases = [
            'invalid email' => [['email' => 'not-an-email', 'username' => 'x'], [], Command::INVALID],
            'empty username' => [['email' => 'x@example.org', 'username' => ' '], [], Command::INVALID],
            'existing email' => [['email' => 'taken@example.org', 'username' => 'new'], [], Command::FAILURE],
            'existing username' => [['email' => 'new@example.org', 'username' => 'taken'], [], Command::FAILURE],
            'short password' => [['email' => 'short@example.org', 'username' => 'short'], ['short', 'short'], Command::INVALID],
            'mismatch' => [['email' => 'mismatch@example.org', 'username' => 'mismatch'], [self::PASSWORD, self::PASSWORD . '!'], Command::INVALID],
        ];

        foreach ($cases as $case => [$input, $answers, $status]) {
            self::assertSame($status, self::runCommand('annosynf:user:create', $input, $answers)->statusCode, $case);
        }

        self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM `user`'));
    }
}
