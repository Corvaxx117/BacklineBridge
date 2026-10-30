<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Band;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected int $sheetId;
    protected int $userId;
    protected int $bandId;
    protected bool $authenticate = true;
    private ?string $databasePath = null;

    /** @var array<string, mixed> */
    protected array $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();

        // A file survives KernelBrowser's kernel reboot between HTTP requests.
        $this->databasePath =
            sys_get_temp_dir().
            '/backlinebridge-phpunit-'.
            getmypid().
            '-'.
            bin2hex(random_bytes(8)).
            '.db';
        $url = 'sqlite:///'.$this->databasePath;
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->catchExceptions(false);

        $em = $this->entityManager();
        $parameters = $em->getConnection()->getParams();
        self::assertSame('test', self::$kernel->getEnvironment());
        self::assertSame('pdo_sqlite', $parameters['driver'] ?? null);
        self::assertStringContainsString('backlinebridge-phpunit-', $parameters['path'] ?? '');

        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->createSchema($metadata);

        $contents = file_get_contents(dirname(__DIR__).'/data/avee-mana-patch.json');
        self::assertNotFalse($contents);
        $this->fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $sheet = new TechnicalSheet()
            ->setBandName($this->fixture['bandName'])
            ->setDocumentLabel($this->fixture['documentLabel'])
            ->setNotices($this->fixture['notices']);

        // Seed out of order so the display test exercises ordering, not insertion order.
        foreach (array_reverse($this->fixture['lines']) as $data) {
            $sheet->addLine(
                new PatchLine()
                    ->setChannelNumber($data['channelNumber'])
                    ->setSourceName($data['sourceName'])
                    ->setPreferredMicrophone($data['preferredMicrophone'])
                    ->setStand($data['stand'])
                    ->setNotes($data['notes'])
                    ->setSection($data['section']),
            );
        }

        $band = new Band($sheet->getBandName(), $sheet);
        $em->persist($band);
        $em->flush();
        self::assertNotNull($sheet->getId());
        $this->sheetId = $sheet->getId();
        $this->bandId = $band->getId();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'owner@example.test',
                'Responsable de test',
                'BacklineBridge test password 2026!',
                $band,
                BandRole::Manager,
            );
        $this->userId = $user->getId();
        if ($this->authenticate) {
            $this->client->loginUser($user);
        }
        $em->clear();
    }

    // @phpstan-ignore phpunit.callParent (L’appel parent est dans try pour garantir le nettoyage dans finally.)
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if (null !== $this->databasePath && is_file($this->databasePath)) {
                unlink($this->databasePath);
            }
        }
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function sheet(): TechnicalSheet
    {
        $sheet = $this->entityManager()->find(TechnicalSheet::class, $this->sheetId);
        self::assertInstanceOf(TechnicalSheet::class, $sheet);

        return $sheet;
    }

    protected function lineForChannel(int $channel): PatchLine
    {
        $line = $this->entityManager()
            ->getRepository(PatchLine::class)
            ->findOneBy([
                'sheet' => $this->sheetId,
                'channelNumber' => $channel,
            ]);
        self::assertInstanceOf(PatchLine::class, $line);

        return $line;
    }

    protected function assertLineCount(int $expected): void
    {
        // Clear managed objects so assertions prove persistence in SQLite.
        $em = $this->entityManager();
        $em->clear();
        self::assertSame($expected, $em->getRepository(PatchLine::class)->count([]));
    }

    /** @param array<string, string> $changes */
    protected function submitLineForm(string $uri, array $changes = [], bool $withCsrf = true): void
    {
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="patch_line"]')->form();
        $values = $form->getPhpValues();
        $values['patch_line'] = array_replace($values['patch_line'], $changes);

        if (!$withCsrf) {
            unset($values['patch_line']['_token']);
        }

        // Submit a real HTTP payload, including values that HTML5 validation would block.
        $this->client->request('POST', $form->getUri(), $values);
    }

    /** @return array<string, string> */
    protected function newLineValues(): array
    {
        return [
            'channelNumber' => '23',
            'sourceName' => 'VOIX INVITÉE',
            'section' => 'vocals',
            'preferredMicrophone' => '',
            'stand' => '',
            'notes' => '',
        ];
    }
}
