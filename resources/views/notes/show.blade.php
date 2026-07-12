<x-layouts.app :title="$note->title">
    <article class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start">
            <div>
                <p class="text-sm text-zinc-500">Created {{ $note->created_at->format('M j, Y g:i A') }}</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ $note->title }}</h1>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('notes.edit', $note) }}" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100">Edit</a>
                <form method="POST" action="{{ route('notes.destroy', $note) }}" onsubmit="return confirm('Delete this note?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                </form>
            </div>
        </div>

        <div class="prose prose-zinc mt-6 max-w-none whitespace-pre-line text-zinc-700">
            {{ $note->body }}
        </div>
    </article>

    <a href="{{ route('notes.index') }}" class="mt-6 inline-flex text-sm font-medium text-zinc-600 hover:text-zinc-950">Back to notes</a>
</x-layouts.app>
