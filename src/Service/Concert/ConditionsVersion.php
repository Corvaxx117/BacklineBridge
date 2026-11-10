<?php

declare(strict_types=1);

namespace App\Service\Concert;

use App\Entity\ConcertConditions;

final class ConditionsVersion
{
    public static function fingerprint(?ConcertConditions $conditions): string
    {
        $values = [];
        if (null !== $conditions) {
            foreach ([
                'Accommodation',
                'TransportCoverage',
                'PaymentFramework',
                'PaymentMethod',
                'EngineerPresence',
                'EngineerPayment',
                'EngineerPaymentFramework',
                'FeeCents',
                'FeeBasis',
                'EngineerFeeCents',
                'Notes',
                'AccommodationNotes',
                'TransportNotes',
                'MealNotes',
            ] as $field) {
                $value = $conditions->{'get'.$field}();
                $values[] = $value instanceof \BackedEnum ? $value->value : $value;
            }
        }

        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }
}
