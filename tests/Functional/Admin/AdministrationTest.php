<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\UserAdminController;
use App\Controller\DashboardController;
use App\Entity\User;
use App\EventSubscriber\NotFoundSubscriber;
use App\Form\Admin\UserAdminData;
use App\Form\Admin\UserAdminType;
use App\Repository\UserRepository;
use App\Security\Account\AccountMailer;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityCheck\SecurityCheckGate;
use App\Security\SecurityHeadersListener;
use App\Security\UserRole;
use App\Tests\Support\EntityFactory;
use App\Tests\Support\FunctionalTestCase;
use App\Twig\SecurityExtension;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(UserAdminController::class)]
#[CoversClass(UserAdminType::class)]
#[CoversClass(UserAdminData::class)]
#[UsesClass(DashboardController::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRole::class)]
#[UsesClass(AccountMailer::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(SecurityHeadersListener::class)]
#[UsesClass(SecurityExtension::class)]
#[UsesClass(NotFoundSubscriber::class)]
#[UsesClass(SecurityCheckCookie::class)]
#[UsesClass(SecurityCheckGate::class)]
final class AdministrationTest extends FunctionalTestCase
{
    private User $admin;

    private User $member;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = EntityFactory::user('admin@example.org', 'admin');
        $this->admin->changeAccessLevel(UserRole::Admin);

        $this->member = EntityFactory::user('member@example.org', 'member');

        foreach ([$this->admin, $this->member] as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function testAdministrationIsReservedToAdministrators(): void
    {
        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('/login');

        $analyst = EntityFactory::user('analyst@example.org', 'analyst');
        $analyst->changeAccessLevel(UserRole::Analyst);

        $this->entityManager->persist($analyst);
        $this->entityManager->flush();

        foreach ([$this->member, $analyst] as $user) {
            $this->client->loginUser($user);

            foreach (['/admin/users', '/admin/users/new', '/admin/users/' . $this->admin->getId()] as $page) {
                $this->client->request('GET', $page);
                self::assertResponseStatusCodeSame(403, $page);
            }

            $this->client->request('GET', '/');
            self::assertSelectorNotExists('a[href="/admin/users"]', 'no administration link for non administrators');
        }
    }

    public function testUsersAreCreatedWithTheChosenAccessLevel(): void
    {
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin/users/new');
        $form = $crawler->selectButton('Save')->form();
        $form['user_admin[email]'] = 'new@example.org';
        $form['user_admin[username]'] = 'newcomer';
        $form['user_admin[password][first]'] = 'correct horse battery';
        $form['user_admin[password][second]'] = 'correct horse battery';
        $this->choice($form, 'user_admin[accessLevel]')->select(UserRole::Analyst->value);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users');
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'new@example.org']) ?? self::fail('user not saved');
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'correct horse battery'));
        self::assertSame(UserRole::Analyst, $user->getAccessLevel());
        self::assertTrue($user->isActive());
        self::assertTrue($user->isEmailVerified(), 'an address entered by an administrator is trusted');

        $crawler = $this->client->request('GET', '/admin/users');
        self::assertStringContainsString('analyst', $crawler->filter('table tr:contains("newcomer")')->text());
    }

    public function testDuplicateUsersAndShortPasswordsAreRejected(): void
    {
        $this->client->loginUser($this->admin);

        foreach ([['member@example.org', 'someone', 'correct horse battery', 'already exists'], ['x@example.org', 'x', 'short', 'This value is too short.']] as [$email, $username, $password, $message]) {
            $crawler = $this->client->request('GET', '/admin/users/new');
            $form = $crawler->selectButton('Save')->form();
            $form['user_admin[email]'] = $email;
            $form['user_admin[username]'] = $username;
            $form['user_admin[password][first]'] = $password;
            $form['user_admin[password][second]'] = $password;
            $this->client->submit($form);

            self::assertResponseStatusCodeSame(422, $message);
            self::assertSelectorTextContains('form', $message);
        }
    }

    public function testUserIsDisabledPromotedAndGivenANewPassword(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/users/' . $this->member->getId());
        $form = $crawler->selectButton('Save')->form();
        $this->choice($form, 'user_admin[accessLevel]')->select(UserRole::Admin->value);
        $this->choice($form, 'user_admin[active]')->untick();
        $form['user_admin[password][first]'] = 'another long password';
        $form['user_admin[password][second]'] = 'another long password';
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users');
        $this->entityManager->clear();
        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'member@example.org']) ?? self::fail('user lost');
        self::assertTrue($member->hasRole(UserRole::Admin));
        self::assertFalse($member->isActive());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($member, 'another long password'));

        $crawler = $this->client->request('GET', '/admin/users/' . $member->getId());
        self::assertSame(UserRole::Admin->value, $crawler->filter('select[name="user_admin[accessLevel]"] option[selected]')->attr('value'));
    }

    public function testAnEmptyPasswordKeepsTheCurrentOne(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/users/' . $this->member->getId());
        $form = $crawler->selectButton('Save')->form();
        $this->choice($form, 'user_admin[accessLevel]')->select(UserRole::Viewer->value);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users');
        $this->entityManager->clear();
        self::assertSame('hashed-password', $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'member@example.org'])?->getPassword());
    }

    public function testAdministratorsCannotLockThemselvesOut(): void
    {
        $this->client->loginUser($this->admin);

        foreach (['demote' => static fn (self $test, Form $form) => $test->choice($form, 'user_admin[accessLevel]')->select(UserRole::Analyst->value), 'disable' => static fn (self $test, Form $form) => $test->choice($form, 'user_admin[active]')->untick()] as $case => $change) {
            $crawler = $this->client->request('GET', '/admin/users/' . $this->admin->getId());
            $form = $crawler->selectButton('Save')->form();
            $change($this, $form);
            $this->client->submit($form);

            self::assertResponseStatusCodeSame(422, $case);
            self::assertSelectorTextContains('form', 'You cannot remove your own administrator role');
        }
    }

    /**
     * Campo checkbox/select del form: DomCrawler restituisce un tipo unione molto ampio.
     */
    private function choice(Form $form, string $name): ChoiceFormField
    {
        $field = $form[$name];

        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }
}
