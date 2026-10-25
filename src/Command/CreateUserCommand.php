<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Account\PasswordPolicy;
use App\Security\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'annosynf:user:create',
    description: 'Creates an enabled user (viewer by default, --analyst or --admin for more rights); the password is asked without echoing it',
)]
final readonly class CreateUserCommand
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Email address, used to sign in')]
        string $email,
        #[Argument('Name shown in the interface')]
        string $username,
        #[Option('Grant the analyst role (create and manage analyses)')]
        bool $analyst = false,
        #[Option('Grant the administrator role (releases, users and every analysis)')]
        bool $admin = false,
    ): int {
        $errors = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);

        if (\count($errors) > 0 || trim($username) === '') {
            $io->error('Enter a valid email address and a non-empty username.');

            return Command::INVALID;
        }

        if ($this->users->findOneBy(['email' => $email]) instanceof User || $this->users->findOneBy(['username' => $username]) instanceof User) {
            $io->error('A user with this email or username already exists.');

            return Command::FAILURE;
        }

        // Mai come argomento: finirebbe nella history della shell e nell'elenco dei processi.
        $password = $io->askHidden(\sprintf('Password (at least %d characters)', PasswordPolicy::MIN_LENGTH));
        $confirmation = $io->askHidden('Repeat the password');

        if (!\is_string($password) || \strlen($password) < PasswordPolicy::MIN_LENGTH || $password !== $confirmation) {
            $io->error(\sprintf('The passwords must match and be at least %d characters long.', PasswordPolicy::MIN_LENGTH));

            return Command::INVALID;
        }

        $user = new User($email, trim($username), 'not-set', $this->clock->now());
        $user->changePassword($this->passwordHasher->hashPassword($user, $password));

        // Creato da un amministratore dal terminale: nasce già abilitato.
        $user->activate();
        $user->verifyEmail($this->clock->now());

        $level = match (true) {
            $admin => UserRole::Admin,
            $analyst => UserRole::Analyst,
            default => UserRole::Viewer,
        };
        $user->changeAccessLevel($level);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('%s "%s" created.', ucfirst($level->label()), $email));

        return Command::SUCCESS;
    }
}
