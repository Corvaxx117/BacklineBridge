<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CreateUserCommand;
use App\Entity\Band;
use App\Entity\BandMembership;
use App\Entity\User;
use App\Enum\BandRole;
use App\Service\Security\UserCreator;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class UserCreationTest extends DatabaseTestCase
{
    public function testInteractiveCommandCreatesAHashedAccountAndItsScopedMembership(): void
    {
        $tester = new CommandTester(static::getContainer()->get(CreateUserCommand::class));
        $tester->setInputs(['NEW@EXAMPLE.TEST', 'Nouvelle personne', 'Corail!94x', 'Corail!94x']);
        $tester->execute(
            ['--band' => (string) $this->bandId, '--role' => 'editor'],
            ['interactive' => true],
        );
        $tester->assertCommandIsSuccessful();
        self::assertStringNotContainsString('Corail!94x', $tester->getDisplay());
        $em = $this->entityManager();
        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'new@example.test']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame('Corail!94x', $user->getPassword());
        self::assertStringNotContainsString($user->getPassword(), serialize($user));
        self::assertTrue(
            static::getContainer()
                ->get(UserPasswordHasherInterface::class)
                ->isPasswordValid($user, 'Corail!94x'),
        );
        self::assertSame(
            BandRole::Editor,
            $em
                ->getRepository(BandMembership::class)
                ->findOneBy(['user' => $user])
                ?->getRole(),
        );
    }

    public function testCommandRejectsUnconfirmedPasswordWithoutCreatingAnAccount(): void
    {
        $tester = new CommandTester(static::getContainer()->get(CreateUserCommand::class));
        $tester->setInputs([
            'new@example.test',
            'Nouvelle personne',
            'Corail!94x',
            'Different confirmation!',
        ]);
        $tester->execute(['--role' => 'manager'], ['interactive' => true]);
        self::assertSame(2, $tester->getStatusCode());
        self::assertSame(1, $this->entityManager()->getRepository(User::class)->count([]));
        self::assertSame(
            1,
            $this->entityManager()->getRepository(BandMembership::class)->count([]),
        );
    }

    public function testDuplicateNormalizedEmailAndShortPasswordAreRejectedWithoutNewMemberships(): void
    {
        $creator = static::getContainer()->get(UserCreator::class);
        $band = $this->entityManager()->find(Band::class, $this->bandId);
        foreach (
            [['OWNER@EXAMPLE.TEST', 'Another password 2026!'], ['new@example.test', 'short']] as [$email, $password]
        ) {
            try {
                $creator->create($email, 'Test', $password, $band, BandRole::Manager);
                self::fail('La création aurait dû être refusée.');
            } catch (ValidationFailedException) {
                self::assertSame(1, $this->entityManager()->getRepository(User::class)->count([]));
                self::assertSame(
                    1,
                    $this->entityManager()->getRepository(BandMembership::class)->count([]),
                );
            }
        }
    }
}
