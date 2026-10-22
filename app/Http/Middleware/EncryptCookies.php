<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Pilihan bahasa ditulis oleh JavaScript (useLocale), jadi tidak boleh
        // dienkripsi -- isinya hanya 'en' atau 'id', dan SetLocale mengabaikan
        // nilai lain. Lihat App\Http\Middleware\SetLocale.
        \App\Http\Middleware\SetLocale::COOKIE,
    ];
}
