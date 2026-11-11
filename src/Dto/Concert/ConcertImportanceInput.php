<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Entity\Concert;
use App\Enum\ConcertImportance;
use Symfony\Component\Validator\Constraints as Assert;

final class ConcertImportanceInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[1-9][0-9]*$/D')]
    public ?string $revision = null;

    public ?ConcertImportance $level = null;

    #[Assert\Length(max: 500, maxMessage: 'La note d’enjeu est limitée à 500 caractères.')]
    public ?string $note = null;

    #[Assert\Choice(choices: ['date', 'importance'])]
    public string $returnSort = 'date';

    #[Assert\Length(max: ConcertListFilters::MAX_SEARCH_LENGTH)]
    public ?string $returnSearch = null;

    #[Assert\Choice(choices: ['list', 'concert'])]
    public string $returnTo = 'list';

    public static function fromConcert(Concert $concert): self
    {
        $input = new self();
        $input->revision = (string) $concert->getRevision();
        $input->level = $concert->getImportance();
        $input->note = $concert->getImportanceNote();

        return $input;
    }
}
