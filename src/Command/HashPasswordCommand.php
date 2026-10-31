<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Calcule le hash à coller dans APP_AUTH_PASSWORD_HASH (.env.local, jamais commité). Le mot de
 * passe est saisi en entrée masquée, jamais écrit en clair sur le disque (jalon 1b).
 */
#[AsCommand(
    name: 'app:auth:hash-password',
    description: 'Calcule le hash à coller dans APP_AUTH_PASSWORD_HASH',
)]
final class HashPasswordCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $question = new Question('Mot de passe à hacher : ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \LogicException('QuestionHelper indisponible.');
        }

        $password = $helper->ask($input, $output, $question);
        if (!is_string($password) || '' === $password) {
            $io->error('Mot de passe vide.');

            return Command::FAILURE;
        }

        $io->success('À coller dans .env.local :');
        $io->writeln(sprintf("APP_AUTH_PASSWORD_HASH='%s'", password_hash($password, \PASSWORD_BCRYPT)));

        return Command::SUCCESS;
    }
}
