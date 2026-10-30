<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;

final class BandsintownImportInput
{
    /** @var list<string> */
    #[Assert\Count(
        min: 1,
        max: 100,
        minMessage: 'Sélectionne au moins un concert.',
        maxMessage: 'Importe au maximum 100 concerts à la fois.',
    ),]
    public array $events = [];
}
