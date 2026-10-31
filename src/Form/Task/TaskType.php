<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Dto\Task\TaskInput;
use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Enum\TaskStatus;
use App\Repository\MemberProfileRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<TaskInput> */
final class TaskType extends AbstractType
{
    public function __construct(private readonly MemberProfileRepository $profiles)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'À faire',
                'attr' => ['maxlength' => 180, 'placeholder' => 'Ex. Préparer la commande de T-shirts'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Précisions',
                'required' => false,
                'attr' => ['maxlength' => 3000, 'rows' => 5],
            ])
            ->add('assignee', EntityType::class, [
                'class' => MemberProfile::class,
                'choices' => $this->profiles->forBand($options['band']),
                'choice_label' => 'publicName',
                'label' => 'Responsable',
                'required' => false,
                'placeholder' => 'Non attribuée',
                'help' => 'Une personne pilote la tâche ; tout le groupe peut y contribuer.',
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'Pour le',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', EnumType::class, [
                'class' => TaskStatus::class,
                'choice_label' => static fn (TaskStatus $status): string => $status->label(),
                'label' => 'État',
            ])
            ->add('revision', HiddenType::class, ['empty_data' => '']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('band');
        $resolver->setAllowedTypes('band', Band::class);
        $resolver->setDefaults(['data_class' => TaskInput::class, 'csrf_token_id' => 'band_task']);
    }
}
