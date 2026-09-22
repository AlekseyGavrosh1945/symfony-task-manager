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
        private readonly WikiFactProvider $wikiFactProvider,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'OPENAI_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire(env: 'OPENAI_MODEL')]
        private readonly string $model,
        // Comma-separated fallback models: free pools are flaky (429s happen).
        #[Autowire(env: 'OPENAI_FALLBACK_MODELS')]
        private readonly string $fallbackModels,
    ) {
    }

    /**
     * @return array{question: string, answer: string, source: string, source_type: string}
     */
    public function generate(): array
    {
        if ('' !== $this->apiKey) {
            // The fact comes from Wikipedia, not from the model's imagination:
            // this is what keeps sources real and verifiable.
            $fact = $this->wikiFactProvider->getRandomFact();
            $models = array_filter(array_map('trim', explode(',', $this->model.','.$this->fallbackModels)));

            foreach ($models as $model) {
                try {
                    $generated = $this->generateViaApi($model, $fact);

                    return [
                        ...$generated,
                        'source' => sprintf("Википедия: «%s» — %s", $fact['title'], $fact['url']),
                        'source_type' => QuizQuestionSources::AI,
                    ];
                } catch (\Throwable $e) {
                    $this->logger->error('AI question generation failed, trying the next model', [
                        'model' => $model,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // The key is configured but nothing answered: surface the failure so
            // the Messenger retry strategy re-runs the handler later, instead of
            // silently saving a placeholder question.
            throw new \RuntimeException(sprintf(
                'All AI models failed (%s). The question will be regenerated on retry.',
                implode(', ', $models)
            ));
        }

        return [
            ...QuizQuestionSources::randomLocal(),
            'source_type' => QuizQuestionSources::LOCAL,
        ];
    }

    /**
     * @param array{title: string, extract: string, url: string} $fact
     *
     * @return array{question: string, answer: string, source: string}
     */
    private function generateViaApi(string $model, array $fact): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'temperature' => 1.0,
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => sprintf(
                        "Реальный факт из Википедии:\n«%s» — %s\n\nСоставь по нему один вопрос для творческого отбора «Что? Где? Когда?».",
                        $fact['title'],
                        $fact['extract']
                    )],
                ],
            ],
            'timeout' => 120,
        ]);

        $payload = $response->toArray();
        $content = (string) ($payload['choices'][0]['message']['content'] ?? '');

        // Not every OpenRouter model honours json_object mode: extract the JSON
        // object from a possibly wrapped (```json ... ```) reply ourselves.
        if (!preg_match('/\{.*\}/s', $content, $m)) {
            throw new \RuntimeException('The AI reply contains no JSON object.');
        }

        $decoded = json_decode($m[0], true, flags: JSON_THROW_ON_ERROR);

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
        Ты — автор телевизионной игры «Что? Где? Когда?» уровня творческого отбора.
        Тебе дают РЕАЛЬНЫЙ факт из Википедии. Твоя задача — построить вокруг него вопрос:
        - ответом служит ключевое понятие, имя или название из этого факта;
        - СТРОГО используй только информацию из данного факта — ничего не выдумывай,
          не добавляй других персон, событий и деталей;
        - спрячь прямые подсказки: убери из текста вопроса само заглавие статьи и
          очевидные слова-маркеры, дай факту интересную фабульную подачу;
        - ответ должен выводиться логикой или узнаванием, а не быть общеизвестной банальностью;
        - вопрос — 2-5 предложений, завершается собственно вопросом.
        Ответ верни строго в JSON без markdown:
        {"question": "...", "answer": "...", "source": "..."}
        TXT;
}
