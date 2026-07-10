<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeDocumentController extends Controller
{
    public function index(Employee $employee)
    {
        $docs = EmployeeDocument::where('employee_id', $employee->id)
            ->with('uploader')
            ->orderBy('tipe')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'id'             => $d->id,
                'tipe'           => $d->tipe,
                'tipe_label'     => $d->getTipeLabel(),
                'nama_file'      => $d->nama_file,
                'size_formatted' => $d->size_formatted,
                'uploaded_by'    => $d->uploader?->name ?? '—',
                'created_at'     => $d->created_at->format('d M Y H:i'),
            ]);

        return response()->json($docs);
    }

    public function upload(Request $request, Employee $employee)
    {
        $tipe = $request->input('tipe');
        // foto karyawan boleh jpg/png/pdf, lainnya PDF only
        $isFoto = $tipe === 'foto';
        $request->validate([
            'file' => $isFoto
                ? 'required|file|mimes:jpeg,jpg,png,pdf|max:5120'
                : 'required|file|mimes:pdf|max:5120',
            'tipe' => 'required|in:ktp,sio,foto,kk,bpjs,bpjs_kesehatan,cv,skck,lainnya',
        ]);

        $file     = $request->file('file');
        $badgeDir = Str::slug($employee->no_ktp ?? 'emp-'.$employee->id);

        // Folder: storage/app/public/employee_docs/{badge}/{tipe}/
        $dir  = "employee_docs/{$badgeDir}/{$tipe}";
        $name = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.pdf';
        $path = $file->storeAs($dir, $name, 'public');

        $doc = EmployeeDocument::create([
            'employee_id' => $employee->id,
            'tipe'        => $tipe,
            'nama_file'   => $file->getClientOriginalName(),
            'path'        => $path,
            'size'        => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        ActivityLog::record(
            'upload', 'Dokumen',
            $employee->nama_lengkap,
            "Upload {$doc->getTipeLabel()} — {$doc->nama_file}"
        );

        return response()->json([
            'id'             => $doc->id,
            'tipe'           => $doc->tipe,
            'tipe_label'     => $doc->getTipeLabel(),
            'nama_file'      => $doc->nama_file,
            'size_formatted' => $doc->size_formatted,
            'uploaded_by'    => auth()->user()?->name,
            'created_at'     => $doc->created_at->format('d M Y H:i'),
            'message'        => 'File berhasil diupload.',
        ]);
    }

    public function preview(EmployeeDocument $document)
    {
        if (!Storage::disk('public')->exists($document->path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $fullPath = Storage::disk('public')->path($document->path);
        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->nama_file . '"',
        ]);
    }

    public function download(EmployeeDocument $document)
    {
        if (!Storage::disk('public')->exists($document->path)) {
            abort(404, 'File tidak ditemukan.');
        }

        ActivityLog::record('download', 'Dokumen', $document->employee?->nama_lengkap, "Download {$document->nama_file}");
        return Storage::disk('public')->download($document->path, $document->nama_file);
    }

    public function destroy(EmployeeDocument $document)
    {
        $nama = $document->nama_file;
        $emp  = $document->employee?->nama_lengkap;
        Storage::disk('public')->delete($document->path);
        $document->delete();

        ActivityLog::record('delete', 'Dokumen', $emp, "Hapus file {$nama}");
        return response()->json(['message' => 'File berhasil dihapus.']);
    }
}
