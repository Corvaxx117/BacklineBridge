<?php

declare(strict_types=1);

namespace App\Dto\Stage;

use Symfony\Component\Validator\Constraints as Assert;

final class StageItemInput
{
    public const KINDS = [
        'Batterie' => 'drums',
        'Praticable' => 'riser',
        'Ampli / cab' => 'amp',
        'Musicien / chant' => 'person',
        'Claviers' => 'keys',
        'Retour' => 'wedge',
        'Alimentation' => 'power',
        'Autre' => 'other',
    ];

    #[Assert\NotBlank, Assert\Length(max: 70)]
    public string $label = '';
    #[
        Assert\NotNull,
        Assert\Choice(
            choices: ['drums', 'riser', 'amp', 'person', 'keys', 'wedge', 'power', 'other'],
        ),
    ]
    public ?string $kind = 'other';
    #[Assert\NotNull, Assert\Range(min: 0, max: 100)]
    public ?float $x = 50;
    #[Assert\NotNull, Assert\Range(min: 0, max: 100)]
    public ?float $y = 50;
    #[Assert\NotNull, Assert\Range(min: 0, max: 360)]
    public ?float $rotation = 0;
    #[Assert\NotNull, Assert\Range(min: 0.5, max: 2.5)]
    public ?float $scale = 1;
    #[Assert\Length(max: 250)]
    public string $notes = '';
}
