<?php

declare(strict_types=1);

namespace App\Tests\Functional\Task;

use App\Dto\Task\TaskInput;
use App\Entity\Band;
use App\Entity\BandTask;
use App\Entity\MemberProfile;
use App\Entity\TechnicalSheet;
use App\Entity\User;
use App\Enum\BandRole;
use App\Enum\MemberKind;
use App\Enum\TaskStatus;
use App\Service\Security\UserCreator;
use App\Service\Task\TaskManager;
use App\Tests\DatabaseTestCase;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TaskWorkspaceTest extends DatabaseTestCase
{
    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->catchExceptions(true);
        $em = $this->entityManager();
        $profile = new MemberProfile($this->band(), $this->user(), 'Julien test', 'Basse', MemberKind::Musician);
        $em->persist($profile);
        $em->flush();
        $this->profileId = $profile->getId();
    }

    public function testCreationIsSharedAndAssignmentFiltersUseTheCurrentMember(): void
    {
        $booker = $this->addUser('booker', BandRole::Booker);
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches/ajouter');
        $this->client->submit($crawler->selectButton('Créer la tâche')->form(), [
            'task[title]' => 'Commander le merch',
            'task[description]' => "Choisir les tailles\nComparer les devis",
            'task[assignee]' => (string) $this->profileId,
            'task[dueAt]' => '2027-02-10',
        ]);
        self::assertResponseRedirects('/groupes/'.$this->bandId.'/taches', 303);
        $this->client->loginUser($booker);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
        self::assertSelectorTextContains('.task-card', 'Commander le merch');
        self::assertSelectorTextContains('.task-assignee', 'Julien test');
        self::assertSelectorTextContains('.task-author', 'Responsable de test');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches?scope=mine');
        self::assertSelectorNotExists('.task-card');
        $this->client->loginUser($this->user());
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches?scope=mine');
        self::assertSelectorCount(1, '.task-card');
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches?scope=unassigned');
        self::assertSelectorNotExists('.task-card');
        self::assertSame(0, $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM concert'));
    }

    public function testAProfileWithoutAnActivatedAccountCanReceiveATask(): void
    {
        $em = $this->entityManager();
        $profile = new MemberProfile($this->band(), null, 'Membre à activer', 'Booker', MemberKind::Booker);
        $em->persist($profile);
        $em->flush();
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches/ajouter');
        $this->client->submit($crawler->selectButton('Créer la tâche')->form(), [
            'task[title]' => 'Préparer les visuels',
            'task[assignee]' => (string) $profile->getId(),
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.task-assignee', 'Membre à activer');
    }

    public function testStateChangesCompletionAndReopeningAreShared(): void
    {
        $id = $this->createTask('Préparer le dossier presse');
        $booker = $this->addUser('booker', BandRole::Booker);
        $this->client->loginUser($booker);
        foreach ([
            'Commencer' => TaskStatus::Doing,
            'Terminer' => TaskStatus::Done,
            'Réouvrir' => TaskStatus::Todo,
        ] as $button => $status) {
            $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
            $this->client->submit($crawler->filter('[data-task-id="'.$id.'"]')->selectButton($button)->form());
            self::assertResponseStatusCodeSame(303);
            $this->entityManager()->clear();
            $task = $this->task($id);
            self::assertSame($status, $task->getStatus());
            self::assertSame(TaskStatus::Done === $status, null !== $task->getCompletedAt());
        }
    }

    public function testInvalidInputAndMissingCsrfCannotCreateOrChangeTasks(): void
    {
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches/ajouter');
        $values = $crawler->selectButton('Créer la tâche')->form()->getPhpValues();
        $values['task']['title'] = str_repeat('x', 181);
        $this->client->request('POST', '/groupes/'.$this->bandId.'/taches/ajouter', $values);
        self::assertResponseStatusCodeSame(422);
        $values['task']['title'] = 'Valide';
        unset($values['task']['_token']);
        $this->client->request('POST', '/groupes/'.$this->bandId.'/taches/ajouter', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager()->getRepository(BandTask::class)->count([]));
        $id = $this->createTask('Ne pas modifier');
        $this->client->request('POST', '/taches/'.$id.'/etat', ['task_state' => ['revision' => '1', 'status' => 'done']]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/taches/'.$id.'/supprimer', ['revision' => '1']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(TaskStatus::Todo, $this->task($id)->getStatus());
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
        $values = $crawler->selectButton('Commencer')->form()->getPhpValues();
        $values['task_state']['status'] = 'unknown';
        $this->client->request('POST', '/taches/'.$id.'/etat', $values);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnotherBandCannotBeReadEditedOrAssigned(): void
    {
        $em = $this->entityManager();
        $other = new Band('Autre groupe', new TechnicalSheet());
        $em->persist($other);
        $em->flush();
        $profile = new MemberProfile($other, null, 'Privé autre groupe', 'Voix', MemberKind::Musician);
        $em->persist($profile);
        $input = new TaskInput();
        $input->title = 'Mission secrète';
        $input->description = 'merch';
        $foreign = static::getContainer()->get(TaskManager::class)->create($other, $this->user(), $input);
        $foreignId = $foreign->getId();
        $em->flush();
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches/ajouter');
        self::assertSelectorTextNotContains('select[name="task[assignee]"]', 'Privé autre groupe');
        $values = $crawler->selectButton('Créer la tâche')->form()->getPhpValues();
        $values['task']['title'] = 'Test attribution';
        $values['task']['assignee'] = (string) $profile->getId();
        $this->client->request('POST', '/groupes/'.$this->bandId.'/taches/ajouter', $values);
        self::assertResponseStatusCodeSame(422);
        foreach ([
            '/groupes/'.$other->getId().'/taches',
            '/taches/'.$foreignId.'/modifier',
            '/taches/'.$foreignId.'/supprimer',
        ] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/taches/'.$foreignId.'/etat');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches?q=merch');
        self::assertSelectorNotExists('.task-card');
    }

    public function testViewersAreReadOnlyAndEditorsCannotDeleteOthersTasks(): void
    {
        $id = $this->createTask('Mission collective');
        $viewer = $this->addUser('viewer', BandRole::Viewer);
        $editor = $this->addUser('editor', BandRole::Editor);
        $this->client->loginUser($viewer);
        $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.task-card', 'Mission collective');
        self::assertSelectorNotExists('.task-state-form');
        foreach ([
            '/groupes/'.$this->bandId.'/taches/ajouter',
            '/taches/'.$id.'/modifier',
            '/taches/'.$id.'/supprimer',
        ] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/taches/'.$id.'/etat');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($editor);
        $this->client->request('GET', '/taches/'.$id.'/modifier');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/taches/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testStaleEditStateAndDeletionDoNotOverwriteNewerChanges(): void
    {
        $id = $this->createTask('Version initiale');
        $crawler = $this->client->request('GET', '/taches/'.$id.'/modifier');
        $edit = $crawler->selectButton('Enregistrer')->form();
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE band_task SET title = ?, revision = revision + 1 WHERE id = ?',
            ['Version récente', $id],
        );
        $this->client->submit($edit, ['task[title]' => 'Ma saisie à garder']);
        self::assertResponseStatusCodeSame(409);
        self::assertInputValueSame('task[title]', 'Ma saisie à garder');
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
        $state = $crawler->selectButton('Commencer')->form();
        $crawler = $this->client->request('GET', '/taches/'.$id.'/supprimer');
        $delete = $crawler->selectButton('Supprimer la tâche')->form();
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE band_task SET revision = revision + 1 WHERE id = ?',
            [$id],
        );
        $this->client->submit($state);
        self::assertResponseStatusCodeSame(409);
        $this->client->submit($delete);
        self::assertResponseStatusCodeSame(409);
        $this->entityManager()->clear();
        self::assertSame('Version récente', $this->task($id)->getTitle());
        self::assertSame(TaskStatus::Todo, $this->task($id)->getStatus());
    }

    public function testOptimisticLockAlsoProtectsAChangeDuringFlush(): void
    {
        $id = $this->createTask('Concurrence');
        $task = $this->task($id);
        $input = TaskInput::fromTask($task);
        $input->title = 'Ancienne saisie';
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE band_task SET revision = revision + 1 WHERE id = ?',
            [$id],
        );
        $this->expectException(OptimisticLockException::class);
        static::getContainer()->get(TaskManager::class)->update($task, $input);
    }

    public function testDeletionChecksRevisionInTheDeleteStatement(): void
    {
        $id = $this->createTask('Conserver la tâche');
        $task = $this->task($id);
        $revision = (string) $task->getRevision();
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE band_task SET revision = revision + 1 WHERE id = ?',
            [$id],
        );
        try {
            static::getContainer()->get(TaskManager::class)->remove($task, $revision);
            self::fail('La suppression concurrente doit être refusée.');
        } catch (ConflictHttpException) {
            self::assertSame(1, $this->entityManager()->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM band_task WHERE id = ?',
                [$id],
            ));
        }
    }

    public function testCreatorCanDeleteOwnTaskAndSearchTreatsWildcardsLiterally(): void
    {
        $editor = $this->addUser('editor', BandRole::Editor);
        $this->client->loginUser($editor);
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches/ajouter');
        $this->client->submit($crawler->selectButton('Créer la tâche')->form(), ['task[title]' => 'Objectif 100% merch']);
        $this->createTask('Autre mission');
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches?q=%25');
        self::assertSelectorCount(1, '.task-card');
        $id = $crawler->filter('.task-card')->attr('data-task-id');
        $crawler = $this->client->request('GET', '/taches/'.$id.'/supprimer');
        $this->client->submit($crawler->selectButton('Supprimer la tâche')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextNotContains('.task-board', 'Objectif 100% merch');
    }

    public function testTaskWorkspaceRequiresAnInternalAccount(): void
    {
        $id = $this->createTask('Privé au groupe');
        $crawler = $this->client->request('GET', '/groupes/'.$this->bandId.'/taches');
        $this->client->submit($crawler->filter('.logout-form')->form());
        foreach (['/groupes/'.$this->bandId.'/taches', '/taches/'.$id.'/modifier'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseRedirects('/connexion');
        }
    }

    private function createTask(string $title): int
    {
        $input = new TaskInput();
        $input->title = $title;
        $task = static::getContainer()->get(TaskManager::class)->create($this->band(), $this->user(), $input);

        return $task->getId();
    }

    private function band(): Band
    {
        return $this->entityManager()->find(Band::class, $this->bandId);
    }

    private function user(): User
    {
        return $this->entityManager()->find(User::class, $this->userId);
    }

    private function task(int $id): BandTask
    {
        return $this->entityManager()->find(BandTask::class, $id);
    }

    private function addUser(string $name, BandRole $role): User
    {
        return static::getContainer()->get(UserCreator::class)->create(
            $name.'@example.test',
            ucfirst($name).' test',
            'CompteTest2026!',
            $this->band(),
            $role,
        );
    }
}
