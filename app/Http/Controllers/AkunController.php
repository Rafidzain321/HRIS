<?php
namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// "Akun Saya" — semua role mengelola akunnya sendiri: foto profil, nama tampilan, email login, nomor WhatsApp.
// Ganti password tetap lewat UserManagementController::changePassword. Role & akses kantor cuma bisa diubah admin.
class AkunController extends Controller
{
    public function index()
    {
        $user   = auth()->user();
        $akses  = $user->aksesKantor();
        $kantor = $akses === null ? null : Project::whereIn('id', $akses)->orderBy('nama')->pluck('nama');

        return Inertia::render('Akun/Index', [
            'akun' => [
                'name'         => $user->name,
                'email'        => $user->email,
                'no_wa'        => $user->no_wa,
                'kantor_utama' => $user->project?->nama,
                'akses_kantor' => $kantor, // null = semua kantor
            ],
        ]);
    }

    const LABEL_FIELD = ['name' => 'nama', 'email' => 'email login', 'no_wa' => 'nomor WhatsApp'];

    public function update(Request $request)
    {
        $user = auth()->user();
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => "required|email|max:255|unique:users,email,{$user->id}",
            'no_wa' => ['nullable', 'string', 'max:20', 'regex:/^(\+?62|0)8[0-9]{7,13}$/'],
        ], [
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'email.email'  => 'Format email tidak valid.',
            'no_wa.regex'  => 'Format nomor WhatsApp tidak valid (cth: 081234567890 atau +6281234567890).',
        ]);
        $data['no_wa'] = $data['no_wa'] ? preg_replace('/^(\+?62)/', '0', $data['no_wa']) : null;

        $lama    = $user->only(['name', 'email', 'no_wa']);
        $berubah = array_keys(array_diff_assoc($data, $lama));
        if (!$berubah) return back();

        $user->update($data);
        $ket = 'Ubah ' . implode(' & ', array_map(fn($f) => self::LABEL_FIELD[$f], $berubah));
        if (in_array('email', $berubah)) $ket .= " (email: {$lama['email']} -> {$data['email']})";
        ActivityLog::record('update', 'Akun', $user->name, $ket);
        return back()->with('success', in_array('email', $berubah)
            ? "Akun berhasil diperbarui. Login berikutnya pakai email {$data['email']}."
            : 'Akun berhasil diperbarui.');
    }

    public function uploadFoto(Request $request)
    {
        $request->validate(['foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048'],
            ['foto.max' => 'Ukuran foto maksimal 2 MB.', 'foto.image' => 'File harus berupa gambar.', 'foto.mimes' => 'Foto harus JPG, PNG, atau WEBP.']);

        $user = auth()->user();
        if ($user->foto_path) Storage::disk('local')->delete($user->foto_path);
        $path = $request->file('foto')->storeAs('avatars', $user->id . '_' . time() . '.' . strtolower($request->file('foto')->extension()), 'local');
        $user->update(['foto_path' => $path]);

        ActivityLog::record('update', 'Akun', $user->name, 'Ganti foto profil');
        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function hapusFoto()
    {
        $user = auth()->user();
        if ($user->foto_path) {
            Storage::disk('local')->delete($user->foto_path);
            $user->update(['foto_path' => null]);
            ActivityLog::record('update', 'Akun', $user->name, 'Hapus foto profil');
        }
        return back()->with('success', 'Foto profil dihapus.');
    }

    // Foto profil disimpan di disk private, disajikan lewat route ini (wajib login).
    public function foto()
    {
        $path = auth()->user()->foto_path;
        if (!$path || !Storage::disk('local')->exists($path)) abort(404);
        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
