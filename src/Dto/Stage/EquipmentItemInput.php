<?php

declare(strict_types=1);

namespace App\Dto\Stage;

use Symfony\Component\Validator\Constraints as Assert;

final class EquipmentItemInput
{
    #[Assert\Regex(pattern: '/^[a-z0-9_]{1,40}$/D')]
    public ?string $id = null;
    #[Assert\NotBlank, Assert\Length(max: 60)]
    public string $category = 'Autre';
    #[Assert\NotBlank, Assert\Length(max: 100)]
    public string $label = '';
    #[Assert\NotNull, Assert\Range(min: 1, max: 100)]
    public ?int $quantity = 1;
    #[Assert\Length(max: 500)]
    public string $specification = '';
}
