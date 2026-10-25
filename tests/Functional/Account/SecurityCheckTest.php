<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account;

use App\Controller\DashboardController;
use App\Controller\PasswordResetController;
use App\Controller\RegistrationController;
use App\Controller\SecurityCheckController;
use App\Controller\SecurityController;
use App\Entity\User;
use App\EventSubscriber\NotFoundSubscriber;
use App\Form\Account\ForgotPasswordType;
use App\Form\Account\NewPasswordType;
use App\Form\Account\RegistrationType;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use App\Security\Account\AccountMailer;
use App\Security\Account\EmailVerifier;
use App\Security\Account\NewUserNotifier;
use App\Security\Account\UserRegistrar;
use App\Security\Altcha\AltchaChallengeFactory;
use App\Security\Altcha\AltchaDifficulties;
use App\Security\Altcha\AltchaDifficulty;
use App\Security\Altcha\AltchaPurpose;
use App\Security\Altcha\AltchaSecret;
use App\Security\Altcha\AltchaVerifier;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityCheck\SecurityCheckGate;
use App\Security\SecurityHeadersListener;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FunctionalTestCase;
use App\Twig\SecurityExtension;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(SecurityCheckController::class)]
#[CoversClass(SecurityCheckGate::class)]
#[CoversClass(SecurityCheckCookie::class)]
#[CoversClass(AltchaVerifier::class)]
#[CoversClass(AltchaChallengeFactory::class)]
#[UsesClass(EmailVerifier::class)]
#[UsesClass(ForgotPasswordType::class)]
#[UsesClass(NewPasswordType::class)]
#[UsesClass(NewUserNotifier::class)]
#[UsesClass(RegistrationType::class)]
#[UsesClass(UserRegistrar::class)]
#[UsesClass(AltchaDifficulties::class)]
#[UsesClass(AltchaDifficulty::class)]
#[UsesClass(PasswordResetController::class)]
#[UsesClass(RegistrationController::class)]
#[UsesClass(DashboardController::class)]
#[UsesClass(SecurityController::class)]
#[UsesClass(User::class)]
#[UsesClass(NotFoundSubscriber::class)]
#[UsesClass(AccountMailer::class)]
#[UsesClass(ResetPasswordRequestRepository::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(SecurityHeadersListener::class)]
#[UsesClass(SecurityExtension::class)]
#[UsesClass(AltchaSecret::class)]
final class SecurityCheckTest extends FunctionalTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        // Questi test partono da un visitatore che non ha ancora superato la verifica.
        $this->client->getCookieJar()->clear();
    }

    public function testAnonymousVisitorsAreSentToTheSecurityCheckBeforeTheIndex(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/security-check');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Security check');
        self::assertCount(1, $crawler->filter('altcha-widget[auto="onload"]'));
        self::assertCount(1, $crawler->filter('input[name="altcha"]'));
        self::assertSelectorExists('script[type="module"][src="/js/altcha.min.js"][nonce]');
    }

    public function testASolvedChallengeGrantsAValidCookie(): void
    {
        $this->client->request('POST', '/security-check', ['altcha' => $this->altchaPayload(AltchaPurpose::SecurityCheck)]);

        self::assertResponseRedirects('/');
        self::assertNotNull($this->client->getCookieJar()->get(SecurityCheckCookie::NAME));

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#about-title', 'About annosynf');

        $this->client->request('GET', '/security-check');
        self::assertResponseRedirects('/', null, 'already checked');
    }

    public function testAnInvalidSolutionRestartsTheCheck(): void
    {
        $this->client->request('POST', '/security-check', ['altcha' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.alert-danger', 'The security check failed.');
        self::assertNull($this->client->getCookieJar()->get(SecurityCheckCookie::NAME));
    }

    public function testASolutionForAnotherPurposeIsNotEnough(): void
    {
        $this->client->request('POST', '/security-check', ['altcha' => $this->altchaPayload(AltchaPurpose::Login)]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSignedInUsersNeverSeeTheCheck(): void
    {
        $user = EntityFactory::user();
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/security-check');
        self::assertResponseRedirects('/');
    }

    public function testThePagesThatAreNotTheIndexDoNotNeedIt(): void
    {
        foreach (['/login', '/register', '/reset-password'] as $path) {
            $this->client->request('GET', $path);

            self::assertResponseIsSuccessful($path);
        }
    }
}
