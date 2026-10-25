<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Entity\User;
use App\Security\Account\DeliverableEmail;
use App\Security\Account\PasswordPolicy;
use App\Security\Altcha\AltchaPurpose;
use App\Security\Altcha\AltchaSolved;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registrazione pubblica: l'account nasce disabilitato finché un amministratore non lo abilita.
 */
#[UniqueEntity(fields: ['email'], message: 'This email address is already registered.', entityClass: User::class)]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.', entityClass: User::class)]
final class RegistrationData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[DeliverableEmail]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    public ?string $username = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public ?string $password = null;

    /** Soluzione della proof of work, scritta dal widget ALTCHA. */
    #[AltchaSolved(AltchaPurpose::Registration)]
    public ?string $altcha = null;
}
