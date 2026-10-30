<?php

declare(strict_types=1);

namespace App\Tests\Functional\Patch;

use App\Entity\PatchLine;
use App\Tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class EditActionTest extends DatabaseTestCase
{
    public function testEditingASourcePersistsAfterAnotherRequest(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $this->submitLineForm('/sources/'.$id.'/edit', [
            'sourceName' => 'JU VOICE LEAD',
            'preferredMicrophone' => 'SM58',
        ]);

        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'JU VOICE LEAD');
        $this->assertLineCount(22);

        $saved = $this->lineForChannel(17);
        self::assertSame($id, $saved->getId());
        self::assertSame('JU VOICE LEAD', $saved->getSourceName());
        self::assertSame('SM58', $saved->getPreferredMicrophone());
    }

    public function testAnEditWithoutACsrfTokenDoesNotMutateTheSource(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $this->submitLineForm(
            '/sources/'.$id.'/edit',
            [
                'channelNumber' => '30',
                'sourceName' => 'MODIFICATION SANS JETON',
            ],
            withCsrf: false,
        );

        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 422]);
        $this->assertLineCount(22);
        $saved = $this->entityManager()->find(PatchLine::class, $id);
        self::assertInstanceOf(PatchLine::class, $saved);
        self::assertSame(17, $saved->getChannelNumber());
        self::assertSame('JU VOICE', $saved->getSourceName());
    }

    public function testChangingAChannelPreservesTheSourceIdentityAndReordersThePatch(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $this->submitLineForm('/sources/'.$id.'/edit', ['channelNumber' => '30']);

        self::assertResponseRedirects('/', 303);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->assertLineCount(22);
        $saved = $this->lineForChannel(30);
        self::assertSame($id, $saved->getId());
        self::assertSame('JU VOICE', $saved->getSourceName());
        self::assertNull(
            $this->entityManager()
                ->getRepository(PatchLine::class)
                ->findOneBy([
                    'sheet' => $this->sheetId,
                    'channelNumber' => 17,
                ]),
        );
        $lastRow = $crawler->filter('table tbody tr')->last();
        self::assertSame(30, (int) trim($lastRow->filter('td, th')->first()->text()));
        self::assertStringContainsString('JU VOICE', $lastRow->text());
        self::assertCount(1, $lastRow->filter('a[href="/sources/'.$id.'/edit"]'));
    }

    public function testEditingToAnOccupiedChannelDoesNotMutateEitherSource(): void
    {
        $id = $this->lineForChannel(17)->getId();
        $other = $this->lineForChannel(18);
        $otherId = $other->getId();
        $otherName = $other->getSourceName();

        $this->submitLineForm('/sources/'.$id.'/edit', [
            'channelNumber' => '18',
            'sourceName' => 'MODIFICATION REFUSÉE',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="patch_line"]', 'déjà utilisé');
        $this->assertLineCount(22);
        $saved = $this->lineForChannel(17);
        self::assertSame($id, $saved->getId());
        self::assertSame('JU VOICE', $saved->getSourceName());
        $unchanged = $this->lineForChannel(18);
        self::assertSame($otherId, $unchanged->getId());
        self::assertSame($otherName, $unchanged->getSourceName());
    }

    #[DataProvider('unknownSourceRequestMethods')]
    public function testAnUnknownSourceReturnsNotFound(string $method): void
    {
        $this->client->catchExceptions(true);
        $this->client->request($method, '/sources/999999/edit');

        self::assertResponseStatusCodeSame(404);
        $this->assertLineCount(22);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownSourceRequestMethods(): iterable
    {
        yield 'display form' => ['GET'];
        yield 'submit form' => ['POST'];
    }
}
