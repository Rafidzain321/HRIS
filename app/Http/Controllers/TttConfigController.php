<?php
namespace App\Http\Controllers;

use App\Models\ProjectTttItem;
use Illuminate\Http\Request;

class TttConfigController extends Controller
{
    // Item default bawaan sistem
    const DEFAULTS = [
        ['key' => 'tunj_makan',         'label' => 'Tunj Makan',      'urutan' => 1],
        ['key' => 'tunj_produksi',       'label' => 'Tunj Produksi',   'urutan' => 2],
        ['key' => 'tunj_lapangan',       'label' => 'Tunj Lapangan',   'urutan' => 3],
        ['key' => 'tunj_kehadiran',      'label' => 'Tunj Kehadiran',  'urutan' => 4],
        ['key' => 'tunj_pulsa',          'label' => 'Tunj Pulsa',      'urutan' => 5],
        ['key' => 'kompensasi_kontrak',  'label' => 'Komp. Kontrak',   'urutan' => 6],
        ['key' => 'insentif',            'label' => 'Insentif',        'urutan' => 7],
        ['key' => 'com_day',             'label' => 'Com Day',         'urutan' => 8],
        ['key' => 'kompensasi_pwt',      'label' => 'Komp. PWT',       'urutan' => 9],
    ];

    // ── GET config TTT untuk project aktif ──────────────────
    public function index()
    {
        $pid = $this->activeProjectId();
        if (!$pid) return response()->json([]);

        // Auto-seed default kalau belum ada
        $existing = ProjectTttItem::where('project_id', $pid)->count();
        if ($existing === 0) {
            foreach (self::DEFAULTS as $d) {
                ProjectTttItem::create([
                    'project_id' => $pid,
                    'key'        => $d['key'],
                    'label'      => $d['label'],
                    'urutan'     => $d['urutan'],
                    'aktif'      => in_array($d['key'], ['tunj_makan','tunj_produksi','tunj_lapangan','kompensasi_pwt']),
                    'is_default' => true,
                ]);
            }
        }

        return ProjectTttItem::where('project_id', $pid)
            ->orderBy('urutan')
            ->get(['id','key','label','urutan','aktif','is_default']);
    }

    // ── UPDATE aktif/label satu item ─────────────────────────
    public function update(Request $request, ProjectTttItem $item)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'aktif' => 'sometimes|boolean',
        ]);

        $item->update($data);
        return response()->json(['ok' => true]);
    }

    // ── TAMBAH item custom baru ──────────────────────────────
    public function store(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $pid = $this->activeProjectId();
        if (!$pid) return response()->json(['ok' => false, 'message' => 'Pilih project dulu'], 422);

        $request->validate([
            'label' => 'required|string|max:100',
        ]);

        // Generate key dari label
        $key = 'custom_' . preg_replace('/[^a-z0-9]/', '_', strtolower($request->label)) . '_' . time();

        $maxUrutan = ProjectTttItem::where('project_id', $pid)->max('urutan') ?? 0;

        $item = ProjectTttItem::create([
            'project_id' => $pid,
            'key'        => $key,
            'label'      => $request->label,
            'urutan'     => $maxUrutan + 1,
            'aktif'      => true,
            'is_default' => false,
        ]);

        return response()->json(['ok' => true, 'item' => $item]);
    }

    // ── HAPUS item custom (hanya non-default) ────────────────
    public function destroy(ProjectTttItem $item)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);
        if ($item->is_default) {
            return response()->json(['ok' => false, 'message' => 'Item default tidak bisa dihapus'], 422);
        }
        $item->delete();
        return response()->json(['ok' => true]);
    }

    // ── UPDATE urutan (drag reorder) ─────────────────────────
    public function reorder(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        foreach ($request->ids as $urutan => $id) {
            ProjectTttItem::where('id', $id)->update(['urutan' => $urutan + 1]);
        }

        return response()->json(['ok' => true]);
    }
}