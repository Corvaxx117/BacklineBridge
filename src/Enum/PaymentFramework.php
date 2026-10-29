<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentFramework: string
{
    case Unknown = 'unknown';
    case AssociationInvoice = 'association_invoice';
    case ArtistFees = 'artist_fees';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'À confirmer',
            self::AssociationInvoice => 'Facture association',
            self::ArtistFees => 'Cachets',
            self::Other => 'Autre · à préciser',
        };
    }
}
