<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeGoal extends Model
{
    protected $fillable = [
        'employee_id', 'reviewer_id', 'nama_goal', 'deskripsi', 'siklus',
        'tanggal_mulai', 'tanggal_selesai', 'satuan',
        'baseline', 'target', 'progress_sekarang', 'bobot',
        'catatan', 'diperbarui_oleh', 'aktif',
    ];

    protected $casts = [
        'tanggal_mulai'     => 'date',
        'tanggal_selesai'   => 'date',
        'baseline'          => 'float',
        'target'            => 'float',
        'progress_sekarang' => 'float',
        'bobot'             => 'float',
        'aktif'             => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Penanggung jawab update progress goal ini — bisa ditugaskan bebas (tidak harus atasan
    // langsung), dipilih saat goal dibuat/diedit. Dipakai buat badge "pending review" manajer.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    // Persentase capaian dari baseline ke target, dibatasi 0-100 — dipakai buat progress bar
    // dan sebagai dasar hitung skor akhir gabungan (dikali bobot).
    public function getProgressPercentAttribute(): float
    {
        $range = $this->target - $this->baseline;
        if ($range == 0) return $this->progress_sekarang >= $this->target ? 100 : 0;
        $pct = ($this->progress_sekarang - $this->baseline) / $range * 100;
        return round(max(0, min(100, $pct)), 2);
    }

    // not_updated | completed | on_track | off_track — dipakai buat donut chart ringkasan.
    public function getStatusAttribute(): string
    {
        if ($this->progress_sekarang == $this->baseline) return 'not_updated';
        if ($this->progress_percent >= 100) return 'completed';

        $totalHari = $this->tanggal_mulai->diffInDays($this->tanggal_selesai) ?: 1;
        $hariBerjalan = max(0, min($totalHari, $this->tanggal_mulai->diffInDays(now(), false)));
        $elapsedPercent = $hariBerjalan / $totalHari * 100;

        return $this->progress_percent >= $elapsedPercent ? 'on_track' : 'off_track';
    }
}
