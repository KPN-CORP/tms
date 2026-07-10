<x-app-layout>

    <x-slot name="header">
        Dashboard
    </x-slot>

    <div class="grid grid-cols-4 gap-4">

        @foreach($widgets as $widget)

            @includeIf($widget->view_path)

        @endforeach

    </div>

</x-app-layout>