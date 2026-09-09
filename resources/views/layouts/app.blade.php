<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'TMS') }}</title>

    {{-- Tom Select CSS di-head DULU agar override di bawah selalu menang --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">

    {{-- Datepicker (flatpickr): dipakai agar SEMUA input tanggal tampil dd/mm/yyyy. --}}
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">

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
        /* ---- Datepicker (flatpickr) ---------------------------------------------
           Kalender dirender ke <body>, jadi <select> bulan dan <input type=number>
           tahun di dalamnya ikut kena base style @tailwindcss/forms (border, padding
           besar, ikon chevron) yang membuat header berantakan. Blok ini menetralkan
           gaya tersebut sekaligus menyamakan aksen dengan tombol aplikasi (red-700). */
        .flatpickr-calendar{
            z-index: 99999 !important;              /* harus di atas modal (z-[60]) */
            width: 19.5rem;
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
            box-shadow: 0 12px 28px -8px rgba(17,24,39,.25);
            font-size: .875rem;
        }
        .flatpickr-calendar.arrowTop::after{ border-bottom-color: #fff; }
        .flatpickr-calendar.arrowBottom::after{ border-top-color: #fff; }

        /* Header: nama bulan + TAHUN */
        .flatpickr-months{ padding: .5rem .25rem .125rem; }
        .flatpickr-months .flatpickr-month{ height: 2.25rem; color: #111827; }
        .flatpickr-current-month{
            display: flex; align-items: center; justify-content: center; gap: .25rem;
            height: 2.25rem; padding: 0; font-size: .9375rem; font-weight: 600;
        }
        /* Netralkan @tailwindcss/forms pada dua kontrol di dalam header. */
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month .numInputWrapper input.cur-year{
            -webkit-appearance: none; appearance: none;
            background-color: transparent;
            border: 0 !important; box-shadow: none !important; outline: none;
            border-radius: .375rem; height: 1.875rem; line-height: 1.875rem;
            font-size: .9375rem; font-weight: 600; color: #111827;
        }
        .flatpickr-current-month .numInputWrapper input.cur-year{ background-image: none !important; }
        /* Caret kecil sebagai penanda bahwa nama bulan bisa diklik (dropdown). */
        .flatpickr-current-month .flatpickr-monthDropdown-months{
            padding: 0 1.25rem 0 .5rem !important; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z' clip-rule='evenodd'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat; background-position: right .3rem center; background-size: .85rem;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months:hover{ background: #fef2f2; }
        .flatpickr-current-month .numInputWrapper{ width: 3.75rem; }
        .flatpickr-current-month .numInputWrapper:hover{ background: #fef2f2; border-radius: .375rem; }
        .flatpickr-current-month .numInputWrapper input.cur-year{
            padding: 0 .25rem !important; text-align: center;
        }
        /* Daftar bulan saat dropdown dibuka (dirender oleh OS/browser). */
        .flatpickr-monthDropdown-months option{ background: #fff; color: #111827; font-weight: 500; }

        /* Panah pindah bulan */
        .flatpickr-months .flatpickr-prev-month,
        .flatpickr-months .flatpickr-next-month{ padding: .375rem .5rem; border-radius: .375rem; }
        .flatpickr-months .flatpickr-prev-month:hover,
        .flatpickr-months .flatpickr-next-month:hover{ background: #fef2f2; }
        .flatpickr-months .flatpickr-prev-month:hover svg,
        .flatpickr-months .flatpickr-next-month:hover svg{ fill: #b91c1c; }

        /* Baris nama hari + grid tanggal */
        span.flatpickr-weekday{ color: #6b7280; font-weight: 600; font-size: .75rem; }
        .flatpickr-day{ border-radius: .5rem; color: #374151; }
        .flatpickr-day:hover, .flatpickr-day:focus{ background: #fee2e2; border-color: #fee2e2; color: #111827; }
        .flatpickr-day.today{ border-color: #b91c1c; font-weight: 600; }
        .flatpickr-day.today:hover{ background: #fee2e2; color: #111827; }
        .flatpickr-day.selected, .flatpickr-day.selected:hover, .flatpickr-day.selected:focus{
            background: #b91c1c !important; border-color: #b91c1c !important;
            color: #fff !important; font-weight: 600;
        }
        .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay{ color: #d1d5db; }
        .flatpickr-day.flatpickr-disabled, .flatpickr-day.flatpickr-disabled:hover{
            color: #e5e7eb; background: transparent; border-color: transparent;
        }
    </style>

    {{-- Penyimpan posisi buka/tutup panel collapsible ke localStorage, dipakai lintas
         halaman (section Project Detail, banner peringatan Committee Assignment, dst).
         Didefinisikan di <head> agar sudah ada sebelum Alpine mengevaluasi x-data.
         Key dikirim LENGKAP oleh pemanggil supaya tiap halaman punya namespace sendiri. --}}
    <script>
        window.tmsSec = {
            get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v === '1'; } catch (e) { return d; } },
            set(k, v) { try { localStorage.setItem(k, v ? '1' : '0'); } catch (e) {} }
        };
    </script>

    {{-- Konversi waktu ke timezone BROWSER pengguna.
         Server merender <time datetime="...Z"> dalam UTC; skrip ini menuliskan
         ulang isinya sesuai zona perangkat, dengan label:
           Indonesia -> WIB (UTC+7) / WITA (UTC+8) / WIT (UTC+9)
           lainnya   -> GMT+HH / GMT-HH:MM
         Bila JS mati, teks bawaan dari server (UTC) tetap terbaca. --}}
    <script>
        window.tmsTime = (function () {
            var ID_ZONES = {
                'Asia/Jakarta': 'WIB', 'Asia/Pontianak': 'WIB',
                'Asia/Makassar': 'WITA', 'Asia/Ujung_Pandang': 'WITA',
                'Asia/Jayapura': 'WIT'
            };
            var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var pad = function (n) { return String(n).padStart(2, '0'); };

            function zoneName() {
                var tz = '';
                try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
                if (ID_ZONES[tz]) return ID_ZONES[tz];

                // Di luar Indonesia: GMT dari offset perangkat (menit, terbalik tandanya).
                var off = -new Date().getTimezoneOffset();
                var sign = off < 0 ? '-' : '+';
                var abs = Math.abs(off);
                var h = pad(Math.floor(abs / 60)), m = abs % 60;
                return 'GMT' + sign + h + (m ? ':' + pad(m) : '');
            }

            function render(el) {
                var iso = el.getAttribute('datetime');
                if (!iso) return;
                var d = new Date(iso);
                if (isNaN(d)) return;

                var mode = el.getAttribute('data-tms-time') || 'datetime';
                var date = pad(d.getDate()) + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
                var time = pad(d.getHours()) + ':' + pad(d.getMinutes());

                if (mode === 'date')      el.textContent = date;
                else if (mode === 'time') el.textContent = time + ' ' + zoneName();
                else                      el.textContent = date + ' ' + time + ' ' + zoneName();

                el.setAttribute('title', d.toString());
            }

            function apply(root) {
                (root || document).querySelectorAll('time[data-tms-time]').forEach(render);
            }

            document.addEventListener('DOMContentLoaded', function () { apply(); });
            return { apply: apply, zoneName: zoneName };
        })();
    </script>

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

{{-- Datepicker global (flatpickr) — diterapkan ke SEMUA <input type="date">.
     Format input tanggal bawaan browser mengikuti locale OS (mm/dd/yyyy di en-US)
     dan TIDAK bisa dipaksa lewat HTML; flatpickr merender kalender sendiri sehingga
     tampilan dd/mm/yyyy konsisten di Chrome, Firefox, Safari, maupun Edge. --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
    // Input ASLI tetap dipertahankan (disembunyikan) berisi nilai Y-m-d, sehingga
    // name/value yang dikirim ke server dan binding Alpine (x-model, :min, :max)
    // sama sekali tidak berubah — yang berubah hanya tampilannya jadi dd/mm/yyyy.
    // Idempotent (skip yang sudah ter-init) → AMAN dipanggil ulang untuk baris repeater baru.
    // Opt-out: tambahkan atribut data-no-datepicker pada input.
    window.tmsDate = function (root) {
        root = root || document;
        if (typeof flatpickr === 'undefined') return;   // CDN gagal → input native tetap berfungsi

        root.querySelectorAll('input[type="date"]:not([data-no-datepicker])').forEach(function (el) {
            if (el._flatpickr) return;

            var locked   = el.hasAttribute('readonly') || el.disabled;
            var required = el.hasAttribute('required');
            var cls      = el.className;   // salin kelas Tailwind ke input tampilan

            var fp = flatpickr(el, {
                dateFormat:    'Y-m-d',    // nilai TERKIRIM tetap ISO (aturan `date` Laravel aman)
                altInput:      true,
                altFormat:     'd/m/Y',    // yang dilihat & diketik user
                altInputClass: cls,
                allowInput:    true,       // boleh diketik manual, mis. 31/12/2025
                clickOpens:    !locked,
                disableMobile: true,       // jangan jatuh balik ke picker native di HP
                minDate:       el.getAttribute('min') || null,
                maxDate:       el.getAttribute('max') || null,
            });

            fp.altInput._fpOwner = fp;   // dipakai handler Enter di bawah
            fp.altInput.setAttribute('placeholder', 'dd/mm/yyyy');
            fp.altInput.setAttribute('autocomplete', 'off');
            if (required) fp.altInput.setAttribute('required', 'required');
            if (locked)   { fp.altInput.readOnly = true; fp.altInput.disabled = el.disabled; }

            // :min / :max dari Alpine (mis. End Date tidak boleh mendahului Start Date)
            // mengubah ATRIBUT pada input asli. flatpickr hanya membacanya saat init,
            // jadi perubahannya disinkronkan manual ke kalender.
            new MutationObserver(function () {
                fp.set('minDate', el.getAttribute('min') || null);
                fp.set('maxDate', el.getAttribute('max') || null);
            }).observe(el, { attributes: true, attributeFilter: ['min', 'max'] });
        });
    };

    // Menekan Enter di input tanggal: handler bawaan flatpickr mem-parse teks memakai
    // dateFormat (Y-m-d), sehingga ketikan "25/12/2025" jadi kosong/salah tanggal dan
    // ikut TERKIRIM karena Enter juga men-submit form. Ditangani di fase CAPTURE pada
    // document agar berjalan LEBIH DULU daripada handler flatpickr (stopPropagation
    // membatalkan handler bawaan). preventDefault sengaja TIDAK dipakai supaya
    // perilaku "Enter = submit" yang sudah ada tetap jalan — dengan nilai yang benar.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var fp = e.target && e.target._fpOwner;
        if (!fp) return;
        e.stopPropagation();

        var raw = String(fp.altInput.value || '').trim();
        if (!raw) { fp.clear(); return; }
        var d = flatpickr.parseDate(raw, 'd/m/Y');
        // Ketikan tidak sah → kembalikan tampilan ke nilai terakhir yang valid.
        fp.setDate(d || fp.selectedDates[0] || null, true);
        fp.close();
    }, true);
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

        // Semua <input type="date"> → kalender dd/mm/yyyy (lihat window.tmsDate).
        if (window.tmsDate) window.tmsDate(root);

        // Untuk single-select: setelah ada nilai terpilih, matikan typing (input readonly).
        // Bisa mengetik lagi hanya setelah item dihapus (tombol × / clear).
        var lockSingle = function (ts) {
            if (ts.settings.maxItems !== 1 || !ts.control_input) return;
            var sync = function () { ts.control_input.readOnly = (ts.items.length >= 1); };
            ts.on('item_add', sync);
            ts.on('item_remove', sync);
            ts.on('clear', sync);
            ts.on('change', sync);
            sync();
        };

        // 0) Isi <option> select dari endpoint JSON (mis. daftar Business Unit) — bukan server-side.
        //    Item bisa string ("Name") ATAU objek {value, text}. Nilai terpilih dirender
        //    server-side sbg <option selected>, atau via atribut data-selected.
        root.querySelectorAll('select[data-remote-options]').forEach(function (el) {
            if (el.dataset.roDone) return;
            el.dataset.roDone = '1';
            var selected = el.getAttribute('data-selected');
            fetch(el.getAttribute('data-remote-options'), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (list) {
                    var ts = el.tomselect;
                    var seen = {};
                    Array.prototype.forEach.call(el.options, function (o) { seen[o.value] = true; });
                    (list || []).forEach(function (item) {
                        var value = (item && typeof item === 'object') ? String(item.value) : String(item);
                        var text  = (item && typeof item === 'object') ? item.text : item;
                        if (seen[value]) return;
                        seen[value] = true;
                        if (ts) { ts.addOption({ value: value, text: text }); }
                        else { var o = document.createElement('option'); o.value = value; o.textContent = text; el.appendChild(o); }
                    });
                    if (ts) ts.refreshOptions(false);
                    if (selected !== null && selected !== '') {
                        if (ts) ts.setValue(selected, true); else el.value = selected;
                    }
                })
                .catch(function () {});
        });

        // 1) Semua dropdown jadi searchable.
        //    KECUALI <select> bulan milik kalender flatpickr (dirender ke <body>):
        //    aturan .ts-wrapper{width:100%} memakan seluruh header kalender sehingga
        //    input TAHUN terdorong keluar dan tidak terlihat.
        root.querySelectorAll('select:not([data-no-search])').forEach(function (el) {
            if (el.tomselect || el.closest('.flatpickr-calendar')) return;
            var hasEmpty = el.querySelector('option[value=""]') !== null;
            lockSingle(new TomSelect(el, {
                // Empty option (value="") jadi PLACEHOLDER, tidak muncul sebagai item list.
                plugins: el.multiple ? ['remove_button'] : (hasEmpty ? ['clear_button'] : []),
                create: false,
                maxOptions: null,
                // Non-multiple = single-select (hanya 1 pilihan); multiple = tanpa batas.
                maxItems: el.multiple ? null : 1,
                // Render dropdown ke <body> agar tidak terpotong / mendorong konten (overflow-hidden card).
                dropdownParent: 'body',
            }));
        });

        // 1b) Dropdown searchable via AJAX — opsi muncul saat user mengetik (data besar).
        root.querySelectorAll('select[data-remote-search]').forEach(function (el) {
            if (el.tomselect) return;
            var url = el.getAttribute('data-remote-search');
            var valueKey = el.getAttribute('data-remote-value') || 'email'; // field yg dipakai jadi value option
            lockSingle(new TomSelect(el, {
                valueField: 'value', labelField: 'text', searchField: ['text'],
                create: false, maxOptions: 50, dropdownParent: 'body',
                maxItems: el.multiple ? null : 1,
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
            }));
        });
    };

    // Submit repeater anggota tim (dialog Add Team Member pada Project Detail).
    // Baris tanpa nama di-disable agar tidak ikut terkirim — slot role wajib boleh
    // diisi bertahap. Return false bila tidak ada satu pun nama yang dipilih.
    // Error JS apa pun TIDAK boleh memblokir submit: server sudah menyaring baris kosong.
    window.tmsMemberSubmit = function (e) {
        var form = e.target, any = false;
        var enableAll = function () {
            form.querySelectorAll('[data-member-name], [data-member-role]')
                .forEach(function (el) { el.disabled = false; });
        };
        try {
            form.querySelectorAll('[data-member-row]').forEach(function (row) {
                var nameEl = row.querySelector('[data-member-name]');
                var roleEl = row.querySelector('[data-member-role]');
                var skip   = ! (nameEl && nameEl.value);
                if (nameEl) nameEl.disabled = skip;
                if (roleEl) roleEl.disabled = skip;
                if (! skip) any = true;
            });
        } catch (err) {
            console.error('tmsMemberSubmit:', err);
            enableAll();
            return true;
        }
        if (! any) { e.preventDefault(); enableAll(); }
        return any;
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