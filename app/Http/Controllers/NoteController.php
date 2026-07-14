<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use App\Services\HuggingFaceEmbeddingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        return view('notes.index', [
            // Regular search is intentionally plain SQL text matching.
            'notes' => $this->regularSearch($search, $request->user()),
            'search' => $search,
            'searchMode' => 'regular',
            'queryVectorPreview' => null,
            'queryVectorStatus' => null,
            'strategy' => null,
            'strategyLabel' => null,
            'metric' => null,
            'metricLabel' => null,
            'distanceThreshold' => null,
            'queryPlan' => null,
            'queryPlanSummary' => null,
        ]);
    }

    public function vectorSearch(Request $request, HuggingFaceEmbeddingService $embeddings)
    {
        $search = trim((string) $request->query('search', ''));
        $strategy = $request->query('strategy', 'exact');
        $metric = $request->query('metric', 'cosine');
        $notes = collect();
        $queryVectorPreview = null;
        $queryVectorStatus = null;
        $distanceThreshold = $this->distanceThreshold($metric);

        if ($search === '') {
            return redirect()->route('notes.index');
        }

        if (! $this->implementedVectorSearch($strategy, $metric)) {
            $queryVectorStatus = 'This strategy and metric combination is not implemented yet.';
        } elseif (! $embeddings->configured()) {
            $queryVectorStatus = 'Add HUGGINGFACE_API_TOKEN to run AI search.';
        } else {
            try {
                // Vector search starts by embedding the user's search text.
                $embedding = $embeddings->embed($search);
                $queryVectorPreview = $this->vectorPreview($embedding);
                $queryVector = $this->toVectorLiteral($embedding);
                $notes = $this->vectorSearchResults($queryVector, $strategy, $metric, $request->user()?->id);
                $queryVectorStatus = $this->vectorSearchStatus($strategy, $metric, $distanceThreshold, $notes->count());
            } catch (Throwable $exception) {
                $queryVectorStatus = 'AI search failed: '.$exception->getMessage();
            }
        }

        return view('notes.index', [
            'notes' => $notes,
            'search' => $search,
            'searchMode' => 'ai',
            'queryVectorPreview' => $queryVectorPreview,
            'queryVectorStatus' => $queryVectorStatus,
            'strategy' => $strategy,
            'strategyLabel' => $this->strategyLabel($strategy),
            'metric' => $metric,
            'metricLabel' => $this->metricLabel($metric),
            'distanceThreshold' => $distanceThreshold,
            'queryPlan' => null,
            'queryPlanSummary' => null,
        ]);
    }

    public function explainVectorSearch(Request $request, HuggingFaceEmbeddingService $embeddings)
    {
        $search = trim((string) $request->query('search', ''));
        $strategy = (string) $request->query('strategy', 'exact');
        $metric = (string) $request->query('metric', 'cosine');
        $distanceThreshold = $this->distanceThreshold($metric);
        $notes = collect();
        $queryVectorPreview = null;
        $queryPlan = null;
        $queryPlanSummary = null;

        if ($search === '') {
            return redirect()->route('notes.index');
        }

        if (! $this->implementedVectorSearch($strategy, $metric)) {
            $queryVectorStatus = 'This strategy and metric combination is not implemented yet.';
        } elseif (! $embeddings->configured()) {
            $queryVectorStatus = 'Add HUGGINGFACE_API_TOKEN to analyze an AI search.';
        } else {
            try {
                // EXPLAIN ANALYZE needs the same query vector and SQL used by the real search.
                $embedding = $embeddings->embed($search);
                $queryVectorPreview = $this->vectorPreview($embedding);
                $queryVector = $this->toVectorLiteral($embedding);
                $notes = $this->vectorSearchResults($queryVector, $strategy, $metric, $request->user()?->id);
                $queryPlan = $this->explainVectorSearchQuery(
                    $queryVector,
                    $metric,
                    $request->user()?->id,
                    $distanceThreshold,
                );
                $queryPlanSummary = $this->summarizeQueryPlan($queryPlan, $strategy);
                $queryVectorStatus = $this->vectorSearchStatus($strategy, $metric, $distanceThreshold, $notes->count());
            } catch (Throwable $exception) {
                $queryVectorStatus = 'EXPLAIN ANALYZE failed: '.$exception->getMessage();
            }
        }

        return view('notes.index', [
            'notes' => $notes,
            'search' => $search,
            'searchMode' => 'ai',
            'queryVectorPreview' => $queryVectorPreview,
            'queryVectorStatus' => $queryVectorStatus,
            'strategy' => $strategy,
            'strategyLabel' => $this->strategyLabel($strategy),
            'metric' => $metric,
            'metricLabel' => $this->metricLabel($metric),
            'distanceThreshold' => $distanceThreshold,
            'queryPlan' => $queryPlan,
            'queryPlanSummary' => $queryPlanSummary,
        ]);
    }

    public function create()
    {
        return view('notes.create');
    }

    public function store(Request $request, HuggingFaceEmbeddingService $embeddings)
    {
        $note = $request->user()->notes()->create($this->validateNote($request));

        return redirect()
            ->route('notes.index')
            ->with('status', $this->embeddingStatus('Note created.', $note, $embeddings));
    }

    public function show(Note $note)
    {
        Gate::authorize('view', $note);
        $note->load('user:id,name');

        return view('notes.show', [
            'note' => $note,
        ]);
    }

    public function edit(Note $note)
    {
        Gate::authorize('update', $note);

        return view('notes.edit', [
            'note' => $note,
        ]);
    }

    public function update(Request $request, Note $note, HuggingFaceEmbeddingService $embeddings)
    {
        Gate::authorize('update', $note);

        $note->update($this->validateNote($request, $note));

        return redirect()
            ->route('notes.show', $note)
            ->with('status', $this->embeddingStatus('Note updated.', $note, $embeddings));
    }

    public function destroy(Note $note)
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return redirect()
            ->route('notes.index')
            ->with('status', 'Note deleted.');
    }

    private function validateNote(Request $request, ?Note $note = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['sometimes', 'string', 'in:public,private'],
        ]);

        return [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_public' => isset($validated['visibility'])
                ? $validated['visibility'] === 'public'
                : ($note?->is_public ?? true),
        ];
    }

    private function embeddingStatus(string $message, Note $note, HuggingFaceEmbeddingService $embeddings): string
    {
        if (! $embeddings->configured()) {
            return $message.' Add HUGGINGFACE_API_TOKEN to generate an embedding.';
        }

        try {
            // A note embedding represents its content, visibility, and author.
            $embedding = $embeddings->embed($note->textToEmbed());

            DB::table('notes')
                ->where('id', $note->id)
                ->update([
                    'embedding' => DB::raw("'".$this->toVectorLiteral($embedding)."'::vector"),
                    'embedded_at' => now(),
                ]);

            return $message.' Embedding generated.';
        } catch (QueryException $exception) {
            return $message.' Embedding skipped because pgvector is not migrated yet.';
        } catch (Throwable $exception) {
            return $message.' Embedding failed: '.$exception->getMessage();
        }
    }

    private function toVectorLiteral(array $embedding): string
    {
        // pgvector expects vectors as a string like: [0.1,0.2,0.3]
        return '['.implode(',', array_map(
            fn ($value): string => rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.'),
            $embedding,
        )).']';
    }

    private function vectorPreview(array $embedding): string
    {
        $firstValues = array_slice($embedding, 0, 8);

        return '['.implode(', ', array_map(
            fn ($value): string => rtrim(rtrim(sprintf('%.4F', (float) $value), '0'), '.'),
            $firstValues,
        )).', ...]';
    }

    private function regularSearch(string $search, ?User $user)
    {
        // This is not AI search; it only checks whether text appears in title/body.
        return Note::query()
            ->with('user:id,name')
            ->visibleTo($user)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'ilike', "%{$search}%")
                        ->orWhere('body', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    private function implementedVectorSearch(string $strategy, string $metric): bool
    {
        return ($strategy === 'exact' && in_array($metric, ['cosine', 'euclidean', 'inner_product'], true))
            || ($strategy === 'ann_hnsw' && in_array($metric, ['cosine', 'euclidean', 'inner_product'], true))
            || ($strategy === 'ann_ivfflat' && in_array($metric, ['cosine', 'euclidean', 'inner_product'], true));
    }

    private function vectorSearchResults(string $queryVector, string $strategy, string $metric, ?int $userId)
    {
        if ($strategy === 'ann_hnsw') {
            return match ($metric) {
                'euclidean' => $this->annHnswEuclideanSearch($queryVector, $userId, $this->distanceThreshold($metric)),
                'inner_product' => $this->annHnswInnerProductSearch($queryVector, $userId),
                default => $this->annHnswCosineSearch($queryVector, $userId, $this->distanceThreshold($metric)),
            };
        }

        if ($strategy === 'ann_ivfflat') {
            return match ($metric) {
                'euclidean' => $this->annIvfflatEuclideanSearch($queryVector, $userId, $this->distanceThreshold($metric)),
                'inner_product' => $this->annIvfflatInnerProductSearch($queryVector, $userId),
                default => $this->annIvfflatCosineSearch($queryVector, $userId, $this->distanceThreshold($metric)),
            };
        }

        return $this->exactSearch($queryVector, $metric, $userId, $this->distanceThreshold($metric));
    }

    private function exactSearch(string $queryVector, string $metric, ?int $userId, ?float $maxDistance)
    {
        return match ($metric) {
            'euclidean' => $this->exactEuclideanSearch($queryVector, $userId, $maxDistance),
            'inner_product' => $this->exactInnerProductSearch($queryVector, $userId),
            default => $this->exactCosineSearch($queryVector, $userId, $maxDistance),
        };
    }

    private function strategyLabel(?string $strategy): string
    {
        return match ($strategy) {
            'ann_hnsw' => 'ANN / HNSW',
            'ann_ivfflat' => 'ANN / IVFFlat',
            'exact' => 'Exact',
            default => 'Vector',
        };
    }

    private function metricLabel(?string $metric): string
    {
        return match ($metric) {
            'euclidean' => 'Euclidean',
            'inner_product' => 'Inner Product',
            'cosine' => 'Cosine',
            default => 'Vector',
        };
    }

    private function distanceThreshold(string $metric): ?float
    {
        return match ($metric) {
            // Cosine distance ranges from 0 to 2 in pgvector. Lower is more similar.
            'cosine' => 0.85,
            // Euclidean/L2 depends on vector scale, so this is a simple learning threshold.
            'euclidean' => 0.5,
            // Inner product uses negative scores in pgvector, so skip a distance threshold for now.
            default => null,
        };
    }

    private function vectorSearchStatus(string $strategy, string $metric, ?float $distanceThreshold, int $count): string
    {
        $status = $this->strategyLabel($strategy).' + '.$this->metricLabel($metric).' vector search returned '.$count.' match'.($count === 1 ? '' : 'es').'.';

        if ($distanceThreshold !== null) {
            return $status.' Only results with '.$this->metricLabel($metric).' distance <= '.$distanceThreshold.' are shown.';
        }

        return $status;
    }

    private function exactCosineSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Cosine metric in pgvector uses the <=> operator, and lower distance is better.
        return $this->vectorSearchByOperator($queryVector, '<=>', $userId, $maxDistance);
    }

    private function exactEuclideanSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Euclidean metric in pgvector uses the <-> operator, and lower distance is better.
        return $this->vectorSearchByOperator($queryVector, '<->', $userId, $maxDistance);
    }

    private function exactInnerProductSearch(string $queryVector, ?int $userId)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Inner Product in pgvector uses the <#> operator.
        // pgvector returns the negative inner product, so lower returned values rank better.
        return $this->vectorSearchByOperator($queryVector, '<#>', $userId, null);
    }

    private function annHnswCosineSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // ANN / HNSW strategy: PostgreSQL can use the HNSW cosine index on notes.embedding.
        // The metric is still cosine distance (<=>); HNSW changes how candidates are retrieved.
        return $this->vectorSearchByOperator($queryVector, '<=>', $userId, $maxDistance);
    }

    private function annHnswEuclideanSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // ANN / HNSW strategy: PostgreSQL can use the HNSW Euclidean/L2 index on notes.embedding.
        // The metric is still Euclidean distance (<->); HNSW changes how candidates are retrieved.
        return $this->vectorSearchByOperator($queryVector, '<->', $userId, $maxDistance);
    }

    private function annHnswInnerProductSearch(string $queryVector, ?int $userId)
    {
        // ANN / HNSW strategy: PostgreSQL can use the HNSW Inner Product index on notes.embedding.
        // The metric is still negative inner product (<#>); HNSW changes how candidates are retrieved.
        return $this->vectorSearchByOperator($queryVector, '<#>', $userId, null);
    }

    private function annIvfflatCosineSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // ANN / IVFFlat strategy: PostgreSQL can use the IVFFlat cosine index on notes.embedding.
        // The metric is still cosine distance (<=>); IVFFlat searches nearby inverted lists.
        return $this->vectorSearchByOperator($queryVector, '<=>', $userId, $maxDistance);
    }

    private function annIvfflatEuclideanSearch(string $queryVector, ?int $userId, ?float $maxDistance)
    {
        // ANN / IVFFlat strategy: PostgreSQL can use the IVFFlat Euclidean/L2 index on notes.embedding.
        // The metric is still Euclidean distance (<->); IVFFlat searches nearby inverted lists.
        return $this->vectorSearchByOperator($queryVector, '<->', $userId, $maxDistance);
    }

    private function annIvfflatInnerProductSearch(string $queryVector, ?int $userId)
    {
        // ANN / IVFFlat strategy: PostgreSQL can use the IVFFlat Inner Product index on notes.embedding.
        // The metric is still negative inner product (<#>); IVFFlat searches nearby inverted lists.
        return $this->vectorSearchByOperator($queryVector, '<#>', $userId, null);
    }

    private function vectorSearchByOperator(string $queryVector, string $operator, ?int $userId, ?float $maxDistance)
    {
        return $this->vectorSearchQueryByOperator($queryVector, $operator, $userId, $maxDistance)
            ->get()
            ->map(function (object $row): Note {
                $note = new Note;
                $note->forceFill((array) $row);
                $note->exists = true;

                return $note;
            });
    }

    private function vectorSearchQueryByOperator(string $queryVector, string $operator, ?int $userId, ?float $maxDistance)
    {
        return DB::table('notes')
            ->leftJoin('users', 'users.id', '=', 'notes.user_id')
            ->select(
                'notes.id',
                'notes.user_id',
                'notes.title',
                'notes.body',
                'notes.is_public',
                'notes.created_at',
                'notes.updated_at',
                'notes.embedded_at',
                'users.name as author_name',
            )
            ->selectRaw("notes.embedding {$operator} ?::vector AS vector_distance", [$queryVector])
            ->whereNotNull('notes.embedding')
            ->when($maxDistance !== null, function ($query) use ($operator, $queryVector, $maxDistance): void {
                // Threshold removes weak matches that only appear because we request the top 2.
                $query->whereRaw("notes.embedding {$operator} ?::vector <= ?", [$queryVector, $maxDistance]);
            })
            ->when(
                $userId === null,
                fn ($query) => $query->where('notes.is_public', true),
                fn ($query) => $query->where('notes.user_id', $userId),
            )
            ->orderByRaw("notes.embedding {$operator} ?::vector", [$queryVector])
            ->limit(2);
    }

    /**
     * Run PostgreSQL's planner and executor against the same SELECT used for retrieval.
     * ANALYZE executes the SELECT, while BUFFERS reports cache/disk activity.
     *
     * @return array<int, string>
     */
    private function explainVectorSearchQuery(string $queryVector, string $metric, ?int $userId, ?float $maxDistance): array
    {
        $query = $this->vectorSearchQueryByOperator(
            $queryVector,
            $this->metricOperator($metric),
            $userId,
            $maxDistance,
        );

        $rows = DB::select(
            'EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) '.$query->toSql(),
            $query->getBindings(),
        );

        return array_map(function (object $row): string {
            $columns = (array) $row;
            $line = (string) ($columns['QUERY PLAN'] ?? reset($columns));

            // Query plans may print the complete vector literal. Keep the learning UI readable.
            return preg_replace(
                "/'\\[[^]]+\\]'::vector/",
                "'[384-dimensional query vector]'::vector",
                $line,
            ) ?? $line;
        }, $rows);
    }

    private function metricOperator(string $metric): string
    {
        return match ($metric) {
            'euclidean' => '<->',
            'inner_product' => '<#>',
            default => '<=>',
        };
    }

    /**
     * Extract the parts a learner usually checks first from the full PostgreSQL plan.
     *
     * @param  array<int, string>  $queryPlan
     * @return array{planning_time: ?string, execution_time: ?string, scans: array<int, string>, indexes: array<int, string>, strategy_observation: string}
     */
    private function summarizeQueryPlan(array $queryPlan, string $selectedStrategy): array
    {
        $planText = implode("\n", $queryPlan);
        preg_match('/Planning Time: ([0-9.]+ ms)/', $planText, $planningTime);
        preg_match('/Execution Time: ([0-9.]+ ms)/', $planText, $executionTime);
        preg_match_all('/(?:Index Only Scan|Index Scan|Bitmap Index Scan|Seq Scan)/', $planText, $scanMatches);
        preg_match_all('/(?:Index Only Scan|Index Scan|Bitmap Index Scan) using ([^ ]+)/', $planText, $indexMatches);

        $scans = array_values(array_unique($scanMatches[0]));
        $indexes = array_values(array_unique($indexMatches[1]));
        $indexText = strtolower(implode(' ', $indexes));

        $strategyObservation = match ($selectedStrategy) {
            'ann_hnsw' => str_contains($indexText, 'hnsw')
                ? 'PostgreSQL used an HNSW index for this execution.'
                : 'No HNSW index appears in this plan. The planner selected a different path.',
            'ann_ivfflat' => str_contains($indexText, 'ivfflat')
                ? 'PostgreSQL used an IVFFlat index for this execution.'
                : 'No IVFFlat index appears in this plan. The planner selected a different path.',
            default => str_contains($indexText, 'hnsw') || str_contains($indexText, 'ivfflat')
                ? 'Although Exact was selected, PostgreSQL chose an ANN index path for this SQL.'
                : 'No ANN vector index appears in this plan; PostgreSQL evaluated another path.',
        };

        return [
            'planning_time' => $planningTime[1] ?? null,
            'execution_time' => $executionTime[1] ?? null,
            'scans' => $scans,
            'indexes' => $indexes,
            'strategy_observation' => $strategyObservation,
        ];
    }
}
