<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BenchmarkSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'laravel_ai_powered_search',
            'services.huggingface.token' => 'fake-token',
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        putenv('SEED_FACTORY_NOTES_PER_USER');
        putenv('SEED_FACTORY_NOTES_WITH_EMBEDDINGS');
        putenv('SEED_EMBEDDING_BATCH_SIZE');

        parent::tearDown();
    }

    public function test_factory_notes_are_batch_embedded_and_searchable(): void
    {
        putenv('SEED_FACTORY_NOTES_PER_USER=25');
        putenv('SEED_FACTORY_NOTES_WITH_EMBEDDINGS=true');
        putenv('SEED_EMBEDDING_BATCH_SIZE=16');

        $existingUsers = User::query()
            ->whereIn('email', ['jhon@email.com', 'sina@email.com'])
            ->pluck('id');

        Note::query()->whereIn('user_id', $existingUsers)->delete();
        User::query()->whereIn('id', $existingUsers)->delete();

        Http::fake(function ($request) {
            $texts = $request['inputs'];
            $embedding = array_pad([0.25, 0.5], 384, 0.0);

            return Http::response(array_map(fn (): array => $embedding, $texts));
        });

        (new DatabaseSeeder)->run();

        $seededUserIds = User::query()
            ->whereIn('email', ['jhon@email.com', 'sina@email.com'])
            ->pluck('id');

        $this->assertCount(2, $seededUserIds);
        $this->assertSame(90, Note::query()->whereIn('user_id', $seededUserIds)->count());
        $this->assertSame(90, Note::query()
            ->whereIn('user_id', $seededUserIds)
            ->whereNotNull('embedding')
            ->count());
        $this->assertSame(90, Note::query()
            ->whereIn('user_id', $seededUserIds)
            ->distinct()
            ->count('title'));
        $this->assertSame(90, Note::query()
            ->whereIn('user_id', $seededUserIds)
            ->distinct()
            ->count('body'));
        $this->assertLessThan(90, count(Http::recorded()));
    }
}
