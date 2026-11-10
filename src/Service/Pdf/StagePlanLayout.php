<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Stage\StageItemInput;
use App\Service\Stage\StagePresentation;
use App\Service\Technical\TechnicalPalette;

/** Prépare un dessin vectoriel autonome, sans dépendre du JavaScript de l'éditeur. */
final class StagePlanLayout
{
    private const WIDTH = 1000;
    private const HEIGHT = 606;
    private const STYLES = [
        'riser' => [280, 'none', '#526359'],
        'drums' => [260, 'none', 'none'],
        'amp' => [110, 'none', 'none'],
        'person' => [120, 'none', 'none'],
        'wedge' => [80, 'none', 'none'],
        'keys' => [140, 'none', 'none'],
        'power' => [70, 'none', 'none'],
        'other' => [110, 'none', 'none'],
        'mic_vocal' => [70, 'none', 'none'],
        'mic_instrument' => [70, 'none', 'none'],
        'stand_straight' => [70, 'none', 'none'],
        'stand_boom' => [70, 'none', 'none'],
        'di_mono' => [70, 'none', 'none'],
        'di_stereo' => [70, 'none', 'none'],
    ];

    public function __construct(private readonly TechnicalPalette $palette = new TechnicalPalette())
    {
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    public function prepare(array $items): array
    {
        $nodes = [];
        $left = -12.0;
        $top = -12.0;
        $right = self::WIDTH + 12.0;
        $bottom = self::HEIGHT + 12.0;

        foreach ($items as $item) {
            $kind = in_array($item['kind'] ?? '', StageItemInput::KINDS, true)
                ? $item['kind']
                : 'other';
            [$width, $fill, $stroke] = self::STYLES[$kind];
            $family = $this->palette->normalize(is_string($item['family'] ?? null)
                ? $item['family'] : $this->palette->stageFamily($item));
            $color = $this->palette->families()[$family];
            $ink = $color['ink'];
            $background = $color['background'];
            if ('riser' === $kind) {
                $stroke = $color['border'];
            }
            $label = 'power' === $kind && 'Alimentation' === ($item['label'] ?? '')
                ? []
                : $this->wrap($item['label'] ?? '', max(10, (int) (($width - 8) / 6.5)), 3);
            $caption = trim(explode('·', $item['notes'] ?? '')[0]);
            // Un repère court reste visible ; la rédaction complète figure dans l’annexe.
            $notes = mb_strlen($caption) <= 32
                ? $this->wrap($caption, max(10, (int) (($width - 18) / 6)), 2)
                : [];
            $patch = $this->wrap($item['patchLabel'] ?? '', max(10, (int) (($width - 18) / 6)), 3);
            $symbolWidth = match ($kind) {
                'drums' => $width,
                'amp', 'keys' => 42,
                'wedge' => 36,
                default => 24,
            };
            $symbolHeight = 'drums' === $kind ? ($symbolWidth * 184) / 260 : $symbolWidth;
            $height =
                'riser' === $kind
                    ? 236
                    : max(38, $symbolHeight + 14 + count($label) * 17 + count($notes) * 13 + count($patch) * 12);
            $x = ($this->number($item['x'] ?? null, 50, 0, 100) / 100) * self::WIDTH;
            $y = ($this->number($item['y'] ?? null, 50, 0, 100) / 100) * self::HEIGHT;
            $rotation = $this->number($item['rotation'] ?? null, 0, 0, 360);
            $scale = $this->number($item['scale'] ?? null, 1, 0.5, 2.5);
            $presentationScale = $scale * StagePresentation::BASE_SCALE;
            $radians = deg2rad($rotation);
            $halfWidth =
                ((abs(cos($radians)) * $width + abs(sin($radians)) * $height) * $presentationScale) / 2;
            $halfHeight =
                ((abs(sin($radians)) * $width + abs(cos($radians)) * $height) * $presentationScale) / 2;
            $left = min($left, $x - $halfWidth - 10);
            $top = min($top, $y - $halfHeight - 10);
            $right = max($right, $x + $halfWidth + 10);
            $bottom = max($bottom, $y + $halfHeight + 10);
            $labelY = -$height / 2 + ('riser' === $kind ? 20 : $symbolHeight + 23);
            $nodes[] = compact(
                'kind',
                'family',
                'ink',
                'background',
                'width',
                'height',
                'fill',
                'stroke',
                'label',
                'notes',
                'patch',
                'x',
                'y',
                'rotation',
                'scale',
                'presentationScale',
                'labelY',
                'symbolWidth',
                'symbolHeight',
            );
        }

        // Les petits repères passent au-dessus des instruments ; les praticables restent au fond.
        usort(
            $nodes,
            fn (array $a, array $b): int => $this->layer($a['kind']) <=> $this->layer($b['kind']),
        );

        return [
            'width' => $right - $left,
            'height' => $bottom - $top,
            'viewBox' => sprintf(
                '%.2F %.2F %.2F %.2F',
                $left,
                $top,
                $right - $left,
                $bottom - $top,
            ),
            'nodes' => $nodes,
        ];
    }

    private function layer(string $kind): int
    {
        return match ($kind) {
            'riser' => 0,
            'power', 'mic_vocal', 'mic_instrument', 'stand_straight', 'stand_boom', 'di_mono', 'di_stereo' => 2,
            default => 1,
        };
    }

    private function number(mixed $value, float $default, float $min, float $max): float
    {
        return is_numeric($value) && is_finite((float) $value)
            ? min($max, max($min, (float) $value))
            : $default;
    }

    /**
     * @param positive-int $width
     * @param positive-int $limit
     *
     * @return list<string>
     */
    private function wrap(string $text, int $width, int $limit): array
    {
        $text =
            preg_replace(
                '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
                '',
                $text,
            ) ?? '';
        $words = preg_split('/\s+/u', trim($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            foreach (mb_str_split($word, $width) as $part) {
                if ('' !== $line && mb_strlen($line.' '.$part) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line = '' === $line ? $part : $line.' '.$part;
            }
        }
        if ('' !== $line) {
            $lines[] = $line;
        }
        if (count($lines) > $limit) {
            $lines = array_slice($lines, 0, $limit);
            $lines[$limit - 1] = rtrim(mb_substr($lines[$limit - 1], 0, $width - 1)).'…';
        }

        return array_values($lines);
    }
}
