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

<fieldset class="mt-5">
    <legend class="text-sm font-medium text-zinc-700">Visibility</legend>
    <div class="mt-2 grid gap-3 sm:grid-cols-2">
        <label class="flex cursor-pointer gap-3 rounded-md border border-zinc-300 bg-white p-4">
            <input
                name="visibility"
                type="radio"
                value="public"
                class="mt-1"
                @checked(old('visibility', isset($note) && ! $note->is_public ? 'private' : 'public') === 'public')
            >
            <span>
                <span class="block font-medium text-zinc-950">Public</span>
                <span class="mt-1 block text-sm text-zinc-600">Everyone can view and search this note.</span>
            </span>
        </label>
        <label class="flex cursor-pointer gap-3 rounded-md border border-zinc-300 bg-white p-4">
            <input
                name="visibility"
                type="radio"
                value="private"
                class="mt-1"
                @checked(old('visibility', isset($note) && ! $note->is_public ? 'private' : 'public') === 'private')
            >
            <span>
                <span class="block font-medium text-zinc-950">Private</span>
                <span class="mt-1 block text-sm text-zinc-600">Only you can view and search this note.</span>
            </span>
        </label>
    </div>
    @error('visibility')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
</fieldset>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
        {{ $buttonText }}
    </button>
    <a href="{{ url()->previous() === url()->current() ? route('notes.index') : url()->previous() }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950">Cancel</a>
</div>
