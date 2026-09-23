// resources/js/Pages/Konseling/Index.jsx
import React, { useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import {
  HeartHandshake, Plus, Trash2, X, Search, ChevronLeft, ChevronRight, ChevronDown, CheckCircle2, TriangleAlert, Loader2,
} from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const KATEGORI_META = {
  kinerja:  { label: 'Kinerja',  color: 'var(--blue)',  bg: 'rgba(58,143,224,.12)' },
  disiplin: { label: 'Disiplin', color: '#E8A020',      bg: 'rgba(232,160,32,.12)' },
  pribadi:  { label: 'Pribadi',  color: '#9B59B6',      bg: 'rgba(155,89,182,.12)' },
  karir:    { label: 'Karir',    color: '#22C97A',      bg: 'rgba(34,201,122,.12)' },
  lainnya:  { label: 'Lainnya',  color: 'var(--muted2)', bg: 'var(--bg3)' },
};

const STATUS_META = {
  selesai:             { label: 'Selesai',             color: '#22C97A', bg: 'rgba(34,201,122,.12)' },
  perlu_tindak_lanjut: { label: 'Perlu Tindak Lanjut',  color: '#E04545', bg: 'rgba(224,69,69,.12)' },
};

const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function fmtDate(iso) {
  if (!iso) return '-';
  const d = new Date(iso + 'T00:00:00');
  if (isNaN(d.getTime())) return iso;
  return `${d.getDate()} ${MONTH_ABBR[d.getMonth()]} ${d.getFullYear()}`;
}

// ── COMBOBOX KARYAWAN (searchable) ──
function EmployeeCombobox({ employees, value, onChange }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const selected = employees.find(e => e.id === value);
  const ql = query.trim().toLowerCase();
  const filtered = ql ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(ql) || e.jabatan.toLowerCase().includes(ql)) : employees;

  return (
    <div style={{ position: 'relative' }}>
      <button type="button" onClick={() => setOpen(o => !o)} style={{
        ...inp, textAlign: 'left', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer',
      }}>
        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{selected ? `${selected.nama_lengkap} — ${selected.jabatan}` : 'Pilih karyawan...'}</span>
        <ChevronDown size={14} style={{ color: 'var(--muted)', flexShrink: 0, transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 500 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 4px)', left: 0, right: 0, zIndex: 510, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', padding: 8 }}>
            <div style={{ position: 'relative', marginBottom: 6 }}>
              <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
              <input autoFocus type="text" value={query} onChange={e => setQuery(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
            </div>
            <div style={{ maxHeight: 280, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 2 }}>
              {filtered.length === 0 && <div style={{ padding: 10, fontSize: 12, color: 'var(--muted)', textAlign: 'center' }}>Tidak ditemukan.</div>}
              {filtered.map(e => (
                <div key={e.id} onClick={() => { onChange(e.id); setOpen(false); setQuery(''); }}
                  style={{
                    padding: '7px 9px', borderRadius: 7, cursor: 'pointer', fontSize: 12.5,
                    background: e.id === value ? 'rgba(232,160,32,.12)' : 'transparent',
                    color: e.id === value ? 'var(--accent)' : 'var(--text)',
                  }}>
                  <div style={{ fontWeight: 600 }}>{e.nama_lengkap}</div>
                  <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{e.jabatan}</div>
                </div>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

// ── CHIP PILIHAN (dipakai untuk Kategori & Status di form) ──
function ChipPicker({ options, value, onChange }) {
  return (
    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
      {options.map(opt => {
        const active = value === opt.key;
        return (
          <div key={opt.key} onClick={() => onChange(opt.key)}
            style={{
              padding: '6px 10px', borderRadius: 7, cursor: 'pointer', fontSize: 11, fontWeight: 600,
              border: `1px solid ${active ? opt.border : 'var(--border)'}`,
              background: active ? opt.bg : 'transparent',
              color: active ? opt.color : 'var(--muted)',
            }}>
            {opt.label}
          </div>
        );
      })}
    </div>
  );
}

// ── MODAL CATAT KONSELING ────────────────────────────────────
function AddCounselingModal({ employees, defaultEmployeeId, onClose }) {
  const [form, setForm] = useState({
    employee_id: defaultEmployeeId || employees[0]?.id || '',
    kategori: 'kinerja',
    tanggal_konseling: '',
    catatan: '',
    tindak_lanjut: '',
    status: 'selesai',
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    router.post('/konseling', form, {
      preserveScroll: true,
      onSuccess: () => { setLoading(false); onClose(); },
      onError: (err) => { setLoading(false); setErrors(err); },
    });
  }

  const kategoriOptions = Object.entries(KATEGORI_META).map(([key, m]) => ({ key, label: m.label, color: m.color, bg: m.bg, border: m.color }));
  const statusOptions = Object.entries(STATUS_META).map(([key, m]) => ({ key, label: m.label, color: m.color, bg: m.bg, border: m.color }));

  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 400, background: 'rgba(0,0,0,.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}
      onMouseDown={e => { e.currentTarget.dataset.downOutside = e.target === e.currentTarget; }}
      onClick={e => { e.target === e.currentTarget && e.currentTarget.dataset.downOutside === 'true' && onClose(); }}>
      <div style={{ background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 16, width: 'min(460px,100%)', maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 20px', borderBottom: '1px solid var(--border)' }}>
          <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><HeartHandshake size={15} /> Catat Konseling</div>
          <div onClick={onClose} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></div>
        </div>
        <form onSubmit={submit}>
          <div style={{ padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Karyawan</label>
              <EmployeeCombobox employees={employees} value={Number(form.employee_id)} onChange={id => setForm(p => ({ ...p, employee_id: id }))} />
              {errors.employee_id && <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 10.5, color: '#E04545', marginTop: 4 }}><TriangleAlert size={12} />{errors.employee_id}</div>}
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Kategori</label>
              <ChipPicker options={kategoriOptions} value={form.kategori} onChange={v => setForm(p => ({ ...p, kategori: v }))} />
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Tanggal Konseling</label>
              <input type="date" style={{ ...inp, borderColor: errors.tanggal_konseling ? '#E04545' : 'var(--border)' }} value={form.tanggal_konseling} onChange={e => setForm(p => ({ ...p, tanggal_konseling: e.target.value }))} />
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Catatan Sesi</label>
              <textarea style={{ ...inp, minHeight: 80, resize: 'vertical' }} value={form.catatan} onChange={e => setForm(p => ({ ...p, catatan: e.target.value }))} placeholder="Ringkasan pembicaraan / hal yang dibahas..." />
              {errors.catatan && <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 10.5, color: '#E04545', marginTop: 4 }}><TriangleAlert size={12} />{errors.catatan}</div>}
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Rencana Tindak Lanjut (opsional)</label>
              <textarea style={{ ...inp, minHeight: 56, resize: 'vertical' }} value={form.tindak_lanjut} onChange={e => setForm(p => ({ ...p, tindak_lanjut: e.target.value }))} placeholder="cth: Evaluasi ulang 2 minggu ke depan" />
            </div>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Status</label>
              <ChipPicker options={statusOptions} value={form.status} onChange={v => setForm(p => ({ ...p, status: v }))} />
            </div>
          </div>
          <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', padding: '12px 20px', borderTop: '1px solid var(--border)' }}>
            <button type="button" onClick={onClose} style={{ padding: '9px 18px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 12.5, cursor: 'pointer', fontFamily: "'Outfit',sans-serif" }}>Batal</button>
            <button type="submit" disabled={loading} style={{ padding: '9px 22px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12.5, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", opacity: loading ? .7 : 1 }}>
              {loading ? <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} /> : 'Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function KonselingIndex({ employees = [], sessions = [], can_edit = false }) {
  const [selectedId, setSelectedId] = useState(employees[0]?.id ?? null);
  const [showAdd, setShowAdd] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  const employeesById = {};
  employees.forEach(e => { employeesById[e.id] = e; });

  const sessionsByEmployee = {};
  sessions.forEach(s => { (sessionsByEmployee[s.employee_id] = sessionsByEmployee[s.employee_id] || []).push(s); });

  const searchLow = search.trim().toLowerCase();
  const filteredEmployees = employees.filter(e => !searchLow || e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow));

  const totalEmployees = filteredEmployees.length;
  const totalPages   = perPage === 'all' ? 1 : Math.max(1, Math.ceil(totalEmployees / perPage));
  const pageSafe     = Math.min(page, totalPages);
  const pagedEmployees = perPage === 'all' ? filteredEmployees : filteredEmployees.slice((pageSafe - 1) * perPage, pageSafe * perPage);
  const rangeStart   = totalEmployees === 0 ? 0 : (pageSafe - 1) * (perPage === 'all' ? totalEmployees : perPage) + 1;
  const rangeEnd     = perPage === 'all' ? totalEmployees : Math.min(pageSafe * perPage, totalEmployees);

  const selected = employees.find(e => e.id === selectedId) || employees[0];
  const selectedSessions = sessionsByEmployee[selected?.id] || [];

  function doDelete(s) { router.delete(`/konseling/${s.id}`, { preserveScroll: true, onSuccess: () => setConfirmDelete(null) }); }
  function doMarkSelesai(s) { router.put(`/konseling/${s.id}/selesai`, {}, { preserveScroll: true }); }

  return (
    <AppLayout title="Konseling" subtitle="Pembinaan Karyawan">
      {showAdd && <AddCounselingModal employees={employees} defaultEmployeeId={selected?.id} onClose={() => setShowAdd(false)} />}
      <ConfirmModal open={!!confirmDelete} onCancel={() => setConfirmDelete(null)}
        onConfirm={() => confirmDelete && doDelete(confirmDelete)}
        title="Hapus Catatan Konseling" message={confirmDelete ? <>Catatan konseling tanggal <b style={{ color: 'var(--text)' }}>{fmtDate(confirmDelete.tanggal_konseling)}</b> akan dihapus.</> : ''}
        confirmLabel="Ya, Hapus" />

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ fontSize: 11.5, color: 'var(--muted)' }}>Catatan pembinaan/percakapan suportif dengan karyawan — cuma bisa dilihat HR & atasan langsung yang bersangkutan.</div>
        {can_edit && (
          <button onClick={() => setShowAdd(true)} style={{ padding: '8px 16px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Plus size={14} /> Catat Konseling</button>
        )}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: 16, alignItems: 'start' }}>
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
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jumlah Sesi</th>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Sesi Terakhir</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Status</th>
                </tr>
              </thead>
              <tbody>
                {pagedEmployees.length === 0 && (
                  <tr><td colSpan={5} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada karyawan dalam cakupanmu.</td></tr>
                )}
                {pagedEmployees.map(e => {
                  const empSessions = (sessionsByEmployee[e.id] || []);
                  const last = empSessions[0]; // sudah urut terbaru dulu dari backend
                  const pending = empSessions.some(s => s.status === 'perlu_tindak_lanjut');
                  const active = e.id === selected?.id;
                  return (
                    <tr key={e.id} onClick={() => setSelectedId(e.id)} style={{ borderTop: '1px solid var(--border)', cursor: 'pointer', background: active ? 'rgba(232,160,32,.08)' : 'transparent' }}>
                      <td style={{ padding: '10px 14px', fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)' }}>{e.nama_lengkap}</td>
                      <td style={{ padding: '10px 14px', color: 'var(--muted2)' }}>{e.jabatan}</td>
                      <td style={{ padding: '10px 14px', textAlign: 'center' }}>{empSessions.length}</td>
                      <td style={{ padding: '10px 14px', color: 'var(--muted2)' }}>{last ? fmtDate(last.tanggal_konseling) : '—'}</td>
                      <td style={{ padding: '10px 14px', textAlign: 'center' }}>
                        {pending
                          ? <span style={{ fontSize: 10.5, fontWeight: 700, padding: '3px 9px', borderRadius: 99, background: STATUS_META.perlu_tindak_lanjut.bg, color: STATUS_META.perlu_tindak_lanjut.color }}>Perlu Tindak Lanjut</span>
                          : empSessions.length > 0 ? <span style={{ fontSize: 10.5, color: 'var(--muted)' }}>—</span> : <span style={{ fontSize: 10.5, color: 'var(--muted)' }}>Belum ada</span>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          {totalEmployees > 0 && (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', padding: '10px 14px', borderTop: '1px solid var(--border)' }}>
              <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Menampilkan {rangeStart}–{rangeEnd} dari {totalEmployees} karyawan</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={pageSafe <= 1} style={{ padding: '5px 10px', borderRadius: 7, border: '1px solid var(--border)', background: 'var(--bg3)', color: pageSafe <= 1 ? 'var(--muted)' : 'var(--text)', fontSize: 11.5, cursor: pageSafe <= 1 ? 'not-allowed' : 'pointer', fontFamily: "'Outfit',sans-serif" }}><ChevronLeft size={13} /></button>
                <span style={{ fontSize: 11.5, color: 'var(--muted2)' }}>Halaman {pageSafe} / {totalPages}</span>
                <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={pageSafe >= totalPages} style={{ padding: '5px 10px', borderRadius: 7, border: '1px solid var(--border)', background: 'var(--bg3)', color: pageSafe >= totalPages ? 'var(--muted)' : 'var(--text)', fontSize: 11.5, cursor: pageSafe >= totalPages ? 'not-allowed' : 'pointer', fontFamily: "'Outfit',sans-serif" }}><ChevronRight size={13} /></button>
              </div>
            </div>
          )}
        </div>

        <div style={{ ...card, padding: 14 }}>
          <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '.06em' }}>Riwayat Konseling · {selected?.nama_lengkap || '—'}</div>
          {selectedSessions.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>Belum ada catatan konseling.</div>}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, maxHeight: 560, overflowY: 'auto' }}>
            {selectedSessions.map(s => {
              const km = KATEGORI_META[s.kategori] || KATEGORI_META.lainnya;
              const sm = STATUS_META[s.status] || STATUS_META.selesai;
              return (
                <div key={s.id} style={{ padding: '10px 0', borderTop: '1px solid var(--border)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 6, marginBottom: 6 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                      <span style={{ fontSize: 9.5, fontWeight: 700, padding: '2px 8px', borderRadius: 99, background: km.bg, color: km.color }}>{km.label}</span>
                      <span style={{ fontSize: 9.5, fontWeight: 700, padding: '2px 8px', borderRadius: 99, background: sm.bg, color: sm.color }}>{sm.label}</span>
                    </div>
                    {can_edit && (
                      <button onClick={() => setConfirmDelete(s)} title="Hapus" style={{ padding: 4, borderRadius: 6, border: '1px solid rgba(224,69,69,.2)', background: 'rgba(224,69,69,.08)', color: '#E04545', cursor: 'pointer', display: 'flex', flexShrink: 0 }}><Trash2 size={11} /></button>
                    )}
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 4 }}>{fmtDate(s.tanggal_konseling)} · oleh {s.ditangani_oleh || '—'}</div>
                  <div style={{ fontSize: 12, color: 'var(--text)', lineHeight: 1.5 }}>{s.catatan}</div>
                  {s.tindak_lanjut && (
                    <div style={{ fontSize: 11.5, color: 'var(--muted2)', marginTop: 6, padding: '6px 8px', borderRadius: 7, background: 'var(--bg3)' }}>
                      <span style={{ fontWeight: 700 }}>Tindak lanjut:</span> {s.tindak_lanjut}
                    </div>
                  )}
                  {s.status === 'perlu_tindak_lanjut' && can_edit && (
                    <button onClick={() => doMarkSelesai(s)}
                      style={{ marginTop: 8, padding: '5px 10px', borderRadius: 7, border: '1px solid rgba(34,201,122,.3)', background: 'rgba(34,201,122,.1)', color: '#22C97A', fontSize: 11, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                      <CheckCircle2 size={12} /> Tandai Selesai
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
