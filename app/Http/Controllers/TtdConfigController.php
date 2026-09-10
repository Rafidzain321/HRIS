<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ProjectTtdConfig;
use Illuminate\Http\Request;

class TtdConfigController extends Controller
{
    // ── Konfigurasi TTD aktif untuk project — 1 konfigurasi saja, tidak ada histori ──
    public function index()
    {
        $pid = $this->activeProjectId();
        if (!$pid) return response()->json(null);

        return ProjectTtdConfig::where('project_id', $pid)->first(['id', 'ttd_list', 'dibuat_oleh']);
    }

    // ── Simpan (buat baru atau timpa yang sudah ada) ─────────
    public function store(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $pid = $this->activeProjectId();
        if (!$pid) return response()->json(['ok' => false, 'message' => 'Pilih project dulu'], 422);

        $data = $request->validate([
            'ttd_list'           => 'required|array|min:1|max:4',
            'ttd_list.*.label'   => 'nullable|string|max:100',
            'ttd_list.*.name'    => 'nullable|string|max:100',
            'ttd_list.*.jabatan' => 'nullable|string|max:100',
        ]);

        $item = ProjectTtdConfig::updateOrCreate(
            ['project_id' => $pid],
            ['ttd_list' => $data['ttd_list'], 'dibuat_oleh' => auth()->user()?->name ?? 'System']
        );

        ActivityLog::record('update', 'Konfigurasi TTD', null, 'Update tanda tangan slip gaji project');

        return response()->json(['ok' => true, 'item' => $item]);
    }

    // ── Reset ke default (hapus konfigurasi custom) ──────────
    public function destroy(ProjectTtdConfig $item)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $item->delete();
        ActivityLog::record('delete', 'Konfigurasi TTD', null, 'Reset tanda tangan slip gaji project ke default');
        return response()->json(['ok' => true]);
    }
}
