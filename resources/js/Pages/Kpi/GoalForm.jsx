// resources/js/Pages/Kpi/GoalForm.jsx
import React, { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { Target, TriangleAlert, Loader2, ChevronRight, ChevronLeft, ChevronDown, ArrowLeft, Search, Calendar as CalendarIcon } from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '9px 12px', fontSize: 13,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const CYCLE_OPTIONS = [
  { key: 'custom', label: 'Custom' },
  { key: 'monthly', label: 'Monthly' },
  { key: 'half_yearly', label: 'Half-yearly' },
  { key: 'yearly', label: 'Yearly' },
];
const CYCLE_MONTHS = { monthly: 1, half_yearly: 6, yearly: 12 };
const SATUAN_OPTIONS = [
  { key: 'percentage', label: 'Percentage (%)' },
  { key: 'number', label: 'Number' },
  { key: 'rupiah', label: 'Rupiah (Rp)' },
];

function todayStr() { return new Date().toISOString().slice(0, 10); }
function addMonths(dateStr, n) {
  const d = new Date(dateStr);
  d.setMonth(d.getMonth() + n);
  return d.toISOString().slice(0, 10);
}

const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const DOW = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

function fmtDateShort(iso) {
  if (!iso) return '';
  const d = new Date(iso + 'T00:00:00');
  if (isNaN(d.getTime())) return '';
  return `${d.getDate()} ${MONTH_ABBR[d.getMonth()]} ${d.getFullYear()}`;
}

// ── DATE PICKER KUSTOM (tampilkan "7 Sep 2026", bukan format tanggal
// bawaan browser) — kalender kecil, klik tanggal buat pilih. ──
function DatePicker({ value, onChange }) {
  const [open, setOpen] = useState(false);
  const base = value ? new Date(value + 'T00:00:00') : new Date();
  const [view, setView] = useState({ year: base.getFullYear(), month: base.getMonth() });

  function toggle() {
    if (!open) {
      const b = value ? new Date(value + 'T00:00:00') : new Date();
      setView({ year: b.getFullYear(), month: b.getMonth() });
    }
    setOpen(o => !o);
  }

  function pick(d) {
    onChange(`${view.year}-${String(view.month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
    setOpen(false);
  }

  const startWeekday = new Date(view.year, view.month, 1).getDay();
  const daysInMonth = new Date(view.year, view.month + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < startWeekday; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);
  const navBtn = { width: 24, height: 24, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 };
  const yearSelect = { background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)', borderRadius: 6, padding: '3px 4px', fontSize: 11.5, fontFamily: "'Outfit',sans-serif", outline: 'none', cursor: 'pointer' };
  const nowYear = new Date().getFullYear();
  const yearOptions = [];
  for (let y = nowYear - 5; y <= nowYear + 10; y++) yearOptions.push(y);

  return (
    <div style={{ position: 'relative' }}>
      <button type="button" onClick={toggle} style={{ ...inp, textAlign: 'left', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer' }}>
        <span style={{ color: value ? 'var(--text)' : 'var(--muted)' }}>{value ? fmtDateShort(value) : 'Pilih tanggal'}</span>
        <CalendarIcon size={14} style={{ color: 'var(--muted)', flexShrink: 0 }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 500 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 6px)', left: 0, zIndex: 510, width: 280, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 12, boxShadow: '0 12px 36px rgba(0,0,0,.35)', padding: 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 4, marginBottom: 10 }}>
              <button type="button" onClick={() => setView(v => v.month === 0 ? { year: v.year - 1, month: 11 } : { year: v.year, month: v.month - 1 })} style={navBtn}><ChevronLeft size={13} /></button>
              <select value={view.month} onChange={e => setView(v => ({ ...v, month: Number(e.target.value) }))} style={{ ...yearSelect, flex: 1 }}>
                {MONTH_NAMES.map((m, i) => <option key={i} value={i}>{m}</option>)}
              </select>
              <select value={view.year} onChange={e => setView(v => ({ ...v, year: Number(e.target.value) }))} style={yearSelect}>
                {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
              </select>
              <button type="button" onClick={() => setView(v => v.month === 11 ? { year: v.year + 1, month: 0 } : { year: v.year, month: v.month + 1 })} style={navBtn}><ChevronRight size={13} /></button>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 3, marginBottom: 3 }}>
              {DOW.map((d, i) => <div key={i} style={{ textAlign: 'center', fontSize: 9.5, color: i === 0 ? '#E04545' : 'var(--muted)', fontWeight: 700 }}>{d}</div>)}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 3 }}>
              {cells.map((d, i) => {
                if (!d) return <div key={i} />;
                const isSunday = new Date(view.year, view.month, d).getDay() === 0;
                const iso = `${view.year}-${String(view.month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const isSelected = value === iso;
                return (
                  <div key={i} onClick={() => pick(d)} style={{
                    aspectRatio: '1', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    borderRadius: 6, fontSize: 11, cursor: 'pointer', fontWeight: isSelected ? 700 : 500,
                    background: isSelected ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'transparent',
                    color: isSelected ? '#0C0F14' : isSunday ? '#E04545' : 'var(--text)',
                  }}>{d}</div>
                );
              })}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

// Susun daftar karyawan jadi berjenjang (atasan -> bawahan) buat tampilan pilih "Goal owner".
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

// ── COMBOBOX GOAL OWNER (searchable, tetap tampilkan hierarki atasan-bawahan
// di dalam daftar dropdown-nya) ─────────────────────────────
function GoalOwnerField({ employees, hierarchy, value, onChange, disabled }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const selected = employees.find(e => String(e.id) === String(value));
  const searchLow = search.trim().toLowerCase();
  const list = searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow)).map(e => ({ ...e, depth: 0 }))
    : hierarchy;

  if (disabled) {
    return (
      <div style={{ ...inp, background: 'var(--bg3)', cursor: 'default' }}>
        <div style={{ fontWeight: 600 }}>{selected?.nama_lengkap || '-'}</div>
        <div style={{ fontSize: 11, color: 'var(--muted)' }}>{selected?.jabatan}</div>
      </div>
    );
  }

  return (
    <div style={{ position: 'relative' }}>
      <button type="button" onClick={() => setOpen(o => !o)} style={{ ...inp, textAlign: 'left', display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer' }}>
        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: selected ? 'var(--text)' : 'var(--muted)' }}>
          {selected ? `${selected.nama_lengkap} — ${selected.jabatan}` : 'Pilih goal owner...'}
        </span>
        <ChevronDown size={14} style={{ color: 'var(--muted)', flexShrink: 0, transform: open ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
      </button>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 500 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 6px)', left: 0, right: 0, zIndex: 510, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', padding: 8 }}>
            <div style={{ position: 'relative', marginBottom: 6 }}>
              <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={13} /></span>
              <input autoFocus type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 30 }} />
            </div>
            <div style={{ maxHeight: 320, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: 2 }}>
              {list.length === 0 && <div style={{ padding: 10, fontSize: 12, color: 'var(--muted)', textAlign: 'center' }}>Tidak ditemukan.</div>}
              {list.map(e => {
                const active = String(e.id) === String(value);
                return (
                  <div key={e.id} onClick={() => { onChange(e.id); setOpen(false); setSearch(''); }}
                    style={{
                      padding: '7px 9px', paddingLeft: 9 + (e.depth || 0) * 16, borderRadius: 7, cursor: 'pointer',
                      display: 'flex', alignItems: 'center', gap: 4,
                      background: active ? 'rgba(232,160,32,.12)' : 'transparent',
                    }}>
                    {e.depth > 0 && <ChevronRight size={11} style={{ color: 'var(--muted)', flexShrink: 0 }} />}
                    <div style={{ minWidth: 0 }}>
                      <div style={{ fontSize: 12.5, fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)' }}>{e.nama_lengkap}</div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{e.jabatan}</div>
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

export default function GoalForm({ mode, goal, employees = [], is_self_only = false, default_employee_id = null }) {
  const hierarchy = useMemo(() => buildHierarchy(employees), [employees]);

  const { data, setData, post, put, processing, errors } = useForm({
    employee_id: goal?.employee_id ?? default_employee_id ?? employees[0]?.id ?? '',
    nama_goal: goal?.nama_goal ?? '',
    deskripsi: goal?.deskripsi ?? '',
    siklus: goal?.siklus ?? 'custom',
    tanggal_mulai: goal?.tanggal_mulai ?? todayStr(),
    tanggal_selesai: goal?.tanggal_selesai ?? '',
    satuan: goal?.satuan ?? 'percentage',
    baseline: goal?.baseline ?? 0,
    target: goal?.target ?? 100,
    bobot: goal?.bobot ?? '',
  });

  function setCycle(key) {
    setData(d => ({
      ...d, siklus: key,
      tanggal_selesai: CYCLE_MONTHS[key] ? addMonths(d.tanggal_mulai, CYCLE_MONTHS[key]) : d.tanggal_selesai,
    }));
  }

  // Ganti tanggal mulai — kalau siklusnya monthly/quarterly/half-yearly/yearly (bukan custom),
  // due date ikut dihitung ulang otomatis biar tetap konsisten sama siklusnya.
  function setStartDate(value) {
    setData(d => ({
      ...d, tanggal_mulai: value,
      tanggal_selesai: CYCLE_MONTHS[d.siklus] ? addMonths(value, CYCLE_MONTHS[d.siklus]) : d.tanggal_selesai,
    }));
  }

  function submit(e) {
    e.preventDefault();
    if (mode === 'add') post('/kpi/goals');
    else put(`/kpi/goals/${goal.id}`);
  }

  const selectedEmployee = employees.find(e => String(e.id) === String(data.employee_id));

  return (
    <AppLayout title="KPI" subtitle={mode === 'add' ? 'Tambah Goal' : 'Edit Goal'}>
      <div style={{ maxWidth: 900 }}>
        <Link href="/kpi" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12.5, color: 'var(--muted)', textDecoration: 'none', marginBottom: 14 }}>
          <ArrowLeft size={14} /> Kembali ke KPI
        </Link>

        <div style={{ marginBottom: 18 }}>
          <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 20, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}>
            <Target size={19} /> {mode === 'add' ? 'Tambah Goal' : 'Edit Goal'}
          </div>
          <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 3 }}>Selaraskan pencapaian kerja dengan target individu atau tim.</div>
        </div>

        <form onSubmit={submit} style={{ maxWidth: 620 }}>
          <div style={{ ...card, padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>
                Goal Owner {is_self_only ? '(Saya & Tim)' : '(Karyawan HO)'}
              </label>
              {employees.length > 1 ? (
                <GoalOwnerField employees={employees} hierarchy={hierarchy} value={data.employee_id}
                  onChange={id => setData('employee_id', id)} disabled={mode === 'edit'} />
              ) : (
                selectedEmployee && <div style={{ fontSize: 12.5, color: 'var(--muted)' }}>Goal owner: <b style={{ color: 'var(--text)' }}>{selectedEmployee.nama_lengkap}</b></div>
              )}
              {errors.employee_id && <div style={{ fontSize: 11.5, color: '#E04545', marginTop: 4 }}>{errors.employee_id}</div>}
            </div>

            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Goal name *</label>
              <input style={inp} value={data.nama_goal} onChange={e => setData('nama_goal', e.target.value)} placeholder="cth: Proses rekrutmen karyawan yang akurat" />
              {errors.nama_goal && <div style={{ fontSize: 11.5, color: '#E04545', marginTop: 4 }}>{errors.nama_goal}</div>}
            </div>

            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Description</label>
              <textarea style={{ ...inp, minHeight: 70, resize: 'vertical' }} value={data.deskripsi} onChange={e => setData('deskripsi', e.target.value)} placeholder="Optional" />
            </div>

            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 7, display: 'block', fontWeight: 600 }}>Goal cycle</label>
              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                {CYCLE_OPTIONS.map(c => (
                  <div key={c.key} onClick={() => setCycle(c.key)}
                    style={{ padding: '7px 14px', borderRadius: 8, fontSize: 12.5, fontWeight: 600, cursor: 'pointer',
                      background: data.siklus === c.key ? 'rgba(58,143,224,.15)' : 'var(--bg3)',
                      color: data.siklus === c.key ? 'var(--blue)' : 'var(--muted2)',
                      border: `1px solid ${data.siklus === c.key ? 'var(--blue)' : 'var(--border)'}` }}>
                    {c.label}
                  </div>
                ))}
              </div>
            </div>

            <div style={{ display: 'flex', gap: 12 }}>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Goal period *</label>
                <DatePicker value={data.tanggal_mulai} onChange={setStartDate} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Due date *</label>
                <DatePicker value={data.tanggal_selesai} onChange={v => setData('tanggal_selesai', v)} />
                {errors.tanggal_selesai && <div style={{ fontSize: 11.5, color: '#E04545', marginTop: 4 }}>{errors.tanggal_selesai}</div>}
              </div>
            </div>

            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 7, display: 'block', fontWeight: 600 }}>Measurement unit</label>
              <div style={{ display: 'flex', gap: 18 }}>
                {SATUAN_OPTIONS.map(s => (
                  <label key={s.key} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, cursor: 'pointer', color: 'var(--text)' }}>
                    <input type="radio" checked={data.satuan === s.key} onChange={() => setData('satuan', s.key)} />
                    {s.label}
                  </label>
                ))}
              </div>
            </div>

            <div style={{ display: 'flex', gap: 12 }}>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Baseline</label>
                <input type="number" style={inp} value={data.baseline} onChange={e => setData('baseline', e.target.value)} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Target</label>
                <input type="number" style={inp} value={data.target} onChange={e => setData('target', e.target.value)} />
              </div>
            </div>

            <div>
              <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Bobot (%) *</label>
              <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 6 }}>Porsi goal ini terhadap skor akhir gabungan orang tersebut.</div>
              <input type="number" style={{ ...inp, maxWidth: 160 }} value={data.bobot} onChange={e => setData('bobot', e.target.value)} placeholder="cth: 30" />
              {errors.bobot && <div style={{ fontSize: 11.5, color: '#E04545', marginTop: 4 }}>{errors.bobot}</div>}
            </div>

            <div style={{ display: 'flex', gap: 10, justifyContent: 'flex-end', paddingTop: 8, borderTop: '1px solid var(--border)' }}>
              <Link href="/kpi" style={{ padding: '10px 20px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 13, textDecoration: 'none', fontFamily: "'Outfit',sans-serif" }}>Cancel</Link>
              <button type="submit" disabled={processing} style={{ padding: '10px 24px', borderRadius: 8, border: 'none', background: 'linear-gradient(135deg,#3A8FE0,#1A5FA0)', color: '#fff', fontSize: 13, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", opacity: processing ? .7 : 1, display: 'flex', alignItems: 'center', gap: 6 }}>
                {processing && <Loader2 size={14} style={{ animation: 'spin .8s linear infinite' }} />} Submit
              </button>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
