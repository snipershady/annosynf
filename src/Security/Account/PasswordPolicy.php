<?php

declare(strict_types=1);

namespace App\Security\Account;

/**
 * Regole condivise dalle password scelte dagli utenti (registrazione, reset, cambio, CLI, admin).
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 12;

    public const int MAX_LENGTH = 4096;
}
