<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\EquipmentExtension;
use PHPUnit\Framework\TestCase;

final class EquipmentExtensionTest extends TestCase
{
    public function testOnlyTheCompleteRequestedSetIsPresentedAsAFullKit(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/data/avee-mana-stage.json'), true, flags: JSON_THROW_ON_ERROR);
        $items = array_values(array_filter($fixture['equipment'], static fn (array $item): bool => 'Batterie' === $item['category']));
        $presentation = new EquipmentExtension();
        self::assertTrue($presentation->isDrumKit($items));
        $requests = [];
        foreach ($items as $item) {
            $requests['gear_'.$item['id']] = ['label' => $item['label']];
        }
        self::assertTrue($presentation->isDrumKit($requests));
        unset($requests['gear_drum_rug']);
        self::assertFalse($presentation->isDrumKit($requests));
        self::assertFalse($presentation->isDrumKit([]));
    }
}
