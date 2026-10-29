<?php

declare(strict_types=1);

namespace App\Dto\Venue;

use App\Enum\Availability;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class AvailabilityAnswerInput
{
    #[Assert\NotNull]
    public ?Availability $status = Availability::Unknown;
    #[Assert\Length(max: 600)]
    public ?string $details = null;

    #[Assert\Callback]
    public function alternativeNeedsDetails(ExecutionContextInterface $context): void
    {
        if ($this->status === Availability::Alternative && trim($this->details ?? '') === '') {
            $context
                ->buildViolation('Précisez le matériel proposé en remplacement.')
                ->atPath('details')
                ->addViolation();
        }
    }
}
