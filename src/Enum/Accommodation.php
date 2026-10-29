<?php

declare(strict_types=1);

namespace App\Enum;

enum Accommodation: string
{
    case Unknown = 'unknown';
    case OnSite = 'on_site';
    case Elsewhere = 'elsewhere';
    case ReturnHome = 'return_home';
    case NotProvided = 'not_provided';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::OnSite => 'Hébergement sur place',
            self::Elsewhere => 'Hébergement ailleurs',
            self::ReturnHome => 'Retour à la casa !',
            self::NotProvided => 'Non fourni',
        };
    }
}
