<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;

final class ReferenceImportInput
{
    #[Assert\Count(min: 1, minMessage: 'Choisis au moins une section à récupérer.')]
    #[Assert\Choice(choices: ['patch', 'stage', 'equipment', 'notes'], multiple: true)]
    public array $sections = [];

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/')]
    public string $fingerprint = '';

    #[Assert\IsTrue(message: 'Confirme le remplacement des sections choisies.')]
    public bool $confirmReplacement = false;
}
