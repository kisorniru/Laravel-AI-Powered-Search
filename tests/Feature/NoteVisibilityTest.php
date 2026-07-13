<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'laravel_ai_powered_search',
            'services.huggingface.token' => null,
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    public function test_a_guest_cannot_create_a_note(): void
    {
        $this->get('/notes/create')->assertRedirect('/login');

        $this->post('/notes', [
            'title' => 'Guest note',
            'body' => 'This must not be stored.',
            'visibility' => 'public',
        ])->assertRedirect('/login');

        $this->assertDatabaseMissing('notes', ['title' => 'Guest note']);
    }

    public function test_a_registered_user_can_choose_private_visibility(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notes', [
            'title' => 'My private note',
            'body' => 'Only I should see this.',
            'visibility' => 'private',
        ])->assertRedirect('/notes');

        $this->assertDatabaseHas('notes', [
            'title' => 'My private note',
            'user_id' => $user->id,
            'is_public' => false,
        ]);
    }

    public function test_guest_and_user_note_lists_follow_their_search_scope(): void
    {
        $owner = User::factory()->create(['name' => 'Public Author']);
        $otherUser = User::factory()->create(['name' => 'Other User']);

        $publicNote = $owner->notes()->create([
            'title' => 'Visible public note',
            'body' => 'Everyone may read this.',
            'is_public' => true,
        ]);

        $privateNote = $owner->notes()->create([
            'title' => 'Hidden private note',
            'body' => 'Only the owner may read this.',
            'is_public' => false,
        ]);

        $this->get('/notes')
            ->assertSee($publicNote->title)
            ->assertSee('By Public Author')
            ->assertDontSee($privateNote->title);

        $this->actingAs($otherUser)->get('/notes')
            ->assertDontSee($publicNote->title)
            ->assertDontSee($privateNote->title);

        $this->actingAs($owner)->get('/notes')
            ->assertSee($publicNote->title)
            ->assertSee($privateNote->title);

        $this->get("/notes/{$privateNote->id}")->assertOk();
        $this->actingAs($otherUser)->get("/notes/{$privateNote->id}")->assertNotFound();
        $this->get("/notes/{$publicNote->id}")->assertOk();
    }

    public function test_keyword_search_uses_public_notes_for_guests_and_owned_notes_for_users(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $publicNote = $owner->notes()->create([
            'title' => 'Public astronomy research',
            'body' => 'Public telescope observations.',
            'is_public' => true,
        ]);
        $privateNote = $owner->notes()->create([
            'title' => 'Secret astronomy research',
            'body' => 'Private telescope observations.',
            'is_public' => false,
        ]);
        $otherUsersNote = $otherUser->notes()->create([
            'title' => 'Personal astronomy journal',
            'body' => 'This belongs to the other user.',
            'is_public' => false,
        ]);

        $this->get('/notes?search=astronomy')
            ->assertSee($publicNote->title)
            ->assertDontSee($privateNote->title)
            ->assertDontSee($otherUsersNote->title);

        $this->actingAs($otherUser)
            ->get('/notes?search=astronomy')
            ->assertSee($otherUsersNote->title)
            ->assertDontSee($publicNote->title)
            ->assertDontSee($privateNote->title);

        $this->actingAs($owner)
            ->get('/notes?search=astronomy')
            ->assertSee($publicNote->title)
            ->assertSee($privateNote->title)
            ->assertDontSee($otherUsersNote->title);
    }

    public function test_another_user_cannot_edit_or_delete_a_note(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $note = $owner->notes()->create([
            'title' => 'Owned note',
            'body' => 'Only its owner can change it.',
            'is_public' => true,
        ]);

        $this->actingAs($otherUser)->put("/notes/{$note->id}", [
            'title' => 'Changed by someone else',
            'body' => 'This update must fail.',
            'visibility' => 'private',
        ])->assertForbidden();

        $this->actingAs($otherUser)
            ->delete("/notes/{$note->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Owned note',
            'is_public' => true,
        ]);
    }

    public function test_updating_a_private_note_does_not_make_it_public_accidentally(): void
    {
        $owner = User::factory()->create();
        $note = $owner->notes()->create([
            'title' => 'Private draft',
            'body' => 'Original private content.',
            'is_public' => false,
        ]);

        $this->actingAs($owner)->put("/notes/{$note->id}", [
            'title' => 'Updated private draft',
            'body' => 'Updated private content.',
        ])->assertRedirect("/notes/{$note->id}");

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated private draft',
            'is_public' => false,
        ]);
    }

    public function test_ai_search_uses_public_notes_for_guests_and_owned_notes_for_users(): void
    {
        $owner = User::factory()->create(['name' => 'Vector Owner']);
        $otherUser = User::factory()->create(['name' => 'Other Vector User']);

        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbedding([1.0, 0.0, 0.0]), 200),
        ]);
        config(['services.huggingface.token' => 'fake-token']);

        $privateNote = $owner->notes()->create([
            'title' => 'Private vector result',
            'body' => 'This is the closest vector.',
            'is_public' => false,
        ]);
        $publicNote = $owner->notes()->create([
            'title' => 'Public vector result',
            'body' => 'This result is public.',
            'is_public' => true,
        ]);
        $otherUsersNote = $otherUser->notes()->create([
            'title' => 'Other user vector result',
            'body' => 'This belongs to the logged-in user.',
            'is_public' => true,
        ]);

        $this->storeEmbedding($privateNote, [1.0, 0.0, 0.0]);
        $this->storeEmbedding($publicNote, [0.9, 0.1, 0.0]);
        $this->storeEmbedding($otherUsersNote, [0.8, 0.2, 0.0]);

        $this->get('/notes/ai-search?search=vector&strategy=exact&metric=cosine')
            ->assertSee($publicNote->title)
            ->assertSee($otherUsersNote->title)
            ->assertSee('By Vector Owner')
            ->assertDontSee($privateNote->title);

        $this->actingAs($otherUser)
            ->get('/notes/ai-search?search=vector&strategy=exact&metric=cosine')
            ->assertSee($otherUsersNote->title)
            ->assertDontSee($publicNote->title)
            ->assertDontSee($privateNote->title);

        $this->actingAs($owner)
            ->get('/notes/ai-search?search=vector&strategy=exact&metric=cosine')
            ->assertSee($publicNote->title)
            ->assertSee($privateNote->title)
            ->assertDontSee($otherUsersNote->title);
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
