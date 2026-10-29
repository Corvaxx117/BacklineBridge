<?php

declare(strict_types=1);

namespace App\Enum;

enum EngineerPayment: string
{
    case Unknown = 'unknown';
    case Included = 'included';
    case Separate = 'separate';
    case NotPlanned = 'not_planned';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::Included => 'Incluse dans le montant du concert',
            self::Separate => 'Prévue séparément',
            self::NotPlanned => 'Non prévue',
        };
    }
}
