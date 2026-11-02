<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\ValueObject\SetlistContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetlistContentTest extends TestCase
{
    public function testPastedLinesKeepTheirOrderAccentsAndDuplicates(): void
    {
        $content = SetlistContent::fromText("  Première chanson  \r\n\n Deuxième chanson \rRappel\nPremière chanson\n");
        self::assertSame(['Première chanson', 'Deuxième chanson', 'Rappel', 'Première chanson'], $content->tracks());
        self::assertSame([], SetlistContent::fromText("\n  \r\n")->tracks());
        self::assertSame(['0'], SetlistContent::fromText('0')->tracks());
    }

    #[DataProvider('invalidContent')]
    public function testInvalidContentIsRejected(string $text): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SetlistContent::fromText($text);
    }

    public static function invalidContent(): iterable
    {
        yield 'titre trop long en caractères' => [str_repeat('é', 161)];
        yield 'trop de titres' => [implode("\n", array_fill(0, 101, 'Morceau'))];
        yield 'texte trop long' => [str_repeat('a', SetlistContent::MAX_TEXT_LENGTH + 1)];
        yield 'encodage invalide' => ["Morceau\xff"];
    }

    public function testTheLargestAllowedListIsAccepted(): void
    {
        $tracks = array_fill(0, 100, str_repeat('é', 160));
        self::assertSame($tracks, SetlistContent::fromTracks($tracks)->tracks());
    }
}
