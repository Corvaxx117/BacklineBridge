<?php

declare(strict_types=1);

namespace App\Dto\Concert;

use Symfony\Component\Validator\Constraints as Assert;

final class OrfeoImportInput
{
    /** @var list<string> */
    #[Assert\Count(min: 1, max: 100, minMessage: 'Sélectionne au moins une date.', maxMessage: 'Importe au maximum 100 dates à la fois.')]
    public array $events = [];

    /** @var array<string, string> */
    public array $targets = [];

    public bool $updateExisting = false;

    public bool $importContacts = true;

    public static function fieldKey(string $eventId): string
    {
        return 'event_'.str_replace(':', '_', $eventId);
    }
}
