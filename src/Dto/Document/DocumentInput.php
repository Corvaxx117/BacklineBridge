<?php

declare(strict_types=1);

namespace App\Dto\Document;

use App\Entity\Concert;
use App\Enum\DocumentCategory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class DocumentInput
{
    #[Assert\NotBlank(message: 'Donne un titre au document.')]
    #[Assert\Length(max: 160)]
    public string $title = '';

    #[Assert\NotNull(message: 'Choisis une catégorie.')]
    public ?DocumentCategory $category = DocumentCategory::Technical;

    #[Assert\Length(max: 500)]
    public ?string $description = null;

    public ?Concert $concert = null;

    #[Assert\NotNull(message: 'Choisis un fichier PDF.', groups: ['pdf'])]
    #[Assert\File(
        maxSize: '10Mi',
        extensions: ['pdf'],
        extensionsMessage: 'Seuls les fichiers PDF sont acceptés.',
        maxSizeMessage: 'Le PDF ne doit pas dépasser 10 Mo.',
        groups: ['pdf'],
    )]
    public ?UploadedFile $file = null;

    #[Assert\NotBlank(message: 'Colle le lien du document.', groups: ['lien'])]
    #[Assert\Url(
        protocols: ['https'],
        message: 'Utilise une adresse complète commençant par https://.',
        groups: ['lien'],
    )]
    #[Assert\Length(max: 2048, groups: ['lien'])]
    public ?string $url = null;

    #[Assert\Callback(groups: ['lien'])]
    public function validateUrl(ExecutionContextInterface $context): void
    {
        if (null === $this->url) {
            return;
        }
        $parts = parse_url($this->url);
        if (false === $parts || isset($parts['user']) || isset($parts['pass'])) {
            $context->buildViolation('Utilise un lien sans identifiant ni mot de passe intégré.')
                ->atPath('url')
                ->addViolation();
        }
    }
}
