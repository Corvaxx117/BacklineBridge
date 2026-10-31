<?php

declare(strict_types=1);

namespace App\Controller\Concert;

use App\Entity\Concert;
use App\Entity\ConcertConditions;
use App\Entity\ConcertContact;
use App\Entity\User;
use App\Form\ConcertAvailabilityType;
use App\Repository\BandDocumentRepository;
use App\Repository\BandsintownConcertRepository;
use App\Repository\PatchLineRepository;
use App\Security\Voter\ConcertVoter;
use App\Service\Concert\ConcertAvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(
    '/concerts/{id}',
    name: 'app_concert_show',
    requirements: ['id' => '\d+'],
    methods: ['GET'],
),]
final class ShowAction extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Concert $concert,
        PatchLineRepository $lines,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
        ConcertAvailabilityManager $availability,
        BandsintownConcertRepository $bandsintown,
        BandDocumentRepository $documents,
    ): Response {
        $this->denyAccessUnlessGranted(ConcertVoter::VIEW, $concert);
        $canViewConditions = $this->isGranted(ConcertVoter::CONDITIONS, $concert);

        return $this->render('concert/show.html.twig', [
            'concert' => $concert,
            'contacts' => $entityManager->getRepository(ConcertContact::class)->findBy(['concert' => $concert], ['name' => 'ASC']),
            'documents' => $documents->forConcert($concert),
            'bandsintownSource' => $bandsintown->forConcert($concert),
            'sheet' => $concert->getPatchSheet(),
            'lines' => $lines->findForSheet($concert->getPatchSheet()),
            'canViewConditions' => $canViewConditions,
            'availability' => $availability->roster($concert, $user),
            'availabilityForm' => $this->isGranted(ConcertVoter::RESPOND_AVAILABILITY, $concert)
                ? $this->createForm(
                    ConcertAvailabilityType::class,
                    $availability->inputFor($concert, $user),
                )->createView()
                : null,
            'conditions' => $canViewConditions
                ? $entityManager
                    ->getRepository(ConcertConditions::class)
                    ->findOneBy(['concert' => $concert])
                : null,
        ]);
    }
}
