@csrf

<div>
    <label for="title" class="block text-sm font-medium text-zinc-700">Title</label>
    <input
        id="title"
        name="title"
        type="text"
        value="{{ old('title', $note->title ?? '') }}"
        class="mt-2 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
        required
    >
    @error('title')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-5">
    <label for="body" class="block text-sm font-medium text-zinc-700">Body</label>
    <textarea
        id="body"
        name="body"
        rows="9"
        class="mt-2 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-zinc-950 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10"
        required
    >{{ old('body', $note->body ?? '') }}</textarea>
    @error('body')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
        {{ $buttonText }}
    </button>
    <a href="{{ url()->previous() === url()->current() ? route('notes.index') : url()->previous() }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950">Cancel</a>
</div>
