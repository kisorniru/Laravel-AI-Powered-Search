<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Laravel Notes' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 text-zinc-950 antialiased">
        <div class="min-h-screen">
            <header class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-5">
                    <a href="{{ route('notes.index') }}" class="text-xl font-semibold">Laravel Notes</a>
                    <nav class="flex items-center gap-3 text-sm font-medium">
                        @auth
                            <span class="hidden text-zinc-600 sm:inline">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-zinc-700 hover:text-zinc-950">Logout</button>
                            </form>
                            <a href="{{ route('notes.create') }}" class="rounded-md bg-zinc-950 px-4 py-2 text-white hover:bg-zinc-800">New note</a>
                        @else
                            <a href="{{ route('login') }}" class="text-zinc-700 hover:text-zinc-950">Login</a>
                            <a href="{{ route('register') }}" class="text-zinc-700 hover:text-zinc-950">Register</a>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="mx-auto max-w-5xl px-6 py-8">
                @if (session('status'))
                    <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
