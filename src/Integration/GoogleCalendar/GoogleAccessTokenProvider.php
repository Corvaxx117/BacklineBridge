<?php

declare(strict_types=1);

namespace App\Integration\GoogleCalendar;

interface GoogleAccessTokenProvider
{
    public function token(): string;
}
