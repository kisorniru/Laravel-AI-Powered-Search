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
                @if ($searchMode === 'comparison')
                    Comparing Exact, HNSW, and IVFFlat for "{{ $search }}".
                @elseif ($searchMode === 'ai')
                    Showing {{ $strategyLabel }} vector search matches for "{{ $search }}".
                @else
                    Showing database matches for "{{ $search }}".
                @endif
                @if (in_array($searchMode, ['ai', 'comparison'], true))
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
                    <span class="mt-1 block text-sm text-zinc-600">সব vector exhaustively check না করে HNSW index দিয়ে দ্রুত approximately nearest neighbor খোঁজার approach. Cosine, Euclidean, and Inner Product are implemented.</span>
                </button>
                <button type="button" data-strategy="ann_ivfflat" class="rounded-md border border-zinc-300 p-4 text-left hover:border-sky-400 hover:bg-sky-50">
                    <span class="block font-medium">ANN / IVFFlat</span>
                    <span class="mt-1 block text-sm text-zinc-600">* inverted file flat index</span>
                    <span class="mt-1 block text-sm text-zinc-600">Vectors are grouped into lists/clusters, then nearby lists are searched. Cosine, Euclidean, and Inner Product are implemented.</span>
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

    @if ($queryVectorStatus && $searchMode !== 'comparison')
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
                            @elseif ($strategy === 'ann_ivfflat')
                                PostgreSQL used the IVFFlat index path, where vectors are grouped into inverted lists and nearby lists are searched.
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
                                @if ($strategy === 'ann_hnsw')
                                    The HNSW index was built with <code>vector_l2_ops</code>.
                                @elseif ($strategy === 'ann_ivfflat')
                                    The IVFFlat index was built with <code>vector_l2_ops</code> and <code>lists = 10</code>.
                                @endif
                            @elseif ($metric === 'inner_product')
                                pgvector used negative inner product with the <code>&lt;#&gt;</code> operator. Lower returned values mean stronger inner product matches.
                                @if ($strategy === 'ann_hnsw')
                                    The HNSW index was built with <code>vector_ip_ops</code>.
                                @elseif ($strategy === 'ann_ivfflat')
                                    The IVFFlat index was built with <code>vector_ip_ops</code> and <code>lists = 10</code>.
                                @endif
                            @else
                                pgvector used cosine distance with the <code>&lt;=&gt;</code> operator.
                                @if ($strategy === 'ann_hnsw')
                                    The HNSW index was built with <code>vector_cosine_ops</code>.
                                @elseif ($strategy === 'ann_ivfflat')
                                    The IVFFlat index was built with <code>vector_cosine_ops</code> and <code>lists = 10</code>.
                                @endif
                            @endif
                        </p>
                    </div>
                    <div class="rounded-md border border-sky-200 bg-white p-3">
                        <p class="font-medium">4. Retrieval</p>
                        <p class="mt-1 text-sky-800">
                            @if ($strategy === 'ann_hnsw')
                                HNSW retrieved approximate nearest candidates, ranked them by {{ $metricLabel }} distance, then returned the best 2 matches.
                            @elseif ($strategy === 'ann_ivfflat')
                                IVFFlat searched nearby vector lists, ranked candidates by {{ $metricLabel }} distance, then returned the best 2 matches.
                            @else
                                Notes were sorted by the best metric value, then the best 2 matches were returned.
                            @endif
                            @if ($distanceThreshold !== null)
                                Weak matches above distance {{ $distanceThreshold }} were filtered out.
                            @endif
                        </p>
                    </div>
                </div>
            @endif

            @if ($queryVectorPreview)
                <div class="mt-4 flex flex-col gap-3 border-t border-sky-200 pt-4 sm:flex-row sm:items-start">
                    @if (!$queryPlan)
                        <form method="GET" action="{{ route('notes.ai-search.explain') }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="strategy" value="{{ $strategy }}">
                            <input type="hidden" name="metric" value="{{ $metric }}">
                            <button type="submit" class="rounded-md border border-sky-300 bg-white px-4 py-2 font-medium text-sky-800 hover:bg-sky-100">
                                Run EXPLAIN ANALYZE
                            </button>
                        </form>
                    @endif
                    <form method="GET" action="{{ route('notes.ai-search.compare') }}">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="metric" value="{{ $metric }}">
                        <button type="submit" class="rounded-md bg-sky-800 px-4 py-2 font-medium text-white hover:bg-sky-700">
                            Compare strategies
                        </button>
                    </form>
                </div>
                <p class="mt-2 text-xs text-sky-800">These inspections execute read-only SELECT queries. No data is changed.</p>
            @endif
        </section>
    @endif

    @if ($queryPlan && $queryPlanSummary)
        <section class="mb-6 border-y border-violet-200 bg-violet-50 py-5 text-sm text-violet-950">
            <div class="mx-auto max-w-5xl px-4">
                <p class="text-xs font-medium uppercase text-violet-700">PostgreSQL query inspection</p>
                <h2 class="mt-1 text-lg font-semibold">EXPLAIN ANALYZE result</h2>
                <p class="mt-2 text-violet-900">{{ $queryPlanSummary['strategy_observation'] }}</p>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="border-l-2 border-violet-300 pl-3">
                        <dt class="text-xs text-violet-700">Planning time</dt>
                        <dd class="mt-1 font-medium">{{ $queryPlanSummary['planning_time'] ?? 'Not reported' }}</dd>
                    </div>
                    <div class="border-l-2 border-violet-300 pl-3">
                        <dt class="text-xs text-violet-700">Execution time</dt>
                        <dd class="mt-1 font-medium">{{ $queryPlanSummary['execution_time'] ?? 'Not reported' }}</dd>
                    </div>
                    <div class="border-l-2 border-violet-300 pl-3">
                        <dt class="text-xs text-violet-700">Scan types</dt>
                        <dd class="mt-1 font-medium">{{ $queryPlanSummary['scans'] ? implode(', ', $queryPlanSummary['scans']) : 'Not detected' }}</dd>
                    </div>
                    <div class="border-l-2 border-violet-300 pl-3">
                        <dt class="text-xs text-violet-700">Indexes used</dt>
                        <dd class="mt-1 break-words font-medium">{{ $queryPlanSummary['indexes'] ? implode(', ', $queryPlanSummary['indexes']) : 'None reported' }}</dd>
                    </div>
                </dl>

                <details class="mt-5">
                    <summary class="cursor-pointer font-medium text-violet-800">View sanitized raw query plan</summary>
                    <pre class="mt-3 max-h-96 overflow-auto border border-violet-200 bg-white p-4 text-xs leading-5 text-zinc-800">{{ implode("\n", $queryPlan) }}</pre>
                </details>

                <p class="mt-4 text-xs text-violet-800">
                    <strong>Important:</strong> the selected UI strategy describes your requested experiment, but PostgreSQL chooses the physical execution plan. The actual index above is the evidence.
                </p>
            </div>
        </section>
    @endif

    @if ($searchMode === 'comparison' && $queryVectorStatus && !$strategyComparison)
        <section class="mb-6 border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <h2 class="font-semibold">Strategy comparison could not run</h2>
            <p class="mt-1">{{ $queryVectorStatus }}</p>
        </section>
    @endif

    @if ($strategyComparison)
        <section class="mb-6 text-sm text-zinc-950">
            <div class="border-y border-emerald-200 bg-emerald-50 py-5">
                <p class="text-xs font-medium uppercase text-emerald-700">Side-by-side experiment</p>
                <h2 class="mt-1 text-xl font-semibold">Strategy comparison with {{ $metricLabel }}</h2>
                <p class="mt-2 max-w-3xl text-emerald-900">{{ $queryVectorStatus }}</p>
                <code class="mt-3 block max-w-2xl border border-emerald-200 bg-white p-3 text-xs text-zinc-700">{{ $queryVectorPreview }}</code>
                <p class="mt-3 text-xs text-emerald-800">Exact disables index scans for an exhaustive baseline. ANN rows report the real plan PostgreSQL selected; HNSW or IVFFlat is not claimed unless its index appears in EXPLAIN ANALYZE.</p>
            </div>

            <div class="mt-5 overflow-x-auto border border-zinc-200 bg-white">
                <table class="min-w-[900px] w-full text-left align-top">
                    <thead class="border-b border-zinc-200 bg-zinc-100 text-xs text-zinc-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Requested strategy</th>
                            <th class="px-4 py-3 font-medium">Actual PostgreSQL plan</th>
                            <th class="px-4 py-3 font-medium">Timing</th>
                            <th class="px-4 py-3 font-medium">Top results</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($strategyComparison as $comparison)
                            <tr>
                                <td class="w-44 px-4 py-4 align-top">
                                    <p class="font-semibold">{{ $comparison['label'] }}</p>
                                    <p class="mt-1 text-xs text-zinc-500">Requested experiment</p>
                                </td>
                                <td class="max-w-sm px-4 py-4 align-top">
                                    <p>{{ $comparison['summary']['strategy_observation'] }}</p>
                                    <dl class="mt-3 space-y-1 text-xs text-zinc-600">
                                        <div><dt class="inline font-medium">Scans:</dt> <dd class="inline">{{ $comparison['summary']['scans'] ? implode(', ', $comparison['summary']['scans']) : 'Not detected' }}</dd></div>
                                        <div><dt class="inline font-medium">Indexes:</dt> <dd class="inline break-all">{{ $comparison['summary']['indexes'] ? implode(', ', $comparison['summary']['indexes']) : 'None reported' }}</dd></div>
                                    </dl>
                                    <details class="mt-3 text-xs">
                                        <summary class="cursor-pointer font-medium text-zinc-700">Raw plan</summary>
                                        <pre class="mt-2 max-h-64 max-w-md overflow-auto border border-zinc-200 bg-zinc-50 p-3 leading-5">{{ implode("\n", $comparison['plan']) }}</pre>
                                    </details>
                                </td>
                                <td class="w-40 px-4 py-4 align-top">
                                    <p><span class="text-xs text-zinc-500">Execution</span><br><strong>{{ $comparison['summary']['execution_time'] ?? 'Not reported' }}</strong></p>
                                    <p class="mt-3"><span class="text-xs text-zinc-500">Planning</span><br><strong>{{ $comparison['summary']['planning_time'] ?? 'Not reported' }}</strong></p>
                                </td>
                                <td class="min-w-64 px-4 py-4 align-top">
                                    @forelse ($comparison['results'] as $result)
                                        <div class="{{ !$loop->first ? 'mt-3 border-t border-zinc-100 pt-3' : '' }}">
                                            <a href="{{ route('notes.show', $result) }}" class="font-medium hover:underline">{{ $loop->iteration }}. {{ $result->title }}</a>
                                            <p class="mt-1 text-xs text-zinc-500">
                                                {{ $metric === 'inner_product' ? 'Negative inner product' : $metricLabel.' distance' }}:
                                                {{ number_format((float) $result->vector_distance, 6) }}
                                            </p>
                                        </div>
                                    @empty
                                        <p class="text-zinc-500">No result passed the current threshold.</p>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-zinc-500">Timings are educational samples, not a formal benchmark. Cache state, dataset size, concurrent work, and execution order can affect them.</p>
        </section>
    @else

    @if ($notes->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center">
            <h2 class="text-lg font-semibold">{{ $search === '' ? 'No notes yet' : 'No matching notes' }}</h2>
            <p class="mt-2 text-zinc-600">
                @if ($search === '')
                    Add your first note and you will see it listed here.
                @elseif ($searchMode === 'ai' && $distanceThreshold !== null)
                    Try another query or relax the {{ $metricLabel }} distance threshold.
                @else
                    Try another title or body keyword.
                @endif
            </p>
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
