<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrfeoRateLimiterTest extends KernelTestCase
{
    public function testTheConfiguredLimiterCanBeCreatedAndLeavesRoomUnderOrfeosQuota(): void
    {
        self::bootKernel();
        $factory = static::getContainer()->get('limiter.orfeo');
        $limit = $factory->create('synthetic-rate-check-'.bin2hex(random_bytes(8)))->consume();
        self::assertTrue($limit->isAccepted());
        self::assertLessThan(10, $limit->getLimit());
    }
}
