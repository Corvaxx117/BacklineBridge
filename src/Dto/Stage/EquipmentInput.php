<?php

declare(strict_types=1);

namespace App\Dto\Stage;

use App\Entity\TechnicalSheet;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class EquipmentInput
{
    /** @var array<array-key, EquipmentItemInput> */
    #[Assert\Valid,
        Assert\Count(max: 60, maxMessage: 'La liste accepte au maximum 60 équipements.'),]
    public array $items = [];
    public string $revision = '';

    public static function fromSheet(TechnicalSheet $sheet): self
    {
        $input = new self();
        $input->revision = self::fingerprint($sheet);
        foreach ($sheet->getEquipmentRequests() as $data) {
            $item = new EquipmentItemInput();
            foreach ($data as $key => $value) {
                $item->$key = $value;
            }
            $input->items[] = $item;
        }

        return $input;
    }

    public static function fingerprint(TechnicalSheet $sheet): string
    {
        return hash('sha256', json_encode($sheet->getEquipmentRequests(), JSON_THROW_ON_ERROR));
    }

    #[Assert\Callback]
    public function uniqueIds(ExecutionContextInterface $context): void
    {
        $ids = array_filter(
            array_map(static fn (EquipmentItemInput $item): ?string => $item->id, $this->items),
        );
        if (count($ids) !== count(array_unique($ids))) {
            $context
                ->buildViolation('Un équipement apparaît plusieurs fois. Rechargez le formulaire.')
                ->atPath('items')
                ->addViolation();
        }
    }
}
