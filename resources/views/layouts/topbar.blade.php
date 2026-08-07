<header class="bg-white border-b px-6 py-4 sticky top-0 z-30">

    <div class="flex justify-between items-center">

        <div>
            @if(isset($header))
                {{ $header }}
            @endif
        </div>

        <div class="flex items-center gap-4">

            <div class="text-right">

                <div class="font-semibold">
                    {{ Auth::user()->name }}
                </div>

                <!-- <div class="text-sm text-gray-500">
                    {{ Auth::user()->getRoleNames()->implode(', ') ?: '-' }}
                </div> -->

            </div>

            <div
                class="w-10 h-10 rounded-full bg-red-700 text-white flex items-center justify-center font-bold">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>

        </div>

    </div>

</header>