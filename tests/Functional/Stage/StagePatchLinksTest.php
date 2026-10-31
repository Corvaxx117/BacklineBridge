<?php

declare(strict_types=1);

namespace App\Tests\Functional\Stage;

use App\Dto\Concert\ReferenceImportInput;
use App\Entity\Concert;
use App\Entity\PatchLine;
use App\Entity\TechnicalSheet;
use App\Service\Concert\ReferenceImporter;
use App\Service\Concert\ReferenceImportPreview;
use App\Service\Pdf\TechnicalDossierFactory;
use App\Service\Stage\StagePatchLinks;
use App\Tests\VenueTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class StagePatchLinksTest extends VenueTestCase
{
    #[DataProvider('audioKinds')]
    public function testAudioElementsCanBeLinkedToTheCurrentPatch(string $kind): void
    {
        $source = $this->lineForChannel(1)->getSourceKey();
        $uri = '/fiches/'.$this->sheetId.'/plan-de-scene';
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $values = $form->getPhpValues();
        $values['stage_plan']['items'][0]['kind'] = $kind;
        $values['stage_plan']['items'][0]['patchSources'] = [$source];
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        self::assertSame([$source], $this->sheet()->getStagePlan()[0]['patchSources']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.stage-'.$kind.' .stage-patch-badge', 'Entrée 1');
    }

    public static function audioKinds(): iterable
    {
        foreach (['mic_vocal', 'mic_instrument', 'stand_straight', 'stand_boom', 'di_mono', 'di_stereo'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function testRenumberingFollowsTheSourceAndReusingItsOldChannelDoesNotRelinkIt(): void
    {
        $source = $this->lineForChannel(1);
        $this->bind($this->sheet(), [$source->getSourceKey()]);
        $source->setChannelNumber(50);
        $this->entityManager()->flush();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.stage-di_stereo .stage-patch-badge', 'Entrée 50');
        $source = $this->lineForChannel(50);
        $source->archive();
        $this->entityManager()->flush();
        $this->sheet()->addLine(new PatchLine()->setChannelNumber(50)->setSourceName('Nouvelle source'));
        $this->entityManager()->flush();
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('.stage-di_stereo .stage-patch-badge', 'source retirée');
        self::assertSelectorTextNotContains('.stage-di_stereo .stage-patch-badge', 'Entrée 50');
    }

    public function testUnchangedPlanWithAnUnavailableSourceCanBeSavedAndThenUnlinked(): void
    {
        $source = $this->lineForChannel(1);
        $this->bind($this->sheet(), [$source->getSourceKey()]);
        $source->archive();
        $this->entityManager()->flush();
        $uri = '/fiches/'.$this->sheetId.'/plan-de-scene';
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $values = $form->getPhpValues();
        unset($values['stage_plan']['items'][0]['patchSources']);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(303);
        $this->entityManager()->clear();
        self::assertArrayNotHasKey('patchSources', $this->sheet()->getStagePlan()[0]);
    }

    public function testOtherSheetSourcesAndOversizedSelectionsCannotBeBound(): void
    {
        $uri = '/fiches/'.$this->sheetId.'/plan-de-scene';
        $other = new TechnicalSheet()->setBandName('Autre fiche');
        $line = new PatchLine()->setChannelNumber(1)->setSourceName('Source étrangère');
        $other->addLine($line);
        $this->entityManager()->persist($other);
        $this->entityManager()->flush();
        $foreignKey = $line->getSourceKey();
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $values = $form->getPhpValues();
        $values['stage_plan']['items'][0]['patchSources'] = [$foreignKey];
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $keys = array_keys(static::getContainer()->get(StagePatchLinks::class)->choices($this->sheet()));
        $values['stage_plan']['items'][0]['patchSources'] = array_slice($keys, 0, 13);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $this->entityManager()->clear();
        self::assertArrayNotHasKey('patchSources', $this->sheet()->getStagePlan()[0]);
    }

    public function testAChangedPatchMakesAnOpenStageFormStale(): void
    {
        $uri = '/fiches/'.$this->sheetId.'/plan-de-scene';
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $this->lineForChannel(1)->setSourceName('Source renommée');
        $this->entityManager()->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(409);
    }

    public function testStereoDiCanBindTwoSourcesButRejectsAThird(): void
    {
        $uri = '/fiches/'.$this->sheetId.'/plan-de-scene';
        $keys = array_keys(static::getContainer()->get(StagePatchLinks::class)->choices($this->sheet()));
        $form = $this->client->request('GET', $uri)->filter('form[name="stage_plan"]')->form();
        $values = $form->getPhpValues();
        $values['stage_plan']['items'][0]['kind'] = 'di_stereo';
        $values['stage_plan']['items'][0]['patchSources'] = array_slice($keys, 0, 3);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(422);
        $values['stage_plan']['items'][0]['patchSources'] = array_slice($keys, 0, 2);
        $this->client->request('POST', $uri, $values);
        self::assertResponseStatusCodeSame(303);
    }

    public function testConcertCopiesResolveTheirOwnChannelsAndGuestPublicationsStayFrozen(): void
    {
        $this->bind($this->sheet(), [$this->lineForChannel(1)->getSourceKey()]);
        $concert = $this->concert();
        $id = $concert->getId();
        $issued = $this->issue($concert);
        $source = $this->source($concert->getPatchSheet(), 1);
        $source->setChannelNumber(50);
        $this->entityManager()->flush();
        $this->client->request('GET', '/concerts/'.$id);
        self::assertSelectorTextContains('.stage-di_stereo .stage-patch-badge', 'Entrée 50');
        $this->enter($issued->token);
        self::assertSelectorTextContains('.stage-di_stereo .stage-patch-badge', 'Entrée 1');
        self::assertSelectorTextNotContains('.stage-di_stereo .stage-patch-badge', 'Entrée 50');
        $publication = $this->entityManager()->find(\App\Entity\TechnicalPublication::class, $issued->invitation->getPublication()->getId());
        $dossier = static::getContainer()->get(TechnicalDossierFactory::class)->forPublication($publication);
        self::assertSame('Entrée 1', $dossier->stagePlan[0]['patchLabel']);
        self::assertArrayNotHasKey('patchSources', $dossier->stagePlan[0]);
    }

    public function testReferenceStageImportRemapsLegacyIdentitiesAndDoesNotInventAStageChangeAfterRenumbering(): void
    {
        $this->bind($this->sheet(), [$this->lineForChannel(1)->getSourceKey()]);
        $concert = $this->concert();
        $target = $concert->getPatchSheet();
        $source = $this->source($target, 1);
        $source->inheritSourceKey(new PatchLine());
        $target->setStagePlan([]);
        $this->entityManager()->flush();
        $this->import($concert, ['stage']);
        self::assertSame([$source->getSourceKey()], $target->getStagePlan()[0]['patchSources']);
        $source->setChannelNumber(50);
        $this->entityManager()->flush();
        self::assertFalse(static::getContainer()->get(ReferenceImportPreview::class)->forConcert($concert)['sections']['stage']['changed']);
    }

    public function testPatchImportPreservesBindingsAcrossChannelSwaps(): void
    {
        $first = $this->lineForChannel(1);
        $second = $this->lineForChannel(2);
        $key = $first->getSourceKey();
        $this->bind($this->sheet(), [$key]);
        $concert = $this->concert();
        $first->setChannelNumber(50);
        $this->entityManager()->flush();
        $second->setChannelNumber(1);
        $this->entityManager()->flush();
        $this->import($concert, ['patch']);
        $items = static::getContainer()->get(StagePatchLinks::class)->present($concert->getPatchSheet());
        self::assertSame('Entrée 50', $items[0]['patchLabel']);
        $keys = [];
        foreach ($concert->getPatchSheet()->getLines() as $line) {
            if ($line->isActive()) {
                $keys[] = $line->getSourceKey();
            }
        }
        self::assertCount(count(array_unique($keys)), $keys);
    }

    /** @param list<string> $keys */
    private function bind(TechnicalSheet $sheet, array $keys): void
    {
        $plan = $sheet->getStagePlan();
        $plan[0]['kind'] = 'di_stereo';
        $plan[0]['patchSources'] = $keys;
        $sheet->setStagePlan($plan);
        $this->entityManager()->flush();
    }

    private function source(TechnicalSheet $sheet, int $channel): PatchLine
    {
        foreach ($sheet->getLines() as $line) {
            if ($line->isActive() && $line->getChannelNumber() === $channel) {
                return $line;
            }
        }
        throw new \LogicException('Source de test introuvable.');
    }

    /** @param list<string> $sections */
    private function import(Concert $concert, array $sections): void
    {
        $input = new ReferenceImportInput();
        $input->sections = $sections;
        $input->confirmReplacement = true;
        $input->fingerprint = static::getContainer()->get(ReferenceImportPreview::class)->forConcert($concert)['fingerprint'];
        static::getContainer()->get(ReferenceImporter::class)->import($concert, $input);
    }
}
