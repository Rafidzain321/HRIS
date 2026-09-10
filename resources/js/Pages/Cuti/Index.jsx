// resources/js/Pages/Cuti/Index.jsx
import React, { useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { usePage, router } from '@inertiajs/react';
import {
  CalendarDays, ClipboardCheck, Plus, Trash2, X, TriangleAlert, Loader2, Search, ChevronLeft, ChevronRight, ChevronDown,
} from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function fmtDate(iso) {
  if (!iso) return '-';
  const d = new Date(iso + 'T00:00:00');
  if (isNaN(d.getTime())) return iso;
  return `${d.getDate()} ${MONTH_ABBR[d.getMonth()]} ${d.getFullYear()}`;
}

// ── COMBOBOX KARYAWAN (searchable, ganti native <select> yang panjang) ──
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

// ── KALENDER KUSTOM (CSS grid, tanpa library) — tandai Minggu/hari libur
// (merah) dan tanggal cuti karyawan terpilih (biru). Klik angka tanggal buat
// tandai/hapus hari libur (kalau canEdit). ────────────────────
function MiniCalendar({ year, month, onMonthChange, holidaysByDate, leaveDatesSet, canEdit, onDayClick }) {
  const startWeekday = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < startWeekday; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  const navBtn = { width: 26, height: 26, borderRadius: 7, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' };

  return (
    <div style={{ ...card, padding: 14 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
        <button onClick={() => onMonthChange(month === 0 ? { year: year - 1, month: 11 } : { year, month: month - 1 })} style={navBtn}><ChevronLeft size={14} /></button>
        <div style={{ fontWeight: 700, fontSize: 13, fontFamily: 'Syne,sans-serif' }}>{MONTH_NAMES[month]} {year}</div>
        <button onClick={() => onMonthChange(month === 11 ? { year: year + 1, month: 0 } : { year, month: month + 1 })} style={navBtn}><ChevronRight size={14} /></button>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4, marginBottom: 4 }}>
        {DOW.map((d, i) => <div key={i} style={{ textAlign: 'center', fontSize: 10, color: i === 0 ? '#E04545' : 'var(--muted)', fontWeight: 700 }}>{d}</div>)}
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
        {cells.map((d, i) => {
          if (!d) return <div key={i} />;
          const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
          const isSunday = new Date(year, month, d).getDay() === 0;
          const holiday = holidaysByDate[iso];
          const isLeave = leaveDatesSet.has(iso);
          const clickable = canEdit && !isSunday;
          let bg = 'transparent', color = 'var(--text)';
          if (isSunday || holiday) { bg = 'rgba(224,69,69,.12)'; color = '#E04545'; }
          if (isLeave) { bg = 'rgba(58,143,224,.18)'; color = 'var(--blue)'; }
          return (
            <div key={i} title={holiday ? `${holiday.keterangan} — klik untuk hapus` : (clickable ? 'Klik untuk tandai hari libur' : '')}
              onClick={() => clickable && onDayClick(iso, holiday || null)}
              style={{
                aspectRatio: '1', display: 'flex', alignItems: 'center', justifyContent: 'center',
                borderRadius: 7, fontSize: 11.5, fontWeight: (isSunday || holiday || isLeave) ? 700 : 500,
                background: bg, color, cursor: clickable ? 'pointer' : (holiday ? 'help' : 'default'),
              }}>{d}</div>
          );
        })}
      </div>
      <div style={{ display: 'flex', gap: 14, marginTop: 12, fontSize: 10, color: 'var(--muted)', flexWrap: 'wrap' }}>
        <span style={{ display: 'flex', alignItems: 'center', gap: 5 }}><span style={{ width: 9, height: 9, borderRadius: 3, background: 'rgba(224,69,69,.5)' }} /> Minggu / Hari Libur</span>
        <span style={{ display: 'flex', alignItems: 'center', gap: 5 }}><span style={{ width: 9, height: 9, borderRadius: 3, background: 'var(--blue)' }} /> Cuti</span>
      </div>
    </div>
  );
}

// ── MODAL TAMBAH CUTI ────────────────────────────────────────
function AddLeaveModal({ employees, defaultEmployeeId, onClose }) {
  const [form, setForm] = useState({
    employee_id: defaultEmployeeId || employees[0]?.id || '',
    tanggal_mulai: '', tanggal_selesai: '', keterangan: '',
  });
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    router.post('/cuti', form, {
      preserveScroll: true,
      onSuccess: () => { setLoading(false); onClose(); },
      onError: (err) => { setLoading(false); setErrors(err); },
    });
  }

  return (
    <div style={{ position: 'fixed', inset: 0, zIndex: 400, background: 'rgba(0,0,0,.65)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 16 }}
      onMouseDown={e => { e.currentTarget.dataset.downOutside = e.target === e.currentTarget; }}
      onClick={e => { e.target === e.currentTarget && e.currentTarget.dataset.downOutside === 'true' && onClose(); }}>
      <div style={{ background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 16, width: 'min(420px,100%)', boxShadow: '0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 20px', borderBottom: '1px solid var(--border)' }}>
          <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><CalendarDays size={15} /> Catat Cuti</div>
          <div onClick={onClose} style={{ cursor: 'pointer', color: 'var(--muted)', display: 'flex' }}><X size={18} /></div>
        </div>
        <form onSubmit={submit}>
          <div style={{ padding: '18px 20px', display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Karyawan</label>
              <EmployeeCombobox employees={employees} value={Number(form.employee_id)} onChange={id => setForm(p => ({ ...p, employee_id: id }))} />
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Tanggal Mulai</label>
                <input type="date" style={{ ...inp, borderColor: errors.tanggal_mulai ? '#E04545' : 'var(--border)' }} value={form.tanggal_mulai} onChange={e => setForm(p => ({ ...p, tanggal_mulai: e.target.value }))} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Tanggal Selesai</label>
                <input type="date" style={{ ...inp, borderColor: errors.tanggal_selesai ? '#E04545' : 'var(--border)' }} value={form.tanggal_selesai} onChange={e => setForm(p => ({ ...p, tanggal_selesai: e.target.value }))} />
              </div>
            </div>
            {errors.tanggal_selesai && <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 10.5, color: '#E04545' }}><TriangleAlert size={12} />{errors.tanggal_selesai}</div>}
            <div>
              <label style={{ fontSize: 10.5, color: 'var(--muted)', marginBottom: 4, display: 'block' }}>Keterangan (opsional)</label>
              <input type="text" style={inp} value={form.keterangan} onChange={e => setForm(p => ({ ...p, keterangan: e.target.value }))} placeholder="cth: Acara keluarga" />
            </div>
            <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>Sabtu, Minggu, dan hari libur di dalam rentang tanggal ini otomatis tidak ikut dihitung.</div>
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
export default function CutiIndex({ employees = [], leaves = [], holidays = [], tahun, jatah }) {
  const { auth } = usePage().props;
  const canEdit = (auth?.user?.permissions || []).includes('edit-cuti');
  const canViewKehadiran = (auth?.user?.permissions || []).includes('view-kehadiran');
  const now = new Date();

  const [selectedId, setSelectedId] = useState(employees[0]?.id ?? null);
  const [calState, setCalState] = useState({ year: tahun, month: now.getFullYear() === tahun ? now.getMonth() : 0 });
  const [showAdd, setShowAdd] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(null);
  const [holidayModal, setHolidayModal] = useState(null); // { iso, existing }
  const [search, setSearch] = useState('');

  const holidaysByDate = {};
  holidays.forEach(h => { holidaysByDate[h.tanggal] = h; });

  const employeesById = {};
  employees.forEach(e => { employeesById[e.id] = e; });

  const usageByEmployee = {};
  leaves.forEach(l => { usageByEmployee[l.employee_id] = (usageByEmployee[l.employee_id] || 0) + l.jumlah_hari; });

  const searchLow = search.trim().toLowerCase();
  const filteredEmployees = employees.filter(e => !searchLow || e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow));

  const selected = employees.find(e => e.id === selectedId) || employees[0];
  const selectedLeaves = leaves.filter(l => l.employee_id === selected?.id);
  const leaveDatesSet = new Set();
  selectedLeaves.forEach(l => {
    for (let d = new Date(l.tanggal_mulai + 'T00:00:00'); d <= new Date(l.tanggal_selesai + 'T00:00:00'); d.setDate(d.getDate() + 1)) {
      leaveDatesSet.add(d.toISOString().slice(0, 10));
    }
  });

  function changeYear(y) { router.get('/cuti', { tahun: y }, { preserveState: false }); }
  function doDelete(leave) { router.delete(`/cuti/${leave.id}`, { preserveScroll: true, onSuccess: () => setConfirmDelete(null) }); }

  function confirmHoliday() {
    if (!holidayModal) return;
    if (holidayModal.existing) {
      router.delete(`/pengaturan/holidays/${holidayModal.existing.id}`, { preserveScroll: true, onFinish: () => setHolidayModal(null) });
    } else {
      router.post('/pengaturan/holidays', { tanggal: holidayModal.iso, keterangan: 'Libur', tipe: 'libur_khusus' }, { preserveScroll: true, onFinish: () => setHolidayModal(null) });
    }
  }

  const yearOptions = [];
  for (let y = now.getFullYear() - 2; y <= now.getFullYear() + 2; y++) yearOptions.push(y);

  return (
    <AppLayout title="Cuti & Kehadiran" subtitle="Head Office">
      <div style={{ display: 'flex', gap: 4, marginBottom: 12 }}>
        <div style={{ padding: '7px 4px', marginRight: 18, fontSize: 12.5, fontWeight: 600, color: 'var(--accent)', borderBottom: '2px solid var(--accent)', display: 'flex', alignItems: 'center', gap: 6, cursor: 'default' }}>
          <CalendarDays size={13} /> Cuti Tahunan
        </div>
        {canViewKehadiran && (
          <div onClick={() => router.visit('/kehadiran')} style={{ padding: '7px 4px', marginRight: 18, cursor: 'pointer', fontSize: 12.5, fontWeight: 600, color: 'var(--muted)', borderBottom: '2px solid transparent', display: 'flex', alignItems: 'center', gap: 6 }}>
            <ClipboardCheck size={13} /> Kehadiran
          </div>
        )}
      </div>

      {showAdd && <AddLeaveModal employees={employees} defaultEmployeeId={selected?.id} onClose={() => setShowAdd(false)} />}
      <ConfirmModal open={!!confirmDelete} onCancel={() => setConfirmDelete(null)}
        onConfirm={() => confirmDelete && doDelete(confirmDelete)}
        title="Hapus Catatan Cuti" message={confirmDelete ? <>Catatan cuti <b style={{ color: 'var(--text)' }}>{fmtDate(confirmDelete.tanggal_mulai)} – {fmtDate(confirmDelete.tanggal_selesai)}</b> akan dihapus.</> : ''}
        confirmLabel="Ya, Hapus" />
      <ConfirmModal open={!!holidayModal} onCancel={() => setHolidayModal(null)} onConfirm={confirmHoliday}
        title={holidayModal?.existing ? 'Hapus Hari Libur' : 'Tandai Hari Libur'}
        message={holidayModal ? (
          holidayModal.existing
            ? <>Hari libur <b style={{ color: 'var(--text)' }}>{holidayModal.existing.keterangan}</b> pada <b style={{ color: 'var(--text)' }}>{fmtDate(holidayModal.iso)}</b> akan dihapus.</>
            : <>Tandai <b style={{ color: 'var(--text)' }}>{fmtDate(holidayModal.iso)}</b> sebagai hari libur?</>
        ) : ''}
        confirmLabel={holidayModal?.existing ? 'Ya, Hapus' : 'Ya, Tandai'} />

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16, flexWrap: 'wrap', gap: 10 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <select value={tahun} onChange={e => changeYear(e.target.value)} style={{ ...inp, width: 'auto', minWidth: 100 }}>
            {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
          </select>
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Jatah {jatah} hari kerja / tahun / karyawan</span>
        </div>
        {canEdit && (
          <button onClick={() => setShowAdd(true)} style={{ padding: '8px 16px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}><Plus size={14} /> Catat Cuti</button>
        )}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 16, alignItems: 'start' }}>
        <div style={{ ...card, overflow: 'hidden' }}>
          <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--border)' }}>
            <div style={{ position: 'relative', maxWidth: 280 }}>
              <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
              <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
            </div>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
              <thead>
                <tr style={{ background: 'var(--bg3)' }}>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Karyawan</th>
                  <th style={{ textAlign: 'left', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Jabatan</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Terpakai</th>
                  <th style={{ textAlign: 'center', padding: '10px 14px', fontSize: 11, color: 'var(--muted)' }}>Sisa</th>
                </tr>
              </thead>
              <tbody>
                {filteredEmployees.length === 0 && (
                  <tr><td colSpan={4} style={{ padding: 24, textAlign: 'center', color: 'var(--muted)' }}>Tidak ada karyawan.</td></tr>
                )}
                {filteredEmployees.map(e => {
                  const terpakai = usageByEmployee[e.id] || 0;
                  const sisa = jatah - terpakai;
                  const active = e.id === selected?.id;
                  const sisaColor = sisa <= 0 ? '#E04545' : sisa <= 3 ? '#E8A020' : '#22C97A';
                  return (
                    <tr key={e.id} onClick={() => setSelectedId(e.id)} style={{ borderTop: '1px solid var(--border)', cursor: 'pointer', background: active ? 'rgba(232,160,32,.08)' : 'transparent' }}>
                      <td style={{ padding: '10px 14px', fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)' }}>{e.nama_lengkap}</td>
                      <td style={{ padding: '10px 14px', color: 'var(--muted2)' }}>{e.jabatan}</td>
                      <td style={{ padding: '10px 14px', textAlign: 'center' }}>{terpakai} hari</td>
                      <td style={{ padding: '10px 14px', textAlign: 'center', fontWeight: 700, color: sisaColor }}>{sisa} hari</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <MiniCalendar year={calState.year} month={calState.month} onMonthChange={setCalState} holidaysByDate={holidaysByDate} leaveDatesSet={leaveDatesSet}
            canEdit={canEdit} onDayClick={(iso, existing) => setHolidayModal({ iso, existing })} />

          <div style={{ ...card, padding: 14 }}>
            <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 8, textTransform: 'uppercase', letterSpacing: '.06em' }}>Riwayat Cuti · {tahun}</div>
            {leaves.length === 0 && <div style={{ fontSize: 12, color: 'var(--muted)' }}>Belum ada cuti tercatat.</div>}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, maxHeight: 420, overflowY: 'auto' }}>
              {leaves.map(l => {
                const owner = employeesById[l.employee_id];
                return (
                  <div key={l.id} style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8, padding: '8px 0', borderTop: '1px solid var(--border)' }}>
                    <div style={{ minWidth: 0 }}>
                      <div style={{ fontSize: 12, fontWeight: 700 }}>{owner?.nama_lengkap || '—'}</div>
                      <div style={{ fontSize: 11.5, color: 'var(--text)', marginTop: 2 }}>{fmtDate(l.tanggal_mulai)} – {fmtDate(l.tanggal_selesai)}</div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{l.jumlah_hari} hari kerja{l.keterangan ? ` · ${l.keterangan}` : ''}</div>
                    </div>
                    {canEdit && (
                      <button onClick={() => setConfirmDelete(l)} title="Hapus" style={{ padding: 5, borderRadius: 6, border: '1px solid rgba(224,69,69,.2)', background: 'rgba(224,69,69,.08)', color: '#E04545', cursor: 'pointer', display: 'flex', flexShrink: 0 }}><Trash2 size={12} /></button>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
