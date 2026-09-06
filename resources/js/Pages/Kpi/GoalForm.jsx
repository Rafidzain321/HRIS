// resources/js/Pages/Kpi/GoalForm.jsx
import React, { useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { Target, TriangleAlert, Loader2, ChevronRight, ArrowLeft } from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '9px 12px', fontSize: 13,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const CYCLE_OPTIONS = [
  { key: 'custom', label: 'Custom' },
  { key: 'monthly', label: 'Monthly' },
  { key: 'quarterly', label: 'Quarterly' },
  { key: 'half_yearly', label: 'Half-yearly' },
  { key: 'yearly', label: 'Yearly' },
];
const CYCLE_MONTHS = { monthly: 1, quarterly: 3, half_yearly: 6, yearly: 12 };
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

        <form onSubmit={submit} style={{ display: 'grid', gridTemplateColumns: employees.length > 1 ? '280px 1fr' : '1fr', gap: 18, alignItems: 'start' }}>
          {employees.length > 1 && (
            <div style={{ ...card, padding: 14 }}>
              <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 10, textTransform: 'uppercase', letterSpacing: '.06em' }}>
                Goal owner {is_self_only ? '(Saya & Tim)' : '(Karyawan HO)'}
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 2, maxHeight: 420, overflowY: 'auto' }}>
                {hierarchy.map(e => {
                  const active = String(e.id) === String(data.employee_id);
                  return (
                    <div key={e.id} onClick={() => mode === 'add' && setData('employee_id', e.id)}
                      style={{
                        padding: '7px 8px', paddingLeft: 8 + e.depth * 16, borderRadius: 7,
                        cursor: mode === 'add' ? 'pointer' : 'default',
                        background: active ? 'rgba(232,160,32,.12)' : 'transparent',
                        border: `1px solid ${active ? 'var(--accent)' : 'transparent'}`,
                        opacity: mode === 'edit' && !active ? 0.4 : 1,
                        display: 'flex', alignItems: 'center', gap: 4,
                      }}>
                      {e.depth > 0 && <ChevronRight size={11} style={{ color: 'var(--muted)', flexShrink: 0 }} />}
                      <div style={{ minWidth: 0 }}>
                        <div style={{ fontSize: 12, fontWeight: 600, color: active ? 'var(--accent)' : 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{e.nama_lengkap}</div>
                        <div style={{ fontSize: 10, color: 'var(--muted)' }}>{e.jabatan}</div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          <div style={{ ...card, padding: 20, display: 'flex', flexDirection: 'column', gap: 16 }}>
            {selectedEmployee && employees.length <= 1 && (
              <div style={{ fontSize: 12.5, color: 'var(--muted)' }}>Goal owner: <b style={{ color: 'var(--text)' }}>{selectedEmployee.nama_lengkap}</b></div>
            )}
            {errors.employee_id && <div style={{ fontSize: 11.5, color: '#E04545' }}>{errors.employee_id}</div>}

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
                <input type="date" style={inp} value={data.tanggal_mulai} onChange={e => setData('tanggal_mulai', e.target.value)} />
              </div>
              <div style={{ flex: 1 }}>
                <label style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 5, display: 'block', fontWeight: 600 }}>Due date *</label>
                <input type="date" style={inp} value={data.tanggal_selesai} onChange={e => setData('tanggal_selesai', e.target.value)} />
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
