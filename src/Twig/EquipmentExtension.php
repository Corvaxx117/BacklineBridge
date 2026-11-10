<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Une seule présentation du kit ; les demandes et leurs réponses restent distinctes. */
final class EquipmentExtension extends AbstractExtension
{
    private const array DRUM_KIT_IDS = [
        'kick', 'rack_tom', 'floor_tom', 'snare_stand',
        'cymbal_stands', 'hh_stand', 'drum_stool', 'drum_rug',
    ];

    public function getFunctions(): array
    {
        return [new TwigFunction('is_drum_kit', $this->isDrumKit(...))];
    }

    /** @param array<array-key, array<string, mixed>> $items */
    public function isDrumKit(array $items): bool
    {
        $ids = [];
        foreach ($items as $key => $item) {
            $id = $item['id'] ?? (is_string($key) ? preg_replace('/^gear_/', '', $key) : null);
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return [] === array_diff(self::DRUM_KIT_IDS, $ids);
    }
}
