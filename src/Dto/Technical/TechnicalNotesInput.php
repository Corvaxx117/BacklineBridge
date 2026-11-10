<?php

declare(strict_types=1);

namespace App\Dto\Technical;

use App\Entity\TechnicalSheet;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TechnicalNotesInput
{
    #[Assert\Length(max: 150), Assert\Regex(pattern: '/\\x00/', match: false)]
    public string $style = '';
    #[Assert\Length(max: 300), Assert\Regex(pattern: '/\\x00/', match: false)]
    public string $description = '';
    #[Assert\Length(max: 12000), Assert\Regex(pattern: '/\\x00/', match: false)]
    public string $notices = '';
    #[Assert\NotBlank, Assert\Regex(pattern: '/^[a-f0-9]{64}$/D')]
    public string $revision = '';

    public static function fromSheet(TechnicalSheet $sheet): self
    {
        $input = new self();
        $identity = $sheet->getBand()?->getTechnicalIdentity();
        $input->style = $identity->style ?? '';
        $input->description = $identity->description ?? '';
        $input->notices = implode("\n\n", $sheet->getNotices());
        $input->revision = self::fingerprint($sheet);

        return $input;
    }

    public static function fingerprint(TechnicalSheet $sheet): string
    {
        $identity = $sheet->getBand()?->getTechnicalIdentity();

        return hash('sha256', json_encode([$sheet->getNotices(), $identity?->style, $identity?->description], JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function values(): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $this->notices));
        if ('' === $text) {
            return [];
        }

        $parts = preg_split('/\n[ \t]*\n+/u', $text) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn (string $note): bool => '' !== $note,
        ));
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $notes = $this->values();
        if (count($notes) > 30 || array_any($notes, static fn (string $note): bool => mb_strlen($note) > 2000)) {
            $context->buildViolation('Prévois au maximum 30 notes de 2 000 caractères chacune.')->atPath('notices')->addViolation();
        }
    }
}
