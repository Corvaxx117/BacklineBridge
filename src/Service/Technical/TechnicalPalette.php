<?php

declare(strict_types=1);

namespace App\Service\Technical;

/** Palette unique du patch, du plan et du PDF ; les textes restent lisibles sans couleur. */
final class TechnicalPalette
{
    private const array FAMILIES = [
        'drums' => [
            'label' => 'Drums',
            'background' => '#fff4c2',
            'ink' => '#69510b',
            'border' => '#c4a137',
            'darkBackground' => '#3b321b',
            'darkInk' => '#f4d885',
            'darkBorder' => '#a98a34',
        ],
        'bass' => [
            'label' => 'Bass',
            'background' => '#e0f0df',
            'ink' => '#24523c',
            'border' => '#83ad87',
            'darkBackground' => '#203b2d',
            'darkInk' => '#a9ddb0',
            'darkBorder' => '#65976d',
        ],
        'guitar' => [
            'label' => 'Guitars',
            'background' => '#e2edff',
            'ink' => '#224a7d',
            'border' => '#80a6d7',
            'darkBackground' => '#20354e',
            'darkInk' => '#afd2ff',
            'darkBorder' => '#668ebd',
        ],
        'keys' => [
            'label' => 'Keyboards',
            'background' => '#eee3fa',
            'ink' => '#654287',
            'border' => '#b49bce',
            'darkBackground' => '#352b46',
            'darkInk' => '#d4b9ef',
            'darkBorder' => '#9377af',
        ],
        'vocals' => [
            'label' => 'Vocals',
            'background' => '#fbe3e9',
            'ink' => '#852b48',
            'border' => '#cf92a5',
            'darkBackground' => '#432936',
            'darkInk' => '#f1b1c5',
            'darkBorder' => '#ad7488',
        ],
        'utility' => [
            'label' => 'Utility',
            'background' => '#e7eef2',
            'ink' => '#3d5360',
            'border' => '#9cafb9',
            'darkBackground' => '#293a44',
            'darkInk' => '#c3d3dd',
            'darkBorder' => '#78929f',
        ],
        'effects' => [
            'label' => 'FX',
            'background' => '#ffecd7',
            'ink' => '#81450f',
            'border' => '#d3a268',
            'darkBackground' => '#432f1e',
            'darkInk' => '#f3c48c',
            'darkBorder' => '#ac8555',
        ],
        'monitors' => [
            'label' => 'Monitors',
            'background' => '#dcf2ef',
            'ink' => '#205e59',
            'border' => '#82b6ac',
            'darkBackground' => '#1d3b3a',
            'darkInk' => '#a4dbd3',
            'darkBorder' => '#629f96',
        ],
    ];

    private const array DEFAULTS = [
        'drums' => 'drums',
        'riser' => 'drums',
        'keys' => 'keys',
        'person' => 'vocals',
        'mic_vocal' => 'vocals',
        'wedge' => 'monitors',
    ];

    private const array RULES = [
        [
            'kinds' => ['amp', 'person', 'other', 'di_mono', 'di_stereo', 'mic_instrument'],
            'words' => ['bass', 'basse'],
            'family' => 'bass',
        ],
        [
            'kinds' => ['amp', 'person', 'other', 'mic_instrument'],
            'words' => ['guit'],
            'family' => 'guitar',
        ],
        [
            'kinds' => ['other', 'di_mono', 'di_stereo'],
            'words' => ['clavi', 'keyboard', 'synth'],
            'family' => 'keys',
        ],
    ];

    /** @return array<string, array{label: string, background: string, ink: string, border: string, darkBackground: string, darkInk: string, darkBorder: string}> */
    public function families(): array
    {
        return self::FAMILIES;
    }

    /** @return array{defaults: array<string, string>, rules: list<array{kinds: list<string>, words: list<string>, family: string}>} */
    public function stageRules(): array
    {
        return ['defaults' => self::DEFAULTS, 'rules' => self::RULES];
    }

    public function normalize(?string $family): string
    {
        return isset(self::FAMILIES[$family ?? '']) ? (string) $family : 'utility';
    }

    /** @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $sources */
    public function stageFamily(array $item, array $sources = []): string
    {
        $linked = [];
        foreach ($item['patchSources'] ?? [] as $key) {
            if (($sources[$key]['available'] ?? false) && isset($sources[$key]['family'])) {
                $linked[$this->normalize($sources[$key]['family'])] = true;
            }
        }
        if (1 === count($linked)) {
            return (string) array_key_first($linked);
        }
        $kind = $item['kind'] ?? 'other';
        $text = mb_strtolower(($item['label'] ?? '').' '.($item['notes'] ?? ''));
        foreach (self::RULES as $rule) {
            if (!in_array($kind, $rule['kinds'], true)) {
                continue;
            }
            foreach ($rule['words'] as $word) {
                if (str_contains($text, $word)) {
                    return $rule['family'];
                }
            }
        }

        return self::DEFAULTS[$kind] ?? 'utility';
    }

    /** @param array<string, mixed> $item */
    public function equipmentFamily(array $item): string
    {
        $id = $item['id'] ?? '';

        return match (true) {
            'Batterie' === ($item['category'] ?? ''), 'riser' === $id => 'drums',
            str_contains($id, 'bass') => 'bass',
            str_contains($id, 'guitar'), 'amp_supports' === $id => 'guitar',
            str_contains($id, 'keyboard') => 'keys',
            'Retours' === ($item['category'] ?? '') => 'monitors',
            default => 'utility',
        };
    }
}
