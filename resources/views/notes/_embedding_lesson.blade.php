<section class="rounded-lg border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950">
    <p class="font-semibold">What happens after you {{ $mode }} this note?</p>
    <div class="mt-3 grid gap-3 md:grid-cols-2">
        <div class="rounded-md border border-sky-200 bg-white p-3">
            <p class="font-medium">1. Laravel saves the note</p>
            <p class="mt-1 text-sky-800">The title and body are stored in PostgreSQL first, so the note is not lost if embedding fails.</p>
        </div>
        <div class="rounded-md border border-sky-200 bg-white p-3">
            <p class="font-medium">2. Text is prepared</p>
            <p class="mt-1 text-sky-800">Laravel prepares the title, body, visibility, and author name as the text that will become an embedding.</p>
        </div>
        <div class="rounded-md border border-sky-200 bg-white p-3">
            <p class="font-medium">3. Hugging Face returns a vector</p>
            <p class="mt-1 text-sky-800">The model returns 384 numbers. A tiny glimpse looks like this:</p>
            <code class="mt-2 block rounded bg-zinc-50 p-2 text-xs text-zinc-700">[0.0241, -0.1184, 0.0037, 0.0912, ...]</code>
        </div>
        <div class="rounded-md border border-sky-200 bg-white p-3">
            <p class="font-medium">4. pgvector stores it</p>
            <p class="mt-1 text-sky-800">The vector is saved in the <code>notes.embedding</code> column and later used for AI search.</p>
        </div>
    </div>
</section>
