<?php

declare(strict_types=1);

namespace App\Service\Technical;

/** CSS issu uniquement de la palette interne ; aucune valeur ne vient d’un formulaire. */
final readonly class TechnicalPaletteStyles
{
    public function __construct(private TechnicalPalette $palette)
    {
    }

    public function application(): string
    {
        $rules = [];
        foreach ($this->palette->families() as $key => $color) {
            $rules[] = sprintf(<<<'CSS'
                [data-technical-family="%1$s"] {
                    --family-bg: %2$s;
                    --family-ink: %3$s;
                    --family-border: %4$s;
                }
                :root[data-theme='dark'] [data-technical-family="%1$s"] {
                    --family-bg: %5$s;
                    --family-ink: %6$s;
                    --family-border: %7$s;
                }
                CSS, $key, $color['background'], $color['ink'], $color['border'],
                $color['darkBackground'], $color['darkInk'], $color['darkBorder']);
        }

        return implode("\n", $rules);
    }

    public function pdf(): string
    {
        $rules = [];
        foreach ($this->palette->families() as $key => $color) {
            $rules[] = sprintf(<<<'CSS'
                .input-family-%1$s > td {
                    background: %2$s;
                }
                .input-family-%1$s > td:first-child {
                    border-left: 3pt solid %4$s;
                }
                .input-family-%1$s.first-in-family > td {
                    border-top: 1pt solid %4$s;
                }
                .input-family-%1$s .pdf-family-name {
                    color: %3$s;
                }
                .equipment-family-%1$s > td:first-child {
                    color: %3$s;
                    background: %2$s;
                    border-left: 3pt solid %4$s;
                }
                .technical-legend li[data-technical-family="%1$s"] {
                    color: %3$s;
                    background: %2$s;
                    border: 0.5pt solid %4$s;
                }
                CSS, $key, $color['background'], $color['ink'], $color['border']);
        }

        return implode("\n", $rules);
    }
}
