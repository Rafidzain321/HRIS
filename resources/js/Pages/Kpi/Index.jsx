// resources/js/Pages/Kpi/Index.jsx
import React, { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  Target, Plus, Trash2, Pencil, X, TriangleAlert, Loader2,
  Users, BarChart3, Search, RefreshCw, ChevronLeft, ChevronRight, ChevronDown, Download, Network, Info,
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

// Ubah Skor Akhir (angka 0-100) jadi kesimpulan/predikat — sama seperti pola penilaian KPI
// pada umumnya (>=100 Baik Sekali, 75-99 Baik, 45-74 Cukup, <45 Kurang).
function getPredikat(skorAkhir) {
  if (skorAkhir === null || skorAkhir === undefined) return null;
  if (skorAkhir >= 100) return { label: 'Baik Sekali', color: '#3A8FE0', bg: 'rgba(58,143,224,.12)' };
  if (skorAkhir >= 75)  return { label: 'Baik',         color: '#22C97A', bg: 'rgba(34,201,122,.12)' };
  if (skorAkhir >= 45)  return { label: 'Cukup',        color: '#E8A020', bg: 'rgba(232,160,32,.12)' };
  return { label: 'Kurang', color: '#E04545', bg: 'rgba(224,69,69,.12)' };
}

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
              <input type="number" min="0" style={inp} value={progress} onChange={e => setProgress(e.target.value === '' ? '' : Math.max(0, Number(e.target.value)))} autoFocus />
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
// ── TOMBOL EXPORT EXCEL (semua karyawan / satu karyawan tertentu) ──
function ExportMenu({ employees }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const searchLow = search.trim().toLowerCase();
  // Selalu rata (tidak berjenjang atasan-bawahan) — struktur organisasi diatur terpisah
  // di Pengaturan, jadi daftar pemilihan di sini cukup diurutkan alfabetis saja.
  const list = (searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : employees
  ).slice().sort((a, b) => a.nama_lengkap.localeCompare(b.nama_lengkap));

  return (
    <div style={{ position: 'relative' }}>
      <button onClick={() => setOpen(o => !o)} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.08)', color: 'var(--blue)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}>
        <Download size={14} /> Export Excel <ChevronDown size={12} style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 60 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 6px)', right: 0, zIndex: 70, width: 280, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', overflow: 'hidden' }}>
            <a href="/kpi/export" onClick={() => setOpen(false)}
              style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 12px', fontSize: 12.5, fontWeight: 700, color: 'var(--text)', textDecoration: 'none', borderBottom: '1px solid var(--border)' }}>
              <Users size={13} /> Semua Karyawan
            </a>
            <div style={{ padding: 8 }}>
              <div style={{ position: 'relative', marginBottom: 6 }}>
                <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={12} /></span>
                <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Atau cari karyawan tertentu..." style={{ ...inp, paddingLeft: 28, fontSize: 12 }} autoFocus />
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 2, maxHeight: 260, overflowY: 'auto' }}>
                {list.length === 0 && <div style={{ padding: 10, fontSize: 11.5, color: 'var(--muted)', textAlign: 'center' }}>Tidak ditemukan.</div>}
                {list.map(e => (
                  <a key={e.id} href={`/kpi/export?employee_id=${e.id}`} onClick={() => setOpen(false)}
                    style={{ display: 'block', padding: '6px 8px', borderRadius: 6, textDecoration: 'none', color: 'var(--text)' }}
                    onMouseEnter={ev => ev.currentTarget.style.background = 'rgba(232,160,32,.08)'}
                    onMouseLeave={ev => ev.currentTarget.style.background = ''}>
                    <div style={{ fontSize: 12, fontWeight: 600 }}>{e.nama_lengkap}</div>
                    <div style={{ fontSize: 10, color: 'var(--muted)' }}>{e.jabatan}</div>
                  </a>
                ))}
              </div>
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
  const MENU_HEIGHT = 116; // perkiraan tinggi 3 item menu (Update Progress, Edit, Hapus)

  function toggle() {
    if (!open && btnRef.current) {
      const r = btnRef.current.getBoundingClientRect();
      // Kalau ruang di bawah tombol tidak cukup buat 3 item menu (mis. baris paling bawah
      // tabel), buka ke atas supaya Edit & Hapus tidak kepotong di luar viewport.
      const openUpward = window.innerHeight - r.bottom < MENU_HEIGHT + 8;
      setPos({
        top: openUpward ? r.top - MENU_HEIGHT - 4 : r.bottom + 4,
        left: Math.max(8, r.right - MENU_WIDTH),
      });
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

function TabGoals({ employees, goals, isViewer, isSelfOnly, onRefresh, highlight }) {
  const [search, setSearch] = useState('');
  const [periodTab, setPeriodTab] = useState('ongoing');
  const [statusFilter, setStatusFilter] = useState('');
  const [reviewerFilter, setReviewerFilter] = useState('');
  const [progressModal, setProgressModal] = useState(null);
  const [confirmDelete, setConfirmDelete] = useState(null);

  // Datang dari klik notifikasi bell (goal yang perlu direview) — jangan langsung buka modal,
  // cukup pindah ke tab yang sesuai lalu sorot barisnya biar user lihat dulu konteksnya.
  useEffect(() => {
    if (!highlight) return;
    const goal = goals.find(g => String(g.id) === String(highlight));
    if (goal) setPeriodTab(isGoalClosed(goal) ? 'closed' : 'ongoing');

    const timer = setTimeout(() => {
      const row = document.querySelector(`tr[data-id="${highlight}"]`);
      if (row) {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.style.transition = 'background 0.3s';
        row.style.background = 'rgba(232,160,32,.35)';
        setTimeout(() => {
          row.style.background = 'rgba(232,160,32,.15)';
          setTimeout(() => { row.style.background = ''; }, 2500);
        }, 600);
      }
      const url = new URL(window.location.href);
      url.searchParams.delete('highlight');
      window.history.replaceState({}, '', url);
    }, 400);
    return () => clearTimeout(timer);
  }, [highlight]);

  const employeesById = {};
  employees.forEach(e => { employeesById[e.id] = e; });

  // Daftar reviewer yang benar-benar sedang ditugaskan (bukan semua karyawan) — buat filter
  // "siapa ditugaskan review apa" dari sisi HR.
  const reviewerOptions = [];
  const seenReviewer = new Set();
  goals.forEach(g => {
    if (g.reviewer_id && !seenReviewer.has(g.reviewer_id)) {
      seenReviewer.add(g.reviewer_id);
      reviewerOptions.push({ id: g.reviewer_id, nama: g.reviewer_nama || `#${g.reviewer_id}` });
    }
  });
  reviewerOptions.sort((a, b) => a.nama.localeCompare(b.nama));

  // Filter karyawan/status/reviewer/pencarian diterapkan dulu ke SEMUA goal (belum dipotong
  // periode) — supaya angka di tab Ongoing/Selesai ikut menyesuaikan filter yang aktif,
  // bukan selalu menampilkan total keseluruhan tanpa filter.
  const searchLow = search.trim().toLowerCase();
  const matchesFilters = g => {
    if (statusFilter && g.status !== statusFilter) return false;
    if (reviewerFilter && String(g.reviewer_id) !== String(reviewerFilter)) return false;
    if (searchLow) {
      const owner = employeesById[g.employee_id];
      const match = g.nama_goal.toLowerCase().includes(searchLow)
        || (owner?.nama_lengkap || '').toLowerCase().includes(searchLow)
        || (owner?.jabatan || '').toLowerCase().includes(searchLow);
      if (!match) return false;
    }
    return true;
  };

  const filteredGoalsAll = goals.filter(matchesFilters);
  const ongoingGoals = filteredGoalsAll.filter(g => !isGoalClosed(g));
  const closedGoals  = filteredGoalsAll.filter(g => isGoalClosed(g));
  const rows = periodTab === 'ongoing' ? ongoingGoals : closedGoals;

  const addHref = '/kpi/goals/create';

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
          <div key={t.key} onClick={() => {
            setPeriodTab(t.key);
            // Ongoing tidak mungkin punya goal berstatus Completed (itu pasti masuk Selesai) —
            // reset biar tidak ada 2 filter yang saling meniadakan sekaligus.
            if (t.key === 'ongoing' && statusFilter === 'completed') setStatusFilter('');
          }} style={{
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
          <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)} style={{ ...inp, width: 'auto', minWidth: 140 }}>
            <option value="">Semua Status</option>
            {Object.entries(STATUS_META)
              // Di tab Ongoing, status Completed pasti tidak ada hasilnya (goal completed
              // otomatis masuk tab Selesai) — jangan tawarkan kombinasi yang mustahil itu.
              .filter(([key]) => !(periodTab === 'ongoing' && key === 'completed'))
              .map(([key, meta]) => <option key={key} value={key}>{meta.label}</option>)}
          </select>
          {reviewerOptions.length > 0 && (
            <select value={reviewerFilter} onChange={e => setReviewerFilter(e.target.value)} style={{ ...inp, width: 'auto', minWidth: 160 }}>
              <option value="">Semua Reviewer</option>
              {reviewerOptions.map(r => <option key={r.id} value={r.id}>Direview: {r.nama}</option>)}
            </select>
          )}
          <div style={{ position: 'relative' }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari goal / pemilik..." style={{ ...inp, paddingLeft: 30, width: 220 }} />
          </div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          {!isViewer && !isSelfOnly && (
            <button onClick={() => router.visit('/pengaturan/struktur-organisasi')} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--muted2)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Network size={14} /> Struktur Organisasi</button>
          )}
          <ExportMenu employees={employees} />
          {!isViewer && (
            <button onClick={() => router.visit(addHref)} style={{ padding: '8px 16px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Plus size={14} /> Tambah Goal</button>
          )}
        </div>
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
                  <tr key={g.id} data-id={g.id} style={{ borderTop: '1px solid var(--border)' }}>
                    <td style={{ padding: '12px 14px', verticalAlign: 'top' }}>
                      <div style={{ fontWeight: 600 }}>{g.nama_goal}</div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 3 }}>{fmtDate(g.tanggal_mulai)} – {fmtDate(g.tanggal_selesai)}</div>
                      {g.reviewer_nama && (
                        <div style={{ fontSize: 10, color: 'var(--blue)', marginTop: 3, display: 'flex', alignItems: 'center', gap: 4 }}>
                          <RefreshCw size={9} /> Reviewer: {g.reviewer_nama}
                        </div>
                      )}
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

// ── STAT TILE KECIL (dipakai di Goal Analytics) ─────────────
function StatTile({ label, value, sub }) {
  return (
    <div style={{ padding: '8px 0', borderBottom: '1px solid var(--border)' }}>
      <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 22, fontWeight: 700 }}>{value}</div>
      <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{label}</div>
      {sub && <div style={{ fontSize: 10, color: 'var(--muted)' }}>{sub}</div>}
    </div>
  );
}

// ── TAB DASHBOARD (Goal Analytics ala Mekari Talenta) ───────
function TabDashboard({ goals, employees }) {
  const [periodFilter, setPeriodFilter] = useState('ongoing'); // all | ongoing | closed
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10); // 5 | 10 | 25 | 50 | 'all'

  const employeesById = {};
  employees.forEach(e => { employeesById[e.id] = e; });

  // Filter goal berdasarkan periode (Ongoing/Selesai) & rentang tanggal (overlap dengan
  // tanggal_mulai–tanggal_selesai goal itu, bukan cuma tanggal_mulai-nya saja).
  const filteredGoals = goals.filter(g => {
    if (periodFilter === 'ongoing' && isGoalClosed(g)) return false;
    if (periodFilter === 'closed' && !isGoalClosed(g)) return false;
    if (dateFrom && g.tanggal_selesai < dateFrom) return false;
    if (dateTo && g.tanggal_mulai > dateTo) return false;
    return true;
  });

  const totalGoals = filteredGoals.length;
  const avgProgress = totalGoals > 0 ? Math.round(filteredGoals.reduce((a, g) => a + g.progress_percent, 0) / totalGoals) : 0;
  const totalKaryawan = new Set(filteredGoals.map(g => g.employee_id)).size;

  const statusCounts = filteredGoals.reduce((acc, g) => {
    acc[g.status] = (acc[g.status] || 0) + 1;
    return acc;
  }, { not_updated: 0, on_track: 0, off_track: 0, completed: 0 });

  // Rekap per karyawan (Total Bobot & Skor Akhir) — dihitung langsung dari goal yang sudah
  // difilter, jadi ikut berubah kalau filter periode/tanggal di atas diubah.
  const byEmployee = {};
  filteredGoals.forEach(g => {
    if (!byEmployee[g.employee_id]) byEmployee[g.employee_id] = [];
    byEmployee[g.employee_id].push(g);
  });
  const summaryRows = Object.entries(byEmployee).map(([empId, gs]) => {
    const e = employeesById[empId] ?? employeesById[Number(empId)];
    const totalBobot = gs.reduce((a, g) => a + g.bobot, 0);
    const skorAkhir = totalBobot > 0 ? Math.round(gs.reduce((a, g) => a + g.progress_percent * g.bobot / 100, 0) * 100) / 100 : null;
    return { employee_id: empId, nama_lengkap: e?.nama_lengkap || '—', jabatan: e?.jabatan || '-', jml_goal: gs.length, total_bobot: totalBobot, skor_akhir: skorAkhir };
  }).sort((a, b) => a.nama_lengkap.localeCompare(b.nama_lengkap));

  const searchLow = search.trim().toLowerCase();
  const filteredRows = searchLow ? summaryRows.filter(r => r.nama_lengkap.toLowerCase().includes(searchLow) || r.jabatan.toLowerCase().includes(searchLow)) : summaryRows;

  const totalRows   = filteredRows.length;
  const totalPages  = perPage === 'all' ? 1 : Math.max(1, Math.ceil(totalRows / perPage));
  const pageSafe    = Math.min(page, totalPages);
  const pagedRows   = perPage === 'all' ? filteredRows : filteredRows.slice((pageSafe - 1) * perPage, pageSafe * perPage);
  const rangeStart  = totalRows === 0 ? 0 : (pageSafe - 1) * (perPage === 'all' ? totalRows : perPage) + 1;
  const rangeEnd    = perPage === 'all' ? totalRows : Math.min(pageSafe * perPage, totalRows);

  return (
    <div className="kpi-dash-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 300px', gap: 16, alignItems: 'start' }}>
      {/* ── KIRI: tabel goals per karyawan ── */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
          <div style={{ position: 'relative', maxWidth: 320, flex: 1 }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Tampilkan</span>
            <select value={perPage} onChange={e => { setPerPage(e.target.value === 'all' ? 'all' : Number(e.target.value)); setPage(1); }} style={{ ...inp, width: 'auto' }}>
              <option value={5}>5</option>
              <option value={10}>10</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value="all">Semua</option>
            </select>
          </div>
        </div>

        <div style={{ ...card, overflow: 'hidden' }}>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
              <thead>
                <tr style={{ background: 'var(--bg3)' }}>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Goals</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Total Bobot</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                      Skor Akhir
                      <Info size={11} style={{ cursor: 'help', flexShrink: 0 }}
                        title="Skor Akhir = Σ(Progress% × Bobot) tiap goal, TIDAK dinormalisasi ke Total Bobot. Kalau Total Bobot karyawan belum 100%, Skor Akhir maksimalnya otomatis ikut lebih rendah dari 100 walau semua goal-nya tercapai penuh. Skor Akhir baru bisa dibandingkan adil antar karyawan kalau Total Bobot masing-masing sudah 100%." />
                    </span>
                  </th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Predikat</th>
                </tr>
              </thead>
              <tbody>
                {pagedRows.length === 0 && <tr><td colSpan={6} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada data.</td></tr>}
                {pagedRows.map(r => {
                  const predikat = getPredikat(r.skor_akhir);
                  return (
                    <tr key={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
                      <td style={{ padding: '9px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
                      <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>{r.jml_goal}</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center', color: r.total_bobot === 100 ? '#22C97A' : '#E04545' }}>{r.total_bobot}%</td>
                      <td style={{ padding: '9px 14px', textAlign: 'center', fontWeight: 700, fontSize: 14 }}>
                        {r.skor_akhir ?? <span style={{ fontSize: 11, fontWeight: 400, color: 'var(--muted)' }}>Belum lengkap</span>}
                      </td>
                      <td style={{ padding: '9px 14px', textAlign: 'center' }}>
                        {predikat
                          ? <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 99, background: predikat.bg, color: predikat.color, whiteSpace: 'nowrap' }}>{predikat.label}</span>
                          : <span style={{ fontSize: 11, color: 'var(--muted)' }}>—</span>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          {totalRows > 0 && (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', padding: '10px 14px', borderTop: '1px solid var(--border)' }}>
              <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Menampilkan {rangeStart}–{rangeEnd} dari {totalRows} karyawan</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={pageSafe <= 1} style={{ padding: '5px 10px', borderRadius: 7, border: '1px solid var(--border)', background: 'var(--bg3)', color: pageSafe <= 1 ? 'var(--muted)' : 'var(--text)', fontSize: 11.5, cursor: pageSafe <= 1 ? 'not-allowed' : 'pointer', fontFamily: "'Outfit',sans-serif" }}><ChevronLeft size={13} /></button>
                <span style={{ fontSize: 11.5, color: 'var(--muted2)' }}>Halaman {pageSafe} / {totalPages}</span>
                <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={pageSafe >= totalPages} style={{ padding: '5px 10px', borderRadius: 7, border: '1px solid var(--border)', background: 'var(--bg3)', color: pageSafe >= totalPages ? 'var(--muted)' : 'var(--text)', fontSize: 11.5, cursor: pageSafe >= totalPages ? 'not-allowed' : 'pointer', fontFamily: "'Outfit',sans-serif" }}><ChevronRight size={13} /></button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── KANAN: Goal Analytics (sidebar) ── */}
      <div style={{ ...card, padding: 16, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><BarChart3 size={14} /> Goal Analytics</div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <select value={periodFilter} onChange={e => { setPeriodFilter(e.target.value); setPage(1); }} style={inp}>
            <option value="all">Semua Periode</option>
            <option value="ongoing">Ongoing</option>
            <option value="closed">Selesai</option>
          </select>
          <div style={{ display: 'flex', alignItems: 'center', gap: 4, background: 'var(--bg3)', border: '1px solid var(--border)', borderRadius: 8, padding: '0 8px' }}>
            <input type="date" value={dateFrom} onChange={e => { setDateFrom(e.target.value); setPage(1); }}
              style={{ flex: 1, minWidth: 0, border: 'none', background: 'transparent', color: 'var(--text)', fontSize: 12, fontFamily: "'Outfit',sans-serif", padding: '8px 0', outline: 'none' }} />
            <span style={{ fontSize: 10.5, color: 'var(--muted)', flexShrink: 0 }}>s/d</span>
            <input type="date" value={dateTo} onChange={e => { setDateTo(e.target.value); setPage(1); }}
              style={{ flex: 1, minWidth: 0, border: 'none', background: 'transparent', color: 'var(--text)', fontSize: 12, fontFamily: "'Outfit',sans-serif", padding: '8px 0', outline: 'none' }} />
          </div>
          {(dateFrom || dateTo) && (
            <button onClick={() => { setDateFrom(''); setDateTo(''); setPage(1); }} style={{ padding: '6px 10px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 11, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", alignSelf: 'flex-start' }}>Reset tanggal</button>
          )}
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <StatTile label="Total Individual Goals" value={totalGoals} />
          <StatTile label="Rata-rata Progress" value={`${avgProgress}%`} />
          <StatTile label="Karyawan Terlibat" value={totalKaryawan} />
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12, paddingTop: 4 }}>
          <DonutChart counts={statusCounts} size={130} />
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, width: '100%' }}>
            <div style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600 }}>Total goals: {totalGoals}</div>
            {Object.entries(STATUS_META).map(([key, meta]) => {
              const count = statusCounts[key] || 0;
              const pct = totalGoals > 0 ? Math.round(count / totalGoals * 100) : 0;
              return (
                <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <div style={{ width: 10, height: 10, borderRadius: 3, background: meta.color, flexShrink: 0 }} />
                  <div style={{ fontSize: 12, flex: 1 }}>{meta.label}</div>
                  <div style={{ fontSize: 12, fontWeight: 700 }}>{count}</div>
                  <div style={{ fontSize: 10.5, color: 'var(--muted)', width: 36, textAlign: 'right' }}>{pct}%</div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function KpiIndex({ employees = [], goals = [], is_self_only = false, highlight = null }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  // Default langsung ke Dashboard biar begitu buka menu KPI langsung kelihatan gambaran
  // besarnya — kecuali datang dari notifikasi bell (highlight goal tertentu), itu harus ke tab Goals.
  const [activeTab, setActiveTab] = useState(highlight ? 'goals' : 'dashboard');

  function onRefresh() { router.reload({ only: ['goals'] }); }

  const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
    { key: 'goals', label: 'Goals', icon: Target },
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
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>
            {is_self_only ? (employees.length > 1 ? `${employees.length} orang (kamu & tim)` : 'Goals kamu') : `${employees.length} karyawan HO`}
          </span>
        </div>
      </div>

      {activeTab === 'goals' && (
        <TabGoals employees={employees} goals={goals} isViewer={isViewer} isSelfOnly={is_self_only} onRefresh={onRefresh} highlight={highlight} />
      )}
      {activeTab === 'dashboard' && (
        <TabDashboard goals={goals} employees={employees} />
      )}
    </AppLayout>
  );
}
