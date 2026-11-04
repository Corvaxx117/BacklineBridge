<?php

declare(strict_types=1);

use App\Dto\Venue\InvitationInput;
use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Kernel;
use App\Service\Concert\ConcertCreator;
use App\Service\Security\UserCreator;
use App\Service\Venue\InvitationIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
if (PHP_SAPI !== 'cli' || ($_SERVER['APP_ENV'] ?? '') !== 'browser') {
    throw new RuntimeException('Cette préparation est réservée aux tests navigateur.');
}
(new Dotenv())->bootEnv($root.'/.env');
$kernel = new Kernel('browser', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
// L'accès Doctrine public est fourni par le registre, sans service de test en production.
$em = $container->get('doctrine')->getManager();
assert($em instanceof EntityManagerInterface);
$path = $em->getConnection()->getParams()['path'] ?? '';
if (dirname($path) !== $root.'/var/browser' || !str_starts_with(basename($path), 'backlinebridge-browser-') || is_file($path)) {
    throw new RuntimeException('Utiliser une base navigateur neuve dans var/browser.');
}
(new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
$fixture = json_decode((string) file_get_contents($root.'/data/avee-mana-patch.json'), true, flags: JSON_THROW_ON_ERROR);
$stage = json_decode((string) file_get_contents($root.'/data/avee-mana-stage.json'), true, flags: JSON_THROW_ON_ERROR);
$sheet = (new TechnicalSheet())->setBandName('Groupe fictif')->setStagePlan($stage['stagePlan'])->setEquipmentRequests($stage['equipment']);
foreach ($fixture['lines'] as $data) {
    $sheet->addLine(
        (new PatchLine())
            ->setChannelNumber($data['channelNumber'])
            ->setSourceName($data['sourceName'])
            ->setPreferredMicrophone($data['preferredMicrophone'])
            ->setStand($data['stand'])
            ->setNotes($data['notes'])
            ->setSection($data['section']),
    );
}
$band = new Band('Groupe fictif', $sheet);
$em->persist($band);
$em->flush();
// Les services métier sont privés : on les utilise via un conteneur browser dédié.
$creator = new UserCreator(
    $em,
    $container->get(Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class),
    $container->get(Symfony\Component\Validator\Validator\ValidatorInterface::class),
);
$user = $creator->create('browser@example.test', 'Musicien fictif', 'BrowserTest2026!', $band, BandRole::Manager);
$em->persist(new MemberProfile($band, $user, 'Musicien fictif', 'Guitare', MemberKind::Musician));
$em->flush();
$availability = new App\Service\Concert\ConcertAvailabilityManager(
    $em,
    $em->getRepository(App\Entity\BandMembership::class),
    $em->getRepository(App\Entity\ConcertAvailability::class),
);
$concertCreator = new ConcertCreator(
    $em,
    $availability,
    $em->getRepository(App\Entity\BandMembership::class),
    new App\Service\Patch\PatchLineCopier(),
    new App\Service\Notification\NotificationRecorder(
        $em,
        $em->getRepository(App\Entity\BandMembership::class),
        new Symfony\Component\Clock\NativeClock(),
        new App\Service\Notification\Push\PushQueue(
            $em,
            $em->getRepository(App\Entity\PushSubscription::class),
            new App\Service\Notification\Push\PushConfiguration(false, '', $root, true),
        ),
    ),
);
$author = $creator->create('author@example.test', 'Membre fictif', 'BrowserTest2026!', $band, BandRole::Booker);
$concert = $concertCreator->create(
    $sheet, new DateTimeImmutable('+5 days'), 'Salle fictive', 'Ville fictive', createdBy: $author,
);
$issuer = new InvitationIssuer($em, new App\Service\Venue\PublicationBuilder(new App\Service\Stage\StagePatchLinks()));
$input = new InvitationInput();
$input->label = 'Régie fictive';
$invitation = $issuer->issue($concert, $input);
file_put_contents($root.'/var/browser/state.json', json_encode([
    'sheetId' => $sheet->getId(), 'concertId' => $concert->getId(),
    'email' => $user->getEmail(), 'password' => 'BrowserTest2026!',
    'invitation' => '/invitation/'.$invitation->token,
], JSON_THROW_ON_ERROR));
$kernel->shutdown();
