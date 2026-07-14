# Laravel AI-Powered Notes Search

This project is a small Laravel learning app for understanding **AI-powered semantic search** step by step.

It started as a simple Notes CRUD app, then gradually added embeddings, pgvector storage, regular database search, Exact vector search, and ANN search with HNSW and IVFFlat.

This is **not RAG yet**. There is no LLM answer generation. The app currently retrieves similar notes by comparing embedding vectors.

## Current Features

- Notes CRUD: create, list, view, edit, and delete notes.
- Session-based registration and login.
- User-owned notes with public or private visibility.
- Hugging Face embeddings using `sentence-transformers/all-MiniLM-L6-v2`.
- PostgreSQL `pgvector` storage with `embedding vector(384)`.
- Regular database search using `ILIKE`.
- AI-powered semantic search by embedding the user query.
- Exact vector search:
  - Cosine distance with `<=>`
  - Euclidean distance with `<->`
  - Inner Product with `<#>`
- ANN vector search:
  - HNSW + Cosine using a pgvector HNSW index.
  - HNSW + Euclidean using a pgvector HNSW index.
  - HNSW + Inner Product using a pgvector HNSW index.
  - IVFFlat + Cosine using a pgvector IVFFlat index.
  - IVFFlat + Euclidean using a pgvector IVFFlat index.
  - IVFFlat + Inner Product using a pgvector IVFFlat index.
- PostgreSQL `EXPLAIN ANALYZE` inspection with timing, scan type, actual index, buffers, and a sanitized raw plan.
- Learning-focused UI explanations for embedding, strategy, metric, comparison, and retrieval.

## What This System Is Called

The best name for the current system is:

**AI-powered semantic search**

More technically:

**A Laravel notes CRUD app with Hugging Face embeddings, PostgreSQL pgvector storage, and vector search using Exact, ANN / HNSW, and ANN / IVFFlat retrieval.**

It is not RAG yet because RAG requires this extra step:

```text
retrieve relevant notes -> send them to an LLM as context -> generate an answer
```

This project currently stops at retrieval.

## Development Path So Far

### 1. Simple Notes CRUD

The first step was a normal Laravel CRUD app.

Main files:

- `app/Models/Note.php`
- `app/Http/Controllers/NoteController.php`
- `database/migrations/2026_07_12_192703_create_notes_table.php`
- `resources/views/notes/*.blade.php`
- `routes/web.php`

At this stage, the app only handled normal note data:

```text
title
body
```

### 2. Add Embedding Storage

The notes table was updated to support vectors:

```php
$table->vector('embedding', dimensions: 384)->nullable();
$table->timestamp('embedded_at')->nullable();
```

The migration also enables pgvector:

```php
DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
```

Current rule:

```text
1 note = 1 embedding vector
```

We are not chunking notes yet.

The embedded text currently contains:

```text
title + body + visibility + author name
```

### 3. Generate Embeddings With Hugging Face

The app uses:

```text
sentence-transformers/all-MiniLM-L6-v2
```

This model returns a 384-dimensional vector.

When a note is created or updated, the backend prepares this text:

```text
{title}

{body}
```

Then it sends that text to Hugging Face and stores the returned vector in PostgreSQL.

If the Hugging Face token is missing or the API fails, the note still saves. This keeps CRUD usable while learning.

### 4. Regular Database Search

Before AI search, regular search was added using normal SQL matching:

```text
title ILIKE %query%
OR body ILIKE %query%
```

This helps compare keyword search against vector search.

### 5. Embed The Search Query

For AI search, the user search text is also embedded:

```text
search query -> 384-dimensional query vector
```

Then the query vector is compared against stored note vectors.

### 6. Exact Vector Search

Exact search means:

```text
compare the query vector with every stored note vector
sort by best metric value
return the best 2 notes
```

Implemented exact metrics:

| Metric | pgvector operator | Meaning |
| --- | --- | --- |
| Cosine | `<=>` | Compares vector direction |
| Euclidean | `<->` | Compares straight-line distance |
| Inner Product | `<#>` | Compares alignment and magnitude |

Important note for Inner Product:

```text
pgvector returns negative inner product for <#>
lower returned value = stronger inner product match
```

### 7. ANN / HNSW + Cosine, Euclidean, And Inner Product

ANN means Approximate Nearest Neighbor.

HNSW is the first ANN strategy implemented.

The HNSW cosine index was added with a separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_cosine_index
ON notes USING hnsw (embedding vector_cosine_ops);
```

The HNSW Euclidean/L2 index was added with another separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_euclidean_index
ON notes USING hnsw (embedding vector_l2_ops);
```

The HNSW Inner Product index was added with another separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_inner_product_index
ON notes USING hnsw (embedding vector_ip_ops);
```

Current ANN support:

| Strategy | Metric | Status |
| --- | --- | --- |
| ANN / HNSW | Cosine | Implemented |
| ANN / HNSW | Euclidean | Implemented |
| ANN / HNSW | Inner Product | Implemented |
| ANN / IVFFlat | Cosine | Implemented |
| ANN / IVFFlat | Euclidean | Implemented |
| ANN / IVFFlat | Inner Product | Implemented |

The SQL still uses the selected distance operator:

```sql
ORDER BY embedding <=> ?::vector
LIMIT 2
```

or:

```sql
ORDER BY embedding <-> ?::vector
LIMIT 2
```

or:

```sql
ORDER BY embedding <#> ?::vector
LIMIT 2
```

The difference is that PostgreSQL now has HNSW index paths available for cosine, Euclidean, and Inner Product vector search.

### 8. ANN / IVFFlat + Cosine

IVFFlat is the second ANN strategy implemented.

The IVFFlat cosine index was added with a separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_cosine_index
ON notes USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 10);
```

Current IVFFlat support:

| Strategy | Metric | Status |
| --- | --- | --- |
| ANN / IVFFlat | Cosine | Implemented |
| ANN / IVFFlat | Euclidean | Implemented |
| ANN / IVFFlat | Inner Product | Implemented |

The IVFFlat Euclidean/L2 index was added with another separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_euclidean_index
ON notes USING ivfflat (embedding vector_l2_ops)
WITH (lists = 10);
```

The IVFFlat Inner Product index was added with another separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_inner_product_index
ON notes USING ivfflat (embedding vector_ip_ops)
WITH (lists = 10);
```

The SQL still uses cosine distance:

```sql
ORDER BY embedding <=> ?::vector
LIMIT 2
```

or Euclidean distance:

```sql
ORDER BY embedding <-> ?::vector
LIMIT 2
```

or negative inner product:

```sql
ORDER BY embedding <#> ?::vector
LIMIT 2
```

The difference is that PostgreSQL now has IVFFlat index paths available for cosine, Euclidean, and Inner Product vector search. IVFFlat groups vectors into inverted lists, then searches nearby lists instead of exhaustively scanning every vector.

### 9. Distance Thresholds

Vector search now filters weak matches before returning results.

Current thresholds:

| Metric | Threshold | Meaning |
| --- | --- | --- |
| Cosine | `<= 0.85` | Hide results that are too far by cosine distance |
| Euclidean | `<= 0.5` | Hide results that are too far by straight-line distance |
| Inner Product | none yet | Uses negative inner product, so it needs a separate threshold rule |

This means the app no longer always returns 2 notes. It returns up to 2 notes that pass the selected metric threshold.

### 10. Inspect The Actual Plan With EXPLAIN ANALYZE

After an AI search, the UI now offers **Run EXPLAIN ANALYZE**. It executes the same read-only vector `SELECT` and reports:

- PostgreSQL planning time.
- Actual execution time.
- Scan types such as `Seq Scan` or `Index Scan`.
- The actual index name selected by PostgreSQL.
- Buffer activity in the sanitized raw plan.

The full 384-dimensional query vector is removed from the displayed plan.

This step reveals an important distinction:

```text
the UI selects a requested experiment
PostgreSQL's planner selects the physical execution plan
EXPLAIN ANALYZE provides the evidence of what actually ran
```

Because HNSW and IVFFlat indexes can exist for the same vector operator, PostgreSQL may choose a different plan than the UI label suggests. This finding will guide the upcoming strategy-comparison implementation.

## Backend Flow

### When A User Creates Or Updates A Note

```text
1. User submits title, body, and visibility.
2. Laravel validates the request.
3. Laravel saves the note first.
4. Laravel builds the embedding text from title, body, visibility, and author name.
5. Laravel calls Hugging Face.
6. Hugging Face returns a 384-dimensional vector.
7. Laravel stores the vector in notes.embedding.
8. Laravel stores the time in notes.embedded_at.
```

If embedding fails:

```text
the note is still saved
embedding stays empty
the user sees a flash message
```

### When A User Runs Regular Search

```text
1. User types search text.
2. Laravel chooses the searchable notes: public notes for guests, or only the logged-in user's notes.
3. Laravel searches title/body with ILIKE.
4. Matching notes are returned.
5. No embedding API call happens.
```

### When A User Runs AI Search

```text
1. User types search text.
2. User chooses a strategy: Exact, ANN / HNSW, or ANN / IVFFlat.
3. User chooses a metric: Cosine, Euclidean, or Inner Product.
4. Laravel embeds the search query.
5. Laravel compares the query vector with stored note vectors.
6. Laravel filters weak matches using the metric distance threshold.
7. Laravel returns up to the best 2 notes.
```

Only implemented strategy and metric combinations run. Unsupported combinations show a learning message instead of failing.

## Environment

This project expects PostgreSQL with pgvector.

Example `.env` values:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_ai_powered_search
DB_USERNAME=root
DB_PASSWORD=

HUGGINGFACE_API_TOKEN=
HUGGINGFACE_EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2
```

Do not commit real API tokens.

## pgvector Setup

Install pgvector for your PostgreSQL version.

Example:

```bash
sudo apt-get install -y postgresql-16-pgvector
```

Then run migrations:

```bash
php artisan migrate
```

For a clean learning reset:

```bash
php artisan migrate:fresh
```

## Local Development

Install dependencies:

```bash
composer install
npm install
```

Create env file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

Run migrations:

```bash
php artisan migrate
```

Reset and seed demo users, notes, and embeddings:

```bash
php artisan migrate:refresh --seed
```

Demo accounts:

| Name | Email | Password |
| --- | --- | --- |
| Mr. Jhon | `jhon@email.com` | `12345678` |
| Mr. Sina | `sina@email.com` | `12345678` |

The seeder always creates 40 curated Bangladesh-context notes: 20 for each user, with 10 public and 10 private notes per user. If `HUGGINGFACE_API_TOKEN` is configured, the seeder generates embeddings for these curated notes.

For IVFFlat practice, you can generate extra factory notes:

```env
SEED_FACTORY_NOTES_PER_USER=500
SEED_FACTORY_NOTES_WITH_EMBEDDINGS=false
```

This example creates 1,000 extra notes total: 500 for Mr. Jhon and 500 for Mr. Sina. Keep `SEED_FACTORY_NOTES_WITH_EMBEDDINGS=false` if you only want rows. Set it to `true` when you are ready to make embedding API calls for the generated notes.

Start development:

```bash
composer run dev
```

Or run Laravel and Vite separately:

```bash
php artisan serve
npm run dev
```

## Testing

Run the PHP tests:

```bash
php artisan test
```

Format PHP code:

```bash
./vendor/bin/pint --dirty
```

Build frontend assets:

```bash
npm run build
```

The test suite expects the configured PostgreSQL database and pgvector extension to be available.

## Learning Roadmap From Here

Recommended next steps:

1. Compare Exact vs HNSW vs IVFFlat results and actual execution plans.
2. Learn HNSW `ef_search` and IVFFlat `probes` tuning.
3. Add chunking later:

```text
1 note = many chunks = many vectors
```

4. Add RAG later:

```text
retrieve relevant chunks -> send context to LLM -> generate grounded answer
```

For now, this project is intentionally focused on understanding search, embeddings, vectors, metrics, and retrieval before adding LLM generation.
