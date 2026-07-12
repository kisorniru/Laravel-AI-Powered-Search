<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\HuggingFaceEmbeddingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        return view('notes.index', [
            // Regular search is intentionally plain SQL text matching.
            'notes' => $this->regularSearch($search),
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
                $notes = $this->vectorSearchResults($queryVector, $strategy, $metric);
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
        $note = Note::create($this->validateNote($request));

        return redirect()
            ->route('notes.index')
            ->with('status', $this->embeddingStatus('Note created.', $note, $embeddings));
    }

    public function show(Note $note)
    {
        return view('notes.show', [
            'note' => $note,
        ]);
    }

    public function edit(Note $note)
    {
        return view('notes.edit', [
            'note' => $note,
        ]);
    }

    public function update(Request $request, Note $note, HuggingFaceEmbeddingService $embeddings)
    {
        $note->update($this->validateNote($request));

        return redirect()
            ->route('notes.show', $note)
            ->with('status', $this->embeddingStatus('Note updated.', $note, $embeddings));
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return redirect()
            ->route('notes.index')
            ->with('status', 'Note deleted.');
    }

    private function validateNote(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
    }

    private function embeddingStatus(string $message, Note $note, HuggingFaceEmbeddingService $embeddings): string
    {
        if (! $embeddings->configured()) {
            return $message.' Add HUGGINGFACE_API_TOKEN to generate an embedding.';
        }

        try {
            // A note embedding represents the latest title + body content.
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

    private function regularSearch(string $search)
    {
        // This is not AI search; it only checks whether text appears in title/body.
        return Note::query()
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

    private function vectorSearchResults(string $queryVector, string $strategy, string $metric)
    {
        if ($strategy === 'ann_hnsw') {
            return $this->annHnswCosineSearch($queryVector);
        }

        return $this->exactSearch($queryVector, $metric);
    }

    private function exactSearch(string $queryVector, string $metric)
    {
        return match ($metric) {
            'euclidean' => $this->exactEuclideanSearch($queryVector),
            'inner_product' => $this->exactInnerProductSearch($queryVector),
            default => $this->exactCosineSearch($queryVector),
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

    private function exactCosineSearch(string $queryVector)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Cosine metric in pgvector uses the <=> operator, and lower distance is better.
        return collect(DB::select(
            <<<'SQL'
                SELECT id, title, body, created_at, updated_at, embedded_at,
                       embedding <=> ?::vector AS vector_distance
                FROM notes
                WHERE embedding IS NOT NULL
                ORDER BY embedding <=> ?::vector
                LIMIT 2
            SQL,
            [$queryVector, $queryVector],
        ))->map(function (object $row): Note {
            $note = new Note;
            $note->forceFill((array) $row);
            $note->exists = true;

            return $note;
        });
    }

    private function exactEuclideanSearch(string $queryVector)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Euclidean metric in pgvector uses the <-> operator, and lower distance is better.
        return collect(DB::select(
            <<<'SQL'
                SELECT id, title, body, created_at, updated_at, embedded_at,
                       embedding <-> ?::vector AS vector_distance
                FROM notes
                WHERE embedding IS NOT NULL
                ORDER BY embedding <-> ?::vector
                LIMIT 2
            SQL,
            [$queryVector, $queryVector],
        ))->map(function (object $row): Note {
            $note = new Note;
            $note->forceFill((array) $row);
            $note->exists = true;

            return $note;
        });
    }

    private function exactInnerProductSearch(string $queryVector)
    {
        // Exact strategy: compare the query vector with every stored note vector.
        // Inner Product in pgvector uses the <#> operator.
        // pgvector returns the negative inner product, so lower returned values rank better.
        return collect(DB::select(
            <<<'SQL'
                SELECT id, title, body, created_at, updated_at, embedded_at,
                       embedding <#> ?::vector AS vector_distance
                FROM notes
                WHERE embedding IS NOT NULL
                ORDER BY embedding <#> ?::vector
                LIMIT 2
            SQL,
            [$queryVector, $queryVector],
        ))->map(function (object $row): Note {
            $note = new Note;
            $note->forceFill((array) $row);
            $note->exists = true;

            return $note;
        });
    }

    private function annHnswCosineSearch(string $queryVector)
    {
        // ANN / HNSW strategy: PostgreSQL can use the HNSW cosine index on notes.embedding.
        // The metric is still cosine distance (<=>); HNSW changes how candidates are retrieved.
        return collect(DB::select(
            <<<'SQL'
                SELECT id, title, body, created_at, updated_at, embedded_at,
                       embedding <=> ?::vector AS vector_distance
                FROM notes
                WHERE embedding IS NOT NULL
                ORDER BY embedding <=> ?::vector
                LIMIT 2
            SQL,
            [$queryVector, $queryVector],
        ))->map(function (object $row): Note {
            $note = new Note;
            $note->forceFill((array) $row);
            $note->exists = true;

            return $note;
        });
    }
}
