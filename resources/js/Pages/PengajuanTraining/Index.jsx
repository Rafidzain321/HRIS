// resources/js/Pages/PengajuanTraining/Index.jsx
import React, { useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import {
  GraduationCap, Plus, X, Search, Pencil, Trash2, Check, Ban, Eye, Loader2, TriangleAlert, CheckCircle2, Users,
} from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};
const lbl = { fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' };
const th  = { textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)', whiteSpace: 'nowrap' };
const td  = { padding: '10px 14px', verticalAlign: 'top' };

const STATUS_META = {
  diajukan:  { label: 'Diajukan',  color: '#E8A020', bg: 'rgba(232,160,32,.12)' },
  disetujui: { label: 'Disetujui', color: 'var(--blue)', bg: 'rgba(58,143,224,.12)' },
  ditolak:   { label: 'Ditolak',   color: '#E04545', bg: 'rgba(224,69,69,.12)' },
  selesai:   { label: 'Selesai',   color: '#22C97A', bg: 'rgba(34,201,122,.12)' },
};

const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso.slice(0, 10) + 'T00:00:00');
  if (isNaN(d.getTime())) return iso;
  return `${d.getDate()} ${MONTH_ABBR[d.getMonth()]} ${d.getFullYear()}`;
}
const fmtRp = v => (v || v === 0) ? 'Rp ' + Number(v).toLocaleString('id-ID') : '—';

function ErrorText({ children }) {
  if (!children) return null;
  return <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 10.5, color: '#E04545', marginTop: 4 }}><TriangleAlert size={12} />{children}</div>;
}

function StatusBadge({ status }) {
  const m = STATUS_META[status] || { label: status, color: 'var(--muted2)', bg: 'var(--bg3)' };
  return <span style={{ fontSize: 10.5, fontWeight: 700, padding: '3px 9px', borderRadius: 99, background: m.bg, color: m.color, whiteSpace: 'nowrap' }}>{m.label}</span>;
}

function Modal({ title, icon, width = 520, onClose, children, footer }) {
  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 400, background: 'rgba(0,0,0,.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}
      onMouseDown={e => { e.currentTarget.dataset.downOutside = e.target === e.currentTarget; }}
      onClick={e => { e.target === e.currentTarget && e.currentTarget.dataset.downOutside === 'true' && onClose(); }}>
      <div style={{ background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 16, width: `min(${width}px,100%)`, maxHeight: '90vh', display: 'flex', flexDirection: 'column', boxShadow: '0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 20px', borderBottom: '1px solid var(--border)' }}>
          <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}>{icon} {title}</div>
          <div onClick={onClose} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></div>
        </div>
        <div style={{ overflowY: 'auto', flex: 1 }}>{children}</div>
        {footer && <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', padding: '12px 20px', borderTop: '1px solid var(--border)' }}>{footer}</div>}
      </div>
    </div>
  );
}

const btnBatal = { padding: '9px 18px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 12.5, cursor: 'pointer', fontFamily: "'Outfit',sans-serif" };
const btnUtama = (bg = 'linear-gradient(135deg,#E8A020,#A06010)', color = '#0C0F14') => ({ padding: '9px 22px', borderRadius: 8, border: 'none', background: bg, color, fontSize: 12.5, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'inline-flex', alignItems: 'center', gap: 6 });
const btnKecil = (color, bg, border) => ({ padding: '4px 9px', borderRadius: 6, fontSize: 11, fontWeight: 600, background: bg, color, border: `1px solid ${border}`, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'inline-flex', alignItems: 'center', gap: 4, whiteSpace: 'nowrap' });

// ── PILIH PESERTA (multi-select karyawan HO) ──
function PesertaPicker({ employees, divisionId, value, onChange }) {
  const [search, setSearch] = useState('');
  const [semua, setSemua]   = useState(false);
  const toggle = id => onChange(value.includes(id) ? value.filter(x => x !== id) : [...value, id]);
  const adaDiDivisi = employees.some(e => e.ho_division_id === Number(divisionId));
  const q = search.trim().toLowerCase();
  const list = employees
    .filter(e => semua || !divisionId || !adaDiDivisi || e.ho_division_id === Number(divisionId))
    .filter(e => !q || e.nama.toLowerCase().includes(q) || (e.jabatan || '').toLowerCase().includes(q));
  const dipilih = employees.filter(e => value.includes(e.id));
  return (
    <div style={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--bg3)', padding: 6 }}>
      {dipilih.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, marginBottom: 6 }}>
          {dipilih.map(e => (
            <span key={e.id} style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11.5, fontWeight: 600, padding: '3px 6px 3px 9px', borderRadius: 99, background: 'rgba(232,160,32,.14)', color: 'var(--accent)', border: '1px solid rgba(232,160,32,.35)' }}>
              {e.nama}<span onClick={() => toggle(e.id)} style={{ cursor: 'pointer', display: 'flex' }}><X size={12} /></span>
            </span>
          ))}
        </div>
      )}
      <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
        <div style={{ position: 'relative', flex: 1 }}>
          <span style={{ position: 'absolute', left: 8, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 28, background: 'var(--bg2)' }} />
        </div>
        {divisionId && adaDiDivisi && (
          <label style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 11, color: 'var(--muted2)', whiteSpace: 'nowrap', cursor: 'pointer' }}>
            <input type="checkbox" checked={semua} onChange={e => setSemua(e.target.checked)} style={{ accentColor: '#E8A020' }} /> Semua HO
          </label>
        )}
      </div>
      <div style={{ maxHeight: 200, overflowY: 'auto', marginTop: 6 }}>
        {list.map(e => {
          const on = value.includes(e.id);
          return (
            <div key={e.id} onClick={() => toggle(e.id)} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '6px 8px', borderRadius: 6, cursor: 'pointer', fontSize: 12.5, background: on ? 'rgba(232,160,32,.1)' : 'transparent' }}>
              <span style={{ width: 15, height: 15, borderRadius: 4, border: `1.5px solid ${on ? 'var(--accent)' : 'var(--border2)'}`, background: on ? 'var(--accent)' : 'transparent', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                {on && <Check size={11} color="#0C0F14" />}
              </span>
              <span style={{ fontWeight: on ? 600 : 400 }}>{e.nama}</span>
              <span style={{ marginLeft: 'auto', fontSize: 10.5, color: 'var(--muted)', textAlign: 'right' }}>{e.jabatan}</span>
            </div>
          );
        })}
        {list.length === 0 && <div style={{ padding: 12, textAlign: 'center', fontSize: 12, color: 'var(--muted)' }}>Tidak ada karyawan yang cocok.</div>}
      </div>
      {divisionId && !adaDiDivisi && (
        <div style={{ fontSize: 10, color: 'var(--muted)', marginTop: 4, padding: '0 2px' }}>Belum ada karyawan yang diset ke departemen ini (atur di Edit Karyawan → Detail HO) — semua karyawan HO ditampilkan.</div>
      )}
    </div>
  );
}

// ── FORM AJUKAN / UBAH ──
function FormModal({ data, divisions, employees, maks, onClose }) {
  const edit = !!data;
  const [form, setForm] = useState({
    ho_division_id:  data?.ho_division_id ?? '',
    nama_training:   data?.nama_training ?? '',
    penyelenggara:   data?.penyelenggara ?? '',
    tanggal_rencana: data?.tanggal_rencana ?? '',
    estimasi_biaya:  data?.estimasi_biaya ?? '',
    alasan:          data?.alasan ?? '',
    employee_ids:    data?.peserta?.map(p => p.id) ?? [],
  });
  const [errors, setErrors]   = useState({});
  const [loading, setLoading] = useState(false);
  const set = (k, v) => { setForm(f => ({ ...f, [k]: v })); setErrors(e => ({ ...e, [k]: null })); };
  const divAktif = divisions.filter(d => d.is_active || d.id === data?.ho_division_id);
  const divDipilih = divisions.find(d => d.id === Number(form.ho_division_id));
  // Kuota yang ditampilkan dihitung dari tahun yang sedang dibuka; backend tetap cek ulang sesuai tahun tanggal rencana.
  const sisa = divDipilih ? maks - divDipilih.terpakai + (edit && data.ho_division_id === divDipilih.id ? 1 : 0) : null;

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    const payload = { ...form, estimasi_biaya: form.estimasi_biaya === '' ? null : Number(form.estimasi_biaya) };
    const opt = { preserveScroll: true, onSuccess: () => onClose(), onError: err => setErrors(err), onFinish: () => setLoading(false) };
    if (edit) router.put(`/pengajuan-training/${data.id}`, payload, opt);
    else router.post('/pengajuan-training', payload, opt);
  }

  return (
    <Modal title={edit ? 'Ubah Pengajuan Training' : 'Ajukan Training'} icon={<GraduationCap size={15} />} width={560} onClose={onClose}
      footer={<>
        <button type="button" onClick={onClose} style={btnBatal}>Batal</button>
        <button type="submit" form="form-pengajuan" disabled={loading} style={{ ...btnUtama(), opacity: loading ? .7 : 1 }}>
          {loading ? <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> : <CheckCircle2 size={14} />} {edit ? 'Simpan' : 'Kirim Pengajuan'}
        </button>
      </>}>
      <form id="form-pengajuan" onSubmit={submit} style={{ padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div>
          <label style={lbl}>Departemen *</label>
          <select style={{ ...inp, borderColor: errors.ho_division_id ? '#E04545' : 'var(--border)' }} value={form.ho_division_id} onChange={e => set('ho_division_id', e.target.value ? Number(e.target.value) : '')}>
            <option value="">— Pilih Departemen —</option>
            {divAktif.map(d => <option key={d.id} value={d.id}>{d.nama}</option>)}
          </select>
          {divDipilih && !errors.ho_division_id && (
            <div style={{ fontSize: 10.5, marginTop: 4, color: sisa > 0 ? 'var(--muted)' : '#E04545' }}>
              Kuota departemen ini: {divDipilih.terpakai}/{maks} terpakai{sisa <= 0 ? ' — sudah penuh untuk tahun ini' : ` (sisa ${sisa})`}
            </div>
          )}
          <ErrorText>{errors.ho_division_id}</ErrorText>
        </div>
        <div>
          <label style={lbl}>Nama / Jenis Training *</label>
          <input style={{ ...inp, borderColor: errors.nama_training ? '#E04545' : 'var(--border)' }} value={form.nama_training} maxLength={200} onChange={e => set('nama_training', e.target.value)} placeholder="cth: Brevet Pajak A & B" />
          <ErrorText>{errors.nama_training}</ErrorText>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
          <div>
            <label style={lbl}>Tempat / Penyelenggara</label>
            <input style={inp} value={form.penyelenggara} maxLength={200} onChange={e => set('penyelenggara', e.target.value)} placeholder="cth: IAI Jakarta / online" />
          </div>
          <div>
            <label style={lbl}>Tanggal Rencana *</label>
            <input type="date" style={{ ...inp, borderColor: errors.tanggal_rencana ? '#E04545' : 'var(--border)' }} value={form.tanggal_rencana} onChange={e => set('tanggal_rencana', e.target.value)} />
            <ErrorText>{errors.tanggal_rencana}</ErrorText>
          </div>
        </div>
        <div>
          <label style={lbl}>Estimasi Biaya (Rp)</label>
          <input style={{ ...inp, borderColor: errors.estimasi_biaya ? '#E04545' : 'var(--border)' }} inputMode="numeric"
            value={form.estimasi_biaya === '' ? '' : Number(form.estimasi_biaya).toLocaleString('id-ID')}
            onChange={e => { const v = e.target.value.replace(/\D/g, ''); set('estimasi_biaya', v === '' ? '' : Number(v)); }} placeholder="cth: 2.500.000" />
          <ErrorText>{errors.estimasi_biaya}</ErrorText>
        </div>
        <div>
          <label style={lbl}>Peserta * ({form.employee_ids.length} dipilih)</label>
          <PesertaPicker employees={employees} divisionId={form.ho_division_id} value={form.employee_ids} onChange={v => set('employee_ids', v)} />
          <ErrorText>{errors.employee_ids}</ErrorText>
        </div>
        <div>
          <label style={lbl}>Alasan / Kebutuhan Pekerjaan *</label>
          <textarea style={{ ...inp, minHeight: 72, resize: 'vertical', borderColor: errors.alasan ? '#E04545' : 'var(--border)' }} value={form.alasan} maxLength={2000} onChange={e => set('alasan', e.target.value)} placeholder="Kenapa training ini dibutuhkan untuk pekerjaan divisi..." />
          <ErrorText>{errors.alasan}</ErrorText>
        </div>
      </form>
    </Modal>
  );
}

// ── PROSES HR (setujui / tolak) ──
function ProsesModal({ data, keputusan, onClose }) {
  const [catatan, setCatatan] = useState('');
  const [error, setError]     = useState('');
  const [loading, setLoading] = useState(false);
  const setuju = keputusan === 'disetujui';
  function submit() {
    if (!setuju && !catatan.trim()) { setError('Alasan penolakan wajib diisi.'); return; }
    setLoading(true);
    router.put(`/pengajuan-training/${data.id}/proses`, { keputusan, catatan_hr: catatan || null }, {
      preserveScroll: true, onSuccess: () => onClose(), onError: err => setError(err.catatan_hr || err.keputusan || 'Gagal menyimpan.'), onFinish: () => setLoading(false),
    });
  }
  return (
    <Modal title={setuju ? 'Setujui Pengajuan' : 'Tolak Pengajuan'} icon={setuju ? <Check size={15} /> : <Ban size={15} />} width={440} onClose={onClose}
      footer={<>
        <button type="button" onClick={onClose} style={btnBatal}>Batal</button>
        <button type="button" onClick={submit} disabled={loading} style={{ ...btnUtama(setuju ? 'linear-gradient(135deg,#22C97A,#14915A)' : 'linear-gradient(135deg,#E04545,#A03030)', '#fff'), opacity: loading ? .7 : 1 }}>
          {loading ? <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> : setuju ? <Check size={14} /> : <Ban size={14} />} {setuju ? 'Ya, Setujui' : 'Ya, Tolak'}
        </button>
      </>}>
      <div style={{ padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 12 }}>
        <div style={{ fontSize: 13, color: 'var(--muted2)', lineHeight: 1.6 }}>
          <b style={{ color: 'var(--text)' }}>{data.nama_training}</b> — {data.divisi}, {data.peserta.length} peserta, rencana {fmtDate(data.tanggal_rencana)}.
        </div>
        <div>
          <label style={lbl}>{setuju ? 'Catatan HR (opsional)' : 'Alasan Penolakan *'}</label>
          <textarea style={{ ...inp, minHeight: 70, resize: 'vertical', borderColor: error ? '#E04545' : 'var(--border)' }} value={catatan} maxLength={1000}
            onChange={e => { setCatatan(e.target.value); setError(''); }} placeholder={setuju ? 'cth: Disetujui, koordinasi jadwal dengan HR' : 'cth: Kuota/biaya belum memungkinkan, ajukan ulang semester depan'} />
          <ErrorText>{error}</ErrorText>
        </div>
      </div>
    </Modal>
  );
}

// ── DETAIL ──
function DetailModal({ data, onClose }) {
  const baris = [
    ['Departemen', data.divisi],
    ['Tempat / Penyelenggara', data.penyelenggara || '—'],
    ['Tanggal Rencana', fmtDate(data.tanggal_rencana)],
    ['Estimasi Biaya', fmtRp(data.estimasi_biaya)],
    ['Diajukan', `${data.diajukan_oleh || '—'} · ${fmtDate(data.diajukan_at)}`],
    ...(data.diproses_at ? [['Diproses', `${data.diproses_oleh || '—'} · ${fmtDate(data.diproses_at)}`]] : []),
  ];
  return (
    <Modal title={data.nama_training} icon={<GraduationCap size={15} />} width={520} onClose={onClose}>
      <div style={{ padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div><StatusBadge status={data.status} /></div>
        <div>
          {baris.map(([k, v]) => (
            <div key={k} style={{ display: 'flex', gap: 12, padding: '7px 0', borderBottom: '1px solid var(--border)', fontSize: 12.5 }}>
              <div style={{ width: 160, flexShrink: 0, color: 'var(--muted)' }}>{k}</div>
              <div style={{ fontWeight: 500, minWidth: 0, wordBreak: 'break-word' }}>{v}</div>
            </div>
          ))}
        </div>
        <div>
          <div style={lbl}>Peserta ({data.peserta.length})</div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
            {data.peserta.map(p => <span key={p.id} style={{ fontSize: 11.5, padding: '3px 9px', borderRadius: 99, background: 'var(--bg3)', color: 'var(--text)' }}>{p.nama}</span>)}
          </div>
        </div>
        <div>
          <div style={lbl}>Alasan / Kebutuhan</div>
          <div style={{ fontSize: 12.5, lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{data.alasan}</div>
        </div>
        {data.catatan_hr && (
          <div style={{ padding: '10px 12px', borderRadius: 8, background: data.status === 'ditolak' ? 'rgba(224,69,69,.08)' : 'var(--bg3)' }}>
            <div style={lbl}>Catatan HR</div>
            <div style={{ fontSize: 12.5, lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{data.catatan_hr}</div>
          </div>
        )}
      </div>
    </Modal>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function PengajuanTrainingIndex({ tahun, requests = [], divisions = [], employees = [], maks = 3, can_edit = false, can_approve = false }) {
  const [form, setForm]       = useState(null);   // {data} | {data:null} untuk baru
  const [detail, setDetail]   = useState(null);
  const [proses, setProses]   = useState(null);   // {data, keputusan}
  const [hapus, setHapus]     = useState(null);
  const [selesai, setSelesai] = useState(null);
  const [status, setStatus]   = useState('');
  const [divisi, setDivisi]   = useState('');
  const [search, setSearch]   = useState('');

  const q = search.trim().toLowerCase();
  const list = requests
    .filter(r => !status || r.status === status)
    .filter(r => !divisi || r.ho_division_id === Number(divisi))
    .filter(r => !q || r.nama_training.toLowerCase().includes(q) || (r.penyelenggara || '').toLowerCase().includes(q) || r.peserta.some(p => p.nama.toLowerCase().includes(q)));
  const hitung = s => requests.filter(r => r.status === s).length;
  const tahunOpsi = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() + 1 - i);

  return (
    <AppLayout title="Pengajuan Training" subtitle="Head Office">
      {form && <FormModal data={form.data} divisions={divisions} employees={employees} maks={maks} onClose={() => setForm(null)} />}
      {detail && <DetailModal data={detail} onClose={() => setDetail(null)} />}
      {proses && <ProsesModal data={proses.data} keputusan={proses.keputusan} onClose={() => setProses(null)} />}
      <ConfirmModal open={!!hapus} onCancel={() => setHapus(null)}
        onConfirm={() => router.delete(`/pengajuan-training/${hapus.id}`, { preserveScroll: true, onFinish: () => setHapus(null) })}
        title="Batalkan Pengajuan" message={hapus ? <>Pengajuan <b style={{ color: 'var(--text)' }}>{hapus.nama_training}</b> akan dibatalkan & dihapus.</> : ''} confirmLabel="Ya, Batalkan" />
      <ConfirmModal open={!!selesai} onCancel={() => setSelesai(null)} type="warning" icon={<CheckCircle2 size={28} color="#22C97A" />}
        onConfirm={() => router.put(`/pengajuan-training/${selesai.id}/selesai`, {}, { preserveScroll: true, onFinish: () => setSelesai(null) })}
        title="Tandai Selesai" message={selesai ? <>Training <b style={{ color: 'var(--text)' }}>{selesai.nama_training}</b> sudah dilaksanakan?</> : ''} confirmLabel="Ya, Selesai" />

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ fontSize: 11.5, color: 'var(--muted)', maxWidth: 640, lineHeight: 1.5 }}>
          Departemen mengajukan kebutuhan training sesuai pekerjaan — maksimal <b>{maks} pengajuan per departemen per tahun</b> (yang ditolak tidak dihitung). Pengajuan disetujui/ditolak oleh HR.
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <select value={tahun} onChange={e => router.get('/pengajuan-training', { tahun: e.target.value }, { preserveState: false })} style={{ ...inp, width: 'auto' }}>
            {tahunOpsi.map(y => <option key={y} value={y}>{y}</option>)}
          </select>
          {can_edit && (
            <button onClick={() => setForm({ data: null })} style={{ ...btnUtama(), padding: '8px 16px', fontSize: 12 }}><Plus size={14} /> Ajukan Training</button>
          )}
        </div>
      </div>

      {/* Kuota per departemen */}
      <div style={{ ...card, padding: '12px 16px', marginBottom: 16 }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--accent)', textTransform: 'uppercase', letterSpacing: '.08em', marginBottom: 10 }}>Kuota Pengajuan {tahun}</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(190px,1fr))', gap: 8 }}>
          {divisions.filter(d => d.is_active || d.terpakai > 0).map(d => {
            const penuh = d.terpakai >= maks;
            return (
              <div key={d.id} onClick={() => setDivisi(divisi === String(d.id) ? '' : String(d.id))} title="Klik untuk filter"
                style={{ padding: '8px 10px', borderRadius: 8, cursor: 'pointer', border: `1px solid ${divisi === String(d.id) ? 'var(--accent)' : 'var(--border)'}`, background: divisi === String(d.id) ? 'rgba(232,160,32,.08)' : 'var(--bg3)' }}>
                <div style={{ fontSize: 11.5, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{d.nama}</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 5 }}>
                  <div style={{ flex: 1, height: 5, borderRadius: 99, background: 'var(--border)', overflow: 'hidden' }}>
                    <div style={{ width: `${Math.min(100, d.terpakai / maks * 100)}%`, height: '100%', background: penuh ? '#E04545' : 'var(--accent)' }} />
                  </div>
                  <span style={{ fontSize: 11, fontWeight: 700, color: penuh ? '#E04545' : 'var(--muted2)' }}>{d.terpakai}/{maks}</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div style={{ ...card, overflow: 'hidden' }}>
        <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <div style={{ position: 'relative', maxWidth: 280, flex: 1, minWidth: 180 }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari training / tempat / peserta..." style={{ ...inp, paddingLeft: 30 }} />
          </div>
          {[['', 'Semua', requests.length], ...Object.entries(STATUS_META).map(([k, m]) => [k, m.label, hitung(k)])].map(([k, label, n]) => (
            <div key={k || 'all'} onClick={() => setStatus(k)}
              style={{ padding: '6px 11px', borderRadius: 8, cursor: 'pointer', fontSize: 11.5, fontWeight: 600, whiteSpace: 'nowrap',
                border: `1px solid ${status === k ? 'var(--accent)' : 'var(--border)'}`, background: status === k ? 'rgba(232,160,32,.12)' : 'transparent', color: status === k ? 'var(--accent)' : 'var(--muted)' }}>
              {label} <span style={{ opacity: .7 }}>({n})</span>
            </div>
          ))}
          {divisi && (
            <span onClick={() => setDivisi('')} style={{ fontSize: 11, fontWeight: 600, color: 'var(--muted2)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 3, padding: '4px 9px', borderRadius: 99, background: 'var(--bg3)' }}>
              {divisions.find(d => String(d.id) === divisi)?.nama} <X size={11} />
            </span>
          )}
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
            <thead>
              <tr style={{ background: 'var(--bg3)' }}>
                <th style={th}>Training</th>
                <th style={th}>Departemen</th>
                <th style={th}>Rencana</th>
                <th style={{ ...th, textAlign: 'center' }}>Peserta</th>
                <th style={{ ...th, textAlign: 'right' }}>Estimasi Biaya</th>
                <th style={{ ...th, textAlign: 'center' }}>Status</th>
                <th style={{ ...th, textAlign: 'center' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {list.length === 0 && (
                <tr><td colSpan={7} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>
                  {requests.length === 0 ? `Belum ada pengajuan training di tahun ${tahun}.` : 'Tidak ada pengajuan yang cocok dengan filter.'}
                </td></tr>
              )}
              {list.map(r => (
                <tr key={r.id} style={{ borderTop: '1px solid var(--border)' }}>
                  <td style={td}>
                    <div style={{ fontWeight: 600, cursor: 'pointer' }} onClick={() => setDetail(r)}>{r.nama_training}</div>
                    <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{r.penyelenggara || '—'} · diajukan {r.diajukan_oleh || '—'}, {fmtDate(r.diajukan_at)}</div>
                  </td>
                  <td style={{ ...td, color: 'var(--muted2)' }}>{r.divisi}</td>
                  <td style={{ ...td, whiteSpace: 'nowrap' }}>{fmtDate(r.tanggal_rencana)}</td>
                  <td style={{ ...td, textAlign: 'center' }} title={r.peserta.map(p => p.nama).join(', ')}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, color: 'var(--muted2)' }}><Users size={12} />{r.peserta.length}</span>
                  </td>
                  <td style={{ ...td, textAlign: 'right', whiteSpace: 'nowrap' }}>{fmtRp(r.estimasi_biaya)}</td>
                  <td style={{ ...td, textAlign: 'center' }}><StatusBadge status={r.status} /></td>
                  <td style={td}>
                    <div style={{ display: 'flex', gap: 5, justifyContent: 'center', flexWrap: 'wrap' }}>
                      <button onClick={() => setDetail(r)} title="Detail" style={btnKecil('var(--muted2)', 'var(--bg3)', 'var(--border)')}><Eye size={12} /></button>
                      {can_approve && r.status === 'diajukan' && <>
                        <button onClick={() => setProses({ data: r, keputusan: 'disetujui' })} style={btnKecil('#22C97A', 'rgba(34,201,122,.1)', 'rgba(34,201,122,.3)')}><Check size={12} /> Setujui</button>
                        <button onClick={() => setProses({ data: r, keputusan: 'ditolak' })} style={btnKecil('#E04545', 'rgba(224,69,69,.08)', 'rgba(224,69,69,.25)')}><Ban size={12} /> Tolak</button>
                      </>}
                      {can_approve && r.status === 'disetujui' && (
                        <button onClick={() => setSelesai(r)} style={btnKecil('#22C97A', 'rgba(34,201,122,.1)', 'rgba(34,201,122,.3)')}><CheckCircle2 size={12} /> Selesai</button>
                      )}
                      {can_edit && r.bisa_ubah && <>
                        <button onClick={() => setForm({ data: r })} title="Ubah" style={btnKecil('var(--accent)', 'rgba(232,160,32,.12)', 'rgba(232,160,32,.25)')}><Pencil size={12} /></button>
                        <button onClick={() => setHapus(r)} title="Batalkan" style={btnKecil('#E04545', 'rgba(224,69,69,.1)', 'rgba(224,69,69,.2)')}><Trash2 size={12} /></button>
                      </>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
