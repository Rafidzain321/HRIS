// resources/js/Pages/Kpi/Index.jsx
import React, { useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  Target, Plus, Trash2, Pencil, X, TriangleAlert, Loader2,
  Users, BarChart3, Search, RefreshCw, ChevronRight, ChevronDown,
} from 'lucide-react';

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content; }

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const STATUS_META = {
  not_updated: { label: 'Not updated', color: '#8A8F98', bg: 'rgba(138,143,152,.12)' },
  on_track:    { label: 'On track',    color: '#22C97A', bg: 'rgba(34,201,122,.12)' },
  off_track:   { label: 'Off track',   color: '#E04545', bg: 'rgba(224,69,69,.12)' },
  completed:   { label: 'Completed',   color: '#3A8FE0', bg: 'rgba(58,143,224,.12)' },
};

function fmtVal(v, satuan) {
  const n = Number(v) || 0;
  if (satuan === 'rupiah') return 'Rp' + n.toLocaleString('id-ID');
  if (satuan === 'percentage') return n + '%';
  return n.toLocaleString('id-ID');
}

const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr + 'T00:00:00');
  if (isNaN(d.getTime())) return dateStr;
  return `${d.getDate()} ${MONTH_ABBR[d.getMonth()]} ${d.getFullYear()}`;
}

// Susun daftar karyawan jadi berjenjang (atasan -> bawahan) buat tampilan sidebar/pilih goal owner.
function buildHierarchy(employees) {
  const byId = {}; employees.forEach(e => { byId[e.id] = { ...e, children: [] }; });
  const roots = [];
  employees.forEach(e => {
    if (e.atasan_id && byId[e.atasan_id]) byId[e.atasan_id].children.push(byId[e.id]);
    else roots.push(byId[e.id]);
  });
  const flat = [];
  function walk(node, depth) { flat.push({ ...node, depth }); node.children.forEach(c => walk(c, depth + 1)); }
  roots.forEach(r => walk(r, 0));
  return flat;
}

// ── MODAL UPDATE PROGRESS CEPAT ─────────────────────────────
function ProgressModal({ goal, onClose, onSaved }) {
  const [progress, setProgress] = useState(goal.progress_sekarang);
  const [catatan, setCatatan] = useState(goal.catatan || '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  async function submit(e) {
    e.preventDefault();
    setSaving(true); setError('');
    try {
      const res = await axios.put(`/kpi/goals/${goal.id}/progress`, { progress_sekarang: progress, catatan }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      if (res.data.ok) { onSaved(); onClose(); }
      else setError(res.data.message || 'Gagal menyimpan.');
    } catch (err) { setError(err.response?.data?.message || 'Gagal menyimpan.'); }
    setSaving(false);
  }

  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 400, background: 'rgba(0,0,0,.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}
      onMouseDown={e => { e.currentTarget.dataset.downOutside = e.target === e.currentTarget; }}
      onClick={e => { e.target === e.currentTarget && e.currentTarget.dataset.downOutside === 'true' && onClose(); }}>
      <div style={{ background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 16, width: 'min(420px,100%)', boxShadow: '0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 20px', borderBottom: '1px solid var(--border)' }}>
          <div>
            <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><RefreshCw size={15} /> Update Progress</div>
            <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{goal.nama_goal}</div>
          </div>
          <div onClick={onClose} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></div>
        </div>
        <form onSubmit={submit}>
          <div style={{ padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>Baseline {fmtVal(goal.baseline, goal.satuan)} → Target {fmtVal(goal.target, goal.satuan)}</div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Progress Sekarang</label>
              <input type="number" style={inp} value={progress} onChange={e => setProgress(e.target.value)} autoFocus />
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Catatan (opsional)</label>
              <textarea style={{ ...inp, minHeight: 60, resize: 'vertical' }} value={catatan} onChange={e => setCatatan(e.target.value)} />
            </div>
            {error && <div style={{ padding: '8px 12px', borderRadius: 8, background: 'rgba(224,69,69,.1)', border: '1px solid rgba(224,69,69,.2)', fontSize: 12, color: '#E04545', display: 'flex', alignItems: 'center', gap: 6 }}><TriangleAlert size={13} /> {error}</div>}
          </div>
          <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', padding: '12px 20px', borderTop: '1px solid var(--border)' }}>
            <button type="button" onClick={onClose} style={{ padding: '9px 18px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 12.5, cursor: 'pointer', fontFamily: "'Outfit',sans-serif" }}>Batal</button>
            <button type="submit" disabled={saving} style={{ padding: '9px 22px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#22C97A,#148050)', color: '#fff', fontSize: 12.5, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", opacity: saving ? .7 : 1 }}>
              {saving ? <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> : 'Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── AVATAR INISIAL (warna konsisten per nama) ───────────────
const AVATAR_COLORS = ['#22C97A','#3A8FE0','#E04545','#E8A020','#9B59B6','#1ABC9C','#E06A20','#5C6BC0'];
function avatarColor(nama) {
  let hash = 0;
  for (const c of (nama || '')) hash = (hash * 31 + c.charCodeAt(0)) >>> 0;
  return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}
function OwnerAvatar({ nama, size = 30 }) {
  const initials = (nama || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  return (
    <div style={{ width: size, height: size, borderRadius: '50%', background: avatarColor(nama), color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: size * 0.36, fontWeight: 700, flexShrink: 0 }}>
      {initials}
    </div>
  );
}

// ── DROPDOWN FILTER KARYAWAN (hierarki atasan-bawahan) ──────
function EmployeeFilterDropdown({ employees, goals, valueId, onChange }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const searchLow = search.trim().toLowerCase();
  const list = searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : buildHierarchy(employees);

  const selected = employees.find(e => e.id === valueId);
  const label = selected ? selected.nama_lengkap : 'Semua Karyawan';

  return (
    <div style={{ position: 'relative' }}>
      <button onClick={() => setOpen(o => !o)} style={{
        padding: '8px 14px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)',
        color: 'var(--text)', fontSize: 12.5, fontWeight: 600, cursor: 'pointer', fontFamily: "'Outfit',sans-serif",
        display: 'flex', alignItems: 'center', gap: 8, minWidth: 200, justifyContent: 'space-between',
      }}>
        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{label}</span>
        <ChevronDown size={14} style={{ color: 'var(--muted)', flexShrink: 0, transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 60 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 6px)', left: 0, zIndex: 70, width: 'max(100%, 280px)', background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', padding: 10 }}>
            <div style={{ position: 'relative', marginBottom: 8 }}>
              <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
              <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} autoFocus />
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2, maxHeight: 340, overflowY: 'auto' }}>
              <div onClick={() => { onChange(null); setOpen(false); }}
                style={{ padding: '7px 9px', borderRadius: 7, cursor: 'pointer', fontSize: 12.5, fontWeight: 600, background: !valueId ? 'rgba(232,160,32,.12)' : 'transparent', color: !valueId ? 'var(--accent)' : 'var(--text)' }}>
                Semua Karyawan
              </div>
              {list.map(e => {
                const jml = goals.filter(g => g.employee_id === e.id).length;
                const active = e.id === valueId;
                const depth = e.depth || 0;
                return (
                  <div key={e.id} onClick={() => { onChange(e.id); setOpen(false); }}
                    style={{ padding: '7px 9px', paddingLeft: 9 + depth * 16, borderRadius: 7, cursor: 'pointer', background: active ? 'rgba(232,160,32,.12)' : 'transparent', display: 'flex', alignItems: 'center', gap: 4 }}>
                    {depth > 0 && <ChevronRight size={11} style={{ color: 'var(--muted)', flexShrink: 0 }} />}
                    <div style={{ minWidth: 0 }}>
                      <div style={{ fontSize: 12.5, fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)' }}>{e.nama_lengkap}</div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{e.jabatan} · {jml} goal</div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

// ── DROPDOWN AKSI PER BARIS (portal ke document.body supaya tidak kepotong
// batas overflow:hidden tabel) ───────────────────────────────
function GoalActionsMenu({ goal, onEdit, onUpdateProgress, onDelete }) {
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState({ top: 0, left: 0 });
  const btnRef = useRef(null);
  const item = { display: 'flex', alignItems: 'center', gap: 8, width: '100%', padding: '8px 12px', border: 'none', background: 'none', fontSize: 12, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", textAlign: 'left', color: 'var(--text)' };
  const MENU_WIDTH = 170;

  function toggle() {
    if (!open && btnRef.current) {
      const r = btnRef.current.getBoundingClientRect();
      setPos({ top: r.bottom + 4, left: Math.max(8, r.right - MENU_WIDTH) });
    }
    setOpen(o => !o);
  }

  return (
    <>
      <button ref={btnRef} onClick={toggle} style={{
        padding: '6px 12px', borderRadius: 7, border: '1px solid var(--border)', background: 'var(--card)',
        color: 'var(--text)', fontSize: 11.5, fontWeight: 600, cursor: 'pointer', fontFamily: "'Outfit',sans-serif",
        display: 'flex', alignItems: 'center', gap: 6,
      }}>
        Aksi <ChevronDown size={12} />
      </button>
      {open && createPortal(
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 200 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'fixed', top: pos.top, left: pos.left, zIndex: 210, width: MENU_WIDTH, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 9, boxShadow: '0 12px 32px rgba(0,0,0,.35)', overflow: 'hidden' }}>
            <button style={item} onClick={() => { setOpen(false); onUpdateProgress(goal); }}><RefreshCw size={13} /> Update Progress</button>
            <button style={item} onClick={() => { setOpen(false); onEdit(goal); }}><Pencil size={13} /> Edit</button>
            <button style={{ ...item, color: '#E04545' }} onClick={() => { setOpen(false); onDelete(goal); }}><Trash2 size={13} /> Hapus</button>
          </div>
        </>,
        document.body
      )}
    </>
  );
}

// ── TAB GOALS (tabel, mirip Mekari Talenta) ─────────────────
function isGoalClosed(g) {
  const todayIso = new Date().toISOString().slice(0, 10);
  return g.status === 'completed' || g.tanggal_selesai < todayIso;
}

function TabGoals({ employees, goals, isViewer, isSelfOnly, onRefresh }) {
  const [filterId, setFilterId] = useState(null);
  const [search, setSearch] = useState('');
  const [periodTab, setPeriodTab] = useState('ongoing');
  const [statusFilter, setStatusFilter] = useState('');
  const [progressModal, setProgressModal] = useState(null);
  const [confirmDelete, setConfirmDelete] = useState(null);

  const employeesById = {};
  employees.forEach(e => { employeesById[e.id] = e; });

  const ongoingGoals = goals.filter(g => !isGoalClosed(g));
  const closedGoals  = goals.filter(g => isGoalClosed(g));
  const periodGoals  = periodTab === 'ongoing' ? ongoingGoals : closedGoals;

  const searchLow = search.trim().toLowerCase();
  const rows = periodGoals
    .filter(g => !filterId || g.employee_id === filterId)
    .filter(g => !statusFilter || g.status === statusFilter)
    .filter(g => {
      if (!searchLow) return true;
      const owner = employeesById[g.employee_id];
      return g.nama_goal.toLowerCase().includes(searchLow)
        || (owner?.nama_lengkap || '').toLowerCase().includes(searchLow)
        || (owner?.jabatan || '').toLowerCase().includes(searchLow);
    });

  const showFilter = employees.length > 1;
  const addHref = filterId ? `/kpi/goals/create?employee_id=${filterId}` : '/kpi/goals/create';

  const periodTabs = [
    { key: 'ongoing', label: 'Ongoing', count: ongoingGoals.length },
    { key: 'closed', label: 'Selesai', count: closedGoals.length },
  ];

  async function doDelete(goal) {
    await axios.delete(`/kpi/goals/${goal.id}`, { headers: { 'X-CSRF-TOKEN': csrf() } });
    setConfirmDelete(null);
    onRefresh();
  }

  return (
    <div>
      {progressModal && <ProgressModal goal={progressModal} onClose={() => setProgressModal(null)} onSaved={onRefresh} />}
      <ConfirmModal open={!!confirmDelete} onCancel={() => setConfirmDelete(null)}
        onConfirm={() => confirmDelete && doDelete(confirmDelete)}
        title="Hapus Goal" message={confirmDelete ? <>Goal <b style={{ color: 'var(--text)' }}>{confirmDelete.nama_goal}</b> akan dihapus permanen.</> : ''}
        confirmLabel="Ya, Hapus" />

      <div style={{ display: 'flex', gap: 4, marginBottom: 14 }}>
        {periodTabs.map(t => (
          <div key={t.key} onClick={() => setPeriodTab(t.key)} style={{
            padding: '7px 4px', marginRight: 18, cursor: 'pointer', fontSize: 12.5, fontWeight: 600,
            color: periodTab === t.key ? 'var(--accent)' : 'var(--muted)',
            borderBottom: `2px solid ${periodTab === t.key ? 'var(--accent)' : 'transparent'}`,
            display: 'flex', alignItems: 'center', gap: 6,
          }}>
            {t.label}
            <span style={{ fontSize: 10.5, fontWeight: 700, padding: '1px 7px', borderRadius: 99, background: periodTab === t.key ? 'rgba(232,160,32,.15)' : 'var(--bg3)', color: periodTab === t.key ? 'var(--accent)' : 'var(--muted2)' }}>{t.count}</span>
          </div>
        ))}
      </div>

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 14, flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          {showFilter && <EmployeeFilterDropdown employees={employees} goals={goals} valueId={filterId} onChange={setFilterId} />}
          <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} style={{ ...inp, width: 'auto', minWidth: 140 }}>
            <option value="">Semua Status</option>
            {Object.entries(STATUS_META).map(([key, meta]) => <option key={key} value={key}>{meta.label}</option>)}
          </select>
          <div style={{ position: 'relative' }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari goal / pemilik..." style={{ ...inp, paddingLeft: 30, width: 220 }} />
          </div>
        </div>
        {!isViewer && (
          <button onClick={() => router.visit(addHref)} style={{ padding: '8px 16px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Plus size={14} /> Tambah Goal</button>
        )}
      </div>

      <div style={{ ...card, overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
            <thead>
              <tr style={{ background: 'var(--bg3)' }}>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)', minWidth: 220 }}>Goal</th>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)', minWidth: 180 }}>Pemilik</th>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)', minWidth: 180 }}>Progress</th>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Status</th>
                {!isViewer && <th style={{ padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }} />}
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr><td colSpan={isViewer ? 4 : 5} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>Belum ada goal.</td></tr>
              )}
              {rows.map(g => {
                const owner = employeesById[g.employee_id];
                const meta = STATUS_META[g.status];
                return (
                  <tr key={g.id} style={{ borderTop: '1px solid var(--border)' }}>
                    <td style={{ padding: '12px 14px', verticalAlign: 'top' }}>
                      <div style={{ fontWeight: 600 }}>{g.nama_goal}</div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 3 }}>{fmtDate(g.tanggal_mulai)} – {fmtDate(g.tanggal_selesai)}</div>
                    </td>
                    <td style={{ padding: '12px 14px', verticalAlign: 'top' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <OwnerAvatar nama={owner?.nama_lengkap} />
                        <div style={{ minWidth: 0 }}>
                          <div style={{ fontSize: 12, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{owner?.nama_lengkap || '—'}</div>
                          <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{owner?.jabatan || '-'}</div>
                        </div>
                      </div>
                    </td>
                    <td style={{ padding: '12px 14px', verticalAlign: 'top' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <div style={{ flex: 1, height: 7, borderRadius: 99, background: 'var(--bg3)', overflow: 'hidden', minWidth: 70 }}>
                          <div style={{ width: `${g.progress_percent}%`, height: '100%', background: meta.color, borderRadius: 99, transition: 'width .3s' }} />
                        </div>
                        <div style={{ fontSize: 11.5, fontWeight: 700, color: meta.color, width: 38, textAlign: 'right', flexShrink: 0 }}>{g.progress_percent}%</div>
                      </div>
                      <div style={{ fontSize: 10, color: 'var(--muted)', marginTop: 3 }}>{fmtVal(g.progress_sekarang, g.satuan)} / {fmtVal(g.target, g.satuan)} <span style={{ marginLeft: 6 }}>· Bobot {g.bobot}%</span></div>
                    </td>
                    <td style={{ padding: '12px 14px', verticalAlign: 'top' }}>
                      <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 99, background: meta.bg, color: meta.color, whiteSpace: 'nowrap' }}>{meta.label}</span>
                    </td>
                    {!isViewer && (
                      <td style={{ padding: '12px 14px', verticalAlign: 'top', textAlign: 'right' }}>
                        <GoalActionsMenu goal={g}
                          onEdit={goal => router.visit(`/kpi/goals/${goal.id}/edit`)}
                          onUpdateProgress={setProgressModal}
                          onDelete={setConfirmDelete} />
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// ── DONUT CHART (CSS conic-gradient, tanpa library) ─────────
function DonutChart({ counts, size = 120 }) {
  const total = Object.values(counts).reduce((a, b) => a + b, 0) || 1;
  let acc = 0;
  const segments = Object.entries(counts).filter(([, v]) => v > 0).map(([key, v]) => {
    const start = acc / total * 360;
    acc += v;
    const end = acc / total * 360;
    return `${STATUS_META[key].color} ${start}deg ${end}deg`;
  });
  const gradient = segments.length ? `conic-gradient(${segments.join(',')})` : 'conic-gradient(var(--border) 0deg 360deg)';
  return (
    <div style={{ position: 'relative', width: size, height: size, borderRadius: '50%', background: gradient, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
      <div style={{ width: size * 0.62, height: size * 0.62, borderRadius: '50%', background: 'var(--card)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column' }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 20, fontWeight: 700 }}>{total}</div>
        <div style={{ fontSize: 9.5, color: 'var(--muted)' }}>goals</div>
      </div>
    </div>
  );
}

// ── TAB DASHBOARD ────────────────────────────────────────────
function TabDashboard({ rows, loading }) {
  const [search, setSearch] = useState('');
  const searchLow = search.trim().toLowerCase();
  const filtered = searchLow ? rows.filter(r => r.nama_lengkap.toLowerCase().includes(searchLow) || r.jabatan.toLowerCase().includes(searchLow)) : rows;

  const agg = rows.reduce((acc, r) => {
    acc.not_updated += r.not_updated; acc.on_track += r.on_track; acc.off_track += r.off_track; acc.completed += r.completed;
    return acc;
  }, { not_updated: 0, on_track: 0, off_track: 0, completed: 0 });

  if (loading) return <div style={{ padding: 30, textAlign: 'center', color: 'var(--muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}><Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> Memuat...</div>;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ ...card, padding: 18, display: 'flex', alignItems: 'center', gap: 24, flexWrap: 'wrap' }}>
        <DonutChart counts={agg} />
        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
          {Object.entries(STATUS_META).map(([key, meta]) => (
            <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <div style={{ width: 10, height: 10, borderRadius: 3, background: meta.color }} />
              <div>
                <div style={{ fontSize: 16, fontWeight: 700, fontFamily: 'Syne,sans-serif' }}>{agg[key]}</div>
                <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{meta.label}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ position: 'relative', maxWidth: 320 }}>
        <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
        <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
      </div>

      <div style={{ ...card, overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
          <thead>
            <tr style={{ background: 'var(--bg3)' }}>
              <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
              <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
              <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Goals</th>
              <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Total Bobot</th>
              <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Skor Akhir</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length === 0 && <tr><td colSpan={5} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada data.</td></tr>}
            {filtered.map(r => (
              <tr key={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
                <td style={{ padding: '9px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
                <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
                <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.jml_goal}</td>
                <td style={{ padding: '9px 14px', textAlign: 'center', color: r.total_bobot === 100 ? '#22C97A' : '#E04545' }}>{r.total_bobot}%</td>
                <td style={{ padding: '9px 14px', textAlign: 'center', fontWeight: 700, fontSize: 14 }}>
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
export default function KpiIndex({ employees = [], goals = [], is_self_only = false }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [activeTab, setActiveTab] = useState('goals');
  const [summaryRows, setSummaryRows] = useState(null);

  function loadSummary() {
    setSummaryRows(null);
    axios.get('/kpi/summary').then(r => setSummaryRows(r.data.rows)).catch(() => setSummaryRows([]));
  }

  function onRefresh() { router.reload({ only: ['goals'] }); }

  const tabs = [
    { key: 'goals', label: 'Goals', icon: Target },
    { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
  ];

  return (
    <AppLayout title="KPI" subtitle="Head Office">
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ display: 'flex', gap: 4 }}>
          {tabs.map(t => (
            <div key={t.key} onClick={() => { setActiveTab(t.key); if (t.key === 'dashboard' && summaryRows === null) loadSummary(); }}
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
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>
            {is_self_only ? (employees.length > 1 ? `${employees.length} orang (kamu & tim)` : 'Goals kamu') : `${employees.length} karyawan HO`}
          </span>
        </div>
      </div>

      {activeTab === 'goals' && (
        <TabGoals employees={employees} goals={goals} isViewer={isViewer} isSelfOnly={is_self_only} onRefresh={onRefresh} />
      )}
      {activeTab === 'dashboard' && (
        <TabDashboard rows={summaryRows || []} loading={summaryRows === null} />
      )}
    </AppLayout>
  );
}
