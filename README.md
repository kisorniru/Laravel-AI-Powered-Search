# Laravel AI-Powered Notes Search

This project is a small Laravel learning app for understanding **AI-powered semantic search** step by step.

It started as a simple Notes CRUD app, then gradually added embeddings, pgvector storage, regular database search, exact vector search, and ANN / HNSW vector search.

This is **not RAG yet**. There is no LLM answer generation. The app currently retrieves similar notes by comparing embedding vectors.

## Current Features

- Notes CRUD: create, list, view, edit, and delete notes.
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
- Learning-focused UI explanations for embedding, strategy, metric, comparison, and retrieval.

## What This System Is Called

The best name for the current system is:

**AI-powered semantic search**

More technically:

**A Laravel notes CRUD app with Hugging Face embeddings, PostgreSQL pgvector storage, and vector search using Exact and ANN / HNSW retrieval.**

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

### 7. ANN / HNSW + Cosine

ANN means Approximate Nearest Neighbor.

HNSW is the first ANN strategy implemented.

The HNSW cosine index was added with a separate migration:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_cosine_index
ON notes USING hnsw (embedding vector_cosine_ops);
```

Current ANN support:

| Strategy | Metric | Status |
| --- | --- | --- |
| ANN / HNSW | Cosine | Implemented |
| ANN / HNSW | Euclidean | Not implemented yet |
| ANN / HNSW | Inner Product | Not implemented yet |
| ANN / IVFFlat | Any metric | Not implemented yet |

The SQL still uses cosine distance:

```sql
ORDER BY embedding <=> ?::vector
LIMIT 2
```

The difference is that PostgreSQL now has an HNSW index path available for cosine vector search.

## Backend Flow

### When A User Creates Or Updates A Note

```text
1. User submits title and body.
2. Laravel validates the request.
3. Laravel saves the note first.
4. Laravel builds the embedding text from title + body.
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
2. Laravel searches title/body with ILIKE.
3. Matching notes are returned.
4. No embedding API call happens.
```

### When A User Runs AI Search

```text
1. User types search text.
2. User chooses a strategy: Exact or ANN / HNSW.
3. User chooses a metric: Cosine, Euclidean, or Inner Product.
4. Laravel embeds the search query.
5. Laravel compares the query vector with stored note vectors.
6. Laravel returns the best 2 notes.
```

Only implemented strategy and metric combinations run. Unsupported combinations show a learning message instead of failing.

## Environment

This project expects PostgreSQL with pgvector.

Example `.env` values:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel_llm_app
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

Last verified state:

```text
php artisan test: 13 tests passing
npm run build: passing
```

## Learning Roadmap From Here

Recommended next steps:

1. Implement **ANN / HNSW + Euclidean**.
2. Implement **ANN / HNSW + Inner Product**.
3. Learn how to inspect query plans with `EXPLAIN`.
4. Add **ANN / IVFFlat** after HNSW metrics are complete.
5. Compare Exact vs HNSW vs IVFFlat behavior.
6. Add chunking later:

```text
1 note = many chunks = many vectors
```

7. Add RAG later:

```text
retrieve relevant chunks -> send context to LLM -> generate grounded answer
```

For now, this project is intentionally focused on understanding search, embeddings, vectors, metrics, and retrieval before adding LLM generation.
