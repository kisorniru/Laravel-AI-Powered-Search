<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $topic = fake()->randomElement($this->topics());

        return [
            'user_id' => User::factory(),
            'title' => $topic['title'],
            'body' => fake()->randomElement($topic['bodies']),
            'is_public' => fake()->boolean(65),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    /**
     * @return array<int, array{title: string, bodies: array<int, string>}>
     */
    private function topics(): array
    {
        return [
            [
                'title' => 'Dhaka traffic diary',
                'bodies' => [
                    'The bus moved slowly from Mirpur to Farmgate because of office hour traffic. I listened to a podcast and watched street vendors selling water and peanuts.',
                    'A sudden rain made the road near Mohakhali even slower. Many people left the bus and started walking toward the next intersection.',
                    'The ride sharing bike was faster than the CNG today, but the driver had to avoid several waterlogged roads near Karwan Bazar.',
                ],
            ],
            [
                'title' => 'Kacha bazaar shopping',
                'bodies' => [
                    'Bought rice, lentils, tomatoes, green chili, and fresh coriander from the morning bazaar. The vegetable seller said prices may rise before Friday.',
                    'The fish market had rui, katla, and shrimp. I compared prices at three stalls before buying fish for lunch.',
                    'The bazaar was crowded after Jummah prayer. I carried two bags of vegetables and remembered to buy mustard oil on the way home.',
                ],
            ],
            [
                'title' => 'Tea stall conversation',
                'bodies' => [
                    'At the tong shop, people discussed cricket, politics, and rising grocery prices while drinking milk tea in small glass cups.',
                    'The tea stall near the office was full during lunch break. Someone ordered singara, and everyone argued about the best biryani place nearby.',
                    'Evening tea felt peaceful after a long day. The shopkeeper played old Bangla songs and served hot biscuits with tea.',
                ],
            ],
            [
                'title' => 'Family errand note',
                'bodies' => [
                    'Need to send money home before Thursday so the electricity bill can be paid on time. Also call Ammu in the evening.',
                    'Buy medicine for Abbu from the pharmacy and check the expiry date carefully. Keep the receipt in the drawer.',
                    'Plan a family visit next month and book train tickets early before the weekend rush starts.',
                ],
            ],
            [
                'title' => 'Weekend travel plan',
                'bodies' => [
                    'Thinking about visiting Sylhet with friends. Need to check weather, hotel price, and whether Jaflong will be too crowded.',
                    'A short Coxs Bazar trip may be possible if bus tickets and hotel rooms are affordable. Keep some emergency cash for the beach trip.',
                    'Padma Bridge road trip sounds fun with cousins. We can stop for tea and take pictures near the river side.',
                ],
            ],
            [
                'title' => 'Study routine',
                'bodies' => [
                    'Revise Laravel routing, migrations, factories, and database indexes after dinner. Practice one feature before watching cricket highlights.',
                    'Read about pgvector operators and write notes about cosine, Euclidean, and inner product metrics.',
                    'Practice authentication middleware and policies. Make a small checklist before sleeping.',
                ],
            ],
            [
                'title' => 'Food memory',
                'bodies' => [
                    'Lunch was khichuri with beef from a small restaurant near Karwan Bazar. It was crowded but the food was warm.',
                    'A winter morning vendor was selling bhapa pitha with coconut and molasses. It reminded me of village mornings.',
                    'Had fuchka near Dhanmondi Lake with friends. The tamarind water was spicy and everyone wanted an extra plate.',
                ],
            ],
            [
                'title' => 'Office work reminder',
                'bodies' => [
                    'Prepare the client report before the morning standup. Keep the summary short and add the updated timeline.',
                    'Finish the API bug fix before lunch, then review the pull request and reply to the client message.',
                    'Keep the meeting notes ready and explain the database indexing decision with a small example.',
                ],
            ],
            [
                'title' => 'Market price observation',
                'bodies' => [
                    'Onion, egg, and chicken prices were higher this week. Many shoppers were bargaining hard at the local bazaar.',
                    'The grocery shop owner said imported items are becoming expensive. I bought only the essentials and skipped snacks.',
                    'Fish prices looked better early in the morning. By afternoon, most fresh items were already sold.',
                ],
            ],
            [
                'title' => 'Local sports evening',
                'bodies' => [
                    'The neighborhood boys played football on the school field. Everyone argued about one offside decision but laughed afterward.',
                    'People at the tea stall cheered during the Bangladesh cricket match. A boundary made the whole shop noisy.',
                    'A small badminton match happened on the lane after dinner. The children kept score on a piece of paper.',
                ],
            ],
        ];
    }
}
