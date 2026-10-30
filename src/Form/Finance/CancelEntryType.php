<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\CancelEntryInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<CancelEntryInput> */
final class CancelEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b->add('reason', TextareaType::class, [
            'label' => 'Pourquoi annuler ce mouvement ?',
            'empty_data' => '',
            'attr' => ['maxlength' => 250, 'rows' => 3],
        ])
            ->add('revision', HiddenType::class)
            ->add('entryRevision', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => CancelEntryInput::class,
            'csrf_token_id' => 'treasury_cancel',
        ]);
    }
}
