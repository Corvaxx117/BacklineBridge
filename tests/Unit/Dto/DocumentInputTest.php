<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\Document\DocumentInput;
use App\Entity\Band;
use App\Entity\Concert;
use App\Entity\TechnicalSheet;
use PHPUnit\Framework\TestCase;

final class DocumentInputTest extends TestCase
{
    public function testThePrefilledTitleFitsTheFieldAndKeepsTheDateForALongVenueName(): void
    {
        $band = new Band('Groupe', new TechnicalSheet());
        $concert = new Concert(
            new \DateTimeImmutable('2026-12-12'),
            str_repeat('É', 150),
            'Lyon',
            new TechnicalSheet(),
            $band,
        );
        $input = DocumentInput::forRoadmap($concert);
        self::assertSame(160, mb_strlen($input->title));
        self::assertStringEndsWith(' — 12/12/2026', $input->title);
        self::assertSame($concert, $input->concert);
    }
}
