<?php

namespace App\Service\Quiz;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a random real fact from Russian Wikipedia.
 *
 * Using a real, verifiable fact as the seed instead of letting the LLM invent
 * one removes the main failure mode of AI-generated quiz questions:
 * hallucinated facts and non-existent sources.
 */
class WikiFactProvider
{
    private const MIN_EXTRACT_LENGTH = 180;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{title: string, extract: string, url: string}
     */
    public function getRandomFact(): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $data = $this->httpClient
                ->request('GET', 'https://ru.wikipedia.org/api/rest_v1/page/random/summary', [
                    'timeout' => 15,
                ])
                ->toArray(false);

            $title = (string) ($data['title'] ?? '');
            $extract = trim((string) ($data['extract'] ?? ''));
            $url = (string) ($data['content_urls']['desktop']['page'] ?? '');

            if ('' !== $title && '' !== $url && mb_strlen($extract) >= self::MIN_EXTRACT_LENGTH) {
                return ['title' => $title, 'extract' => $extract, 'url' => $url];
            }
        }

        throw new \RuntimeException('Could not fetch a usable random Wikipedia article.');
    }
}
