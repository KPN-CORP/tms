<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyetel bahasa aplikasi dari pilihan pengguna.
 *
 * Pilihan bahasa hidup di klien (resources/js/composables/useLocale.ts) dan
 * ditulis ke cookie `tms-locale`. Server perlu tahu pilihan itu karena ada teks
 * yang TIDAK dirender Vue: pesan validasi Laravel dan flash message. Keduanya
 * muncul tepat sesudah sebuah request, jadi cookie-nya selalu sudah mutakhir.
 *
 * Cookie-nya sengaja tidak dienkripsi (lihat EncryptCookies::$except) supaya
 * JavaScript bisa menulisnya; isinya hanya kode bahasa, bukan data rahasia, dan
 * nilai yang tidak dikenal diabaikan sehingga tidak ada yang bisa diselundupkan
 * lewat cookie ini.
 */
class SetLocale
{
    public const COOKIE = 'tms-locale';

    /** Sama dengan `Locale` di resources/js/locales/index.ts. */
    public const SUPPORTED = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->cookie(self::COOKIE);

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        // Nilai lain (kosong, kode bahasa yang sudah dihapus, isian asal)
        // dibiarkan: config('app.locale') yang berlaku, yaitu English.

        return $next($request);
    }
}
