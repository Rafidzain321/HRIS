// resources/js/Pages/Kpi/Index.jsx
import React, { useState, useEffect } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
  Target, Search, BarChart3, ClipboardList, ChevronLeft, ChevronRight,
  Download, ChevronDown, Users, Network, ListChecks, Eye, Pencil, FilePlus2, Trash2,
} from 'lucide-react';

function csrfHeaders() {
  const token = document.querySelector('meta[name=csrf-token]')?.content;
  return { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' };
}

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const STATUS_META = {
  belum_dinilai: { label: 'Belum Dinilai', color: '#8A8F98', bg: 'rgba(138,143,152,.12)' },
  draft:         { label: 'Draft',         color: '#E8A020', bg: 'rgba(232,160,32,.12)' },
  submitted:     { label: 'Final',         color: '#22C97A', bg: 'rgba(34,201,122,.12)' },
};

const PREDIKAT_META = {
  K:  { label: 'Kurang',        color: '#E04545' },
  C:  { label: 'Cukup',         color: '#E8A020' },
  B:  { label: 'Baik',          color: '#22C97A' },
  BS: { label: 'Baik Sekali',   color: '#3A8FE0' },
  A:  { label: 'Memuaskan',     color: '#9B59B6' },
};

const MONTH_SEMESTER_HINT = (semester) => (semester === 1 ? 'Jan – Jun' : 'Jul – Des');

// ── DONUT CHART (CSS conic-gradient) ────────────────────────
function DonutChart({ counts, colorMap, size = 130 }) {
  const total = Object.values(counts).reduce((a, b) => a + b, 0) || 1;
  let acc = 0;
  const segments = Object.entries(counts).filter(([, v]) => v > 0).map(([key, v]) => {
    const start = acc / total * 360;
    acc += v;
    const end = acc / total * 360;
    return `${colorMap[key]?.color || '#8A8F98'} ${start}deg ${end}deg`;
  });
  const gradient = segments.length ? `conic-gradient(${segments.join(',')})` : 'conic-gradient(var(--border) 0deg 360deg)';
  return (
    <div style={{ position: 'relative', width: size, height: size, borderRadius: '50%', background: gradient, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
      <div style={{ width: size * 0.62, height: size * 0.62, borderRadius: '50%', background: 'var(--card)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column' }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 20, fontWeight: 700 }}>{total}</div>
        <div style={{ fontSize: 9.5, color: 'var(--muted)' }}>karyawan</div>
      </div>
    </div>
  );
}

// ── TOMBOL IKON AKSI TABEL (dengan tooltip native) ──
function IconBtn({ icon, title, onClick, color = 'var(--muted2)', borderColor, hoverBg, disabled }) {
  const [hover, setHover] = useState(false);
  return (
    <button type="button" onClick={onClick} disabled={disabled} title={title}
      onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)}
      style={{
        width: 28, height: 28, borderRadius: 7, cursor: disabled ? 'default' : 'pointer',
        border: `1px solid ${borderColor || 'var(--border)'}`,
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        background: hover && !disabled ? (hoverBg || 'var(--bg3)') : 'var(--card)',
        color, opacity: disabled ? 0.4 : 1, transition: 'background .12s',
      }}>
      {icon}
    </button>
  );
}

function StatTile({ label, value }) {
  return (
    <div style={{ padding: '8px 0', borderBottom: '1px solid var(--border)' }}>
      <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 22, fontWeight: 700 }}>{value}</div>
      <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{label}</div>
    </div>
  );
}

// ── TOMBOL EXPORT EXCEL (semua karyawan / satu karyawan tertentu) ──
function ExportMenu({ employees, tahun, semester }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const searchLow = search.trim().toLowerCase();
  const list = (searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : employees
  ).slice().sort((a, b) => a.nama_lengkap.localeCompare(b.nama_lengkap));

  const baseHref = `/kpi/export?tahun=${tahun}&semester=${semester}`;

  return (
    <div style={{ position: 'relative' }}>
      <button onClick={() => setOpen(o => !o)} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.08)', color: 'var(--blue)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}>
        <Download size={14} /> Export Excel <ChevronDown size={12} style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 60 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 6px)', right: 0, zIndex: 70, width: 280, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', overflow: 'hidden' }}>
            <a href={baseHref} onClick={() => setOpen(false)}
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
                  <a key={e.id} href={`${baseHref}&employee_id=${e.id}`} onClick={() => setOpen(false)}
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

// ── PERIOD SELECTOR (Tahun + Semester) ──────────────────────
function PeriodSelector({ tahun, semester }) {
  const now = new Date();
  const yearOptions = [];
  for (let y = now.getFullYear() - 2; y <= now.getFullYear() + 1; y++) yearOptions.push(y);

  function change(newTahun, newSemester) {
    router.get('/kpi', { tahun: newTahun, semester: newSemester }, { preserveState: false });
  }

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <select value={tahun} onChange={e => change(e.target.value, semester)} style={{ ...inp, width: 'auto' }}>
        {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
      </select>
      <select value={semester} onChange={e => change(tahun, e.target.value)} style={{ ...inp, width: 'auto' }}>
        <option value={1}>Semester 1 (Jan–Jun)</option>
        <option value={2}>Semester 2 (Jul–Des)</option>
      </select>
    </div>
  );
}

// ── TABEL PENILAIAN (dipakai di tab Penilaian & Dashboard) ──
function AppraisalTable({ rows, tahun, semester, showActions, isViewer }) {
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [confirmDiscard, setConfirmDiscard] = useState(null);
  const [discarding, setDiscarding] = useState(false);

  function discardDraft() {
    const r = confirmDiscard;
    setDiscarding(true);
    axios.delete(`/kpi/appraisals/${r.appraisal_id}`, { headers: csrfHeaders() })
      .then(() => { setConfirmDiscard(null); router.reload({ only: ['rows'] }); })
      .catch(err => alert(err.response?.data?.message || 'Gagal membuang draft.'))
      .finally(() => setDiscarding(false));
  }

  const searchLow = search.trim().toLowerCase();
  const filtered = rows.filter(r => {
    if (statusFilter && r.status !== statusFilter) return false;
    if (searchLow && !r.nama_lengkap.toLowerCase().includes(searchLow) && !r.jabatan.toLowerCase().includes(searchLow)) return false;
    return true;
  });

  const totalRows  = filtered.length;
  const totalPages = perPage === 'all' ? 1 : Math.max(1, Math.ceil(totalRows / perPage));
  const pageSafe   = Math.min(page, totalPages);
  const paged      = perPage === 'all' ? filtered : filtered.slice((pageSafe - 1) * perPage, pageSafe * perPage);
  const rangeStart = totalRows === 0 ? 0 : (pageSafe - 1) * (perPage === 'all' ? totalRows : perPage) + 1;
  const rangeEnd   = perPage === 'all' ? totalRows : Math.min(pageSafe * perPage, totalRows);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <div style={{ position: 'relative', maxWidth: 260 }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30, width: 220 }} />
          </div>
          <select value={statusFilter} onChange={e => { setStatusFilter(e.target.value); setPage(1); }} style={{ ...inp, width: 'auto', minWidth: 140 }}>
            <option value="">Semua Status</option>
            {Object.entries(STATUS_META).map(([key, meta]) => <option key={key} value={key}>{meta.label}</option>)}
          </select>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Tampilkan</span>
          <select value={perPage} onChange={e => { setPerPage(e.target.value === 'all' ? 'all' : Number(e.target.value)); setPage(1); }} style={{ ...inp, width: 'auto' }}>
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
                <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Nilai A (40%)</th>
                <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Nilai B (60%)</th>
                <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Total</th>
                <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Predikat</th>
                <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Status</th>
                {showActions && <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {paged.length === 0 && (
                <tr><td colSpan={showActions ? 8 : 7} style={{ padding: 28, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada data.</td></tr>
              )}
              {paged.map(r => {
                const statusMeta = STATUS_META[r.status] || STATUS_META.belum_dinilai;
                const predikatMeta = r.predikat ? PREDIKAT_META[r.predikat] : null;
                return (
                  <tr key={r.employee_id} data-id={r.employee_id} style={{ borderTop: '1px solid var(--border)' }}>
                    <td style={{ padding: '10px 14px', fontWeight: 600 }}>{r.nama_lengkap}</td>
                    <td style={{ padding: '10px 14px', color: 'var(--muted2)' }}>{r.jabatan}</td>
                    <td style={{ padding: '10px 14px', textAlign: 'center' }}>{r.nilai_a ?? '—'}</td>
                    <td style={{ padding: '10px 14px', textAlign: 'center' }}>{r.nilai_b ?? '—'}</td>
                    <td style={{ padding: '10px 14px', textAlign: 'center', fontWeight: 700 }}>{r.total_nilai ?? '—'}</td>
                    <td style={{ padding: '10px 14px', textAlign: 'center' }}>
                      {predikatMeta ? (
                        <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 99, background: `${predikatMeta.color}22`, color: predikatMeta.color }}>{r.predikat} · {predikatMeta.label}</span>
                      ) : <span style={{ color: 'var(--muted)' }}>—</span>}
                    </td>
                    <td style={{ padding: '10px 14px', textAlign: 'center' }}>
                      <span style={{ fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 99, background: statusMeta.bg, color: statusMeta.color }}>{statusMeta.label}</span>
                    </td>
                    {showActions && (
                      <td style={{ padding: '6px 14px' }}>
                        {!isViewer && (
                          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
                            <IconBtn
                              onClick={() => router.visit(`/kpi/appraisals/${r.employee_id}?tahun=${tahun}&semester=${semester}`)}
                              color="var(--accent)" borderColor="rgba(232,160,32,.3)" hoverBg="rgba(232,160,32,.12)"
                              title={r.status === 'belum_dinilai' ? 'Isi Penilaian' : (r.status === 'submitted' ? 'Lihat' : 'Lanjutkan / Edit')}
                              icon={r.status === 'belum_dinilai' ? <FilePlus2 size={14} /> : r.status === 'submitted' ? <Eye size={14} /> : <Pencil size={14} />}
                            />
                            {r.status === 'draft' && (
                              <IconBtn
                                onClick={() => setConfirmDiscard(r)}
                                color="#E04545" borderColor="rgba(224,69,69,.3)" hoverBg="rgba(224,69,69,.12)"
                                title="Buang Draft" icon={<Trash2 size={13} />}
                              />
                            )}
                          </div>
                        )}
                      </td>
                    )}
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

      <ConfirmModal
        open={!!confirmDiscard}
        onCancel={() => setConfirmDiscard(null)}
        onConfirm={discardDraft}
        title="Buang Draft"
        message={confirmDiscard ? `Buang draft penilaian ${confirmDiscard.nama_lengkap}? Semua skor yang sudah diisi akan hilang dan status kembali ke "Belum Dinilai".` : ''}
        confirmLabel={discarding ? 'Membuang...' : 'Ya, Buang'}
        type="danger"
      />
    </div>
  );
}

// ── TAB DASHBOARD ────────────────────────────────────────────
function TabDashboard({ rows, tahun, semester }) {
  const total = rows.length;
  const withScore = rows.filter(r => r.total_nilai !== null);
  const avgTotal = withScore.length > 0 ? Math.round((withScore.reduce((a, r) => a + r.total_nilai, 0) / withScore.length) * 100) / 100 : 0;
  const submittedCount = rows.filter(r => r.status === 'submitted').length;

  const statusCounts = rows.reduce((acc, r) => { acc[r.status] = (acc[r.status] || 0) + 1; return acc; }, { belum_dinilai: 0, draft: 0, submitted: 0 });
  const predikatCounts = rows.reduce((acc, r) => { if (r.predikat) acc[r.predikat] = (acc[r.predikat] || 0) + 1; return acc; }, { K: 0, C: 0, B: 0, BS: 0, A: 0 });

  return (
    <div className="kpi-dash-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 16, alignItems: 'start' }}>
      <div style={{ minWidth: 0 }}>
        <AppraisalTable rows={rows} tahun={tahun} semester={semester} showActions={false} />
      </div>

      <div style={{ ...card, padding: 16, display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><BarChart3 size={14} /> Ringkasan Semester</div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <StatTile label="Total Karyawan" value={total} />
          <StatTile label="Sudah Final" value={`${submittedCount} / ${total}`} />
          <StatTile label="Rata-rata Total Nilai" value={avgTotal || '—'} />
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12, paddingTop: 4 }}>
          <DonutChart counts={statusCounts} colorMap={STATUS_META} size={130} />
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, width: '100%' }}>
            <div style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600 }}>Status Penilaian</div>
            {Object.entries(STATUS_META).map(([key, meta]) => {
              const count = statusCounts[key] || 0;
              const pct = total > 0 ? Math.round(count / total * 100) : 0;
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

        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, paddingTop: 8, borderTop: '1px solid var(--border)' }}>
          <div style={{ fontSize: 11, color: 'var(--muted)', fontWeight: 600 }}>Distribusi Predikat ({withScore.length} sudah dinilai)</div>
          {Object.entries(PREDIKAT_META).map(([key, meta]) => {
            const count = predikatCounts[key] || 0;
            const pct = withScore.length > 0 ? Math.round(count / withScore.length * 100) : 0;
            return (
              <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{ width: 10, height: 10, borderRadius: 3, background: meta.color, flexShrink: 0 }} />
                <div style={{ fontSize: 12, flex: 1 }}>{key} · {meta.label}</div>
                <div style={{ fontSize: 12, fontWeight: 700 }}>{count}</div>
                <div style={{ fontSize: 10.5, color: 'var(--muted)', width: 36, textAlign: 'right' }}>{pct}%</div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function KpiIndex({ rows = [], tahun, semester, is_self_only = false, all_employees = [], highlight = null }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [activeTab, setActiveTab] = useState('dashboard');

  useEffect(() => {
    if (!highlight) return;
    const timer = setTimeout(() => {
      setActiveTab('penilaian');
      setTimeout(() => {
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
      }, 150);
    }, 50);
    return () => clearTimeout(timer);
  }, [highlight]);

  const tabs = [
    { key: 'dashboard', label: 'Dashboard', icon: BarChart3 },
    { key: 'penilaian', label: 'Penilaian', icon: ListChecks },
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
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          <PeriodSelector tahun={tahun} semester={semester} />
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>
            {is_self_only ? (rows.length > 1 ? `${rows.length} orang (kamu & tim)` : 'Penilaian kamu') : `${rows.length} karyawan HO`}
          </span>
        </div>
      </div>

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 8, marginBottom: 14, flexWrap: 'wrap' }}>
        {!isViewer && !is_self_only && (
          <>
            <button onClick={() => router.visit('/pengaturan/struktur-organisasi')} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--muted2)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Network size={14} /> Struktur Organisasi</button>
            <button onClick={() => router.visit('/kpi/kriteria')} style={{ padding: '8px 16px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--muted2)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><ClipboardList size={14} /> Kelola Kriteria</button>
          </>
        )}
        <ExportMenu employees={all_employees} tahun={tahun} semester={semester} />
      </div>

      <div style={{ marginBottom: 10, fontSize: 11.5, color: 'var(--muted)' }}>
        Periode: Semester {semester} {tahun} ({MONTH_SEMESTER_HINT(semester)})
      </div>

      {activeTab === 'dashboard' && <TabDashboard rows={rows} tahun={tahun} semester={semester} />}
      {activeTab === 'penilaian' && <AppraisalTable rows={rows} tahun={tahun} semester={semester} showActions isViewer={isViewer} />}
    </AppLayout>
  );
}
