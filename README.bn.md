# Laravel AI-Powered Notes Search: বাংলা লার্নিং নোট

এই প্রজেক্টটি একটি ছোট Laravel learning app। মূল লক্ষ্য হলো ধাপে ধাপে বোঝা:

- সাধারণ CRUD কীভাবে কাজ করে
- text থেকে embedding vector কীভাবে তৈরি হয়
- PostgreSQL + pgvector দিয়ে vector কীভাবে store করা হয়
- keyword search এবং semantic search এর পার্থক্য কী
- Exact search, ANN / HNSW, এবং ANN / IVFFlat কীভাবে কাজ করে
- Cosine, Euclidean, এবং Inner Product metric দিয়ে similarity কীভাবে মাপা হয়

এই প্রজেক্ট এখনো **RAG নয়**। এখানে কোনো LLM answer generation নেই। এখন পর্যন্ত সিস্টেমটি শুধু relevant notes retrieve করে।

সহজ ভাষায় বর্তমান সিস্টেমের নাম:

```text
AI-powered semantic search system
```

আরও technical ভাবে:

```text
Laravel notes CRUD app with Hugging Face embeddings, PostgreSQL pgvector storage,
and vector search using Exact, ANN / HNSW, and ANN / IVFFlat retrieval.
```

## এখন পর্যন্ত কী কী আছে

- Notes CRUD: create, list, view, edit, delete
- Registration এবং login
- User-owned notes
- Public/private visibility
- Hugging Face embedding model:

```text
sentence-transformers/all-MiniLM-L6-v2
```

- `384` dimensional embedding vector
- PostgreSQL `pgvector`
- `notes.embedding vector(384)`
- Regular database search using `ILIKE`
- AI search by embedding the user query
- Exact vector search
- ANN / HNSW vector search
- ANN / IVFFlat vector search
- Distance threshold for Cosine and Euclidean
- Learning-focused UI explanations
- Demo users, curated seed notes, and optional factory-generated notes

## শেখার পথ

### 1. সাধারণ Notes CRUD

প্রথমে এই প্রজেক্টটি ছিল একটি simple Notes CRUD application।

এই stage-এ note table মূলত এই data রাখত:

```text
title
body
```

ব্যবহারকারী note create, update, delete, list, এবং view করতে পারত।

এই foundation খুব গুরুত্বপূর্ণ, কারণ AI search শেখার আগে normal data flow বোঝা দরকার।

### 2. Embedding storage যোগ করা

এরপর notes table-এ vector রাখার জন্য field যোগ করা হয়েছে:

```php
$table->vector('embedding', dimensions: 384)->nullable();
$table->timestamp('embedded_at')->nullable();
```

pgvector extension enable করা হয়েছে:

```php
DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
```

বর্তমান নিয়ম:

```text
1 note = 1 embedding vector
```

এখনো chunking করা হয়নি।

### 3. Hugging Face দিয়ে note embedding তৈরি করা

যখন user note create বা update করে, backend এই কাজগুলো করে:

```text
1. User title, body, visibility submit করে।
2. Laravel request validate করে।
3. Note আগে database-এ save হয়।
4. Note থেকে embedding text তৈরি হয়।
5. Laravel Hugging Face API call করে।
6. Hugging Face 384-dimensional vector return করে।
7. Laravel সেই vector notes.embedding column-এ save করে।
8. embedded_at timestamp update হয়।
```

বর্তমানে embedding text তৈরি হয় note content, visibility, এবং author দিয়ে:

```text
Title: {title}

Body:
{body}

Visibility: Public/Private

Author: {author name}
```

যদি Hugging Face token না থাকে বা API fail করে:

```text
note save হবে
embedding empty থাকবে
user flash message দেখবে
CRUD break হবে না
```

এটা learning project-এর জন্য ভালো, কারণ embedding fail করলেও app ব্যবহার করা যায়।

### 4. Regular database search

AI search করার আগে simple database search করা হয়েছে।

Regular search শুধু title/body এর মধ্যে text match করে:

```sql
title ILIKE %query%
OR body ILIKE %query%
```

এটি keyword search।

উদাহরণ:

```text
Search: friends
```

Regular search তখন only সেই notes খুঁজবে যেখানে সরাসরি `friends` শব্দটি আছে।

### 5. AI search query embedding

AI search-এ user-এর search text-ও embedding করা হয়।

Flow:

```text
search query -> Hugging Face -> 384-dimensional query vector
```

এরপর query vector compare করা হয় stored note vectors এর সাথে।

এখানেই keyword search থেকে semantic search আলাদা হয়ে যায়।

Regular search শব্দ খোঁজে।

AI search অর্থ বা meaning অনুযায়ী কাছাকাছি note খোঁজে।

## Search strategy এবং metric

এই project-এ strategy এবং metric আলাদা করে শেখা হচ্ছে।

Strategy মানে:

```text
কীভাবে candidate vector খোঁজা হবে
```

Metric মানে:

```text
দুটি vector কতটা similar বা close, সেটা কী নিয়মে মাপা হবে
```

## Implemented search matrix

বর্তমানে matrix সম্পূর্ণ হয়েছে:

| Strategy | Cosine | Euclidean | Inner Product |
| --- | --- | --- | --- |
| Exact | Done | Done | Done |
| ANN / HNSW | Done | Done | Done |
| ANN / IVFFlat | Done | Done | Done |

## Exact search

Exact search সবচেয়ে সহজভাবে বোঝা যায়।

এটি করে:

```text
query vector কে database-এর প্রতিটি stored note vector এর সাথে compare করে
metric অনুযায়ী sort করে
best 2 result return করে
```

এটি accurate, কিন্তু data অনেক বড় হলে slow হতে পারে।

Implemented Exact metrics:

| Metric | pgvector operator | সহজ ব্যাখ্যা |
| --- | --- | --- |
| Cosine | `<=>` | vector direction compare করে |
| Euclidean | `<->` | straight-line distance compare করে |
| Inner Product | `<#>` | alignment এবং magnitude compare করে |

Inner Product নিয়ে গুরুত্বপূর্ণ বিষয়:

```text
pgvector <#> operator negative inner product return করে।
তাই lower returned value মানে stronger match।
```

## ANN / HNSW

ANN মানে:

```text
Approximate Nearest Neighbor
```

HNSW মানে:

```text
Hierarchical Navigable Small World
```

HNSW সব vector exhaustively scan না করে index graph ব্যবহার করে likely nearest vectors খুঁজে।

এটি exact search থেকে দ্রুত হতে পারে, বিশেষ করে data বড় হলে।

HNSW indexes:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_cosine_index
ON notes USING hnsw (embedding vector_cosine_ops);
```

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_euclidean_index
ON notes USING hnsw (embedding vector_l2_ops);
```

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_hnsw_inner_product_index
ON notes USING hnsw (embedding vector_ip_ops);
```

HNSW strategy বদলায় candidate retrieval process।

Metric operator একই থাকে:

```sql
ORDER BY embedding <=> ?::vector
```

অথবা:

```sql
ORDER BY embedding <-> ?::vector
```

অথবা:

```sql
ORDER BY embedding <#> ?::vector
```

## ANN / IVFFlat

IVFFlat হলো আরেকটি ANN strategy।

IVFFlat vector গুলোকে inverted lists বা clusters-এ ভাগ করে।

Search করার সময় সব vector না দেখে কাছাকাছি list বা cluster search করে।

IVFFlat indexes:

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_cosine_index
ON notes USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 10);
```

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_euclidean_index
ON notes USING ivfflat (embedding vector_l2_ops)
WITH (lists = 10);
```

```sql
CREATE INDEX IF NOT EXISTS notes_embedding_ivfflat_inner_product_index
ON notes USING ivfflat (embedding vector_ip_ops)
WITH (lists = 10);
```

এখানে `lists = 10` মানে pgvector vector space-কে 10টি inverted list বা cluster-এ organize করবে।

Learning project হিসেবে এটা ছোট value রাখা হয়েছে। Real large dataset হলে `lists` tuning করতে হয়।

## Distance threshold

আগে AI search সবসময় best 2 return করত।

সমস্যা হলো: query খুব unrelated হলেও system forced best 2 দেখাতে পারে।

তাই distance threshold যোগ করা হয়েছে।

Current threshold:

| Metric | Threshold | অর্থ |
| --- | --- | --- |
| Cosine | `<= 0.85` | বেশি দূরের cosine match hide করে |
| Euclidean | `<= 0.5` | বেশি দূরের straight-line match hide করে |
| Inner Product | none yet | negative score হওয়ায় আলাদা rule দরকার |

এর ফলে app এখন:

```text
best 2 এর মধ্যে শুধু sufficiently close result দেখায়
```

তাই কখনো 2টির কম result আসতে পারে।

## Visibility কীভাবে কাজ করে

Note-এর visibility দুই রকম:

```text
Public
Private
```

Search visibility rule:

```text
Guest user -> শুধু public notes দেখতে পাবে
Logged-in user -> শুধু নিজের notes দেখতে পাবে
```

বর্তমানে visibility embedding text-এর মধ্যেও রাখা হয়েছে।

অর্থাৎ vector তৈরি করার সময় model note-এর content ছাড়াও note public/private কিনা এবং author কে, সেটাও embedding context হিসেবে পায়।

Learning-এর জন্য এটা রাখা হয়েছে। Later advanced version-এ visibility সাধারণত embedding-এর বাইরে filter হিসেবে রাখা ভালো হতে পারে।

## Backend flow: note create/update

যখন user note create/update করে:

```text
Browser form submit করে
        |
        v
routes/web.php request NoteController-এ পাঠায়
        |
        v
NoteController validate করে
        |
        v
Note database-এ save/update হয়
        |
        v
textToEmbed() embedding text তৈরি করে
        |
        v
HuggingFaceEmbeddingService API call করে
        |
        v
384-dimensional vector পাওয়া যায়
        |
        v
notes.embedding এবং notes.embedded_at update হয়
```

সংশ্লিষ্ট important files:

- `routes/web.php`
- `app/Http/Controllers/NoteController.php`
- `app/Models/Note.php`
- `app/Services/HuggingFaceEmbeddingService.php`
- `database/migrations/2026_07_12_192703_create_notes_table.php`

## Backend flow: regular search

Regular search flow:

```text
User search text লিখে
        |
        v
Regular search button click করে
        |
        v
/notes route hit হয়
        |
        v
NoteController@index runs
        |
        v
regularSearch() title/body ILIKE দিয়ে match করে
        |
        v
visibility filter apply হয়
        |
        v
matching notes page-এ দেখায়
```

এখানে Hugging Face API call হয় না।

এটি normal database keyword search।

## Backend flow: AI search

AI search flow:

```text
User search text লিখে
        |
        v
AI search button click করে
        |
        v
Strategy select করে: Exact / ANN HNSW / ANN IVFFlat
        |
        v
Metric select করে: Cosine / Euclidean / Inner Product
        |
        v
/notes/ai-search route hit হয়
        |
        v
NoteController@vectorSearch runs
        |
        v
search query embedding হয়
        |
        v
query vector তৈরি হয়
        |
        v
selected strategy + metric অনুযায়ী vector search হয়
        |
        v
visibility filter apply হয়
        |
        v
distance threshold apply হয়, যদি থাকে
        |
        v
best 2 notes return হয়
```

## Demo users

Seeder দুইজন user তৈরি করে:

| Name | Email | Password |
| --- | --- | --- |
| Mr. Jhon | `jhon@email.com` | `12345678` |
| Mr. Sina | `sina@email.com` | `12345678` |

Seeder 40টি curated Bangladesh-context notes তৈরি করে:

```text
Mr. Jhon -> 20 notes
Mr. Sina -> 20 notes
```

প্রতি user-এর:

```text
10 public notes
10 private notes
```

যদি `HUGGINGFACE_API_TOKEN` configured থাকে, seeder curated notes-এর embedding তৈরি করে।

## Factory notes

IVFFlat শেখার জন্য data বেশি দরকার।

তাই factory যোগ করা হয়েছে।

Extra factory notes generate করতে `.env` এ ব্যবহার করা যায়:

```env
SEED_FACTORY_NOTES_PER_USER=500
SEED_FACTORY_NOTES_WITH_EMBEDDINGS=false
```

এতে total 1000 extra notes তৈরি হবে:

```text
500 for Mr. Jhon
500 for Mr. Sina
```

যদি embedding-ও generate করতে চান:

```env
SEED_FACTORY_NOTES_WITH_EMBEDDINGS=true
```

কিন্তু মনে রাখতে হবে, এতে Hugging Face API call অনেক বেশি হবে।

## Local setup

Dependencies install:

```bash
composer install
npm install
```

Environment তৈরি:

```bash
cp .env.example .env
php artisan key:generate
```

Migration:

```bash
php artisan migrate
```

Clean reset with seed:

```bash
php artisan migrate:refresh --seed
```

Development server:

```bash
php artisan serve
npm run dev
```

অথবা:

```bash
composer run dev
```

## Required environment values

PostgreSQL + pgvector দরকার।

Example:

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

Real token commit করা যাবে না।

## pgvector setup

PostgreSQL version অনুযায়ী pgvector install করতে হবে।

Example:

```bash
sudo apt-get install -y postgresql-16-pgvector
```

তারপর:

```bash
php artisan migrate
```

## Testing and build

PHP tests:

```bash
php artisan test
```

PHP formatting:

```bash
./vendor/bin/pint --dirty
```

Frontend build:

```bash
npm run build
```

## গুরুত্বপূর্ণ ধারণা

### Keyword search

```text
শব্দ মিলে কিনা দেখে
```

Example:

```text
friends লিখলে notes-এ friends শব্দ থাকলে match করবে
```

### Semantic search

```text
অর্থ কাছাকাছি কিনা দেখে
```

Example:

```text
friends লিখলে weekend plan, adda, university, travel with cousins টাইপ note আসতে পারে,
কারণ meaning কাছাকাছি।
```

### Embedding

```text
Text কে number vector-এ convert করা
```

এই app-এ:

```text
text -> 384-dimensional vector
```

### Vector database

এখানে আলাদা vector database ব্যবহার করা হয়নি।

PostgreSQL + pgvector ব্যবহার করা হয়েছে।

মানে:

```text
normal relational database + vector search capability
```

### Retrieval

Retrieval মানে:

```text
query অনুযায়ী relevant notes খুঁজে বের করা
```

এই project এখন retrieval পর্যন্ত আছে।

### RAG

RAG মানে:

```text
Retrieve relevant context
        |
        v
Send context to LLM
        |
        v
Generate grounded answer
```

এই app এখনো RAG নয়, কারণ এখানে LLM answer generation নেই।

## Chunking নিয়ে বর্তমান সিদ্ধান্ত

বর্তমানে chunking করা হয়নি।

এখন:

```text
1 note = 1 vector
```

Chunking করলে হবে:

```text
1 note = many chunks = many vectors
```

Chunking আলাদা কোনো magic feature নয়। Embedding design করার সময়ই chunking strategy নিতে হয়।

আমাদের learning path:

```text
first finish retrieval strategies
then learn chunking
then later learn RAG
```

## পরবর্তী শেখার roadmap

Recommended next steps:

1. `EXPLAIN` এবং `EXPLAIN ANALYZE` দিয়ে query plan দেখা।
2. Exact vs HNSW vs IVFFlat performance compare করা।
3. HNSW tuning শেখা।
4. IVFFlat `lists` এবং `probes` tuning শেখা।
5. Inner Product threshold design করা।
6. Chunking implement করা।
7. `note_chunks` table তৈরি করা।
8. Chunk-level vector search করা।
9. Retrieval result দিয়ে RAG বানানো।
10. Queue ব্যবহার করে embedding generation background job-এ নেওয়া।

## সবচেয়ে সংক্ষিপ্ত summary

এই project এখন শেখাচ্ছে:

```text
CRUD -> Embedding -> pgvector storage -> Regular search -> Semantic search
-> Exact search -> ANN / HNSW -> ANN / IVFFlat -> Retrieval understanding
```

এখনো শেখা বাকি:

```text
EXPLAIN analysis -> tuning -> chunking -> RAG
```

এই project-এর আসল শক্তি হলো, এখানে AI search এক লাফে করা হয়নি। প্রতিটি concept আলাদা করে implement করা হয়েছে, যাতে backend-এ আসলে কী ঘটছে সেটা চোখে দেখা যায়।
