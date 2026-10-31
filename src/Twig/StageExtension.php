<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Stage\StagePatchLinks;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StageExtension extends AbstractExtension
{
    public function __construct(private readonly StagePatchLinks $links)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('stage_items', $this->links->present(...))];
    }
}
