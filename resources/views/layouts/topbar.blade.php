<header class="bg-white border-b px-6 py-4 sticky top-0 z-30">

    <div class="flex justify-between items-center">

        <div class="flex items-center gap-3">
            <button type="button" @click="sidebarOpen = !sidebarOpen"
                    class="p-2 rounded-lg hover:bg-gray-100 text-gray-600"
                    :aria-expanded="sidebarOpen" aria-label="Toggle sidebar" title="Toggle menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
            <div>
                @if(isset($header))
                    {{ $header }}
                @endif
            </div>
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