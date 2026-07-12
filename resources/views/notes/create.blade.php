<x-layouts.app title="Create note">
    <div class="mb-6">
        <p class="text-sm font-medium uppercase text-zinc-500">Create</p>
        <h1 class="mt-1 text-3xl font-semibold">New note</h1>
    </div>

    <form method="POST" action="{{ route('notes.store') }}" class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
        @include('notes._form', ['buttonText' => 'Create note'])
    </form>

    <div class="mt-6">
        @include('notes._embedding_lesson', ['mode' => 'create'])
    </div>
</x-layouts.app>
