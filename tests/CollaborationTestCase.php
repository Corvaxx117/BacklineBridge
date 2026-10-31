<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Band;
use App\Entity\BandDocument;
use App\Entity\User;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use Dompdf\Dompdf;
use Symfony\Component\DomCrawler\Field\FileFormField;

abstract class CollaborationTestCase extends VenueTestCase
{
    protected string $documentsDirectory;
    private ?string $previousDirectory;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->previousDirectory = $_ENV['DOCUMENT_TEST_DIRECTORY'] ?? null;
        $this->documentsDirectory = sys_get_temp_dir().'/backlinebridge-documents-test-'.bin2hex(random_bytes(8));
        $_ENV['DOCUMENT_TEST_DIRECTORY'] = $_SERVER['DOCUMENT_TEST_DIRECTORY'] = $this->documentsDirectory;
        parent::setUp();
    }

    // @phpstan-ignore phpunit.callParent (Le finally nettoie aussi les fichiers si un test échoue.)
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach ($this->temporaryFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($this->documentsDirectory)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($this->documentsDirectory, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($this->documentsDirectory);
            }
            if (null === $this->previousDirectory) {
                unset($_ENV['DOCUMENT_TEST_DIRECTORY'], $_SERVER['DOCUMENT_TEST_DIRECTORY']);
            } else {
                $_ENV['DOCUMENT_TEST_DIRECTORY'] = $_SERVER['DOCUMENT_TEST_DIRECTORY'] = $this->previousDirectory;
            }
        }
    }

    protected function loginRole(BandRole $role, ?Band $band = null): User
    {
        $band ??= $this->entityManager()->find(Band::class, $this->bandId);
        $user = static::getContainer()->get(UserCreator::class)->create(
            bin2hex(random_bytes(4)).'@example.test',
            'Profil '.$role->value,
            'CompteTest2026!',
            $band,
            $role,
        );
        $this->client->loginUser($user);

        return $user;
    }

    protected function pdfFile(?string $contents = null): string
    {
        $path = sys_get_temp_dir().'/backlinebridge-document-'.bin2hex(random_bytes(8)).'.pdf';
        if (null === $contents) {
            $pdf = new Dompdf();
            $pdf->loadHtml('<h1>Fiche technique de test</h1><p>Document fictif.</p>');
            $pdf->render();
            $contents = $pdf->output();
        }
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }

    protected function uploadPdf(?int $concertId = null, ?string $file = null): void
    {
        $uri = '/groupes/'.$this->bandId.'/documents/ajouter/pdf';
        if (null !== $concertId) {
            $uri .= '?concert='.$concertId;
        }
        $form = $this->client->request('GET', $uri)->filter('form[name="document"]')->form();
        $fileField = $form['document[file]'];
        self::assertInstanceOf(FileFormField::class, $fileField);
        $fileField->upload($file ?? $this->pdfFile());
        $this->client->submit($form, ['document' => ['title' => 'Fiche de la salle']]);
    }

    protected function document(): BandDocument
    {
        $document = $this->entityManager()->getRepository(BandDocument::class)->findOneBy([]);
        self::assertInstanceOf(BandDocument::class, $document);

        return $document;
    }
}
