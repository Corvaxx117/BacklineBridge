<?php

declare(strict_types=1);

namespace App\Dto\Finance;

use App\Enum\TreasuryAccountKind;
use Symfony\Component\Validator\Constraints as Assert;

final class TransferInput extends TreasuryEntryInput
{
    #[Assert\NotNull]
    public ?TreasuryAccountKind $source = TreasuryAccountKind::Cash;
    #[Assert\NotNull]
    public ?TreasuryAccountKind $destination = TreasuryAccountKind::Bank;

    public function __construct()
    {
        parent::__construct();
        $this->label = 'Transfert entre les comptes';
    }
}
