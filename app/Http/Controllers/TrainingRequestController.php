<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\HoDivision;
use App\Models\Project;
use App\Models\TrainingRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

// Pengajuan Training (khusus HO) — SOP poin 11: tiap divisi mengajukan kebutuhan training (jenis/tempat
// sesuai kebutuhan), maksimal MAKS_PER_DIVISI pengajuan per tahun per divisi. Alur: diajukan -> disetujui /
// ditolak HR -> selesai. Pengajuan yang ditolak tidak dihitung ke kuota.
class TrainingRequestController extends Controller
{
    const MAKS_PER_DIVISI = 3;
    const STATUS_KUOTA    = ['diajukan', 'disetujui', 'selesai'];

    const RULES = [
        'ho_division_id'  => 'required|exists:ho_divisions,id',
        'nama_training'   => 'required|string|max:200',
        'penyelenggara'   => 'nullable|string|max:200',
        'tanggal_rencana' => 'required|date',
        'estimasi_biaya'  => 'nullable|integer|min:0|max:9999999999',
        'alasan'          => 'required|string|max:2000',
        'employee_ids'    => 'required|array|min:1',
        'employee_ids.*'  => 'integer|exists:employees,id',
    ];
    const MESSAGES = [
        'ho_division_id.required' => 'Divisi wajib dipilih.',
        'nama_training.required'  => 'Nama/jenis training wajib diisi.',
        'tanggal_rencana.required'=> 'Tanggal rencana wajib diisi.',
        'alasan.required'         => 'Alasan/kebutuhan training wajib diisi.',
        'employee_ids.required'   => 'Pilih minimal 1 karyawan peserta.',
        'employee_ids.min'        => 'Pilih minimal 1 karyawan peserta.',
    ];

    public function index(Request $request)
    {
        $tahun = (int) $request->get('tahun', now()->year);
        $user  = auth()->user();

        $requests = TrainingRequest::with(['division:id,nama', 'employees:id,nama_lengkap', 'pengaju:id,name', 'pemroses:id,name'])
            ->where('tahun', $tahun)->latest('id')->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'ho_division_id'  => $r->ho_division_id,
                'divisi'          => $r->division?->nama,
                'nama_training'   => $r->nama_training,
                'penyelenggara'   => $r->penyelenggara,
                'tanggal_rencana' => $r->tanggal_rencana->format('Y-m-d'),
                'estimasi_biaya'  => $r->estimasi_biaya,
                'alasan'          => $r->alasan,
                'status'          => $r->status,
                'catatan_hr'      => $r->catatan_hr,
                'peserta'         => $r->employees->map(fn($e) => ['id' => $e->id, 'nama' => $e->nama_lengkap])->values(),
                'diajukan_oleh'   => $r->pengaju?->name,
                'diajukan_at'     => $r->created_at->format('Y-m-d H:i'),
                'diproses_oleh'   => $r->pemroses?->name,
                'diproses_at'     => $r->diproses_at?->format('Y-m-d H:i'),
                'bisa_ubah'       => $r->status === 'diajukan' && ($this->bolehProses() || $r->diajukan_oleh == $user->id),
            ]);

        $terpakai  = $requests->whereIn('status', self::STATUS_KUOTA)->countBy('ho_division_id');
        $divisions = HoDivision::orderBy('nama')->get(['id', 'nama', 'is_active'])
            ->map(fn($d) => ['id' => $d->id, 'nama' => $d->nama, 'is_active' => $d->is_active, 'terpakai' => $terpakai->get($d->id, 0)]);

        $ho = Project::where('kode', 'HO')->value('id');
        $employees = Employee::aktif()->where('project_id', $ho)->with(['position:id,nama_jabatan', 'hoDetail:id,employee_id,ho_division_id'])
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'position_id'])
            ->map(fn($e) => ['id' => $e->id, 'nama' => $e->nama_lengkap, 'jabatan' => $e->position?->nama_jabatan, 'ho_division_id' => $e->hoDetail?->ho_division_id]);

        return Inertia::render('PengajuanTraining/Index', [
            'tahun'       => $tahun,
            'requests'    => $requests->values(),
            'divisions'   => $divisions,
            'employees'   => $employees,
            'maks'        => self::MAKS_PER_DIVISI,
            'can_edit'    => $user->can('edit-pengajuan-training'),
            'can_approve' => $this->bolehProses(),
        ]);
    }

    public function store(Request $request)
    {
        $data  = $request->validate(self::RULES, self::MESSAGES);
        $tahun = Carbon::parse($data['tanggal_rencana'])->year;
        if ($error = $this->cekPeserta($data['employee_ids']) ?? $this->cekKuota($data['ho_division_id'], $tahun)) {
            return back()->withErrors($error);
        }

        $tr = DB::transaction(function () use ($data, $tahun) {
            $tr = TrainingRequest::create([...collect($data)->except('employee_ids')->all(), 'tahun' => $tahun, 'status' => 'diajukan', 'diajukan_oleh' => auth()->id()]);
            $tr->employees()->sync($data['employee_ids']);
            return $tr;
        });

        ActivityLog::record('create', 'Pengajuan Training', $tr->nama_training,
            "Ajukan training \"{$tr->nama_training}\" — divisi {$tr->division->nama}, " . count($data['employee_ids']) . " peserta");
        return back()->with('success', "Pengajuan training \"{$tr->nama_training}\" berhasil dikirim.");
    }

    public function update(Request $request, TrainingRequest $trainingRequest)
    {
        if ($error = $this->cekBolehUbah($trainingRequest)) return back()->with('error', $error);

        $data  = $request->validate(self::RULES, self::MESSAGES);
        $tahun = Carbon::parse($data['tanggal_rencana'])->year;
        if ($error = $this->cekPeserta($data['employee_ids']) ?? $this->cekKuota($data['ho_division_id'], $tahun, $trainingRequest->id)) {
            return back()->withErrors($error);
        }

        DB::transaction(function () use ($trainingRequest, $data, $tahun) {
            $trainingRequest->update([...collect($data)->except('employee_ids')->all(), 'tahun' => $tahun]);
            $trainingRequest->employees()->sync($data['employee_ids']);
        });

        ActivityLog::record('update', 'Pengajuan Training', $trainingRequest->nama_training, "Ubah pengajuan training \"{$trainingRequest->nama_training}\"");
        return back()->with('success', 'Pengajuan training berhasil diperbarui.');
    }

    public function destroy(TrainingRequest $trainingRequest)
    {
        if ($error = $this->cekBolehUbah($trainingRequest)) return back()->with('error', $error);

        $nama = $trainingRequest->nama_training;
        $trainingRequest->delete();
        ActivityLog::record('delete', 'Pengajuan Training', $nama, "Batalkan pengajuan training \"{$nama}\"");
        return back()->with('success', "Pengajuan training \"{$nama}\" dibatalkan.");
    }

    // HR menyetujui / menolak pengajuan yang masih berstatus diajukan.
    public function proses(Request $request, TrainingRequest $trainingRequest)
    {
        if (!$this->bolehProses()) abort(403, 'Hanya HR yang bisa menyetujui/menolak pengajuan training.');
        if ($trainingRequest->status !== 'diajukan') return back()->with('error', 'Pengajuan ini sudah diproses.');

        $data = $request->validate([
            'keputusan'  => 'required|in:disetujui,ditolak',
            'catatan_hr' => 'nullable|required_if:keputusan,ditolak|string|max:1000',
        ], ['catatan_hr.required_if' => 'Alasan penolakan wajib diisi.']);

        $trainingRequest->update([
            'status' => $data['keputusan'], 'catatan_hr' => $data['catatan_hr'] ?? null,
            'diproses_oleh' => auth()->id(), 'diproses_at' => now(),
        ]);
        $label = $data['keputusan'] === 'disetujui' ? 'disetujui' : 'ditolak';
        ActivityLog::record('update', 'Pengajuan Training', $trainingRequest->nama_training,
            "Pengajuan training \"{$trainingRequest->nama_training}\" {$label}" . (!empty($data['catatan_hr']) ? " — {$data['catatan_hr']}" : ''));
        return back()->with('success', "Pengajuan training \"{$trainingRequest->nama_training}\" {$label}.");
    }

    public function selesai(TrainingRequest $trainingRequest)
    {
        if (!$this->bolehProses()) abort(403, 'Hanya HR yang bisa menandai training selesai.');
        if ($trainingRequest->status !== 'disetujui') return back()->with('error', 'Hanya pengajuan yang sudah disetujui yang bisa ditandai selesai.');

        $trainingRequest->update(['status' => 'selesai']);
        ActivityLog::record('update', 'Pengajuan Training', $trainingRequest->nama_training, "Training \"{$trainingRequest->nama_training}\" ditandai selesai");
        return back()->with('success', "Training \"{$trainingRequest->nama_training}\" ditandai selesai.");
    }

    // Persetujuan oleh HR (hr-staff) / super-admin.
    private function bolehProses(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super-admin') || $user->hasRole('hr-staff');
    }

    // Ubah/batalkan: cuma selama masih diajukan, oleh pengaju sendiri atau HR.
    private function cekBolehUbah(TrainingRequest $tr): ?string
    {
        if ($tr->status !== 'diajukan') return 'Pengajuan yang sudah diproses tidak bisa diubah/dibatalkan.';
        if (!$this->bolehProses() && $tr->diajukan_oleh != auth()->id()) return 'Hanya pengaju atau HR yang bisa mengubah pengajuan ini.';
        return null;
    }

    private function cekKuota(int $divisionId, int $tahun, ?int $kecualiId = null): ?array
    {
        $jumlah = TrainingRequest::where('ho_division_id', $divisionId)->where('tahun', $tahun)
            ->whereIn('status', self::STATUS_KUOTA)->when($kecualiId, fn($q) => $q->where('id', '!=', $kecualiId))->count();
        if ($jumlah < self::MAKS_PER_DIVISI) return null;
        $nama = HoDivision::whereKey($divisionId)->value('nama');
        return ['ho_division_id' => "Divisi {$nama} sudah mencapai batas " . self::MAKS_PER_DIVISI . " pengajuan training di tahun {$tahun}."];
    }

    // Peserta harus karyawan HO yang masih aktif.
    private function cekPeserta(array $ids): ?array
    {
        $ho  = Project::where('kode', 'HO')->value('id');
        $sah = Employee::aktif()->where('project_id', $ho)->whereIn('id', $ids)->count();
        return $sah === count(array_unique($ids)) ? null : ['employee_ids' => 'Peserta harus karyawan Head Office yang masih aktif.'];
    }
}
