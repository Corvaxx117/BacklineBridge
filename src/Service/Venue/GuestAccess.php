<?php

declare(strict_types=1);

namespace App\Service\Venue;

use App\Entity\VenueInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GuestAccess
{
    private const SESSION_KEY = 'venue_invitation_accesses';

    public function __construct(private EntityManagerInterface $em) {}

    public function enter(Request $request, string $token): string
    {
        $invitation = $this->em
            ->getRepository(VenueInvitation::class)
            ->findOneBy(['tokenHash' => hash('sha256', $token)]);
        $this->assertUsable($invitation);
        $session = $request->getSession();
        $session->migrate(true);
        $accesses = $session->get(self::SESSION_KEY, []);
        foreach ($accesses as $context => $id) {
            if ($id === $invitation->getId()) {
                return $context;
            }
        }
        // L'URL identifie un contexte de cette session ; elle ne suffit pas à autoriser l'accès.
        $context = bin2hex(random_bytes(16));
        $accesses[$context] = $invitation->getId();
        $session->set(self::SESSION_KEY, $accesses);

        return $context;
    }

    public function context(Request $request): string
    {
        $context = $request->query->get('access');
        $accesses = $request->getSession()->get(self::SESSION_KEY, []);
        if (!is_string($context) || !isset($accesses[$context])) {
            throw $this->unavailable();
        }

        return $context;
    }

    public function invitation(Request $request): VenueInvitation
    {
        $context = $this->context($request);
        $id = $request->getSession()->get(self::SESSION_KEY)[$context];
        $invitation = is_int($id) ? $this->em->find(VenueInvitation::class, $id) : null;
        $this->assertUsable($invitation);

        return $invitation;
    }

    public function leave(Request $request): void
    {
        $context = $this->context($request);
        $accesses = $request->getSession()->get(self::SESSION_KEY);
        unset($accesses[$context]);
        $request->getSession()->set(self::SESSION_KEY, $accesses);
    }

    private function assertUsable(?VenueInvitation $invitation): void
    {
        if ($invitation === null || !$invitation->isUsable()) {
            throw $this->unavailable();
        }
    }

    private function unavailable(): NotFoundHttpException
    {
        return new NotFoundHttpException(
            'Cet accès invité est indisponible. Demandez un nouveau lien au groupe.',
        );
    }
}
