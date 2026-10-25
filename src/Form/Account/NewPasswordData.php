<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Security\Account\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nuova password scelta con il link ricevuto per e-mail.
 */
final class NewPasswordData
{
    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public ?string $password = null;
}
