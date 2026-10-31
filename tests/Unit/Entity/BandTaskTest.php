<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Band;
use App\Entity\BandTask;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\TaskStatus;
use PHPUnit\Framework\TestCase;

final class BandTaskTest extends TestCase
{
    public function testDueTodayIsNotLateAndFinishedTasksAreNotLate(): void
    {
        $task = new BandTask(new Band('Test', new TechnicalSheet()), new User('test@example.test', 'Test'));
        $today = new \DateTimeImmutable('2026-10-31 00:00:00', new \DateTimeZone('Europe/Paris'));
        $task->update('Mission', null, null, new \DateTimeImmutable('2026-10-31'), TaskStatus::Todo);
        self::assertFalse($task->isOverdue($today));
        $task->update('Mission', null, null, new \DateTimeImmutable('2026-10-30'), TaskStatus::Doing);
        self::assertTrue($task->isOverdue($today));
        $task->changeStatus(TaskStatus::Done);
        self::assertFalse($task->isOverdue($today));
        $completion = $task->getCompletedAt();
        self::assertNotNull($completion);
        $task->update('Titre corrigé', null, null, null, TaskStatus::Done);
        self::assertSame($completion, $task->getCompletedAt());
        $task->changeStatus(TaskStatus::Todo);
        self::assertNull($task->getCompletedAt());
        self::assertFalse($task->isOverdue($today));
    }
}
