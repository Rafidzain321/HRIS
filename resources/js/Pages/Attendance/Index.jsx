// resources/js/Pages/Attendance/Index.jsx
import React, { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  CalendarDays, ClipboardCheck, Search, TriangleAlert, Loader2, BarChart3, Save,
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
  const [form, setForm] = useState({ hadir: row.hadir, izin: row.izin, sakit: row.sakit, alpha: row.alpha });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [dirty, setDirty] = useState(false);

  useEffect(() => { setForm({ hadir: row.hadir, izin: row.izin, sakit: row.sakit, alpha: row.alpha }); setDirty(false); }, [row.hadir, row.izin, row.sakit, row.alpha]);

  const total = (Number(form.hadir) || 0) + (Number(form.izin) || 0) + (Number(form.sakit) || 0) + (Number(form.alpha) || 0) + row.cuti;
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
        hadir: form.hadir || 0, izin: form.izin || 0, sakit: form.sakit || 0, alpha: form.alpha || 0,
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
      {['hadir', 'izin', 'sakit', 'alpha'].map(k => (
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
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Izin</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Sakit</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Alpha</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Cuti</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>% Kehadiran</th>
                </tr>
              </thead>
              <tbody>
                {filtered.length === 0 && <tr><td colSpan={8} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada data.</td></tr>}
                {filtered.map(r => {
                  const color = r.persentase === null ? 'var(--muted)' : r.persentase >= 95 ? '#22C97A' : r.persentase >= 85 ? '#E8A020' : '#E04545';
                  return (
                    <tr key={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
                      <td style={{ padding: '9px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
                      <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.hadir}</td>
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
      <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 10 }}>% Kehadiran = Hadir ÷ (Hadir + Izin + Sakit + Alpha) × 100. Cuti tidak ikut dihitung (hak karyawan).</div>
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
  ];

  return (
    <AppLayout title="Cuti & Kehadiran" subtitle="Head Office">
      <div style={{ display: 'flex', gap: 4, marginBottom: 12 }}>
        {canViewCuti && (
          <div onClick={() => router.visit('/cuti')} style={{ padding: '7px 4px', marginRight: 18, cursor: 'pointer', fontSize: 12.5, fontWeight: 600, color: 'var(--muted)', borderBottom: '2px solid transparent', display: 'flex', alignItems: 'center', gap: 6 }}>
            <CalendarDays size={13} /> Cuti Tahunan
          </div>
        )}
        <div style={{ padding: '7px 4px', marginRight: 18, fontSize: 12.5, fontWeight: 600, color: 'var(--accent)', borderBottom: '2px solid var(--accent)', display: 'flex', alignItems: 'center', gap: 6, cursor: 'default' }}>
          <ClipboardCheck size={13} /> Kehadiran
        </div>
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
        <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>{employees.length} karyawan HO</span>
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
                    <tr><td colSpan={9} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada karyawan.</td></tr>
                  )}
                  {filteredEmployees.map(row => (
                    <AttendanceRow key={row.employee_id} row={row} tahun={tahun} bulan={bulan} daysInMonth={days_in_month} canEdit={canEdit} onSaved={reload} />
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 10 }}>Kolom Cuti otomatis diambil dari Cuti Tahunan — tidak diketik manual. Total (Hadir+Izin+Sakit+Alpha+Cuti) tidak boleh melebihi jumlah hari di bulan ini ({days_in_month} hari).</div>
        </div>
      )}

      {activeTab === 'semester' && <TabSemester tahun={tahun} />}
    </AppLayout>
  );
}
