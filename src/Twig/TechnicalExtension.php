<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Technical\TechnicalPalette;
use App\Service\Technical\TechnicalPaletteStyles;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TechnicalExtension extends AbstractExtension
{
    public function __construct(private readonly TechnicalPalette $palette, private readonly TechnicalPaletteStyles $styles)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('technical_palette_styles', $this->styles->application(...), ['is_safe' => ['html']]),
            new TwigFunction('technical_pdf_styles', $this->styles->pdf(...), ['is_safe' => ['html']]),
            new TwigFunction('technical_palette', $this->palette->families(...)),
            new TwigFunction('technical_family', $this->palette->normalize(...)),
            new TwigFunction('technical_stage_rules', $this->palette->stageRules(...)),
            new TwigFunction('technical_stage_family', $this->palette->stageFamily(...)),
            new TwigFunction('technical_equipment_family', $this->palette->equipmentFamily(...)),
        ];
    }
}
