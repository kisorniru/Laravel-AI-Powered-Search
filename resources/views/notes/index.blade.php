<x-layouts.app title="Notes">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-medium uppercase text-zinc-500">CRUD starter</p>
            <h1 class="mt-1 text-3xl font-semibold">Notes</h1>
            <p class="mt-2 max-w-2xl text-zinc-600">Create, view, edit, and delete simple notes.</p>
        </div>
    </div>

    <form id="search-form" method="GET" class="mb-6 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
        <label for="search" class="block text-sm font-medium text-zinc-700">Search notes</label>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row">
            <input
                id="search"
                name="search"
                type="search"
                value="{{ $search }}"
                placeholder="Search by title or body"
                class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
            >
            <input id="ai-strategy" type="hidden" name="strategy" value="exact">
            <input id="ai-metric" type="hidden" name="metric" value="cosine">
            <button type="submit" formaction="{{ route('notes.index') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Regular search</button>
            <button id="open-strategy-modal" type="button" class="rounded-md border border-sky-300 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100">AI search</button>
            @if ($search !== '')
                <a href="{{ route('notes.index') }}" class="rounded-md border border-zinc-300 px-4 py-2 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-100">Clear</a>
            @endif
        </div>
        @if ($search !== '')
            <p class="mt-2 text-sm text-zinc-500">
                @if ($searchMode === 'ai')
                    Showing {{ $strategyLabel }} vector search matches for "{{ $search }}".
                @else
                    Showing database matches for "{{ $search }}".
                @endif
                @if ($searchMode === 'ai')
                    {{ $metricLabel }} is used and only the best 2 notes are returned.
                @endif
            </p>
        @else
            <p class="mt-2 text-sm text-zinc-500">Regular search uses the database. AI search embeds your query, then ranks notes with your selected strategy and metric.</p>
        @endif
        <p class="mt-2 text-sm text-zinc-500">
            @auth
                You are searching only your own public and private notes.
            @else
                You are searching public notes from all authors.
            @endauth
        </p>
    </form>

    <div id="strategy-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/50 px-4">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase text-sky-700">AI search</p>
                    <h2 class="mt-1 text-xl font-semibold">Choose strategy</h2>
                </div>
                <button type="button" data-close-modal class="text-2xl leading-none text-zinc-400 hover:text-zinc-700">&times;</button>
            </div>

            <div class="mt-5 grid gap-3">
                <button type="button" data-strategy="exact" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">Exact</span>
                    <span class="mt-1 block text-sm text-zinc-600">সব relevant vector compare করে mathematically exact nearest result খোঁজে । এখানেও semantic similarity হচ্ছে। অর্থাৎ wording completely different হলেও Exact Search relevant chunk খুঁজে পেতে পারে ।</span>
                </button>
                <button type="button" data-strategy="ann_hnsw" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">ANN / HNSW</span>
                    <span class="mt-1 block text-sm text-zinc-600">* approximate nearest neighbor</span>
                    <span class="mt-1 block text-sm text-zinc-600">* Hierarchical Navigable Small World</span>
                    <span class="mt-1 block text-sm text-zinc-600">সব vector exhaustively check না করে HNSW index দিয়ে দ্রুত approximately nearest neighbor খোঁজার approach. For now only Cosine is implemented.</span>
                </button>
            </div>
        </section>
    </div>

    <div id="metric-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/50 px-4">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase text-sky-700">AI search</p>
                    <h2 class="mt-1 text-xl font-semibold">Choose metric</h2>
                    <span class="mt-1 block text-sm text-zinc-600">দুটি vector কতটা কাছাকাছি; সেটা মাপার নিয়ম; যেমন Cosine, Euclidean, Inner Product</span>
                </div>
                <button type="button" data-close-modal class="text-2xl leading-none text-zinc-400 hover:text-zinc-700">&times;</button>
            </div>

            <div class="mt-5 grid gap-3">
                <button type="button" data-metric="cosine" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">Cosine</span>
                    <span class="mt-1 block text-sm text-zinc-600">Compares vector direction. Good when meaning matters more than vector length. Current implementation returns best 2 by cosine distance.</span>
                </button>
                <button type="button" data-metric="euclidean" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">Euclidean</span>
                    <span class="mt-1 block text-sm text-zinc-600">Compares straight-line distance between vectors. Current implementation returns best 2 by Euclidean distance.</span>
                </button>
                <button type="button" data-metric="inner_product" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">Inner Product</span>
                    <span class="mt-1 block text-sm text-zinc-600">Compares vector alignment and magnitude. Current implementation returns best 2 by inner product score.</span>
                </button>
            </div>

            <button type="button" id="back-to-strategy" class="mt-5 text-sm font-medium text-zinc-600 hover:text-zinc-950">Back to strategy</button>
        </section>
    </div>

    @if ($queryVectorStatus)
        <section class="mb-6 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
            <h2 class="font-semibold">How AI search worked in the background</h2>
            <p class="mt-1">{{ $queryVectorStatus }}</p>
            @if ($queryVectorPreview)
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-md border border-sky-200 bg-white p-3">
                        <p class="font-medium">1. Query embedding</p>
                        <p class="mt-1 text-sky-800">The text "{{ $search }}" was converted into a 384-dimensional query vector.</p>
                        <code class="mt-2 block rounded bg-zinc-50 p-2 text-xs text-zinc-700">{{ $queryVectorPreview }}</code>
                    </div>
                    <div class="rounded-md border border-sky-200 bg-white p-3">
                        <p class="font-medium">2. Search strategy</p>
                        <p class="mt-1 text-sky-800">
                            Strategy: {{ $strategyLabel }}.
                            @if ($strategy === 'ann_hnsw')
                                PostgreSQL used the HNSW index path to quickly visit likely-nearest note vectors instead of exhaustively scanning every vector.
                            @else
                                The query vector was compared with every stored note vector.
                            @endif
                        </p>
                    </div>
                    <div class="rounded-md border border-sky-200 bg-white p-3">
                        <p class="font-medium">3. Similarity metric</p>
                        <p class="mt-1 text-sky-800">
                            Metric: {{ $metricLabel }}.
                            @if ($metric === 'euclidean')
                                pgvector used Euclidean distance with the <code>&lt;-&gt;</code> operator.
                            @elseif ($metric === 'inner_product')
                                pgvector used negative inner product with the <code>&lt;#&gt;</code> operator. Lower returned values mean stronger inner product matches.
                            @else
                                pgvector used cosine distance with the <code>&lt;=&gt;</code> operator.
                                @if ($strategy === 'ann_hnsw')
                                    The HNSW index was built with <code>vector_cosine_ops</code>.
                                @endif
                            @endif
                        </p>
                    </div>
                    <div class="rounded-md border border-sky-200 bg-white p-3">
                        <p class="font-medium">4. Retrieval</p>
                        <p class="mt-1 text-sky-800">
                            @if ($strategy === 'ann_hnsw')
                                HNSW retrieved approximate nearest candidates, ranked them by cosine distance, then returned the best 2 matches.
                            @else
                                Notes were sorted by the best metric value, then the best 2 matches were returned.
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </section>
    @endif

    @if ($notes->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">{{ $search === '' ? 'No notes yet' : 'No matching notes' }}</h2>
            <p class="mt-2 text-zinc-600">{{ $search === '' ? 'Add your first note and you will see it listed here.' : 'Try another title or body keyword.' }}</p>
            @auth
                @if ($search === '')
                    <a href="{{ route('notes.create') }}" class="mt-5 inline-flex rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Create note</a>
                @endif
            @endauth
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-100 text-zinc-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="hidden px-4 py-3 font-medium md:table-cell">Body</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($notes as $note)
                        <tr>
                            <td class="px-4 py-4 align-top">
                                <a href="{{ route('notes.show', $note) }}" class="font-medium text-zinc-950 hover:underline">{{ $note->title }}</a>
                                <div class="mt-1 flex items-center gap-2 text-xs text-zinc-500">
                                    <span>{{ $note->created_at->diffForHumans() }}</span>
                                    <span>By {{ $note->author_name ?? $note->user?->name ?? 'Unknown author' }}</span>
                                    <span class="rounded-full px-2 py-0.5 font-medium {{ $note->is_public ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $note->is_public ? 'Public' : 'Private' }}
                                    </span>
                                </div>
                            </td>
                            <td class="hidden max-w-xl px-4 py-4 text-zinc-600 md:table-cell">
                                {{ \Illuminate\Support\Str::limit($note->body, 140) }}
                                @if (isset($note->vector_distance))
                                    <p class="mt-2 text-xs text-zinc-500">
                                        @if ($metric === 'inner_product')
                                            Negative inner product:
                                        @else
                                            {{ $metricLabel ?? 'Vector' }} distance:
                                        @endif
                                        {{ number_format((float) $note->vector_distance, 6) }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right align-top">
                                @can('update', $note)
                                    <div class="flex justify-end gap-3">
                                        <a href="{{ route('notes.edit', $note) }}" class="font-medium text-zinc-700 hover:text-zinc-950">Edit</a>
                                        <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm('Delete this note?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (method_exists($notes, 'links'))
            <div class="mt-6">
                {{ $notes->links() }}
            </div>
        @endif
    @endif

    <script>
        const searchForm = document.getElementById('search-form');
        const strategyModal = document.getElementById('strategy-modal');
        const metricModal = document.getElementById('metric-modal');
        const strategyInput = document.getElementById('ai-strategy');
        const metricInput = document.getElementById('ai-metric');

        function showModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function hideModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        document.getElementById('open-strategy-modal').addEventListener('click', () => {
            showModal(strategyModal);
        });

        document.querySelectorAll('[data-strategy]').forEach((button) => {
            button.addEventListener('click', () => {
                strategyInput.value = button.dataset.strategy;
                hideModal(strategyModal);
                showModal(metricModal);
            });
        });

        document.querySelectorAll('[data-metric]').forEach((button) => {
            button.addEventListener('click', () => {
                metricInput.value = button.dataset.metric;
                searchForm.action = '{{ route('notes.ai-search') }}';
                searchForm.submit();
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                hideModal(strategyModal);
                hideModal(metricModal);
            });
        });

        document.getElementById('back-to-strategy').addEventListener('click', () => {
            hideModal(metricModal);
            showModal(strategyModal);
        });
    </script>
</x-layouts.app>
