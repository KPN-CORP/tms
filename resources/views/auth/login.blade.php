<x-guest-layout>

    <div class="text-center mb-8">

        <div class="w-20 h-20 mx-auto bg-amber-400 rounded-xl flex items-center justify-center shadow-md">
            <span class="text-2xl font-bold text-white">
                TMS
            </span>
        </div>

        <h1 class="mt-6 text-3xl font-bold text-[#7B001C]">
            Transformation Management System
        </h1>

        <p class="mt-2 text-gray-500">
            Sign in to continue
        </p>

    </div>

    <x-auth-session-status
        class="mb-4"
        :status="session('status')"
    />

    <form method="POST" action="{{ route('login') }}">

        @csrf

        <!-- Email -->
        <div>
            <x-input-label
                for="email"
                value="Email"
            />

            <x-text-input
                id="email"
                class="block mt-1 w-full"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
            />

            <x-input-error
                :messages="$errors->get('email')"
                class="mt-2"
            />

            <x-input-error
                :messages=" "
        </div>

        <!-- Password -->
        <div class="mt-4">

            <x-input-label
                for="password"
                value="Password"
            />

            <x-text-input
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required
            />

            <x-input-error
                :messages="$errors->get('password')"
                class="mt-2"
            />

        </div>

        <div class="flex items-center justify-between mt-4">

            <label class="inline-flex items-center">

                <input
                    type="checkbox"
                    name="remember"
                    class="rounded border-gray-300"
                >

                <span class="ml-2 text-sm text-gray-600">
                    Remember Me
                </span>

            </label>

            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-[#7B001C] hover:underline"
                >
                    Forgot Password?
                </a>
            @endif

        </div>

        <button
            type="submit"
            class="w-full mt-6 bg-[#7B001C] hover:bg-[#660018] text-white py-3 rounded-lg font-semibold transition"
        >
            Login
        </button>

    </form>

</x-guest-layout>