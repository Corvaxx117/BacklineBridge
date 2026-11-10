<?php

declare(strict_types=1);

namespace App\Tests\Functional\Concert;

use App\Tests\VenueTestCase;

final class SetlistRemovalTest extends VenueTestCase
{
    public function testSheetsAndConcertsNoLongerOfferSetlistsAndOldRoutesAreGone(): void
    {
        $concert = $this->concert();
        foreach (['/', '/concerts/'.$concert->getId()] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#setlist, link[href*="setlist"], a[href*="/setlist"]');
        }
        foreach (['/fiches/'.$this->sheetId.'/setlist', '/concerts/'.$concert->getId().'/setlist/reference'] as $uri) {
            foreach (['GET', 'POST'] as $method) {
                $this->client->request($method, $uri);
                self::assertResponseStatusCodeSame(404);
            }
        }
    }
}
