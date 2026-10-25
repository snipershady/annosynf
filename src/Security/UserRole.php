<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Ruoli di sicurezza di un utente (§B.15). ROLE_USER è implicito per ogni utente abilitato e,
 * tramite la role_hierarchy, comprende ROLE_VIEWER (sola lettura); ROLE_ANALYST crea e gestisce
 * le analisi; ROLE_ADMIN gestisce release e utenti. ROLE_DISABLED è lo stato di un account appena
 * registrato o sospeso: può accedere ma non ha nessun altro ruolo.
 */
enum UserRole: string
{
    case User = 'ROLE_USER';
    case Viewer = 'ROLE_VIEWER';
    case Analyst = 'ROLE_ANALYST';
    case Admin = 'ROLE_ADMIN';
    case Disabled = 'ROLE_DISABLED';

    /**
     * Livelli d'accesso assegnabili a un utente abilitato, dal più basso al più alto.
     *
     * @return list<self>
     */
    public static function accessLevels(): array
    {
        return [self::Viewer, self::Analyst, self::Admin];
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'user',
            self::Viewer => 'viewer',
            self::Analyst => 'analyst',
            self::Admin => 'administrator',
            self::Disabled => 'disabled',
        };
    }
}
