<?php

declare(strict_types=1);

namespace App\Controller\Notification\Push;

use App\Dto\Notification\PushSubscriptionInput;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\Notification\Push\PushDeviceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/notifications/appareil', name: 'app_notification_push_enable', methods: ['POST'])]
#[IsCsrfTokenValid('notification_push', tokenKey: 'X-CSRF-Token', tokenSource: IsCsrfTokenValid::SOURCE_HEADER)]
final class EnableAction extends AbstractController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        PushDeviceManager $devices,
        PushSubscriptionRepository $subscriptions,
        #[Autowire(service: 'limiter.push_device_updates')] RateLimiterFactory $limiter,
    ): JsonResponse {
        if (!$limiter->create((string) $user->getId())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Patientez avant de réactiver les notifications.');
        }
        if (strlen($request->getContent()) > 8192) {
            throw new BadRequestHttpException('Les informations de cet appareil sont trop longues.');
        }
        try {
            $input = PushSubscriptionInput::fromArray($request->toArray());
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Les informations de cet appareil sont invalides.');
        }
        $token = $request->cookies->getString(PushDeviceManager::COOKIE);
        if (null === $subscriptions->device($user, $token)) {
            $token = bin2hex(random_bytes(32));
        }
        $devices->enable($user, $token, $input);
        $response = $this->json(['enabled' => true]);
        $response->headers->setCookie(Cookie::create(PushDeviceManager::COOKIE)
            ->withValue($token)->withExpires(new \DateTimeImmutable('+1 year'))
            ->withSecure($request->isSecure())->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_STRICT));

        return $response;
    }
}
