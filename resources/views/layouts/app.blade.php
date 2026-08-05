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
        .ts-wrapper.focus .ts-control,
        .ts-wrapper.input-active .ts-control{
            border-color: #fca5a5 !important;       /* red-300 */
            box-shadow: 0 0 0 3px rgba(254,202,202,.5) !important;
        }
        .ts-dropdown{ border-radius: .5rem; font-size: .875rem; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-100">

<div class="flex h-screen overflow-hidden">

    @include('layouts.sidebar')

    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        @include('layouts.topbar')

        <main class="flex-1 p-6 overflow-y-auto">
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
                            class="px-5 py-2 border rounded-lg hover:bg-gray-100">Batal</button>
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
            show: false, title: 'Konfirmasi', message: '', okLabel: 'Ya, Lanjutkan', _btn: null,
            open(detail) {
                this.title   = detail.title || 'Konfirmasi';
                this.message = detail.message || 'Apakah Anda yakin?';
                this.okLabel = detail.ok || 'Ya, Lanjutkan';
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
    document.addEventListener('DOMContentLoaded', function () {
        // 1) Semua dropdown jadi searchable.
        document.querySelectorAll('select:not([data-no-search])').forEach(function (el) {
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

        // 3) Remote cascade: child diisi via AJAX dari data-remote-url?bu=<parent value>
        //    (mis. Department diambil dari hcis sesuai Business Unit terpilih).
        document.querySelectorAll('select[data-remote-url][data-remote-parent]').forEach(function (child) {
            var parent = document.querySelector(child.getAttribute('data-remote-parent'));
            if (!parent) return;
            var url = child.getAttribute('data-remote-url');

            function load(keep) {
                var bu = parent.value;
                var ts = child.tomselect;
                if (!bu) {
                    if (ts) { ts.clear(true); ts.clearOptions(); ts.refreshOptions(false); }
                    return;
                }
                fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'bu=' + encodeURIComponent(bu), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (list) {
                    if (ts) {
                        ts.clear(true); ts.clearOptions();
                        list.forEach(function (name) { ts.addOption({ value: name, text: name }); });
                        ts.refreshOptions(false);
                        if (keep && list.indexOf(keep) !== -1) ts.setValue(keep, true);
                    }
                })
                .catch(function () {});
            }

            // Ganti BU → muat ulang department (reset pilihan).
            parent.addEventListener('change', function () { load(null); });
            // Saat load: bila BU sudah terisi (edit / old input), muat & pertahankan pilihan.
            if (parent.value) load(child.getAttribute('data-selected') || '');
        });
    });
</script>

</body>

</html>