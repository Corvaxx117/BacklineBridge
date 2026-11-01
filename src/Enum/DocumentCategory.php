<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentCategory: string
{
    case Technical = 'technical';
    case Roadmap = 'roadmap';
    case Contract = 'contract';
    case Logistics = 'logistics';
    case Promotion = 'promotion';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Technique',
            self::Roadmap => 'Feuilles de route',
            self::Contract => 'Contrats',
            self::Logistics => 'Logistique',
            self::Promotion => 'Communication',
            self::Other => 'Autres documents',
        };
    }
}
