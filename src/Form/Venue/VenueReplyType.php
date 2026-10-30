<?php

declare(strict_types=1);

namespace App\Form\Venue;

use App\Dto\Venue\VenueReplyInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<VenueReplyInput> */
final class VenueReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('respondent', TextType::class, [
                'label' => 'Votre nom / fonction',
                'empty_data' => '',
                'attr' => ['maxlength' => 150],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Message pour le groupe (facultatif)',
                'required' => false,
                'attr' => ['maxlength' => 2000, 'rows' => 4],
            ])
            ->add('revision', HiddenType::class)
            ->add('answers', CollectionType::class, [
                'entry_type' => AvailabilityAnswerType::class,
                'entry_options' => ['label' => false],
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => VenueReplyInput::class,
            'csrf_token_id' => 'venue_reply',
        ]);
    }
}
