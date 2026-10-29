<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PrivateDataSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'protect'];
    }

    public function protect(ResponseEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        foreach (
            [
                'app_treasury_',
                'app_stock_',
                'app_profile_',
                'app_activation_',
                'app_concert_bandsintown_',
            ]
            as $prefix
        ) {
            if (str_starts_with($route, $prefix)) {
                $headers = $event->getResponse()->headers;
                $headers->set('Cache-Control', 'no-store, private');
                $headers->set('Referrer-Policy', 'no-referrer');
                $headers->set('X-Robots-Tag', 'noindex, nofollow');
                break;
            }
        }
    }
}
