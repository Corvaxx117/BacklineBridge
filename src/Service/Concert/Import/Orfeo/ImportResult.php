<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Orfeo;

final readonly class ImportResult
{
    public function __construct(
        public int $created,
        public int $linked,
        public int $updated,
        public int $contactsAdded,
        public int $contactsPreserved,
    ) {
    }
}
