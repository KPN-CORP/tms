<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TMS') }}</title>

    <style>[x-cloak]{ display: none !important; }</style>

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
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1) Semua dropdown jadi searchable.
        document.querySelectorAll('select:not([data-no-search])').forEach(function (el) {
            if (el.tomselect) return;
            new TomSelect(el, {
                plugins: el.multiple ? ['remove_button'] : [],
                create: false,
                maxOptions: null,
                allowEmptyOption: true,
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
                    filtered.forEach(function (o) { ts.addOption({ value: o.value, text: o.text }); });
                    ts.refreshOptions(false);
                    ts.setValue(filtered.some(function (o) { return o.value === current; }) ? current : '', true);
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
    });
</script>

</body>

</html>