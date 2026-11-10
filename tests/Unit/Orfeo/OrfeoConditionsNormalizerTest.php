<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orfeo;

use App\Integration\Orfeo\Dto\OrfeoEvent;
use App\Integration\Orfeo\OrfeoConditionsNormalizer;
use PHPUnit\Framework\TestCase;

final class OrfeoConditionsNormalizerTest extends TestCase
{
    public function testOnlyUsefulConditionsAreKeptAndPayersAreNotGuessed(): void
    {
        $contracts = new OrfeoConditionsNormalizer()->contracts(['contracts' => [[
            'pk' => 5,
            'contract_type' => ['name' => 'Cession'],
            'status' => 'Confirmé',
            'accommodation_notes' => '<p>Deux chambres.</p><p>Arrivée après 18 h.</p>',
            'accommodation_paid_by' => 'organizer',
            'journey_notes' => 'Train &amp; transferts.',
            'journey_paid_by' => 'producer',
            'meal_notes' => 'Repas végétariens.',
            'meal_paid_by' => 'not-specified',
            'total_ht' => '1500.50',
            'total_ttc' => '1800.60',
            'organizer' => ['account_iban' => 'MUST_STAY_PRIVATE'],
            'signatory_email' => 'must-not-copy@example.test',
        ]]]);
        self::assertCount(1, $contracts);
        $contract = $contracts[0];
        self::assertSame(150050, $contract->feeHtCents);
        self::assertSame(180060, $contract->feeTtcCents);
        self::assertSame("Prise en charge : organisateur.\nDeux chambres.\nArrivée après 18 h.", $contract->accommodationNotes);
        self::assertSame("Prise en charge : production.\nTrain & transferts.", $contract->transportNotes);
        self::assertSame('Repas végétariens.', $contract->mealNotes);
        $json = json_encode($contract->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('MUST_STAY_PRIVATE', $json);
        self::assertStringNotContainsString('must-not-copy', $json);
    }

    public function testMalformedAmountsAndLargeNotesRemainBounded(): void
    {
        $contracts = new OrfeoConditionsNormalizer()->contracts(['contracts' => [[
            'pk' => 5, 'total_ht' => '-5', 'total_ttc' => 12.5,
            'journey_notes' => str_repeat('x', 10000), 'meal_paid_by' => 'unexpected',
        ], ['pk' => 'invalid']]]);
        self::assertCount(1, $contracts);
        self::assertNull($contracts[0]->feeHtCents);
        self::assertNull($contracts[0]->feeTtcCents);
        self::assertLessThanOrEqual(4000, mb_strlen($contracts[0]->transportNotes));
        self::assertStringEndsWith('…', $contracts[0]->transportNotes);
        self::assertSame('', $contracts[0]->mealNotes);
    }

    public function testLegacySnapshotsCanStillBeReadAndNewSnapshotsKeepContractIdentity(): void
    {
        $event = new OrfeoEvent(901, 1001, 301, '2026-12-12', null, 'Salle', 'Ville', null, 1, 'Confirmé', true, false, []);
        $legacy = $event->toArray();
        unset($legacy['contracts']);
        self::assertSame([], OrfeoEvent::fromArray($legacy)->contracts);
        $contract = new OrfeoConditionsNormalizer()->contracts(['contracts' => [['pk' => 5, 'total_ht' => '0']]])[0];
        $current = [...$event->toArray(), 'contracts' => [$contract->toArray()]];
        self::assertSame($contract->toArray(), OrfeoEvent::fromArray($current)->contracts[0]->toArray());
        self::assertSame(0, OrfeoEvent::fromArray($current)->contracts[0]->feeHtCents);
    }
}
