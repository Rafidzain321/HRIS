// resources>js>Pages>Employee>Edit.jsx
import React, { useState, useEffect } from 'react';
import { useForm, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DocumentPanel from '@/Components/DocumentPanel';
import TrainingPanel from '@/Components/TrainingPanel';
import axios from 'axios';

function ComboBox({ value, onChange, options, placeholder, inputStyle }) {
  const [open,    setOpen]    = React.useState(false);
  const [search,  setSearch]  = React.useState('');
  const ref = React.useRef(null);
 
  // Tutup dropdown kalau klik di luar
  React.useEffect(() => {
    function handler(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);
 
  // Sinkronisasi search input dengan value dari luar
  React.useEffect(() => { setSearch(value || ''); }, [value]);
 
  const filtered = options.filter(o =>
    o.toLowerCase().includes(search.toLowerCase())
  );
 
  // Kalau search tidak kosong dan tidak ada di options → anggap custom value
  const showCustom = search && !options.find(o => o.toLowerCase() === search.toLowerCase());
 
  function select(val) {
    onChange(val);
    setSearch(val);
    setOpen(false);
  }
 
  return (
    <div ref={ref} style={{ position:'relative' }}>
      <div style={{ position:'relative' }}>
        <input
          style={{ ...inputStyle, paddingRight: 32 }}
          value={search}
          placeholder={placeholder}
          onChange={e => { setSearch(e.target.value); onChange(e.target.value); setOpen(true); }}
          onFocus={() => setOpen(true)}
        />
        {/* Panah dropdown */}
        <div
          onClick={() => setOpen(o => !o)}
          style={{
            position:'absolute', right:10, top:'50%', transform:'translateY(-50%)',
            cursor:'pointer', color:'var(--muted)', fontSize:11, userSelect:'none',
          }}
        >
          {open ? '▲' : '▼'}
        </div>
      </div>
 
      {/* Dropdown list */}
      {open && (
        <div style={{
          position:'absolute', top:'calc(100% + 4px)', left:0, right:0,
          background:'var(--bg2)', border:'1px solid var(--border2)',
          borderRadius:8, boxShadow:'0 8px 24px rgba(0,0,0,.25)',
          zIndex:100, maxHeight:200, overflowY:'auto',
        }}>
          {/* Opsi custom kalau ketik nilai baru */}
          {showCustom && (
            <div
              onClick={() => select(search)}
              style={{
                padding:'8px 12px', cursor:'pointer', fontSize:12.5,
                color:'var(--accent)', borderBottom:'1px solid var(--border)',
                background:'rgba(232,160,32,.06)',
              }}
              onMouseEnter={e => e.currentTarget.style.background = 'rgba(232,160,32,.12)'}
              onMouseLeave={e => e.currentTarget.style.background = 'rgba(232,160,32,.06)'}
            >
              ➕ Gunakan "<b>{search}</b>"
            </div>
          )}
 
          {/* Opsi dari list */}
          {filtered.length === 0 && !showCustom && (
            <div style={{ padding:'10px 12px', color:'var(--muted)', fontSize:12 }}>
              Tidak ada opsi — ketik untuk tambah custom
            </div>
          )}
          {filtered.map(opt => (
            <div
              key={opt}
              onClick={() => select(opt)}
              style={{
                padding:'8px 12px', cursor:'pointer', fontSize:12.5,
                background: opt === value ? 'rgba(232,160,32,.1)' : 'transparent',
                color: opt === value ? 'var(--accent)' : 'var(--text)',
                fontWeight: opt === value ? 600 : 400,
              }}
              onMouseEnter={e => { if (opt !== value) e.currentTarget.style.background = 'var(--bg3)'; }}
              onMouseLeave={e => { if (opt !== value) e.currentTarget.style.background = 'transparent'; }}
            >
              {opt}
              {opt === value && <span style={{ marginLeft:6, fontSize:10 }}>✓</span>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function Field({ label, children }) {
  return (
    <div style={{ marginBottom:16 }}>
      <label>{label}</label>
      {children}
    </div>
  );
}

function Section({ title, children, fullWidth=false }) {
  return (
    <div style={{ marginBottom:24 }}>
      <div style={{ fontSize:12, fontWeight:600, color:'var(--accent)', textTransform:'uppercase', letterSpacing:'.08em', marginBottom:14, paddingBottom:8, borderBottom:'1px solid var(--border)' }}>{title}</div>
      <div className="form-grid-2" style={{ display:'grid', gridTemplateColumns: fullWidth ? '1fr' : '1fr 1fr', gap:'0 20px' }}>
        {children}
      </div>
    </div>
  );
}

// ── MODAL TAMBAH SP ──
function TambahSpModal({ employeeId, onClose, onSaved }) {
  const [form, setForm] = useState({ tipe_sp:'SP1', tanggal_sp:'', alasan:'', catatan:'' });
  const [loading, setLoading] = useState(false);
  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
  };
  function submit(e) {
    e.preventDefault();
    if (!form.tanggal_sp || !form.alasan.trim()) { alert('Tanggal dan alasan wajib diisi!'); return; }
    setLoading(true);
    router.post('/employees/sp', { ...form, employee_id: employeeId }, {
      onSuccess: () => { setLoading(false); onSaved(); onClose(); },
      onError:   () => setLoading(false),
    });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.75)',display:'flex',alignItems:'center',justifyContent:'center'}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(480px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,color:'#E04545'}}>⚠️ Tambah Surat Peringatan</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tipe SP *</label>
              <select style={inp} value={form.tipe_sp} onChange={e=>setForm(f=>({...f,tipe_sp:e.target.value}))}>
                {['SP1','SP2','SP3','SKORSING','PHK'].map(v=><option key={v} value={v}>{v}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal SP *</label>
              <input type="date" style={inp} value={form.tanggal_sp} onChange={e=>setForm(f=>({...f,tanggal_sp:e.target.value}))} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Alasan / Pelanggaran *</label>
              <textarea style={{...inp,minHeight:80,resize:'vertical'}} value={form.alasan}
                placeholder="Jelaskan alasan pemberian SP..."
                onChange={e=>setForm(f=>({...f,alasan:e.target.value}))} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan Tambahan</label>
              <textarea style={{...inp,minHeight:60,resize:'vertical'}} value={form.catatan}
                placeholder="Opsional..."
                onChange={e=>setForm(f=>({...f,catatan:e.target.value}))} />
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose}
              style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading}
              style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E04545,#A03030)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?'⏳...':'⚠️ Simpan SP'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── SECTION SP ──
function SpSection({ employeeId }) {
  const [spList,   setSpList]   = useState([]);
  const [loading,  setLoading]  = useState(true);
  const [showAdd,  setShowAdd]  = useState(false);

  function load() {
    setLoading(true);
    axios.get(`/employees/${employeeId}/history`)
      .then(r => { setSpList(r.data.sp || []); })
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [employeeId]);

  function hapusSp(id) {
    if (!window.confirm('Hapus catatan SP ini?')) return;
    router.delete(`/employees/sp/${id}`, {
      preserveScroll: true,
      onSuccess: () => load(),
    });
  }

  const SP_COLOR = {
    SP1:      { bg:'rgba(232,160,32,.12)',  color:'var(--accent)' },
    SP2:      { bg:'rgba(224,106,32,.12)', color:'#E06A20' },
    SP3:      { bg:'rgba(224,69,69,.12)',  color:'#E04545' },
    SKORSING: { bg:'rgba(155,89,182,.12)', color:'#9B59B6' },
    PHK:      { bg:'rgba(224,69,69,.2)',   color:'#C00000' },
  };

  return (
    <>
      {showAdd && <TambahSpModal employeeId={employeeId} onClose={()=>setShowAdd(false)} onSaved={load} />}
      <div style={{marginBottom:24}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:14,paddingBottom:8,borderBottom:'1px solid var(--border)'}}>
          <div style={{fontSize:12,fontWeight:600,color:'#E04545',textTransform:'uppercase',letterSpacing:'.08em'}}>
            ⚠️ Surat Peringatan (SP)
          </div>
          <button type="button" onClick={()=>setShowAdd(true)}
            style={{padding:'5px 12px',borderRadius:7,border:'1px solid rgba(224,69,69,.3)',background:'rgba(224,69,69,.08)',color:'#E04545',fontSize:11.5,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            ➕ Tambah SP
          </button>
        </div>

        {loading ? (
          <div style={{padding:12,textAlign:'center',color:'var(--muted)',fontSize:12}}>⏳ Memuat...</div>
        ) : spList.length === 0 ? (
          <div style={{padding:16,textAlign:'center',color:'var(--muted)',fontSize:12,borderRadius:8,border:'1px dashed var(--border)'}}>
            Tidak ada catatan SP
          </div>
        ) : (
          <div style={{display:'flex',flexDirection:'column',gap:8}}>
            {spList.map((sp,i) => {
              const sc = SP_COLOR[sp.tipe_sp] || SP_COLOR.SP1;
              return (
                <div key={i} style={{padding:'12px 14px',borderRadius:9,background:'var(--bg3)',border:'1px solid var(--border)'}}>
                  <div style={{display:'flex',alignItems:'flex-start',justifyContent:'space-between',gap:10}}>
                    <div style={{display:'flex',alignItems:'center',gap:10}}>
                      <span style={{padding:'3px 10px',borderRadius:99,fontSize:11,fontWeight:700,background:sc.bg,color:sc.color}}>
                        {sp.tipe_sp}
                      </span>
                      <span style={{fontSize:12,fontWeight:600,color:'var(--text)'}}>{sp.tanggal_sp}</span>
                    </div>
                    <button type="button" onClick={()=>hapusSp(sp.id)}
                      style={{padding:'2px 8px',borderRadius:5,fontSize:11,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",flexShrink:0}}>
                      🗑️
                    </button>
                  </div>
                  <div style={{fontSize:12.5,color:'var(--text)',marginTop:8,lineHeight:1.5}}>{sp.alasan}</div>
                  {sp.catatan && <div style={{fontSize:11.5,color:'var(--muted)',marginTop:4,fontStyle:'italic'}}>{sp.catatan}</div>}
                  <div style={{fontSize:10.5,color:'var(--muted)',marginTop:6}}>Dicatat oleh: {sp.dibuat_oleh} · {sp.created_at}</div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}

// ── SECTION HISTORY KELUAR ──
function HistoryKeluarSection({ employeeId }) {
  const [logs,    setLogs]    = useState([]);
  const [loading, setLoading] = useState(true);

  function load() {
    setLoading(true);
    axios.get(`/employees/${employeeId}/history`)
      .then(r => { setLogs(r.data.termLog || []); })
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, [employeeId]);

  const ALASAN_COLOR = {
    'RESIGN':         { bg:'rgba(58,143,224,.1)',  color:'var(--blue)' },
    'PHK':            { bg:'rgba(224,69,69,.1)',   color:'#E04545' },
    'KONTRAK HABIS':  { bg:'rgba(232,160,32,.1)',  color:'var(--accent)' },
    'MENINGGAL DUNIA':{ bg:'rgba(128,128,128,.1)', color:'var(--muted2)' },
    'MUTASI':         { bg:'rgba(34,201,122,.1)',  color:'var(--green)' },
    'LAINNYA':        { bg:'rgba(128,128,128,.1)', color:'var(--muted2)' },
  };

  return (
    <div style={{marginBottom:24}}>
      <div style={{fontSize:12,fontWeight:600,color:'var(--muted2)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:14,paddingBottom:8,borderBottom:'1px solid var(--border)'}}>
        🚪 History Keluar
      </div>

      {loading ? (
        <div style={{padding:12,textAlign:'center',color:'var(--muted)',fontSize:12}}>⏳ Memuat...</div>
      ) : logs.length === 0 ? (
        <div style={{padding:16,textAlign:'center',color:'var(--muted)',fontSize:12,borderRadius:8,border:'1px dashed var(--border)'}}>
          Tidak ada history keluar
        </div>
      ) : (
        <div style={{display:'flex',flexDirection:'column',gap:8}}>
          {logs.map((log,i) => {
            const ac = ALASAN_COLOR[log.alasan_keluar] || ALASAN_COLOR['LAINNYA'];
            return (
              <div key={i} style={{padding:'12px 14px',borderRadius:9,background:'var(--bg3)',border:'1px solid var(--border)'}}>
                <div style={{display:'flex',alignItems:'center',gap:10,marginBottom:6}}>
                  <span style={{padding:'3px 10px',borderRadius:99,fontSize:11,fontWeight:700,background:ac.bg,color:ac.color}}>
                    {log.alasan_keluar}
                  </span>
                  <span style={{fontSize:12,fontWeight:600,color:'var(--text)'}}>{log.tanggal_keluar}</span>
                </div>
                {log.catatan_keluar && (
                  <div style={{fontSize:12,color:'var(--muted2)',lineHeight:1.5}}>{log.catatan_keluar}</div>
                )}
                <div style={{fontSize:10.5,color:'var(--muted)',marginTop:6}}>Dicatat oleh: {log.dicatat_oleh} · {log.created_at}</div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

// ── MAIN COMPONENT ──
export default function EmployeeEdit({ employee, positions = [] }) {
  const { data, setData, put, processing, errors, isDirty } = useForm({
    id_badge:             employee.id_badge             || '',
    nama_lengkap:         employee.nama_lengkap         || '',
    nama_ibu:             employee.nama_ibu             || '',
    no_ktp:               employee.no_ktp               || '',
    no_bpjs_tk:           employee.no_bpjs_tk           || '',
    no_bpjs_kes:          employee.no_bpjs_kes          || '',
    no_telepon:           employee.no_telepon            || '',
    tempat_lahir:         employee.tempat_lahir          || '',
    tanggal_lahir:        employee.tanggal_lahir         || '',
    tanggal_masuk:        employee.tanggal_masuk         || '',
    position_id:          employee.position_id           || '',
    alamat:               employee.alamat               || '',
    agama:                employee.agama                || '',
    kota_asal:            employee.kota_asal            || '',
    tamatan:              employee.tamatan              || '',
    ptkp:                 employee.ptkp                 || '',
    ccpm:                 employee.ccpm                 || '',
    // Badge & KP
    expire_badge:         employee.expire_badge         || '',
    rfid:                 employee.rfid                 || '',
    status_kp:            employee.status_kp            || '',
    kp_ready:             employee.kp_ready             || '',
    exp_kp:               employee.exp_kp               || '',
    // SIM
    type_sim:             employee.type_sim             || '',
    no_sim:               employee.no_sim               || '',
    sim_kota_keluar:      employee.sim_kota_keluar      || '',
    expired_sim:          employee.expired_sim          || '',
    // SIO
    sio_k3:               employee.sio_k3               || 'NO',
    no_sio:               employee.no_sio               || '',
    expire_sio:           employee.expire_sio           || '',
    nama_perusahaan_sio:  employee.nama_perusahaan_sio  || '',
    tipe_sio:             employee.tipe_sio             || '',
    // MCU
    tgl_mcu:              employee.tgl_mcu              || '',
    exp_mcu:              employee.exp_mcu              || '',
    status_mcu:           employee.status_mcu           || '',
    lokasi_mcu:           employee.lokasi_mcu           || '',
    derajat_kesehatan:    employee.derajat_kesehatan    || '',
    // PPE
    ukuran_baju:          employee.ukuran_baju          || '',
    ukuran_sepatu:        employee.ukuran_sepatu        || '',
    // PKWT
    start_pkwt:           employee.start_pkwt           || '',
    end_pkwt:             employee.end_pkwt             || '',
    no_contract:          employee.no_contract          || '',
    no_rekening:          employee.no_rekening          || '',
    nama_bank:            employee.nama_bank            || '',
  });

  const [badgeWarning, setBadgeWarning] = useState('');

  function submit(e) {
    e.preventDefault();
    if (badgeWarning) {
      alert('ID Badge sudah digunakan. Silakan gunakan ID Badge yang lain.');
      return;
    }
    put(`/employees/${employee.id}`);
  }

  const inputStyle = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'9px 12px', fontSize:13,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', transition:'border .18s',
  };
  const selectStyle = { ...inputStyle, cursor:'pointer' };

  return (
    <AppLayout title="Edit" subtitle="Karyawan">
      <div style={{ maxWidth:900, margin:'0 auto' }}>

        {/* HEADER */}
        <div style={{ display:'flex', alignItems:'center', gap:16, marginBottom:24 }}>
          <Link href="/employees" style={{
            display:'flex', alignItems:'center', gap:6,
            padding:'8px 14px', borderRadius:8,
            background:'var(--card)', border:'1px solid var(--border)',
            color:'var(--muted2)', fontSize:12.5, textDecoration:'none',
          }}>← Kembali</Link>
          <div>
            <div style={{ fontFamily:'Syne,sans-serif', fontSize:16, fontWeight:700 }}>{employee.nama_lengkap}</div>
            <div style={{ fontSize:11.5, color:'var(--muted)' }}>{employee.id_badge}</div>
          </div>
          {isDirty && (
            <div style={{ marginLeft:'auto', fontSize:11.5, color:'var(--accent)', background:'rgba(232,160,32,.12)', padding:'4px 10px', borderRadius:6 }}>
              ⚠️ Ada perubahan belum disimpan
            </div>
          )}
        </div>

        <form onSubmit={submit}>
          <div className="panel" style={{ padding:24 }}>

            {/* DATA PRIBADI */}
            <Section title="📋 Data Pribadi">
              <Field label="ID Badge">
                <input style={{...inputStyle, borderColor:badgeWarning?'#E04545':'var(--border)'}}
                  value={data.id_badge}
                  onChange={e=>{ setData('id_badge',e.target.value); setBadgeWarning(''); }}
                  onBlur={e=>{
                    const val = e.target.value.trim();
                    if (!val || val === employee.id_badge) { setBadgeWarning(''); return; }
                    fetch(`/employees/check-badge?badge=${encodeURIComponent(val)}&exclude=${employee.id}`)
                      .then(r=>r.json())
                      .then(res=>{ setBadgeWarning(res.exists ? `⚠️ ID Badge "${val}" sudah digunakan oleh ${res.nama}.` : ''); })
                      .catch(()=>setBadgeWarning(''));
                  }}
                />
                {badgeWarning && <div style={{fontSize:11,color:'#E04545',marginTop:4}}>{badgeWarning}</div>}
              </Field>
              <Field label="Nama Lengkap">
                <input style={inputStyle} value={data.nama_lengkap} onChange={e=>setData('nama_lengkap',e.target.value)} />
              </Field>
              <Field label="Nama Ibu Kandung">
                <input style={inputStyle} value={data.nama_ibu} onChange={e=>setData('nama_ibu',e.target.value)} placeholder="Nama ibu kandung" />
              </Field>
              <Field label="No. KTP">
                <input style={inputStyle} value={data.no_ktp} onChange={e=>setData('no_ktp',e.target.value)} />
              </Field>
              <Field label="No. Telepon">
                <input style={inputStyle} value={data.no_telepon} onChange={e=>setData('no_telepon',e.target.value)} />
              </Field>
              <Field label="Tempat Lahir">
                <input style={inputStyle} value={data.tempat_lahir} onChange={e=>setData('tempat_lahir',e.target.value)} />
              </Field>
              {employee.umur !== null && employee.umur !== undefined && (
                <Field label="Umur">
                  <div style={{
                    background:'var(--bg3)', border:'1px solid var(--border)',
                    borderRadius:8, padding:'9px 12px', fontSize:13,
                    color:'var(--accent)', fontWeight:600,
                    display:'flex', alignItems:'center', gap:8,
                  }}>
                    👤 {employee.umur} tahun
                    {employee.umur >= 53 && (
                      <span style={{
                        fontSize:11, fontWeight:700, padding:'2px 8px', borderRadius:99,
                        background: employee.umur >= 56 ? 'rgba(224,69,69,.15)' : 'rgba(232,160,32,.15)',
                        color: employee.umur >= 56 ? '#E04545' : 'var(--accent)',
                      }}>
                        {employee.umur >= 56 ? '⚠️ Sudah memasuki usia pensiun' : `⚠️ ${56 - employee.umur} thn lagi masuk umur pensiun`}
                      </span>
                    )}
                  </div>
                </Field>
              )}
              <Field label="Tanggal Lahir">
                <input type="date" style={inputStyle} value={data.tanggal_lahir} onChange={e=>setData('tanggal_lahir',e.target.value)} />
              </Field>
              <Field label="Tanggal Masuk / Bergabung">
                <input type="date" style={inputStyle} value={data.tanggal_masuk} onChange={e=>setData('tanggal_masuk',e.target.value)} />
              </Field>
              <Field label="Jabatan">
                <select style={selectStyle} value={data.position_id} onChange={e=>setData('position_id',e.target.value)}>
                  <option value="">— Pilih Jabatan —</option>
                  {positions.map((p,i)=><option key={i} value={p.id}>{p.nama_jabatan}</option>)}
                </select>
              </Field>
              <Field label="Kota Asal">
                <input style={inputStyle} value={data.kota_asal} onChange={e=>setData('kota_asal',e.target.value)} />
              </Field>
              <Field label="Pendidikan Terakhir">
                <select style={selectStyle} value={data.tamatan} onChange={e=>setData('tamatan',e.target.value)}>
                  <option value="">— Pilih —</option>
                  {['SD','SMP','SMA / SLTA','SMK','D3','S1','S2'].map(v=><option key={v} value={v}>{v}</option>)}
                </select>
              </Field>
              <Field label="Agama">
                <select style={inputStyle} value={data.agama} onChange={e=>setData('agama',e.target.value)}>
                  <option value="">— Pilih —</option>
                  {['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'].map(a=>(
                    <option key={a} value={a}>{a}</option>
                  ))}
                </select>
              </Field>
              <Field label="Alamat">
                <textarea style={{...inputStyle,minHeight:70,resize:'vertical'}} value={data.alamat} onChange={e=>setData('alamat',e.target.value)}/>
              </Field>
              <Field label="PTKP">
                <select style={selectStyle} value={data.ptkp} onChange={e=>setData('ptkp',e.target.value)}>
                  <option value="">— Pilih —</option>
                  {['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3'].map(v=><option key={v} value={v}>{v}</option>)}
                </select>
              </Field>
              <Field label="Status CCPM">
                <select style={selectStyle} value={data.ccpm} onChange={e=>setData('ccpm',e.target.value)}>
                  <option value="">— Pilih —</option>
                  <option value="AKTIF">AKTIF</option>
                  <option value="NONAKTIF">NONAKTIF</option>
                </select>
              </Field>
            </Section>

            {/* BADGE & KP */}
            <Section title="💳 Badge & KP">
              <Field label="Expire Badge">
                <input type="date" style={inputStyle} value={data.expire_badge} onChange={e=>setData('expire_badge',e.target.value)} />
              </Field>
              <Field label="RFID">
                <input style={inputStyle} value={data.rfid} onChange={e=>setData('rfid',e.target.value)} placeholder="cth: 2D9A70FB" />
              </Field>
              <Field label="Status KP">
                <input style={inputStyle} value={data.status_kp} onChange={e=>setData('status_kp',e.target.value)} placeholder="KP has been exist" />
              </Field>
              <Field label="KP Ready">
                <input style={inputStyle} value={data.kp_ready} onChange={e=>setData('kp_ready',e.target.value)} />
              </Field>
              <Field label="Exp KP">
                <input type="date" style={inputStyle} value={data.exp_kp} onChange={e=>setData('exp_kp',e.target.value)} />
              </Field>
            </Section>

            {/* SIM */}
            <Section title="🚗 SIM">
              <Field label="Type SIM">
                <select style={selectStyle} value={data.type_sim} onChange={e=>setData('type_sim',e.target.value)}>
                  <option value="">— Pilih —</option>
                  {['A','BI','BII','BIIU','C','D'].map(v=><option key={v} value={v}>{v}</option>)}
                </select>
              </Field>
              <Field label="No. SIM">
                <input style={inputStyle} value={data.no_sim} onChange={e=>setData('no_sim',e.target.value)} />
              </Field>
              <Field label="Kota Dikeluarkan SIM">
                <input style={inputStyle} placeholder="cth: Pekanbaru" value={data.sim_kota_keluar} onChange={e=>setData('sim_kota_keluar',e.target.value)} />
              </Field>
              <Field label="Expired SIM">
                <input type="date" style={inputStyle} value={data.expired_sim} onChange={e=>setData('expired_sim',e.target.value)} />
              </Field>
            </Section>

            {/* SIO */}
            <Section title="🏗️ SIO">
              <Field label="Punya SIO K3?">
                <select style={selectStyle} value={data.sio_k3} onChange={e=>setData('sio_k3',e.target.value)}>
                  <option value="NO">Tidak</option>
                  <option value="YES">Ya</option>
                </select>
              </Field>
              <Field label="No. SIO">
                <input style={inputStyle} value={data.no_sio} onChange={e=>setData('no_sio',e.target.value)} />
              </Field>
              <Field label="Expire SIO">
                <input type="date" style={inputStyle} value={data.expire_sio} onChange={e=>setData('expire_sio',e.target.value)} />
              </Field>
              <Field label="Tipe SIO">
                <input style={inputStyle} value={data.tipe_sio} onChange={e=>setData('tipe_sio',e.target.value)} />
              </Field>
              <Field label="Nama Perusahaan SIO">
                <input style={inputStyle} value={data.nama_perusahaan_sio} onChange={e=>setData('nama_perusahaan_sio',e.target.value)} />
              </Field>
            </Section>

            {/* MCU */}
            <Section title="🏥 MCU">
              <Field label="Tgl Pelaksanaan MCU">
                <input type="date" style={inputStyle} value={data.tgl_mcu} onChange={e=>setData('tgl_mcu',e.target.value)} />
              </Field>
              <Field label="Exp MCU">
                <input type="date" style={inputStyle} value={data.exp_mcu} onChange={e=>setData('exp_mcu',e.target.value)} />
              </Field>
              <Field label="Status MCU">
                <select style={selectStyle} value={data.status_mcu} onChange={e=>setData('status_mcu',e.target.value)}>
                  <option value="">— Pilih —</option>
                  <option value="OK">OK</option>
                  <option value="NOT OK">NOT OK</option>
                </select>
              </Field>
              <Field label="Lokasi MCU">
                <input style={inputStyle} value={data.lokasi_mcu} onChange={e=>setData('lokasi_mcu',e.target.value)} placeholder="RS Mutia Sari" />
              </Field>
              <Field label="Derajat Kesehatan (DK)">
                <input style={inputStyle} value={data.derajat_kesehatan} onChange={e=>setData('derajat_kesehatan',e.target.value)} placeholder="cth: P1, P2, P3" />
              </Field>
            </Section>

            {/* PPE */}
            <Section title="🦺 PPE">
              <Field label="Ukuran Baju (FRC)">
                <ComboBox
                  value={data.ukuran_baju}
                  onChange={v=>setData('ukuran_baju',v)}
                  options={['S','M','L','XL','XXL','XXXL','XXXXL','2L','3L','3XL','2XL','7L']}
                  placeholder="Pilih atau ketik ukuran baju..."
                  inputStyle={inputStyle}
                />
              </Field>
              <Field label="Ukuran Sepatu">
                <ComboBox
                  value={data.ukuran_sepatu}
                  onChange={v=>setData('ukuran_sepatu',v)}
                  options={['5','6','7','8','9','10','11','38','39','40','41','42','43','44','45']}
                  placeholder="Pilih atau ketik ukuran sepatu..."
                  inputStyle={inputStyle}
                />
              </Field>
            </Section>

            {/* PKWT */}
            <Section title="📄 PKWT">
              <Field label="Start PKWT">
                <input type="date" style={inputStyle} value={data.start_pkwt} onChange={e=>setData('start_pkwt',e.target.value)} />
              </Field>
              <Field label="End PKWT">
                <input type="date" style={inputStyle} value={data.end_pkwt} onChange={e=>setData('end_pkwt',e.target.value)} />
              </Field>
              <Field label="No. Kontrak">
                <input style={inputStyle} value={data.no_contract} onChange={e=>setData('no_contract',e.target.value)} />
              </Field>
            </Section>

            {/* BANK & BPJS */}
            <Section title="🏦 Bank & BPJS">
              <Field label="Nama Bank">
                <select style={selectStyle} value={data.nama_bank} onChange={e=>setData('nama_bank',e.target.value)}>
                  <option value="">— Pilih Bank —</option>
                  {['BCA','Mandiri','BRI','BNI','BSI','CIMB Niaga','Danamon','Permata','BTN','Mega','Bank Riau Kepri'].map(b=>(
                    <option key={b} value={b}>{b}</option>
                  ))}
                </select>
              </Field>
              <Field label="No. Rekening">
                <input style={inputStyle} value={data.no_rekening}
                  onChange={e=>setData('no_rekening',e.target.value)}
                  placeholder="cth: 1234567890" />
              </Field>
              <Field label="No. BPJS Ketenagakerjaan (TK)">
                <input style={inputStyle} value={data.no_bpjs_tk}
                  onChange={e=>setData('no_bpjs_tk',e.target.value)}
                  placeholder="cth: 12345678901" />
              </Field>
              <Field label="No. BPJS Kesehatan">
                <input style={inputStyle} value={data.no_bpjs_kes}
                  onChange={e=>setData('no_bpjs_kes',e.target.value)}
                  placeholder="cth: 0001234567890" />
              </Field>
            </Section>

            {/* ACTIONS */}
            <div style={{ display:'flex', gap:12, justifyContent:'flex-end', marginTop:8, paddingTop:16, borderTop:'1px solid var(--border)' }}>
              <Link href="/employees" style={{
                padding:'10px 20px', borderRadius:8, border:'1px solid var(--border)',
                background:'var(--bg3)', color:'var(--muted2)', fontSize:13,
                textDecoration:'none', display:'inline-block',
              }}>Batal</Link>
              <button type="submit" disabled={processing} style={{
                padding:'10px 28px', borderRadius:8, border:'none',
                background:'linear-gradient(135deg,#E8A020,#A06010)',
                color:'#0C0F14', fontSize:13, fontWeight:700,
                cursor:processing?'not-allowed':'pointer',
                opacity:processing?0.7:1,
                fontFamily:"'Outfit',sans-serif",
              }}>
                {processing ? '⏳ Menyimpan...' : '💾 Simpan Perubahan'}
              </button>
            </div>
          </div>
        </form>

        {/* ── PANEL DI LUAR FORM ── */}
        <div style={{ display:'flex', flexDirection:'column', gap:12, marginTop:16 }}>

          {/* SP & History Keluar */}
          <div style={{ background:'var(--card)', border:'1px solid var(--border)', borderRadius:12, padding:'20px 24px' }}>
            <SpSection employeeId={employee.id} />
            <HistoryKeluarSection employeeId={employee.id} />
          </div>

          {/* Dokumen */}
          <div style={{ background:'var(--card)', border:'1px solid var(--border)', borderRadius:12, padding:'20px 24px' }}>
            <DocumentPanel employeeId={employee.id} employeeName={employee.nama_lengkap} />
          </div>

          {/* Training */}
          <div style={{ background:'var(--card)', border:'1px solid var(--border)', borderRadius:12, padding:'20px 24px' }}>
            <TrainingPanel employeeId={employee.id} />
          </div>
        </div>

      </div>
    </AppLayout>
  );
}