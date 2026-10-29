<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Concert;
use App\Enum\ConcertStatus;

/** Données du formulaire de création ou de modification du concert. */
final class CreateConcertInput
{
    public ?string $revision = null;

    #[Assert\NotNull(message: 'Indique la date du concert.')]
    public ?\DateTimeImmutable $date = null;

    #[Assert\NotBlank(message: 'Indique le nom de la salle.')]
    #[
        Assert\Length(
            max: 150,
            maxMessage: 'Le nom de la salle ne peut pas dépasser {{ limit }} caractères.',
        ),
    ]
    public string $venue = '';

    #[Assert\NotBlank(message: 'Indique la ville du concert.')]
    #[Assert\Length(max: 120, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.')]
    public string $city = '';

    #[Assert\NotNull(message: 'Choisissez un statut.')]
    public ?ConcertStatus $status = ConcertStatus::Pending;

    #[
        Assert\Length(
            max: 2000,
            maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.',
        ),
    ]
    public ?string $notes = null;

    public static function fromConcert(Concert $concert): self
    {
        $input = new self();
        $input->revision = (string) $concert->getRevision();
        $input->date = $concert->getDate();
        $input->venue = $concert->getVenue();
        $input->city = $concert->getCity();
        $input->status = $concert->getStatus();
        $input->notes = $concert->getNotes();

        return $input;
    }
}
