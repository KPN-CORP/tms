<?php

namespace App\Http\Middleware;

use App\Support\Navigation\SidebarBuilder;
use App\Support\ReportOverride;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

/**
 * Data yang dibagikan ke SETIAP halaman Inertia.
 *
 * Semua nilai yang mahal dibungkus closure: Inertia hanya mengevaluasinya pada
 * kunjungan penuh, atau pada partial reload yang memang meminta prop tersebut.
 * Jadi `router.reload({ only: ['projects'] })` tidak ikut menghitung ulang
 * badge Task Box maupun daftar izin.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Versi aset: Inertia memaksa full reload bila manifest berubah, sehingga
     * pengguna tidak pernah terjebak di bundle lama setelah deploy.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],

                // Peta izin lengkap, dihormati Gate::before -- jadi Super Admin
                // tetap terbaca `true` di semua ability meski tidak punya baris
                // permission langsung. Daftar ability-nya sendiri di-cache oleh
                // Spatie, bukan query per request.
                'can' => fn () => $user === null ? [] : $this->abilities($user),
            ],

            // Konteks Report: satu-satunya tempat kewenangan lintas organisasi
            // hidup. Komponen memakainya untuk meneruskan ?from=report, bukan
            // untuk memutuskan izin -- keputusan itu tetap di server.
            'reportContext' => fn () => ReportOverride::aktif($request),

            'nav' => fn () => $user === null
                ? ['groups' => []]
                : app(SidebarBuilder::class)->build($user),

            'flash' => fn () => array_filter([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'status' => $request->session()->get('status'),
            ], static fn ($value) => $value !== null),
        ]);
    }

    /** @return array<string, bool> */
    private function abilities($user): array
    {
        return app(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => $user->can($name)])
            ->all();
    }
}
