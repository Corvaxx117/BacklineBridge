<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\Notification\Push\PushDeviceManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class PushLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(private PushDeviceManager $devices)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => ['revoke', -32]];
    }

    public function revoke(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if ($user instanceof User) {
            $this->devices->disable($user, $event->getRequest()->cookies->getString(PushDeviceManager::COOKIE));
        }
        $event->getResponse()?->headers->clearCookie(PushDeviceManager::COOKIE);
    }
}
