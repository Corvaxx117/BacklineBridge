<?php

declare(strict_types=1);
namespace App\Tests\Integration;
use App\Command\ImportPrivateBandCommand;
use App\Entity\{User, MemberProfile, TreasuryAccount, MerchProduct};
use App\Tests\DatabaseTestCase;
use Symfony\Component\Console\Tester\CommandTester;
final class PrivateBandImportTest extends DatabaseTestCase
{
    public function testImportIsIdempotentAndPreservesExistingPasswordAndOpeningBalances(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'backlinebridge-private-import-');
        $filesBefore = glob(dirname(__DIR__, 2) . '/var/private/activation-codes-*.json') ?: [];
        $password = $this->entityManager()->find(User::class, $this->userId)->getPassword();
        $data = [
            'bandName' => $this->fixture['bandName'],
            'members' => [
                [
                    'name' => 'Owner test',
                    'kind' => 'musician',
                    'function' => 'Guitare',
                    'email' => 'owner@example.test',
                    'existingUserId' => $this->userId,
                    'administrative' => ['Contact' => 'ADMIN-IMPORT-SECRET'],
                ],
                [
                    'name' => 'Engineer test',
                    'kind' => 'engineer',
                    'function' => 'Son',
                    'email' => 'sound@example.test',
                    'administrative' => [],
                ],
                [
                    'name' => 'Booker pending',
                    'kind' => 'booker',
                    'function' => 'Booker',
                    'administrative' => [],
                ],
            ],
            'accounts' => ['cash' => 15000, 'bank' => 50000],
            'products' => [
                [
                    'name' => 'Test shirt',
                    'category' => 'T-shirt',
                    'variants' => [
                        ['label' => 'XL', 'quantity' => null],
                        ['label' => 'M', 'quantity' => 11],
                    ],
                ],
            ],
            'notes' => ['Remboursement à vérifier'],
            'links' => [['label' => 'Site', 'url' => 'https://example.test']],
        ];
        try {
            file_put_contents($file, json_encode($data));
            $command = new CommandTester(
                static::getContainer()->get(ImportPrivateBandCommand::class),
            );
            for ($i = 0; $i < 2; ++$i) {
                self::assertSame(
                    0,
                    $command->execute(['file' => $file, '--band' => $this->bandId]),
                );
                self::assertStringNotContainsString('ADMIN-IMPORT-SECRET', $command->getDisplay());
            }
            $em = $this->entityManager();
            $em->clear();
            self::assertSame($password, $em->find(User::class, $this->userId)->getPassword());
            self::assertSame(2, $em->getRepository(User::class)->count([]));
            self::assertSame(3, $em->getRepository(MemberProfile::class)->count([]));
            self::assertSame(2, $em->getRepository(TreasuryAccount::class)->count([]));
            self::assertSame(1, $em->getRepository(MerchProduct::class)->count([]));
            self::assertSame(
                65000,
                $em->getConnection()->fetchOne('SELECT SUM(balance_cents) FROM treasury_account'),
            );
            self::assertSame(
                1,
                $em->getConnection()->fetchOne('SELECT COUNT(*) FROM finance_note'),
            );
            self::assertSame(
                1,
                $em
                    ->getConnection()
                    ->fetchOne('SELECT COUNT(*) FROM merch_variant WHERE quantity IS NULL'),
            );
            $newFiles = array_values(
                array_diff(
                    glob(dirname(__DIR__, 2) . '/var/private/activation-codes-*.json') ?: [],
                    $filesBefore,
                ),
            );
            self::assertCount(1, $newFiles);
            $codes = json_decode(file_get_contents($newFiles[0]), true);
            self::assertCount(1, $codes['codes']);
            self::assertSame('Engineer test', $codes['codes'][0]['name']);
            // Attacher plus tard un booker n'invente aucune coordonnée et ne touche pas aux soldes.
            $data['members'][2]['email'] = 'booker@example.test';
            $data['accounts']['cash'] = 1;
            file_put_contents($file, json_encode($data));
            self::assertSame(0, $command->execute(['file' => $file, '--band' => $this->bandId]));
            self::assertSame(3, $em->getRepository(User::class)->count([]));
            self::assertSame(
                15000,
                $em
                    ->getConnection()
                    ->fetchOne("SELECT balance_cents FROM treasury_account WHERE kind = 'cash'"),
            );
        } finally {
            unlink($file);
            foreach (
                array_diff(
                    glob(dirname(__DIR__, 2) . '/var/private/activation-codes-*.json') ?: [],
                    $filesBefore,
                )
                as $path
            ) {
                unlink($path);
            }
        }
    }
}
