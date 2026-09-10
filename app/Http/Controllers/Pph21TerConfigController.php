<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Pph21TerBracket;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Pph21TerConfigController extends Controller
{
    // Urutkan ulang satu kategori berdasarkan nilai batas_atas (menaik) — baris "tak terhingga"
    // (null) selalu dianggap paling besar jadi otomatis berada di paling akhir. Dipanggil setiap
    // ada baris ditambah/diubah, supaya urutannya selalu benar tanpa perlu geser manual.
    private function resortKategori(string $kategori): void
    {
        $rows = Pph21TerBracket::where('kategori', $kategori)->get()
            ->sortBy(fn ($r) => $r->batas_atas === null ? PHP_INT_MAX : $r->batas_atas)
            ->values();

        foreach ($rows as $i => $row) {
            $urutanBaru = $i + 1;
            if ((int) $row->urutan !== $urutanBaru) {
                $row->update(['urutan' => $urutanBaru]);
            }
        }
    }

    // Halaman konfigurasi tabel tarif TER PPh21 (PMK 168/2023) — dikelompokkan per kategori
    // A/B/C. Hanya untuk HR/finance yang punya akses edit-data-gaji; hasil hitungnya dipakai
    // otomatis di seluruh slip gaji (lihat app/Support/Pph21Ter.php), tidak per-project.
    public function index()
    {
        $brackets = Pph21TerBracket::orderBy('kategori')->orderBy('urutan')->get()
            ->groupBy('kategori')
            ->map(fn ($rows) => $rows->map(fn ($b) => [
                'id'           => $b->id,
                'batas_atas'   => $b->batas_atas,
                'tarif_persen' => $b->tarif_persen,
                'urutan'       => $b->urutan,
            ])->values());

        return Inertia::render('Pph21Ter/Index', [
            'brackets' => $brackets,
        ]);
    }

    public function update(Request $request, Pph21TerBracket $bracket)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $data = $request->validate([
            'batas_atas'   => 'nullable|integer|min:0',
            'tarif_persen' => 'required|numeric|min:0|max:100',
        ]);

        $bracket->update($data);
        $this->resortKategori($bracket->kategori);
        \App\Support\Pph21Ter::clearCache();

        ActivityLog::record('update', 'Konfigurasi PPh21 (TER)', "Kategori {$bracket->kategori}",
            'Batas atas ' . ($data['batas_atas'] !== null ? number_format($data['batas_atas'], 0, ',', '.') : 'tak terhingga') . ", tarif {$data['tarif_persen']}%");

        return response()->json(['ok' => true]);
    }

    // Tambah baris baru — otomatis disisip di urutan yang sesuai nilai batas_atas-nya (bukan
    // selalu di akhir), supaya urutan tabel selalu terurut menaik.
    public function store(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $data = $request->validate([
            'kategori'     => 'required|in:A,B,C',
            'batas_atas'   => 'required|integer|min:0',
            'tarif_persen' => 'required|numeric|min:0|max:100',
        ]);

        $urutanSementara = (Pph21TerBracket::where('kategori', $data['kategori'])->max('urutan') ?? 0) + 1;

        $bracket = Pph21TerBracket::create([
            'kategori'     => $data['kategori'],
            'batas_atas'   => $data['batas_atas'],
            'tarif_persen' => $data['tarif_persen'],
            'urutan'       => $urutanSementara,
        ]);
        $this->resortKategori($data['kategori']);
        \App\Support\Pph21Ter::clearCache();

        ActivityLog::record('create', 'Konfigurasi PPh21 (TER)', "Kategori {$data['kategori']}", "Tambah baris: batas atas " . number_format($data['batas_atas'], 0, ',', '.') . ", tarif {$data['tarif_persen']}%");

        return response()->json(['ok' => true, 'bracket' => $bracket]);
    }

    public function destroy(Pph21TerBracket $bracket)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        if ($bracket->batas_atas === null) {
            return response()->json(['ok' => false, 'message' => 'Baris "tak terhingga" (baris terakhir) tidak boleh dihapus.'], 422);
        }

        $kategori = $bracket->kategori;
        $bracket->delete();
        $this->resortKategori($kategori);
        \App\Support\Pph21Ter::clearCache();

        ActivityLog::record('delete', 'Konfigurasi PPh21 (TER)', "Kategori {$kategori}", 'Hapus 1 baris tarif TER');

        return response()->json(['ok' => true]);
    }
}
