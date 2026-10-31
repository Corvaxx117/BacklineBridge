<?php

declare(strict_types=1);

use App\Entity\MemberProfile;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
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
    $projectDir = dirname(__DIR__, 2);
    require $projectDir.'/vendor/autoload.php';
    new Dotenv()->bootEnv($projectDir.'/.env');
    if (
        'prod' !== $_SERVER['APP_ENV']
        || '0' !== $_SERVER['APP_DEBUG']
        || ($_SERVER['APP_DEMO_MODE'] ?? '') !== '1'
    ) {
        throw new RuntimeException('Ce contrôle nécessite la démonstration en mode production.');
    }

    $kernel = new Kernel('prod', false);
    $kernel->boot();
    $manager = $kernel->getContainer()->get('doctrine')->getManager();
    if (!$manager instanceof EntityManagerInterface) {
        throw new RuntimeException('Le contrôle nécessite Doctrine ORM.');
    }
    $databasePath = $manager->getConnection()->getParams()['path'] ?? '';
    if (realpath($databasePath) !== realpath($projectDir.'/var/demo.db')) {
        throw new RuntimeException('Le contrôle doit utiliser uniquement var/demo.db.');
    }
    $profiles = $manager->getRepository(MemberProfile::class)->findAll();
    if (6 !== count($profiles)) {
        throw new RuntimeException('Les six profils fictifs doivent être initialisés.');
    }
    $cipher = new ProfileCipher($_SERVER['PROFILE_DATA_KEY']);
    foreach ($profiles as $profile) {
        if (
            ($cipher->decrypt($profile)['Information'] ?? '') !==
            'Profil fictif réservé aux essais.'
        ) {
            throw new RuntimeException('Le contrôle est réservé aux profils fictifs.');
        }
    }

    $sheet = $manager->getRepository(TechnicalSheet::class)->findReference();
    if (null === $sheet) {
        throw new RuntimeException('Initialiser la fiche technique avant ce contrôle.');
    }
    $stageLinks = new App\Service\Stage\StagePatchLinks();
    $factory = new TechnicalDossierFactory(
        $manager->getRepository(PatchLine::class),
        new PublicationBuilder($stageLinks),
        $stageLinks,
    );
    $twig = new Environment(new FilesystemLoader($projectDir.'/templates'), [
        'strict_variables' => true,
    ]);
    $twig->addGlobal('app_name', 'BacklineBridge');
    $renderer = new TechnicalPdfRenderer(
        $twig,
        new StagePlanLayout(),
        $projectDir.'/var/cache/prod/pdf',
        $projectDir.'/public',
    );
    $pdf = $renderer->render($factory->forSheet($sheet));
    if (!str_starts_with($pdf, '%PDF-') || !str_contains($pdf, '%%EOF')) {
        throw new RuntimeException('L’export PDF de démonstration a échoué.');
    }

    $request = Request::create($_SERVER['DEFAULT_URI'].'/connexion');
    $response = $kernel->handle($request);
    if (
        200 !== $response->getStatusCode()
        || !str_contains($response->getContent() ?: '', 'demo-banner')
    ) {
        throw new RuntimeException('La connexion de démonstration ne s’affiche pas correctement.');
    }
    $kernel->terminate($request, $response);
    echo json_encode(
        [
            'environment' => 'prod',
            'debug' => false,
            'database' => 'var/demo.db',
            'fictitious_profiles' => count($profiles),
            'profile_decryption' => 'ok',
            'login_page' => 'ok',
            'pdf_bytes' => strlen($pdf),
            'peak_memory_mib' => round(memory_get_peak_usage(true) / 1048576, 1),
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    )."\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
