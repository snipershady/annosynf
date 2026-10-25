<?php

declare(strict_types=1);

namespace App\Security\Account;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Avvisa ogni amministratore abilitato che un nuovo utente attende di essere abilitato.
 */
final readonly class NewUserNotifier
{
    public function __construct(
        private UserRepository $users,
        private AccountMailer $mailer,
    ) {
    }

    public function notify(User $newUser): void
    {
        foreach ($this->users->findAdministrators() as $administrator) {
            $this->mailer->sendNewUserNotification($administrator, $newUser);
        }
    }
}
