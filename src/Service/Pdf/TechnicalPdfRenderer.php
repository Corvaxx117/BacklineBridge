<?php

declare(strict_types=1);

namespace App\Service\Pdf;

use App\Dto\Pdf\TechnicalDossier;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class TechnicalPdfRenderer
{
    public function __construct(
        private Environment $twig,
        private StagePlanLayout $stagePlan,
        #[Autowire('%kernel.cache_dir%/pdf')] private string $cacheDirectory,
        #[Autowire('%kernel.project_dir%/public')] private string $publicDirectory,
    ) {
    }

    public function render(TechnicalDossier $dossier): string
    {
        foreach (['fonts', 'tmp'] as $directory) {
            $path = $this->cacheDirectory.'/'.$directory;
            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException(
                    'Impossible de créer le répertoire temporaire des PDF.',
                );
            }
        }
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setAllowedProtocols(['data://']);
        $options->setChroot($this->publicDirectory);
        $options->setFontCache($this->cacheDirectory.'/fonts');
        $options->setTempDir($this->cacheDirectory.'/tmp');
        $layout = $this->stagePlan->prepare($dossier->stagePlan);
        $svg = $this->twig->render('pdf/stage.svg.twig', $layout);
        $document = new \DOMDocument();
        $document->loadXML($svg, LIBXML_NONET);
        // La bibliothèque SVG mesure les espaces d'indentation avant de dessiner le texte.
        foreach ($document->getElementsByTagName('text') as $text) {
            $text->textContent = trim($text->textContent);
        }
        $svg = $document->saveXML();
        if (false === $svg) {
            throw new \RuntimeException('Impossible de préparer le dessin du plan de scène.');
        }
        $scale = min(740 / $layout['width'], 340 / $layout['height']);
        $html = $this->twig->render('pdf/technical-sheet.html.twig', [
            'dossier' => $dossier,
            'stageImage' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            'stageWidth' => $layout['width'] * $scale,
            'stageHeight' => $layout['height'] * $scale,
        ]);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font =
            $pdf->getFontMetrics()->getFont('DejaVu Sans') ??
            throw new \RuntimeException('La police du PDF est introuvable.');
        $color = [0.4, 0.46, 0.45];
        $canvas->page_text(
            28,
            $canvas->get_height() - 23,
            $this->twig->getGlobals()['app_name'].' - Fiche technique',
            $font,
            7,
            $color,
        );
        $canvas->page_text(
            $canvas->get_width() - 100,
            $canvas->get_height() - 23,
            '{PAGE_NUM} / {PAGE_COUNT}',
            $font,
            7,
            $color,
        );

        return $pdf->output();
    }
}
