<?php

namespace App\MessageHandler;

use App\Entity\QuizQuestion;
use App\Message\SendQuizQuestion;
use App\Service\Quiz\QuestionGeneratorService;
use App\Service\Quiz\TvigraQuestionMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on schedule (twice a day): generates a question and submits it
 * to the tvigra.ru editorial office (or keeps it as a draft).
 */
#[AsMessageHandler]
class SendQuizQuestionHandler
{
    public function __construct(
        private readonly QuestionGeneratorService $generator,
        private readonly TvigraQuestionMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SendQuizQuestion $message): void
    {
        $data = $this->generator->generate();

        $question = new QuizQuestion(
            $data['question'],
            $data['answer'],
            $data['source'],
            $data['source_type'],
        );

        $channel = $this->mailer->submit($question);
        $question->markSent($channel);

        $this->entityManager->persist($question);
        $this->entityManager->flush();
    }
}
