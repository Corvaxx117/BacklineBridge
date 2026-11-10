<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\Technical\TechnicalIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TechnicalIdentityTest extends TestCase
{
    public function testOnlyTheExplicitlyShareableFieldsCanBeStored(): void
    {
        $data = ['style' => 'Indie / Rock', 'description' => 'Dynamic live show', 'contacts' => [
            ['name' => 'Régie fictive', 'role' => 'FOH', 'email' => 'sound@example.test', 'phone' => '01 00 00 00 00'],
        ]];
        self::assertSame($data, TechnicalIdentity::fromArray($data)->toArray());
        self::assertTrue(TechnicalIdentity::fromArray($data)->hasContent());
        self::assertFalse(TechnicalIdentity::fromArray([])->hasContent());
    }

    #[DataProvider('invalid')]
    public function testPrivateOrMalformedFieldsAreRejected(array $data): void
    {
        $this->expectException(\DomainException::class);
        TechnicalIdentity::fromArray($data);
    }

    public static function invalid(): iterable
    {
        yield 'administrative fields' => [['socialSecurityNumber' => 'DO_NOT_PUBLISH']];
        yield 'nested administrative fields' => [['contacts' => [['name' => 'Test', 'role' => 'FOH', 'birthday' => '2000-01-01']]]];
        yield 'bad email' => [['contacts' => [['name' => 'Test', 'role' => 'FOH', 'email' => 'invalid']]]];
        yield 'phone header injection' => [['contacts' => [['name' => 'Test', 'role' => 'FOH', 'phone' => "0100000000\r\nX: value"]]]];
        yield 'overlong style' => [['style' => str_repeat('a', 151)]];
    }
}
