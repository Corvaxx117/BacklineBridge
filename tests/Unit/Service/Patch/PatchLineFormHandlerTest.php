<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Patch;

use App\Entity\PatchLine;
use App\Service\Patch\FormSubmissionResult;
use App\Service\Patch\PatchLineFormHandler;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;

final class PatchLineFormHandlerTest extends TestCase
{
    public function testAConcurrentChannelCollisionIsReportedOnTheForm(): void
    {
        $line = new PatchLine();
        $driverException = new class('Duplicate channel') extends \RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23505';
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($line);
        $entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException(new UniqueConstraintViolationException($driverException, null));

        $channel = $this->createMock(FormInterface::class);
        $channel
            ->expects(self::once())
            ->method('addError')
            ->with(
                self::callback(
                    static fn (
                        FormError $error,
                    ): bool => 'Ce numéro de canal vient d’être utilisé. Choisis-en un autre.' ===
                        $error->getMessage(),
                ),
            )
            ->willReturnSelf();
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->expects(self::once())->method('get')->with('channelNumber')->willReturn($channel);

        $result = new PatchLineFormHandler($entityManager)->handle($form, $line);

        self::assertSame(FormSubmissionResult::Conflict, $result);
    }
}
