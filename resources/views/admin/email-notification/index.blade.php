<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Email Notifications</h2>
    </x-slot>

    @php
        $isEdit = (bool) $schedule;
        $action = $isEdit
            ? route('admin.email-notifications.update', $schedule)
            : route('admin.email-notifications.store');

        $inp   = 'w-full h-[42px] border border-gray-300 rounded px-3 text-sm focus:ring focus:ring-red-200 focus:border-red-400';
        $label = 'block text-sm font-semibold text-gray-700 mb-1.5';

        // Nilai awal (old() menang agar isian tidak hilang saat validasi gagal).
        $val  = fn ($f, $d = null) => old($f, $isEdit ? ($schedule->$f ?? $d) : $d);
        $arr  = fn ($f) => collect(old($f, $isEdit ? ($schedule->$f ?? []) : []))->all();
        $date = fn ($f) => old($f, $isEdit && $schedule->$f ? $schedule->$f->format('Y-m-d') : '');
    @endphp

    <div class="p-6 max-w-4xl mx-auto w-full">

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-lg border border-gray-200">

            {{-- Baris tutup — mengikuti tombol silang di pojok kanan atas pada rancangan. --}}
            <div class="flex justify-end px-6 pt-5">
                <a href="{{ route('admin.sla.index') }}" class="text-gray-400 hover:text-gray-600" title="Close">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </a>
            </div>

            <form method="POST" action="{{ $action }}" class="px-8 pb-8 pt-2 space-y-5"
                  x-data="notifForm({{ Js::from($arr('repeat_days')) }}, {{ Js::from($val('message', '')) }})">
                @csrf
                @if($isEdit) @method('PUT') @endif

                @if($errors->any())
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                {{-- SLA — diletakkan sebelum Title. Isinya hanya SLA yang berstatus Active. --}}
                <div>
                    <label class="{{ $label }}">SLA <span class="text-red-600">*</span></label>
                    <select name="sla_setting_id" data-no-search class="{{ $inp }} bg-white appearance-none">
                        <option value="">Select an active SLA..</option>
                        @foreach($slaOptions as $id => $text)
                            <option value="{{ $id }}" @selected((int) $val('sla_setting_id') === $id)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @if($slaOptions->isEmpty())
                        <p class="text-xs text-amber-700 mt-1">
                            No active SLA yet. Activate one in
                            <a href="{{ route('admin.sla.index') }}" class="underline font-semibold">SLA Settings</a> first.
                        </p>
                    @endif
                </div>

                {{-- Title --}}
                <div>
                    <label class="{{ $label }}">Title <span class="text-red-600">*</span></label>
                    <input name="title" value="{{ $val('title') }}" placeholder="Enter.." class="{{ $inp }}">
                </div>

                {{-- Business Unit — sumber hcis; menjadi induk cascade utk Company & Location. --}}
                <div>
                    <label class="{{ $label }}">Business Unit</label>
                    <select id="notif-bu" name="business_units[]" multiple
                            data-remote-options="{{ route('org.business-units') }}"
                            placeholder="Type to search business unit..." class="w-full border border-gray-300 rounded">
                        @foreach($arr('business_units') as $n)<option value="{{ $n }}" selected>{{ $n }}</option>@endforeach
                    </select>
                </div>

                {{-- Unit — cascade dari Business Unit, memakai endpoint & pola yang
                     sama persis dengan filter Unit di My Ideas / My Project
                     (org.unit-names, sumber departments.department_name di hcis). --}}
                <div>
                    <label class="{{ $label }}">Unit</label>
                    <select name="units[]" multiple
                            data-remote-url="{{ route('org.unit-names') }}" data-remote-parent="#notif-bu"
                            placeholder="Select a business unit first, then a unit..." class="w-full border border-gray-300 rounded">
                        @foreach($arr('units') as $n)<option value="{{ $n }}" selected>{{ $n }}</option>@endforeach
                    </select>
                </div>

                {{-- Filter Company — cascade dari Business Unit di atas. --}}
                <div>
                    <label class="{{ $label }}">Filter Company:</label>
                    <select name="companies[]" multiple
                            data-remote-url="{{ route('org.companies') }}" data-remote-parent="#notif-bu"
                            placeholder="Select a business unit first, then a company..." class="w-full border border-gray-300 rounded">
                        @foreach($arr('companies') as $n)<option value="{{ $n }}" selected>{{ $n }}</option>@endforeach
                    </select>
                </div>

                {{-- Filter Locations — cascade dari Business Unit di atas. --}}
                <div>
                    <label class="{{ $label }}">Filter Locations:</label>
                    <select name="locations[]" multiple
                            data-remote-url="{{ route('org.locations') }}" data-remote-parent="#notif-bu"
                            placeholder="Select a business unit first, then a location..." class="w-full border border-gray-300 rounded">
                        @foreach($arr('locations') as $n)<option value="{{ $n }}" selected>{{ $n }}</option>@endforeach
                    </select>
                </div>

                {{-- Filter Job Level — daftar job level hcis, tidak cascade (berlaku lintas BU). --}}
                <div>
                    <label class="{{ $label }}">Filter Job Level:</label>
                    <select name="job_levels[]" multiple
                            data-remote-options="{{ route('org.job-levels') }}"
                            placeholder="Type to search job level..." class="w-full border border-gray-300 rounded">
                        @foreach($arr('job_levels') as $n)<option value="{{ $n }}" selected>{{ $n }}</option>@endforeach
                    </select>
                </div>

                {{-- Pratinjau penerima — dihitung dari tabel employees (hcis) memakai
                     filter di atas. Muncul agar admin tahu email akan menyasar siapa
                     sebelum menyimpan. --}}
                <div class="rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-gray-700">Recipients</span>
                        <button type="button" @click="loadRecipients()"
                                class="text-xs px-3 py-1 border border-gray-300 rounded bg-white hover:bg-gray-100">Refresh</button>
                    </div>
                    <div class="mt-2 text-gray-600" x-show="! recipients.loading">
                        <span class="font-semibold text-red-700" x-text="recipients.total"></span>
                        <span>employee(s) will receive this email.</span>
                        <template x-if="recipients.unfiltered && recipients.total > 0">
                            <span class="text-amber-700">&mdash; no filter applied, so every employee is included.</span>
                        </template>
                        <ul class="mt-1 text-xs text-gray-500 list-disc list-inside">
                            <template x-for="p in recipients.sample" :key="p"><li x-text="p"></li></template>
                        </ul>
                        <template x-if="recipients.total > recipients.sample.length">
                            <p class="mt-1 text-xs text-gray-400"
                               x-text="'and ' + (recipients.total - recipients.sample.length) + ' more'"></p>
                        </template>
                    </div>
                    <p class="mt-2 text-xs text-gray-400" x-show="recipients.loading">Counting...</p>
                </div>

                {{-- Start / End Date. min pada End Date mencegah tanggal selesai
                     lebih awal dari tanggal mulai langsung di pemilih tanggal. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5" x-data="{ start: '{{ $date('start_date') }}' }">
                    <div>
                        <label class="{{ $label }}">Start Date</label>
                        <input type="date" name="start_date" value="{{ $date('start_date') }}" x-model="start" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="{{ $label }}">End Date</label>
                        <input type="date" name="end_date" value="{{ $date('end_date') }}" :min="start" class="{{ $inp }}">
                    </div>
                </div>

                {{-- Attach Detail --}}
                <div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="attach_detail" value="1" class="rounded border-gray-300"
                               @checked($val('attach_detail'))>
                        <span>Attach Detail</span>
                    </label>
                </div>

                {{-- Repeat On — tujuh tombol hari, aktif/nonaktif dengan klik. --}}
                <div>
                    <label class="{{ $label }}">Repeat On</label>
                    <div class="grid grid-cols-7 border border-red-300 rounded overflow-hidden">
                        @foreach($days as $key => $text)
                            <button type="button" @click="toggleDay('{{ $key }}')"
                                    :class="days.includes('{{ $key }}')
                                        ? 'bg-red-700 text-white font-semibold'
                                        : 'bg-white text-red-700 hover:bg-red-50'"
                                    class="px-2 py-2 text-sm text-center transition {{ ! $loop->last ? 'border-r border-red-300' : '' }}">{{ $text }}</button>
                        @endforeach
                    </div>
                    <template x-for="d in days" :key="d">
                        <input type="hidden" name="repeat_days[]" :value="d">
                    </template>
                </div>

                {{-- Messages — editor sederhana tanpa pustaka luar; hasilnya HTML yang
                     disalin ke input tersembunyi tepat sebelum form dikirim. --}}
                <div>
                    <label class="{{ $label }}">Messages</label>
                    <div class="border border-gray-300 rounded overflow-hidden">
                        <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-gray-200 bg-gray-50">
                            <select @change="format('formatBlock', $event.target.value)" data-no-search
                                    class="h-7 text-xs border border-gray-300 rounded px-1 bg-white">
                                <option value="p">Normal</option>
                                <option value="h1">Heading 1</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @click="format('bold')"      class="w-7 h-7 rounded hover:bg-gray-200 font-bold text-sm">B</button>
                            <button type="button" @click="format('italic')"    class="w-7 h-7 rounded hover:bg-gray-200 italic text-sm font-serif">I</button>
                            <button type="button" @click="format('underline')" class="w-7 h-7 rounded hover:bg-gray-200 underline text-sm">U</button>
                            <button type="button" @click="addLink()"           class="w-7 h-7 rounded hover:bg-gray-200 text-sm" title="Insert link">&#128279;</button>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @click="format('insertOrderedList')"   class="w-7 h-7 rounded hover:bg-gray-200 text-sm" title="Numbered list">1.</button>
                            <button type="button" @click="format('insertUnorderedList')" class="w-7 h-7 rounded hover:bg-gray-200 text-sm" title="Bulleted list">&bull;</button>
                            <span class="w-px h-5 bg-gray-300 mx-1"></span>
                            <button type="button" @click="format('removeFormat')" class="w-7 h-7 rounded hover:bg-gray-200 text-sm" title="Clear formatting">T<sub>x</sub></button>
                        </div>
                        <div x-ref="editor" contenteditable="true"
                             class="min-h-[150px] px-3 py-2 text-sm focus:outline-none"></div>
                    </div>
                    <input type="hidden" name="message" x-ref="message">
                </div>

                {{-- Aksi --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('admin.sla.index') }}"
                       class="px-8 py-2 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-100">Cancel</a>
                    {{-- Submit tidak langsung menyimpan: tampilkan dulu daftar penerima
                         berdasarkan filter yang diisi, baru dikonfirmasi. --}}
                    <button type="button" @click="openConfirm()" :disabled="confirm.loading"
                            class="px-10 py-2 bg-red-700 text-white rounded text-sm font-semibold hover:bg-red-800 disabled:opacity-60">
                        <span x-show="! confirm.loading">Submit</span>
                        <span x-show="confirm.loading" x-cloak>Checking...</span>
                    </button>
                </div>

                {{-- ===== Konfirmasi penerima ===== --}}
                <div x-show="confirm.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="confirm.open = false">
                    <div class="absolute inset-0 bg-black/40" @click="confirm.open = false"></div>

                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">
                        <div class="flex items-start justify-between gap-4 px-6 py-4 border-b">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800">Confirm Recipients</h3>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    This email will be sent to
                                    <span class="font-semibold text-red-700" x-text="confirm.total"></span>
                                    employee(s) based on the filters you entered.
                                </p>
                            </div>
                            <button type="button" @click="confirm.open = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <template x-if="confirm.unfiltered && confirm.total > 0">
                            <div class="px-6 py-3 bg-amber-50 border-b border-amber-200 text-sm text-amber-800">
                                No filter is applied, so <span class="font-semibold">every employee</span> in the company is included.
                            </div>
                        </template>

                        <template x-if="confirm.total === 0">
                            <div class="px-6 py-3 bg-red-50 border-b border-red-200 text-sm text-red-700">
                                No employee matches these filters, so nobody would receive this email.
                            </div>
                        </template>

                        <div class="overflow-auto flex-1">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-600 text-xs uppercase sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 w-10">#</th>
                                        <th class="px-4 py-2">Name</th>
                                        <th class="px-4 py-2">Email</th>
                                        <th class="px-4 py-2">Unit</th>
                                        <th class="px-4 py-2">Job Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <template x-for="(p, i) in confirm.recipients" :key="p.email + i">
                                        <tr>
                                            <td class="px-4 py-2 text-gray-400" x-text="i + 1"></td>
                                            <td class="px-4 py-2 text-gray-800" x-text="p.name"></td>
                                            <td class="px-4 py-2 text-gray-600" x-text="p.email"></td>
                                            <td class="px-4 py-2 text-gray-500 text-xs" x-text="p.unit"></td>
                                            <td class="px-4 py-2 text-gray-500 text-xs" x-text="p.job_level"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <template x-if="confirm.truncated">
                                <p class="px-4 py-3 text-xs text-gray-500 bg-gray-50 border-t"
                                   x-text="'Showing the first ' + confirm.recipients.length + ' of ' + confirm.total + ' recipients.'"></p>
                            </template>
                        </div>

                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t">
                            <button type="button" @click="confirm.open = false"
                                    class="px-6 py-2 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-100">Back</button>
                            <button type="submit" @click="syncMessage()"
                                    class="px-8 py-2 bg-red-700 text-white rounded text-sm font-semibold hover:bg-red-800">Confirm &amp; Submit</button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>

    {{-- Script inline (bukan @push): layout ini tidak menyediakan stack 'scripts'. --}}
    <script>
            function notifForm(repeatDays, message) {
                return {
                    days: repeatDays || [],
                    recipients: { total: 0, sample: [], unfiltered: true, loading: true },
                    confirm: { open: false, loading: false, total: 0, recipients: [], truncated: false, unfiltered: true },

                    init() {
                        // Isi awal editor dipasang sbg HTML supaya format tersimpan tetap tampil.
                        this.$refs.editor.innerHTML = message || '';
                        this.syncMessage();

                        this.loadRecipients();

                        // Hitung ulang tiap filter organisasi berubah. TomSelect dipasang
                        // setelah Alpine init, jadi pemasangan listener ditunda sesaat.
                        setTimeout(() => {
                            this.$el.querySelectorAll('select[name$="[]"]').forEach(el => {
                                if (el.tomselect) {
                                    el.tomselect.on('change', () => this.loadRecipients());
                                } else {
                                    el.addEventListener('change', () => this.loadRecipients());
                                }
                            });
                            this.loadRecipients();
                        }, 300);
                    },

                    // Kirim filter yang sedang dipilih ke server; kembalikan jumlah + daftar penerima.
                    fetchRecipients(limit) {
                        // TomSelect mengambil alih <select> asli dan mengelola pilihannya
                        // sendiri, sehingga membaca option:checked mengembalikan kosong.
                        // Nilai diambil dari instance-nya; selectedOptions hanya cadangan
                        // untuk select yang belum/tidak di-TomSelect-kan.
                        const pick = (name) => {
                            const el = this.$el.querySelector('select[name="' + name + '[]"]');
                            if (! el) return [];

                            if (el.tomselect) {
                                const v = el.tomselect.getValue();
                                return (Array.isArray(v) ? v : [v]).filter(Boolean);
                            }

                            return Array.from(el.selectedOptions).map(o => o.value).filter(Boolean);
                        };

                        return fetch('{{ route('admin.email-notifications.recipients') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                limit:          limit,
                                business_units: pick('business_units'),
                                units:          pick('units'),
                                companies:      pick('companies'),
                                locations:      pick('locations'),
                                job_levels:     pick('job_levels'),
                            }),
                        }).then(r => r.json());
                    },

                    // Panel ringkas di atas form: jumlah + 5 contoh nama.
                    loadRecipients() {
                        this.recipients.loading = true;
                        this.fetchRecipients(5)
                            .then(d => { this.recipients = { ...d, loading: false }; })
                            .catch(() => { this.recipients.loading = false; });
                    },

                    // Ambil daftar penerima LENGKAP lalu tampilkan dialog konfirmasi.
                    openConfirm() {
                        this.confirm.loading = true;
                        this.fetchRecipients(500)
                            .then(d => { this.confirm = { ...d, open: true, loading: false }; })
                            .catch(() => {
                                // Bila pengecekan gagal, jangan menghalangi: kirim form apa adanya.
                                this.confirm.loading = false;
                                this.syncMessage();
                                this.$el.submit();
                            });
                    },

                    toggleDay(day) {
                        this.days = this.days.includes(day)
                            ? this.days.filter(d => d !== day)
                            : [...this.days, day];
                    },

                    format(command, value) {
                        this.$refs.editor.focus();
                        document.execCommand(command, false, value || null);
                        this.syncMessage();
                    },

                    addLink() {
                        const url = window.prompt('Link URL:');
                        if (url) this.format('createLink', url);
                    },

                    // Isi editor disalin ke input tersembunyi agar ikut terkirim.
                    syncMessage() {
                        const html = this.$refs.editor.innerHTML.trim();
                        this.$refs.message.value = (html === '' || html === '<br>') ? '' : html;
                    },
                };
            }
    </script>

</x-app-layout>
