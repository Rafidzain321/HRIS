// resources/js/Pages/Kpi/CriteriaManage.jsx
import React, { useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Plus, Trash2, ShieldCheck, UserCog, Search, Pencil, Check, X } from 'lucide-react';

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 8, padding: '8px 11px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
};

const SECTION_LABEL = { A: 'A. Aspek Keselamatan (40%)', B: 'B. Produktivitas / Keandalan / Kerjasama / Komunikasi (60%)' };

function csrfHeaders() {
  const token = document.querySelector('meta[name=csrf-token]')?.content;
  return { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' };
}

// ── PEMILIH KARYAWAN TARGET (pencarian bebas dari daftar yang diizinkan backend) ──
function EmployeePicker({ employees, value, onChange }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const selected = employees.find(e => e.id === value);
  const searchLow = search.trim().toLowerCase();
  const list = (searchLow
    ? employees.filter(e => e.nama_lengkap.toLowerCase().includes(searchLow) || e.jabatan.toLowerCase().includes(searchLow))
    : employees
  ).slice(0, 50);

  return (
    <div style={{ position: 'relative' }}>
      <div onClick={() => setOpen(o => !o)} style={{ ...inp, cursor: 'pointer' }}>
        {selected?.nama_lengkap || '— Pilih Karyawan —'}
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

function AddCriteriaForm({ employees, onAdded }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    section: 'B',
    sub_kategori: '',
    deskripsi: '',
    employee_id: '',
  });

  function submit(e) {
    e.preventDefault();
    post('/kpi/kriteria', {
      preserveScroll: true,
      onSuccess: () => { reset('deskripsi', 'sub_kategori'); onAdded(); },
    });
  }

  return (
    <form onSubmit={submit} style={{ ...card, padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}><Plus size={14} /> Tambah Kriteria Khusus Karyawan</div>

      <div>
        <label style={{ fontSize: 11, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Karyawan</label>
        <EmployeePicker employees={employees} value={data.employee_id} onChange={v => setData('employee_id', v)} />
        {errors.employee_id && <div style={{ fontSize: 11, color: '#E04545', marginTop: 3 }}>{errors.employee_id}</div>}
      </div>

      <div>
        <label style={{ fontSize: 11, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Section</label>
        <select value={data.section} onChange={e => setData('section', e.target.value)} style={inp}>
          <option value="A">{SECTION_LABEL.A}</option>
          <option value="B">{SECTION_LABEL.B}</option>
        </select>
      </div>

      {data.section === 'B' && (
        <div>
          <label style={{ fontSize: 11, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Sub Kategori (opsional)</label>
          <input type="text" value={data.sub_kategori} onChange={e => setData('sub_kategori', e.target.value)} placeholder="cth: Produktifitas Kerja" style={inp} />
        </div>
      )}

      <div>
        <label style={{ fontSize: 11, color: 'var(--muted)', display: 'block', marginBottom: 4 }}>Deskripsi Kriteria</label>
        <textarea value={data.deskripsi} onChange={e => setData('deskripsi', e.target.value)} rows={2} placeholder="Tuliskan kriteria penilaian..." style={{ ...inp, resize: 'vertical' }} />
        {errors.deskripsi && <div style={{ fontSize: 11, color: '#E04545', marginTop: 3 }}>{errors.deskripsi}</div>}
      </div>

      <button type="submit" disabled={processing}
        style={{ padding: '9px 16px', borderRadius: 9, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 12.5, fontWeight: 700, cursor: processing ? 'default' : 'pointer', fontFamily: "'Outfit',sans-serif" }}>
        Tambah Kriteria
      </button>
      <div style={{ fontSize: 10.5, color: 'var(--muted)', lineHeight: 1.5 }}>
        Kriteria tambahan otomatis membagi ulang bobot per poin di section terkait (semua poin di satu section tetap sama-sama berkontribusi terhadap bobot 40%/60%), dan hanya berlaku untuk karyawan yang dipilih saja.
      </div>
    </form>
  );
}

// ── SATU BARIS KRITERIA — bisa masuk mode edit teks kalau canEdit=true ──
function CriteriaRow({ c, canEdit, onSaved, onDelete, deleting, numbered }) {
  const [editing, setEditing] = useState(false);
  const [sub, setSub] = useState(c.sub_kategori || '');
  const [desk, setDesk] = useState(c.deskripsi);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState('');

  function save() {
    if (!desk.trim()) { setErr('Deskripsi tidak boleh kosong.'); return; }
    setSaving(true); setErr('');
    axios.put(`/kpi/kriteria/${c.id}`, { sub_kategori: sub || null, deskripsi: desk }, { headers: csrfHeaders() })
      .then(() => { onSaved(c.id, { sub_kategori: sub || null, deskripsi: desk }); setEditing(false); })
      .catch(e => setErr(e.response?.data?.message || 'Gagal menyimpan perubahan.'))
      .finally(() => setSaving(false));
  }

  function cancel() {
    setSub(c.sub_kategori || ''); setDesk(c.deskripsi); setErr(''); setEditing(false);
  }

  if (editing) {
    return (
      <div style={{ padding: '8px 0', display: 'flex', flexDirection: 'column', gap: 6 }}>
        <input type="text" value={sub} onChange={e => setSub(e.target.value)} placeholder="Sub kategori (opsional)" style={{ ...inp, fontSize: 11.5 }} />
        <textarea value={desk} onChange={e => setDesk(e.target.value)} rows={2} style={{ ...inp, fontSize: 12, resize: 'vertical' }} autoFocus />
        {err && <div style={{ fontSize: 10.5, color: '#E04545' }}>{err}</div>}
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={save} disabled={saving} style={{ padding: '5px 10px', borderRadius: 6, border: 'none', background: '#22C97A', color: '#0C0F14', fontSize: 11, fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4 }}><Check size={12} /> Simpan</button>
          <button onClick={cancel} disabled={saving} style={{ padding: '5px 10px', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--bg3)', color: 'var(--muted2)', fontSize: 11, fontWeight: 700, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4 }}><X size={12} /> Batal</button>
        </div>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
      {numbered !== undefined && <span style={{ color: 'var(--muted)', width: 18, flexShrink: 0, fontSize: 12, paddingTop: 1 }}>{numbered}.</span>}
      <div style={{ flex: 1, fontSize: 12 }}>
        {c.sub_kategori && <span style={{ color: 'var(--muted)' }}>[{c.sub_kategori}] </span>}{c.deskripsi}
      </div>
      {canEdit && (
        <button onClick={() => setEditing(true)} style={{ background: 'none', border: 'none', color: 'var(--muted)', cursor: 'pointer', padding: 2, flexShrink: 0 }} title="Edit teks">
          <Pencil size={12} />
        </button>
      )}
      {onDelete && (
        <button onClick={onDelete} disabled={deleting} style={{ background: 'none', border: 'none', color: '#E04545', cursor: 'pointer', padding: 2, flexShrink: 0 }} title="Nonaktifkan">
          <Trash2 size={13} />
        </button>
      )}
    </div>
  );
}

export default function CriteriaManage({ criteria = [], target_employees = [], can_edit_text = false }) {
  const [list, setList] = useState(criteria);
  const [deletingId, setDeletingId] = useState(null);
  const [confirmDelete, setConfirmDelete] = useState(null);

  function reload() {
    router.reload({ only: ['criteria'], onSuccess: (page) => setList(page.props.criteria) });
  }

  function handleSaved(id, patch) {
    setList(l => l.map(c => c.id === id ? { ...c, ...patch } : c));
  }

  function handleDelete() {
    const c = confirmDelete;
    setConfirmDelete(null);
    setDeletingId(c.id);
    axios.delete(`/kpi/kriteria/${c.id}`, { headers: csrfHeaders() })
      .then(() => setList(l => l.filter(x => x.id !== c.id)))
      .catch(err => alert(err.response?.data?.message || 'Gagal menghapus kriteria.'))
      .finally(() => setDeletingId(null));
  }

  const base = list.filter(c => c.is_base);
  const extra = list.filter(c => !c.is_base);
  const extraByEmployee = extra.reduce((acc, c) => {
    const key = c.employee_nama || '-';
    (acc[key] = acc[key] || []).push(c);
    return acc;
  }, {});

  return (
    <AppLayout title="Kelola Kriteria KPI" subtitle="Kriteria Penilaian">
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16 }}>
        <button onClick={() => router.visit('/kpi')}
          style={{ padding: '8px 12px', borderRadius: 8, border: '1px solid var(--border)', background: 'var(--card)', color: 'var(--muted2)', fontSize: 12, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif", display: 'flex', alignItems: 'center', gap: 6 }}>
          <ArrowLeft size={14} /> Kembali
        </button>
      </div>

      <div className="kpi-criteria-grid" style={{ display: 'grid', gridTemplateColumns: '1fr 340px', gap: 16, alignItems: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, marginBottom: 4, display: 'flex', alignItems: 'center', gap: 8 }}><ShieldCheck size={14} /> 20 Kriteria Baku</div>
            <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 10 }}>
              Berlaku untuk semua karyawan, tidak bisa dihapus.{can_edit_text ? ' Teksnya bisa diedit lewat ikon pensil (khusus HR/super-admin).' : ''}
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              {['A', 'B'].map(sec => (
                <div key={sec} style={{ marginBottom: 6 }}>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--accent)', margin: '8px 0 4px' }}>{SECTION_LABEL[sec]}</div>
                  {base.filter(c => c.section === sec).map((c, i) => (
                    <div key={c.id} style={{ padding: '6px 0', borderTop: i > 0 ? '1px solid var(--border)' : 'none' }}>
                      <CriteriaRow c={c} canEdit={can_edit_text} onSaved={handleSaved} numbered={i + 1} />
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>

          <div style={{ ...card, padding: 16 }}>
            <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 14, fontWeight: 700, marginBottom: 4, display: 'flex', alignItems: 'center', gap: 8 }}><UserCog size={14} /> Kriteria Khusus Karyawan</div>
            <div style={{ fontSize: 11, color: 'var(--muted)', marginBottom: 10 }}>Hanya berlaku untuk karyawan yang bersangkutan.</div>
            {Object.keys(extraByEmployee).length === 0 && (
              <div style={{ padding: 20, textAlign: 'center', color: 'var(--muted)', fontSize: 12 }}>Belum ada kriteria tambahan.</div>
            )}
            {Object.entries(extraByEmployee).map(([nama, items]) => (
              <div key={nama} style={{ marginBottom: 12 }}>
                <div style={{ fontSize: 11.5, fontWeight: 700, marginBottom: 6 }}>{nama}</div>
                {items.map(c => (
                  <div key={c.id} style={{ padding: '7px 10px', borderRadius: 8, background: 'var(--bg3)', marginBottom: 6, display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                    <span style={{ fontSize: 9.5, fontWeight: 700, padding: '2px 7px', borderRadius: 99, background: 'rgba(58,143,224,.12)', color: 'var(--blue)', flexShrink: 0, marginTop: 1 }}>{c.section}</span>
                    <div style={{ flex: 1 }}>
                      <CriteriaRow c={c} canEdit={can_edit_text} onSaved={handleSaved} onDelete={() => setConfirmDelete(c)} deleting={deletingId === c.id} />
                    </div>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>

        <AddCriteriaForm employees={target_employees} onAdded={reload} />
      </div>

      <ConfirmModal
        open={!!confirmDelete}
        onCancel={() => setConfirmDelete(null)}
        onConfirm={handleDelete}
        title="Nonaktifkan Kriteria"
        message={confirmDelete ? `Nonaktifkan kriteria "${confirmDelete.deskripsi}"? Riwayat penilaian yang sudah memakai kriteria ini tetap tersimpan.` : ''}
        confirmLabel="Ya, Nonaktifkan"
        type="warning"
      />
    </AppLayout>
  );
}
