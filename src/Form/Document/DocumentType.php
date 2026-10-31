<?php

declare(strict_types=1);

namespace App\Form\Document;

use App\Dto\Document\DocumentInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Enum\DocumentCategory;
use App\Enum\DocumentSource;
use App\Repository\ConcertRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<DocumentInput> */
final class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $band = $options['band'];
        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'attr' => ['maxlength' => 160]])
            ->add('category', EnumType::class, [
                'class' => DocumentCategory::class,
                'choice_label' => static fn (DocumentCategory $category): string => $category->label(),
                'label' => 'Catégorie',
            ])
            ->add('concert', EntityType::class, [
                'class' => Concert::class,
                'label' => 'Concert associé',
                'placeholder' => 'Document du groupe, sans concert particulier',
                'required' => false,
                'choice_label' => static fn (Concert $concert): string => implode(' · ', [
                    $concert->getDate()->format('d/m/Y'),
                    $concert->getVenue(),
                    $concert->getCity(),
                ]),
                'query_builder' => static fn (ConcertRepository $repository) => $repository
                    ->createQueryBuilder('concert')
                    ->where('concert.band = :band')
                    ->setParameter('band', $band)
                    ->orderBy('concert.date', 'DESC'),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Précisions',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 500],
            ]);

        if (DocumentSource::Pdf === $options['source']) {
            $builder->add('file', FileType::class, [
                'label' => 'Fichier PDF',
                'attr' => ['accept' => '.pdf,application/pdf'],
                'help' => '10 Mo maximum. Pour un fichier volumineux, ajoute plutôt un lien.',
            ]);
        } else {
            $builder->add('url', UrlType::class, [
                'label' => 'Lien du document',
                'attr' => ['placeholder' => 'https://docs.google.com/…', 'maxlength' => 2048],
                'help' => 'Drive, Google Docs, Dropbox… Vérifie que les membres ont accès au fichier chez ce service.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['band', 'source']);
        $resolver->setAllowedTypes('band', Band::class);
        $resolver->setAllowedTypes('source', DocumentSource::class);
        $resolver->setDefaults([
            'data_class' => DocumentInput::class,
            'csrf_token_id' => 'band_document',
        ]);
    }
}
