<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar\Dto;

final readonly class PublicationResult
{
    public function __construct(public bool $created, public ?string $url)
    {
    }
}
