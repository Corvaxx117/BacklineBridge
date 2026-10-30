<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\AccountPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class AccountPasswordTest extends TestCase
{
    #[DataProvider('passwords')]
    public function testPasswordRequirements(string $password, bool $accepted): void
    {
        $violations = Validation::createValidator()->validate($password, new AccountPassword());

        self::assertSame($accepted, 0 === count($violations));
    }

    public static function passwords(): iterable
    {
        yield 'ten characters' => ['Corail!94x', true];
        yield 'longer password' => ['Une longue phrase Corail!94 lune?', true];
        yield 'uppercase without lowercase' => ['CORAIL!94X', true];
        yield 'accented uppercase' => ['étoile!9Éx', true];
        yield 'currency symbol' => ['Corail€94x', true];
        yield 'underscore' => ['Corail_94x', true];
        yield 'maximum length' => ['A1!'.str_repeat('x', 125), true];
        yield 'empty' => ['', false];
        yield 'nine characters' => ['Corai!94x', false];
        yield 'too long' => ['A1!'.str_repeat('x', 126), false];
        yield 'without uppercase' => ['corail!94x', false];
        yield 'without digit' => ['Corail!xxx', false];
        yield 'without symbol' => ['Corail194xx', false];
        yield 'space is not a symbol' => ['Corail194 x', false];
        yield 'newline is not a symbol' => ["Corail194\nx", false];
    }
}
