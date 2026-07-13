<x-layouts.app title="Register">
    <div class="mx-auto max-w-md">
        <div class="mb-6">
            <p class="text-sm font-medium uppercase text-zinc-500">Account</p>
            <h1 class="mt-1 text-3xl font-semibold">Create an account</h1>
            <p class="mt-2 text-zinc-600">Enter your details to register.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-zinc-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                @error('name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="email" class="block text-sm font-medium text-zinc-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="password" class="block text-sm font-medium text-zinc-700">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-5">
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-700">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-md border border-zinc-300 px-3 py-2 shadow-sm outline-none focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10">
            </div>

            <button type="submit" class="mt-6 w-full rounded-md bg-zinc-950 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Register</button>

            <p class="mt-5 text-center text-sm text-zinc-600">
                Already registered?
                <a href="{{ route('login') }}" class="font-medium text-zinc-950 hover:underline">Login</a>
            </p>
        </form>
    </div>
</x-layouts.app>
