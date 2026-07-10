@php
    $activeRole = session('active_role');
@endphp

<aside class="w-64 bg-white border-r border-gray-200 min-h-screen flex flex-col">

    {{-- Logo --}}
    <div class="px-6 py-5 border-b">

        <h1 class="text-2xl font-bold text-red-700">
            TMS
        </h1>

        <p class="text-sm text-gray-500">
            Transformation Management System
        </p>

        @if($activeRole)
            <div class="mt-3">
                <span class="inline-flex px-3 py-1 text-xs rounded-full bg-red-100 text-red-700">
                    {{ ucwords(str_replace('-', ' ', $activeRole)) }}
                </span>
            </div>
        @endif

    </div>

    {{-- Dynamic Menu --}}
    <nav class="flex-1 p-4">

        @forelse($sidebarMenus as $menu)

            <a
                href="{{ $menu->route_name && Route::has($menu->route_name)
                    ? route($menu->route_name)
                    : '#'
                }}"
                class="flex items-center px-4 py-3 rounded-xl mb-2 transition

                {{
                    $menu->route_name &&
                    request()->routeIs($menu->route_name)

                    ? 'bg-red-50 text-red-700 font-semibold'

                    : 'hover:bg-gray-100 text-gray-700'
                }}"
            >

                {{-- Future Icon --}}
                @if(!empty($menu->icon))
                    <span class="mr-3">
                        {!! $menu->icon !!}
                    </span>
                @endif

                <span>
                    {{ $menu->name }}
                </span>

            </a>

        @empty

            <div class="px-4 py-3 text-sm text-gray-400">
                No menu assigned
            </div>

        @endforelse

    </nav>

    {{-- Footer --}}
    <div class="border-t p-4">

        <a
            href="{{ route('profile.edit') }}"
            class="block px-4 py-3 rounded-xl hover:bg-gray-100 mb-2"
        >
            Profile
        </a>

        {{-- Function Owner --}}
        @if(Auth::user()->hasRole('function-owner'))

            <a
                href="{{ route('select-role') }}"
                class="block px-4 py-3 rounded-xl hover:bg-gray-100 mb-2"
            >
                Switch Role
            </a>

        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="w-full text-left px-4 py-3 rounded-xl text-red-600 hover:bg-red-50"
            >
                Logout
            </button>

        </form>

    </div>

</aside>