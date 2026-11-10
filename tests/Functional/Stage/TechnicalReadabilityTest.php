<?php

declare(strict_types=1);

namespace App\Tests\Functional\Stage;

use App\Entity\Band;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use App\Tests\VenueTestCase;

final class TechnicalReadabilityTest extends VenueTestCase
{
    public function testThePatchKeepsAllChannelsWithLabelledIconsAndExpandableNotes(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(22, '.patch-table tbody tr');
        self::assertSelectorCount(22, '.patch-actions a[title][aria-label] svg');
        self::assertSelectorCount(22, '.patch-actions button[title][aria-label] svg');
        self::assertSelectorExists('.patch-note summary[title][aria-label]');
        self::assertSelectorTextContains('.patch-table thead', 'MIC / DI');
        self::assertSelectorTextContains('.patch-table', 'CLICK');
        self::assertSelectorTextContains('.patch-short-note', 'OWN MIC');
        self::assertSelectorNotExists('.patch-table .edit-link');
    }

    public function testTheDiagramHasRecognizablePartsAndItsFullNotesRemainAvailable(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorCount(7, '.stage-drum-kit [data-drum-part]');
        self::assertSelectorExists('.stage-drum-kit [data-drum-part="hi-hat"] ellipse');
        self::assertSelectorExists('.stage-drum-kit [data-drum-part="kick"] path');
        self::assertSelectorExists('.stage-drum-kit [data-drum-part="snare"] circle');
        self::assertSelectorExists('.stage-drum-kit [data-drum-part="rack-tom"] circle');
        self::assertSelectorExists('.stage-drum-kit [data-drum-part="floor-tom"] circle');
        self::assertSelectorTextContains('.stage-legend', '200 × 200 × 30 cm');
        self::assertSelectorTextContains('.stage-legend', 'IEM filaire mono/stéréo à confirmer');
    }

    public function testTheFullKitIsGroupedWithoutRemovingIndividualVenueRequests(): void
    {
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.equipment-group-drums h3', 'Kit batterie complet');
        self::assertSelectorCount(8, '.drum-kit-details .equipment-item');
        $issued = $this->issue();
        $this->enter($issued->token);
        self::assertSelectorCount(8, '[data-group-label="Batterie"] [data-answer-row]');
        self::assertSelectorTextContains('[data-group-label="Batterie"]', 'Fûts et hardware');
    }

    public function testReadOnlyMembersSeeNotesButNoWriteIcons(): void
    {
        $user = static::getContainer()->get(UserCreator::class)->create(
            'reader@example.test', 'Lecture seule', 'CompteTest2026!',
            $this->entityManager()->find(Band::class, $this->bandId), BandRole::Viewer,
        );
        $this->client->loginUser($user);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.patch-actions a, .patch-actions button');
        self::assertSelectorExists('.patch-note summary');
    }
}
