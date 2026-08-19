<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;
use Inertia\Inertia;

class NotificationController extends Controller
{
    private function sortItems($items)
    {
        $warning = $items->where('level', 'warning')->sortBy('days')->values();
        $expired = $items->where('level', 'expired')->sortByDesc('days')->values();
        return $warning->concat($expired)->values();
    }

    // ── Base query Employee per project ──
    private function empQuery()
    {
        $query = Employee::aktif()->with('position');
        $this->applyProjectFilter($query);
        return $query;
    }

    // ── JSON endpoint untuk polling realtime ──
    public function data()
    {
        $today = Carbon::today();
        $in30  = $today->copy()->addDays(30);
        $items = collect();

        // SIM
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('expired_sim', '<', $today)
                ->orWhereBetween('expired_sim', [$today, $in30]))
            ->orderBy('expired_sim')->limit(20)->get()
            ->each(fn($e) => $items->push([
                'id'      => $e->id,
                'type'    => 'sim',
                'level'   => $e->expired_sim < $today ? 'expired' : 'warning',
                'nama'    => $e->nama_lengkap,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'SIM',
                'date'    => $e->expired_sim?->format('d M Y'),
                'days'    => $today->diffInDays($e->expired_sim, false),
                'href'    => '/compliance/sim?filter=' . ($e->expired_sim < $today ? 'expired' : 'warning'),
            ]));

        // MCU
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('exp_mcu', '<', $today)
                ->orWhereBetween('exp_mcu', [$today, $in30]))
            ->orderBy('exp_mcu')->limit(20)->get()
            ->each(fn($e) => $items->push([
                'id'      => $e->id,
                'type'    => 'mcu',
                'level'   => $e->exp_mcu < $today ? 'expired' : 'warning',
                'nama'    => $e->nama_lengkap,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'MCU',
                'date'    => $e->exp_mcu?->format('d M Y'),
                'days'    => $today->diffInDays($e->exp_mcu, false),
                'href'    => '/compliance/mcu?filter=' . ($e->exp_mcu < $today ? 'expired' : 'warning'),
            ]));

        // Badge
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('expire_badge', '<', $today)
                ->orWhereBetween('expire_badge', [$today, $in30]))
            ->orderBy('expire_badge')->limit(20)->get()
            ->each(fn($e) => $items->push([
                'id'      => $e->id,
                'type'    => 'badge',
                'level'   => $e->expire_badge < $today ? 'expired' : 'warning',
                'nama'    => $e->nama_lengkap,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'Badge',
                'date'    => $e->expire_badge?->format('d M Y'),
                'days'    => $today->diffInDays($e->expire_badge, false),
                'href'    => '/compliance/badge?filter=' . ($e->expire_badge < $today ? 'expired' : 'warning') . '&tab=badge',
            ]));

        // KP
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('exp_kp', '<', $today)
                ->orWhereBetween('exp_kp', [$today, $in30]))
            ->orderBy('exp_kp')->limit(20)->get()
            ->each(fn($e) => $items->push([
                'id'      => $e->id,
                'type'    => 'kp',
                'level'   => $e->exp_kp < $today ? 'expired' : 'warning',
                'nama'    => $e->nama_lengkap,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'KP',
                'date'    => $e->exp_kp?->format('d M Y'),
                'days'    => $today->diffInDays($e->exp_kp, false),
                'href'    => '/compliance/badge?filter=' . ($e->exp_kp < $today ? 'expired' : 'warning') . '&tab=kp',
            ]));

        $sorted  = $this->sortItems($items);
        $summary = [
            'total'   => $sorted->count(),
            'expired' => $sorted->where('level', 'expired')->count(),
            'warning' => $sorted->where('level', 'warning')->count(),
            'by_type' => [
                'sim'   => $sorted->where('type', 'sim')->count(),
                'mcu'   => $sorted->where('type', 'mcu')->count(),
                'badge' => $sorted->where('type', 'badge')->count(),
                'kp'    => $sorted->where('type', 'kp')->count(),
            ],
        ];
        return response()->json(['items' => $sorted, 'summary' => $summary]);
    }

    // ── Halaman notifikasi lengkap ──
    public function page()
    {
        $today   = Carbon::today();
        $in30    = $today->copy()->addDays(30);
        $in3bln  = $today->copy()->addMonths(3);
        $pid     = $this->activeProjectId();

        // ── MCU ──
        $mcu = collect();
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('exp_mcu', '<', $today)
                ->orWhereBetween('exp_mcu', [$today, $in30]))
            ->get()->each(fn($e) => $mcu->push([
                'id'      => $e->id,
                'nama'    => $e->nama_lengkap,
                'badge'   => $e->id_badge,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'MCU',
                'date'    => $e->exp_mcu?->format('d M Y'),
                'days'    => $today->diffInDays($e->exp_mcu, false),
                'level'   => $e->exp_mcu < $today ? 'expired' : 'warning',
                'href'    => '/compliance/mcu',
            ]));

        // ── Badge ──
        $badge = collect();
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('expire_badge', '<', $today)
                ->orWhereBetween('expire_badge', [$today, $in30]))
            ->get()->each(fn($e) => $badge->push([
                'id'      => $e->id,
                'nama'    => $e->nama_lengkap,
                'badge'   => $e->id_badge,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'Badge',
                'date'    => $e->expire_badge?->format('d M Y'),
                'days'    => $today->diffInDays($e->expire_badge, false),
                'level'   => $e->expire_badge < $today ? 'expired' : 'warning',
                'href'    => '/compliance/badge?tab=badge',
            ]));

        // ── KP ──
        $kp = collect();
        (clone $this->empQuery())
            ->where(fn($q) => $q->where('exp_kp', '<', $today)
                ->orWhereBetween('exp_kp', [$today, $in30]))
            ->get()->each(fn($e) => $kp->push([
                'id'      => $e->id,
                'nama'    => $e->nama_lengkap,
                'badge'   => $e->id_badge,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'KP',
                'date'    => $e->exp_kp?->format('d M Y'),
                'days'    => $today->diffInDays($e->exp_kp, false),
                'level'   => $e->exp_kp < $today ? 'expired' : 'warning',
                'href'    => '/compliance/badge?tab=kp',
            ]));

        // ── SIM ──
        $sim = collect();
        (clone $this->empQuery())
            ->whereNotNull('type_sim')
            ->where(fn($q) => $q->where('expired_sim', '<', $today)
                ->orWhereBetween('expired_sim', [$today, $in30]))
            ->get()->each(fn($e) => $sim->push([
                'id'      => $e->id,
                'nama'    => $e->nama_lengkap,
                'badge'   => $e->id_badge,
                'jabatan' => $e->position?->nama_jabatan ?? '-',
                'label'   => 'SIM ' . $e->type_sim,
                'date'    => $e->expired_sim?->format('d M Y'),
                'days'    => $today->diffInDays($e->expired_sim, false),
                'level'   => $e->expired_sim < $today ? 'expired' : 'warning',
                'href'    => '/compliance/sim',
            ]));

        // ── PENSIUN (warning 3 bulan sebelum usia 56) ──
        $pensiun = collect();
        (clone $this->empQuery())
            ->whereNotNull('tanggal_lahir')
            ->get()
            ->each(function ($e) use ($today, $in3bln, &$pensiun) {
                $ultah56 = $e->tanggal_lahir->copy()->addYears(56);
                if ($ultah56 > $in3bln) return;
                $days = $today->diffInDays($ultah56, false);
                $pensiun->push([
                    'id'             => $e->id,
                    'nama'           => $e->nama_lengkap,
                    'badge'          => $e->id_badge,
                    'jabatan'        => $e->position?->nama_jabatan ?? '-',
                    'label'          => 'Pensiun 56 Thn',
                    'date'           => $ultah56->format('d M Y'),
                    'days'           => $days,
                    'level'          => $days < 0 ? 'expired' : 'warning',
                    'href'           => '/employees',
                    'tanggal_lahir'  => $e->tanggal_lahir->format('d M Y'),
                    'usia'           => floor($e->tanggal_lahir->diffInYears($today, true)),
                ]);
            });

        // ── Equipment Unit — difilter per project ──
        $equipUnit  = collect();
        $equipFields = [
            'stnk_expired'          => 'STNK',
            'tax_expired'           => 'Pajak',
            'kir_expired'           => 'KIR',
            'vehicle_pass_expired'  => 'Vehicle Pass',
            'smbr_pass_expired'     => 'SMBR Pass',
            'green_stiker_expired'  => 'Green Stiker',
            'sio_migas_expired'     => 'SIO Migas',
            'sio_disnaker_expired'  => 'SIO Disnaker',
            'k3_p3a2_expired'       => 'K3 P3A2',
        ];
        \App\Models\Equipment::when($pid, fn($q) => $q->where('project_id', $pid))
            ->get()->each(function ($eq) use ($today, $in30, &$equipUnit, $equipFields) {
            foreach ($equipFields as $field => $label) {
                if (!$eq->$field) continue;
                $date = Carbon::parse($eq->$field);
                if ($date >= $today && $date > $in30) continue;
                $days = $today->diffInDays($date, false);
                $equipUnit->push([
                    'id'      => $eq->id,
                    'nama'    => $eq->no_unit,
                    'badge'   => $eq->plat_nomor ?? '-',
                    'jabatan' => $eq->type_unit ?? '-',
                    'label'   => $label,
                    'date'    => $date->format('d M Y'),
                    'days'    => $days,
                    'level'   => $date < $today ? 'expired' : 'warning',
                    'href'    => '/equipment?view=vehicle',
                ]);
            }
        });

        // ── Equipment Operator — difilter per project ──
        $equipOp  = collect();
        $opFields = [
            'license_expired_date'  => 'License',
            'kp_expired_date'       => 'KP',
            'permit_expired_date'   => 'Permit',
            'sio_migas_expired'     => 'SIO Migas',
            'sio_disnaker_expired'  => 'SIO Disnaker',
            'k3_p3a2_expired'       => 'K3 P3A2',
        ];
        \App\Models\EquipmentOperator::with('equipment')
            ->where('is_active', true)
            ->when($pid, fn($q) => $q->where('project_id', $pid))
            ->get()->each(function ($op) use ($today, $in30, &$equipOp, $opFields) {
                foreach ($opFields as $field => $label) {
                    if (!$op->$field) continue;
                    $date = Carbon::parse($op->$field);
                    if ($date >= $today && $date > $in30) continue;
                    $days = $today->diffInDays($date, false);
                    $equipOp->push([
                        'id'      => $op->equipment_id,
                        'nama'    => $op->operator_name,
                        'badge'   => $op->badge ?? '-',
                        'jabatan' => $op->equipment?->no_unit ?? '-',
                        'label'   => $label,
                        'date'    => $date->format('d M Y'),
                        'days'    => $days,
                        'level'   => $date < $today ? 'expired' : 'warning',
                        'href'    => '/equipment?view=operator',
                    ]);
                }
            });

        return Inertia::render('Notifications/Index', [
            'project_info' => $pid
                ? \App\Models\Project::find($pid, ['id', 'kode', 'nama', 'tipe_gaji'])
                : null,
            'tabs' => [
                'mcu'        => $this->sortItems($mcu)->values(),
                'badge'      => $this->sortItems($badge)->values(),
                'kp'         => $this->sortItems($kp)->values(),
                'sim'        => $this->sortItems($sim)->values(),
                'equip_unit' => $this->sortItems($equipUnit)->values(),
                'equip_op'   => $this->sortItems($equipOp)->values(),
                'pensiun'    => $this->sortItems($pensiun)->values(),
            ],
            'summary' => [
                'mcu'        => $mcu->count(),
                'badge'      => $badge->count(),
                'kp'         => $kp->count(),
                'sim'        => $sim->count(),
                'equip_unit' => $equipUnit->count(),
                'equip_op'   => $equipOp->count(),
                'pensiun'    => $pensiun->count(),
                'total'      => $mcu->count() + $badge->count() + $kp->count()
                            + $sim->count() + $equipUnit->count()
                            + $equipOp->count() + $pensiun->count(),
            ],
        ]);
    }
}