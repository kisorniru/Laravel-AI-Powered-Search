<x-layouts.app title="Edit note">
    <div class="mb-6">
        <p class="text-sm font-medium uppercase text-zinc-500">Edit</p>
        <h1 class="mt-1 text-3xl font-semibold">{{ $note->title }}</h1>
    </div>

    <form method="POST" action="{{ route('notes.update', $note) }}" class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        @method('PUT')
        @include('notes._form', ['buttonText' => 'Save changes'])
    </form>

    <div class="mt-6">
        @include('notes._embedding_lesson', ['mode' => 'update'])
    </div>
</x-layouts.app>
