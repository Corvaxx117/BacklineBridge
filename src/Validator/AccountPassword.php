<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class AccountPassword extends Compound
{
    public const int MIN_LENGTH = 10;
    public const int MAX_LENGTH = 128;
    public const string HELP = '10 caractères minimum, avec au moins une majuscule, un chiffre et un symbole (par exemple !, ? ou @).';

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\Sequentially([
                new Assert\NotBlank(message: 'Choisis et confirme ton mot de passe.'),
                new Assert\Length(
                    min: self::MIN_LENGTH,
                    max: self::MAX_LENGTH,
                    minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
                ),
                new Assert\Regex(
                    pattern: '/\p{Lu}/u',
                    message: 'Ajoute au moins une lettre majuscule.',
                ),
                new Assert\Regex(pattern: '/[0-9]/', message: 'Ajoute au moins un chiffre.'),
                new Assert\Regex(
                    pattern: '/[\p{P}\p{S}]/u',
                    message: 'Ajoute au moins un symbole, par exemple !, ? ou @.',
                ),
            ]),
        ];
    }
}
