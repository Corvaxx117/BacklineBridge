<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\ReadRequestCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ReadRequestCacheTest extends TestCase
{
    public function testReadValuesExpireWithTheRequestAndWritesAlwaysReload(): void
    {
        $stack = new RequestStack();
        $cache = new ReadRequestCache($stack);
        $calls = 0;
        $load = static function () use (&$calls): int { return ++$calls; };
        $stack->push(Request::create('/concerts'));
        self::assertSame(1, $cache->remember('role.1.1', $load));
        self::assertSame(1, $cache->remember('role.1.1', $load));
        self::assertSame(2, $cache->remember('role.2.1', $load));
        $stack->pop();
        $stack->push(Request::create('/concerts'));
        self::assertSame(3, $cache->remember('role.1.1', $load));
        $stack->pop();
        $stack->push(Request::create('/concerts', 'POST'));
        self::assertSame(4, $cache->remember('role.1.1', $load));
        self::assertSame(5, $cache->remember('role.1.1', $load));
        $stack->pop();
        self::assertSame(6, $cache->remember('role.1.1', $load));
    }
}
