<?php

namespace App\Service\Quiz;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates a "What? Where? When?" style question.
 *
 * Primary path: an OpenAI-compatible chat completions API (configurable via
 * OPENAI_BASE_URL / OPENAI_MODEL / OPENAI_API_KEY). The prompt demands a
 * fact-based question with a real, verifiable source — the tvigra.ru editors
 * reject questions without one.
 *
 * Fallback: a small built-in set of questions, so the whole pipeline
 * (schedule -> handler -> mailer) works without any API key configured.
 */
class QuestionGeneratorService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'OPENAI_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire(env: 'OPENAI_MODEL')]
        private readonly string $model,
    ) {
    }

    /**
     * @return array{question: string, answer: string, source: string, source_type: string}
     */
    public function generate(): array
    {
        if ('' !== $this->apiKey) {
            try {
                $generated = $this->generateViaApi();

                return [...$generated, 'source_type' => QuizQuestionSources::AI];
            } catch (\Throwable $e) {
                $this->logger->error('AI question generation failed, falling back to the local set', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            ...QuizQuestionSources::randomLocal(),
            'source_type' => QuizQuestionSources::LOCAL,
        ];
    }

    /**
     * @return array{question: string, answer: string, source: string}
     */
    private function generateViaApi(): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'temperature' => 1.0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => 'Сгенерируй один оригинальный вопрос для творческого отбора телепрограммы «Что? Где? Когда?».'],
                ],
            ],
            'timeout' => 90,
        ]);

        $payload = $response->toArray();
        $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        foreach (['question', 'answer', 'source'] as $key) {
            if (empty($decoded[$key]) || !\is_string($decoded[$key])) {
                throw new \RuntimeException(sprintf('The AI response is missing the "%s" field.', $key));
            }
        }

        return [
            'question' => trim($decoded['question']),
            'answer' => trim($decoded['answer']),
            'source' => trim($decoded['source']),
        ];
    }

    private const SYSTEM_PROMPT = <<<'TXT'
        Ты — опытный автор телевизионной игры «Что? Где? Когда?» уровня творческого отбора «ИГРА-ТВ».
        Напиши ОДИН новый вопрос высшей категории сложности. Требования редакции:
        - короткая фабула (2-5 предложений), информация подаётся через неочевидный, но честный ключ;
        - ответ должен ВЫВОДИТЬСЯ из текста вопроса логикой, ассоциацией или знанием факта,
          а не быть общеизвестной банальностью («столица Франции» — не вопрос);
        - ответ — конкретный существующий факт: слово, имя, название, термин, дата;
        - избегай шаблонов: вопросов про столицы, планеты, самые-самые рекорды;
        - вопрос должен опираться на РЕАЛЬНЫЙ проверяемый факт — не выдумывай.
        Источник укажи настоящий и точный: книга (автор, название, издательство, год, страница),
        статья (издание, дата) или точная ссылка. Если не уверен в реальности источника —
        возьми другой факт.
        Ответ верни строго в JSON без markdown:
        {"question": "...", "answer": "...", "source": "..."}
        TXT;
}
