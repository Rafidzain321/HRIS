<?php
namespace App\Http\Controllers;

use App\Models\ProjectPotonganItem;
use Illuminate\Http\Request;

class PotonganConfigController extends Controller
{
    // ── GET config potongan dinamis untuk project aktif ──────
    public function index()
    {
        $pid = $this->activeProjectId();
        if (!$pid) return response()->json([]);

        return ProjectPotonganItem::where('project_id', $pid)
            ->orderBy('urutan')
            ->get(['id','key','label','urutan','aktif','is_default']);
    }

    // ── UPDATE aktif/label satu item ─────────────────────────
    public function update(Request $request, ProjectPotonganItem $item)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'aktif' => 'sometimes|boolean',
        ]);

        $item->update($data);
        return response()->json(['ok' => true]);
    }

    // ── TAMBAH item potongan custom baru ─────────────────────
    public function store(Request $request)
    {
        if ($this->isViewer()) return response()->json(['ok' => false], 403);

        $pid = $this->activeProjectId();
        if (!$pid) return response()->json(['ok' => false, 'message' => 'Pilih project dulu'], 422);

        $request->validate([
            'label' => 'required|string|max:100',
        ]);

        // Generate key dari label
        $key = 'pot_' . preg_replace('/[^a-z0-9]/', '_', strtolower($request->label)) . '_' . time();

        $maxUrutan = ProjectPotonganItem::where('project_id', $pid)->max('urutan') ?? 0;

        $item = ProjectPotonganItem::create([
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
    public function destroy(ProjectPotonganItem $item)
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
            ProjectPotonganItem::where('id', $id)->update(['urutan' => $urutan + 1]);
        }

        return response()->json(['ok' => true]);
    }
}
