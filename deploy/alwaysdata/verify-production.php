<?php

declare(strict_types=1);

use App\Entity\MemberProfile;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Enum\MemberKind;
use App\Kernel;
use App\Service\Member\ProfileCipher;
use App\Service\Pdf\StagePlanLayout;
use App\Service\Pdf\TechnicalDossierFactory;
use App\Service\Pdf\TechnicalPdfRenderer;
use App\Service\Venue\PublicationBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $root = dirname(__DIR__, 2);
    require $root.'/vendor/autoload.php';
    new Dotenv()->bootEnv($root.'/.env');
    if (
        ($_SERVER['APP_ENV'] ?? '') !== 'prod'
        || ($_SERVER['APP_DEBUG'] ?? '') !== '0'
        || ($_SERVER['APP_DEMO_MODE'] ?? '') !== '0'
    ) {
        throw new RuntimeException('Ce contrôle est réservé à l’instance officielle.');
    }
    $kernel = new Kernel('prod', false);
    $kernel->boot();
    $em = $kernel->getContainer()->get('doctrine')->getManager();
    if (!$em instanceof EntityManagerInterface) {
        throw new RuntimeException('Doctrine ORM est nécessaire.');
    }
    $path = $em->getConnection()->getParams()['path'] ?? '';
    if (!is_string($path) || realpath($path) !== realpath($root.'/var/production.db')) {
        throw new RuntimeException('La base attendue est var/production.db.');
    }
    $profiles = $em->getRepository(MemberProfile::class)->findAll();
    $cipher = new ProfileCipher($_SERVER['PROFILE_DATA_KEY']);
    $musicians = 0;
    foreach ($profiles as $profile) {
        $cipher->decrypt($profile);
        if (MemberKind::Musician === $profile->getKind()) {
            ++$musicians;
        }
    }
    if (6 !== count($profiles) || 4 !== $musicians) {
        throw new RuntimeException('Les profils attendus ne sont pas tous présents.');
    }
    $connection = $em->getConnection();
    $integrity = $connection->fetchOne('PRAGMA integrity_check');
    if ('ok' !== $integrity || [] !== $connection->fetchAllAssociative('PRAGMA foreign_key_check')) {
        throw new RuntimeException('La base nécessite une vérification.');
    }
    if (in_array('--initial', $argv, true)) {
        if (
            0 !== (int) $connection->fetchOne('SELECT COUNT(*) FROM concert')
            || 0 !== (int) $connection->fetchOne("SELECT COUNT(*) FROM app_user WHERE password <> ''")
        ) {
            throw new RuntimeException('L’installation initiale contient des données d’essai.');
        }
    }
    $sheet = $em->getRepository(TechnicalSheet::class)->findReference();
    if (null === $sheet) {
        throw new RuntimeException('La fiche de référence est absente.');
    }
    $factory = new TechnicalDossierFactory(
        $em->getRepository(PatchLine::class),
        new PublicationBuilder(),
    );
    $twig = new Environment(new FilesystemLoader($root.'/templates'), ['strict_variables' => true]);
    $twig->addGlobal('app_name', 'BacklineBridge');
    $renderer = new TechnicalPdfRenderer(
        $twig,
        new StagePlanLayout(),
        $root.'/var/cache/prod/pdf',
        $root.'/public',
    );
    $pdf = $renderer->render($factory->forSheet($sheet));
    if (!str_starts_with($pdf, '%PDF-') || !str_contains($pdf, '%%EOF')) {
        throw new RuntimeException('Le PDF est invalide.');
    }
    $uri = $_SERVER['DEFAULT_URI'] ?? '';
    if ('https://backlinebridgeoffice.alwaysdata.net' !== $uri) {
        throw new RuntimeException('Adresse officielle inattendue.');
    }
    $request = Request::create($uri.'/connexion');
    $response = $kernel->handle($request);
    if (200 !== $response->getStatusCode() || str_contains($response->getContent() ?: '', 'demo-banner')) {
        throw new RuntimeException('La page de connexion officielle est incorrecte.');
    }
    $kernel->terminate($request, $response);
    echo json_encode([
        'environment' => 'production',
        'profiles' => count($profiles),
        'musicians' => $musicians,
        'database_integrity' => 'ok',
        'pdf_bytes' => strlen($pdf),
        'login_status' => $response->getStatusCode(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Contrôle officiel incomplet. Consulter les journaux privés sans publier leur contenu.\n");
    exit(1);
}
