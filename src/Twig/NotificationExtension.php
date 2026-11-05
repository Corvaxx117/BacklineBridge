<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\NotificationRecipientRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(private readonly NotificationRecipientRepository $recipients)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('notification_unread_count', $this->recipients->unreadCount(...))];
    }
}
