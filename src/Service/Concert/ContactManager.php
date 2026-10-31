<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Dto\Concert\ContactInput;
use App\Entity\Concert;
use App\Entity\ConcertContact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class ContactManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Concert $concert, ContactInput $input, ?ConcertContact $contact = null): ConcertContact
    {
        if (null !== $contact && $input->revision !== (string) $contact->getRevision()) {
            throw new ConflictHttpException('Ce contact vient d’être modifié. Recharge sa fiche.');
        }
        $contact ??= new ConcertContact($concert);
        assert(null !== $input->role);
        $contact->update($input->name, $input->role, $input->email, $input->phone, $input->notes);
        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $contact;
    }

    public function remove(ConcertContact $contact): void
    {
        $this->entityManager->remove($contact);
        $this->entityManager->flush();
    }
}
