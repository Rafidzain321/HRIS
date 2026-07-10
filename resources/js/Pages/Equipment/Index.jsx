// resources>js>Pages>Equipment>Index.jsx
import React, { useState, useRef, useEffect } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import ImportModal from '@/Components/ImportModal';

// Kolom opsional tab Operator & Driver
const OPERATOR_COLS = [
  { key:'badge',                label:'Badge ID' },
  { key:'license_no',           label:'License No' },
  { key:'license_expired_date', label:'License Expired' },
  { key:'rfid',                 label:'RFID' },
  { key:'kp_no',                label:'KP No' },
  { key:'kp_expired_date',      label:'KP Expired' },
  { key:'cdrive_expired_date',  label:'C-Drive Expired' },
  { key:'postest_expired_date', label:'Postest Expired' },
  { key:'permit_no',            label:'Permit No' },
  { key:'permit_expired_date',  label:'Permit Expired' },
  { key:'sio_migas_no',         label:'SIO Migas No.' },
  { key:'sio_migas_expired',    label:'SIO Migas Expired' },
  { key:'k3_p3a2_no',           label:'K3 P3A2 No.' },
  { key:'k3_p3a2_expired',      label:'K3 P3A2 Expired' },
];

// Kolom opsional tab Equipment & Vehicle
const VEHICLE_COLS = [
  { key:'plat_nomor',              label:'No. Polisi' },
  { key:'model',                   label:'Model' },
  { key:'manufacture',             label:'Manufacture' },
  { key:'serial_no',               label:'Serial No.' },
  { key:'gps_unit_id',             label:'GPS Unit ID' },
  { key:'tahun',                   label:'Tahun' },
  { key:'kategori',                label:'Kategori' },
  { key:'kapasitas',               label:'Kapasitas' },
  { key:'stnk_expired',            label:'STNK Expired' },
  { key:'tax_expired',             label:'TAX Expired' },
  { key:'kir_expired',             label:'KIR Expired' },
  { key:'izin_non_bm_expired',     label:'Izin Non BM' },
  { key:'vehicle_pass_expired',    label:'Vehicle Pass' },
  { key:'inspection_date',         label:'Inspection' },
  { key:'smbr_pass_expired',       label:'SMBR Pass' },
  { key:'green_stiker_expired',    label:'Green Stiker' },
  { key:'sio_migas_no',            label:'SIO Migas No.' },
  { key:'sio_migas_expired',       label:'SIO Migas Expired' },
  { key:'sio_disnaker_expired',    label:'SIO Disnaker' },
  { key:'k3_p3a2_no',              label:'K3 P3A2 No.' },
  { key:'k3_p3a2_expired',         label:'K3 P3A2 Expired' },
  { key:'tpe_cem_inspector',       label:'TPE CEM' },
  { key:'contractor_cem_inspector',label:'Contractor CEM' },
  { key:'location_of_inspection',  label:'Lokasi Inspeksi' },
  { key:'keterangan',              label:'Keterangan' },
];

const DEFAULT_OPERATOR_COLS = ['license_expired_date','kp_no','kp_expired_date','permit_no','permit_expired_date'];
const DEFAULT_VEHICLE_COLS  = ['model','tahun','stnk_expired','kir_expired','vehicle_pass_expired'];

function fmtDate(val) {
  if (!val) return '—';
  try {
    const d = new Date(val);
    if (isNaN(d.getTime())) return '—';
    const m = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return `${String(d.getDate()).padStart(2,'0')} ${m[d.getMonth()]} ${d.getFullYear()}`;
  } catch { return '—'; }
}

function DocPill({ status }) {
  if (!status || status === 'na') return <span className="pill pill-gray">—</span>;
  if (status === 'expired') return <span className="pill pill-red">Expired</span>;
  if (status === 'warning') return <span className="pill pill-warn">&lt;30hr</span>;
  return <span className="pill pill-green">Valid</span>;
}

function StatusPill({ status }) {
  const s = (status||'').toUpperCase();
  if (s === 'AKTIF') return <span className="pill pill-green">Aktif</span>;
  if (s === 'NO COMPLY') return <span className="pill pill-red">No Comply</span>;
  if (s === 'NONAKTIF') return <span className="pill pill-gray">Nonaktif</span>;
  return <span className="pill pill-gray">{status||'—'}</span>;
}

function ColPickerModal({ cols, selected, onClose, onApply, title }) {
  const [sel, setSel] = useState([...selected]);
  function toggle(key) {
    setSel(prev => prev.includes(key) ? prev.filter(k=>k!==key) : [...prev, key]);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(520px, calc(100vw - 24px))',maxHeight:'80vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>⚙️ {title}</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <div style={{padding:'16px 20px'}}>
          <div style={{fontSize:11.5,color:'var(--muted)',marginBottom:16,padding:'8px 12px',background:'var(--bg3)',borderRadius:8}}>
            Kolom utama (No. Unit, Type, Operator/Driver) selalu tampil. Pilih kolom tambahan:
          </div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8}}>
            {cols.map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',
                  background:sel.includes(col.key)?'rgba(232,160,32,.15)':'var(--bg3)',
                  color:sel.includes(col.key)?'var(--accent)':'var(--muted2)',
                  border:`1px solid ${sel.includes(col.key)?'var(--accent)':'var(--border)'}`}}>
                {sel.includes(col.key)?'✓ ':''}{col.label}
              </div>
            ))}
          </div>
        </div>
        <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={()=>setSel([])}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Reset
          </button>
          <button type="button" onClick={onClose}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Batal
          </button>
          <button type="button" onClick={()=>{ onApply(sel); onClose(); }}
            style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            ✓ Terapkan
          </button>
        </div>
      </div>
    </div>
  );
}

// ── MODAL EQUIPMENT ──
function EquipmentModal({ mode, data, onClose, onSave }) {
  const empty = {
    no_unit:'', plat_nomor:'', type_unit:'', model:'', manufacture:'',
    serial_no:'', tahun:'', gps_unit_id:'', kategori:'', kapasitas:'',
    stnk_expired:'', tax_expired:'', kir_expired:'', izin_non_bm_expired:'',
    vehicle_pass_expired:'', inspection_date:'', smbr_pass_expired:'',
    green_stiker_expired:'', sio_migas_no:'', sio_migas_expired:'',
    sio_disnaker_expired:'', k3_p3a2_no:'', k3_p3a2_expired:'',
    tpe_cem_inspector:'', contractor_cem_inspector:'',
    location_of_inspection:'', status:'AKTIF', keterangan:'',
  };
  const [form, setForm] = useState(data || empty);
  const set = (k, v) => setForm(f => ({...f, [k]:v}));
  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)',
    color:'var(--text)', borderRadius:8, padding:'8px 11px',
    fontSize:12.5, fontFamily:"'Outfit',sans-serif",
    outline:'none', width:'100%',
  };

  const Section = ({ title, children }) => (
    <div style={{ marginBottom:18 }}>
      <div style={{ fontSize:10.5, fontWeight:700, color:'var(--accent)', textTransform:'uppercase', letterSpacing:'.08em', marginBottom:10, paddingBottom:6, borderBottom:'1px solid var(--border)' }}>{title}</div>
      <div className="form-grid-2" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px 14px' }}>{children}</div>
    </div>
  );

  const F = ({ label, k, type='text', opts }) => (
    <div>
      <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:3, display:'block' }}>{label}</label>
      {opts ? (
        <select style={inp} value={form[k]||''} onChange={e=>set(k,e.target.value)}>
          <option value="">— Pilih —</option>
          {opts.map(o=><option key={o} value={o}>{o}</option>)}
        </select>
      ) : (
        <input type={type} style={inp} value={form[k]||''} onChange={e=>set(k,e.target.value)} />
      )}
    </div>
  );

  return (
    <div style={{ position:'fixed', inset:0, zIndex:300, background:'rgba(0,0,0,.65)', display:'flex', alignItems:'center', justifyContent:'center' }}
      onClick={e => e.target===e.currentTarget && onClose()}>
      <div style={{ background:'var(--bg2)', border:'1px solid var(--border2)', borderRadius:16, width:'min(780px, calc(100vw - 24px))', maxHeight:'90vh', overflow:'auto', boxShadow:'0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 20px', borderBottom:'1px solid var(--border)', position:'sticky', top:0, background:'var(--bg2)', zIndex:10 }}>
          <div style={{ fontFamily:'Syne,sans-serif', fontSize:15, fontWeight:700, color:'var(--text)' }}>
            {mode==='add' ? '➕ Tambah Unit Equipment' : `✏️ Edit — ${data?.no_unit}`}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', fontSize:18, color:'var(--muted)' }}>✕</div>
        </div>
        <div style={{ padding:'18px 20px' }}>
          <Section title="🏗️ Identitas Unit">
            <F label="No. Unit AKM *" k="no_unit" />
            <F label="No. Polisi" k="plat_nomor" />
            <F label="Type Equipment" k="type_unit" opts={['DUMP TRUCK','EXCAVATOR TRACK','BULLDOZER','COMPACTOR SMOOTH DRUM','COMPACTOR PADFOOT','MOTOR GRADER','FUEL TRUCK','WATER TRUCK','MICROBUS','PRIME MOVER','LOW BOY','HI BOY','FOCO TRUCK','CRAWLER CRANE','PICK UP D.CABIN','PICK UP S.CABIN','MINIBUS','DOUBLE CABIN','WELDING MACHINE','DROP HAMMER']} />
            <F label="Model" k="model" />
            <F label="Manufacture" k="manufacture" opts={['MITSUBISHI','CATERPILLAR','KOMATSU','DOOSAN','VOLVO','DYNAPAC','TOYOTA','DAIHATSU','SUMITOMO','ISTIMEWA','HAND MADE','JCB','UD Truck GWE 410','FUSO']} />
            <F label="Serial No / No. Rangka" k="serial_no" />
            <F label="Tahun" k="tahun" type="number" />
            <F label="GPS Unit ID" k="gps_unit_id" />
            <F label="Kategori" k="kategori" opts={['HEAVY VEHICLE','LIGHT VEHICLE']} />
            <F label="Kapasitas" k="kapasitas" />
          </Section>
          <Section title="📄 Dokumen & Expired">
            <F label="STNK Expired" k="stnk_expired" type="date" />
            <F label="TAX/Pajak Expired" k="tax_expired" type="date" />
            <F label="KIR Expired" k="kir_expired" type="date" />
            <F label="Izin Non BM Expired" k="izin_non_bm_expired" type="date" />
            <F label="Vehicle Pass Expired" k="vehicle_pass_expired" type="date" />
            <F label="Date of Inspection" k="inspection_date" type="date" />
            <F label="SMBR Pass Expired (x6 bln)" k="smbr_pass_expired" type="date" />
            <F label="Green Stiker Expired" k="green_stiker_expired" type="date" />
          </Section>
          <Section title="🔒 SIO / K3">
            <F label="SIO Migas / K3 Disnaker No." k="sio_migas_no" />
            <F label="SIO Migas Expired" k="sio_migas_expired" type="date" />
            <F label="SIO Disnaker Expired" k="sio_disnaker_expired" type="date" />
            <F label="K3 P3A2 No." k="k3_p3a2_no" />
            <F label="K3 P3A2 Expired" k="k3_p3a2_expired" type="date" />
          </Section>
          <Section title="🔍 Inspeksi">
            <F label="TPE CEM Inspector" k="tpe_cem_inspector" />
            <F label="Contractor CEM Inspector" k="contractor_cem_inspector" />
            <F label="Location of Inspection" k="location_of_inspection" />
            <F label="Status" k="status" opts={['AKTIF','NONAKTIF','NO COMPLY']} />
            <div style={{ gridColumn:'1/-1' }}>
              <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:3, display:'block' }}>Keterangan</label>
              <textarea style={{...inp, minHeight:60, resize:'vertical'}} value={form.keterangan||''} onChange={e=>set('keterangan',e.target.value)} />
            </div>
          </Section>
        </div>
        <div style={{ display:'flex', gap:10, justifyContent:'flex-end', padding:'12px 20px', borderTop:'1px solid var(--border)', position:'sticky', bottom:0, background:'var(--bg2)' }}>
          <button onClick={onClose} style={{ padding:'9px 18px', borderRadius:8, border:'1px solid var(--border)', background:'var(--bg3)', color:'var(--muted2)', fontSize:12.5, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>Batal</button>
          <button onClick={() => { if(!form.no_unit.trim()){alert('No. Unit wajib diisi!');return;} onSave(form); }}
            style={{ padding:'9px 22px', borderRadius:8, border:'none', background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14', fontSize:12.5, fontWeight:700, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
            {mode==='add' ? '➕ Tambah' : '💾 Simpan'}
          </button>
        </div>
      </div>
    </div>
  );
}

function GantiOperatorModal({ equipment, employees, onClose }) {
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [confirm, setConfirm] = useState(false);
  const [loading, setLoading] = useState(false);

  const filtered = employees.filter(e =>
    e.nama_lengkap.toLowerCase().includes(search.toLowerCase()) ||
    (e.id_badge||'').toLowerCase().includes(search.toLowerCase())
  );

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)',
    color:'var(--text)', borderRadius:8, padding:'8px 11px',
    fontSize:12.5, fontFamily:"'Outfit',sans-serif",
    outline:'none', width:'100%',
  };

  function handleConfirm() {
    if (!selected) return;
    setLoading(true);
    router.post(`/equipment/${equipment.id}/assign-operator`,
      { employee_id: selected.id },
      {
        preserveScroll: true,
        onSuccess: () => { setLoading(false); onClose(); },
        onError: () => setLoading(false),
      }
    );
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(520px, calc(100vw - 24px))',maxHeight:'85vh',display:'flex',flexDirection:'column',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>

        {/* Header */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:10}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>🔄 Ganti Operator</div>
            <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>Unit: <b style={{color:'var(--text)'}}>{equipment.no_unit}</b>{equipment.operator && <> · Operator sekarang: <b style={{color:'var(--accent)'}}>{equipment.operator.operator_name}</b></>}</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>

        {!confirm ? (
          <>
            {/* Search */}
            <div style={{padding:'14px 20px 0'}}>
              <input style={inp} placeholder="🔍 Cari nama atau badge..." value={search} onChange={e=>setSearch(e.target.value)} />
            </div>

            {/* List karyawan */}
            <div style={{flex:1,overflowY:'auto',padding:'10px 20px',display:'flex',flexDirection:'column',gap:6}}>
              {filtered.length === 0 ? (
                <div style={{padding:24,textAlign:'center',color:'var(--muted)',fontSize:12}}>Tidak ada karyawan ditemukan</div>
              ) : filtered.map(emp => (
                <div key={emp.id} onClick={()=>setSelected(emp)}
                  style={{padding:'10px 14px',borderRadius:9,cursor:'pointer',transition:'all .15s',
                    background: selected?.id===emp.id ? 'rgba(34,201,122,.1)' : 'var(--bg3)',
                    border: `1px solid ${selected?.id===emp.id ? 'rgba(34,201,122,.4)' : 'var(--border)'}`,
                    display:'flex',alignItems:'center',gap:12}}>
                  <div style={{width:32,height:32,borderRadius:8,background:'rgba(58,143,224,.15)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,color:'var(--blue)',flexShrink:0}}>
                    {emp.nama_lengkap.split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase()}
                  </div>
                  <div style={{flex:1,minWidth:0}}>
                    <div style={{fontSize:12.5,fontWeight:600,color:'var(--text)'}}>{emp.nama_lengkap}</div>
                    <div style={{fontSize:11,color:'var(--muted)',marginTop:1}}>{emp.id_badge||'—'}{emp.rfid ? ` · RFID: ${emp.rfid}` : ''}</div>
                  </div>
                  {selected?.id===emp.id && <span style={{color:'#22C97A',fontSize:16}}>✓</span>}
                </div>
              ))}
            </div>

            {/* Footer */}
            <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
              <button onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
              <button disabled={!selected} onClick={()=>setConfirm(true)}
                style={{padding:'9px 22px',borderRadius:8,border:'none',background:selected?'linear-gradient(135deg,#22C97A,#148050)':'var(--bg3)',color:selected?'#fff':'var(--muted)',fontSize:12.5,fontWeight:700,cursor:selected?'pointer':'not-allowed',fontFamily:"'Outfit',sans-serif",opacity:selected?1:0.5}}>
                Lanjut →
              </button>
            </div>
          </>
        ) : (
          /* Konfirmasi */
          <div style={{padding:'24px 20px',display:'flex',flexDirection:'column',gap:16}}>
            <div style={{padding:'14px 16px',borderRadius:10,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.25)',fontSize:12.5,lineHeight:1.6}}>
              ⚠️ Kamu akan mengganti operator unit <b style={{color:'var(--text)'}}>{equipment.no_unit}</b> dari{' '}
              <b style={{color:'#E04545'}}>{equipment.operator?.operator_name || '(kosong)'}</b> ke{' '}
              <b style={{color:'#22C97A'}}>{selected.nama_lengkap}</b>.
              {equipment.operator && (
                <div style={{marginTop:8,fontSize:11.5,color:'var(--muted)'}}>
                  Operator lama akan dinonaktifkan dari unit ini. Data compliance-nya tetap tersimpan.
                </div>
              )}
            </div>
            <div style={{display:'flex',gap:10,justifyContent:'flex-end'}}>
              <button onClick={()=>setConfirm(false)} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>← Kembali</button>
              <button onClick={handleConfirm} disabled={loading}
                style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?0.7:1}}>
                {loading ? '⏳ Menyimpan...' : '✅ Ya, Ganti Operator'}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ── MODAL OPERATOR ──
function OperatorModal({ equipment, data, onClose, onSave }) {
  const empty = {
    operator_name:'', badge:'', license_no:'', license_expired_date:'',
    rfid:'', kp_no:'', kp_expired_date:'', cdrive_expired_date:'',
    postest_expired_date:'', permit_no:'', permit_expired_date:'',
    sio_migas_no:'', sio_migas_expired:'', sio_disnaker_expired:'',
    k3_p3a2_no:'', k3_p3a2_expired:'',
  };
  const [form, setForm] = useState(data || empty);
  const set = (k, v) => setForm(f => ({...f, [k]:v}));
  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)',
    color:'var(--text)', borderRadius:8, padding:'8px 11px',
    fontSize:12.5, fontFamily:"'Outfit',sans-serif",
    outline:'none', width:'100%',
  };

  return (
    <div style={{ position:'fixed', inset:0, zIndex:300, background:'rgba(0,0,0,.65)', display:'flex', alignItems:'center', justifyContent:'center' }}
      onClick={e => e.target===e.currentTarget && onClose()}>
      <div style={{ background:'var(--bg2)', border:'1px solid var(--border2)', borderRadius:16, width:'min(660px, calc(100vw - 24px))', maxHeight:'90vh', overflow:'auto', boxShadow:'0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 20px', borderBottom:'1px solid var(--border)', position:'sticky', top:0, background:'var(--bg2)', zIndex:10 }}>
          <div style={{ fontFamily:'Syne,sans-serif', fontSize:15, fontWeight:700 }}>
            👷 {data ? 'Edit Operator' : 'Tambah Operator'} — {equipment?.no_unit}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', fontSize:18, color:'var(--muted)' }}>✕</div>
        </div>
        <div className="form-grid-2" style={{ padding:'18px 20px', display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px 16px' }}>
          <div style={{ gridColumn:'1/-1' }}>
            <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:3, display:'block' }}>Nama Operator *</label>
            <input style={inp} value={form.operator_name||''} onChange={e=>set('operator_name',e.target.value)} placeholder="NAMA OPERATOR" />
          </div>
          {[
            {k:'badge',       l:'Badge ID',      p:'AKM-EW-0001'},
            {k:'license_no',  l:'License No',    p:'09227603000091'},
            {k:'rfid',        l:'RFID',          p:'2D9A70FB'},
            {k:'kp_no',       l:'KP No',         p:'2-2023-042-783'},
            {k:'permit_no',   l:'Permit No',     p:'N/A'},
            {k:'sio_migas_no',l:'SIO Migas No.', p:''},
            {k:'k3_p3a2_no',  l:'K3 P3A2 No.',  p:'500.15.18.1/PAPA/...'},
          ].map(f=>(
            <div key={f.k}>
              <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:3, display:'block' }}>{f.l}</label>
              <input style={inp} value={form[f.k]||''} onChange={e=>set(f.k,e.target.value)} placeholder={f.p} />
            </div>
          ))}
          {[
            {k:'license_expired_date', l:'License Expired'},
            {k:'kp_expired_date',      l:'KP Expired'},
            {k:'cdrive_expired_date',  l:'C-Drive Expired'},
            {k:'postest_expired_date', l:'Postest Expired'},
            {k:'permit_expired_date',  l:'Permit Expired'},
            {k:'sio_migas_expired',    l:'SIO Migas Expired'},
            {k:'sio_disnaker_expired', l:'SIO Disnaker Expired'},
            {k:'k3_p3a2_expired',      l:'K3 P3A2 Expired'},
          ].map(f=>(
            <div key={f.k}>
              <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:3, display:'block' }}>{f.l}</label>
              <input type="date" style={inp} value={form[f.k]||''} onChange={e=>set(f.k,e.target.value)} />
            </div>
          ))}
        </div>
        <div style={{ display:'flex', gap:10, justifyContent:'flex-end', padding:'12px 20px', borderTop:'1px solid var(--border)' }}>
          <button onClick={onClose} style={{ padding:'9px 18px', borderRadius:8, border:'1px solid var(--border)', background:'var(--bg3)', color:'var(--muted2)', fontSize:12.5, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>Batal</button>
          <button onClick={() => { if(!form.operator_name.trim()){alert('Nama wajib diisi!');return;} onSave(form); }}
            style={{ padding:'9px 22px', borderRadius:8, border:'none', background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14', fontSize:12.5, fontWeight:700, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
            💾 Simpan
          </button>
        </div>
      </div>
    </div>
  );
}

// ── MAIN COMPONENT ──
export default function EquipmentIndex({ equipment = {data:[],stats:{}}, filters = {}, employees = [], highlight = null }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [showImport, setShowImport] = useState(false);
  const [search, setSearch] = useState(filters.search || '');
  const [view,   setView]   = useState(filters.view || 'operator');
  const [type,   setType]   = useState(filters.type   || '');
  const [status, setStatus] = useState(filters.status || '');
  const [modal,  setModal]  = useState(null);
  const [selectedRow, setSelectedRow] = useState(null);
  const [confirmHapus, setConfirmHapus] = useState(null);
  const [showColPicker, setShowColPicker] = useState(false);
  const [activeOpCols, setActiveOpCols] = useState(() => {
    try { const s = localStorage.getItem('eq-op-cols'); return s ? JSON.parse(s) : DEFAULT_OPERATOR_COLS; }
    catch { return DEFAULT_OPERATOR_COLS; }
  });
  const [activeVehicleCols, setActiveVehicleCols] = useState(() => {
    try { const s = localStorage.getItem('eq-vehicle-cols'); return s ? JSON.parse(s) : DEFAULT_VEHICLE_COLS; }
    catch { return DEFAULT_VEHICLE_COLS; }
  });

  useEffect(() => {
    const highlightId = highlight ? String(highlight) : null;
    if (!highlightId) return;

    const timer = setTimeout(() => {
      const row = document.querySelector(`tr[data-id="${highlightId}"]`);
      if (!row) return;

      row.scrollIntoView({ behavior: 'smooth', block: 'center' });

      row.style.transition = 'background 0.3s';
      row.style.background = 'rgba(232,160,32,.35)';
      setTimeout(() => {
        row.style.background = 'rgba(232,160,32,.15)';
        setTimeout(() => { row.style.background = ''; }, 2500);
      }, 600);

      const url = new URL(window.location.href);
      url.searchParams.delete('highlight');
      window.history.replaceState({}, '', url.toString());
    }, 400);

    return () => clearTimeout(timer);
  }, [highlight]);

  useEffect(() => {
    setView(filters.view || 'operator');
  }, [filters.view]);

  function applyOpCols(cols)      { setActiveOpCols(cols);      localStorage.setItem('eq-op-cols', JSON.stringify(cols)); }
  function applyVehicleCols(cols) { setActiveVehicleCols(cols); localStorage.setItem('eq-vehicle-cols', JSON.stringify(cols)); }

  const activeCols    = view === 'operator' ? activeOpCols : activeVehicleCols;
  const applyColsFn   = view === 'operator' ? applyOpCols  : applyVehicleCols;
  const colDefs       = view === 'operator' ? OPERATOR_COLS : VEHICLE_COLS;

  // ── DUAL SCROLLBAR REFS ──
  const topScrollRef  = useRef(null);
  const tableWrapRef  = useRef(null);
  const isSyncingTop  = useRef(false);
  const isSyncingTbl  = useRef(false);

  function onTopScroll() {
    if (isSyncingTop.current) return;
    isSyncingTbl.current = true;
    if (tableWrapRef.current) tableWrapRef.current.scrollLeft = topScrollRef.current.scrollLeft;
    isSyncingTbl.current = false;
  }

  function onTableScroll() {
    if (isSyncingTbl.current) return;
    isSyncingTop.current = true;
    if (topScrollRef.current) topScrollRef.current.scrollLeft = tableWrapRef.current.scrollLeft;
    isSyncingTop.current = false;
  }

  // lebar konten tabel sesuai tab aktif
  const tableWidth = view === 'operator' ? 2840 : 4100;

    function doFilter(searchVal, typeVal, statusVal, viewVal) {
        router.get('/equipment', {search:searchVal,type:typeVal,status:statusVal,view:viewVal||view}, {preserveState:true,replace:true});
    }

  function handleSaveEquipment(form) {
    if (modal.mode === 'eq_add') {
      router.post('/equipment', form, { onSuccess:()=>setModal(null) });
    } else {
      router.put(`/equipment/${modal.data.id}`, form, { onSuccess:()=>setModal(null) });
    }
  }

  function handleSaveOperator(form) {
    if (modal.mode === 'op_add') {
      router.post(`/equipment/${modal.equipment.id}/operator`, form, { onSuccess:()=>setModal(null) });
    } else {
      router.put(`/equipment/${modal.equipment.id}/operator/${modal.data.id}`, form, { onSuccess:()=>setModal(null) });
    }
  }

  const data     = equipment.data      || [];
  const total    = equipment.total     || 0;
  const stats    = equipment.stats     || {};
  const typeList = equipment.type_list || [];
  const prevUrl  = equipment.prev_page_url;
  const nextUrl  = equipment.next_page_url;
  const links    = (equipment.links||[]).filter(l => l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;');

  const thStyle = (minWidth, extra={}) => ({
    fontSize:10, color:'var(--muted)', textTransform:'uppercase',
    letterSpacing:'.06em', fontWeight:600, textAlign:'left',
    padding:'10px 8px', borderBottom:'1px solid var(--border)',
    background:'var(--card)', whiteSpace:'nowrap', minWidth, ...extra,
  });

  const tdStyle = (extra={}) => ({
    padding:'10px 8px', borderBottom:'1px solid rgba(128,128,128,.06)',
    fontSize:12, verticalAlign:'middle', color:'var(--text)', ...extra,
  });

  const AksiButtonsOperator = ({ eq }) => (
    <div style={{display:'flex',gap:4,justifyContent:'center'}}>
      {!isViewer && (
        <button onClick={()=>setModal(eq.operator
          ? {mode:'op_assign', equipment:eq}
          : {mode:'op_add', equipment:eq, data:null}
        )}
          style={{padding:'3px 7px',borderRadius:6,fontSize:11,fontWeight:600,
            background: eq.operator ? 'rgba(232,160,32,.12)' : 'rgba(34,201,122,.1)',
            color: eq.operator ? 'var(--accent)' : 'var(--green)',
            border: eq.operator ? '1px solid rgba(232,160,32,.25)' : '1px solid rgba(34,201,122,.2)',
            cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap'}}>
          {eq.operator ? '🔄 Ganti Operator' : '➕ Assign Operator'}
        </button>
      )}
      {!isViewer && eq.operator && (
        <button onClick={()=>setModal({mode:'op_edit',equipment:eq,data:eq.operator})}
          style={{padding:'3px 7px',borderRadius:6,fontSize:11,fontWeight:600,
            background:'rgba(58,143,224,.1)',color:'var(--blue)',
            border:'1px solid rgba(58,143,224,.25)',
            cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap'}}>
          ✏️ Edit Operator
        </button>
      )}
      {!isViewer && eq.operator && (
        <button onClick={()=>setConfirmHapus({
          type: 'operator',
          id: eq.operator.id,
          equipment_id: eq.id,
          operator_name: eq.operator.operator_name,
          no_unit: eq.no_unit,
        })}
          style={{padding:'3px 7px',borderRadius:6,fontSize:11,fontWeight:600,
            background:'rgba(224,69,69,.1)',color:'#E04545',
            border:'1px solid rgba(224,69,69,.2)',
            cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap'}}>
          🗑️ Hapus Operator
        </button>
      )}
    </div>
  );

  const AksiButtonsVehicle = ({ eq }) => (
    <div style={{display:'flex',gap:4,justifyContent:'center'}}>
      {!isViewer && (
        <button onClick={()=>setModal({mode:'eq_edit',data:eq})}
          style={{padding:'3px 7px',borderRadius:6,fontSize:11,fontWeight:600,
            background:'rgba(232,160,32,.12)',color:'var(--accent)',
            border:'1px solid rgba(232,160,32,.25)',
            cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap'}}>
          ✏️ Edit Unit
        </button>
      )}
      {!isViewer && (
        <button onClick={()=>setConfirmHapus({
          type: 'unit',
          id: eq.id,
          no_unit: eq.no_unit,
        })}
          style={{padding:'3px 7px',borderRadius:6,fontSize:11,fontWeight:600,
            background:'rgba(224,69,69,.1)',color:'#E04545',
            border:'1px solid rgba(224,69,69,.2)',
            cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap'}}>
          🗑️ Hapus Unit
        </button>
      )}
    </div>
  );

return (
    <AppLayout title="Equipment &" subtitle="Operator">
      {/* MODALS */}
      {(modal?.mode==='eq_add'||modal?.mode==='eq_edit') && (
        <EquipmentModal mode={modal.mode==='eq_add'?'add':'edit'} data={modal.data} onClose={()=>setModal(null)} onSave={handleSaveEquipment} />
      )}
      {showImport && (
        <ImportModal
          onClose={()=>setShowImport(false)}
          importUrl="/equipment/import"
          templateUrl="/equipment/template-import"
          title="Import Equipment & Operator"
        />
      )}
      {showColPicker && (
        <ColPickerModal
          cols={colDefs}
          selected={activeCols}
          onClose={()=>setShowColPicker(false)}
          onApply={applyColsFn}
          title={`Pilih Kolom — ${view==='operator'?'Operator & Driver':'Equipment & Vehicle'}`}
        />
      )}
      {(modal?.mode==='op_add'||modal?.mode==='op_edit') && (
        <OperatorModal equipment={modal.equipment} data={modal.data} onClose={()=>setModal(null)} onSave={handleSaveOperator} />
      )}

      <ConfirmModal
        open={!!confirmHapus}
        onCancel={()=>setConfirmHapus(null)}
        onConfirm={()=>{
          const d = confirmHapus;
          setConfirmHapus(null);
          if (d.type === 'unit') {
            router.delete(`/equipment/${d.id}`, {
              preserveScroll: true,
              preserveState: true,
              onSuccess: () => doFilter(search, type, status),
            });
          } else {
            router.delete(`/equipment/${d.equipment_id}/operator/${d.id}`, {
              preserveScroll: true,
              preserveState: true,
              onSuccess: () => doFilter(search, type, status),
            });
          }
        }}
        title={confirmHapus?.type === 'unit' ? 'Hapus Unit Equipment' : 'Hapus Operator'}
        message={confirmHapus?.type === 'unit'
          ? <>Unit <b style={{color:'var(--text)'}}>{confirmHapus?.no_unit}</b> beserta <b style={{color:'#E04545'}}>semua data operator</b> di unit ini akan dihapus permanen.</>
          : <>Operator <b style={{color:'var(--text)'}}>{confirmHapus?.operator_name}</b> akan dihapus dari unit <b style={{color:'var(--text)'}}>{confirmHapus?.no_unit}</b>. Unit tetap ada, hanya data operator yang dihapus.</>
        }
        confirmLabel="Ya, Hapus"
        type="danger"
      />

      {modal?.mode==='op_assign' && (
        <GantiOperatorModal
          equipment={modal.equipment}
          employees={employees}
          onClose={()=>setModal(null)}
        />
      )}

      {/* ── STATS ── */}
      <div className="stat-grid-6" style={{ display:'grid', gridTemplateColumns:'repeat(6,1fr)', gap:12, marginBottom:20 }}>
        {[
          {l:'Total Unit',       v:stats.total||0,                c:'var(--blue)',   href:'/equipment'},
          {l:'Aktif',            v:stats.aktif||0,                c:'var(--green)',  href:'/equipment?status=AKTIF'},
          {l:'No Comply',        v:stats.no_comply||0,            c:'var(--red)',    href:'/equipment?status=NO COMPLY'},
          {l:'STNK Expired',     v:stats.stnk_expired||0,         c:'var(--red)',    href:'/equipment'},
          {l:'KIR Expired',      v:stats.kir_expired||0,          c:'var(--accent)', href:'/equipment'},
          {l:'Vehicle Pass Exp', v:stats.vehicle_pass_expired||0, c:'var(--accent)', href:'/equipment'},
        ].map((st,i)=>(
          <div key={i} className="stat" onClick={()=>router.visit(st.href)}
            style={{cursor:'pointer',textAlign:'center',padding:'14px 10px'}}>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:st.c}}>{st.v}</div>
            <div style={{fontSize:10,color:'var(--muted)',marginTop:4}}>{st.l}</div>
          </div>
        ))}
      </div>

      {/* ── PANEL HEAD — tidak ikut scroll ── */}
      <div style={{
        background:'var(--card)',
        border:'1px solid var(--border)',
        borderRadius:'12px 12px 0 0',
        padding:'14px 16px',
      }}>
        {/* Title + Controls */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:14,flexWrap:'wrap',gap:8}}>
          <div style={{fontSize:13,fontWeight:600,color:'var(--text)'}}>
            🚜 Equipment & Operator — PT. Andalas Karya Mulia
          </div>
          <div style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap'}}>
            <select value={type} onChange={e=>{setType(e.target.value);doFilter(search,e.target.value,status);}}>
              <option value="">Semua Type</option>
              {typeList.map(t=><option key={t} value={t}>{t}</option>)}
            </select>
            <select value={status} onChange={e=>{setStatus(e.target.value);doFilter(search,type,e.target.value);}}>
              <option value="">Semua Status</option>
              <option value="AKTIF">Aktif</option>
              <option value="NO COMPLY">No Comply</option>
              <option value="NONAKTIF">Nonaktif</option>
            </select>
            {!isViewer && view==='vehicle' && (
              <button onClick={()=>setModal({mode:'eq_add'})}
                style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                ➕ Tambah Unit
              </button>
            )}
            {!isViewer && (
              <button onClick={()=>setShowImport(true)}
                style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                📥 Import Equipment
              </button>
            )}
            <button onClick={()=>setShowColPicker(true)}
              style={{padding:'6px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}>
              ⚙️ Kolom
            </button>
            <a href="/export/equipment-unit"
              style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif"}}>
              📤 Export Unit
            </a>
            <a href="/export/equipment-operator"
              style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif"}}>
              📤 Export Operator
            </a>
          </div>
        </div>

        {/* Search */}
        <div style={{position:'relative',marginBottom:12}}>
          <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',fontSize:15,color:'var(--muted)'}}>🔍</span>
          <input className="search-input" type="text" value={search}
            placeholder="Cari no. unit, plat, model, operator..."
            onChange={e=>{setSearch(e.target.value);doFilter(e.target.value,type,status);}}
            style={{paddingLeft:'36px'}}
          />
        </div>

        {/* Tab Switcher */}
        <div style={{display:'flex',gap:4}}>
            <div className={`tab ${view==='operator'?'active':''}`}
            onClick={()=>{ setView('operator'); doFilter(search,type,status,'operator'); }}
            style={{cursor:'pointer'}}>
            👷 Operator & Driver
            </div>
            <div className={`tab ${view==='vehicle'?'active':''}`}
            onClick={()=>{ setView('vehicle'); doFilter(search,type,status,'vehicle'); }}
            style={{cursor:'pointer'}}>
            🚜 Equipment & Vehicle
            </div>
        </div>
        {view === 'operator' && (
        <div style={{
          fontSize:11, color:'var(--muted)', marginTop:10,
          padding:'8px 12px', borderRadius:7,
          background:'var(--bg3)', border:'1px solid var(--border)',
          display:'flex', alignItems:'center', gap:8,
        }}>
          <span style={{fontSize:14}}>💡</span>
          <span>
            Untuk ganti operator, klik{' '}
            <span style={{
              background:'rgba(34,201,122,.1)', color:'var(--green)',
              border:'1px solid rgba(34,201,122,.2)', borderRadius:5,
              padding:'1px 7px', fontSize:11, fontWeight:600,
            }}>🔄 Ganti Operator</span>{' '}
            di kolom Aksi. Untuk edit data operator aktif, klik{' '}
            <span style={{
              background:'rgba(58,143,224,.1)', color:'var(--blue)',
              border:'1px solid rgba(58,143,224,.2)', borderRadius:5,
              padding:'1px 7px', fontSize:11, fontWeight:600,
            }}>✏️ Edit Operator</span>
          </span>
        </div>
      )}
      </div>
      {/* ── PENUTUP PANEL HEAD ── */}

      {/* ── TABEL — scroll horizontal ── */}
        <div
        ref={tableWrapRef}
        onScroll={onTableScroll}
        className="hide-scrollbar"
        style={{
            background:'var(--card)',
            border:'1px solid var(--border)',
            borderTop:'none',
            overflowX:'auto',
            paddingBottom:20,
            scrollbarWidth:'none',
            msOverflowStyle:'none',
        }}
        >
        {/* TAB 1: OPERATOR / DRIVER */}
        {view==='operator' && (
          <table style={{width:'100%',borderCollapse:'collapse',minWidth:2240}}>
            <thead>
              <tr>
                <th style={thStyle(130)}>No. Unit</th>
                <th style={thStyle(110)}>Type</th>
                <th style={thStyle(180)}>Operator / Driver</th>
                {activeOpCols.map(k => {
                  const col = OPERATOR_COLS.find(c=>c.key===k);
                  return <th key={k} style={thStyle(130)}>{col?.label||k}</th>;
                })}
                {!isViewer && <th style={thStyle(100,{textAlign:'center'})}>Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {data.map((eq,i)=>{
                const op = eq.operator;
                return (
                  <tr key={eq.id||i} data-id={eq.id}
                    onClick={()=>setSelectedRow(selectedRow===`op-${eq.id||i}` ? null : `op-${eq.id||i}`)}
                    onMouseEnter={e=>{ if(selectedRow!==`op-${eq.id||i}`) Array.from(e.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.06)'); }}
                    onMouseLeave={e=>{ if(selectedRow!==`op-${eq.id||i}`) Array.from(e.currentTarget.cells).forEach(c=>c.style.background=''); }}
                    style={{cursor:'pointer', outline: selectedRow===`op-${eq.id||i}` ? '2px solid rgba(232,160,32,.6)' : 'none', outlineOffset:'-1px', background: selectedRow===`op-${eq.id||i}` ? 'rgba(232,160,32,.1)' : ''}}>
                    <td style={tdStyle()}>
                      <div style={{fontFamily:'monospace',fontSize:11.5,fontWeight:600,color:'var(--accent)'}}>{eq.no_unit}</div>
                      <div style={{fontSize:10,color:'var(--muted)'}}>{eq.plat_nomor||'N/A'}</div>
                    </td>
                    <td style={tdStyle()}>
                      <span style={{fontSize:11,background:'rgba(58,143,224,.1)',color:'var(--blue)',padding:'2px 8px',borderRadius:99,fontWeight:600,whiteSpace:'nowrap'}}>
                        {eq.type_unit||'—'}
                      </span>
                    </td>
                    <td style={tdStyle()}>
                      {op ? (
                        <div>
                          <div style={{fontSize:12,fontWeight:500}}>{op.operator_name}</div>
                          <div style={{fontSize:10,color:'var(--accent)',fontFamily:'monospace'}}>{op.badge||'—'}</div>
                        </div>
                      ) : <span style={{fontSize:11,color:'var(--muted)'}}>— Belum ada —</span>}
                    </td>
                    {activeOpCols.map(k => {
                      const val = op?.[k];
                      const isDate = k.endsWith('_date') || k.endsWith('_expired');
                      return (
                        <td key={k} style={tdStyle({whiteSpace:isDate?'nowrap':undefined,fontSize:11})}>
                          {isDate ? fmtDate(val) : (val||'—')}
                        </td>
                        );
                        })}
                        {!isViewer && <td style={tdStyle({textAlign:'center'})}><AksiButtonsOperator eq={eq}/></td>}
                  </tr>
                );
              })}
              {data.length===0 && (
                <tr><td colSpan={isViewer?14:15} style={{padding:32,textAlign:'center',color:'var(--muted)'}}>Tidak ada data equipment</td></tr>
              )}
            </tbody>
          </table>
        )}

        {/* TAB 2: EQUIPMENT & VEHICLE */}
        {view==='vehicle' && (
          <table style={{width:'100%',borderCollapse:'collapse',minWidth:3900}}>
            <thead>
              <tr>
                <th style={thStyle(130)}>No. Unit</th>
                <th style={thStyle(110)}>Type</th>
                <th style={thStyle(80)}>Status</th>
                {activeVehicleCols.map(k => {
                  const col = VEHICLE_COLS.find(c=>c.key===k);
                  return <th key={k} style={thStyle(130)}>{col?.label||k}</th>;
                })}
                {!isViewer && <th style={thStyle(100,{textAlign:'center'})}>Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {data.map((eq,i)=>(
                <tr key={eq.id||i} data-id={eq.id}
                  onClick={()=>setSelectedRow(selectedRow===`veh-${eq.id||i}` ? null : `veh-${eq.id||i}`)}
                  onMouseEnter={e=>{ if(selectedRow!==`veh-${eq.id||i}`) Array.from(e.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.06)'); }}
                  onMouseLeave={e=>{ if(selectedRow!==`veh-${eq.id||i}`) Array.from(e.currentTarget.cells).forEach(c=>c.style.background=''); }}
                  style={{cursor:'pointer', outline: selectedRow===`veh-${eq.id||i}` ? '2px solid rgba(232,160,32,.6)' : 'none', outlineOffset:'-1px', background: selectedRow===`veh-${eq.id||i}` ? 'rgba(232,160,32,.1)' : ''}}>
                  <td style={tdStyle()}>
                    <div style={{fontFamily:'monospace',fontSize:11.5,fontWeight:600,color:'var(--accent)'}}>{eq.no_unit}</div>
                    <div style={{fontSize:10,color:'var(--muted)'}}>{eq.plat_nomor||'N/A'}</div>
                  </td>
                  <td style={tdStyle()}>
                    <span style={{fontSize:11,background:'rgba(58,143,224,.1)',color:'var(--blue)',padding:'2px 8px',borderRadius:99,fontWeight:600,whiteSpace:'nowrap'}}>
                      {eq.type_unit||'—'}
                    </span>
                  </td>
                  <td style={tdStyle()}><StatusPill status={eq.status}/></td>
                  {activeVehicleCols.map(k => {
                    const val = eq[k];
                    const isDate = k.endsWith('_expired') || k === 'inspection_date';
                    const isStatus = ['stnk_expired','kir_expired','vehicle_pass_expired'].includes(k);
                    const statusKey = k === 'stnk_expired' ? 'stnk_status'
                                    : k === 'kir_expired'  ? 'kir_status'
                                    : k === 'vehicle_pass_expired' ? 'vehicle_pass_status' : null;
                    return (
                      <td key={k} style={tdStyle({whiteSpace:isDate?'nowrap':undefined,fontSize:11.5})}>
                        {isDate ? (
                          <>
                            <div>{fmtDate(val)}</div>
                            {isStatus && statusKey && <DocPill status={eq[statusKey]}/>}
                          </>
                        ) : (val||'—')}
                      </td>
                    );
                  })}
                  {!isViewer && <td style={tdStyle({textAlign:'center'})}><AksiButtonsVehicle eq={eq}/></td>}
                </tr>
              ))}
              {data.length===0 && (
                <tr><td colSpan={isViewer?20:21} style={{padding:32,textAlign:'center',color:'var(--muted)'}}>Tidak ada data equipment</td></tr>
              )}
            </tbody>
          </table>
        )}
      </div>
      {/* ── PENUTUP DIV TABEL ── */}

      {/* ── SCROLLBAR FIXED NEMPEL DI BAWAH LAYAR ── */}
      <div
        ref={topScrollRef}
        onScroll={onTopScroll}
        className="equipment-scrollbar"
        style={{
          position:'fixed',
          bottom:0,
          left:220,
          right:0,
          height:16,
          overflowX:'auto',
          overflowY:'hidden',
          zIndex:50,
          background:'var(--card)',
          borderTop:'1px solid var(--border)',
        }}
      >
        <div style={{ width: tableWidth, height:1 }} />
      </div>

      {/* ── PAGINATION — tidak scroll ── */}
      <div style={{
        background:'var(--card)',
        border:'1px solid var(--border)',
        borderTop:'none',
        borderRadius:'0 0 12px 12px',
        padding:'12px 16px',
        marginBottom:20,
        display:'flex',
        alignItems:'center',
        justifyContent:'space-between',
      }}>
        <div style={{fontSize:11,color:'var(--muted)'}}>
          Menampilkan {data.length} dari {total} unit
        </div>
        <div style={{display:'flex',gap:4}}>
          <button disabled={!prevUrl} onClick={()=>prevUrl&&router.get(prevUrl, {view}, {preserveState:true})}
            style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:prevUrl?'var(--text)':'var(--muted)',cursor:prevUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>
            ← Prev
          </button>
          {links.map((l,i)=>(
            <button key={i} disabled={!l.url} onClick={()=>l.url&&router.get(l.url, {view}, {preserveState:true})}
              style={{padding:'5px 10px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:l.active?'var(--accent)':'var(--card)',color:l.active?'#0C0F14':l.url?'var(--text)':'var(--muted)',cursor:l.url?'pointer':'default',fontFamily:"'Outfit',sans-serif",fontWeight:l.active?700:400}}>
              {l.label}
            </button>
          ))}
          <button disabled={!nextUrl} onClick={()=>nextUrl&&router.get(nextUrl, {view}, {preserveState:true})}
            style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:nextUrl?'var(--text)':'var(--muted)',cursor:nextUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>
            Next →
          </button>
        </div>
      </div>

    </AppLayout>
  );
}
