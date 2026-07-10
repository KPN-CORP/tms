<x-guest-layout>

<div class="w-full max-w-xl bg-white rounded-2xl shadow-lg overflow-hidden">

    <div class="bg-[#8B0015] text-white text-center py-8">

        <h1 class="text-3xl font-bold">
            Select Your Role
        </h1>

        <p class="mt-2 text-sm">
            Please select how you want to proceed
        </p>

    </div>

    <div class="grid grid-cols-2 gap-4 p-6">

        <a
            href="{{ route('go.employee') }}"
            class="border rounded-xl p-6 hover:bg-blue-50 transition"
        >
            <h2 class="font-bold text-lg">
                Employee
            </h2>

            <p class="text-sm text-gray-500">
                Submit ideas & manage projects
            </p>
        </a>

        <a
            href="{{ route('go.committee') }}"
            class="border rounded-xl p-6 hover:bg-yellow-50 transition"
        >
            <h2 class="font-bold text-lg">
                Committee
            </h2>

            <p class="text-sm text-gray-500">
                Review ideas & manage approvals
            </p>
        </a>

    </div>

</div>

</x-guest-layout>