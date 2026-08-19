<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TMS') }}</title>

    {{-- Tom Select CSS di-head DULU agar override di bawah selalu menang --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">

    <style>
        [x-cloak]{ display: none !important; }
        /* App muat penuh 100vh: hanya <main> yang scroll — matikan scrollbar terluar (body). */
        html, body { height: 100%; }
        body { overflow: hidden; }
        /* Samakan tampilan Tom Select dengan input Tailwind: BORDER TUNGGAL, tinggi = search (38px) */
        .ts-wrapper{ border: 0 !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .ts-wrapper .ts-control{
            box-sizing: border-box !important;
            display: flex !important; align-items: center !important;   /* konten center vertikal */
            width: 100% !important;
            height: 38px !important; min-height: 38px !important;        /* TEPAT sama dengan Search & Apply */
            border: 1px solid #d1d5db !important;   /* gray-300 — satu-satunya border */
            border-radius: .5rem !important;        /* rounded-lg */
            padding: 0 .75rem !important;
            box-shadow: none !important;
            background: #fff !important;
            font-size: .875rem !important;          /* text-sm */
            line-height: 1.5rem !important;
        }
        .ts-wrapper .ts-control > *{ margin: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important; }
        .ts-wrapper .ts-control input{ line-height: 1.5rem; height: auto; }
        .ts-wrapper .ts-control > input::placeholder{ color: #9ca3af; }  /* placeholder abu-abu */
        /* Multi-select: tinggi MENGIKUTI isi (item wrap ke bawah), tidak terpotong saat banyak.
           Single-select tetap 38px agar sama dengan input lain. */
        .ts-wrapper.multi .ts-control{
            height: auto !important;
            min-height: 38px !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            align-content: center !important;
            gap: .3rem !important;
            padding: .3rem .5rem !important;
        }
        .ts-wrapper.multi .ts-control > .item{ margin: 0 !important; max-width: 100%; }
        .ts-wrapper.multi .ts-control > input{ height: 1.5rem !important; }
        .ts-wrapper.focus .ts-control,
        .ts-wrapper.input-active .ts-control{
            border-color: #fca5a5 !important;       /* red-300 */
            box-shadow: 0 0 0 3px rgba(254,202,202,.5) !important;
        }
        /* Dropdown TomSelect (dirender ke <body>) harus DI ATAS modal/dialog (z-[60]). */
        .ts-dropdown{ border-radius: .5rem; font-size: .875rem; z-index: 9999 !important; }
        /* Tombol clear (x): tempel di ujung kanan & sediakan ruang agar tidak menimpa teks terpilih */
        .ts-wrapper.single.has-items .ts-control{ padding-right: 2rem !important; }
        .ts-wrapper .clear-button{
            position: absolute !important;
            right: .5rem !important; left: auto !important;
            top: 50% !important; transform: translateY(-50%) !important;
            margin: 0 !important; opacity: 1 !important; background: transparent !important;
        }
        .ts-wrapper .ts-control > .item{
            max-width: 100%;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">

<div class="flex h-screen overflow-hidden"
     x-data="{ sidebarOpen: JSON.parse(localStorage.getItem('sidebarOpen') ?? 'true') }"
     x-init="$watch('sidebarOpen', v => localStorage.setItem('sidebarOpen', JSON.stringify(v)))">

    @include('layouts.sidebar')

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        @include('layouts.topbar')

        <main class="flex-1 min-h-0 p-6 overflow-y-auto">
            {{ $slot }}
        </main>

    </div>

</div>

{{-- Confirmation dialog global — pasang atribut data-confirm="pesan" pada tombol submit.
     Opsional: data-confirm-title, data-confirm-ok. Menjaga name/value tombol (requestSubmit). --}}
<div x-data="confirmDialog()" x-cloak @confirm-request.window="open($event.detail)">
    <template x-if="show">
        <div class="fixed inset-0 z-[60] flex items-center justify-center"
             @keydown.escape.window="cancel()">
            <div class="fixed inset-0 bg-black/40" @click="cancel()"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6"
                 x-transition.opacity>
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xl">?</div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-800" x-text="title"></h3>
                        <p class="text-sm text-gray-600 mt-1" x-text="message"></p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="cancel()"
                            class="px-5 py-2 border rounded-lg hover:bg-gray-100">Cancel</button>
                    <button type="button" @click="confirm()" x-init="$nextTick(() => $el.focus())"
                            class="px-6 py-2 bg-red-700 text-white rounded-lg font-semibold hover:bg-red-800"
                            x-text="okLabel"></button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function confirmDialog() {
        return {
            show: false, title: 'Confirmation', message: '', okLabel: 'Yes, Continue', _btn: null,
            open(detail) {
                this.title   = detail.title || 'Confirmation';
                this.message = detail.message || 'Are you sure?';
                this.okLabel = detail.ok || 'Yes, Continue';
                this._btn    = detail.button;
                this.show    = true;
            },
            cancel() { this.show = false; this._btn = null; },
            confirm() {
                var b = this._btn; this.show = false; this._btn = null;
                if (!b) return;
                var form = b.form || b.closest('form');
                if (!form) return;
                // requestSubmit(submitter) mempertahankan name/value tombol (mis. action=submit).
                if (form.requestSubmit) form.requestSubmit(b);
                else form.submit();
            },
        };
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-confirm]');
        if (!btn) return;
        var form = btn.form || btn.closest('form');
        // Jalankan validasi native dulu: bila ada field wajib kosong, biarkan browser
        // menampilkan pesannya (jangan preventDefault) dan jangan munculkan konfirmasi.
        if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) return;
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('confirm-request', { detail: {
            message: btn.getAttribute('data-confirm'),
            title:   btn.getAttribute('data-confirm-title'),
            ok:      btn.getAttribute('data-confirm-ok'),
            button:  btn,
        }}));
    });
</script>

{{-- Searchable dropdown (Tom Select) — diterapkan ke SEMUA <select> di aplikasi.
     Opt-out: tambahkan atribut data-no-search. Cascade: data-cascade-parent="#idParent"
     dengan tiap <option data-bu="..."> untuk menyaring anak mengikuti parent. --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    // Bila halaman dipulihkan dari bfcache (tombol Back/Forward browser), paksa reload
    // agar data terbaru (mis. status ide submitted → On Review) selalu tampil, bukan versi cache.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { window.location.reload(); }
    });

    // Init TomSelect untuk semua <select> di dalam `root` (default: seluruh dokumen).
    // Idempotent (skip yang sudah ter-init) → AMAN dipanggil ulang untuk baris repeater baru.
    window.tmsInit = function (root) {
        root = root || document;
        // 1) Semua dropdown jadi searchable.
        root.querySelectorAll('select:not([data-no-search])').forEach(function (el) {
            if (el.tomselect) return;
            var hasEmpty = el.querySelector('option[value=""]') !== null;
            new TomSelect(el, {
                // Empty option (value="") jadi PLACEHOLDER, tidak muncul sebagai item list.
                plugins: el.multiple ? ['remove_button'] : (hasEmpty ? ['clear_button'] : []),
                create: false,
                maxOptions: null,
                // Render dropdown ke <body> agar tidak terpotong / mendorong konten (overflow-hidden card).
                dropdownParent: 'body',
            });
        });

        // 1b) Dropdown searchable via AJAX — opsi muncul saat user mengetik (data besar).
        root.querySelectorAll('select[data-remote-search]').forEach(function (el) {
            if (el.tomselect) return;
            var url = el.getAttribute('data-remote-search');
            var valueKey = el.getAttribute('data-remote-value') || 'email'; // field yg dipakai jadi value option
            new TomSelect(el, {
                valueField: 'value', labelField: 'text', searchField: ['text'],
                create: false, maxOptions: 50, dropdownParent: 'body',
                plugins: el.multiple ? ['remove_button'] : ['clear_button'],
                load: function (query, callback) {
                    if (!query.length) { callback(); return; }
                    fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(query), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (list) { callback(list.map(function (x) { return { value: x[valueKey], text: x.label }; })); })
                    .catch(function () { callback(); });
                },
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.tmsInit();

        // 2) Cascade parent → child (mis. Business Unit → Department).
        document.querySelectorAll('select[data-cascade-parent]').forEach(function (child) {
            var parent = document.querySelector(child.getAttribute('data-cascade-parent'));
            if (!parent) return;

            var all = Array.from(child.options).map(function (o) {
                return { value: o.value, text: o.text, bu: o.getAttribute('data-bu') || '' };
            });

            function apply() {
                var pv = parent.value;
                var current = child.value;
                var filtered = all.filter(function (o) { return !o.value || !pv || o.bu === pv; });
                var ts = child.tomselect;
                if (ts) {
                    ts.clearOptions();
                    // Lewati opsi kosong (placeholder) — jangan di-add sebagai item list.
                    filtered.forEach(function (o) { if (o.value) ts.addOption({ value: o.value, text: o.text }); });
                    ts.refreshOptions(false);
                    ts.setValue(filtered.some(function (o) { return o.value && o.value === current; }) ? current : '', true);
                } else {
                    Array.from(child.options).forEach(function (opt) {
                        if (!opt.value) return;
                        opt.hidden = pv && opt.getAttribute('data-bu') !== pv;
                    });
                }
            }

            parent.addEventListener('change', apply);
            apply();
        });

        // 3) Remote cascade: child diisi via AJAX dari data-remote-url?bu=<parent value>.
        //    Mendukung parent/child SINGLE (mis. Department←BU di form ide) maupun
        //    MULTI-select (mis. Restrict Company←Restrict Group Company di form role,
        //    contribution_level dari SEMUA BU terpilih di-union).
        document.querySelectorAll('select[data-remote-url][data-remote-parent]').forEach(function (child) {
            var parent = document.querySelector(child.getAttribute('data-remote-parent'));
            if (!parent) return;
            var url = child.getAttribute('data-remote-url');

            function parentValues() {
                if (parent.multiple) {
                    return Array.from(parent.selectedOptions).map(function (o) { return o.value; }).filter(Boolean);
                }
                return parent.value ? [parent.value] : [];
            }

            function selectedValues(ts) {
                if (!ts) return [];
                return child.multiple ? ts.items.slice() : (ts.getValue() ? [ts.getValue()] : []);
            }

            function apply(ts, names, keep) {
                ts.clear(true); ts.clearOptions();
                names.forEach(function (n) { ts.addOption({ value: n, text: n }); });
                // Pastikan nilai yang dipertahankan tetap punya opsi (mis. edit tanpa parent / data lama).
                keep.forEach(function (v) { if (v && !ts.options[v]) ts.addOption({ value: v, text: v }); });
                ts.refreshOptions(false);
                var valid = keep.filter(Boolean);
                if (valid.length) ts.setValue(child.multiple ? valid : valid[0], true);
            }

            function load(initial) {
                var ts = child.tomselect;
                if (!ts) return;
                var bus = parentValues();

                // Nilai yang ingin dipertahankan: pilihan saat ini; saat initial tambah data-selected.
                var desired = selectedValues(ts);
                if (initial) {
                    var ds = child.getAttribute('data-selected');
                    if (ds) ds.split('||').forEach(function (v) { if (v && desired.indexOf(v) === -1) desired.push(v); });
                }

                if (!bus.length) {
                    // Tanpa parent: initial (edit) pertahankan pilihan; saat change kosongkan.
                    apply(ts, [], initial ? desired : []);
                    return;
                }

                Promise.all(bus.map(function (bu) {
                    return fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'bu=' + encodeURIComponent(bu), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    })
                    .then(function (r) { return r.json(); })
                    .catch(function () { return []; });
                })).then(function (lists) {
                    var names = [];
                    lists.forEach(function (list) {
                        (list || []).forEach(function (n) { if (names.indexOf(n) === -1) names.push(n); });
                    });
                    names.sort();
                    // initial: pertahankan pilihan walau tak ada di list (data lama);
                    // change: hanya pertahankan yang masih valid.
                    var keep = initial ? desired : desired.filter(function (v) { return names.indexOf(v) !== -1; });
                    apply(ts, names, keep);
                });
            }

            // Ganti parent → muat ulang child (buang pilihan yang tak lagi valid).
            parent.addEventListener('change', function () { load(false); });
            // Saat load awal: bila parent sudah terisi / ada data-selected (edit / old input), muat & pertahankan.
            if (parentValues().length || child.getAttribute('data-selected')) load(true);
        });
    });
</script>

</body>

</html>