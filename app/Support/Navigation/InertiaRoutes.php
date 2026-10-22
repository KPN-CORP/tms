<?php

namespace App\Support\Navigation;

/**
 * Daftar route yang halamannya SUDAH dipindahkan ke Inertia/Vue.
 *
 * Selama pemindahan belum tuntas, sebagian halaman masih dirender Blade. Tautan
 * Inertia (<Link>) tidak bisa membuka halaman Blade: respons-nya bukan respons
 * Inertia, jadi navigasinya gagal alih-alih memuat halaman. Karena itu tautan ke
 * route yang belum ada di daftar ini harus dirender sebagai <a> biasa.
 *
 * CARA PAKAI: begitu sebuah halaman dipindahkan ke Inertia, tambahkan nama
 * route-nya di sini. Begitu daftar ini memuat SEMUA route halaman, berkas ini —
 * beserta prop `external` di komponen AppLink — bisa dihapus seluruhnya.
 */
class InertiaRoutes
{
    /** @var list<string> */
    private const PORTED = [
        'dashboard',

        'ideas.index',
        'ideas.create',
        'ideas.edit',
        'ideas.show',
        'ideas.taskbox',
        'ideas.review.show',

        'projects.index',
        'projects.implementation',
        'projects.completion',
        'projects.shell',
        'projects.shell.progress',
        'projects.review',
        'projects.create',
        'projects.show',

        'reports.index',

        'admin.committee.index',
        'admin.committee.form',
        'admin.project-categories.index',
        'admin.roles.index',
        'admin.roles.create',
        'admin.roles.edit',
        'admin.roles.assign-user',
        'admin.sla.index',
        'admin.email-notifications.index',
        'admin.activity-logs.index',

        'guidelines.index',
        'profile.edit',

        'login',
        'dev.login',
        'dev.login.users',
        'register',
        'password.request',
        'password.reset',
        'password.confirm',
        'verification.notice',
    ];

    public static function has(?string $route): bool
    {
        return $route !== null && in_array($route, self::PORTED, true);
    }
}
