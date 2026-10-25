<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\User;
use App\Security\Account\PasswordPolicy;
use App\Security\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Creazione e modifica di un utente dall'amministrazione.
 */
final class UserAdminData
{
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Email(groups: ['create'])]
    #[Assert\Length(max: 180, groups: ['create'])]
    public ?string $email = null;

    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(max: 180, groups: ['create'])]
    public ?string $username = null;

    /** Obbligatoria alla creazione; in modifica vuota = invariata. */
    #[Assert\NotBlank(groups: ['create'])]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public ?string $password = null;

    #[Assert\Choice(callback: [UserRole::class, 'accessLevels'])]
    public UserRole $accessLevel = UserRole::Viewer;

    public bool $active = true;

    public static function fromUser(User $user): self
    {
        $data = new self();
        $data->email = $user->getEmail();
        $data->username = $user->getUsername();
        $data->accessLevel = $user->getAccessLevel();
        $data->active = $user->isActive();

        return $data;
    }

    /**
     * Livello d'accesso e stato (la password la gestisce il controller, che ha l'hasher).
     */
    public function applyTo(User $user): void
    {
        $user->changeAccessLevel($this->accessLevel);

        if ($this->active) {
            $user->activate();
        } else {
            $user->deactivate();
        }
    }
}
