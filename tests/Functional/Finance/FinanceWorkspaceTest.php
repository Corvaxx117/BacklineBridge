<?php

declare(strict_types=1);
namespace App\Tests\Functional\Finance;
use App\Entity\{
    Band,
    User,
    BandMembership,
    MemberProfile,
    TreasuryAccount,
    TreasuryEntry,
    MerchProduct,
    MerchVariant,
    StockMovement,
};
use App\Enum\{BandRole, MemberKind, TreasuryAccountKind, TreasuryEntryKind};
use App\Tests\DatabaseTestCase;
use App\Service\Finance\TreasuryView;
use App\Service\Security\UserCreator;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
final class FinanceWorkspaceTest extends DatabaseTestCase
{
    private int $variantId;
    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $em->persist(
            new MemberProfile(
                $band,
                $em->find(User::class, $this->userId),
                'Musicien test',
                'Guitare',
                MemberKind::Musician,
            ),
        );
        foreach (
            [[TreasuryAccountKind::Cash, 120000], [TreasuryAccountKind::Bank, 80000]]
            as [$kind, $amount]
        ) {
            $account = new TreasuryAccount($band, $kind);
            $account->credit($amount);
            $em->persist($account);
            $em->persist(
                new TreasuryEntry(
                    $account,
                    TreasuryEntryKind::Opening,
                    $amount,
                    'Solde validé',
                    'Initialisation',
                    new \DateTimeImmutable('today'),
                ),
            );
        }
        $product = new MerchProduct($band, 'Vinyle de test', 'Vinyle');
        $variant = new MerchVariant($product, 'Unique');
        $variant->setQuantity(9);
        $em->persist($product);
        $em->flush();
        $this->variantId = $variant->getId();
        $em->clear();
    }
    private function balances(): array
    {
        return $this->entityManager()
            ->getConnection()
            ->fetchAllKeyValue('SELECT kind, balance_cents FROM treasury_account ORDER BY kind');
    }
    private function submitForm(string $uri, string $name, array $changes): void
    {
        $form = $this->client
            ->request('GET', $uri)
            ->filter('form[name="' . $name . '"]')
            ->form();
        $values = $form->getPhpValues();
        $values[$name] = array_replace($values[$name], $changes);
        $this->client->request('POST', $uri, $values);
    }
    public function testOverviewAndStockRenderWithoutCountingOpeningBalancesAsIncome(): void
    {
        $this->client->request('GET', '/groupes/' . $this->bandId . '/tresorerie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.balance-grid', '2 000,00');
        self::assertSelectorTextContains('.chart-legend', 'Recettes : 0,00');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $this->client->request('GET', '/groupes/' . $this->bandId . '/stock');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.merch-grid', 'Vinyle de test');
    }
    public function testSaleAndCancellationUpdateMoneyAndStockTogether(): void
    {
        $this->submitForm('/stock/variantes/' . $this->variantId . '/mouvement', 'stock_movement', [
            'kind' => 'sale',
            'quantity' => '2',
            'reason' => 'Concert test',
            'amount' => '31,50',
            'account' => 'cash',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(123150, $this->balances()['cash']);
        self::assertSame(
            7,
            $this->entityManager()
                ->getConnection()
                ->fetchOne('SELECT quantity FROM merch_variant WHERE id = ?', [$this->variantId]),
        );
        $id = $this->entityManager()
            ->getConnection()
            ->fetchOne("SELECT id FROM treasury_entry WHERE kind = 'income'");
        $this->submitForm('/tresorerie/mouvements/' . $id . '/annuler', 'cancel_entry', [
            'reason' => 'Vente saisie deux fois',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(120000, $this->balances()['cash']);
        self::assertSame(
            9,
            $this->entityManager()
                ->getConnection()
                ->fetchOne('SELECT quantity FROM merch_variant WHERE id = ?', [$this->variantId]),
        );
        $this->submitForm('/tresorerie/mouvements/' . $id . '/annuler', 'cancel_entry', [
            'reason' => 'Encore',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(120000, $this->balances()['cash']);
        self::assertSame(2, $this->entityManager()->getRepository(StockMovement::class)->count([]));
    }
    public function testTransferPreservesTotalAndIsExcludedFromIncomeAndExpense(): void
    {
        $this->submitForm('/groupes/' . $this->bandId . '/tresorerie/transfert', 'transfer', [
            'source' => 'cash',
            'destination' => 'bank',
            'amount' => '100,25',
            'label' => 'Dépôt banque',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(['bank' => 90025, 'cash' => 109975], $this->balances());
        $summary = static::getContainer()
            ->get(TreasuryView::class)
            ->summary($this->entityManager()->find(Band::class, $this->bandId));
        self::assertSame(200000, $summary['total']);
        self::assertSame(0, $summary['income']);
        self::assertSame(0, $summary['expense']);
    }
    public function testExpenseAndOverdraftValidation(): void
    {
        $uri = '/groupes/' . $this->bandId . '/tresorerie/mouvement';
        $this->submitForm($uri, 'treasury_entry', [
            'kind' => 'expense',
            'amount' => '8,73',
            'label' => 'Transport',
            'category' => 'Transport',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(119127, $this->balances()['cash']);
        $this->submitForm($uri, 'treasury_entry', [
            'kind' => 'expense',
            'amount' => '1200',
            'label' => 'Trop',
            'category' => 'Transport',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(119127, $this->balances()['cash']);
    }
    public function testInvalidStockAndUnknownQuantityCannotProduceIncome(): void
    {
        $uri = '/stock/variantes/' . $this->variantId . '/mouvement';
        $this->submitForm($uri, 'stock_movement', [
            'kind' => 'sale',
            'quantity' => '10',
            'amount' => '20',
            'reason' => 'Trop',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(120000, $this->balances()['cash']);
        $this->entityManager()
            ->getConnection()
            ->executeStatement('UPDATE merch_variant SET quantity = NULL WHERE id = ?', [
                $this->variantId,
            ]);
        $this->submitForm($uri, 'stock_movement', [
            'kind' => 'sale',
            'quantity' => '1',
            'amount' => '20',
            'reason' => 'Inconnu',
        ]);
        self::assertResponseStatusCodeSame(422);
        $this->submitForm($uri, 'stock_movement', [
            'kind' => 'count',
            'quantity' => '0',
            'reason' => 'Inventaire réel',
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(
            0,
            $this->entityManager()
                ->getConnection()
                ->fetchOne('SELECT quantity FROM merch_variant WHERE id = ?', [$this->variantId]),
        );
        self::assertSame(120000, $this->balances()['cash']);
    }
    public function testStaleSaleAndMissingCsrfDoNotChangeAnyBalance(): void
    {
        $uri = '/stock/variantes/' . $this->variantId . '/mouvement';
        $form = $this->client->request('GET', $uri)->filter('form[name="stock_movement"]')->form();
        $this->entityManager()
            ->getConnection()
            ->executeStatement(
                "UPDATE treasury_account SET revision = revision + 1 WHERE kind = 'cash'",
            );
        $values = $form->getPhpValues();
        $values['stock_movement'] = array_replace($values['stock_movement'], [
            'kind' => 'sale',
            'quantity' => '1',
            'amount' => '20',
            'reason' => 'Ancienne page',
        ]);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(120000, $this->balances()['cash']);
        unset($values['stock_movement']['_token']);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(120000, $this->balances()['cash']);
        self::assertSame(
            9,
            $this->entityManager()
                ->getConnection()
                ->fetchOne('SELECT quantity FROM merch_variant WHERE id = ?', [$this->variantId]),
        );
    }
    public function testStockRaceRollsBackBothLedgerAndInventory(): void
    {
        $this->client->disableReboot();
        $uri = '/stock/variantes/' . $this->variantId . '/mouvement';
        $form = $this->client->request('GET', $uri)->filter('form[name="stock_movement"]')->form();
        $em = $this->entityManager();
        $other = DriverManager::getConnection(
            $em->getConnection()->getParams(),
            $em->getConnection()->getConfiguration(),
        );
        $listener = new class ($other, $this->variantId) {
            public function __construct(private $connection, private int $id) {}
            public function preFlush(): void
            {
                $this->connection->executeStatement(
                    'UPDATE merch_variant SET quantity = 8, revision = revision + 1 WHERE id = ?',
                    [$this->id],
                );
            }
        };
        $em->getEventManager()->addEventListener([Events::preFlush], $listener);
        try {
            $this->client->submit($form, [
                'stock_movement' => [
                    'kind' => 'sale',
                    'quantity' => '2',
                    'amount' => '30',
                    'reason' => 'Conflit',
                ],
            ]);
            self::assertResponseStatusCodeSame(409);
            self::assertSame(
                120000,
                $other->fetchOne("SELECT balance_cents FROM treasury_account WHERE kind = 'cash'"),
            );
            self::assertSame(
                8,
                $other->fetchOne('SELECT quantity FROM merch_variant WHERE id = ?', [
                    $this->variantId,
                ]),
            );
            self::assertSame(
                0,
                $other->fetchOne("SELECT COUNT(*) FROM treasury_entry WHERE kind = 'income'"),
            );
            self::assertSame(0, $other->fetchOne('SELECT COUNT(*) FROM stock_movement'));
        } finally {
            $em->getEventManager()->removeEventListener([Events::preFlush], $listener);
            $other->close();
        }
    }
    #[DataProvider('nonMusicians')]
    public function testAllFinanceAndStockRoutesDenyOtherRoles(
        BandRole $role,
        ?MemberKind $kind,
    ): void {
        $em = $this->entityManager();
        $band = $em->find(Band::class, $this->bandId);
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'other@example.test',
                'Autre',
                'Another solid password for 2026!',
                $band,
                $role,
            );
        if ($kind !== null) {
            $em->persist(new MemberProfile($band, $user, 'Autre', 'Test', $kind));
            $em->flush();
        }
        $this->client->loginUser($user);
        $this->client->catchExceptions(true);
        foreach (
            [
                '/groupes/' . $this->bandId . '/tresorerie',
                '/groupes/' . $this->bandId . '/stock',
                '/stock/variantes/' . $this->variantId . '/mouvement',
                '/groupes/' . $this->bandId . '/tresorerie/mouvement',
                '/groupes/' . $this->bandId . '/tresorerie/transfert',
                '/groupes/' . $this->bandId . '/stock/nouveau',
            ]
            as $uri
        ) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
            $this->client->request('POST', $uri, []);
            self::assertContains($this->client->getResponse()->getStatusCode(), [403, 405]);
        }
        $this->client->request('GET', '/concerts');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/groupes/' . $this->bandId . '/tresorerie"]');
    }
    public static function nonMusicians(): iterable
    {
        yield 'Marty' => [BandRole::Manager, MemberKind::Engineer];
        yield 'Romain' => [BandRole::Booker, MemberKind::Booker];
        yield 'Manager without a musician profile' => [BandRole::Manager, null];
        yield 'Reader' => [BandRole::Viewer, MemberKind::Musician];
    }
    public function testMusicianFromAnotherBandCannotReadOrWriteThisInventory(): void
    {
        $em = $this->entityManager();
        $sheet = new \App\Entity\TechnicalSheet()->setBandName('Other band');
        $band = new Band('Other band', $sheet);
        $em->persist($band);
        $em->flush();
        $user = static::getContainer()
            ->get(UserCreator::class)
            ->create(
                'outsider@example.test',
                'Other musician',
                'Different strong password 2026!',
                $band,
                BandRole::Manager,
            );
        $em->persist(
            new MemberProfile($band, $user, 'Other musician', 'Bass', MemberKind::Musician),
        );
        $em->flush();
        $this->client->loginUser($user);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/groupes/' . $this->bandId . '/tresorerie');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/groupes/' . $this->bandId . '/stock');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/stock/variantes/' . $this->variantId . '/mouvement', []);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(120000, $this->balances()['cash']);
    }

    public function testRevokedMembershipImmediatelyRemovesFinanceAccess(): void
    {
        $this->client->request('GET', '/groupes/' . $this->bandId . '/tresorerie');
        self::assertResponseIsSuccessful();
        $this->entityManager()
            ->getConnection()
            ->executeStatement('DELETE FROM band_membership WHERE user_id = ?', [$this->userId]);
        $this->client->catchExceptions(true);
        $this->client->request('GET', '/groupes/' . $this->bandId . '/tresorerie');
        self::assertResponseStatusCodeSame(403);
    }
}
