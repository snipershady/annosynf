<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\DashboardController;
use App\Entity\User;
use App\EventSubscriber\NotFoundSubscriber;
use App\Repository\UserRepository;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityCheck\SecurityCheckGate;
use App\Security\SecurityHeadersListener;
use App\Security\UserRole;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FunctionalTestCase;
use App\Twig\SecurityExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DashboardController::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRole::class)]
#[UsesClass(NotFoundSubscriber::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(SecurityCheckCookie::class)]
#[UsesClass(SecurityCheckGate::class)]
#[UsesClass(SecurityHeadersListener::class)]
#[UsesClass(SecurityExtension::class)]
final class DashboardTest extends FunctionalTestCase
{
    public function testAnonymousVisitorsSeeTheProjectDescriptionOnly(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#about-title', 'About annosynf');
        self::assertSelectorTextContains('main', 'de novo');
        self::assertSelectorExists('main a[href="/login"]');
        self::assertSelectorExists('main a[href="/register"]');
        self::assertSelectorNotExists('#dashboard-title');
    }

    public function testAnonymousVisitorsPassTheSecurityCheckFirst(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/security-check');
    }

    public function testEveryAccessLevelSeesTheDashboard(): void
    {
        foreach (UserRole::accessLevels() as $level) {
            $user = EntityFactory::user($level->label() . '@example.org', 'user_' . $level->label());
            $user->changeAccessLevel($level);
            $this->client->loginUser($this->persisted($user));

            $this->client->request('GET', '/');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#dashboard-title');
            self::assertSelectorNotExists('#about-title');
            self::assertCount($level === UserRole::Admin ? 1 : 0, $this->client->getCrawler()->filter('nav a[href="/admin/users"]'), $level->label());
        }
    }

    public function testDisabledUsersAreAskedToWaitForAnAdministrator(): void
    {
        $user = EntityFactory::user();
        $user->changeAccessLevel(UserRole::Admin);
        $user->deactivate();

        $this->client->loginUser($this->persisted($user));
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-info', 'Please wait for an administrator to enable your account.');
        self::assertSelectorNotExists('.alert-warning');
        self::assertSelectorNotExists('#dashboard-title');

        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403, 'a disabled administrator keeps no privilege');
    }

    public function testUnconfirmedAddressesAreAskedToConfirmTheEmail(): void
    {
        $this->client->loginUser($this->persisted(EntityFactory::pendingUser()));
        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.alert-warning', 'Confirm your email address');
        self::assertSelectorTextContains('.alert-info', 'Please wait for an administrator to enable your account.');
        self::assertSelectorExists('form[action="/account/verify-email/resend"]');
    }

    private function persisted(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
