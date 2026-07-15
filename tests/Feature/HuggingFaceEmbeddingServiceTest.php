<?php

namespace Tests\Feature;

use App\Services\HuggingFaceEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HuggingFaceEmbeddingServiceTest extends TestCase
{
    public function test_multiple_texts_can_be_embedded_in_one_request(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        $first = array_pad([1.0, 0.0], 384, 0.0);
        $second = array_pad([0.0, 1.0], 384, 0.0);

        Http::fake([
            'router.huggingface.co/*' => Http::response([$first, $second]),
        ]);

        $embeddings = app(HuggingFaceEmbeddingService::class)->embedMany([
            'Dhaka traffic',
            'Village farming',
        ]);

        $this->assertCount(2, $embeddings);
        $this->assertCount(384, $embeddings[0]);
        $this->assertSame(1.0, $embeddings[0][0]);
        $this->assertSame(1.0, $embeddings[1][1]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['inputs'] === [
            'Dhaka traffic',
            'Village farming',
        ]);
    }
}
