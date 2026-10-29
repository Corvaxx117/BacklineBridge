<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Pdf;

use App\Service\Pdf\StagePlanLayout;
use PHPUnit\Framework\TestCase;

final class StagePlanLayoutTest extends TestCase
{
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

    public function testLegacyValuesAndUnexpectedNumbersHaveSafeDefaults(): void
    {
        $layout = new StagePlanLayout()->prepare([
            [
                'kind' => 'not-a-kind',
                'label' => 'Élément',
                'notes' => "Texte\x00 XML",
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
        self::assertStringNotContainsString("\x00", implode('', $node['notes']));
    }
}
