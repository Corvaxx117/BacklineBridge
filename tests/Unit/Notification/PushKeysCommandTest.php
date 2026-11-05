<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Command\CreatePushKeysCommand;
use App\Service\Notification\Push\PushConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PushKeysCommandTest extends TestCase
{
    public function testKeysStayPrivateAndAreNeverReplacedOnASecondRun(): void
    {
        $directory = sys_get_temp_dir().'/bb-push-keys-'.bin2hex(random_bytes(8));
        $file = $directory.'/vapid.json';
        try {
            $configuration = new PushConfiguration(true, $file, '', false);
            $tester = new CommandTester(new CreatePushKeysCommand($configuration));
            self::assertSame(0, $tester->execute(['--subject' => 'https://bb.example.test']));
            $contents = file_get_contents($file);
            self::assertNotFalse($contents);
            $keys = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString($keys['privateKey'], $tester->getDisplay());
            self::assertSame(0600, fileperms($file) & 0777);
            self::assertTrue($configuration->available());
            self::assertSame(0, $tester->execute(['--subject' => 'https://bb.example.test']));
            self::assertSame($contents, file_get_contents($file));
            self::assertFalse(new PushConfiguration(true, $file, '', true)->available());
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
