<?php

declare(strict_types=1);

namespace App\Dto\Stage;

use App\Entity\TechnicalSheet;
use Symfony\Component\Validator\Constraints as Assert;

final class StagePlanInput
{
    /** @var array<array-key, StageItemInput> */
    #[Assert\Valid, Assert\Count(max: 40, maxMessage: 'Le plan accepte au maximum 40 éléments.')]
    public array $items = [];
    public string $revision = '';

    public static function fromSheet(TechnicalSheet $sheet): self
    {
        $input = new self();
        $input->revision = self::fingerprint($sheet);
        foreach ($sheet->getStagePlan() as $data) {
            $item = new StageItemInput();
            foreach ($data as $key => $value) {
                $item->$key = $value;
            }
            $input->items[] = $item;
        }

        return $input;
    }

    public static function fingerprint(TechnicalSheet $sheet): string
    {
        $patch = [];
        foreach ($sheet->getLines() as $line) {
            if ($line->isActive()) {
                $patch[] = [$line->getSourceKey(), $line->getChannelNumber(), $line->getSourceName()];
            }
        }
        usort($patch, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return hash('sha256', json_encode([$sheet->getStagePlan(), $patch], JSON_THROW_ON_ERROR));
    }
}
