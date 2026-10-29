<?php

declare(strict_types=1);

namespace App\Form\Member;

use App\Validator\AccountPassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountPasswordType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $attributes = [
            'autocomplete' => 'new-password',
            'minlength' => AccountPassword::MIN_LENGTH,
            'maxlength' => AccountPassword::MAX_LENGTH,
        ];
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'options' => ['empty_data' => ''],
            'invalid_message' => 'Les mots de passe ne correspondent pas.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'help' => AccountPassword::HELP,
                'attr' => $attributes,
            ],
            'second_options' => [
                'label' => 'Confirme ton nouveau mot de passe',
                'attr' => $attributes,
            ],
        ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }
}
