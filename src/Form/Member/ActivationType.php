<?php

declare(strict_types=1);

namespace App\Form\Member;

use App\Dto\Member\ActivationInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ActivationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('code', PasswordType::class, [
            'label' => 'Ton code d’activation personnel',
            'always_empty' => true,
            'empty_data' => '',
            'help' => 'En cas d’erreur, renseigne à nouveau le code et les deux mots de passe.',
            'attr' => ['autocomplete' => 'one-time-code', 'maxlength' => 100],
        ])->add('password', AccountPasswordType::class);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['data_class' => ActivationInput::class]);
    }
}
