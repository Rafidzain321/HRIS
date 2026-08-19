// resources/js/Pages/Kpi/Index.jsx
import React, { useState, useEffect, useRef } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  Target, Plus, Trash2, Pencil, Check, X, TriangleAlert, Loader2,
  Users, ClipboardList, BarChart3, Save, Search,
} from 'lucide-react';

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content; }

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

function skorColor(skor) {
  if (skor === null || skor === undefined) return 'var(--muted)';
  if (skor >= 85) return '#22C97A';
  if (skor >= 70) return 'var(--accent)';
  return '#E04545';
}

// ── TAB 1: ATUR INDIKATOR ──────────────────────────────────
function TabIndikator({ employees, indicators, isViewer, onRefresh }) {
  const [selectedId, setSelectedId] = useState(employees[0]?.id ?? null);
  const [search, setSearch] = useState('');
  const [namaBaru, setNamaBaru] = useState('');
  const [bobotBaru, setBobotBaru] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null); // {id, nama_indikator, bobot}
  const [confirmDelete, setConfirmDelete] = useState(null);

  const searchLow = search.trim().toLowerCase();
  const filteredEmployees = searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : employees;

  const selected = employees.find(e => e.id === selectedId);
  const myIndicators = indicators.filter(i => i.employee_id === selectedId);
  const totalBobot = myIndicators.reduce((s, i) => s + Number(i.bobot), 0);

  async function submitBaru(e) {
    e.preventDefault();
    if (!namaBaru || !bobotBaru) return;
    setSaving(true); setError('');
    try {
      await axios.post('/kpi/indicators', { employee_id: selectedId, nama_indikator: namaBaru, bobot: bobotBaru }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      setNamaBaru(''); setBobotBaru('');
      onRefresh();
    } catch (err) { setError(err.response?.data?.message || 'Gagal menambah indikator.'); }
    setSaving(false);
  }

  async function submitEdit(e) {
    e.preventDefault();
    setSaving(true); setError('');
    try {
      await axios.put(`/kpi/indicators/${editing.id}`, { nama_indikator: editing.nama_indikator, bobot: editing.bobot }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      setEditing(null);
      onRefresh();
    } catch (err) { setError(err.response?.data?.message || 'Gagal menyimpan perubahan.'); }
    setSaving(false);
  }

  async function doDelete(id) {
    await axios.delete(`/kpi/indicators/${id}`, { headers: { 'X-CSRF-TOKEN': csrf() } });
    onRefresh();
  }

  return (
    <div style={{ display: 'grid', gridTemplateColumns: '260px 1fr', gap: 16 }}>
      <div style={{ ...card, padding: 12 }}>
        <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '.06em' }}>Karyawan HO</div>
        <div style={{ position: 'relative', marginBottom: 10 }}>
          <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
          <input type="text" value={search} onChange={e => setSearch(e.target.value)}
            placeholder="Cari nama / jabatan..."
            style={{ ...inp, paddingLeft: 30 }} />
          {search && (
            <span onClick={() => setSearch('')} style={{ position: 'absolute', right: 9, top: '50%', transform: 'translateY(-50%)', cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={13} /></span>
          )}
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4, maxHeight: 470, overflowY: 'auto' }}>
          {filteredEmployees.length === 0 && (
            <div style={{ padding: '12px 8px', fontSize: 11.5, color: 'var(--muted)', textAlign: 'center' }}>Tidak ada karyawan yang cocok.</div>
          )}
          {filteredEmployees.map(e => {
            const bobot = indicators.filter(i => i.employee_id === e.id).reduce((s, i) => s + Number(i.bobot), 0);
            const active = e.id === selectedId;
            return (
              <div key={e.id} onClick={() => setSelectedId(e.id)}
                style={{
                  padding: '8px 10px', borderRadius: 8, cursor: 'pointer',
                  background: active ? 'rgba(232,160,32,.12)' : 'transparent',
                  border: `1px solid ${active ? 'var(--accent)' : 'transparent'}`,
                }}>
                <div style={{ fontSize: 12.5, fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)' }}>{e.nama_lengkap}</div>
                <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 2, display: 'flex', justifyContent: 'space-between' }}>
                  <span>{e.jabatan}</span>
                  <span style={{ color: bobot === 100 ? '#22C97A' : bobot === 0 ? 'var(--muted)' : '#E04545' }}>{bobot}%</span>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div style={{ ...card, padding: 18 }}>
        {!selected ? (
          <div style={{ color: 'var(--muted)', fontSize: 12.5 }}>Pilih karyawan di sebelah kiri.</div>
        ) : (
          <>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
              <div>
                <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700 }}>{selected.nama_lengkap}</div>
                <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>{selected.jabatan}</div>
              </div>
              <div style={{
                padding: '5px 12px', borderRadius: 99, fontSize: 12, fontWeight: 700,
                background: totalBobot === 100 ? 'rgba(34,201,122,.12)' : 'rgba(224,69,69,.1)',
                color: totalBobot === 100 ? '#22C97A' : '#E04545',
              }}>
                Total Bobot: {totalBobot}%
              </div>
            </div>

            {totalBobot !== 100 && myIndicators.length > 0 && (
              <div style={{ marginBottom: 12, padding: '8px 12px', borderRadius: 8, background: 'rgba(232,160,32,.08)', border: '1px solid rgba(232,160,32,.2)', fontSize: 11.5, color: 'var(--muted2)', display: 'flex', alignItems: 'center', gap: 6 }}>
                <TriangleAlert size={13} /> Total bobot harus tepat 100% supaya skor akhir bisa dihitung. Sekarang: {totalBobot}%.
              </div>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 16 }}>
              {myIndicators.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>Belum ada indikator untuk karyawan ini.</div>}
              {myIndicators.map(i => (
                <div key={i.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '9px 12px', borderRadius: 8, background: 'var(--bg3)', border: '1px solid var(--border)' }}>
                  {editing?.id === i.id ? (
                    <form onSubmit={submitEdit} style={{ display: 'flex', gap: 8, flex: 1, alignItems: 'center' }}>
                      <input style={inp} value={editing.nama_indikator} onChange={e => setEditing(p => ({ ...p, nama_indikator: e.target.value }))} />
                      <input style={{ ...inp, width: 80 }} type="number" step="0.01" value={editing.bobot} onChange={e => setEditing(p => ({ ...p, bobot: e.target.value }))} />
                      <button type="submit" disabled={saving} style={{ padding: '6px 10px', borderRadius: 6, border: 'none', background: '#22C97A', color: '#fff', cursor: 'pointer', display: 'flex' }}><Check size={13} /></button>
                      <button type="button" onClick={() => setEditing(null)} style={{ padding: '6px 10px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--muted2)', cursor: 'pointer', display: 'flex' }}><X size={13} /></button>
                    </form>
                  ) : (
                    <>
                      <div style={{ flex: 1, fontSize: 12.5 }}>{i.nama_indikator}</div>
                      <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--accent)', width: 50, textAlign: 'right' }}>{i.bobot}%</div>
                      {!isViewer && (
                        <div style={{ display: 'flex', gap: 5 }}>
                          <button onClick={() => setEditing({ id: i.id, nama_indikator: i.nama_indikator, bobot: i.bobot })} style={{ padding: '4px 8px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--muted2)', cursor: 'pointer', display: 'flex' }}><Pencil size={12} /></button>
                          <button onClick={() => setConfirmDelete(i)} style={{ padding: '4px 8px', borderRadius: 6, border: '1px solid rgba(224,69,69,.25)', background: 'rgba(224,69,69,.08)', color: '#E04545', cursor: 'pointer', display: 'flex' }}><Trash2 size={12} /></button>
                        </div>
                      )}
                    </>
                  )}
                </div>
              ))}
            </div>

            {!isViewer && (
              <form onSubmit={submitBaru} style={{ display: 'flex', gap: 8, alignItems: 'flex-start', paddingTop: 12, borderTop: '1px solid var(--border)' }}>
                <input style={inp} placeholder="Nama indikator baru..." value={namaBaru} onChange={e => setNamaBaru(e.target.value)} />
                <input style={{ ...inp, width: 90 }} type="number" step="0.01" min="0.01" max="100" placeholder="Bobot %" value={bobotBaru} onChange={e => setBobotBaru(e.target.value)} />
                <button type="submit" disabled={saving} style={{ padding: '8px 16px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontWeight: 700, fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap' }}>
                  {saving ? <Loader2 size={13} style={{ animation: 'spin .8s linear infinite' }} /> : <Plus size={13} />} Tambah
                </button>
              </form>
            )}
            {error && <div style={{ marginTop: 8, fontSize: 11.5, color: '#E04545' }}>{error}</div>}
          </>
        )}
      </div>

      <ConfirmModal
        open={!!confirmDelete}
        title="Hapus Indikator"
        message={confirmDelete ? `Hapus indikator "${confirmDelete.nama_indikator}"? Kalau indikator ini sudah pernah dinilai, riwayat skornya tetap disimpan (cuma disembunyikan dari daftar aktif).` : ''}
        confirmLabel="Hapus"
        onConfirm={() => { doDelete(confirmDelete.id); setConfirmDelete(null); }}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

// ── TAB 2: INPUT SKOR ──────────────────────────────────────
function ScoreInput({ indicatorId, tahun, semester, initial, initialCatatan, isViewer }) {
  const [skor, setSkor] = useState(initial ?? '');
  const [catatan, setCatatan] = useState(initialCatatan ?? '');
  const [saved, setSaved] = useState(true);
  const timer = useRef(null);

  function schedule(nextSkor, nextCatatan) {
    setSaved(false);
    clearTimeout(timer.current);
    timer.current = setTimeout(async () => {
      if (nextSkor === '' || nextSkor === null) { setSaved(true); return; }
      try {
        await axios.post('/kpi/scores', { indicator_id: indicatorId, tahun, semester, skor: nextSkor, catatan: nextCatatan || null }, { headers: { 'X-CSRF-TOKEN': csrf() } });
        setSaved(true);
      } catch { setSaved(true); }
    }, 500);
  }

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <input type="number" min="0" max="100" step="0.01" disabled={isViewer}
        style={{ ...inp, width: 70, textAlign: 'center', fontWeight: 700, color: skorColor(skor === '' ? null : Number(skor)) }}
        value={skor}
        onChange={e => { setSkor(e.target.value); schedule(e.target.value, catatan); }} />
      <input type="text" disabled={isViewer} placeholder="catatan (opsional)"
        style={{ ...inp, fontSize: 11 }}
        value={catatan}
        onChange={e => { setCatatan(e.target.value); schedule(skor, e.target.value); }} />
      {!saved && <Loader2 size={12} style={{ animation: 'spin .8s linear infinite', color: 'var(--muted)', flexShrink: 0 }} />}
    </div>
  );
}

function TabInputSkor({ employees, indicators, scores, tahun, semester, isViewer }) {
  const scoreMap = {};
  scores.forEach(s => { scoreMap[s.indicator_id] = s; });

  const employeesWithIndicators = employees.filter(e => indicators.some(i => i.employee_id === e.id));

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      {employeesWithIndicators.length === 0 && (
        <div style={{ ...card, padding: 20, textAlign: 'center', color: 'var(--muted)', fontSize: 12.5 }}>
          Belum ada karyawan yang punya indikator KPI. Atur dulu di tab "Atur Indikator".
        </div>
      )}
      {employeesWithIndicators.map(e => {
        const myIndicators = indicators.filter(i => i.employee_id === e.id);
        const totalBobot = myIndicators.reduce((s, i) => s + Number(i.bobot), 0);
        return (
          <div key={e.id} style={{ ...card, padding: 14 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 700 }}>{e.nama_lengkap}</div>
                <div style={{ fontSize: 11, color: 'var(--muted)' }}>{e.jabatan}</div>
              </div>
              {totalBobot !== 100 && (
                <div style={{ fontSize: 11, color: '#E04545', display: 'flex', alignItems: 'center', gap: 4 }}>
                  <TriangleAlert size={12} /> Bobot belum 100% ({totalBobot}%)
                </div>
              )}
            </div>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                  <th style={{ textAlign: 'left', padding: '5px 4px', fontSize: 10.5, color: 'var(--muted)' }}>Indikator</th>
                  <th style={{ textAlign: 'center', padding: '5px 4px', fontSize: 10.5, color: 'var(--muted)', width: 60 }}>Bobot</th>
                  <th style={{ textAlign: 'left', padding: '5px 4px', fontSize: 10.5, color: 'var(--muted)', width: 320 }}>Skor (0-100)</th>
                </tr>
              </thead>
              <tbody>
                {myIndicators.map(i => (
                  <tr key={i.id} style={{ borderBottom: '1px solid var(--border)' }}>
                    <td style={{ padding: '7px 4px' }}>{i.nama_indikator}</td>
                    <td style={{ padding: '7px 4px', textAlign: 'center', color: 'var(--muted2)' }}>{i.bobot}%</td>
                    <td style={{ padding: '7px 4px' }}>
                      <ScoreInput indicatorId={i.id} tahun={tahun} semester={semester}
                        initial={scoreMap[i.id]?.skor} initialCatatan={scoreMap[i.id]?.catatan} isViewer={isViewer} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        );
      })}
    </div>
  );
}

// ── TAB 3: RINGKASAN ───────────────────────────────────────
function TabRingkasan({ tahun, semester }) {
  const [rows, setRows] = useState(null);
  const [search, setSearch] = useState('');

  useEffect(() => {
    setRows(null);
    axios.get('/kpi/summary', { params: { tahun, semester } }).then(r => setRows(r.data.rows)).catch(() => setRows([]));
  }, [tahun, semester]);

  if (rows === null) {
    return <div style={{ padding: 30, textAlign: 'center', color: 'var(--muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}><Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> Memuat...</div>;
  }

  const searchLow = search.trim().toLowerCase();
  const filteredRows = searchLow
    ? rows.filter(r => r.nama_lengkap.toLowerCase().includes(searchLow) || r.jabatan.toLowerCase().includes(searchLow))
    : rows;

  return (
    <div>
      <div style={{ position: 'relative', marginBottom: 12, maxWidth: 320 }}>
        <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
        <input type="text" value={search} onChange={e => setSearch(e.target.value)}
          placeholder="Cari nama / jabatan..."
          style={{ ...inp, paddingLeft: 30 }} />
        {search && (
          <span onClick={() => setSearch('')} style={{ position: 'absolute', right: 9, top: '50%', transform: 'translateY(-50%)', cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={13} /></span>
        )}
      </div>
      <div style={{ ...card, overflow: 'hidden' }}>
      <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
        <thead>
          <tr style={{ background: 'var(--bg3)' }}>
            <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
            <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
            <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jml Indikator</th>
            <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Total Bobot</th>
            <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Skor Akhir</th>
          </tr>
        </thead>
        <tbody>
          {filteredRows.length === 0 && <tr><td colSpan={5} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>{search ? 'Tidak ada karyawan yang cocok.' : 'Tidak ada data.'}</td></tr>}
          {filteredRows.map(r => (
            <tr key={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
              <td style={{ padding: '9px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
              <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
              <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.jml_indikator}</td>
              <td style={{ padding: '9px 14px', textAlign: 'center', color: r.total_bobot === 100 ? '#22C97A' : '#E04545' }}>{r.total_bobot}%</td>
              <td style={{ padding: '9px 14px', textAlign: 'center', fontWeight: 700, fontSize: 14, color: skorColor(r.skor_akhir) }}>
                {r.skor_akhir ?? <span style={{ fontSize: 11, fontWeight: 400, color: 'var(--muted)' }}>Belum lengkap</span>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      </div>
    </div>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function KpiIndex({ employees = [], indicators = [], scores = [], tahun, semester }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [activeTab, setActiveTab] = useState('indikator');

  function navigate(t, s) {
    router.get('/kpi', { tahun: t, semester: s }, { preserveState: true, preserveScroll: true });
  }

  const tabs = [
    { key: 'indikator', label: 'Atur Indikator', icon: Target },
    { key: 'skor', label: 'Input Skor', icon: ClipboardList },
    { key: 'ringkasan', label: 'Ringkasan', icon: BarChart3 },
  ];

  return (
    <AppLayout title="KPI" subtitle="Head Office">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ display: 'flex', gap: 4 }}>
          {tabs.map(t => (
            <div key={t.key} onClick={() => setActiveTab(t.key)}
              style={{
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

        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <Users size={13} style={{ color: 'var(--muted)' }} />
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>{employees.length} karyawan HO</span>
          <select value={semester} onChange={e => navigate(tahun, e.target.value)}
            style={{ padding: '7px 11px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--text)', fontSize: 12.5, fontFamily: "'Outfit',sans-serif" }}>
            <option value={1}>Semester 1 (Jan-Jun)</option>
            <option value={2}>Semester 2 (Jul-Des)</option>
          </select>
          <select value={tahun} onChange={e => navigate(e.target.value, semester)}
            style={{ padding: '7px 11px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--text)', fontSize: 12.5, fontFamily: "'Outfit',sans-serif" }}>
            {Array.from({ length: 5 }, (_, i) => tahun - 2 + i).map(y => <option key={y} value={y}>{y}</option>)}
          </select>
        </div>
      </div>

      {activeTab === 'indikator' && (
        <TabIndikator employees={employees} indicators={indicators} isViewer={isViewer}
          onRefresh={() => router.reload({ only: ['indicators'] })} />
      )}
      {activeTab === 'skor' && (
        <TabInputSkor employees={employees} indicators={indicators} scores={scores} tahun={tahun} semester={semester} isViewer={isViewer} />
      )}
      {activeTab === 'ringkasan' && <TabRingkasan tahun={tahun} semester={semester} />}
    </AppLayout>
  );
}
