<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Pengajuan training dari divisi HO — diajukan, lalu disetujui/ditolak HR, lalu ditandai selesai.
class TrainingRequest extends Model
{
    const STATUS = ['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai'];

    protected $fillable = [
        'ho_division_id', 'tahun', 'nama_training', 'penyelenggara', 'tanggal_rencana', 'estimasi_biaya',
        'alasan', 'status', 'catatan_hr', 'diajukan_oleh', 'diproses_oleh', 'diproses_at',
    ];

    protected $casts = ['tanggal_rencana' => 'date', 'diproses_at' => 'datetime'];

    public function division(): BelongsTo { return $this->belongsTo(HoDivision::class, 'ho_division_id'); }
    public function pengaju(): BelongsTo  { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function pemroses(): BelongsTo { return $this->belongsTo(User::class, 'diproses_oleh'); }
    public function employees(): BelongsToMany { return $this->belongsToMany(Employee::class, 'training_request_employee'); }
}
