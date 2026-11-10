<?php

declare(strict_types=1);

namespace App\Dto\Technical;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class TechnicalContactInput
{
    public string $name = '';
    public string $role = '';
    public string $email = '';
    public string $phone = '';
    public bool $remove = false;

    public function isEmpty(): bool
    {
        return '' === trim($this->name.$this->role.$this->email.$this->phone);
    }

    /** @return array{name: string, role: string, email: string, phone: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'role' => $this->role, 'email' => $this->email, 'phone' => $this->phone];
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->remove || $this->isEmpty()) {
            return;
        }
        try {
            TechnicalIdentity::fromArray(['contacts' => [$this->toArray()]]);
        } catch (\DomainException $exception) {
            $context->buildViolation($exception->getMessage())->addViolation();
        }
    }
}
