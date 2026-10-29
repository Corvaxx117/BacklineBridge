<?php

declare(strict_types=1);

namespace App\Enum;

enum StockMovementKind: string
{
    case Initial = 'initial';
    case Sale = 'sale';
    case Restock = 'restock';
    case Count = 'count';
    case Gift = 'gift';
    case Return = 'return';
    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stock de départ',
            self::Sale => 'Vente',
            self::Restock => 'Entrée de stock',
            self::Count => 'Inventaire',
            self::Gift => 'Sortie sans vente',
            self::Return => 'Annulation de vente',
        };
    }
}
