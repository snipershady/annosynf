<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use DateTimeImmutable;

/**
 * Costruttori di entity valide per i test, con default sovrascrivibili.
 */
final class EntityFactory
{
    public const string NOW = '2026-10-24 12:00:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * @param non-empty-string $email
     * @param non-empty-string $username
     */
    public static function user(string $email = 'user@example.org', string $username = 'user'): User
    {
        $user = new User($email, $username, 'hashed-password', self::now());
        // Un utente nuovo è disabilitato e con l'e-mail da confermare: i test lavorano di norma con un account pronto.
        $user->activate();
        $user->verifyEmail(self::now());

        return $user;
    }

    /**
     * Utente appena registrato: disabilitato e con l'e-mail ancora da confermare.
     *
     * @param non-empty-string $email
     * @param non-empty-string $username
     */
    public static function pendingUser(string $email = 'pending@example.org', string $username = 'pending'): User
    {
        return new User($email, $username, 'hashed-password', self::now());
    }
}
