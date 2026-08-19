<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ProjectBpjsConfig;
use Illuminate\Http\Request;

class BpjsConfigController extends Controller
{
    // ── Riwayat konfigurasi BPJS untuk project aktif ─────────
    public function index()
    {
        $pid = $this->activeProjectId();
        if (!$pid) return response()->json([]);

        return ProjectBpjsConfig::where('project_id', $pid)
            ->orderByDesc('berlaku_mulai')
            ->get(['id', 'berlaku_mulai', 'pct_jht', 'pct_pensiun', 'pct_kes', 'dibuat_oleh']);
    }

    // ── Tambah versi baru (berlaku mulai tanggal tertentu) ───
    public function store(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $pid = $this->activeProjectId();
        if (!$pid) return response()->json(['ok' => false, 'message' => 'Pilih project dulu'], 422);

        $data = $request->validate([
            'berlaku_mulai' => 'required|date',
            'pct_jht'       => 'required|numeric|min:0|max:100',
            'pct_pensiun'   => 'required|numeric|min:0|max:100',
            'pct_kes'       => 'required|numeric|min:0|max:100',
        ]);

        $item = ProjectBpjsConfig::updateOrCreate(
            ['project_id' => $pid, 'berlaku_mulai' => $data['berlaku_mulai']],
            [
                'pct_jht'     => $data['pct_jht'],
                'pct_pensiun' => $data['pct_pensiun'],
                'pct_kes'     => $data['pct_kes'],
                'dibuat_oleh' => auth()->user()?->name ?? 'System',
            ]
        );

        ActivityLog::record(
            'update',
            'Konfigurasi BPJS',
            null,
            "Set BPJS project mulai {$data['berlaku_mulai']}: JHT {$data['pct_jht']}%, Pensiun {$data['pct_pensiun']}%, Kes {$data['pct_kes']}%"
        );

        return response()->json(['ok' => true, 'item' => $item]);
    }

    // ── Hapus — hanya boleh versi TERBARU milik project itu ──
    public function destroy(ProjectBpjsConfig $item)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $latestId = ProjectBpjsConfig::where('project_id', $item->project_id)
            ->orderByDesc('berlaku_mulai')
            ->value('id');

        if ($latestId !== $item->id) {
            return response()->json(['ok' => false, 'message' => 'Hanya versi terbaru yang bisa dihapus.'], 422);
        }

        $item->delete();
        return response()->json(['ok' => true]);
    }
}
