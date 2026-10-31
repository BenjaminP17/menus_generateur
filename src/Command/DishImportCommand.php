<?php

namespace App\Command;

use App\Database\DishImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:plats:import',
    description: 'Ajoute à la base les plats d\'un fichier JSON (les plats déjà présents sont ignorés, tout ou rien en cas d\'erreur)',
)]
final class DishImportCommand extends Command
{
    public function __construct(private readonly DishImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Fichier JSON des plats, par exemple var/import/plats.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide le fichier sans rien écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('fichier');

        $content = is_file($file) ? file_get_contents($file) : false;
        if (false === $content) {
            $io->error(sprintf('Fichier illisible : %s', $file));

            return Command::FAILURE;
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            $io->error('JSON invalide : '.$error->getMessage());

            return Command::FAILURE;
        }
        if (!is_array($data)) {
            $io->error('Le fichier doit contenir un objet JSON.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->importer->import($data, $dryRun);

        if (!$report->isSuccessful()) {
            $io->error('Import annulé, rien n\'a été écrit.');
            $io->listing($report->errors);

            return Command::FAILURE;
        }

        if ([] !== $report->skipped) {
            $io->note(sprintf('%d plat(s) déjà en base, laissés tels quels :', count($report->skipped)));
            $io->listing($report->skipped);
        }

        $io->success(sprintf(
            $dryRun ? 'Simulation : %d plat(s) seraient ajoutés, rien n\'a été écrit.' : '%d plat(s) ajoutés.',
            count($report->imported),
        ));

        return Command::SUCCESS;
    }
}
