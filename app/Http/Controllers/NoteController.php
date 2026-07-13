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
                $queryVectorStatus = $this->strategyLabel($strategy).' + '.$this->metricLabel($metric).' vector search returned the best 2 matches.';
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
            || ($strategy === 'ann_hnsw' && $metric === 'cosine');
    }

    private function vectorSearchResults(string $queryVector, string $strategy, string $metric, ?int $userId)
    {
        if ($strategy === 'ann_hnsw') {
            return $this->annHnswCosineSearch($queryVector, $userId);
        }

        return $this->exactSearch($queryVector, $metric, $userId);
    }

    private function exactSearch(string $queryVector, string $metric, ?int $userId)
    {
        return match ($metric) {
            'euclidean' => $this->exactEuclideanSearch($queryVector, $userId),
            'inner_product' => $this->exactInnerProductSearch($queryVector, $userId),
            default => $this->exactCosineSearch($queryVector, $userId),
        };
    }

    private function strategyLabel(?string $strategy): string
    {
        return match ($strategy) {
            'ann_hnsw' => 'ANN / HNSW',
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

    private function exactCosineSearch(string $queryVector, ?int $userId)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Cosine metric in pgvector uses the <=> operator, and lower distance is better.
        return $this->vectorSearchByOperator($queryVector, '<=>', $userId);
    }

    private function exactEuclideanSearch(string $queryVector, ?int $userId)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Euclidean metric in pgvector uses the <-> operator, and lower distance is better.
        return $this->vectorSearchByOperator($queryVector, '<->', $userId);
    }

    private function exactInnerProductSearch(string $queryVector, ?int $userId)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Inner Product in pgvector uses the <#> operator.
        // pgvector returns the negative inner product, so lower returned values rank better.
        return $this->vectorSearchByOperator($queryVector, '<#>', $userId);
    }

    private function annHnswCosineSearch(string $queryVector, ?int $userId)
    {
        // ANN / HNSW strategy: PostgreSQL can use the HNSW cosine index on notes.embedding.
        // The metric is still cosine distance (<=>); HNSW changes how candidates are retrieved.
        return $this->vectorSearchByOperator($queryVector, '<=>', $userId);
    }

    private function vectorSearchByOperator(string $queryVector, string $operator, ?int $userId)
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
            ->when(
                $userId === null,
                fn ($query) => $query->where('notes.is_public', true),
                fn ($query) => $query->where('notes.user_id', $userId),
            )
            ->orderByRaw("notes.embedding {$operator} ?::vector", [$queryVector])
            ->limit(2)
            ->get()
            ->map(function (object $row): Note {
                $note = new Note;
                $note->forceFill((array) $row);
                $note->exists = true;

                return $note;
            });
    }
}
