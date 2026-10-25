<?php

namespace App\Command;

use App\Database\Database;
use App\Database\SchemaInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:init',
    description: 'Crée la base SQLite et son schéma si nécessaire (idempotent, ne supprime rien)',
)]
final class DbInitCommand extends Command
{
    public function __construct(
        private readonly Database $database,
        private readonly SchemaInstaller $installer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $pdo = $this->database->connection();
            $this->installer->install($pdo);

            $categories = (int) $pdo->query('SELECT COUNT(*) FROM categorie_principale')->fetchColumn();
        } catch (\Throwable $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Schéma à jour dans %s. %d catégories principales disponibles.',
            $this->database->path(),
            $categories,
        ));

        return Command::SUCCESS;
    }
}
