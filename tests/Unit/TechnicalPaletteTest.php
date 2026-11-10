<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Technical\TechnicalPalette;
use App\Service\Technical\TechnicalPaletteStyles;
use PHPUnit\Framework\TestCase;

final class TechnicalPaletteTest extends TestCase
{
    public function testAllFamiliesRemainReadableInBothThemesAndShareTheirPdfColors(): void
    {
        $palette = new TechnicalPalette();
        $styles = new TechnicalPaletteStyles($palette);
        foreach ($palette->families() as $key => $color) {
            foreach (['background', 'ink', 'border', 'darkBackground', 'darkInk', 'darkBorder'] as $field) {
                self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/D', $color[$field]);
            }
            foreach ([['background', 'ink'], ['darkBackground', 'darkInk']] as [$background, $ink]) {
                $luminances = [$this->luminance($color[$background]), $this->luminance($color[$ink])];
                $ratio = (max($luminances) + 0.05) / (min($luminances) + 0.05);
                self::assertGreaterThanOrEqual(4.5, $ratio, $key.' contrast');
            }
            self::assertStringContainsString($color['background'], $styles->application());
            self::assertStringContainsString($color['background'], $styles->pdf());
        }
    }

    public function testLinkedSourcesTakePriorityAndGenericElementsHaveSafeFallbacks(): void
    {
        $palette = new TechnicalPalette();
        self::assertSame('drums', $palette->stageFamily(['kind' => 'drums']));
        self::assertSame('guitar', $palette->stageFamily(['kind' => 'amp', 'label' => 'Ampli guitare Ju']));
        self::assertSame('bass', $palette->stageFamily(['kind' => 'person', 'notes' => 'Basse']));
        self::assertSame('bass', $palette->stageFamily(['kind' => 'di_mono', 'patchSources' => ['source']],
            ['source' => ['family' => 'bass', 'available' => true]]));
        self::assertSame('utility', $palette->stageFamily(['kind' => 'power']));
        self::assertSame('utility', $palette->normalize('untrusted-family'));
        self::assertSame('monitors', $palette->equipmentFamily(['category' => 'Retours']));
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(static function (string $part): float {
            $value = hexdec($part) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
