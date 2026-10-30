<?php

declare(strict_types=1);

namespace App\Service\Concert\Import\Bandsintown;

final readonly class ImportResult
{
    public function __construct(public int $created, public int $linked, public int $skipped)
    {
    }
}
