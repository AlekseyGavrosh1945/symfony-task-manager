<?php

namespace App\Command;

use App\Entity\QuizQuestion;
use App\Service\Quiz\QuestionGeneratorService;
use App\Service\Quiz\TvigraQuestionMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates one question and processes it immediately.
 *
 * Examples:
 *   php bin/console app:quiz:send             # generate, save as draft (respecting TVIGRA_AUTOSEND)
 *   php bin/console app:quiz:send --send      # really send the letter
 *   php bin/console app:quiz:send --send --to me@example.com --allow-local
 */
#[AsCommand(
    name: 'app:quiz:send',
    description: 'Generates a ЧГК question and sends it to the tvigra.ru editorial office (or saves as a draft)',
)]
class SendQuizQuestionCommand extends Command
{
    public function __construct(
        private readonly QuestionGeneratorService $generator,
        private readonly TvigraQuestionMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('send', null, InputOption::VALUE_NONE, 'Really send the letter (ignores TVIGRA_AUTOSEND)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Override the recipient email (e.g. your own address for a test)')
            ->addOption('allow-local', null, InputOption::VALUE_NONE, 'Allow sending a question from the local demo set');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $data = $this->generator->generate();
        $question = new QuizQuestion($data['question'], $data['answer'], $data['source'], $data['source_type']);

        $io->section('Generated question');
        $io->writeln($question->getQuestion());
        $io->writeln('');
        $io->writeln('<comment>Answer:</comment> '.$question->getAnswer());
        $io->writeln('<comment>Source:</comment> '.$question->getSource());
        $io->writeln('<comment>Type:</comment> '.$question->getSourceType());

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        $channel = $this->mailer->submit(
            $question,
            force: (bool) $input->getOption('send'),
            allowLocal: (bool) $input->getOption('allow-local'),
        );
        $question->markSent($channel);
        $this->entityManager->flush();

        if (QuizQuestion::CHANNEL_EMAIL === $channel) {
            $io->success('Letter sent. Saved as quiz_question #'.$question->getId());
        } else {
            $io->note('Kept as a draft (quiz_question #'.$question->getId().'). Nothing was sent.');
        }

        return Command::SUCCESS;
    }
}
