<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Mekanik lampiran file yang dipakai bersama IdeaController & ProjectController.
 * Aturan otorisasi tetap di controller masing-masing (berbeda per konteks);
 * trait ini hanya menyeragamkan validasi, penyimpanan, unduh, dan hapus file.
 */
trait HandlesFileAttachments
{
    /** Aturan validasi untuk satu file lampiran (maks 10 MB). */
    protected function attachmentRules(): array
    {
        return ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip,csv,txt'];
    }

    /** Simpan file ke $dir dan kembalikan payload kolom untuk record attachment. */
    protected function storeAttachmentFile(UploadedFile $file, string $dir, int $userId): array
    {
        return [
            'file_name'   => $file->getClientOriginalName(),
            'file_path'   => $file->store($dir),
            'file_size'   => $file->getSize(),
            'uploaded_by' => $userId,
        ];
    }

    /** Kembalikan response unduhan; 404 bila file hilang dari disk. */
    protected function downloadAttachmentFile(string $path, string $name)
    {
        abort_unless(Storage::exists($path), 404, 'File tidak ditemukan.');

        return Storage::download($path, $name);
    }

    /**
     * Kembalikan response INLINE (Content-Disposition: inline) agar bisa
     * ditampilkan langsung di browser (gambar/PDF) tanpa memaksa unduh.
     */
    protected function viewAttachmentFile(string $path, string $name)
    {
        abort_unless(Storage::exists($path), 404, 'File tidak ditemukan.');

        return Storage::response($path, $name);
    }

    /** Hapus file dari disk (aman bila sudah tidak ada). */
    protected function deleteAttachmentFile(string $path): void
    {
        Storage::delete($path);
    }
}
