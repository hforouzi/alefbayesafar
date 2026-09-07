<?php

namespace App\Modules\TripPlanner\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Minimal OpenAI Chat Completions wrapper.
 *
 * Deliberately small: one method, JSON-object responses only. Not a generic
 * AI framework. Every caller must supply its own strict system prompt and
 * must fall back to a deterministic implementation when this throws.
 */
final readonly class OpenAiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.openai.com',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->apiKey) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function chatJson(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = trim((string) $this->apiKey);
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
                'timeout' => 15,
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('OpenAI transport failure: ' . $exception->getMessage(), 0, $exception);
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf('OpenAI HTTP %d.', $statusCode));
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('OpenAI returned malformed JSON.', 0, $exception);
        }

        $message = \is_array($decoded) ? ($decoded['choices'][0]['message']['content'] ?? null) : null;
        if (!\is_string($message) || trim($message) === '') {
            throw new \RuntimeException('OpenAI response did not contain a message.');
        }

        try {
            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('OpenAI message content was not valid JSON.', 0, $exception);
        }

        if (!\is_array($payload)) {
            throw new \RuntimeException('OpenAI message content was not a JSON object.');
        }

        return $payload;
    }
}
