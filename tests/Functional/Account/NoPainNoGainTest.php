<?php

declare(strict_types=1);

namespace App\Tests\Functional\Account;

use App\Controller\NoPainNoGainController;
use App\EventSubscriber\NotFoundSubscriber;
use App\Security\Altcha\AltchaChallengeFactory;
use App\Security\Altcha\AltchaDifficulties;
use App\Security\Altcha\AltchaDifficulty;
use App\Security\Altcha\AltchaSecret;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityHeadersListener;
use App\Tests\Support\FunctionalTestCase;
use App\Twig\SecurityExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(NoPainNoGainController::class)]
#[CoversClass(NotFoundSubscriber::class)]
#[CoversClass(AltchaChallengeFactory::class)]
#[UsesClass(AltchaDifficulties::class)]
#[UsesClass(AltchaDifficulty::class)]
#[UsesClass(SecurityCheckCookie::class)]
#[UsesClass(SecurityHeadersListener::class)]
#[UsesClass(SecurityExtension::class)]
#[UsesClass(AltchaSecret::class)]
final class NoPainNoGainTest extends FunctionalTestCase
{
    public function testUnknownRoutesEndUpOnTheTrapPage(): void
    {
        foreach (['/wp-login.php', '/.env', '/admin/unknown/section', '/analysis/not-an-id'] as $path) {
            $this->client->request('GET', $path);

            self::assertResponseRedirects('/nopainnogain', null, $path);
        }
    }

    public function testTheTrapPageIsABlankPageWithAProofOfWork(): void
    {
        $crawler = $this->client->request('GET', '/nopainnogain');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
        self::assertCount(1, $crawler->filter('altcha-widget[auto="onload"]'));
        self::assertSelectorExists('meta[name="csp-nonce"]');
        self::assertSelectorNotExists('nav', 'no navigation, no links to follow');
        self::assertCount(1, $crawler->filter('[data-altcha-redirect-value="/"]'));
    }

    public function testTheTrapPageDoesNotRedirectOnItsOwn(): void
    {
        $this->client->request('GET', '/nopainnogain');

        self::assertResponseStatusCodeSame(200);
    }
}
