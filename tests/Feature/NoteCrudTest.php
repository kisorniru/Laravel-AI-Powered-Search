<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NoteCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'laravel_ai_powered_search',
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_a_note_can_be_created_and_viewed(): void
    {
        config(['services.huggingface.token' => null]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/notes', [
            'title' => 'Vector search basics',
            'body' => 'Store plain text notes in Laravel.',
        ]);

        $note = Note::query()
            ->where('title', 'Vector search basics')
            ->firstOrFail();

        $response->assertRedirect('/notes');
        $this->assertDatabaseHas('notes', [
            'title' => 'Vector search basics',
            'user_id' => $user->id,
            'is_public' => true,
        ]);

        $this->get("/notes/{$note->id}")
            ->assertOk()
            ->assertSee('Vector search basics')
            ->assertSee('Store plain text notes in Laravel.');
    }

    public function test_a_note_can_be_created_without_a_hugging_face_token(): void
    {
        config(['services.huggingface.token' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notes', [
            'title' => 'No token note',
            'body' => 'The note should still be saved.',
        ])->assertRedirect('/notes');

        $this->assertDatabaseHas('notes', [
            'title' => 'No token note',
            'body' => 'The note should still be saved.',
        ]);
    }

    public function test_a_note_embedding_is_stored_after_hugging_face_returns_a_vector(): void
    {
        if (! Schema::hasColumn('notes', 'embedded_at')) {
            $this->markTestSkipped('Run php artisan migrate:fresh after installing pgvector to enable embedding columns.');
        }

        config(['services.huggingface.token' => 'fake-token']);
        $user = User::factory()->create(['name' => 'Embedding Author']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding(), 200),
        ]);

        $this->actingAs($user)->post('/notes', [
            'title' => 'Embedded note',
            'body' => 'This note is converted into a vector.',
        ])->assertRedirect('/notes');

        $note = Note::query()
            ->where('title', 'Embedded note')
            ->firstOrFail();

        $this->assertNotNull($note->embedded_at);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === "Title: Embedded note\n\nBody:\nThis note is converted into a vector.\n\nVisibility: Public\n\nAuthor: Embedding Author");
    }

    public function test_a_note_can_be_updated(): void
    {
        config(['services.huggingface.token' => null]);
        $user = User::factory()->create();

        $note = $user->notes()->create([
            'title' => 'Old title',
            'body' => 'Old body',
        ]);

        $this->actingAs($user)->put("/notes/{$note->id}", [
            'title' => 'Updated title',
            'body' => 'Updated body',
        ])->assertRedirect("/notes/{$note->id}");

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated title',
            'body' => 'Updated body',
        ]);
    }

    public function test_a_note_can_be_deleted(): void
    {
        config(['services.huggingface.token' => null]);
        $user = User::factory()->create();

        $note = $user->notes()->create([
            'title' => 'Temporary note',
            'body' => 'This note will be deleted.',
        ]);

        $this->actingAs($user)->delete("/notes/{$note->id}")
            ->assertRedirect('/notes');

        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
        ]);
    }

    public function test_notes_can_be_searched_by_title_or_body(): void
    {
        config(['services.huggingface.token' => null]);

        Note::create([
            'title' => 'Laravel database search',
            'body' => 'This note explains SQL filtering.',
        ]);

        Note::create([
            'title' => 'Cooking ideas',
            'body' => 'A note about dinner.',
        ]);

        $this->get('/notes?search=database')
            ->assertOk()
            ->assertSee('Laravel database search')
            ->assertDontSee('Cooking ideas');

        $this->get('/notes?search=dinner')
            ->assertOk()
            ->assertSee('Cooking ideas')
            ->assertDontSee('Laravel database search');
    }

    public function test_regular_search_does_not_embed_the_query(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake();

        Note::create([
            'title' => 'Laravel database search',
            'body' => 'This note explains SQL filtering.',
        ]);

        $this->get('/notes?search=database')
            ->assertOk()
            ->assertSee('Laravel database search')
            ->assertDontSee('Query vector prepared');

        Http::assertNothingSent();
    }

    public function test_ai_search_uses_exact_cosine_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Closest vector note',
            'body' => 'This should rank first.',
        ]);

        $second = Note::create([
            'title' => 'Second vector note',
            'body' => 'This should rank second.',
        ]);

        $third = Note::create([
            'title' => 'Third vector note',
            'body' => 'This should not appear because AI search returns only two.',
        ]);

        $this->storeEmbedding($closest, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.8, 0.2, 0.0]);
        $this->storeEmbedding($third, [0.0, 1.0, 0.0]);

        $this->get('/notes/ai-search?search=database')
            ->assertOk()
            ->assertSee('Exact + Cosine vector search')
            ->assertSee('How AI search worked in the background')
            ->assertSee('384-dimensional query vector')
            ->assertSee('Cosine distance')
            ->assertSee('Closest vector note')
            ->assertSee('Second vector note')
            ->assertDontSee('Third vector note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_exact_euclidean_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([0.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Nearest euclidean note',
            'body' => 'This should rank first by straight-line distance.',
        ]);

        $second = Note::create([
            'title' => 'Second euclidean note',
            'body' => 'This should rank second by straight-line distance.',
        ]);

        $third = Note::create([
            'title' => 'Far euclidean note',
            'body' => 'This should not appear because Euclidean search returns only two.',
        ]);

        $this->storeEmbedding($closest, [0.1, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.3, 0.0, 0.0]);
        $this->storeEmbedding($third, [0.9, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=exact&metric=euclidean')
            ->assertOk()
            ->assertSee('Exact + Euclidean vector search')
            ->assertSee('Euclidean distance')
            ->assertSee('Nearest euclidean note')
            ->assertSee('Second euclidean note')
            ->assertDontSee('Far euclidean note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_exact_inner_product_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $strongest = Note::create([
            'title' => 'Strongest inner product note',
            'body' => 'This should rank first by inner product.',
        ]);

        $second = Note::create([
            'title' => 'Second inner product note',
            'body' => 'This should rank second by inner product.',
        ]);

        $weakest = Note::create([
            'title' => 'Weak inner product note',
            'body' => 'This should not appear because Inner Product search returns only two.',
        ]);

        $this->storeEmbedding($strongest, [0.9, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.5, 0.0, 0.0]);
        $this->storeEmbedding($weakest, [0.1, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=exact&metric=inner_product')
            ->assertOk()
            ->assertSee('Exact + Inner Product vector search')
            ->assertSee('negative inner product')
            ->assertSee('Strongest inner product note')
            ->assertSee('Second inner product note')
            ->assertDontSee('Weak inner product note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_ann_hnsw_cosine_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Closest HNSW cosine note',
            'body' => 'This should rank first through the HNSW cosine path.',
        ]);

        $second = Note::create([
            'title' => 'Second HNSW cosine note',
            'body' => 'This should rank second through the HNSW cosine path.',
        ]);

        $third = Note::create([
            'title' => 'Far HNSW cosine note',
            'body' => 'This should not appear because ANN HNSW search returns only two.',
        ]);

        $this->storeEmbedding($closest, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.8, 0.2, 0.0]);
        $this->storeEmbedding($third, [0.0, 1.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_hnsw&metric=cosine')
            ->assertOk()
            ->assertSee('ANN / HNSW + Cosine vector search')
            ->assertSee('HNSW index')
            ->assertSee('vector_cosine_ops')
            ->assertSee('Closest HNSW cosine note')
            ->assertSee('Second HNSW cosine note')
            ->assertDontSee('Far HNSW cosine note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    private function fakeEmbedding(array $start = []): array
    {
        return array_pad($start, 384, 0.0);
    }

    private function storeEmbedding(Note $note, array $start): void
    {
        DB::table('notes')
            ->where('id', $note->id)
            ->update([
                'embedding' => DB::raw("'".$this->vectorLiteral($this->fakeEmbedding($start))."'::vector"),
                'embedded_at' => now(),
            ]);
    }

    private function vectorLiteral(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}
