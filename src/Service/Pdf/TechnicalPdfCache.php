<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Pdf\TechnicalDossier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TechnicalPdfCache
{
    public function __construct(
        private TechnicalPdfRenderer $renderer,
        private PdfFileCache $files,
        #[Autowire('%kernel.project_dir%/public/asset-manifest.json')] private string $manifest,
        #[Autowire('%kernel.debug%')] private bool $debug,
    ) {
    }

    public function render(TechnicalDossier $dossier): string
    {
        if ($this->debug) {
            return $this->renderer->render($dossier);
        }
        $manifest = json_decode((string) file_get_contents($this->manifest), true, flags: JSON_THROW_ON_ERROR);
        $key = hash('sha256', json_encode([$manifest['__pdf'], $dossier], JSON_THROW_ON_ERROR));

        return $this->files->get($key, fn (): string => $this->renderer->render($dossier));
    }
}
