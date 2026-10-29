<?php

declare(strict_types=1);

namespace App\Tests\Functional\Patch;

use App\Entity\PatchLine;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateActionTest extends DatabaseTestCase
{
    public function testAnAdditionalSourceCanLeaveEquipmentAndNotesEmpty(): void
    {
        $this->submitLineForm('/sources/new', $this->newLineValues());

        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->assertLineCount(23);
        $created = $this->lineForChannel(23);
        self::assertSame('VOIX INVITÉE', $created->getSourceName());
        self::assertNull($created->getPreferredMicrophone());
        self::assertNull($created->getStand());
        self::assertNull($created->getNotes());
    }

    public function testADuplicateChannelIsReportedWithoutAddingASource(): void
    {
        $this->submitLineForm(
            '/sources/new',
            array_replace($this->newLineValues(), [
                'channelNumber' => '17',
            ]),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="patch_line"]', 'déjà utilisé');
        $this->assertLineCount(22);
        self::assertSame('JU VOICE', $this->lineForChannel(17)->getSourceName());
    }

    #[DataProvider('invalidChannels')]
    public function testANonPositiveOrInvalidChannelIsRejected(string $channel): void
    {
        $this->submitLineForm(
            '/sources/new',
            array_replace($this->newLineValues(), [
                'channelNumber' => $channel,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
        $this->assertLineCount(22);
        self::assertNull(
            $this->entityManager()
                ->getRepository(PatchLine::class)
                ->findOneBy([
                    'sourceName' => 'VOIX INVITÉE',
                ]),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidChannels(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-3'];
        yield 'text' => ['vingt-trois'];
        yield 'fractional' => ['23.5'];
    }
}
