<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Entity\Concert;
use App\Entity\ConcertAvailability;
use App\Enum\AvailabilityStatus;
use App\Tests\VenueTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConcurrentEditTest extends VenueTestCase
{
    public function testAConcurrentWriteDuringSubmissionAlsoDisplaysTheConflictForm(): void
    {
        $this->client->disableReboot();
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/edit';
        $form = $this->client->request('GET', $uri)->filter('form[name="concert"]')->form();
        $em = $this->entityManager();
        $connection = $em->getConnection();
        $other = DriverManager::getConnection(
            $connection->getParams(),
            $connection->getConfiguration(),
        );
        $listener = new class($other, $id) {
            public function __construct(private Connection $connection, private int $id)
            {
            }

            public function preFlush(): void
            {
                $this->connection->executeStatement(
                    'UPDATE concert SET city = ?, revision = revision + 1 WHERE id = ?',
                    ['Paris', $this->id],
                );
            }
        };
        $em->getEventManager()->addEventListener([Events::preFlush], $listener);
        try {
            $this->client->submit($form, ['concert' => ['city' => 'Marseille']]);
            self::assertResponseStatusCodeSame(409);
            self::assertSelectorTextContains('.form-error-summary', 'Ce concert a été modifié');
            self::assertInputValueSame('concert[city]', 'Marseille');
            self::assertSame(
                'Paris',
                $other->fetchOne('SELECT city FROM concert WHERE id = ?', [$id]),
            );
        } finally {
            $em->getEventManager()->removeEventListener([Events::preFlush], $listener);
            $other->close();
        }
    }

    public function testStaleFormPreservesNewDateNotesAndAvailability(): void
    {
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/edit';
        $first = $this->client->request('GET', $uri)->filter('form[name="concert"]')->form();
        $stale = $this->client->request('GET', $uri)->filter('form[name="concert"]')->form();

        $this->client->submit($first, [
            'concert' => ['date' => '2026-12-20', 'notes' => 'Nouvel accueil à 18 h.'],
        ]);
        self::assertResponseStatusCodeSame(303);

        $em = $this->entityManager();
        $availability = $em->getRepository(ConcertAvailability::class)->findOneBy([
            'concert' => $id,
            'user' => $this->userId,
        ]);
        $availability->respond(AvailabilityStatus::Available, 'Présent le 20.');
        $em->flush();

        $this->client->submit($stale, ['concert' => ['city' => 'Marseille']]);
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('.form-error-summary', 'Ce concert a été modifié');
        self::assertSelectorExists('.form-error-summary a[href="'.$uri.'"]');
        self::assertInputValueSame('concert[city]', 'Marseille');

        $em = $this->entityManager();
        $em->clear();
        $saved = $em->find(Concert::class, $id);
        self::assertSame('2026-12-20', $saved->getDate()->format('Y-m-d'));
        self::assertSame('Nouvel accueil à 18 h.', $saved->getNotes());
        self::assertSame('Lyon', $saved->getCity());
        self::assertSame(2, $saved->getRevision());
        $availability = $em->getRepository(ConcertAvailability::class)->findOneBy([
            'concert' => $id,
            'user' => $this->userId,
        ]);
        self::assertSame(AvailabilityStatus::Available, $availability->getStatus());
        self::assertSame('Présent le 20.', $availability->getComment());

        $fresh = $this->client->request('GET', $uri)->filter('form[name="concert"]')->form();
        $this->client->submit($fresh, ['concert' => ['city' => 'Marseille']]);
        self::assertResponseStatusCodeSame(303);
    }

    #[DataProvider('invalidRevisions')]
    public function testAnAbsentOrInvalidRevisionCannotBypassProtection(?string $revision): void
    {
        $id = $this->concert()->getId();
        $uri = '/concerts/'.$id.'/edit';
        $values = $this->client
            ->request('GET', $uri)
            ->filter('form[name="concert"]')
            ->form()
            ->getPhpValues();
        if (null === $revision) {
            unset($values['concert']['revision']);
        } else {
            $values['concert']['revision'] = $revision;
        }
        $values['concert']['notes'] = 'Écriture refusée';
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertSame(
            'Accueil à 16 h.',
            $this->entityManager()->find(Concert::class, $id)->getNotes(),
        );
    }

    public static function invalidRevisions(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'invalid' => ['invalid'];
        yield 'zero' => ['0'];
    }
}
