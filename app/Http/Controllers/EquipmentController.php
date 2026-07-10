<?php
namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentOperator;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        // ── PROJECT FILTER ──
        $pid       = $this->activeProjectId();
        $highlight = $request->get('highlight');
        $query     = Equipment::with('activeOperator.employee')
            ->when($pid, fn($q) => $q->where('project_id', $pid));

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q
                ->where('no_unit',    'like', "%$search%")
                ->orWhere('plat_nomor','like', "%$search%")
                ->orWhere('model',    'like', "%$search%")
                ->orWhereHas('activeOperator', fn($q2) => $q2
                    ->where('operator_name', 'like', "%$search%")
                    ->orWhere('badge',       'like', "%$search%")
                )
            );
        }
        if ($type   = $request->get('type'))   $query->where('type_unit', $type);
        if ($status = $request->get('status')) $query->where('status', $status);

        $query->orderBy('no_unit');

        $page = $request->get('page', 1);
        if ($highlight) {
            $allIds = (clone $query)->pluck('id')->toArray();
            $pos    = array_search((int) $highlight, $allIds);
            if ($pos !== false) $page = (int) floor($pos / 50) + 1;
        }

        $paginated = $query->paginate(50, ['*'], 'page', $page)->appends($request->except('highlight'));
        $paginated->getCollection()->transform(fn($e) => $this->formatEquipment($e));

        $data = $paginated->toArray();

        // ── STATS juga difilter per project ──
        $base = Equipment::when($pid, fn($q) => $q->where('project_id', $pid));
        $data['stats'] = [
            'total'                => (clone $base)->count(),
            'aktif'                => (clone $base)->where('status','AKTIF')->count(),
            'no_comply'            => (clone $base)->where('status','NO COMPLY')->count(),
            'stnk_expired'         => (clone $base)->whereNotNull('stnk_expired')->where('stnk_expired','<',now())->count(),
            'kir_expired'          => (clone $base)->whereNotNull('kir_expired')->where('kir_expired','<',now())->count(),
            'vehicle_pass_expired' => (clone $base)->whereNotNull('vehicle_pass_expired')->where('vehicle_pass_expired','<',now())->count(),
        ];
        $data['type_list'] = (clone $base)->distinct()->orderBy('type_unit')->whereNotNull('type_unit')->pluck('type_unit')->filter()->values();

        $employees = Employee::aktif()
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->orderBy('nama_lengkap')
            ->get(['id','nama_lengkap','id_badge','rfid']);

        return Inertia::render('Equipment/Index', [
            'equipment' => $data,
            'filters'   => $request->only(['search','type','status']),
            'employees' => $employees,
            'highlight' => $highlight,
        ]);
    }

    public function store(Request $request)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $data = $request->validate([
            'no_unit'                  => 'required|string|max:30|unique:equipments',
            'plat_nomor'               => 'nullable|string|max:20',
            'type_unit'                => 'nullable|string|max:100',
            'model'                    => 'nullable|string|max:100',
            'manufacture'              => 'nullable|string|max:50',
            'serial_no'                => 'nullable|string|max:50',
            'tahun'                    => 'nullable|integer|min:1990|max:' . (date('Y') + 5),
            'gps_unit_id'              => 'nullable|string|max:50',
            'kategori'                 => 'nullable|string|max:50',
            'kapasitas'                => 'nullable|string|max:30',
            'stnk_expired'             => 'nullable|date',
            'tax_expired'              => 'nullable|date',
            'kir_expired'              => 'nullable|date',
            'izin_non_bm_expired'      => 'nullable|date',
            'vehicle_pass_expired'     => 'nullable|date',
            'inspection_date'          => 'nullable|date',
            'smbr_pass_expired'        => 'nullable|date',
            'green_stiker_expired'     => 'nullable|date',
            'sio_migas_no'             => 'nullable|string|max:100',
            'sio_migas_expired'        => 'nullable|date',
            'sio_disnaker_expired'     => 'nullable|date',
            'k3_p3a2_no'               => 'nullable|string|max:150',
            'k3_p3a2_expired'          => 'nullable|date',
            'tpe_cem_inspector'        => 'nullable|string|max:100',
            'contractor_cem_inspector' => 'nullable|string|max:100',
            'location_of_inspection'   => 'nullable|string|max:100',
            'status'                   => 'nullable|string|max:30',
            'keterangan'               => 'nullable|string',
        ]);

        // Set project_id otomatis
        $data['project_id'] = $this->activeProjectId() ?? auth()->user()->project_id;

        Equipment::create($data);
        return redirect()->route('equipment')->with('success', "Unit {$data['no_unit']} berhasil ditambahkan.");
    }

    public function update(Request $request, Equipment $equipment)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $data = $request->validate([
            'no_unit'                  => "required|string|max:30|unique:equipments,no_unit,{$equipment->id}",
            'plat_nomor'               => 'nullable|string|max:20',
            'type_unit'                => 'nullable|string|max:100',
            'model'                    => 'nullable|string|max:100',
            'manufacture'              => 'nullable|string|max:50',
            'serial_no'                => 'nullable|string|max:50',
            'tahun'                    => 'nullable|integer|min:1990|max:' . (date('Y') + 5),
            'gps_unit_id'              => 'nullable|string|max:50',
            'kategori'                 => 'nullable|string|max:50',
            'kapasitas'                => 'nullable|string|max:30',
            'stnk_expired'             => 'nullable|date',
            'tax_expired'              => 'nullable|date',
            'kir_expired'              => 'nullable|date',
            'izin_non_bm_expired'      => 'nullable|date',
            'vehicle_pass_expired'     => 'nullable|date',
            'inspection_date'          => 'nullable|date',
            'smbr_pass_expired'        => 'nullable|date',
            'green_stiker_expired'     => 'nullable|date',
            'sio_migas_no'             => 'nullable|string|max:100',
            'sio_migas_expired'        => 'nullable|date',
            'sio_disnaker_expired'     => 'nullable|date',
            'k3_p3a2_no'               => 'nullable|string|max:150',
            'k3_p3a2_expired'          => 'nullable|date',
            'tpe_cem_inspector'        => 'nullable|string|max:100',
            'contractor_cem_inspector' => 'nullable|string|max:100',
            'location_of_inspection'   => 'nullable|string|max:100',
            'status'                   => 'nullable|string|max:30',
            'keterangan'               => 'nullable|string',
        ]);

        $equipment->update($data);
        return redirect()->route('equipment')->with('success', "Unit {$equipment->no_unit} berhasil diperbarui.");
    }

    public function destroy(Equipment $equipment)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $no = $equipment->no_unit;

        // Hapus semua operator unit ini sekalian
        $equipment->operators()->delete();
        $equipment->delete();

        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'delete',
            'module'      => 'Equipment',
            'target_name' => $no,
            'description' => "Hapus unit equipment {$no} beserta semua data operator",
            'ip_address'  => request()->ip(),
        ]);

        return redirect()->route('equipment')->with('success', "Unit {$no} beserta data operator berhasil dihapus.");
    }

    // ── Operator CRUD ────────────────────────────────────────
    public function storeOperator(Request $request, Equipment $equipment)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $data = $request->validate([
            'operator_name'        => 'required|string|max:200',
            'badge'                => 'nullable|string|max:30',
            'employee_id'          => 'nullable|exists:employees,id',
            'license_no'           => 'nullable|string|max:50',
            'license_expired_date' => 'nullable|date',
            'rfid'                 => 'nullable|string|max:20',
            'kp_no'                => 'nullable|string|max:100',
            'kp_expired_date'      => 'nullable|date',
            'cdrive_expired_date'  => 'nullable|date',
            'postest_expired_date' => 'nullable|date',
            'permit_no'            => 'nullable|string|max:100',
            'permit_expired_date'  => 'nullable|date',
            'sio_migas_no'         => 'nullable|string|max:100',
            'sio_migas_expired'    => 'nullable|date',
            'sio_disnaker_expired' => 'nullable|date',
            'k3_p3a2_no'           => 'nullable|string|max:150',
            'k3_p3a2_expired'      => 'nullable|date',
        ]);

        // Set project_id dari equipment induknya
        $data['project_id'] = $equipment->project_id;

        $equipment->operators()->where('is_active', true)->update(['is_active' => false]);
        $equipment->operators()->create(array_merge($data, ['is_active' => true]));

        return redirect()->route('equipment')->with('success', "Operator {$data['operator_name']} berhasil ditambahkan ke unit {$equipment->no_unit}.");
    }

    public function assignOperator(Request $request, Equipment $equipment)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        // Nonaktifkan operator lama di unit ini
        $equipment->operators()->where('is_active', true)->update(['is_active' => false]);

        // Cek kalau employee ini aktif di unit lain, nonaktifkan juga
        EquipmentOperator::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('equipment_id', '!=', $equipment->id)
            ->update(['is_active' => false]);

        // Buat operator baru
        $equipment->operators()->create([
            'employee_id'   => $employee->id,
            'operator_name' => $employee->nama_lengkap,
            'badge'         => $employee->id_badge,
            'rfid'          => $employee->rfid,
            'project_id'    => $equipment->project_id,
            'is_active'     => true,
        ]);

        return redirect()->route('equipment')->with('success',
            "Operator unit {$equipment->no_unit} berhasil diganti ke {$employee->nama_lengkap}."
        );
    }
    public function updateOperator(Request $request, Equipment $equipment, EquipmentOperator $operator)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $data = $request->validate([
            'operator_name'        => 'required|string|max:200',
            'badge'                => 'nullable|string|max:30',
            'employee_id'          => 'nullable|exists:employees,id',
            'license_no'           => 'nullable|string|max:50',
            'license_expired_date' => 'nullable|date',
            'rfid'                 => 'nullable|string|max:20',
            'kp_no'                => 'nullable|string|max:100',
            'kp_expired_date'      => 'nullable|date',
            'cdrive_expired_date'  => 'nullable|date',
            'postest_expired_date' => 'nullable|date',
            'permit_no'            => 'nullable|string|max:100',
            'permit_expired_date'  => 'nullable|date',
            'sio_migas_no'         => 'nullable|string|max:100',
            'sio_migas_expired'    => 'nullable|date',
            'sio_disnaker_expired' => 'nullable|date',
            'k3_p3a2_no'           => 'nullable|string|max:150',
            'k3_p3a2_expired'      => 'nullable|date',
        ]);

        $operator->update($data);
        return redirect()->route('equipment')->with('success', "Data operator {$operator->operator_name} berhasil diperbarui.");
    }

    public function destroyOperator(Equipment $equipment, EquipmentOperator $operator)
    {
        if ($this->isViewer()) return back()->with('error', 'Viewer tidak memiliki akses.');

        $nama = $operator->operator_name;
        $noUnit = $equipment->no_unit;

        // Hapus operator, unit tetap ada
        $operator->delete();

        ActivityLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'delete',
            'module'      => 'Equipment',
            'target_name' => $nama,
            'description' => "Hapus operator {$nama} dari unit {$noUnit}",
            'ip_address'  => request()->ip(),
        ]);

        return redirect()->route('equipment')->with('success', "Operator {$nama} berhasil dihapus dari unit {$noUnit}.");
    }

    private function formatEquipment(Equipment $e): array
    {
        $op = $e->activeOperator;
        return [
            'id'                       => $e->id,
            'no_unit'                  => $e->no_unit,
            'plat_nomor'               => $e->plat_nomor,
            'type_unit'                => $e->type_unit,
            'model'                    => $e->model,
            'manufacture'              => $e->manufacture,
            'serial_no'                => $e->serial_no,
            'tahun'                    => $e->tahun,
            'gps_unit_id'              => $e->gps_unit_id,
            'kategori'                 => $e->kategori,
            'kapasitas'                => $e->kapasitas,
            'status'                   => $e->status,
            'keterangan'               => $e->keterangan,
            'stnk_expired'             => $e->stnk_expired?->format('Y-m-d'),
            'tax_expired'              => $e->tax_expired?->format('Y-m-d'),
            'kir_expired'              => $e->kir_expired?->format('Y-m-d'),
            'izin_non_bm_expired'      => $e->izin_non_bm_expired?->format('Y-m-d'),
            'vehicle_pass_expired'     => $e->vehicle_pass_expired?->format('Y-m-d'),
            'inspection_date'          => $e->inspection_date?->format('Y-m-d'),
            'smbr_pass_expired'        => $e->smbr_pass_expired?->format('Y-m-d'),
            'green_stiker_expired'     => $e->green_stiker_expired?->format('Y-m-d'),
            'sio_migas_no'             => $e->sio_migas_no,
            'sio_migas_expired'        => $e->sio_migas_expired?->format('Y-m-d'),
            'sio_disnaker_expired'     => $e->sio_disnaker_expired?->format('Y-m-d'),
            'k3_p3a2_no'               => $e->k3_p3a2_no,
            'k3_p3a2_expired'          => $e->k3_p3a2_expired?->format('Y-m-d'),
            'tpe_cem_inspector'        => $e->tpe_cem_inspector,
            'contractor_cem_inspector' => $e->contractor_cem_inspector,
            'location_of_inspection'   => $e->location_of_inspection,
            'stnk_status'              => $e->stnk_status,
            'kir_status'               => $e->kir_status,
            'vehicle_pass_status'      => $e->vehicle_pass_status,
            'operator' => $op ? [
                'id'                   => $op->id,
                'operator_name'        => $op->operator_name,
                'badge'                => $op->badge,
                'license_no'           => $op->license_no,
                'license_expired_date' => $op->license_expired_date?->format('Y-m-d'),
                'rfid'                 => $op->rfid,
                'kp_no'                => $op->kp_no,
                'kp_expired_date'      => $op->kp_expired_date?->format('Y-m-d'),
                'permit_no'            => $op->permit_no,
                'permit_expired_date'  => $op->permit_expired_date?->format('Y-m-d'),
                'sio_migas_no'         => $op->sio_migas_no,
                'sio_migas_expired'    => $op->sio_migas_expired?->format('Y-m-d'),
                'k3_p3a2_no'           => $op->k3_p3a2_no,
                'k3_p3a2_expired'      => $op->k3_p3a2_expired?->format('Y-m-d'),
                'license_status'       => $op->license_status,
                'kp_status'            => $op->kp_status,
                'permit_status'        => $op->permit_status,
            ] : null,
        ];
    }
}