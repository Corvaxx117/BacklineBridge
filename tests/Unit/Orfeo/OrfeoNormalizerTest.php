<?php

declare(strict_types=1);

namespace App\Tests\Unit\Orfeo;

use App\Integration\Orfeo\OrfeoConfiguration;
use App\Integration\Orfeo\OrfeoEventNormalizer;
use PHPUnit\Framework\TestCase;

final class OrfeoNormalizerTest extends TestCase
{
    private function normalizer(): OrfeoEventNormalizer
    {
        return new OrfeoEventNormalizer(new OrfeoConfiguration('1', 'fake', '301', '44', 'Europe/Paris', false));
    }

    private function project(): array
    {
        return ['pk' => 901, 'spectacle' => ['pk' => 301], 'status' => ['pk' => 44, 'name' => 'Confirmé'],
            'place' => ['pk' => 8, 'name' => 'Salle fictive', 'city' => 'Ville fictive', 'account_iban' => 'MUST_NOT_BE_IMPORTED'],
            'contracts' => [['internal_private_amount' => 9000]],
            'performances' => [['pk' => 1001, 'start_datetime' => '2027-01-15T00:00:00', 'is_all_day_event' => true]]];
    }

    public function testAnAllDayPerformanceKeepsItsUnknownTimeAndOnlySelectedFields(): void
    {
        $events = $this->normalizer()->normalize($this->project(), []);
        self::assertCount(1, $events);
        self::assertSame('901:1001', $events[0]->id);
        self::assertNull($events[0]->localTime);
        self::assertTrue($events[0]->confirmed);
        $json = json_encode($events[0]->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('MUST_NOT_BE_IMPORTED', $json);
        self::assertStringNotContainsString('internal_private_amount', $json);
    }

    public function testEachPerformanceProducesItsOwnIdentityAndLocalDate(): void
    {
        $project = $this->project();
        $project['performances'][] = ['pk' => 1002, 'start_datetime' => '2027-01-15T23:30:00Z', 'is_all_day_event' => false];
        $events = $this->normalizer()->normalize($project, []);
        self::assertCount(2, $events);
        self::assertSame('2027-01-16', $events[1]->date);
        self::assertSame('00:30', $events[1]->localTime);
        self::assertNotSame($events[0]->id, $events[1]->id);
    }

    public function testMissingAndInvalidDatesAreNotInvented(): void
    {
        $project = $this->project();
        $project['performances'] = [];
        self::assertSame([], $this->normalizer()->normalize($project, []));
        $project['performances'] = [['pk' => 1001, 'start_datetime' => '2027-02-31T20:00:00']];
        self::assertSame([], $this->normalizer()->normalize($project, []));
    }

    public function testProjectsAndContactsOutsideTheScopeAreRejected(): void
    {
        $project = $this->project();
        $events = $this->normalizer()->normalize($project, [['parent' => 999, 'pk' => 9, 'entity' => ['name' => 'Foreign contact']]]);
        self::assertSame([], $events[0]->contacts);
        $project['spectacle']['pk'] = 999;
        $this->expectException(\UnexpectedValueException::class);
        $this->normalizer()->normalize($project, []);
    }
}
