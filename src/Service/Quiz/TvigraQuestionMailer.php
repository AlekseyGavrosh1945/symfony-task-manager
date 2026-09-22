<?php

namespace App\Service\Quiz;

use App\Entity\QuizQuestion;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Builds and submits the question letter to the tvigra.ru editorial office.
 *
 * The letter follows the published requirements (chgk.tvigra.ru/question/email):
 * 1) question wording and the correct answer;
 * 2) a detailed verifiable source;
 * 3) author's full name;
 * 4) home address and phone;
 * 5) photo, age and occupation.
 *
 * Author data comes from AUTHOR_* environment variables (keep real values
 * in .env.local, never commit them).
 */
class TvigraQuestionMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MAILER_DSN')]
        private readonly string $mailerDsn,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $fromEmail,
        #[Autowire(env: 'TVIGRA_QUESTION_EMAIL')]
        private readonly string $editorEmail,
        #[Autowire(env: 'TVIGRA_TEST_EMAIL')]
        private readonly string $testEmail,
        #[Autowire(env: 'TVIGRA_AUTOSEND')]
        private readonly string $autosend,
        #[Autowire(env: 'AUTHOR_FULL_NAME')]
        private readonly string $authorName,
        #[Autowire(env: 'AUTHOR_ADDRESS')]
        private readonly string $authorAddress,
        #[Autowire(env: 'AUTHOR_PHONE')]
        private readonly string $authorPhone,
        #[Autowire(env: 'AUTHOR_BIO')]
        private readonly string $authorBio,
        #[Autowire(env: 'AUTHOR_PHOTO_PATH')]
        private readonly string $authorPhotoPath,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Sends the letter (or keeps it as a draft according to the config).
     *
     * @param bool $force      send even when TVIGRA_AUTOSEND=0 (used by the console command --send)
     * @param bool $allowLocal send even when the question comes from the local demo set
     *
     * @return string QuizQuestion::CHANNEL_EMAIL when really sent, CHANNEL_DRAFT otherwise
     */
    public function submit(QuizQuestion $question, bool $force = false, bool $allowLocal = false): string
    {
        if ($force) {
            return $this->doSend($question, $allowLocal);
        }

        if ('1' !== $this->autosend) {
            $this->logger->info('Quiz question kept as a draft (TVIGRA_AUTOSEND=0)', [
                'question' => $question->getQuestion(),
            ]);

            return QuizQuestion::CHANNEL_DRAFT;
        }

        return $this->doSend($question, $allowLocal);
    }

    private function doSend(QuizQuestion $question, bool $allowLocal): string
    {
        if (str_starts_with($this->mailerDsn, 'null://')) {
            $this->logger->warning('MAILER_DSN is null:// — no real transport configured, letter kept as a draft.');
            $this->logger->info($this->renderPlain($question));

            return QuizQuestion::CHANNEL_DRAFT;
        }

        if (QuizQuestionSources::LOCAL === $question->getSourceType() && !$allowLocal) {
            // Local demo questions must never reach the real editors.
            $this->logger->warning('Local demo question is not submitted (TVIGRA_ALLOW_LOCAL=0).');
            $this->logger->info($this->renderPlain($question));

            return QuizQuestion::CHANNEL_DRAFT;
        }

        $email = (new Email())
            ->from($this->fromEmail)
            ->to('' !== $this->testEmail ? $this->testEmail : $this->editorEmail)
            ->subject(sprintf('Вопрос для творческого отбора «Что? Где? Когда?» — %s', $this->authorName ?: 'автор'))
            ->text($this->renderPlain($question))
            ->html($this->renderHtml($question));

        if ('' !== $this->authorPhotoPath) {
            $photoPath = str_starts_with($this->authorPhotoPath, '/')
                ? $this->authorPhotoPath
                : $this->projectDir.'/'.$this->authorPhotoPath;

            if (is_file($photoPath)) {
                $email->attachFromPath($photoPath, 'author_photo', $this->guessMime($photoPath));
            } else {
                $this->logger->warning('AUTHOR_PHOTO_PATH is set but the file does not exist', ['path' => $photoPath]);
            }
        }

        $this->mailer->send($email);

        $this->logger->info('Quiz question letter sent', [
            'to' => $email->getTo()[0]?->toString(),
            'question_id' => $question->getId(),
        ]);

        return QuizQuestion::CHANNEL_EMAIL;
    }

    /**
     * Plain-text letter following the tvigra.ru requirements point by point.
     */
    public function renderPlain(QuizQuestion $question): string
    {
        return <<<TXT
        Здравствуйте!

        Направляю вопрос для творческого отбора телепрограммы «Что? Где? Когда?».

        1. Формулировка вопроса и правильный ответ

        Вопрос: {$question->getQuestion()}

        Ответ: {$question->getAnswer()}

        2. Источник информации

        {$question->getSource()}

        3. ФИО автора
        {$this->placeholder($this->authorName, 'ФИО автора (AUTHOR_FULL_NAME)')}

        4. Домашний адрес, телефон
        {$this->placeholder($this->authorAddress, 'домашний адрес (AUTHOR_ADDRESS)')}
        {$this->placeholder($this->authorPhone, 'телефон (AUTHOR_PHONE)')}

        5. Возраст, чем занимается
        {$this->placeholder($this->authorBio, 'возраст и род занятий (AUTHOR_BIO)')}

        Фотография прилагается отдельным файлом.

        С уважением,
        {$this->placeholder($this->authorName, 'ФИО автора')}
        TXT;
    }

    private function renderHtml(QuizQuestion $question): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $or = fn (string $value, string $placeholder): string => $esc('' !== $value ? $value : '['.$placeholder.']');

        return <<<HTML
        <html><body style="font-family: Georgia, serif; line-height: 1.5;">
        <p>Здравствуйте!</p>
        <p>Направляю вопрос для творческого отбора телепрограммы «Что? Где? Когда?».</p>

        <h3>1. Формулировка вопроса и правильный ответ</h3>
        <p><b>Вопрос:</b> {$esc($question->getQuestion())}</p>
        <p><b>Ответ:</b> {$esc($question->getAnswer())}</p>

        <h3>2. Источник информации</h3>
        <p>{$esc($question->getSource())}</p>

        <h3>3. ФИО автора</h3>
        <p>{$or($this->authorName, 'ФИО автора (AUTHOR_FULL_NAME)')}</p>

        <h3>4. Домашний адрес, телефон</h3>
        <p>{$or($this->authorAddress, 'домашний адрес (AUTHOR_ADDRESS)')}<br>
        {$or($this->authorPhone, 'телефон (AUTHOR_PHONE)')}</p>

        <h3>5. Возраст, чем занимается</h3>
        <p>{$or($this->authorBio, 'возраст и род занятий (AUTHOR_BIO)')}</p>

        <p>Фотография прилагается отдельным файлом.</p>
        </body></html>
        HTML;
    }

    private function placeholder(string $value, string $placeholder): string
    {
        return '' !== $value ? $value : '['.$placeholder.']';
    }

    private function guessMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
