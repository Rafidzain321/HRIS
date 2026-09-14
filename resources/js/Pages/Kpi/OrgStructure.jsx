// resources/js/Pages/Kpi/OrgStructure.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, Link } from '@inertiajs/react';
import { Network, Search, ArrowLeft, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

// ── SATU BARIS: dropdown atasan langsung, auto-save begitu dipilih ──
function AtasanRow({ row, employees, onSaved }) {
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  function change(e) {
    const val = e.target.value || null;
    setSaving(true); setError('');
    router.put(`/pengaturan/struktur-organisasi/${row.id}`, { atasan_id: val }, {
      preserveScroll: true, preserveState: true,
      onSuccess: () => { setSaving(false); onSaved(row.id, val); },
      onError: (err) => { setSaving(false); setError(err.atasan_id || 'Gagal menyimpan.'); },
    });
  }

  return (
    <tr style={{ borderTop: '1px solid var(--border)' }}>
      <td style={{ padding: '10px 14px', fontWeight: 600 }}>{row.nama_lengkap}</td>
      <td style={{ padding: '10px 14px', color: 'var(--muted2)' }}>{row.jabatan}</td>
      <td style={{ padding: '8px 14px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <select value={row.atasan_id ?? ''} onChange={change} disabled={saving} style={{ ...inp, opacity: saving ? .6 : 1 }}>
            <option value="">— Tidak ada (paling atas) —</option>
            {employees.filter(e => e.id !== row.id).map(e => (
              <option key={e.id} value={e.id}>{e.nama_lengkap} — {e.jabatan}</option>
            ))}
          </select>
          {saving && <Loader2 size={14} style={{ animation: 'spin .8s linear infinite', color: 'var(--muted)', flexShrink: 0 }} />}
        </div>
        {error && <div style={{ fontSize: 10.5, color: '#E04545', marginTop: 4 }}>{error}</div>}
      </td>
    </tr>
  );
}

export default function OrgStructure({ employees = [] }) {
  const [rows, setRows] = useState(employees);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  function handleSaved(id, atasanId) {
    setRows(prev => prev.map(r => r.id === id ? { ...r, atasan_id: atasanId ? Number(atasanId) : null } : r));
  }

  const searchLow = search.trim().toLowerCase();
  const filtered = rows.filter(r => !searchLow || r.nama_lengkap.toLowerCase().includes(searchLow) || r.jabatan.toLowerCase().includes(searchLow));

  const totalRows  = filtered.length;
  const totalPages = perPage === 'all' ? 1 : Math.max(1, Math.ceil(totalRows / perPage));
  const pageSafe   = Math.min(page, totalPages);
  const paged      = perPage === 'all' ? filtered : filtered.slice((pageSafe - 1) * perPage, pageSafe * perPage);
  const rangeStart = totalRows === 0 ? 0 : (pageSafe - 1) * (perPage === 'all' ? totalRows : perPage) + 1;
  const rangeEnd   = perPage === 'all' ? totalRows : Math.min(pageSafe * perPage, totalRows);

  return (
    <AppLayout title="Struktur" subtitle="Organisasi">
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
        <Link href="/kpi" style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--muted2)', textDecoration: 'none', fontSize: 12.5 }}>
          <ArrowLeft size={14} /> Kembali ke KPI
        </Link>
      </div>

      <div style={{ ...card, padding: 16, marginBottom: 16, display: 'flex', alignItems: 'center', gap: 10 }}>
        <Network size={16} style={{ color: 'var(--accent)', flexShrink: 0 }} />
        <div style={{ fontSize: 12.5, color: 'var(--muted2)', lineHeight: 1.5 }}>
          Atur siapa atasan langsung setiap karyawan HO di sini. Perubahan langsung berlaku ke hierarki notifikasi & cakupan lihat/edit KPI — tidak perlu ubah data lewat cara lain lagi.
        </div>
      </div>

      <div style={{ ...card, overflow: 'hidden' }}>
        <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--border)', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
          <div style={{ position: 'relative', maxWidth: 280, flex: 1 }}>
            <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
            <input type="text" value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Tampilkan</span>
            <select value={perPage} onChange={e => { setPerPage(e.target.value === 'all' ? 'all' : Number(e.target.value)); setPage(1); }} style={{ ...inp, width: 'auto' }}>
              <option value={10}>10</option>
              <option value={15}>15</option>
              <option value={25}>25</option>
              <option value={50}>50</option>
              <option value="all">Semua</option>
            </select>
          </div>
        </div>

        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
            <thead>
              <tr style={{ background: 'var(--bg3)' }}>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
                <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)', minWidth: 260 }}>Atasan Langsung</th>
              </tr>
            </thead>
            <tbody>
              {paged.length === 0 && (
                <tr><td colSpan={3} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada karyawan.</td></tr>
              )}
              {paged.map(row => (
                <AtasanRow key={row.id} row={row} employees={rows} onSaved={handleSaved} />
              ))}
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
    </AppLayout>
  );
}
