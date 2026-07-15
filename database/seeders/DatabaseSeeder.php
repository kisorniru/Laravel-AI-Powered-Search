<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\User;
use App\Services\HuggingFaceEmbeddingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $john = User::query()->create([
            'name' => 'Mr. Jhon',
            'email' => 'jhon@email.com',
            'password' => '12345678',
        ]);

        $sina = User::query()->create([
            'name' => 'Mr. Sina',
            'email' => 'sina@email.com',
            'password' => '12345678',
        ]);

        $notes = collect([
            ...$this->seedNotesFor($john, [
                [
                    'title' => 'Morning tea at Tong',
                    'body' => 'Before office I stopped at the local tong shop in Mirpur for milk tea and a small paratha. The road was busy, but the tea helped me start the day slowly.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Bazaar list for Friday',
                    'body' => 'Need to buy rice, dal, eggplant, green chili, coriander, and hilsa if the price is reasonable. Also remember to bargain at the kacha bazaar.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Private mobile banking note',
                    'body' => 'Check bKash and Nagad balance tonight. Send money to the village before Thursday so Ammu can pay the electricity bill on time.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private office reminder',
                    'body' => 'Prepare the client report before the Dhaka traffic gets too heavy tomorrow. Leave home early because the bus from Mirpur to Motijheel is unpredictable.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Launch break khichuri',
                    'body' => 'At lunch I ate beef khichuri from a small restaurant near Karwan Bazar. The place was crowded with office workers, but the food was warm and filling.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Metro rail commute',
                    'body' => 'The metro rail from Agargaon saved a lot of time today. It felt calm compared with the regular bus route through Farmgate traffic.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Cricket match at tea stall',
                    'body' => 'Everyone at the tea stall was watching Bangladesh bat in the evening match. People cheered loudly whenever a boundary came.',
                    'is_public' => true,
                ],
                [
                    'title' => 'CNG fare discussion',
                    'body' => 'The CNG driver asked for a high fare from Dhanmondi to Gulshan. I decided to use a ride sharing bike instead because the traffic was heavy.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Book fair evening',
                    'body' => 'Visited the Ekushey book fair after work. I bought a small poetry book and walked around the stalls near Bangla Academy.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Village phone call',
                    'body' => 'Called my cousin in Bogura to ask about the aman rice harvest. He said the weather has been good and the family is planning a small picnic.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Street fuchka plan',
                    'body' => 'Planning to meet friends near Dhanmondi Lake for fuchka and lemon tea. We should go before the evening crowd gets too large.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Electricity load shedding note',
                    'body' => 'There was load shedding in the evening, so I charged the phone and laptop early. Need to keep the IPS battery checked before summer.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Private rent reminder',
                    'body' => 'Pay the house rent by the fifth day of the month. Keep the receipt photo in Google Drive and update the monthly budget sheet.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private doctor appointment',
                    'body' => 'Book an appointment at the clinic in Uttara for Saturday morning. Carry the old prescription and recent blood test report.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private salary plan',
                    'body' => 'After salary comes, save part of it, pay credit card bill, and keep some cash for family shopping before Eid.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private learning goal',
                    'body' => 'Practice Laravel policies, database indexes, and pgvector search after office. Avoid scrolling social media before finishing one lesson.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private gift idea',
                    'body' => 'Buy a small watch for my younger brother after his exam result. Check Bashundhara City prices before ordering online.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private travel budget',
                    'body' => 'Calculate the cost for a Coxs Bazar trip: bus ticket, hotel, food, beach transport, and emergency cash.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private meeting preparation',
                    'body' => 'Review the project notes before tomorrow morning meeting. Keep the slides short and prepare answers about timeline and cost.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private family medicine list',
                    'body' => 'Buy diabetes medicine for Abbu and vitamins for Ammu from the pharmacy. Check the expiry dates carefully.',
                    'is_public' => false,
                ],
            ]),
            ...$this->seedNotesFor($sina, [
                [
                    'title' => 'Rainy evening in Chittagong',
                    'body' => 'Heavy rain started after Maghrib. The street food stalls near GEC were still open, and people were eating fuchka under small umbrellas.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Weekend plan in Sylhet',
                    'body' => 'Thinking about visiting Ratargul or Jaflong with friends. Need to check weather, road condition, and whether the tea garden area is too crowded.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Private family shopping',
                    'body' => 'Buy a panjabi for Abbu and a saree for Ammu from New Market before Eid. Keep the budget under control and avoid the evening crowd.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private study routine',
                    'body' => 'Revise Laravel routing, migrations, and vector search after dinner. Spend at least one hour practicing before watching cricket highlights.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Breakfast with ruti and bhaji',
                    'body' => 'Started the morning with ruti, mixed bhaji, and tea from a nearby hotel. The owner was talking about rising vegetable prices.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Padma Bridge road trip',
                    'body' => 'Thinking about a road trip through Padma Bridge with cousins. We want to stop for tea and take photos near the river side.',
                    'is_public' => true,
                ],
                [
                    'title' => 'University adda',
                    'body' => 'Met old university friends near TSC. We talked about jobs, family, football, and the old days on campus.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Fish market morning',
                    'body' => 'Went to the fish market early and found fresh rui, pangash, and shrimp. The seller said prices may rise before the weekend.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Bus delay at Mohakhali',
                    'body' => 'The bus was delayed at Mohakhali because of road work and rain. I used the time to read a short article on my phone.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Evening walk by the lake',
                    'body' => 'Walked beside the lake after Asr prayer. Families were sitting near the water and children were flying small kites.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Pitha in winter morning',
                    'body' => 'A street vendor was selling bhapa pitha with coconut and molasses. It reminded me of winter mornings in the village.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Local football match',
                    'body' => 'The neighborhood boys played football on the school field. The match was friendly, but everyone argued about one offside decision.',
                    'is_public' => true,
                ],
                [
                    'title' => 'Private tuition schedule',
                    'body' => 'Update the tuition schedule for the two students in Banani. One class should move to Friday because of exam preparation.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private savings target',
                    'body' => 'Try to save extra money this month by reducing restaurant meals and unnecessary online shopping.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private health reminder',
                    'body' => 'Drink more water, walk for twenty minutes, and avoid too much oily food from the roadside stalls.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private cousin wedding task',
                    'body' => 'Call the decorator, confirm the community center booking, and collect the invitation cards before next week.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private exam preparation',
                    'body' => 'Revise database indexing, Laravel validation, and authentication middleware. Make handwritten notes before sleeping.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private courier tracking',
                    'body' => 'Track the parcel from Chittagong and call the courier office if it does not arrive by Thursday.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private parents visit',
                    'body' => 'Plan a visit to parents next month. Buy mangoes, biscuits, and medicine before taking the train.',
                    'is_public' => false,
                ],
                [
                    'title' => 'Private work focus',
                    'body' => 'Finish the API bug fix before lunch, then review the pull request and reply to client messages.',
                    'is_public' => false,
                ],
            ]),
        ]);

        $factoryNotes = $this->seedFactoryNotes([$john, $sina]);
        $notesToEmbed = $this->shouldEmbedFactoryNotes()
            ? $notes->merge($factoryNotes)
            : $notes;

        $this->command?->info('Seeded '.$notes->count().' curated notes.');

        if ($factoryNotes->isNotEmpty()) {
            $this->command?->info('Seeded '.$factoryNotes->count().' factory notes.');

            if (! $this->shouldEmbedFactoryNotes()) {
                $this->command?->warn('Factory notes were not embedded. Set SEED_FACTORY_NOTES_WITH_EMBEDDINGS=true to embed them during seeding.');
            }
        }

        $embeddedCount = $this->embedNotes($notesToEmbed);

        if ($embeddedCount > 0) {
            $this->rebuildVectorIndexes();
        }
    }

    private function seedNotesFor(User $user, array $notes): array
    {
        return array_map(
            fn (array $note): Note => $user->notes()->create($note),
            $notes,
        );
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedFactoryNotes(array $users)
    {
        $countPerUser = max(0, (int) env('SEED_FACTORY_NOTES_PER_USER', 2500));

        if ($countPerUser === 0) {
            return collect();
        }

        return collect($users)->flatMap(
            fn (User $user) => Note::factory()
                ->count($countPerUser)
                ->for($user)
                ->create(),
        );
    }

    private function shouldEmbedFactoryNotes(): bool
    {
        return filter_var(env('SEED_FACTORY_NOTES_WITH_EMBEDDINGS', true), FILTER_VALIDATE_BOOL);
    }

    private function embedNotes(iterable $notes): int
    {
        $embeddings = app(HuggingFaceEmbeddingService::class);

        if (! $embeddings->configured()) {
            $this->command?->warn('Seed notes created without embeddings because HUGGINGFACE_API_TOKEN is not configured.');

            return 0;
        }

        $notes = collect($notes)->each->load('user:id,name');
        $groups = $notes->groupBy(fn (Note $note): string => hash('sha256', $note->textToEmbed()));
        $batchSize = max(1, (int) env('SEED_EMBEDDING_BATCH_SIZE', 16));
        $embeddedCount = 0;

        $this->command?->info(
            'Embedding '.$notes->count().' notes from '.$groups->count().' unique texts in batches of '.$batchSize.'.',
        );

        foreach ($groups->chunk($batchSize) as $groupBatch) {
            try {
                $texts = $groupBatch
                    ->map(fn ($noteGroup): string => $noteGroup->first()->textToEmbed())
                    ->values()
                    ->all();
                $batchEmbeddings = $this->embedSeedBatch($embeddings, $texts);

                foreach ($groupBatch->values() as $index => $noteGroup) {
                    $embeddedCount += $this->storeEmbeddings(
                        $noteGroup,
                        $batchEmbeddings[$index],
                    );
                }

                $this->command?->info('Embedded '.$embeddedCount.' / '.$notes->count().' notes.');
            } catch (Throwable $exception) {
                $this->command?->warn('Could not embed a seed batch: '.$exception->getMessage());
            }
        }

        return $embeddedCount;
    }

    private function embedSeedBatch(HuggingFaceEmbeddingService $embeddings, array $texts): array
    {
        try {
            return $embeddings->embedMany($texts);
        } catch (Throwable $exception) {
            if (count($texts) === 1) {
                throw $exception;
            }

            // Some providers enforce smaller payload limits. Split and retry without losing the seed run.
            $middle = (int) ceil(count($texts) / 2);

            return array_merge(
                $this->embedSeedBatch($embeddings, array_slice($texts, 0, $middle)),
                $this->embedSeedBatch($embeddings, array_slice($texts, $middle)),
            );
        }
    }

    private function storeEmbeddings(Collection $notes, array $embedding): int
    {
        $placeholders = [];
        $bindings = [now()];

        foreach ($notes as $note) {
            $placeholders[] = '(CAST(? AS bigint), CAST(? AS vector))';
            $bindings[] = $note->id;
            $bindings[] = $this->toVectorLiteral($embedding);
        }

        return DB::affectingStatement(
            'UPDATE notes '
            .'SET embedding = seed_vectors.embedding, embedded_at = ? '
            .'FROM (VALUES '.implode(', ', $placeholders).') AS seed_vectors(id, embedding) '
            .'WHERE notes.id = seed_vectors.id',
            $bindings,
        );
    }

    private function rebuildVectorIndexes(): void
    {
        $this->command?->info('Rebuilding vector indexes after bulk embedding.');

        foreach ([
            'notes_embedding_hnsw_cosine_index',
            'notes_embedding_hnsw_euclidean_index',
            'notes_embedding_hnsw_inner_product_index',
            'notes_embedding_ivfflat_cosine_index',
            'notes_embedding_ivfflat_euclidean_index',
            'notes_embedding_ivfflat_inner_product_index',
        ] as $index) {
            DB::statement("REINDEX INDEX {$index}");
        }

        DB::statement('ANALYZE notes');
    }

    private function toVectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(
            fn ($value): string => rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.'),
            $embedding,
        )).']';
    }
}
