<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Orfeo;

use App\Entity\ConcertContact;
use App\Entity\OrfeoConcert;
use App\Integration\Orfeo\Dto\OrfeoContact;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactImporter
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** L’appelant ouvre et valide la transaction globale de l’import.
     * @param list<OrfeoContact> $contacts
     *
     * @return array{added: int, updated: int, preserved: int} */
    public function import(OrfeoConcert $source, array $contacts): array
    {
        $concert = $source->getConcert();
        $existing = $this->em->getRepository(ConcertContact::class)->findBy(['concert' => $concert]);
        $links = $source->getContactLinks();
        $pending = [];
        $counts = ['added' => 0, 'updated' => 0, 'preserved' => 0];
        foreach ($contacts as $incoming) {
            $link = $links[$incoming->key] ?? null;
            if (is_array($link)) {
                $contact = $this->em->find(ConcertContact::class, (int) ($link['id'] ?? 0));
                if (null === $contact || $contact->getConcert() !== $concert
                    || !($link['owned'] ?? false) || $this->snapshot($contact) !== ($link['snapshot'] ?? null)) {
                    ++$counts['preserved'];
                    continue;
                }
                $before = $this->snapshot($contact);
                $this->update($contact, $incoming);
                $links[$incoming->key]['snapshot'] = $this->snapshot($contact);
                $counts['updated'] += $before !== $this->snapshot($contact) ? 1 : 0;
                continue;
            }
            $match = null;
            foreach ($existing as $candidate) {
                if ((null !== $incoming->email && mb_strtolower($candidate->getEmail() ?? '') === $incoming->email)
                    || (null !== $incoming->phone && '' !== $this->phone($incoming->phone) && $this->phone($candidate->getPhone()) === $this->phone($incoming->phone))
                    || (null === $incoming->email && null === $incoming->phone
                        && mb_strtolower($candidate->getName()) === mb_strtolower($incoming->name))) {
                    $match = $candidate;
                    break;
                }
            }
            if (null !== $match) {
                $links[$incoming->key] = ['id' => $match->getId(), 'owned' => false, 'snapshot' => $this->snapshot($match)];
                ++$counts['preserved'];
                continue;
            }
            $contact = new ConcertContact($concert);
            $this->update($contact, $incoming);
            $this->em->persist($contact);
            $existing[] = $contact;
            $pending[$incoming->key] = $contact;
            ++$counts['added'];
        }
        if ([] !== $pending) {
            // Obtenir les identifiants pour le suivi, sans sortir de la transaction de l’import.
            $this->em->flush();
            foreach ($pending as $key => $contact) {
                $links[$key] = ['id' => $contact->getId(), 'owned' => true, 'snapshot' => $this->snapshot($contact)];
            }
        }
        $source->setContactLinks($links);

        return $counts;
    }

    private function update(ConcertContact $contact, OrfeoContact $incoming): void
    {
        $contact->update($incoming->name, $incoming->role, $incoming->email, $incoming->phone, $incoming->notes);
    }

    /** @return array<string, ?string> */
    private function snapshot(ConcertContact $contact): array
    {
        return ['name' => $contact->getName(), 'role' => $contact->getRole()->value,
            'email' => $contact->getEmail(), 'phone' => $contact->getPhone(), 'notes' => $contact->getNotes()];
    }

    private function phone(?string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone ?? '') ?? '';
    }
}
