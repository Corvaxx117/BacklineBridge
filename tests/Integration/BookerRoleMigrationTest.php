<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Band;
use App\Entity\MemberProfile;
use App\Entity\TechnicalSheet;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261102010000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class BookerRoleMigrationTest extends TestCase
{
    public function testBookerCorrectionIsIdempotentAndPreservesOtherMembershipsAndPendingProfiles(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261102010000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        try {
            $connection->executeStatement('CREATE TABLE member_profile (id INTEGER PRIMARY KEY, revision INTEGER, function TEXT, kind TEXT, band_id INTEGER, user_id INTEGER)');
            $connection->executeStatement('CREATE TABLE band_membership (id INTEGER PRIMARY KEY, role TEXT, band_id INTEGER, user_id INTEGER)');
            $connection->executeStatement("INSERT INTO member_profile VALUES (1, 1, 'Booker / manager', 'booker', 1, 10), (2, 1, 'Ingénieur son', 'engineer', 1, 11), (3, 1, 'Guitare', 'musician', 2, 10), (4, 1, 'Booker / manager', 'booker', 1, NULL)");
            $connection->executeStatement("INSERT INTO band_membership VALUES (1, 'manager', 1, 10), (2, 'manager', 1, 11), (3, 'manager', 2, 10)");
            for ($run = 0; $run < 2; ++$run) {
                $migration = new Version20261102010000($connection, new NullLogger());
                $migration->up(new Schema());
                foreach ($migration->getSql() as $query) {
                    $connection->executeStatement($query->getStatement());
                }
            }
            self::assertSame([1 => 'booker', 2 => 'manager', 3 => 'manager'], $connection->fetchAllKeyValue('SELECT id, role FROM band_membership ORDER BY id'));
            self::assertSame([1 => 'Booker', 2 => 'Ingénieur son', 3 => 'Guitare', 4 => 'Booker'], $connection->fetchAllKeyValue('SELECT id, function FROM member_profile ORDER BY id'));
            self::assertSame(2, $connection->fetchOne('SELECT revision FROM member_profile WHERE id = 1'));
            self::assertNull($connection->fetchOne('SELECT user_id FROM member_profile WHERE id = 4'));
            $rollback = new Version20261102010000($connection, new NullLogger());
            $rollback->down(new Schema());
            self::assertSame([], $rollback->getSql());
        } finally {
            $connection->close();
        }
    }

    public function testAReimportedBookerCannotReintroduceTheManagerLabel(): void
    {
        $profile = new MemberProfile(new Band('Test', new TechnicalSheet()), null, 'Romain', 'Booker / manager', MemberKind::Booker);
        self::assertSame('Booker', $profile->getFunction());
        self::assertSame('Booker', BandRole::Booker->label());
    }
}
