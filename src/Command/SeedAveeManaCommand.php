<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PatchLine;
use App\Entity\Band;
use App\Entity\TechnicalSheet;
use App\Repository\TechnicalSheetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[
    AsCommand(
        name: 'app:seed-avee-mana',
        description: 'Charge la fiche de départ une seule fois, sans écraser les modifications.',
    ),
]
final class SeedAveeManaCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TechnicalSheetRepository $sheets,
        private readonly ValidatorInterface $validator,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->sheets->findReference() !== null) {
            $io->note('Une fiche existe déjà : les données et vos modifications sont conservées.');

            return Command::SUCCESS;
        }

        $data = json_decode(
            file_get_contents($this->projectDir . '/data/avee-mana-patch.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $sheet = new TechnicalSheet()
            ->setBandName($data['bandName'])
            ->setDocumentLabel($data['documentLabel'])
            ->setNotices($data['notices']);
        $seen = [];

        $setup = json_decode(
            file_get_contents($this->projectDir . '/data/avee-mana-stage.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $sheet->setStagePlan($setup['stagePlan'])->setEquipmentRequests($setup['equipment']);

        foreach ($data['lines'] as $row) {
            if (isset($seen[$row['channelNumber']])) {
                $io->error('Le jeu de départ contient un numéro de canal en double.');

                return Command::INVALID;
            }
            $seen[$row['channelNumber']] = true;
            $line = new PatchLine()
                ->setSheet($sheet)
                ->setChannelNumber($row['channelNumber'])
                ->setSourceName($row['sourceName'])
                ->setPreferredMicrophone($row['preferredMicrophone'])
                ->setStand($row['stand'])
                ->setNotes($row['notes'])
                ->setSection($row['section']);
            $violations = $this->validator->validate($line);

            if (count($violations) > 0) {
                $io->error((string) $violations);

                return Command::INVALID;
            }
        }

        $this->entityManager->persist(new Band($sheet->getBandName(), $sheet));
        $this->entityManager->flush();
        $io->success(sprintf('Fiche Avee Mana initialisée : %d sources.', count($seen)));

        return Command::SUCCESS;
    }
}
