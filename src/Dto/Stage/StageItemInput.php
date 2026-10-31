<?php

declare(strict_types=1);

namespace App\Dto\Stage;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class StageItemInput
{
    public const SOURCE_LIMITS = [
        'mic_vocal' => 1,
        'mic_instrument' => 1,
        'di_mono' => 1,
        'di_stereo' => 2,
    ];
    public const KINDS = [
        'Batterie' => 'drums',
        'Praticable' => 'riser',
        'Ampli / cab' => 'amp',
        'Musicien / chant' => 'person',
        'Claviers' => 'keys',
        'Retour' => 'wedge',
        'Alimentation' => 'power',
        'Micro chant' => 'mic_vocal',
        'Micro instrument' => 'mic_instrument',
        'Pied droit' => 'stand_straight',
        'Pied perche' => 'stand_boom',
        'DI mono' => 'di_mono',
        'DI stéréo' => 'di_stereo',
        'Autre' => 'other',
    ];

    #[Assert\NotBlank, Assert\Length(max: 70)]
    public string $label = '';
    #[Assert\NotNull,
        Assert\Choice(callback: [self::class, 'kindValues']),]
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

    /** @var list<string> */
    #[Assert\Count(max: 12, maxMessage: 'Relie au maximum 12 entrées à un élément.')]
    #[Assert\All([new Assert\Regex(pattern: '/^[a-f0-9]{32}$/D')])]
    public array $patchSources = [];

    #[Assert\Callback]
    public function validatePatchSources(ExecutionContextInterface $context): void
    {
        $limit = self::SOURCE_LIMITS[$this->kind ?? 'other'] ?? null;
        if (null !== $limit && count($this->patchSources) > $limit) {
            $context->buildViolation(1 === $limit
                ? 'Associe une seule entrée à cet élément.'
                : 'Associe au maximum deux entrées à cette DI stéréo.')
                ->atPath('patchSources')
                ->addViolation();
        }
    }

    /** @return list<string> */
    public static function kindValues(): array
    {
        return array_values(self::KINDS);
    }
}
