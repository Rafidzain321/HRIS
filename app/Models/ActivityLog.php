<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'module', 'target_name', 'description', 'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Helper static method untuk log mudah dari mana saja
    public static function record(string $action, string $module, ?string $target = null, ?string $desc = null): void
    {
        static::create([
            'user_id'     => auth()->id(),
            'action'      => $action,
            'module'      => $module,
            'target_name' => $target,
            'description' => $desc,
            'ip_address'  => request()->ip(),
        ]);
    }

    // Nama kolom → label yang mudah dibaca di Log Aktivitas. Kolom yang tidak ada di sini
    // ditampilkan dari nama kolomnya (mis. "ukuran_baju" → "Ukuran baju").
    const LABEL = [
        'nama_lengkap' => 'Nama', 'no_ktp' => 'NIK', 'id_badge' => 'ID Badge', 'nama_ibu' => 'Nama Ibu',
        'no_telepon' => 'No. Telepon', 'no_rekening' => 'No. Rekening', 'nama_bank' => 'Bank',
        'no_bpjs' => 'No. BPJS', 'no_bpjs_tk' => 'No. BPJS TK', 'no_bpjs_kes' => 'No. BPJS Kesehatan',
        'tempat_lahir' => 'Tempat Lahir', 'tanggal_lahir' => 'Tgl Lahir', 'tanggal_masuk' => 'Tgl Masuk',
        'position_id' => 'Jabatan', 'department_id' => 'Departemen', 'project_id' => 'Kantor', 'atasan_id' => 'Atasan',
        'ptkp' => 'PTKP', 'rfid' => 'RFID', 'status_kerja' => 'Status Kerja',
        'expire_badge' => 'Expired Badge', 'status_kp' => 'Status KP', 'kp_ready' => 'KP Ready', 'exp_kp' => 'Expired KP',
        'type_sim' => 'Tipe SIM', 'no_sim' => 'No. SIM', 'expired_sim' => 'Expired SIM', 'sim_kota_keluar' => 'Kota SIM',
        'sio_k3' => 'SIO K3', 'no_sio' => 'No. SIO', 'expire_sio' => 'Expired SIO', 'tipe_sio' => 'Tipe SIO',
        'tgl_mcu' => 'Tgl MCU', 'exp_mcu' => 'Expired MCU', 'status_mcu' => 'Status MCU',
        'lokasi_mcu' => 'Lokasi MCU', 'derajat_kesehatan' => 'Derajat Kesehatan',
        'start_pkwt' => 'Start PKWT', 'end_pkwt' => 'End PKWT', 'no_contract' => 'No. Kontrak',
        'tanggal_keluar' => 'Tgl Keluar', 'alasan_keluar' => 'Alasan Keluar', 'catatan_keluar' => 'Catatan Keluar',
        'name' => 'Nama', 'email' => 'Email', 'no_wa' => 'No. WA', 'is_active' => 'Aktif',
    ];

    // Kolom ID yang ditampilkan sebagai nama di log.
    const RELASI = [
        'position_id'   => [Position::class, 'nama_jabatan'],
        'department_id' => [Department::class, 'nama'],
        'project_id'    => [Project::class, 'nama'],
        'atasan_id'     => [Employee::class, 'nama_lengkap'],
    ];

    // Rincian perubahan model yang BARU disimpan (panggil setelah ->update()/->save()), untuk
    // disambung ke deskripsi log, mis. " | Tgl MCU: 2026-05-24 → 2027-05-24, NIK: (kosong) → 1471...".
    // '' kalau tidak ada yang berubah.
    public static function rincian(Model $model, array $abaikan = []): string
    {
        $abaikan = [...$abaikan, 'created_at', 'updated_at', 'password', 'plain_password', 'remember_token'];
        $sebelum = $model->getPrevious();

        $baris = collect($model->getChanges())->except($abaikan)
            ->map(fn ($baru, $kolom) => (self::LABEL[$kolom] ?? ucfirst(str_replace('_', ' ', $kolom)))
                . ': ' . self::nilai($sebelum[$kolom] ?? null, $kolom) . ' → ' . self::nilai($baru, $kolom))
            ->implode(', ');

        return $baris === '' ? '' : " | {$baris}";
    }

    private static function nilai($v, string $kolom): string
    {
        if ($v === null || $v === '') return '(kosong)';
        if (isset(self::RELASI[$kolom])) {
            [$kelas, $field] = self::RELASI[$kolom];
            return $kelas::whereKey($v)->value($field) ?? "#{$v}";
        }
        $v = preg_replace('/^(\d{4}-\d{2}-\d{2}) 00:00:00$/', '$1', (string) $v); // kolom date
        return mb_strimwidth($v, 0, 80, '…');
    }
}
