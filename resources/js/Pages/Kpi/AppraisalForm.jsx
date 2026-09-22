// resources/js/Pages/Kpi/AppraisalForm.jsx
import React, { useState, useMemo } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Save, CheckCircle2, Search, Loader2, Info, Trash2 } from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const SKOR_GUIDE = [
  { v: 1, label: 'Kurang' },
  { v: 2, label: 'Cukup' },
  { v: 3, label: 'Baik' },
  { v: 4, label: 'Memuaskan' },
  { v: 5, label: 'Sangat Memuaskan' },
];

const PREDIKAT_META = {
  K:  { label: 'Kurang',      color: '#E04545' },
  C:  { label: 'Cukup',       color: '#E8A020' },
  B:  { label: 'Baik',        color: '#22C97A' },
  BS: { label: 'Baik Sekali', color: '#3A8FE0' },
  A:  { label: 'Memuaskan',   color: '#9B59B6' },
};

function predikatDari(total) {
  if (total >= 100) return 'A';
  if (total >= 96) return 'BS';
  if (total >= 76) return 'B';
  if (total >= 60) return 'C';
  return 'K';
}

function csrfHeaders() {
  const token = document.querySelector('meta[name=csrf-token]')?.content;
  return { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' };
}

// ── PEMILIH PENILAI (reviewer) — pencarian bebas dari semua karyawan HO ──
function ReviewerPicker({ employees, value, onChange, disabled }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const selected = employees.find(e => e.id === value);
  const searchLow = search.trim().toLowerCase();
  const list = (searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : employees
  ).slice(0, 50);

  if (disabled) {
    return <div style={{ ...inp, background: 'var(--bg2)', color: 'var(--muted2)' }}>{selected?.nama_lengkap || '— Belum ditentukan —'}</div>;
  }

  return (
    <div style={{ position: 'relative' }}>
      <div onClick={() => setOpen(o => !o)} style={{ ...inp, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <span>{selected?.nama_lengkap || '— Pilih Penilai —'}</span>
      </div>
      {open && (
        <>
          <div style={{ position: 'fixed', inset: 0, zIndex: 60 }} onClick={() => setOpen(false)} />
          <div style={{ position: 'absolute', top: 'calc(100% + 4px)', left: 0, right: 0, zIndex: 70, background: 'var(--bg2)', border: '1px solid var(--border2)', borderRadius: 10, boxShadow: '0 12px 36px rgba(0,0,0,.35)', overflow: 'hidden' }}>
            <div style={{ padding: 8 }}>
              <div style={{ position: 'relative' }}>
                <span style={{ position: 'absolute', left: 9, top: '50%', transform: 'translateY(-50%)', color: 'var(--muted)', display: 'flex' }}><Search size={12} /></span>
                <input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari nama / jabatan..." style={{ ...inp, paddingLeft: 28, fontSize: 12 }} autoFocus />
              </div>
            </div>
            <div style={{ maxHeight: 220, overflowY: 'auto', padding: '0 8px 8px' }}>
              <div onClick={() => { onChange(null); setOpen(false); }} style={{ padding: '6px 8px', borderRadius: 6, cursor: 'pointer', fontSize: 12, color: 'var(--muted)' }}
                onMouseEnter={ev => ev.currentTarget.style.background = 'rgba(232,160,32,.08)'} onMouseLeave={ev => ev.currentTarget.style.background = ''}>
                — Belum ditentukan —
              </div>
              {list.map(e => (
                <div key={e.id} onClick={() => { onChange(e.id); setOpen(false); setSearch(''); }}
                  style={{ padding: '6px 8px', borderRadius: 6, cursor: 'pointer' }}
                  onMouseEnter={ev => ev.currentTarget.style.background = 'rgba(232,160,32,.08)'} onMouseLeave={ev => ev.currentTarget.style.background = ''}>
                  <div style={{ fontSize: 12, fontWeight: 600 }}>{e.nama_lengkap}</div>
                  <div style={{ fontSize: 10, color: 'var(--muted)' }}>{e.jabatan}</div>
                </div>
              ))}
              {list.length === 0 && <div style={{ padding: 10, fontSize: 11.5, color: 'var(--muted)', textAlign: 'center' }}>Tidak ditemukan.</div>}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

// ── PILIHAN SKOR 1-5 (pill buttons) ──
function ScorePicker({ value, onChange, disabled }) {
  return (
    <div style={{ display: 'flex', gap: 4, justifyContent: 'center' }}>
      {SKOR_GUIDE.map(s => {
        const active = value === s.v;
        return (
          <button key={s.v} type="button" disabled={disabled} title={s.label}
            onClick={() => onChange(s.v)}
            style={{
              width: 28, height: 28, borderRadius: 7, cursor: disabled ? 'default' : 'pointer',
              border: `1px solid ${active ? 'transparent' : 'var(--border)'}`,
              background: active ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--bg3)',
              color: active ? '#0C0F14' : 'var(--muted2)',
              fontWeight: 700, fontSize: 12.5, fontFamily: "'Outfit',sans-serif",
              opacity: disabled && !active ? 0.5 : 1,
            }}>
            {s.v}
          </button>
        );
      })}
    </div>
  );
}

function CriteriaTable({ title, subtitle, rows, scores, onScore, disabled, groupBySub }) {
  const groups = groupBySub
    ? rows.reduce((acc, r) => {
        const key = r.sub_kategori || '-';
        (acc[key] = acc[key] || []).push(r);
        return acc;
      }, {})
    : { _all: rows };

  return (
    <div style={{ ...card, overflow: 'hidden' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid var(--border)' }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700 }}>{title}</div>
        {subtitle && <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>{subtitle}</div>}
      </div>
      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
          <thead>
            <tr style={{ background: 'var(--bg3)' }}>
              <th style={{ textAlign: 'left', padding: '8px 14px', fontSize: 10.5, color: 'var(--muted)', width: 36 }}>No.</th>
              <th style={{ textAlign: 'left', padding: '8px 14px', fontSize: 10.5, color: 'var(--muted)' }}>Kriteria Penilaian</th>
              <th style={{ textAlign: 'center', padding: '8px 14px', fontSize: 10.5, color: 'var(--muted)', width: 200 }}>Skor (1-5)</th>
            </tr>
          </thead>
          <tbody>
            {Object.entries(groups).map(([sub, items]) => (
              <React.Fragment key={sub}>
                {groupBySub && (
                  <tr>
                    <td colSpan={3} style={{ padding: '6px 14px', background: 'rgba(232,160,32,.06)', fontSize: 11, fontWeight: 700, color: 'var(--accent)', borderTop: '1px solid var(--border)' }}>{sub}</td>
                  </tr>
                )}
                {items.map((r, i) => (
                  <tr key={r.id} style={{ borderTop: '1px solid var(--border)' }}>
                    <td style={{ padding: '9px 14px', color: 'var(--muted2)' }}>{i + 1}</td>
                    <td style={{ padding: '9px 14px' }}>
                      {r.deskripsi}
                      {r.is_base === false && <span style={{ marginLeft: 6, fontSize: 9.5, padding: '1px 7px', borderRadius: 99, background: 'rgba(58,143,224,.12)', color: 'var(--blue)' }}>Khusus Karyawan Ini</span>}
                    </td>
                    <td style={{ padding: '7px 14px' }}>
                      <ScorePicker value={scores[r.id] ?? null} onChange={v => onScore(r.id, v)} disabled={disabled} />
                    </td>
                  </tr>
                ))}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function AppraisalForm({ appraisal, employee, criteria, all_employees = [], can_edit, can_reopen = false }) {
  const [scores, setScores] = useState(() => Object.fromEntries(criteria.map(c => [c.id, c.nilai ?? null])));
  const [reviewerId, setReviewerId] = useState(appraisal.reviewer_id);
  const [catatan, setCatatan] = useState(appraisal.catatan || '');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [confirmReopen, setConfirmReopen] = useState(false);
  const [confirmDiscard, setConfirmDiscard] = useState(false);

  const isFinal = appraisal.status === 'submitted';
  const editable = can_edit && !isFinal;

  const criteriaA = criteria.filter(c => c.section === 'A');
  const criteriaB = criteria.filter(c => c.section === 'B');

  const live = useMemo(() => {
    const maxA = criteriaA.length * 5;
    const maxB = criteriaB.length * 5;
    const sumA = criteriaA.reduce((a, c) => a + (scores[c.id] || 0), 0);
    const sumB = criteriaB.reduce((a, c) => a + (scores[c.id] || 0), 0);
    const nilaiA = maxA > 0 ? Math.round((40 / maxA) * sumA * 100) / 100 : 0;
    const nilaiB = maxB > 0 ? Math.round((60 / maxB) * sumB * 100) / 100 : 0;
    const total = Math.round((nilaiA + nilaiB) * 100) / 100;
    const filled = criteria.filter(c => scores[c.id]).length;
    return { nilaiA, nilaiB, total, predikat: predikatDari(total), filled, totalCriteria: criteria.length };
  }, [scores, criteriaA, criteriaB, criteria]);

  function handleScore(criteriaId, v) {
    setScores(s => ({ ...s, [criteriaId]: s[criteriaId] === v ? null : v }));
  }

  function buildPayload(submit) {
    return {
      reviewer_id: reviewerId || null,
      catatan: catatan || null,
      scores: criteria.map(c => ({ criteria_id: c.id, nilai: scores[c.id] || null })),
      submit,
    };
  }

  function save(submit) {
    setError('');
    setSaving(true);
    axios.put(`/kpi/appraisals/${appraisal.id}`, buildPayload(submit), { headers: csrfHeaders() })
      .then(() => {
        router.visit(`/kpi?tahun=${appraisal.tahun}&semester=${appraisal.semester}`);
      })
      .catch(err => {
        setError(err.response?.data?.message || 'Gagal menyimpan penilaian.');
      })
      .finally(() => setSaving(false));
  }

  function reopen() {
    setConfirmReopen(false);
    setSaving(true);
    axios.put(`/kpi/appraisals/${appraisal.id}/reopen`, {}, { headers: csrfHeaders() })
      .then(() => router.reload())
      .catch(err => setError(err.response?.data?.message || 'Gagal membuka kembali penilaian.'))
      .finally(() => setSaving(false));
  }

  function hapusDraft() {
    setConfirmDiscard(false);
    setSaving(true);
    axios.delete(`/kpi/appraisals/${appraisal.id}`, { headers: csrfHeaders() })
      .then(() => router.visit(`/kpi?tahun=${appraisal.tahun}&semester=${appraisal.semester}`))
      .catch(err => { setError(err.response?.data?.message || 'Gagal membuang draft.'); setSaving(false); });
  }

  const predikatMeta = PREDIKAT_META[live.predikat];

  return (
    <AppLayout title="Penilaian KPI" subtitle={employee.nama_lengkap}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
        <button onClick={() => router.visit(`/kpi?tahun=${appraisal.tahun}&semester=${appraisal.semester}`)}
          style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--muted2)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}>
          <ArrowLeft size={14} /> Kembali
        </button>
        {isFinal && (
          <span style={{ fontSize: 11.5, fontWeight: 700, padding: '5px 12px', borderRadius: 99, background: 'rgba(34,201,122,.12)', color: '#22C97A', display: 'flex', alignItems: 'center', gap: 6 }}>
            <CheckCircle2 size={13} /> Sudah Final
          </span>
        )}
        {isFinal && can_reopen && (
          <button onClick={() => setConfirmReopen(true)} disabled={saving}
            style={{ padding: '7px 14px', borderRadius: 8, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.08)', color: 'var(--blue)', fontSize: 11.5, fontWeight: 700, cursor: saving ? 'default' : 'pointer', fontFamily: "'Outfit',sans-serif" }}>
            Buka Kembali
          </button>
        )}
        {!can_edit && (
          <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>Mode lihat saja — kamu tidak memiliki akses untuk mengubah penilaian ini.</span>
        )}
      </div>

      <div className="kpi-form-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 300px', gap: 16, alignItems: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 15, fontWeight: 700, marginBottom: 10 }}>Formulir Penilaian Kinerja Karyawan</div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: '8px 20px', fontSize: 12 }}>
              <div><span style={{ color: 'var(--muted)' }}>Periode</span><div style={{ fontWeight: 600 }}>Semester {appraisal.semester} — {appraisal.tahun}</div></div>
              <div><span style={{ color: 'var(--muted)' }}>Nama</span><div style={{ fontWeight: 600 }}>{employee.nama_lengkap}</div></div>
              <div><span style={{ color: 'var(--muted)' }}>Jabatan</span><div style={{ fontWeight: 600 }}>{employee.jabatan}</div></div>
              <div><span style={{ color: 'var(--muted)' }}>Departemen</span><div style={{ fontWeight: 600 }}>{employee.departemen}</div></div>
              <div><span style={{ color: 'var(--muted)' }}>Badge No.</span><div style={{ fontWeight: 600 }}>{employee.id_badge || '—'}</div></div>
              <div><span style={{ color: 'var(--muted)' }}>Tanggal Bergabung</span><div style={{ fontWeight: 600 }}>{employee.tanggal_masuk || '—'}</div></div>
            </div>
          </div>

          <CriteriaTable title="A. Aspek Keselamatan (Bobot 40%)" rows={criteriaA} scores={scores} onScore={handleScore} disabled={!editable} />
          <CriteriaTable title="B. Produktifitas Kerja, Keandalan, Kerjasama Team & Komunikasi (Bobot 60%)" rows={criteriaB} scores={scores} onScore={handleScore} disabled={!editable} groupBySub />

          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 8 }}>Catatan Penilai (opsional)</div>
            <textarea value={catatan} onChange={e => setCatatan(e.target.value)} disabled={!editable} rows={3}
              placeholder="Catatan tambahan mengenai kinerja karyawan..." style={{ ...inp, resize: 'vertical', minHeight: 68, fontFamily: "'Outfit',sans-serif" }} />
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, position: 'sticky', top: 16 }}>
          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, marginBottom: 12 }}>Hasil Perhitungan</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}><span style={{ color: 'var(--muted)' }}>Nilai A (40%)</span><span style={{ fontWeight: 700 }}>{live.nilaiA}</span></div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}><span style={{ color: 'var(--muted)' }}>Nilai B (60%)</span><span style={{ fontWeight: 700 }}>{live.nilaiB}</span></div>
              <div style={{ borderTop: '1px solid var(--border)', margin: '4px 0' }} />
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14 }}><span style={{ fontWeight: 700 }}>Total Nilai</span><span style={{ fontFamily: 'Syne,sans-serif', fontWeight: 700, fontSize: 20 }}>{live.total}</span></div>
              <div style={{ display: 'flex', justifyContent: 'center', marginTop: 6 }}>
                <span style={{ fontSize: 12.5, fontWeight: 700, padding: '5px 16px', borderRadius: 99, background: `${predikatMeta.color}22`, color: predikatMeta.color }}>
                  {live.predikat} · {predikatMeta.label}
                </span>
              </div>
              <div style={{ fontSize: 10.5, color: 'var(--muted)', textAlign: 'center', marginTop: 4 }}>{live.filled} / {live.totalCriteria} poin sudah diisi</div>
            </div>
          </div>

          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 8 }}>Penilai (Reviewer)</div>
            <ReviewerPicker employees={all_employees} value={reviewerId} onChange={setReviewerId} disabled={!editable} />
          </div>

          <div style={{ ...card, padding: 14 }}>
            <div style={{ display: 'flex', gap: 6, alignItems: 'flex-start', marginBottom: 10 }}>
              <Info size={13} style={{ color: 'var(--muted)', flexShrink: 0, marginTop: 1 }} />
              <div style={{ fontSize: 10.5, color: 'var(--muted)', lineHeight: 1.5 }}>
                1=Kurang, 2=Cukup, 3=Baik, 4=Memuaskan, 5=Sangat Memuaskan.
              </div>
            </div>
            {error && <div style={{ fontSize: 11.5, color: '#E04545', marginBottom: 10, padding: '8px 10px', background: 'rgba(224,69,69,.08)', borderRadius: 8 }}>{error}</div>}
            {editable && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <button onClick={() => save(false)} disabled={saving}
                  style={{ padding: '10px 16px', borderRadius: 9, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--text)', fontSize: 12.5, fontWeight: 700, cursor: saving ? 'default' : 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                  {saving ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />} Simpan Draft
                </button>
                <button onClick={() => save(true)} disabled={saving}
                  style={{ padding: '10px 16px', borderRadius: 9, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12.5, fontWeight: 700, cursor: saving ? 'default' : 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
                  {saving ? <Loader2 size={14} className="animate-spin" /> : <CheckCircle2 size={14} />} Simpan Final
                </button>
                <button onClick={() => setConfirmDiscard(true)} disabled={saving}
                  style={{ padding: '9px 16px', borderRadius: 9, border: '1px solid rgba(224,69,69,.25)', background: 'rgba(224,69,69,.06)', color: '#E04545', fontSize: 12, fontWeight: 700, cursor: saving ? 'default' : 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 2 }}>
                  <Trash2 size={13} /> Buang Draft
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      <ConfirmModal
        open={confirmReopen}
        onCancel={() => setConfirmReopen(false)}
        onConfirm={reopen}
        title="Buka Kembali Penilaian"
        message="Buka kembali penilaian ini ke status draft supaya bisa dikoreksi?"
        confirmLabel="Ya, Buka Kembali"
        type="warning"
      />
      <ConfirmModal
        open={confirmDiscard}
        onCancel={() => setConfirmDiscard(false)}
        onConfirm={hapusDraft}
        title="Buang Draft"
        message='Buang draft penilaian ini? Semua skor yang sudah diisi akan hilang dan status kembali ke "Belum Dinilai".'
        confirmLabel="Ya, Buang"
        type="danger"
      />
    </AppLayout>
  );
}
