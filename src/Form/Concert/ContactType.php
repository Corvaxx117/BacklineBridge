<?php

declare(strict_types=1);

namespace App\Form\Concert;

use App\Dto\Concert\ContactInput;
use App\Enum\ContactRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ContactInput> */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['maxlength' => 120]])
            ->add('role', EnumType::class, [
                'class' => ContactRole::class,
                'choice_label' => static fn (ContactRole $role): string => $role->label(),
                'label' => 'Rôle',
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
                'attr' => ['maxlength' => 180],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['maxlength' => 40],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Précisions',
                'required' => false,
                'attr' => ['maxlength' => 500, 'rows' => 3],
                'help' => 'Par exemple : à joindre après 17 h, contact transmis par Rémi…',
            ])
            ->add('revision', HiddenType::class, ['empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactInput::class,
            'csrf_token_id' => 'concert_contact',
        ]);
    }
}
