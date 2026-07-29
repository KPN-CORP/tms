@php
    $map = [
        'draft'           => ['Draft', 'bg-gray-100 text-gray-600'],
        'submitted'       => ['Submitted', 'bg-blue-100 text-blue-700'],
        'review'          => ['On Review', 'bg-amber-100 text-amber-700'],
        'approved'        => ['Approved', 'bg-green-100 text-green-700'],
        'rejected'        => ['Rejected', 'bg-red-100 text-red-700'],
        'project_created' => ['Project Created', 'bg-purple-100 text-purple-700'],
    ];
    [$label, $cls] = $map[$status] ?? [$status, 'bg-gray-100 text-gray-600'];
@endphp
<span class="inline-flex px-3 py-1 text-xs rounded-full {{ $cls }}">{{ $label }}</span>
