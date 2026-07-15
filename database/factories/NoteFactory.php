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
        $reference = fake()->unique()->numberBetween(10000000, 99999999);
        $location = fake()->randomElement($this->locations());
        $recordedAt = fake()->dateTimeBetween('-3 years', 'now');
        $sentences = collect($topic['bodies'])
            ->flatMap(fn (string $body): array => preg_split('/(?<=[.!?])\s+/', $body) ?: [])
            ->filter()
            ->unique()
            ->shuffle()
            ->take(fake()->numberBetween(2, 4))
            ->values();

        $sentences->push(fake()->randomElement($this->contextSentences())([
            'location' => $location,
            'date' => $recordedAt->format('d F Y'),
            'time' => $recordedAt->format('g:i A'),
            'companion' => fake()->randomElement($this->companions()),
            'weather' => fake()->randomElement($this->weatherConditions()),
            'transport' => fake()->randomElement($this->transportOptions()),
            'amount' => fake()->numberBetween(80, 15000),
        ]));
        $sentences->push(fake()->randomElement($this->followUpSentences()));
        $sentences->push("Diary reference BD-{$reference} was recorded for this specific event.");

        return [
            'user_id' => User::factory(),
            'title' => "{$topic['title']} - {$location} #BD-{$reference}",
            'body' => $sentences->implode(' '),
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
            [
                'title' => 'Mobile banking and bills',
                'bodies' => [
                    'Paid the electricity bill through bKash and saved the transaction number. The internet bill is still due next week.',
                    'Sent money to the village using Nagad before calling home. Ammu confirmed that the transfer arrived safely.',
                    'The mobile recharge offer was useful, but I need to review the monthly spending before making another payment.',
                ],
            ],
            [
                'title' => 'Doctor and pharmacy visit',
                'bodies' => [
                    'Visited the local clinic for a regular checkup and carried the previous prescription. The doctor advised more walking and water.',
                    'The pharmacy had the blood pressure medicine, but one brand was unavailable. I checked the dosage before buying an alternative.',
                    'Booked a diagnostic test for Saturday morning and kept the receipt with the medical reports.',
                ],
            ],
            [
                'title' => 'School and exam preparation',
                'bodies' => [
                    'The student revised mathematics and English before the school exam. A short routine made the evening study easier.',
                    'Collected the admission form and checked the required photographs, certificates, and application deadline.',
                    'The coaching class moved to Friday because the model test starts next week. Everyone received a new practice sheet.',
                ],
            ],
            [
                'title' => 'Rain and flood update',
                'bodies' => [
                    'Continuous rain caused waterlogging on several Dhaka roads. Commuters checked traffic updates before leaving home.',
                    'The river water rose near the village after days of rain. The family moved important documents and dry food upstairs.',
                    'A weather warning mentioned heavy rain in Chittagong and Sylhet. The weekend travel plan may need to change.',
                ],
            ],
            [
                'title' => 'Village farming news',
                'bodies' => [
                    'Farmers prepared the field for aman rice after the rain. Fertilizer price and irrigation cost were the main concerns.',
                    'The vegetable garden produced eggplant, chili, and bottle gourd. Some vegetables will be sent to relatives in town.',
                    'Harvest workers started early before the midday heat. The family plans to store part of the rice for the year.',
                ],
            ],
            [
                'title' => 'Freelancing work log',
                'bodies' => [
                    'Finished a small website update for an overseas client and submitted the work before midnight.',
                    'The internet connection dropped during a video meeting, so I sent the project update and screenshots by email.',
                    'Practiced Laravel and JavaScript after completing client revisions. The next goal is improving database query performance.',
                ],
            ],
            [
                'title' => 'House rent and utilities',
                'bodies' => [
                    'Paid the monthly house rent and asked the landlord for a receipt. The water bill will be shared with the other flat.',
                    'The electrician checked the ceiling fan and replaced a damaged switch. I added the repair cost to the household budget.',
                    'Looked at a small apartment near the metro station, but the advance payment and service charge were too high.',
                ],
            ],
            [
                'title' => 'Online order and courier',
                'bodies' => [
                    'Tracked an online book order that reached the local courier hub. Delivery should happen before the weekend.',
                    'The clothing parcel had the wrong size, so I contacted customer support and requested an exchange.',
                    'Sent homemade food to a relative through a same-day delivery service and shared the rider number.',
                ],
            ],
            [
                'title' => 'Community and mosque activity',
                'bodies' => [
                    'Neighbors discussed cleaning the lane and repairing the streetlight after the evening prayer.',
                    'The mosque committee collected donations for families affected by flooding and prepared food packages.',
                    'A community meeting planned security during the Eid holiday when many residents will travel home.',
                ],
            ],
            [
                'title' => 'Garment factory commute',
                'bodies' => [
                    'Workers waited for the factory bus early in the morning near Savar. Traffic increased around the industrial area.',
                    'The production team discussed an urgent shipment and checked the quality report before packing garments.',
                    'Heavy rain delayed the return trip from Gazipur, and many buses were crowded after the factory shift ended.',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function locations(): array
    {
        return [
            'Mirpur', 'Uttara', 'Dhanmondi', 'Mohakhali', 'Motijheel', 'Farmgate',
            'Gulshan', 'Banani', 'Old Dhaka', 'Savar', 'Gazipur', 'Narayanganj',
            'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal', 'Rangpur',
            'Mymensingh', 'Cumilla', 'Bogura', 'Coxs Bazar', 'Jashore', 'Noakhali',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function companions(): array
    {
        return [
            'a family member', 'an old friend', 'a colleague', 'a neighbor',
            'a cousin', 'a local shopkeeper', 'a classmate', 'the building caretaker',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function weatherConditions(): array
    {
        return [
            'clear and warm', 'humid with light rain', 'cloudy', 'windy after rain',
            'very hot', 'cool and comfortable', 'foggy in the morning', 'stormy',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function transportOptions(): array
    {
        return [
            'a local bus', 'the metro rail', 'a CNG', 'a rickshaw',
            'a ride-sharing bike', 'a train', 'a launch', 'walking',
        ];
    }

    /**
     * @return array<int, callable(array{location: string, date: string, time: string, companion: string, weather: string, transport: string, amount: int}): string>
     */
    private function contextSentences(): array
    {
        return [
            fn (array $context): string => "This happened in {$context['location']} on {$context['date']} at {$context['time']} while the weather was {$context['weather']}.",
            fn (array $context): string => "I discussed the details with {$context['companion']} and estimated the related cost at Tk {$context['amount']}.",
            fn (array $context): string => "Travel through {$context['location']} was by {$context['transport']}, and the conditions were {$context['weather']}.",
            fn (array $context): string => "On {$context['date']}, {$context['companion']} helped confirm the plan before the {$context['time']} deadline.",
            fn (array $context): string => "The activity in {$context['location']} cost about Tk {$context['amount']} and required travel by {$context['transport']}.",
            fn (array $context): string => "The {$context['weather']} weather changed the original plan, so I coordinated with {$context['companion']}.",
        ];
    }

    /**
     * @return array<int, string>
     */
    private function followUpSentences(): array
    {
        return [
            'The next step is to confirm the schedule and keep the necessary phone numbers ready.',
            'I should compare the available options again before making the final decision.',
            'A receipt and a short written record should be kept for the monthly budget.',
            'I need to call the family in the evening and share the latest update.',
            'The plan should be reviewed tomorrow in case the price or weather changes.',
            'I added a reminder to check the result before the end of the week.',
            'The remaining task is to collect the documents and verify every detail.',
            'I will avoid the busiest hour and leave additional time for traffic.',
        ];
    }
}
