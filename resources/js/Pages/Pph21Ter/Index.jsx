// resources/js/Pages/Pph21Ter/Index.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { usePage, Link } from '@inertiajs/react';
import axios from 'axios';
import {
  Percent, Plus, Trash2, ArrowLeft, TriangleAlert, Loader2, Save, Info,
} from 'lucide-react';

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content; }

const card = { background: 'var(--card)', border: '1px solid var(--border)', borderRadius: 12 };
const inp = {
  background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
  borderRadius: 7, padding: '6px 9px', fontSize: 12.5,
  fontFamily: "'Outfit',sans-serif", outline: 'none', boxSizing: 'border-box',
};

const KATEGORI_LABEL = {
  A: 'Kategori A — PTKP TK/0, TK/1, K/0',
  B: 'Kategori B — PTKP TK/2, TK/3, K/1, K/2',
  C: 'Kategori C — PTKP K/3',
};

function fmtRp(n) {
  if (n === null || n === undefined || n === '') return '';
  return Number(n).toLocaleString('id-ID');
}

// ── SATU BARIS BRACKET (editable) ───────────────────────────
function BracketRow({ bracket, canEdit, onSaved, onDeleted }) {
  const [batasAtas, setBatasAtas] = useState(bracket.batas_atas === null ? '' : String(bracket.batas_atas));
  const [tarif, setTarif] = useState(String(bracket.tarif_persen));
  const [focusBatas, setFocusBatas] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const isInfinite = bracket.batas_atas === null;
  const dirty = (isInfinite ? '' : String(bracket.batas_atas)) !== batasAtas || String(bracket.tarif_persen) !== tarif;

  async function save() {
    setSaving(true); setError('');
    try {
      await axios.put(`/pph21-ter-config/${bracket.id}`, {
        batas_atas: isInfinite ? null : (batasAtas === '' ? null : Number(batasAtas)),
        tarif_persen: Number(tarif),
      }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menyimpan.');
    }
    setSaving(false);
  }

  async function del() {
    if (!window.confirm('Hapus baris tarif ini?')) return;
    try {
      await axios.delete(`/pph21-ter-config/${bracket.id}`, { headers: { 'X-CSRF-TOKEN': csrf() } });
      onDeleted();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menghapus.');
    }
  }

  return (
    <tr style={{ borderTop: '1px solid var(--border)' }}>
      <td style={{ padding: '7px 10px', textAlign: 'center', color: 'var(--muted)', fontSize: 11 }}>{bracket.urutan}</td>
      <td style={{ padding: '7px 10px' }}>
        {isInfinite ? (
          <span style={{ fontSize: 12, color: 'var(--muted)', fontStyle: 'italic' }}>tak terhingga</span>
        ) : (
          <input type="text" inputMode="numeric" style={{ ...inp, width: 140 }}
            value={focusBatas ? batasAtas : fmtRp(batasAtas)}
            disabled={!canEdit}
            onFocus={() => setFocusBatas(true)}
            onBlur={() => setFocusBatas(false)}
            onChange={e => setBatasAtas(e.target.value.replace(/[^0-9]/g, ''))} />
        )}
      </td>
      <td style={{ padding: '7px 10px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
          <input type="text" inputMode="decimal" style={{ ...inp, width: 70 }} value={tarif}
            disabled={!canEdit} onChange={e => setTarif(e.target.value.replace(/[^0-9.]/g, ''))} />
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>%</span>
        </div>
      </td>
      {canEdit && (
        <td style={{ padding: '7px 10px', textAlign: 'center', width: 90 }}>
          <div style={{ display: 'flex', gap: 4, justifyContent: 'center' }}>
            {saving ? <Loader2 size={13} style={{ animation: 'spin .8s linear infinite', color: 'var(--muted)' }} /> : dirty && (
              <button onClick={save} title="Simpan" style={{ padding: 4, borderRadius: 6, border: '1px solid rgba(58,143,224,.3)', background: 'rgba(58,143,224,.1)', color: 'var(--blue)', cursor: 'pointer', display: 'flex' }}><Save size={12} /></button>
            )}
            {!isInfinite && (
              <button onClick={del} title="Hapus" style={{ padding: 4, borderRadius: 6, border: '1px solid rgba(224,69,69,.2)', background: 'rgba(224,69,69,.08)', color: '#E04545', cursor: 'pointer', display: 'flex' }}><Trash2 size={12} /></button>
            )}
          </div>
        </td>
      )}
      {error && <td colSpan={4} style={{ color: '#E04545', fontSize: 10.5, padding: '0 10px 6px' }}>{error}</td>}
    </tr>
  );
}

// ── FORM TAMBAH BARIS BARU ───────────────────────────────────
function AddRow({ kategori, onAdded }) {
  const [batasAtas, setBatasAtas] = useState('');
  const [tarif, setTarif] = useState('');
  const [focusBatas, setFocusBatas] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  async function submit(e) {
    e.preventDefault();
    if (!batasAtas || tarif === '') return;
    setSaving(true); setError('');
    try {
      await axios.post('/pph21-ter-config', {
        kategori, batas_atas: Number(batasAtas), tarif_persen: Number(tarif),
      }, { headers: { 'X-CSRF-TOKEN': csrf() } });
      setBatasAtas(''); setTarif('');
      onAdded();
    } catch (err) {
      setError(err.response?.data?.message || 'Gagal menambah baris.');
    }
    setSaving(false);
  }

  return (
    <tr style={{ borderTop: '1px solid var(--border)', background: 'var(--bg3)' }}>
      <td style={{ padding: '7px 10px', textAlign: 'center' }}><Plus size={12} style={{ color: 'var(--muted)' }} /></td>
      <td style={{ padding: '7px 10px' }}>
        <input type="text" inputMode="numeric" style={{ ...inp, width: 140 }} placeholder="Batas atas (Rp)"
          value={focusBatas ? batasAtas : fmtRp(batasAtas)}
          onFocus={() => setFocusBatas(true)}
          onBlur={() => setFocusBatas(false)}
          onChange={e => setBatasAtas(e.target.value.replace(/[^0-9]/g, ''))} />
      </td>
      <td style={{ padding: '7px 10px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
          <input type="text" inputMode="decimal" style={{ ...inp, width: 70 }} placeholder="0" value={tarif} onChange={e => setTarif(e.target.value.replace(/[^0-9.]/g, ''))} />
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>%</span>
        </div>
      </td>
      <td style={{ padding: '7px 10px', textAlign: 'center' }}>
        <button onClick={submit} disabled={saving} type="button" style={{ padding: '4px 10px', borderRadius: 6, border: 'none', background: 'linear-gradient(135deg,#E8A020,#A06010)', color: '#0C0F14', fontSize: 11, fontWeight: 700, cursor: 'pointer', fontFamily: "'Outfit',sans-serif" }}>
          {saving ? '...' : 'Tambah'}
        </button>
      </td>
      {error && <td colSpan={4} style={{ color: '#E04545', fontSize: 10.5, padding: '0 10px 6px' }}>{error}</td>}
    </tr>
  );
}

// ── MAIN ────────────────────────────────────────────────────
export default function Pph21TerIndex({ brackets = {} }) {
  const { auth } = usePage().props;
  const canEdit = (auth?.user?.permissions || []).includes('edit-data-gaji');
  const [, forceRefresh] = useState(0);

  function reload() { window.location.reload(); }

  return (
    <AppLayout title="Konfigurasi PPh21" subtitle="TER">
      <Link href="/timesheet/data-gaji" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12.5, color: 'var(--muted)', textDecoration: 'none', marginBottom: 14 }}>
        <ArrowLeft size={14} /> Kembali ke Data Gaji
      </Link>

      <div style={{ marginBottom: 18 }}>
        <div style={{ fontFamily: 'Syne,sans-serif', fontSize: 20, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 8 }}>
          <Percent size={19} /> Konfigurasi Tarif TER PPh21
        </div>
        <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 3 }}>Tabel tarif efektif rata-rata (PP 58/2023 & PMK 168/2023) — dipakai otomatis untuk menghitung estimasi PPh21 di semua slip gaji.</div>
      </div>

      <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8, padding: '10px 14px', borderRadius: 8, background: 'rgba(232,160,32,.08)', border: '1px solid rgba(232,160,32,.2)', marginBottom: 18, fontSize: 12, color: 'var(--accent)' }}>
        <Info size={15} style={{ flexShrink: 0, marginTop: 1 }} />
        <div>PPh21 di sistem ini sifatnya <b>catatan saja</b> (ditanggung penuh perusahaan) — mengubah tarif di sini tidak memotong gaji karyawan, cuma mengubah angka estimasi yang tampil di slip gaji. "Batas atas" adalah batas penghasilan bruto bulanan; baris "tak terhingga" (terakhir tiap kategori) tidak bisa dihapus/diubah batasnya.</div>
      </div>

      {!canEdit && (
        <div style={{ marginBottom: 14, fontSize: 11.5, color: 'var(--muted)' }}>Mode lihat saja — akun Anda tidak punya izin edit Data Gaji.</div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 16 }}>
        {['A', 'B', 'C'].map(kategori => (
          <div key={kategori} className="panel" style={card}>
            <div style={{ padding: '12px 14px', borderBottom: '1px solid var(--border)', fontSize: 13, fontWeight: 700 }}>
              {KATEGORI_LABEL[kategori]}
            </div>
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5 }}>
                <thead>
                  <tr style={{ background: 'var(--bg3)' }}>
                    <th style={{ padding: '8px 10px', fontSize: 10.5, color: 'var(--muted)' }}>#</th>
                    <th style={{ textAlign: 'left', padding: '8px 10px', fontSize: 10.5, color: 'var(--muted)' }}>Batas Atas Bruto Bulanan (Rp)</th>
                    <th style={{ textAlign: 'left', padding: '8px 10px', fontSize: 10.5, color: 'var(--muted)' }}>Tarif</th>
                    {canEdit && <th style={{ padding: '8px 10px' }} />}
                  </tr>
                </thead>
                <tbody>
                  {(brackets[kategori] || []).map(b => (
                    <BracketRow key={b.id} bracket={b} canEdit={canEdit} onSaved={reload} onDeleted={reload} />
                  ))}
                  {canEdit && <AddRow kategori={kategori} onAdded={reload} />}
                </tbody>
              </table>
            </div>
          </div>
        ))}
      </div>
    </AppLayout>
  );
}
