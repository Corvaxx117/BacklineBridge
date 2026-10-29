<?php

declare(strict_types=1);

namespace App\Dto\Merch;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductInput
{
    public const CATEGORIES = ['CD', 'Vinyle', 'T-shirt', 'Cassette', 'Autre'];
    #[Assert\NotBlank, Assert\Length(max: 160)]
    public string $name = '';
    #[Assert\Choice(choices: self::CATEGORIES)]
    public string $category = 'CD';
    #[Assert\Length(max: 250)]
    public string $variants = '';
}
