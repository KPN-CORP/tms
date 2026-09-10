<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Models\CommitteeAssignment;
use App\Models\Guideline;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Guideline — pustaka dokumen panduan (§9.7).
 * Semua user boleh melihat & mengunduh (guideline.view); hanya pemegang
 * guideline.upload yang boleh unggah/arsip/hapus (dicek via middleware rute).
 */
class GuidelineController extends Controller
{
    use HandlesFileAttachments;

    private const DIR = 'guidelines';

    public function index()
    {
        $user        = auth()->user();
        $canManage   = $user->can('guideline.upload');
        $isCommittee = $this->isCommittee($user);

        $guidelines = Guideline::with('uploader')
            ->when(! $canManage, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('created_at')
            ->get()
            // Non-pengelola hanya melihat guideline yang boleh di-view ATAU download olehnya.
            ->when(! $canManage, fn ($c) => $c->filter(
                fn (Guideline $g) => $g->viewableBy($isCommittee) || $g->downloadableBy($isCommittee)
            )->values());

        return view('guidelines.index', [
            'guidelines'  => $guidelines,
            'canManage'   => $canManage,
            'isCommittee' => $isCommittee,
        ]);
    }

    /** Apakah user tergolong Committee (anggota committee_assignments atau role Committee). */
    private function isCommittee(User $user): bool
    {
        return $user->hasRole('Committee')
            || CommitteeAssignment::where('user_id', $user->id)->exists();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file'        => $this->attachmentRules(),
        ]);

        $payload = $this->storeAttachmentFile($request->file('file'), self::DIR, auth()->id());

        Guideline::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'file_name'   => $payload['file_name'],
            'file_path'   => $payload['file_path'],
            'file_size'   => $payload['file_size'],
            'uploaded_by' => $payload['uploaded_by'],
            'is_active'   => true,
            // Hak akses di-set bersamaan saat upload.
            'employee_can_view'      => $request->boolean('employee_can_view'),
            'employee_can_download'  => $request->boolean('employee_can_download'),
            'committee_can_view'     => $request->boolean('committee_can_view'),
            'committee_can_download' => $request->boolean('committee_can_download'),
        ]);

        return back()->with('success', "Guideline \"{$data['title']}\" uploaded.");
    }

    public function download(Guideline $guideline)
    {
        $user      = auth()->user();
        $canManage = $user->can('guideline.upload');

        abort_if(! $guideline->is_active && ! $canManage, 404);
        abort_unless(
            $canManage || $guideline->downloadableBy($this->isCommittee($user)),
            403,
            'You do not have download access to this guideline.'
        );

        return $this->downloadAttachmentFile($guideline->file_path, $guideline->file_name);
    }

    /** Buka/baca guideline INLINE (mis. PDF di tab baru) tanpa mengunduh. */
    public function view(Guideline $guideline)
    {
        $user      = auth()->user();
        $canManage = $user->can('guideline.upload');

        abort_if(! $guideline->is_active && ! $canManage, 404);
        abort_unless(
            $canManage || $guideline->viewableBy($this->isCommittee($user)),
            403,
            'You do not have view access to this guideline.'
        );

        return $this->viewAttachmentFile($guideline->file_path, $guideline->file_name);
    }

    /** Atur hak akses Employee & Committee (View/Download) — hanya pengelola. */
    public function updateAccess(Request $request, Guideline $guideline)
    {
        $guideline->update([
            'employee_can_view'      => $request->boolean('employee_can_view'),
            'employee_can_download'  => $request->boolean('employee_can_download'),
            'committee_can_view'     => $request->boolean('committee_can_view'),
            'committee_can_download' => $request->boolean('committee_can_download'),
        ]);

        return back()->with('success', "Hak akses \"{$guideline->title}\" diperbarui.");
    }

    public function toggle(Guideline $guideline)
    {
        $guideline->update(['is_active' => ! $guideline->is_active]);

        return back()->with('success', $guideline->is_active ? 'Guideline activated.' : 'Guideline deactivated.');
    }

    public function destroy(Guideline $guideline)
    {
        $this->deleteAttachmentFile($guideline->file_path);
        $title = $guideline->title;
        $guideline->delete();

        return back()->with('success', "Guideline \"{$title}\" deleted.");
    }
}
