<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HcisAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Developer login — khusus lokal/QA: masuk sebagai user hcis mana pun tanpa
 * password. Dijaga satu kunci bersama (services.dev_login.key): langkah 1
 * memverifikasi kunci, langkah 2 memilih user yang mau ditumpangi. Bila kunci
 * tidak dikonfigurasi, seluruh rute di sini mati (404 / redirect ke login).
 *
 * PERMISSION: tidak ada jalur istimewa. Setelah user dipilih, Auth::login lalu
 * HcisAuthService::ensureRole() dijalankan — persis seperti LoginRequest pada
 * login biasa. Jadi hak akses yang didapat sama dengan kalau orang itu login
 * sendiri memakai password-nya: role Spatie miliknya di tm_system, plus role
 * Employee default. Tidak ada role tambahan yang diberikan.
 *
 * hcis READ-ONLY: user TIDAK pernah dibuat di sini (beda dengan kpn-tmp yang
 * firstOrCreate). Pencarian hanya memunculkan orang yang memang punya baris di
 * tabel users hcis, sehingga setiap hasil pasti bisa dimasuki.
 */
class DevLoginController extends Controller
{
    /** Term terpendek yang mau dicari. Di bawah ini endpoint mengembalikan kosong. */
    public const MIN_SEARCH_LENGTH = 2;

    /** Maksimum baris hasil pencarian. */
    private const SEARCH_LIMIT = 30;

    /** Fitur hidup hanya bila kunci dikonfigurasi. */
    private function enabled(): bool
    {
        return ! empty(config('services.dev_login.key'));
    }

    public function create(): Response|RedirectResponse
    {
        if (! $this->enabled()) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/DevLogin');
    }

    /** Langkah 1 — verifikasi kunci. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);

        $validated = $request->validate([
            'access_key' => ['required', 'string'],
        ]);

        if (! hash_equals((string) config('services.dev_login.key'), $validated['access_key'])) {
            throw ValidationException::withMessages([
                'access_key' => __('messages.validation.dev_login_invalid_key'),
            ]);
        }

        $request->session()->put('dev_login_verified', true);

        return redirect()->route('dev.login.users');
    }

    /** Langkah 2 — halaman pemilih user. */
    public function users(Request $request): Response|RedirectResponse
    {
        if (! $this->verified($request)) {
            return redirect()->route('dev.login');
        }

        return Inertia::render('Auth/DevLoginUser', [
            'minSearchLength' => self::MIN_SEARCH_LENGTH,
        ]);
    }

    /**
     * Pencarian user hcis. Join kiri ke employees supaya jabatan & Business Unit
     * ikut tampil — keduanya di koneksi kpncorp, jadi join-nya aman. User tanpa
     * baris employee tetap muncul (ditandai di UI): dia bisa login, cuma tanpa
     * data organisasi sehingga role scope-nya kosong.
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless($this->verified($request), 403);

        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return response()->json([]);
        }

        $rows = DB::connection('kpncorp')
            ->table('users as u')
            ->leftJoin('employees as e', 'e.employee_id', '=', 'u.employee_id')
            ->whereNull('e.deleted_at')
            ->where(function ($w) use ($term) {
                $w->where('u.name', 'like', "%{$term}%")
                    ->orWhere('u.email', 'like', "%{$term}%")
                    ->orWhere('u.employee_id', 'like', "%{$term}%")
                    ->orWhere('e.fullname', 'like', "%{$term}%")
                    ->orWhere('e.designation_name', 'like', "%{$term}%")
                    ->orWhere('e.group_company', 'like', "%{$term}%");
            })
            ->orderByRaw('COALESCE(e.fullname, u.name)')
            ->limit(self::SEARCH_LIMIT)
            ->get([
                'u.id',
                'u.email',
                'u.employee_id',
                DB::raw('COALESCE(e.fullname, u.name) as name'),
                'e.designation_name',
                'e.group_company',
            ]);

        return response()->json($rows);
    }

    /** Langkah 3 — masuk sebagai user terpilih. */
    public function impersonate(Request $request): RedirectResponse
    {
        abort_unless($this->verified($request), 403);

        $validated = $request->validate([
            'user_id' => ['required'],
        ]);

        $user = User::find($validated['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => __('messages.validation.user_not_found'),
            ]);
        }

        Auth::login($user);

        // Jalur role yang sama persis dengan login biasa (lihat LoginRequest).
        app(HcisAuthService::class)->ensureRole($user);

        $request->session()->forget('dev_login_verified');
        $request->session()->regenerate();

        return redirect()->route($user->homeRoute());
    }

    private function verified(Request $request): bool
    {
        return $this->enabled() && $request->session()->get('dev_login_verified') === true;
    }
}
