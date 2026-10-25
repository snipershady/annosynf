<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\DashboardController;
use App\Controller\SecurityController;
use App\Entity\User;
use App\EventSubscriber\NotFoundSubscriber;
use App\Repository\UserRepository;
use App\Security\Altcha\AltchaChallengeFactory;
use App\Security\Altcha\AltchaDifficulties;
use App\Security\Altcha\AltchaDifficulty;
use App\Security\Altcha\AltchaPurpose;
use App\Security\Altcha\AltchaSecret;
use App\Security\Altcha\AltchaVerifier;
use App\Security\LoginAltchaListener;
use App\Security\RecordLastLoginListener;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityCheck\SecurityCheckGate;
use App\Security\SecurityHeadersListener;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FunctionalTestCase;
use App\Twig\SecurityExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(SecurityController::class)]
#[CoversClass(RecordLastLoginListener::class)]
#[UsesClass(DashboardController::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(SecurityHeadersListener::class)]
#[UsesClass(SecurityExtension::class)]
#[UsesClass(AltchaChallengeFactory::class)]
#[UsesClass(AltchaDifficulties::class)]
#[UsesClass(AltchaDifficulty::class)]
#[UsesClass(AltchaVerifier::class)]
#[UsesClass(NotFoundSubscriber::class)]
#[UsesClass(SecurityCheckCookie::class)]
#[UsesClass(SecurityCheckGate::class)]
#[UsesClass(LoginAltchaListener::class)]
#[UsesClass(AltchaSecret::class)]
final class SecurityControllerTest extends FunctionalTestCase
{
    private const string PASSWORD = 'correct horse battery';

    public function testSignInRecordsTheLastLoginAndSignOut(): void
    {
        $this->user();

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="_csrf_token"]'));

        $this->signIn(self::PASSWORD);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('nav summary', 'lab');
        self::assertSelectorTextContains('nav .dropdown-menu a[href="/logout"]', 'Sign out');
        self::assertSelectorTextContains('nav .dropdown-menu a[href="/account/password"]', 'Change password');
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'lab@example.org'])?->getLastLoginAt());

        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/', null, 'already signed in');

        $this->client->request('GET', '/logout');
        $this->client->followRedirect();
        self::assertSelectorTextContains('nav', 'Sign in');
    }

    public function testTheLoginPageCarriesTheProofOfWork(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertCount(1, $crawler->filter('altcha-widget[auto="onload"]'));
        self::assertCount(1, $crawler->filter('input[name="altcha"]'));
        self::assertSelectorExists('button[type="submit"][disabled]');
    }

    public function testSigningInWithoutTheSecurityCheckIsRefused(): void
    {
        $this->user();

        $this->client->request('POST', '/login', ['_username' => 'lab@example.org', '_password' => self::PASSWORD, '_csrf_token' => 'csrf-token'], [], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'The security check failed.');
        self::assertSelectorNotExists('a[href="/logout"]');
    }

    public function testASolutionCannotBeUsedTwice(): void
    {
        $this->user();
        $payload = $this->altchaPayload(AltchaPurpose::Login);

        $this->client->request('POST', '/login', ['_username' => 'lab@example.org', '_password' => 'wrong', '_csrf_token' => 'csrf-token', 'altcha' => $payload], [], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');

        $this->client->request('POST', '/login', ['_username' => 'lab@example.org', '_password' => self::PASSWORD, '_csrf_token' => 'csrf-token', 'altcha' => $payload], [], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'The security check failed.');
    }

    public function testWrongPasswordIsReported(): void
    {
        $this->user();

        $this->signIn('wrong password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public function testDisabledUsersSignInAndSeeTheWaitForApprovalMessage(): void
    {
        $user = $this->user();
        $user->deactivate();

        $this->entityManager->flush();

        $this->signIn(self::PASSWORD);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-info', 'Please wait for an administrator to enable your account.');
        self::assertSelectorNotExists('#dashboard-title');
    }

    private function signIn(string $password): void
    {
        $this->client->request('POST', '/login', ['_username' => 'lab@example.org', '_password' => $password, '_csrf_token' => 'csrf-token', 'altcha' => $this->altchaPayload(AltchaPurpose::Login)], [], ['HTTP_ORIGIN' => 'http://localhost']);
    }

    private function user(): User
    {
        $user = EntityFactory::user('lab@example.org', 'lab');
        $user->changePassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
