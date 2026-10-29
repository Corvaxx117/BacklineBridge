<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Stage\StageItemInput;

/** Prépare un dessin vectoriel autonome, sans dépendre du JavaScript de l'éditeur. */
final class StagePlanLayout
{
    private const WIDTH = 1000;
    private const HEIGHT = 606;
    private const STYLES = [
        'riser' => [280, '#e9e6d3', '#9b9a7f'],
        'drums' => [260, 'none', 'none'],
        'amp' => [130, '#e9e0e6', '#bcabb7'],
        'person' => [160, 'none', 'none'],
        'wedge' => [120, '#e9bc85', '#b28756'],
        'keys' => [160, '#dce8d5', '#99b18c'],
        'power' => [130, '#fffefa', '#bacbc0'],
        'other' => [130, '#fffefa', '#bacbc0'],
    ];

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
            $label = $this->wrap($item['label'] ?? '', max(10, (int) (($width - 18) / 8)), 3);
            $notes = $this->wrap($item['notes'] ?? '', max(10, (int) (($width - 18) / 6)), 2);
            $symbolWidth = $kind === 'drums' ? $width - 28 : 24;
            $symbolHeight = $kind === 'drums' ? ($symbolWidth * 184) / 260 : 24;
            $height =
                $kind === 'riser'
                    ? 236
                    : max(74, $symbolHeight + 14 + count($label) * 17 + count($notes) * 13);
            $x = ($this->number($item['x'] ?? null, 50, 0, 100) / 100) * self::WIDTH;
            $y = ($this->number($item['y'] ?? null, 50, 0, 100) / 100) * self::HEIGHT;
            $rotation = $this->number($item['rotation'] ?? null, 0, 0, 360);
            $scale = $this->number($item['scale'] ?? null, 1, 0.5, 2.5);
            $radians = deg2rad($rotation);
            $halfWidth =
                ((abs(cos($radians)) * $width + abs(sin($radians)) * $height) * $scale) / 2;
            $halfHeight =
                ((abs(sin($radians)) * $width + abs(cos($radians)) * $height) * $scale) / 2;
            $left = min($left, $x - $halfWidth - 10);
            $top = min($top, $y - $halfHeight - 10);
            $right = max($right, $x + $halfWidth + 10);
            $bottom = max($bottom, $y + $halfHeight + 10);
            $labelY = -$height / 2 + ($kind === 'riser' ? 20 : $symbolHeight + 23);
            $nodes[] = compact(
                'kind',
                'width',
                'height',
                'fill',
                'stroke',
                'label',
                'notes',
                'x',
                'y',
                'rotation',
                'scale',
                'labelY',
                'symbolWidth',
                'symbolHeight',
            );
        }

        // Les praticables restent derrière les instruments, comme dans l'application.
        usort(
            $nodes,
            static fn(array $a, array $b): int => ($a['kind'] !== 'riser') <=>
                ($b['kind'] !== 'riser'),
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

    private function number(mixed $value, float $default, float $min, float $max): float
    {
        return is_numeric($value) && is_finite((float) $value)
            ? min($max, max($min, (float) $value))
            : $default;
    }

    /** @return list<string> */
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
                if ($line !== '' && mb_strlen($line . ' ' . $part) > $width) {
                    $lines[] = $line;
                    $line = '';
                }
                $line = $line === '' ? $part : $line . ' ' . $part;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $limit) {
            $lines = array_slice($lines, 0, $limit);
            $lines[$limit - 1] = rtrim(mb_substr($lines[$limit - 1], 0, $width - 1)) . '…';
        }

        return $lines;
    }
}
