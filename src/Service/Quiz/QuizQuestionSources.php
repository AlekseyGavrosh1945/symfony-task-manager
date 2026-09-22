<?php

namespace App\Service\Quiz;

/**
 * Local fallback questions with real, verifiable facts.
 *
 * They exist so the whole pipeline works without an AI API key.
 * They are NOT meant to be actually submitted to the editors:
 * TvigraQuestionMailer keeps them as drafts unless TVIGRA_ALLOW_LOCAL=1.
 */
final class QuizQuestionSources
{
    public const AI = 'ai';
    public const LOCAL = 'local';

    private const LOCAL_QUESTIONS = [
        [
            'question' => 'Этот город расположен сразу на двух континентах. Местный житель может перейти из Европы в Азию за пять минут на пароме. Назовите город.',
            'answer' => 'Стамбул.',
            'source' => 'Общедоступный географический факт: Стамбул, разделённый проливом Босфор, находится в Европе и Азии.',
        ],
        [
            'question' => 'Ближе к Солнцу находится Меркурий, однако самое жаркое место в Солнечной системе — не он. Назовите самую горячую планету.',
            'answer' => 'Венера.',
            'source' => 'Плотная атмосфера Венеры создаёт парниковый эффект, из-за чего её поверхность горячее меркурианской. См. любой справочник по астрономии.',
        ],
        [
            'question' => 'По первоначальному контракту эту башню через 20 лет после строительства планировали разобрать. Спасти её удалось во многом благодаря радиотелеграфу. Назовите башню.',
            'answer' => 'Эйфелева башня.',
            'source' => 'Исторический факт: Эйфелева башня использовалась для военной радиосвязи, что сделало её демонтаж нецелесообразным.',
        ],
    ];

    /**
     * @return array{question: string, answer: string, source: string}
     */
    public static function randomLocal(): array
    {
        return self::LOCAL_QUESTIONS[array_rand(self::LOCAL_QUESTIONS)];
    }
}
