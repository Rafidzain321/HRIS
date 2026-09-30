// resources/js/Pages/Attendance/Index.jsx
import React, { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  CalendarDays, ClipboardCheck, Search, TriangleAlert, Loader2, BarChart3, Save, Download,
  Fingerprint, Upload, X, Eye, CheckCircle2,
} from 'lucide-react';

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content; }

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};
const numInp = { ...inp, width: 56, textAlign: 'center', padding: '6px 4px' };

const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// ── BARIS EDITABLE (Hadir/Izin/Sakit/Alpha) ─────────────────
function AttendanceRow({ row, tahun, bulan, daysInMonth, canEdit, onSaved }) {
  const [form, setForm] = useState({ hadir: row.hadir, dinas_luar: row.dinas_luar, izin: row.izin, sakit: row.sakit, alpha: row.alpha });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [dirty, setDirty] = useState(false);

  useEffect(() => { setForm({ hadir: row.hadir, dinas_luar: row.dinas_luar, izin: row.izin, sakit: row.sakit, alpha: row.alpha }); setDirty(false); }, [row.hadir, row.dinas_luar, row.izin, row.sakit, row.alpha]);

  const total = (Number(form.hadir) || 0) + (Number(form.dinas_luar) || 0) + (Number(form.izin) || 0) + (Number(form.sakit) || 0) + (Number(form.alpha) || 0) + row.cuti;
  const over = total > daysInMonth;

  function setField(key, val) {
    setForm(f => ({ ...f, [key]: val.replace(/[^0-9]/g, '') }));
    setDirty(true);
  }

  async function save() {
    setSaving(true); setError('');
    try {
      const res = await axios.post('/kehadiran', {
        employee_id: row.employee_id, tahun, bulan,
        hadir: form.hadir || 0, dinas_luar: form.dinas_luar || 0, izin: form.izin || 0, sakit: form.sakit || 0, alpha: form.alpha || 0,
      }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      setDirty(false);
      onSaved?.();
    } catch (err) {
      setError(err.response?.data?.errors?.hadir?.[0] || 'Gagal menyimpan.');
    }
    setSaving(false);
  }

  return (
    <tr style={{ borderTop: '1px solid var(--border)', background: over ? 'rgba(224,69,69,.04)' : 'transparent' }}>
      <td style={{ padding: '9px 14px', fontWeight: 600 }}>{row.nama_lengkap}</td>
      <td style={{ padding: '9px 14px', color: 'var(--muted2)', fontSize: 12 }}>{row.jabatan}</td>
      {['hadir', 'dinas_luar', 'izin', 'sakit', 'alpha'].map(k => (
        <td key={k} style={{ padding: '6px 8px', textAlign: 'center' }}>
          <input type="text" inputMode="numeric" style={numInp} value={form[k]} disabled={!canEdit}
            onChange={e => setField(k, e.target.value)} onBlur={() => dirty && canEdit && save()} />
        </td>
      ))}
      <td style={{ padding: '9px 14px', textAlign: 'center', color: 'var(--muted)' }}>{row.cuti}</td>
      <td style={{ padding: '9px 14px', textAlign: 'center', fontWeight: 700, color: over ? '#E04545' : 'var(--text)' }}>
        {total}<span style={{ color: 'var(--muted)', fontWeight: 400 }}>/{daysInMonth}</span>
      </td>
      <td style={{ padding: '9px 14px', textAlign: 'center', width: 40 }}>
        {saving ? <Loader2 size={13} style={{ animation: 'spin .8s linear infinite', color: 'var(--muted)' }} />
          : dirty && canEdit ? <button onClick={save} title="Simpan" style={{ padding: 4, borderRadius: 6, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.1)', color: 'var(--blue)', cursor: 'pointer', display: 'inline-flex' }}><Save size={12} /></button> : null}
        {error && <div title={error} style={{ display: 'inline-flex', color: '#E04545', marginLeft: 4 }}><TriangleAlert size={13} /></div>}
      </td>
    </tr>
  );
}

// ── TAB RINGKASAN SEMESTER ───────────────────────────────────
function TabSemester({ tahun }) {
  const [semester, setSemester] = useState(new Date().getMonth() < 6 ? 1 : 2);
  const [rows, setRows] = useState(null);
  const [search, setSearch] = useState('');

  useEffect(() => {
    setRows(null);
    axios.get('/kehadiran/semester', { params: { tahun, semester } }).then(r => setRows(r.data.rows)).catch(() => setRows([]));
  }, [tahun, semester]);

  const searchLow = search.trim().toLowerCase();
  const filtered = (rows || []).filter(r => !searchLow || r.nama_lengkap.toLowerCase().includes(searchLow) || r.jabatan.toLowerCase().includes(searchLow));

  return (
    <div>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 14, flexWrap: 'wrap' }}>
        <select value={semester} onChange={e => setSemester(Number(e.target.value))} style={{ ...inp, width: 'auto' }}>
          <option value={1}>Semester 1 (Jan–Jun)</option>
          <option value={2}>Semester 2 (Jul–Des)</option>
        </select>
        <div style={{ position: 'relative' }}>
          <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
          <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30, width: 220 }} />
        </div>
      </div>

      {rows === null ? (
        <div style={{ padding: 30, textAlign: 'center', color: 'var(--muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}><Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> Memuat...</div>
      ) : (
        <div style={{ ...card, overflow: 'hidden' }}>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
              <thead>
                <tr style={{ background: 'var(--bg3)' }}>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Hadir</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Dinas Luar</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Izin</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Sakit</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Alpha</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Cuti</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>% Kehadiran</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 && <tr><td colSpan={9} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada data.</td></tr>}
                {filtered.map(r => {
                  const color = r.persentase === null ? 'var(--muted)' : r.persentase >= 95 ? '#22C97A' : r.persentase >= 85 ? '#E8A020' : '#E04545';
                  return (
                    <tr key={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
                      <td style={{ padding: '9px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
                      <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.hadir}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.dinas_luar}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.izin}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.sakit}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.alpha}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center', color: 'var(--muted)' }}>{r.cuti}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center', fontWeight: 700, color }}>
                        {r.persentase === null ? <span style={{ fontSize: 11, fontWeight: 400, color: 'var(--muted)' }}>Belum ada data</span> : `${r.persentase}%`}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
      <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 10 }}>% Kehadiran = (Hadir + Dinas Luar) ÷ (Hadir + Dinas Luar + Izin + Sakit + Alpha) × 100. Cuti tidak ikut dihitung (hak karyawan).</div>
    </div>
  );
}

// ── ABSENSI MESIN (import fingerprint → rekap bulanan per karyawan) ──
const TTD_DEFAULT = { hr: 'M.Ali Nst', gm: 'Nedriyanto', direktur: 'Rahmat Sjukri' };
const btn = (color, bg) => ({ padding: '8px 14px', borderRadius: 8, border: `1px solid ${color}40`, background: bg, color, fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6, textDecoration: 'none' });
const modalWrap = { position: 'fixed', inset: 0, zIndex: 400, background: 'rgba(0,0,0,.6)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 12 };
const modalBox = { background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 14, boxShadow: '0 24px 80px rgba(0,0,0,.5)', display: 'flex', flexDirection: 'column', maxHeight: '92vh' };

function ImportMesinModal({ onClose, onDone }) {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState(null);

  async function submit() {
    if (!file) { setError('Pilih file export mesin dulu.'); return; }
    setLoading(true); setError('');
    const fd = new FormData(); fd.append('file', file);
    try {
      const r = await axios.post('/kehadiran/mesin/import', fd, { headers: { 'X-CSRF-TOKEN': csrf() } });
      setResult(r.data);
    } catch (e) {
      const d = e.response?.data;
      setError(d?.message && !d?.errors ? d.message : Object.values(d?.errors || {}).flat()[0] || 'Import gagal.');
    }
    setLoading(false);
  }

  return (
    <div style={modalWrap}>
      <div style={{ ...modalBox, width: 'min(480px, 100%)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 18px', borderBottom: '1px solid var(--border)' }}>
          <div style={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><Upload size={15} /> Import Data Mesin Absensi</div>
          <span onClick={() => result ? onDone(result) : onClose()} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></span>
        </div>
        <div style={{ padding: 18, overflowY: 'auto' }}>
          {result ? (
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#22C97A', fontWeight: 700, marginBottom: 10 }}><CheckCircle2 size={18} /> Import berhasil</div>
              <div style={{ fontSize: 12.5, lineHeight: 1.8 }}>
                {result.lokasi && <>Lokasi (dari nama file): <b>{result.lokasi}</b><br /></>}
                Baris data: <b>{result.baris}</b>{result.dilewati > 0 && <> (dilewati {result.dilewati})</>} · Periode: <b>{result.periode.join(', ') || '-'}</b><br />
                Karyawan di mesin: <b>{result.karyawan}</b> · otomatis tersinkron dengan data karyawan: <b style={{ color: '#22C97A' }}>{result.tersinkron}</b>
              </div>
              {result.belum_cocok.length > 0 && (
                <div style={{ fontSize: 11.5, marginTop: 10, padding: '8px 12px', borderRadius: 8, background: 'rgba(232,160,32,.08)', border: '1px solid rgba(232,160,32,.3)' }}>
                  <b>{result.belum_cocok.length} nama belum cocok</b> (tidak ada / ada lebih dari 1 karyawan yang mirip): {result.belum_cocok.join(', ')}.
                  <div style={{ color: 'var(--muted)', marginTop: 4 }}>Pilih manual di kolom "Data Karyawan" supaya jabatan, atasan & cuti ikut terbaca.</div>
                </div>
              )}
            </div>
          ) : (
            <>
              <div style={{ fontSize: 11.5, color: 'var(--muted)', marginBottom: 12, padding: '8px 12px', background: 'var(--bg3)', borderRadius: 8, lineHeight: 1.5 }}>
                Upload file export dari mesin fingerprint (.xls/.xlsx) — sheet berisi kolom No. ID, Nama, Tanggal, Jam Kerja, Scan Masuk, Scan Pulang (sheet dicari otomatis). Nama di mesin langsung dicocokkan ke data karyawan. Import ulang aman: keterangan yang sudah diisi manual tidak hilang.
              </div>
              <label style={{ fontSize: 11, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>File export mesin *</label>
              <input type="file" accept=".xls,.xlsx" onChange={e => setFile(e.target.files[0] || null)} style={{ ...inp, padding: 7 }} />
              {error && <div style={{ display: 'flex', gap: 5, alignItems: 'center', color: '#E04545', fontSize: 11.5, marginTop: 10 }}><TriangleAlert size={13} />{error}</div>}
            </>
          )}
        </div>
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, padding: '12px 18px', borderTop: '1px solid var(--border)' }}>
          {result
            ? <button onClick={() => onDone(result)} style={btn('#0C0F14', 'linear-gradient(135deg,#E8A020,#A06010)')}>Selesai</button>
            : <>
                <button onClick={onClose} style={btn('var(--muted2)', 'var(--bg3)')}>Batal</button>
                <button onClick={submit} disabled={loading} style={{ ...btn('#0C0F14', 'linear-gradient(135deg,#E8A020,#A06010)'), opacity: loading ? .7 : 1 }}>
                  {loading ? <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> : <Upload size={14} />} Import
                </button>
              </>}
        </div>
      </div>
    </div>
  );
}

function DetailMesinModal({ user, tahun, bulan, kategoriList, canEdit, onClose }) {
  const [days, setDays] = useState(null);
  const [saving, setSaving] = useState('');
  const label = Object.fromEntries(kategoriList.map(k => [k.key, k.label]));

  function load() { axios.get(`/kehadiran/mesin/${user.id}/detail`, { params: { tahun, bulan } }).then(r => setDays(r.data.days)); }
  useEffect(load, [user.id, tahun, bulan]);

  async function save(d, kategori, keterangan) {
    setSaving(d.tanggal);
    await axios.put(`/kehadiran/mesin/${user.id}/hari`, { tanggal: d.tanggal, kategori: kategori || null, keterangan: keterangan || null }, { headers: { 'X-CSRF-TOKEN': csrf() } }).catch(() => {});
    setSaving(''); load();
  }

  const th = { padding: '8px 6px', fontSize: 10.5, color: 'var(--muted)', textAlign: 'center', position: 'sticky', top: 0, background: 'var(--bg3)' };
  const td = { padding: '5px 6px', textAlign: 'center', borderTop: '1px solid var(--border)' };
  return (
    <div style={modalWrap}>
      <div style={{ ...modalBox, width: 'min(1000px, 100%)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 18px', borderBottom: '1px solid var(--border)' }}>
          <div>
            <div style={{ fontWeight: 700 }}>{user.employee_nama || user.nama_mesin} <span style={{ fontWeight: 400, color: 'var(--muted)', fontSize: 12 }}>· {MONTH_NAMES[bulan - 1]} {tahun}</span></div>
            {!user.employee_nama
              ? <div style={{ fontSize: 11, color: '#E8A020' }}>Belum dicocokkan dengan data karyawan</div>
              : user.employee_nama.toLowerCase() !== user.nama_mesin.toLowerCase() && <div style={{ fontSize: 11, color: 'var(--muted)' }}>Nama di mesin: {user.nama_mesin}</div>}
          </div>
          <span onClick={onClose} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></span>
        </div>
        <div style={{ overflow: 'auto', flex: 1 }}>
          {!days ? <div style={{ padding: 30, textAlign: 'center', color: 'var(--muted)' }}><Loader2 size={18} style={{ animation: 'spin .8s linear infinite' }} /></div> : (
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
              <thead><tr>
                <th style={th}>Tgl</th><th style={{ ...th, textAlign: 'left' }}>Hari</th><th style={th}>Masuk</th><th style={th}>Pulang</th>
                <th style={th}>Jumlah Jam</th><th style={th}>Kurang</th><th style={th}>Lebih</th>
                <th style={{ ...th, textAlign: 'left' }}>Status / Keterangan</th><th style={{ ...th, textAlign: 'left' }}>Catatan (kolom Keterangan)</th>
              </tr></thead>
              <tbody>
                {days.map(d => (
                  <tr key={d.tanggal} style={{ background: d.pink ? 'rgba(218,150,148,.18)' : d.tidak_lengkap ? 'rgba(224,69,69,.05)' : undefined }}>
                    <td style={td}>{d.tgl}</td>
                    <td style={{ ...td, textAlign: 'left' }}>{d.hari}</td>
                    <td style={{ ...td, color: !d.masuk && d.tidak_lengkap ? '#E04545' : undefined }}>{d.masuk || '—'}</td>
                    <td style={{ ...td, color: !d.pulang && d.tidak_lengkap ? '#E04545' : undefined }}>{d.pulang || '—'}</td>
                    <td style={td}>{d.jumlah ?? '—'}</td>
                    <td style={{ ...td, color: d.kurang && d.kurang !== '00:00' ? '#E04545' : undefined }}>{d.kurang ?? '—'}</td>
                    <td style={{ ...td, color: d.lebih && d.lebih !== '00:00' ? '#22C97A' : undefined }}>{d.lebih ?? '—'}</td>
                    <td style={{ ...td, textAlign: 'left' }}>
                      {canEdit ? (
                        <select value={d.kategori_manual || ''} disabled={saving === d.tanggal}
                          onChange={e => save(d, e.target.value, d.keterangan_manual)} style={{ ...inp, padding: '5px 8px', fontSize: 11.5, width: 190 }}>
                          <option value="">Otomatis{d.kategori && d.kategori_sumber !== 'manual' ? `: ${label[d.kategori]}` : d.pink ? ': Libur' : ''}</option>
                          {kategoriList.map(k => <option key={k.key} value={k.key}>{k.label}</option>)}
                        </select>
                      ) : <span>{d.kategori ? label[d.kategori] : d.pink ? 'Libur' : '—'}</span>}
                    </td>
                    <td style={{ ...td, textAlign: 'left' }}>
                      {canEdit ? (
                        <input defaultValue={d.keterangan_manual || ''} key={`${d.tanggal}-${d.keterangan_manual}`} placeholder={d.keterangan || ''}
                          onBlur={e => { if ((e.target.value || '') !== (d.keterangan_manual || '')) save(d, d.kategori_manual, e.target.value); }}
                          style={{ ...inp, padding: '5px 8px', fontSize: 11.5, minWidth: 180 }} />
                      ) : <span>{d.keterangan || '—'}</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
        <div style={{ padding: '10px 18px', borderTop: '1px solid var(--border)', fontSize: 10.5, color: 'var(--muted)', lineHeight: 1.6 }}>
          "Otomatis" = diambil dari hari libur, data Cuti Tahunan, kolom Pengecualian mesin, atau Alfa kalau hari kerja tanpa scan. Pilih status lain untuk menimpa (mis. Sakit, Dinas Luar, "Hadir" untuk yang masuk tapi lupa scan). Catatan tampil di kolom KETERANGAN Excel.
        </div>
      </div>
    </div>
  );
}

function TabMesin({ tahunAwal, bulanAwal, canEdit, yearOptions }) {
  const [tahun, setTahun] = useState(tahunAwal);
  const [bulan, setBulan] = useState(bulanAwal);
  const [lokasi, setLokasi] = useState('');
  const [data, setData] = useState(null);
  const [showImport, setShowImport] = useState(false);
  const [detail, setDetail] = useState(null);
  const [ttd, setTtd] = useState(() => { try { return { ...TTD_DEFAULT, ...JSON.parse(localStorage.getItem('rekap-absensi-ttd') || '{}') }; } catch { return TTD_DEFAULT; } });

  function load() {
    axios.get('/kehadiran/mesin', { params: { tahun, bulan, lokasi: lokasi || undefined } })
      .then(r => setData(r.data))
      .catch(() => setData({ users: [], lokasi_list: [], employees: [], kategori: [] }));
  }
  useEffect(load, [tahun, bulan, lokasi]);

  function setTtdField(k, v) {
    const next = { ...ttd, [k]: v }; setTtd(next);
    try { localStorage.setItem('rekap-absensi-ttd', JSON.stringify(next)); } catch {}
  }
  async function mapEmployee(u, employeeId) {
    await axios.put(`/kehadiran/mesin/${u.id}/mapping`, { employee_id: employeeId || null }, { headers: { 'X-CSRF-TOKEN': csrf() } }).catch(() => {});
    load();
  }

  const users = data?.users || [];
  const exportUrl = `/kehadiran/mesin/export?${new URLSearchParams({ tahun, bulan, ...(lokasi ? { lokasi } : {}), ...ttd }).toString()}`;
  const th = { padding: '10px 12px', fontSize: 11, color: 'var(--muted)', textAlign: 'center' };

  return (
    <div>
      {showImport && <ImportMesinModal onClose={() => setShowImport(false)}
        onDone={res => {
          setShowImport(false);
          const [y, m] = (res.periode?.[0] || '').split('-').map(Number);
          if (y && m) { setTahun(y); setBulan(m); }
          setLokasi(''); load();
        }} />}
      {detail && <DetailMesinModal user={detail} tahun={tahun} bulan={bulan} kategoriList={data?.kategori || []} canEdit={canEdit} onClose={() => { setDetail(null); load(); }} />}

      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12, flexWrap: 'wrap' }}>
        <select value={bulan} onChange={e => setBulan(Number(e.target.value))} style={{ ...inp, width: 'auto' }}>
          {MONTH_NAMES.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
        </select>
        <select value={tahun} onChange={e => setTahun(Number(e.target.value))} style={{ ...inp, width: 'auto' }}>
          {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
        </select>
        {(data?.lokasi_list || []).length > 1 && (
          <select value={lokasi} onChange={e => setLokasi(e.target.value)} style={{ ...inp, width: 'auto' }}>
            <option value="">Semua lokasi</option>
            {data.lokasi_list.map(l => <option key={l} value={l}>{l}</option>)}
          </select>
        )}
        <div style={{ flex: 1 }} />
        {canEdit && <button onClick={() => setShowImport(true)} style={btn('var(--green)', 'rgba(34,201,122,.08)')}><Upload size={14} /> Import Data Mesin</button>}
        {users.length > 0
          ? <a href={exportUrl} style={btn('var(--blue)', 'rgba(58,143,224,.08)')}><Download size={14} /> Download Rekap Absensi Excel</a>
          : <span style={{ ...btn('var(--muted)', 'var(--bg3)'), cursor: 'not-allowed', opacity: .6 }}><Download size={14} /> Download Rekap Absensi Excel</span>}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14, flexWrap: 'wrap', fontSize: 11.5, color: 'var(--muted)' }}>
        <span>Penanda tangan di Excel:</span>
        {[['hr', 'HR, Legal Mngr'], ['gm', 'GM'], ['direktur', 'Direktur']].map(([k, l]) => (
          <label key={k} style={{ display: 'flex', alignItems: 'center', gap: 5 }}>{l}
            <input value={ttd[k]} onChange={e => setTtdField(k, e.target.value)} style={{ ...inp, width: 140, padding: '5px 8px' }} />
          </label>
        ))}
        <span>· Atasan langsung otomatis dari Struktur Organisasi.</span>
      </div>

      <div style={{ ...card, overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
            <thead><tr style={{ background: 'var(--bg3)' }}>
              <th style={th}>No. ID</th><th style={{ ...th, textAlign: 'left' }}>Nama di Mesin</th><th style={{ ...th, textAlign: 'left' }}>Lokasi</th><th style={{ ...th, textAlign: 'left' }}>Data Karyawan</th>
              <th style={th}>Scan Lengkap</th><th style={th}>Scan Tdk Lengkap</th><th style={th}>Alfa</th><th style={th}>Sakit/Cuti/Izin/DL</th><th style={th} />
            </tr></thead>
            <tbody>
              {!data && <tr><td colSpan={9} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}><Loader2 size={16} style={{ animation: 'spin .8s linear infinite' }} /></td></tr>}
              {data && users.length === 0 && (
                <tr><td colSpan={9} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>
                  Belum ada data mesin untuk bulan {MONTH_NAMES[bulan - 1]} {tahun}.{canEdit && ' Klik "Import Data Mesin".'}
                </td></tr>
              )}
              {users.map(u => (
                <tr key={u.id} style={{ borderTop: '1px solid var(--border)' }}>
                  <td style={{ padding: '8px 12px', textAlign: 'center' }}>{u.no_id}</td>
                  <td style={{ padding: '8px 12px', fontWeight: 600 }}>{u.nama_mesin}</td>
                  <td style={{ padding: '8px 12px', fontSize: 11.5, color: 'var(--muted2)' }}>{u.lokasi}</td>
                  <td style={{ padding: '8px 12px' }}>
                    {canEdit ? (
                      <select value={u.employee_id || ''} onChange={e => mapEmployee(u, e.target.value)}
                        style={{ ...inp, padding: '5px 8px', width: 220, borderColor: u.employee_id ? 'var(--border)' : '#E8A020' }}>
                        <option value="">— Belum dicocokkan —</option>
                        {data.employees.map(e => <option key={e.id} value={e.id}>{e.nama_lengkap}</option>)}
                      </select>
                    ) : (u.employee_nama || <span style={{ color: 'var(--muted)' }}>Belum dicocokkan</span>)}
                  </td>
                  <td style={{ padding: '8px 12px', textAlign: 'center' }}>{u.lengkap}</td>
                  <td style={{ padding: '8px 12px', textAlign: 'center', color: u.tidak_lengkap ? '#E04545' : undefined, fontWeight: u.tidak_lengkap ? 700 : 400 }}>{u.tidak_lengkap}</td>
                  <td style={{ padding: '8px 12px', textAlign: 'center', color: u.alfa ? '#E04545' : undefined, fontWeight: u.alfa ? 700 : 400 }}>{u.alfa}</td>
                  <td style={{ padding: '8px 12px', textAlign: 'center' }}>{u.keterangan}</td>
                  <td style={{ padding: '8px 12px', textAlign: 'right' }}>
                    <button onClick={() => setDetail(u)} style={{ ...btn('var(--accent)', 'rgba(232,160,32,.1)'), padding: '5px 10px', fontSize: 11.5, display: 'inline-flex' }}><Eye size={12} /> {canEdit ? 'Detail / Keterangan' : 'Detail'}</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 10, lineHeight: 1.6 }}>
        Aturan hitung: Senin–Jumat normal 07:00 + istirahat 02:00, Sabtu normal 04:00 tanpa istirahat, Minggu & hari libur kosong/pink (hari libur ditandai lewat kalender di tab Cuti Tahunan).
        Jumlah jam = (pulang − masuk) − istirahat; selisih dengan normal masuk ke kolom Kekurangan/Kelebihan. Hari kerja tanpa scan otomatis Alfa sampai diberi keterangan.
      </div>
    </div>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function AttendanceIndex({ employees = [], tahun, bulan, days_in_month }) {
  const { auth } = usePage().props;
  const canEdit = (auth?.user?.permissions || []).includes('edit-kehadiran');
  const canViewCuti = (auth?.user?.permissions || []).includes('view-cuti');
  const [activeTab, setActiveTab] = useState('input');
  const [search, setSearch] = useState('');

  function changePeriod(t, b) { router.get('/kehadiran', { tahun: t, bulan: b }, { preserveState: false }); }
  function reload() { router.reload({ only: ['employees'] }); }

  const now = new Date();
  const yearOptions = [];
  for (let y = now.getFullYear() - 2; y <= now.getFullYear() + 1; y++) yearOptions.push(y);

  const searchLow = search.trim().toLowerCase();
  const filteredEmployees = employees.filter(e => !searchLow || e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow));

  const tabs = [
    { key: 'input', label: 'Input Bulanan', icon: ClipboardCheck },
    { key: 'semester', label: 'Ringkasan Semester', icon: BarChart3 },
    { key: 'mesin', label: 'Absensi Mesin', icon: Fingerprint },
  ];

  return (
    <AppLayout title="Cuti & Kehadiran" subtitle="Head Office">
      <div style={{ display: 'flex', gap: 4, marginBottom: 12 }}>
        <div style={{ padding: '7px 4px', marginRight: 18, fontSize: 12.5, fontWeight: 600, color: 'var(--accent)', borderBottom: '2px solid var(--accent)', display: 'flex', alignItems: 'center', gap: 6, cursor: 'default' }}>
          <ClipboardCheck size={13} /> Kehadiran
        </div>
        {canViewCuti && (
          <div onClick={() => router.visit('/cuti')} style={{ padding: '7px 4px', marginRight: 18, cursor: 'pointer', fontSize: 12.5, fontWeight: 600, color: 'var(--muted)', borderBottom: '2px solid transparent', display: 'flex', alignItems: 'center', gap: 6 }}>
            <CalendarDays size={13} /> Cuti Tahunan
          </div>
        )}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ display: 'flex', gap: 4 }}>
          {tabs.map(t => (
            <div key={t.key} onClick={() => setActiveTab(t.key)} style={{
              padding: '9px 18px', borderRadius: 9, cursor: 'pointer', fontSize: 13, fontWeight: 600,
              background: activeTab === t.key ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--card)',
              color: activeTab === t.key ? '#0C0F14' : 'var(--muted)',
              border: `1px solid ${activeTab === t.key ? 'transparent' : 'var(--border)'}`,
              display: 'flex', alignItems: 'center', gap: 8,
            }}>
              <t.icon size={14} /> {t.label}
            </div>
          ))}
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>{employees.length} karyawan HO</span>
          <a href={`/cuti/export?tahun=${tahun}`} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.08)', color: 'var(--blue)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6, textDecoration: 'none' }}><Download size={14} /> Export Excel</a>
        </div>
      </div>

      {activeTab === 'input' && (
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
            <select value={bulan} onChange={e => changePeriod(tahun, Number(e.target.value))} style={{ ...inp, width: 'auto' }}>
              {MONTH_NAMES.map((m, i) => <option key={i} value={i + 1}>{m}</option>)}
            </select>
            <select value={tahun} onChange={e => changePeriod(Number(e.target.value), bulan)} style={{ ...inp, width: 'auto' }}>
              {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
            </select>
            <div style={{ position: 'relative' }}>
              <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
              <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30, width: 220 }} />
            </div>
            {!canEdit && <span style={{ fontSize: 11, color: 'var(--muted)' }}>(mode lihat saja)</span>}
          </div>

          <div style={{ ...card, overflow: 'hidden' }}>
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
                <thead>
                  <tr style={{ background: 'var(--bg3)' }}>
                    <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
                    <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
                    <th style={{ textAlign: 'center', padding: '10px 8px', fontSize: 11, color: 'var(--muted)' }}>Hadir</th>
                    <th style={{ textAlign: 'center', padding: '10px 8px', fontSize: 11, color: 'var(--muted)' }}>Dinas Luar</th>
                    <th style={{ textAlign: 'center', padding: '10px 8px', fontSize: 11, color: 'var(--muted)' }}>Izin</th>
                    <th style={{ textAlign: 'center', padding: '10px 8px', fontSize: 11, color: 'var(--muted)' }}>Sakit</th>
                    <th style={{ textAlign: 'center', padding: '10px 8px', fontSize: 11, color: 'var(--muted)' }}>Alpha</th>
                    <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Cuti</th>
                    <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Total</th>
                    <th style={{ padding: '10px 14px' }} />
                  </tr>
                </thead>
                <tbody>
                  {filteredEmployees.length === 0 && (
                    <tr><td colSpan={10} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada karyawan.</td></tr>
                  )}
                  {filteredEmployees.map(row => (
                    <AttendanceRow key={row.employee_id} row={row} tahun={tahun} bulan={bulan} daysInMonth={days_in_month} canEdit={canEdit} onSaved={reload} />
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 10 }}>Kolom Cuti otomatis diambil dari Cuti Tahunan — tidak diketik manual. Dinas Luar dihitung sebagai Masuk. Total (Hadir+Dinas Luar+Izin+Sakit+Alpha+Cuti) tidak boleh melebihi jumlah hari di bulan ini ({days_in_month} hari).</div>
        </div>
      )}

      {activeTab === 'semester' && <TabSemester tahun={tahun} />}
      {activeTab === 'mesin' && <TabMesin tahunAwal={tahun} bulanAwal={bulan} canEdit={canEdit} yearOptions={yearOptions} />}
    </AppLayout>
  );
}
