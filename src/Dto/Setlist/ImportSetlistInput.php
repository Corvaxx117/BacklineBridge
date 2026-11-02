<?php

declare(strict_types=1);

namespace App\Dto\Setlist;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportSetlistInput
{
    #[Assert\NotBlank(message: 'Recharge l’aperçu avant de confirmer.')]
    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D', message: 'Recharge l’aperçu avant de confirmer.')]
    public ?string $revision = null;

    #[Assert\NotBlank(message: 'Recharge l’aperçu avant de confirmer.')]
    #[Assert\Regex(pattern: '/^[a-f0-9]{64}$/D', message: 'Recharge l’aperçu avant de confirmer.')]
    public ?string $referenceRevision = null;

    public bool $confirmReplacement = false;
}
