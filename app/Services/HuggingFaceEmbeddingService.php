<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HuggingFaceEmbeddingService
{
    public function configured(): bool
    {
        return filled(config('services.huggingface.token'));
    }

    public function embed(string $text): array
    {
        $response = $this->client()->post($this->endpoint(), [
            'inputs' => $text,
            'options' => [
                'wait_for_model' => true,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Hugging Face embedding request failed: '.$response->body());
        }

        return $this->normalizeEmbedding($response->json());
    }

    /**
     * Embed several texts in one HTTP request. Hugging Face accepts string arrays
     * for feature extraction, which keeps benchmark seeding practical.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = $this->client()->post($this->endpoint(), [
            'inputs' => array_values($texts),
            'options' => [
                'wait_for_model' => true,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Hugging Face batch embedding request failed: '.$response->body());
        }

        $payload = $response->json();

        if (count($texts) === 1) {
            return [$this->normalizeEmbedding($payload)];
        }

        if (! is_array($payload) || count($payload) !== count($texts)) {
            throw new RuntimeException('Hugging Face batch response did not match the number of input texts.');
        }

        return array_map(
            fn (mixed $embedding): array => $this->normalizeEmbedding($embedding),
            $payload,
        );
    }

    private function client(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('HUGGINGFACE_API_TOKEN is not configured.');
        }

        return Http::withToken(config('services.huggingface.token'))
            ->acceptJson()
            ->asJson()
            ->timeout(90);
    }

    private function endpoint(): string
    {
        $model = config('services.huggingface.embedding_model');

        return 'https://router.huggingface.co/hf-inference/models/'.$model.'/pipeline/feature-extraction';
    }

    private function normalizeEmbedding(mixed $payload): array
    {
        if ($this->isFlatVector($payload)) {
            return $this->validateDimensions($payload);
        }

        if (is_array($payload) && isset($payload[0]) && $this->isFlatVector($payload[0])) {
            return $this->meanPool($payload);
        }

        if (is_array($payload) && isset($payload[0][0]) && $this->isFlatVector($payload[0][0])) {
            return $this->meanPool($payload[0]);
        }

        throw new RuntimeException('Hugging Face response did not contain an embedding vector.');
    }

    private function isFlatVector(mixed $value): bool
    {
        return is_array($value)
            && $value !== []
            && is_numeric($value[0] ?? null);
    }

    private function meanPool(array $vectors): array
    {
        $count = count($vectors);
        $dimensions = count($vectors[0]);
        $pooled = array_fill(0, $dimensions, 0.0);

        foreach ($vectors as $vector) {
            foreach ($vector as $index => $value) {
                $pooled[$index] += (float) $value;
            }
        }

        return $this->validateDimensions(array_map(
            fn (float $value): float => $value / $count,
            $pooled,
        ));
    }

    private function validateDimensions(array $embedding): array
    {
        $expected = (int) config('services.huggingface.embedding_dimensions', 384);

        if (count($embedding) !== $expected) {
            throw new RuntimeException("Expected {$expected} embedding dimensions, received ".count($embedding).'.');
        }

        return array_map(fn ($value): float => (float) $value, $embedding);
    }
}
