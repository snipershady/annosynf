<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Security\UserRole;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<UserAdminData>
 */
final class UserAdminType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $creating = $options['creating'] === true;

        $builder
            // Email e username identificano l'utente: si scelgono solo alla creazione.
            ->add('email', EmailType::class, ['disabled' => !$creating])
            ->add('username', TextType::class, ['disabled' => !$creating])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => $creating,
                'invalid_message' => 'The passwords do not match.',
                'first_options' => ['label' => $creating ? 'Password' : 'New password', 'help' => $creating ? null : 'Leave empty to keep the current password.', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Repeat the password', 'attr' => ['autocomplete' => 'new-password']],
            ])
            ->add('accessLevel', EnumType::class, [
                'class' => UserRole::class,
                'choices' => UserRole::accessLevels(),
                'choice_label' => static fn (UserRole $role): string => ucfirst($role->label()),
                'label' => 'Access level',
                'help' => 'Viewers read analyses and releases; analysts also create and manage analyses; administrators also manage releases and users.',
            ])
            ->add('active', CheckboxType::class, ['required' => false, 'label' => 'Enabled', 'help' => 'Disabled users (including new registrations) can sign in but cannot see any data. Enabling sends them an email.']);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserAdminData::class,
            'creating' => false,
            'validation_groups' => static fn (FormInterface $form): array => $form->getConfig()->getOption('creating') === true ? ['Default', 'create'] : ['Default'],
        ]);
        $resolver->setAllowedTypes('creating', 'bool');
    }
}
