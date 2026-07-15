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

    public function test_cosine_vector_search_filters_weak_matches_by_distance_threshold(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $strong = Note::create([
            'title' => 'Strong cosine threshold note',
            'body' => 'This should stay because it is close to the query vector.',
        ]);

        $weak = Note::create([
            'title' => 'Weak cosine threshold note',
            'body' => 'This would be second without the distance threshold.',
        ]);

        $this->storeEmbedding($strong, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($weak, [0.0, 1.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_hnsw&metric=cosine')
            ->assertOk()
            ->assertSee('Cosine distance &lt;= 0.85', false)
            ->assertSee('Strong cosine threshold note')
            ->assertDontSee('Weak cosine threshold note');
    }

    public function test_ai_search_uses_ann_hnsw_euclidean_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([0.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Nearest HNSW euclidean note',
            'body' => 'This should rank first through the HNSW Euclidean path.',
        ]);

        $second = Note::create([
            'title' => 'Second HNSW euclidean note',
            'body' => 'This should rank second through the HNSW Euclidean path.',
        ]);

        $third = Note::create([
            'title' => 'Far HNSW euclidean note',
            'body' => 'This should not appear because ANN HNSW Euclidean search returns only two.',
        ]);

        $this->storeEmbedding($closest, [0.1, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.3, 0.0, 0.0]);
        $this->storeEmbedding($third, [0.9, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_hnsw&metric=euclidean')
            ->assertOk()
            ->assertSee('ANN / HNSW + Euclidean vector search')
            ->assertSee('HNSW index')
            ->assertSee('vector_l2_ops')
            ->assertSee('Nearest HNSW euclidean note')
            ->assertSee('Second HNSW euclidean note')
            ->assertDontSee('Far HNSW euclidean note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_ann_hnsw_inner_product_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $strongest = Note::create([
            'title' => 'Strongest HNSW inner product note',
            'body' => 'This should rank first through the HNSW Inner Product path.',
        ]);

        $second = Note::create([
            'title' => 'Second HNSW inner product note',
            'body' => 'This should rank second through the HNSW Inner Product path.',
        ]);

        $weakest = Note::create([
            'title' => 'Weak HNSW inner product note',
            'body' => 'This should not appear because ANN HNSW Inner Product search returns only two.',
        ]);

        $this->storeEmbedding($strongest, [0.9, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.5, 0.0, 0.0]);
        $this->storeEmbedding($weakest, [0.1, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_hnsw&metric=inner_product')
            ->assertOk()
            ->assertSee('ANN / HNSW + Inner Product vector search')
            ->assertSee('HNSW index')
            ->assertSee('vector_ip_ops')
            ->assertSee('Strongest HNSW inner product note')
            ->assertSee('Second HNSW inner product note')
            ->assertDontSee('Weak HNSW inner product note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_ann_ivfflat_cosine_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Closest IVFFlat cosine note',
            'body' => 'This should rank first through the IVFFlat cosine path.',
        ]);

        $second = Note::create([
            'title' => 'Second IVFFlat cosine note',
            'body' => 'This should rank second through the IVFFlat cosine path.',
        ]);

        $third = Note::create([
            'title' => 'Far IVFFlat cosine note',
            'body' => 'This should not appear because ANN IVFFlat Cosine search returns only two.',
        ]);

        $this->storeEmbedding($closest, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.8, 0.2, 0.0]);
        $this->storeEmbedding($third, [0.0, 1.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_ivfflat&metric=cosine')
            ->assertOk()
            ->assertSee('ANN / IVFFlat + Cosine vector search')
            ->assertSee('IVFFlat index')
            ->assertSee('vector_cosine_ops')
            ->assertSee('lists = 10')
            ->assertSee('Closest IVFFlat cosine note')
            ->assertSee('Second IVFFlat cosine note')
            ->assertDontSee('Far IVFFlat cosine note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_ann_ivfflat_euclidean_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([0.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Nearest IVFFlat euclidean note',
            'body' => 'This should rank first through the IVFFlat Euclidean path.',
        ]);

        $second = Note::create([
            'title' => 'Second IVFFlat euclidean note',
            'body' => 'This should rank second through the IVFFlat Euclidean path.',
        ]);

        $third = Note::create([
            'title' => 'Far IVFFlat euclidean note',
            'body' => 'This should not appear because ANN IVFFlat Euclidean search returns only two.',
        ]);

        $this->storeEmbedding($closest, [0.1, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.3, 0.0, 0.0]);
        $this->storeEmbedding($third, [0.9, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_ivfflat&metric=euclidean')
            ->assertOk()
            ->assertSee('ANN / IVFFlat + Euclidean vector search')
            ->assertSee('IVFFlat index')
            ->assertSee('vector_l2_ops')
            ->assertSee('lists = 10')
            ->assertSee('Nearest IVFFlat euclidean note')
            ->assertSee('Second IVFFlat euclidean note')
            ->assertDontSee('Far IVFFlat euclidean note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_ai_search_uses_ann_ivfflat_inner_product_vector_search_and_returns_the_best_two(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $strongest = Note::create([
            'title' => 'Strongest IVFFlat inner product note',
            'body' => 'This should rank first through the IVFFlat Inner Product path.',
        ]);

        $second = Note::create([
            'title' => 'Second IVFFlat inner product note',
            'body' => 'This should rank second through the IVFFlat Inner Product path.',
        ]);

        $weakest = Note::create([
            'title' => 'Weak IVFFlat inner product note',
            'body' => 'This should not appear because ANN IVFFlat Inner Product search returns only two.',
        ]);

        $this->storeEmbedding($strongest, [0.9, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.5, 0.0, 0.0]);
        $this->storeEmbedding($weakest, [0.1, 0.0, 0.0]);

        $this->get('/notes/ai-search?search=database&strategy=ann_ivfflat&metric=inner_product')
            ->assertOk()
            ->assertSee('ANN / IVFFlat + Inner Product vector search')
            ->assertSee('IVFFlat index')
            ->assertSee('vector_ip_ops')
            ->assertSee('lists = 10')
            ->assertSee('Strongest IVFFlat inner product note')
            ->assertSee('Second IVFFlat inner product note')
            ->assertDontSee('Weak IVFFlat inner product note');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_an_ai_search_query_can_be_inspected_with_explain_analyze(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $note = Note::create([
            'title' => 'Explain vector search',
            'body' => 'Inspect how PostgreSQL executes a cosine vector query.',
            'is_public' => true,
        ]);

        $this->storeEmbedding($note, [1.0, 0.0, 0.0]);

        $this->get('/notes/ai-search/explain?search=database&strategy=ann_hnsw&metric=cosine')
            ->assertOk()
            ->assertSee('EXPLAIN ANALYZE result')
            ->assertSee('Planning time')
            ->assertSee('Execution time')
            ->assertSee('Scan types')
            ->assertSee('Indexes used')
            ->assertSee('View sanitized raw query plan')
            ->assertDontSee('[1,0,0,0,0,0');

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer fake-token')
            && $request['inputs'] === 'database');
    }

    public function test_vector_search_strategies_can_be_compared_with_one_query_embedding(): void
    {
        config(['services.huggingface.token' => 'fake-token']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);

        $closest = Note::create([
            'title' => 'Closest comparison note',
            'body' => 'The first result shared by the comparison experiments.',
            'is_public' => true,
        ]);

        $second = Note::create([
            'title' => 'Second comparison note',
            'body' => 'The second result shared by the comparison experiments.',
            'is_public' => true,
        ]);

        $this->storeEmbedding($closest, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($second, [0.8, 0.2, 0.0]);

        $this->get('/notes/ai-search/compare?search=database&metric=cosine')
            ->assertOk()
            ->assertSee('Strategy comparison with Cosine')
            ->assertSee('Exact')
            ->assertSee('ANN / HNSW')
            ->assertSee('ANN / IVFFlat')
            ->assertSee('Actual PostgreSQL plan')
            ->assertSee('Closest comparison note')
            ->assertSee('Second comparison note')
            ->assertSee('Timings are educational samples')
            ->assertDontSee('[1,0,0,0,0,0');

        Http::assertSentCount(1);
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
