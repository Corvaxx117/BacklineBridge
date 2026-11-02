<?php

declare(strict_types=1);

namespace App\Service\Setlist;

use App\Entity\Setlist;
use App\Entity\TechnicalSheet;

final class SetlistVersion
{
    public static function fingerprint(TechnicalSheet $sheet, ?Setlist $setlist): string
    {
        return hash('sha256', json_encode([
            'sheet' => $sheet->getId(),
            'id' => $setlist?->getId(),
            'revision' => $setlist?->getRevision(),
            'tracks' => $setlist?->getTracks() ?? [],
        ], JSON_THROW_ON_ERROR));
    }
}
