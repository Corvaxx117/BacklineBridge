<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentSource: string
{
    case Pdf = 'pdf';
    case Link = 'lien';
}
