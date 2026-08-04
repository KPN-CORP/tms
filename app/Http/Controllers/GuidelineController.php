<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Models\Guideline;
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
        $canManage = auth()->user()->can('guideline.upload');

        // Non-pengelola hanya melihat guideline aktif.
        $guidelines = Guideline::with('uploader')
            ->when(! $canManage, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('created_at')
            ->get();

        return view('guidelines.index', [
            'guidelines' => $guidelines,
            'canManage'  => $canManage,
        ]);
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
        ]);

        return back()->with('success', "Guideline \"{$data['title']}\" uploaded.");
    }

    public function download(Guideline $guideline)
    {
        // Non-pengelola tak boleh unduh guideline yang diarsipkan.
        abort_if(! $guideline->is_active && ! auth()->user()->can('guideline.upload'), 404);

        return $this->downloadAttachmentFile($guideline->file_path, $guideline->file_name);
    }

    public function toggle(Guideline $guideline)
    {
        $guideline->update(['is_active' => ! $guideline->is_active]);

        return back()->with('success', $guideline->is_active ? 'Guideline restored.' : 'Guideline archived.');
    }

    public function destroy(Guideline $guideline)
    {
        $this->deleteAttachmentFile($guideline->file_path);
        $title = $guideline->title;
        $guideline->delete();

        return back()->with('success', "Guideline \"{$title}\" deleted.");
    }
}
