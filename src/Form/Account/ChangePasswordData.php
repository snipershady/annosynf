<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Security\Account\PasswordPolicy;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cambio password di un utente già autenticato: serve la password attuale.
 */
final class ChangePasswordData
{
    #[Assert\NotBlank]
    #[UserPassword(message: 'The current password is not correct.')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    #[Assert\NotEqualTo(propertyPath: 'currentPassword', message: 'The new password must differ from the current one.')]
    public ?string $password = null;
}
