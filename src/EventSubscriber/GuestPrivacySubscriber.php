<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\{ResponseEvent, ExceptionEvent};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final readonly class GuestPrivacySubscriber implements EventSubscriberInterface
{
    public function __construct(private Environment $twig) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'headers',
            KernelEvents::EXCEPTION => ['unavailable', 20],
        ];
    }

    public function headers(ResponseEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (str_starts_with($route, 'app_guest_') || str_contains($route, 'invitation')) {
            $event->getResponse()->headers->set('Cache-Control', 'no-store, private');
            $event->getResponse()->headers->set('Referrer-Policy', 'no-referrer');
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function unavailable(ExceptionEvent $event): void
    {
        if (
            str_starts_with(
                (string) $event->getRequest()->attributes->get('_route', ''),
                'app_guest_',
            ) &&
            $event->getThrowable() instanceof NotFoundHttpException
        ) {
            $event->setResponse(
                new Response($this->twig->render('guest/unavailable.html.twig'), 404),
            );
        }
    }
}
