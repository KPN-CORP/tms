<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Create Project Shell</h2>
    </x-slot>

    <div class="p-6 space-y-6 max-w-4xl">

        <div>
            <a href="{{ route('projects.approved-ideas') }}" class="text-sm text-gray-500 hover:text-red-700">&larr; Back to Approved Ideas</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">Create Project Shell</h1>
            <p class="text-gray-500">Dari ide: <span class="font-mono text-red-700">{{ $idea->idea_id }}</span> — {{ $idea->idea_name }}</p>
        </div>

        <form method="POST" action="{{ route('projects.store') }}" class="bg-white rounded-xl shadow p-8 space-y-5">
            @csrf
            <input type="hidden" name="idea_id" value="{{ $idea->idea_id }}">

            <div>
                <label class="block font-semibold mb-1">Project Name <span class="text-red-600">*</span></label>
                <input type="text" name="project_name" value="{{ old('project_name', $idea->idea_name) }}"
                       class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('project_name') border-red-500 @enderror">
                @error('project_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Project Category <span class="text-red-600">*</span></label>
                    <select name="project_category_id"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('project_category_id') border-red-500 @enderror">
                        <option value="">Select Category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('project_category_id') === (string) $cat->id)>{{ $cat->name }} ({{ $cat->code }}) — Leader {{ $cat->gradeRangeText($cat->leader_grade_min, $cat->leader_grade_max) }}, Sponsor {{ $cat->gradeRangeText($cat->sponsor_grade_min, $cat->sponsor_grade_max) }}@if($cat->max_team_members), Tim ≤ {{ $cat->max_team_members }}@endif</option>
                        @endforeach
                    </select>
                    @error('project_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block font-semibold mb-1">Project Scope <span class="text-red-600">*</span></label>
                <textarea name="project_scope" rows="3" placeholder="Ruang lingkup project"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('project_scope') border-red-500 @enderror">{{ old('project_scope') }}</textarea>
                @error('project_scope')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block font-semibold mb-1">Expected Outcome <span class="text-red-600">*</span></label>
                <textarea name="expected_outcome" rows="3"
                          class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('expected_outcome') border-red-500 @enderror">{{ old('expected_outcome', $idea->expected_outcome) }}</textarea>
                @error('expected_outcome')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold mb-1">Project Sponsor <span class="text-red-600">*</span></label>
                    <select name="project_sponsor_id"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('project_sponsor_id') border-red-500 @enderror">
                        <option value="">Select Sponsor</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((string) old('project_sponsor_id') === (string) $u->id)>{{ $u->name }} [{{ $u->job_level_label ?? 'tanpa grade' }}]</option>
                        @endforeach
                    </select>
                    @error('project_sponsor_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block font-semibold mb-1">Project Leader <span class="text-red-600">*</span></label>
                    <select name="project_leader_id"
                            class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-red-200 @error('project_leader_id') border-red-500 @enderror">
                        <option value="">Select Leader</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((string) old('project_leader_id') === (string) $u->id)>{{ $u->name }} [{{ $u->job_level_label ?? 'tanpa grade' }}]</option>
                        @endforeach
                    </select>
                    @error('project_leader_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('projects.approved-ideas') }}" class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800">Create Project</button>
            </div>
        </form>

    </div>

</x-app-layout>
