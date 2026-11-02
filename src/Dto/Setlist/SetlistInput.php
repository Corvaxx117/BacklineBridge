<?php

declare(strict_types=1);

namespace App\Dto\Setlist;

use App\ValueObject\SetlistContent;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class SetlistInput
{
    #[Assert\Length(max: SetlistContent::MAX_TEXT_LENGTH, maxMessage: 'La setlist est trop longue.')]
    public string $songs = '';

    #[Assert\NotBlank(message: 'Recharge la page avant d’enregistrer.')]
    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D', message: 'Recharge la page avant d’enregistrer.')]
    public ?string $revision = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (mb_strlen($this->songs) > SetlistContent::MAX_TEXT_LENGTH) {
            return;
        }
        try {
            SetlistContent::fromText($this->songs);
        } catch (\InvalidArgumentException $exception) {
            $context->buildViolation($exception->getMessage())->atPath('songs')->addViolation();
        }
    }
}
