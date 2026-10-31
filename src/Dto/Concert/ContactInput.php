<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use App\Entity\ConcertContact;
use App\Enum\ContactRole;
use Symfony\Component\Validator\Constraints as Assert;

final class ContactInput
{
    #[Assert\NotBlank(message: 'Indique le nom du contact.')]
    #[Assert\Length(max: 120)]
    public string $name = '';

    #[Assert\NotNull(message: 'Choisis un rôle.')]
    public ?ContactRole $role = ContactRole::StageManager;

    #[Assert\Email(message: 'Indique une adresse e-mail valide.')]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Regex(pattern: '/^[+0-9().\s-]{3,40}$/uD', message: 'Indique un numéro de téléphone valide.')]
    public ?string $phone = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[1-9][0-9]*$/D')]
    public string $revision = '1';

    public static function fromContact(ConcertContact $contact): self
    {
        $input = new self();
        $input->name = $contact->getName();
        $input->role = $contact->getRole();
        $input->email = $contact->getEmail();
        $input->phone = $contact->getPhone();
        $input->notes = $contact->getNotes();
        $input->revision = (string) $contact->getRevision();

        return $input;
    }
}
