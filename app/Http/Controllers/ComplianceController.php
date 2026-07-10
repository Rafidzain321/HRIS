<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use Inertia\Inertia;

class ComplianceController extends Controller
{
    public function sim()
    {
        $expired = Employee::simExpired()->with('position')->get()->map(fn($e) => [
            'id' => $e->id, 'id_badge' => $e->id_badge, 'nama' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'type_sim' => $e->type_sim, 'no_sim' => $e->no_sim,
            'expired_sim' => $e->expired_sim?->format('d M Y'),
            'days_overdue' => $e->expired_sim ? (int)$e->expired_sim->diffInDays(now()) : 0,
        ]);
        $warning = Employee::simWarning()->with('position')->get()->map(fn($e) => [
            'id' => $e->id, 'id_badge' => $e->id_badge, 'nama' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'type_sim' => $e->type_sim,
            'expired_sim' => $e->expired_sim?->format('d M Y'),
            'days_left' => $e->expired_sim ? (int)now()->diffInDays($e->expired_sim) : 0,
        ]);
        return Inertia::render('Compliance/Sim', compact('expired', 'warning'));
    }

    public function mcu()
    {
        $expired = Employee::mcuExpired()->with('position')->get()->map(fn($e) => [
            'id' => $e->id, 'id_badge' => $e->id_badge, 'nama' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'exp_mcu' => $e->exp_mcu?->format('d M Y'),
            'status_mcu' => $e->status_mcu, 'lokasi_mcu' => $e->lokasi_mcu,
            'days_overdue' => $e->exp_mcu ? (int)$e->exp_mcu->diffInDays(now()) : 0,
        ]);
        return Inertia::render('Compliance/Mcu', compact('expired'));
    }

    public function badge()
    {
        $expired = Employee::badgeExpired()->with('position')->get()->map(fn($e) => [
            'id_badge' => $e->id_badge, 'nama' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'expire_badge' => $e->expire_badge?->format('d M Y'),
            'status_kp' => $e->status_kp, 'exp_kp' => $e->exp_kp?->format('d M Y'),
            'kp_ready' => $e->kp_ready,
        ]);
        $warning = Employee::badgeWarning()->with('position')->get()->map(fn($e) => [
            'id_badge' => $e->id_badge, 'nama' => $e->nama_lengkap,
            'jabatan' => $e->position?->nama_jabatan ?? '-',
            'expire_badge' => $e->expire_badge?->format('d M Y'),
            'status_kp' => $e->status_kp, 'exp_kp' => $e->exp_kp?->format('d M Y'),
            'kp_ready' => $e->kp_ready,
        ]);
        return Inertia::render('Compliance/Badge', compact('expired', 'warning'));
    }

    public function ppe()
    {
        $employees = Employee::aktif()->with('position')
            ->get(['id','id_badge','nama_lengkap','position_id','ukuran_baju','ukuran_sepatu']);
        return Inertia::render('Compliance/Ppe', compact('employees'));
    }
}
