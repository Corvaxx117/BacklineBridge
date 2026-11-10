<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pdf;

use App\Service\Pdf\StagePlanLayout;
use PHPUnit\Framework\TestCase;

final class StagePlanLayoutTest extends TestCase
{
    public function testAllAudioSymbolsAndTheirPatchLabelsHaveSpaceInThePdf(): void
    {
        $kinds = ['mic_vocal', 'mic_instrument', 'stand_straight', 'stand_boom', 'di_mono', 'di_stereo'];
        $items = array_map(static fn (string $kind): array => [
            'kind' => $kind,
            'label' => 'Élément audio',
            'notes' => 'Précision',
            'patchLabel' => 'Entrées 12, 13',
            'x' => 50,
            'y' => 50,
        ], $kinds);
        $layout = new StagePlanLayout()->prepare($items);
        self::assertSame($kinds, array_column($layout['nodes'], 'kind'));
        foreach ($layout['nodes'] as $node) {
            self::assertSame('Entrées 12, 13', implode(' ', $node['patch']));
            self::assertSame(['Précision'], $node['notes']);
            $lastBaseline = $node['labelY'] + count($node['label']) * 17 + count($node['notes']) * 13;
            self::assertLessThanOrEqual($node['height'] / 2 - 2, $lastBaseline);
        }
    }

    public function testAPowerSymbolStaysCenteredOnItsSavedPositionWithoutAnInvisibleCard(): void
    {
        $node = new StagePlanLayout()->prepare([
            ['kind' => 'power', 'label' => 'Alimentation', 'notes' => '', 'x' => 72, 'y' => 48],
        ])['nodes'][0];
        self::assertSame([], $node['label']);
        self::assertEqualsWithDelta(0.0, -$node['height'] / 2 + 7 + $node['symbolHeight'] / 2, 0.001);
        self::assertSame(720.0, $node['x']);
    }

    public function testTransformsRemainInsideTheExportedViewport(): void
    {
        $layout = new StagePlanLayout()->prepare([
            [
                'kind' => 'amp',
                'label' => 'Ampli à gauche',
                'notes' => 'Référence complète',
                'x' => 0,
                'y' => 0,
                'rotation' => 45,
                'scale' => 2.5,
            ],
        ]);
        $node = $layout['nodes'][0];
        [$left, $top, $width, $height] = array_map('floatval', explode(' ', $layout['viewBox']));
        self::assertSame(45.0, $node['rotation']);
        self::assertSame(2.5, $node['scale']);
        self::assertLessThan(-100, $left);
        self::assertLessThan(-100, $top);
        self::assertGreaterThanOrEqual(1000, $left + $width);
        self::assertGreaterThanOrEqual(606, $top + $height);
    }

    public function testPresentationEnlargesEveryKindWithoutChangingSavedScaleOrPosition(): void
    {
        $items = array_map(static fn (string $kind): array => [
            'kind' => $kind, 'label' => 'Repère', 'x' => 35, 'y' => 42, 'scale' => 1.5,
        ], \App\Dto\Stage\StageItemInput::kindValues());
        foreach (new StagePlanLayout()->prepare($items)['nodes'] as $node) {
            self::assertSame(1.5, $node['scale']);
            self::assertEqualsWithDelta(1.68, $node['presentationScale'], 0.0001);
            self::assertSame(350.0, $node['x']);
            self::assertEqualsWithDelta(254.52, $node['y'], 0.0001);
        }
    }

    public function testLegacyValuesAndUnexpectedNumbersHaveSafeDefaults(): void
    {
        $layout = new StagePlanLayout()->prepare([
            [
                'kind' => 'not-a-kind',
                'label' => "Texte\x00 XML",
                'notes' => 'Précision réservée aux repères',
                'x' => INF,
                'y' => NAN,
            ],
            ['kind' => 'riser', 'label' => 'Praticable', 'notes' => ''],
        ]);
        self::assertSame('riser', $layout['nodes'][0]['kind']);
        $node = $layout['nodes'][1];
        self::assertSame('other', $node['kind']);
        self::assertSame(0.0, $node['rotation']);
        self::assertSame(1.0, $node['scale']);
        self::assertSame(500.0, $node['x']);
        self::assertSame(303.0, $node['y']);
        self::assertStringNotContainsString("\x00", implode('', $node['label']));
    }
}
