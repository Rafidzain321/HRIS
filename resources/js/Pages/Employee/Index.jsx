  // resources>js>Pages>Employee>Index.jsx
import React, { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, Link, useForm, usePage } from '@inertiajs/react';
import { ConfirmModal } from '@/Layouts/AppLayout';
import axios from 'axios';
import {
  Pencil, X, TriangleAlert, Plus, Check, Search, Settings, Upload, Download,
  Stethoscope, User, CreditCard, CheckCircle2, XCircle, ClipboardList, Trash2,
  Save, Loader2, RefreshCw, Users, LogOut, Camera, Syringe, ShieldCheck, Shield,
  ScrollText, Award, Archive, RotateCcw, Bell, Building2,
} from 'lucide-react';

const avatarColors = ['#3A8FE0','#22C97A','#E8A020','#E04545','#9B59B6','#E06A20','#3ABCDE','#C97A22'];
function getAv(nama){ return (nama||'?').split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase(); }
function getAvColor(badge){ const i=parseInt((badge||'').replace(/\D/g,''))%avatarColors.length; return avatarColors[i||0]; }
function fmtDate(d){ if(!d) return '—'; return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}); }

function Pill({type,children}){ return <span className={`pill pill-${type}`}>{children}</span>; }
function simPill(sim_status,type_sim){
  if(sim_status==='expired') return <Pill type="red">Expired</Pill>;
  if(sim_status==='warning') return <Pill type="warn">&lt;30hr</Pill>;
  if(!type_sim) return <Pill type="gray">N/A</Pill>;
  return <Pill type="green">Valid</Pill>;
}

const DOC_TIPES = [
  { key:'doc_foto',   tipe:'foto',           label:'Foto', icon:Camera },
  { key:'doc_ktp',    tipe:'ktp',            label:'KTP', icon:CreditCard },
  { key:'doc_kk',     tipe:'kk',             label:'KK', icon:Users },
  { key:'doc_bpjs',   tipe:'bpjs',           label:'BPJS TK', icon:Stethoscope },
  { key:'doc_bpjs_k', tipe:'bpjs_kesehatan', label:'BPJS Kes', icon:Syringe },
  { key:'doc_skck',   tipe:'skck',           label:'SKCK', icon:ShieldCheck },
  { key:'doc_sio',    tipe:'sio',            label:'SIO', icon:ScrollText },
  { key:'doc_cv',     tipe:'cv',             label:'CV', icon:ClipboardList },
  { key:'doc_k3u',    tipe:'k3u',            label:'Sertifikat K3U', icon:Award },
];
const HO_COLS = [
  { key:'ho_nik',             label:'NIK HO' },
  { key:'ho_lokasi_kerja',    label:'Lokasi Kerja' },
  { key:'ho_status_karyawan', label:'Status Karyawan' },
  { key:'ho_no_kk',           label:'No. KK' },
  { key:'ho_rt_rw',           label:'RT/RW' },
  { key:'ho_kelurahan',       label:'Kelurahan' },
  { key:'ho_kecamatan',       label:'Kecamatan' },
  { key:'ho_propinsi',        label:'Propinsi' },
  { key:'ho_npwp',            label:'NPWP' },
  { key:'ho_email',           label:'Email' },
];
const OPTIONAL_COLS = [
  { key:'sim',       label:'SIM' },
  { key:'sio_k3',    label:'SIO K3' },
  { key:'mcu',       label:'MCU' },
  { key:'kp',        label:'KP' },
  { key:'badge',     label:'Badge' },
  { key:'tgl_masuk', label:'Tgl Masuk' },
  { key:'masa_kerja',label:'Masa Pengabdian' },
  { key:'agama',     label:'Agama' },
  { key:'alamat',    label:'Alamat' },
  { key:'umur',      label:'Umur' },
  ...DOC_TIPES.map(d => ({ key: d.key, label: d.label })),
  ...HO_COLS,
];
const DEFAULT_COLS = ['sim','sio_k3','mcu','kp','badge'];

// ── COLUMN PICKER MODAL ──
function ColPickerModal({ selected, onClose, onApply, hideCompliance=false }) {
  const [selectedCols, setSelectedCols] = useState([...selected]);
  function toggle(key) {
    setSelectedCols(prev => prev.includes(key) ? prev.filter(k=>k!==key) : [...prev, key]);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(480px, calc(100vw - 24px))',maxHeight:'80vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Settings size={15}/> Pilih Kolom Tampilan</div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <div style={{padding:'16px 20px'}}>
          <div style={{fontSize:11.5,color:'var(--muted)',marginBottom:16,padding:'8px 12px',background:'var(--bg3)',borderRadius:8}}>
            NIK, Nama, dan Jabatan selalu tampil. Pilih kolom tambahan di bawah:
          </div>
          {!hideCompliance && (
          <>
          <div style={{fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:10,display:'flex',alignItems:'center',gap:6}}><Shield size={12}/> Compliance</div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8,marginBottom:20}}>
            {OPTIONAL_COLS.filter(c=>!c.key.startsWith('doc_')&&!['tgl_masuk','agama','alamat'].includes(c.key)).map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',display:'flex',alignItems:'center',gap:5,
                  background:selectedCols.includes(col.key)?'rgba(232,160,32,.15)':'var(--bg3)',
                  color:selectedCols.includes(col.key)?'var(--accent)':'var(--muted2)',
                  border:`1px solid ${selectedCols.includes(col.key)?'var(--accent)':'var(--border)'}`}}>
                {selectedCols.includes(col.key)&&<Check size={12}/>}{col.label}
              </div>
            ))}
          </div>
          </>
          )}
          <div style={{fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:10,display:'flex',alignItems:'center',gap:6}}><User size={12}/> Data Pribadi</div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8,marginBottom:20}}>
            {OPTIONAL_COLS.filter(c=>['tgl_masuk','agama','alamat'].includes(c.key) || (c.key==='masa_kerja' && hideCompliance)).map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',display:'flex',alignItems:'center',gap:5,
                  background:selectedCols.includes(col.key)?'rgba(232,160,32,.15)':'var(--bg3)',
                  color:selectedCols.includes(col.key)?'var(--accent)':'var(--muted2)',
                  border:`1px solid ${selectedCols.includes(col.key)?'var(--accent)':'var(--border)'}`}}>
                {selectedCols.includes(col.key)&&<Check size={12}/>}{col.label}
              </div>
            ))}
          </div>
          {hideCompliance && (
          <>
          <div style={{fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:10,display:'flex',alignItems:'center',gap:6}}><Building2 size={12}/> Detail HO</div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8,marginBottom:20}}>
            {HO_COLS.map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',display:'flex',alignItems:'center',gap:5,
                  background:selectedCols.includes(col.key)?'rgba(232,160,32,.15)':'var(--bg3)',
                  color:selectedCols.includes(col.key)?'var(--accent)':'var(--muted2)',
                  border:`1px solid ${selectedCols.includes(col.key)?'var(--accent)':'var(--border)'}`}}>
                {selectedCols.includes(col.key)&&<Check size={12}/>}{col.label}
              </div>
            ))}
          </div>
          </>
          )}
          <div style={{fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:10,display:'flex',alignItems:'center',gap:6}}><Archive size={12}/> Dokumen Karyawan</div>
          <div style={{fontSize:11,color:'var(--muted)',marginBottom:8,display:'flex',alignItems:'center',gap:10}}><span style={{display:'flex',alignItems:'center',gap:3}}><Check size={11} color="#22C97A"/> = sudah upload</span><span style={{display:'flex',alignItems:'center',gap:3}}><X size={11}/> = belum upload</span></div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8}}>
            {DOC_TIPES.map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',display:'flex',alignItems:'center',gap:5,
                  background:selectedCols.includes(col.key)?'rgba(34,201,122,.12)':'var(--bg3)',
                  color:selectedCols.includes(col.key)?'#22C97A':'var(--muted2)',
                  border:`1px solid ${selectedCols.includes(col.key)?'#22C97A':'var(--border)'}`}}>
                <col.icon size={12}/> {col.label}
              </div>
            ))}
          </div>
        </div>
        <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={()=>setSelectedCols(DEFAULT_COLS)}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Reset Default
          </button>
          <button type="button" onClick={onClose}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Batal
          </button>
          <button type="button" onClick={()=>{ onApply(selectedCols); onClose(); }}
            style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:6}}>
            <Check size={14}/> Terapkan
          </button>
        </div>
      </div>
    </div>
  );
}

// ── MODAL IMPORT EXCEL ──
function ImportModal({ onClose, isHo=false }) {
  const [file,    setFile]    = useState(null);
  const [loading, setLoading] = useState(false);
  const [result,  setResult]  = useState(null);
  const [error,   setError]   = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!file) { alert('Pilih file Excel terlebih dahulu!'); return; }
    setLoading(true); setError(''); setResult(null);
    const fd = new FormData();
    fd.append('file', file);
    router.post('/employees/import', fd, {
      forceFormData: true, preserveScroll: true, preserveState: true,
      onSuccess: (page) => {
        setLoading(false);
        const importResult = page.props?.flash?.import_result;
        if (importResult) setResult(importResult);
        else setResult({ imported: 0, skipped: 0, errors: ['Tidak ada response dari server.'], skipped_list: [] });
      },
      onError: (errors) => {
        setLoading(false);
        setError(errors?.file || 'Upload gagal. Pastikan file adalah .xlsx dan ukuran maks 10MB.');
      },
      onFinish: () => setLoading(false),
    });
  }

  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'9px 12px', fontSize:13, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%' };

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(560px, calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Upload size={15}/> Import Karyawan dari Excel</div>
            <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>Gunakan template resmi HRIS AKM</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        {error && <div style={{margin:'14px 20px 0',padding:'10px 14px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)',fontSize:12,color:'#E04545',display:'flex',alignItems:'center',gap:6}}><XCircle size={13}/> {error}</div>}
        {result && (
          <div style={{margin:'16px 20px 0',display:'flex',flexDirection:'column',gap:10}}>
            <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:10}}>
              <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(34,201,122,.1)',border:'1px solid rgba(34,201,122,.25)'}}>
                <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'#22C97A'}}>{result.imported}</div>
                <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Berhasil Import</div>
              </div>
              <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(232,160,32,.1)',border:'1px solid rgba(232,160,32,.25)'}}>
                <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'var(--accent)'}}>{result.skipped}</div>
                <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Di-skip (duplikat)</div>
              </div>
              <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)'}}>
                <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'#E04545'}}>{result.errors?.length || 0}</div>
                <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Error</div>
              </div>
            </div>
            {result.skipped_list?.length > 0 && (
              <div style={{background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',borderRadius:9,padding:'12px 14px'}}>
                <div style={{fontSize:11.5,fontWeight:600,color:'var(--accent)',marginBottom:8,display:'flex',alignItems:'center',gap:6}}><TriangleAlert size={13}/> Di-skip karena NIK/Badge sudah ada:</div>
                <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                  {result.skipped_list.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--muted2)'}}>• {s}</div>)}
                </div>
              </div>
            )}
            {result.errors?.length > 0 && (
              <div style={{background:'rgba(224,69,69,.08)',border:'1px solid rgba(224,69,69,.2)',borderRadius:9,padding:'12px 14px'}}>
                <div style={{fontSize:11.5,fontWeight:600,color:'#E04545',marginBottom:8,display:'flex',alignItems:'center',gap:6}}><XCircle size={13}/> Error:</div>
                <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                  {result.errors.map((err,i)=><div key={i} style={{fontSize:11,color:'#E04545'}}>• {err}</div>)}
                </div>
              </div>
            )}
            {result.imported > 0 && <div style={{background:'rgba(34,201,122,.08)',border:'1px solid rgba(34,201,122,.2)',borderRadius:9,padding:'10px 14px',fontSize:12,color:'#22C97A',fontWeight:600,display:'flex',alignItems:'center',gap:6}}><CheckCircle2 size={14}/> {result.imported} karyawan berhasil ditambahkan ke sistem!</div>}
          </div>
        )}
        <form onSubmit={handleSubmit}>
          <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div style={{background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',borderRadius:9,padding:'12px 14px'}}>
              <div style={{fontSize:12,fontWeight:600,color:'var(--blue)',marginBottom:6,display:'flex',alignItems:'center',gap:6}}><ClipboardList size={13}/> Sebelum import, pastikan:</div>
              <div style={{fontSize:11.5,color:'var(--muted2)',display:'flex',flexDirection:'column',gap:4}}>
                <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> Gunakan template resmi (download di bawah)</div>
                <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> Data diisi mulai baris ke-5</div>
                <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> Format tanggal: DD-MM-YYYY (ketik sebagai teks)</div>
                <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> Kolom wajib: {isHo ? 'Nama Lengkap, No. KTP' : 'ID Badge, Nama Lengkap, Status'}</div>
                <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> NIK yang sudah ada akan otomatis di-skip</div>
                {isHo && <div style={{display:"flex",alignItems:"center",gap:5}}><Check size={11}/> Template ini khusus untuk karyawan HO (unit, NIK HO, data KTP, dll)</div>}
              </div>
              <a href="/employees/template-import" style={{display:'inline-flex',alignItems:'center',gap:6,marginTop:10,padding:'6px 14px',borderRadius:7,background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12,fontWeight:600,textDecoration:'none'}}>
                <Download size={13}/> Download Template Excel {isHo ? 'HO' : ''}
              </a>
            </div>
            <div>
              <label style={{fontSize:11.5,color:'var(--muted)',marginBottom:6,display:'block'}}>Pilih File Excel (.xlsx)</label>
              <input type="file" accept=".xlsx,.xls" style={{...inp,cursor:'pointer',padding:'7px 12px'}} onChange={e=>{setFile(e.target.files[0]);setResult(null);setError('');}} />
              {file && <div style={{fontSize:11,color:'var(--green)',marginTop:4,display:'flex',alignItems:'center',gap:5}}><Check size={12}/> {file.name} ({(file.size/1024).toFixed(1)} KB)</div>}
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>{result ? 'Tutup' : 'Batal'}</button>
            <button type="submit" disabled={loading||!file} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading||!file?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading||!file?0.6:1}}>
              {loading ? <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>Mengimport...</span> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Upload size={14}/>Import Sekarang</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL TAMBAH KARYAWAN ──
function TambahModal({ jabatan_list, projects, onClose, isHo=false, isSuperAdmin=false }) {
  const [badgeOwner, setBadgeOwner] = React.useState(null);
  const { data, setData, post, processing, errors, reset } = useForm({
    nama_lengkap:'', id_badge:'', no_ktp:'', no_telepon:'',
    tempat_lahir:'', tanggal_lahir:'', tanggal_masuk:'', alamat:'', position_id:'',
    nama_ibu:'', ptkp:'', agama:'', project_id:'',
    status:'AKTIF', status_mcu:'', lokasi_mcu:'', tgl_mcu:'', exp_mcu:'',
    derajat_kesehatan:'',
    ho_detail: { unit:'', nik_ho:'', lokasi_kerja:'', status_karyawan:'', no_kk:'', rt_rw:'', kelurahan:'', kecamatan:'', propinsi:'', npwp:'', email:'' },
  });
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };

  // Super admin memilih project lewat dropdown di modal ini sendiri, jadi status HO
  // ditentukan dari project yang sedang dipilih; non-super-admin ikut project_info halaman.
  const resolvedIsHo = isSuperAdmin
    ? projects.find(p => String(p.id) === String(data.project_id))?.tipe_gaji === 'ho'
    : isHo;

  function setHoDetail(field, value) {
    setData('ho_detail', { ...data.ho_detail, [field]: value });
  }

  function submit(e) {
    e.preventDefault();
    post('/employees', { onSuccess: () => { reset(); onClose(); } });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(680px, calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:10}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Plus size={15}/> Tambah Karyawan Baru</div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'grid',gridTemplateColumns:'1fr 1fr',gap:'12px 16px'}}>
            <div style={{gridColumn:'1/-1',fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',paddingBottom:6,borderBottom:'1px solid var(--border)',display:'flex',alignItems:'center',gap:6}}><User size={12}/> Identitas</div>
            <div style={{gridColumn:'1/-1'}}>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Nama Lengkap *</label>
              <input type="text" style={{...inp,borderColor:errors.nama_lengkap?'#E04545':'var(--border)'}} value={data.nama_lengkap} onChange={e=>setData('nama_lengkap',e.target.value)} placeholder="NAMA LENGKAP SESUAI KTP" />
              {errors.nama_lengkap && <div style={{fontSize:10.5,color:'#E04545',marginTop:3,display:'flex',alignItems:'center',gap:4}}><TriangleAlert size={11}/>{errors.nama_lengkap}</div>}
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>ID Badge{resolvedIsHo?'':' *'}</label>
              <input type="text" style={{...inp,borderColor:errors.id_badge||badgeOwner?'#E04545':'var(--border)'}}
                defaultValue={data.id_badge}
                onBlur={async e => {
                  const val = e.target.value.trim();
                  setData('id_badge', val);
                  if (val) {
                    const res = await fetch(`/employees/check-badge?badge=${encodeURIComponent(val)}`);
                    const json = await res.json();
                    setBadgeOwner(json.exists ? json.nama : null);
                  } else {
                    setBadgeOwner(null);
                  }
                }} />
              {badgeOwner && !errors.id_badge && (
                <div style={{fontSize:10.5,color:'#E04545',marginTop:3}}>
                  <TriangleAlert size={11} style={{verticalAlign:-2}}/> Badge ini sudah digunakan oleh <b>{badgeOwner}</b>
                </div>
              )}
              {errors.id_badge && (
                <div style={{fontSize:10.5,color:'#E04545',marginTop:3,display:'flex',alignItems:'center',gap:4}}><TriangleAlert size={11}/>{errors.id_badge}</div>
              )}
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>No. KTP</label>
              <input type="text" style={{...inp,borderColor:errors.no_ktp?'#E04545':'var(--border)'}} defaultValue={data.no_ktp} onBlur={e=>setData('no_ktp',e.target.value)} />
              {errors.no_ktp && <div style={{fontSize:10.5,color:'#E04545',marginTop:3,display:'flex',alignItems:'center',gap:4}}><TriangleAlert size={11}/>{errors.no_ktp}</div>}
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>No. Telepon</label>
              <input type="text" style={inp} defaultValue={data.no_telepon} onBlur={e=>setData('no_telepon',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tempat Lahir</label>
              <input type="text" style={inp} defaultValue={data.tempat_lahir} onBlur={e=>setData('tempat_lahir',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tanggal Lahir</label>
              <input type="date" style={inp} value={data.tanggal_lahir} onChange={e=>setData('tanggal_lahir',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tanggal Bergabung</label>
              <input type="date" style={inp} value={data.tanggal_masuk} onChange={e=>setData('tanggal_masuk',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Agama</label>
              <select style={inp} value={data.agama} onChange={e=>setData('agama',e.target.value)}>
                <option value="">— Pilih —</option>
                {['Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu'].map(a=><option key={a} value={a}>{a}</option>)}
              </select>
            </div>
            <div style={{gridColumn:'1/-1'}}>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Nama Ibu Kandung</label>
              <input type="text" style={inp} value={data.nama_ibu} onChange={e=>setData('nama_ibu',e.target.value)} placeholder="Nama ibu kandung" />
            </div>
            <div style={{gridColumn:'1/-1'}}>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Alamat</label>
              <textarea style={{...inp,minHeight:60,resize:'vertical'}} value={data.alamat} onChange={e=>setData('alamat',e.target.value)} placeholder="Alamat lengkap sesuai KTP" />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Jabatan</label>
              <select style={inp} value={data.position_id} onChange={e=>setData('position_id',e.target.value)}>
                <option value="">— Pilih Jabatan —</option>
                {jabatan_list.map((j,i)=><option key={i} value={j.id}>{j.nama_jabatan}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Status *</label>
              <select style={inp} value={data.status} onChange={e=>setData('status',e.target.value)}>
                <option value="AKTIF">AKTIF</option>
                <option value="NONAKTIF">NONAKTIF</option>
              </select>
            </div>
            {/* Dropdown project — hanya tampil kalau super admin */}
            {isSuperAdmin && (
              <div style={{gridColumn:'1/-1'}}>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>
                  Project *
                  <span style={{marginLeft:6,fontSize:10,color:'var(--accent)'}}>— wajib diisi untuk Super Admin</span>
                </label>
                <select style={{...inp,borderColor:errors.project_id?'#E04545':'rgba(232,160,32,.4)',background:'rgba(232,160,32,.06)'}}
                  value={data.project_id} onChange={e=>setData('project_id',e.target.value)}>
                  <option value="">— Pilih Project —</option>
                  {projects.map(p=><option key={p.id} value={p.id}>{p.nama} ({p.kode})</option>)}
                </select>
                {errors.project_id && <div style={{fontSize:10.5,color:'#E04545',marginTop:3,display:'flex',alignItems:'center',gap:4}}><TriangleAlert size={11}/>{errors.project_id}</div>}
              </div>
            )}
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>PTKP</label>
              <select style={inp} value={data.ptkp} onChange={e=>setData('ptkp',e.target.value)}>
                <option value="">— Pilih —</option>
                {['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3'].map(v=><option key={v} value={v}>{v}</option>)}
              </select>
            </div>
            {!resolvedIsHo && (<>
            <div style={{gridColumn:'1/-1',fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',paddingBottom:6,borderBottom:'1px solid var(--border)',marginTop:6,display:'flex',alignItems:'center',gap:6}}><Stethoscope size={12}/> MCU</div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Status MCU</label>
              <select style={inp} value={data.status_mcu} onChange={e=>setData('status_mcu',e.target.value)}>
                <option value="">— Pilih —</option>
                <option value="OK">OK</option>
                <option value="TIDAK OK">TIDAK OK</option>
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Lokasi MCU</label>
              <input type="text" style={inp} defaultValue={data.lokasi_mcu} onBlur={e=>setData('lokasi_mcu',e.target.value)} placeholder="RS Mutia Sari" />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Derajat Kesehatan (DK)</label>
              <input type="text" style={inp} defaultValue={data.derajat_kesehatan} onBlur={e=>setData('derajat_kesehatan',e.target.value)} placeholder="cth: P1, P2, P3" />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Pelaksanaan MCU</label>
              <input type="date" style={inp} value={data.tgl_mcu} onChange={e=>setData('tgl_mcu',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Expired MCU</label>
              <input type="date" style={inp} value={data.exp_mcu} onChange={e=>setData('exp_mcu',e.target.value)} />
            </div>
            </>)}
            {resolvedIsHo && (<>
            <div style={{gridColumn:'1/-1',fontSize:10.5,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',paddingBottom:6,borderBottom:'1px solid var(--border)',marginTop:6,display:'flex',alignItems:'center',gap:6}}><Building2 size={12}/> Detail HO</div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Unit</label>
              <select style={inp} value={data.ho_detail.unit} onChange={e=>setHoDetail('unit',e.target.value)}>
                <option value="">— Pilih —</option>
                <option value="HO-1">HO-1</option>
                <option value="HO-2">HO-2</option>
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>NIK HO</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.nik_ho} onBlur={e=>setHoDetail('nik_ho',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Status Karyawan</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.status_karyawan} onBlur={e=>setHoDetail('status_karyawan',e.target.value)} placeholder="cth: Karyawan Tetap" />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Lokasi Kerja</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.lokasi_kerja} onBlur={e=>setHoDetail('lokasi_kerja',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>No. KK</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.no_kk} onBlur={e=>setHoDetail('no_kk',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>RT/RW</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.rt_rw} onBlur={e=>setHoDetail('rt_rw',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Kelurahan</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.kelurahan} onBlur={e=>setHoDetail('kelurahan',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Kecamatan</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.kecamatan} onBlur={e=>setHoDetail('kecamatan',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Propinsi</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.propinsi} onBlur={e=>setHoDetail('propinsi',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>NPWP</label>
              <input type="text" style={inp} defaultValue={data.ho_detail.npwp} onBlur={e=>setHoDetail('npwp',e.target.value)} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Email</label>
              <input type="email" style={inp} defaultValue={data.ho_detail.email} onBlur={e=>setHoDetail('email',e.target.value)} />
            </div>
            </>)}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={processing} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:processing?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:processing?0.7:1}}>
              {processing ? <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>Menyimpan...</span> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Save size={14}/>Simpan</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL TERMINATION ──
function TerminationModal({ employee, onClose }) {
  const [form, setForm] = useState({ tanggal_keluar: new Date().toISOString().split('T')[0], alasan_keluar: '', catatan_keluar: '' });
  const [loading, setLoading] = useState(false);
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  const ALASAN = ['RESIGN','PHK','KONTRAK HABIS','MENINGGAL DUNIA','MUTASI','LAINNYA'];
  function submit(e) {
    e.preventDefault();
    if (!form.alasan_keluar) { alert('Alasan keluar wajib dipilih!'); return; }
    setLoading(true);
    router.post(`/employees/${employee.id}/terminate`, form, { onSuccess: ()=>{setLoading(false);onClose();}, onError: ()=>setLoading(false) });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.75)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid rgba(224,69,69,.3)',borderRadius:16,width:'min(480px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.6)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,color:'#E04545',display:'flex',alignItems:'center',gap:8}}><LogOut size={15}/> Termination Karyawan</div>
            <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>{employee.nama_lengkap} · {employee.id_badge}</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <div style={{margin:'16px 20px 0',padding:'10px 14px',borderRadius:8,background:'rgba(224,69,69,.08)',border:'1px solid rgba(224,69,69,.2)',fontSize:12,color:'#F09090',lineHeight:1.5}}>
          <TriangleAlert size={12} style={{verticalAlign:-2}}/> Karyawan ini akan dipindahkan ke <b>tab Terminated</b>. Data tidak dihapus — tetap bisa dilihat sebagai arsip.
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal Keluar *</label>
              <input type="date" style={inp} value={form.tanggal_keluar} onChange={e=>setForm(f=>({...f,tanggal_keluar:e.target.value}))} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Alasan Keluar *</label>
              <select style={inp} value={form.alasan_keluar} onChange={e=>setForm(f=>({...f,alasan_keluar:e.target.value}))}>
                <option value="">— Pilih Alasan —</option>
                {ALASAN.map(a=><option key={a} value={a}>{a}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan Tambahan</label>
              <textarea style={{...inp,minHeight:80,resize:'vertical'}} value={form.catatan_keluar} placeholder="Opsional..." onChange={e=>setForm(f=>({...f,catatan_keluar:e.target.value}))} />
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E04545,#A03030)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?0.7:1}}>
              {loading ? <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>Memproses...</span> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><LogOut size={14}/>Konfirmasi Termination</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL EDIT TERMINATED ──
function EditTerminatedModal({ employee, onClose }) {
  const [form, setForm] = useState({ tanggal_keluar: employee.tanggal_keluar||'', alasan_keluar: employee.alasan_keluar||'', catatan_keluar: employee.catatan_keluar||'' });
  const [loading, setLoading] = useState(false);
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  const ALASAN = ['RESIGN','PHK','KONTRAK HABIS','MENINGGAL DUNIA','MUTASI','LAINNYA'];
  function submit(e) {
    e.preventDefault();
    if (!form.alasan_keluar) { alert('Alasan keluar wajib dipilih!'); return; }
    setLoading(true);
    router.put(`/employees/${employee.id}/terminate-update`, form, { onSuccess:()=>{setLoading(false);onClose();}, onError:()=>setLoading(false) });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.75)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(480px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.6)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Pencil size={15}/> Edit Data Terminated</div>
            <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>{employee.nama_lengkap} · {employee.id_badge}</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal Keluar *</label>
              <input type="date" style={inp} value={form.tanggal_keluar} onChange={e=>setForm(f=>({...f,tanggal_keluar:e.target.value}))} />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Alasan Keluar *</label>
              <select style={inp} value={form.alasan_keluar} onChange={e=>setForm(f=>({...f,alasan_keluar:e.target.value}))}>
                <option value="">— Pilih Alasan —</option>
                {ALASAN.map(a=><option key={a} value={a}>{a}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan Tambahan</label>
              <textarea style={{...inp,minHeight:80,resize:'vertical'}} value={form.catatan_keluar} placeholder="Opsional..." onChange={e=>setForm(f=>({...f,catatan_keluar:e.target.value}))} />
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?0.7:1}}>
              {loading?<Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>:<span style={{display:'inline-flex',alignItems:'center',gap:6}}><Save size={14}/>Simpan</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL PINDAH PROJECT ──
function PindahProjectModal({ employee, projects, isSuperAdmin, onClose, onSuccess }) {
  const [toProjectId,    setToProjectId]    = useState('');
  const [catatan,        setCatatan]        = useState('');
  const [loading,        setLoading]        = useState(false);
  const [error,          setError]          = useState('');
  // currentProject bisa berubah setelah transfer berhasil (fresh dari server)
  const [currentProject, setCurrentProject] = useState(employee.project_nama || 'Tidak ada project');
  const [currentProjectId, setCurrentProjectId] = useState(employee.project_id);

  const availableProjects = projects.filter(p => p.id !== currentProjectId);
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  async function submit(e) {
    e.preventDefault();
    if (!toProjectId) { setError('Pilih project tujuan.'); return; }
    setLoading(true); setError('');
    try {
      const url = isSuperAdmin ? `/employees/${employee.id}/transfer-direct` : `/employees/${employee.id}/transfer-request`;
      const res = await axios.post(url, { to_project_id: toProjectId, catatan }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
      if (res.data.ok) {
        // Update state modal langsung dari response server
        if (res.data.new_project_id) {
          setCurrentProjectId(res.data.new_project_id);
          setCurrentProject(res.data.new_project_nama || '');
          setToProjectId(''); // reset pilihan
        }
        onSuccess(res.data.message, res.data.new_project_id, res.data.new_project_nama);
        onClose();
      } else {
        setError(res.data.message);
      }
    } catch (err) { setError(err.response?.data?.message || 'Terjadi kesalahan.'); }
    setLoading(false);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(460px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden'}}>
        <div style={{height:4,background:'linear-gradient(90deg,#3A8FE0,#22C97A)'}}/>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><RefreshCw size={15}/> {isSuperAdmin ? 'Pindah Project' : 'Ajukan Mutasi'}</div>
            <div style={{fontSize:11,color:'var(--muted)',marginTop:2}}>{employee.nama_lengkap} · {employee.jabatan}</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div style={{padding:'10px 14px',borderRadius:9,background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',fontSize:12}}>
              <div style={{color:'var(--muted)',marginBottom:4}}>Project saat ini:</div>
              <div style={{fontWeight:700,color:'var(--blue)'}}>{currentProject}</div>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Project Tujuan *</label>
              <select style={inp} value={toProjectId} onChange={e=>setToProjectId(e.target.value)}>
                <option value="">— Pilih Project —</option>
                {availableProjects.map(p=><option key={p.id} value={p.id}>{p.nama} ({p.kode})</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan {isSuperAdmin ? '(opsional)' : '(alasan mutasi)'}</label>
              <textarea style={{...inp,minHeight:80,resize:'vertical'}} value={catatan} onChange={e=>setCatatan(e.target.value)} placeholder={isSuperAdmin ? 'Alasan pemindahan...' : 'Jelaskan alasan pengajuan mutasi...'} />
            </div>
            {!isSuperAdmin && (
              <div style={{padding:'10px 14px',borderRadius:8,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',fontSize:11.5,color:'var(--muted2)'}}>
                <Loader2 size={12} style={{verticalAlign:-2}}/> Pengajuan akan dikirim ke Super Admin untuk disetujui. Karyawan belum pindah sampai disetujui.
              </div>
            )}
            {error && <div style={{padding:'8px 12px',borderRadius:8,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.2)',fontSize:12,color:'#E04545',display:'flex',alignItems:'center',gap:6}}><TriangleAlert size={13}/> {error}</div>}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:isSuperAdmin?'linear-gradient(135deg,#3A8FE0,#1A5FA0)':'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : isSuperAdmin ? <span style={{display:'inline-flex',alignItems:'center',gap:6}}><RefreshCw size={14}/>Pindahkan Sekarang</span> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Download size={14}/>Kirim Pengajuan</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL PINDAH PROJECT MASSAL ──
function BulkPindahProjectModal({ employeeIds, count, projects, isSuperAdmin, onClose, onSuccess }) {
  const [toProjectId, setToProjectId] = useState('');
  const [catatan,     setCatatan]     = useState('');
  const [loading,     setLoading]     = useState(false);
  const [error,       setError]       = useState('');

  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  async function submit(e) {
    e.preventDefault();
    if (!toProjectId) { setError('Pilih project tujuan.'); return; }
    setLoading(true); setError('');
    try {
      const res = await axios.post('/employees/transfer-bulk', { employee_ids: employeeIds, to_project_id: toProjectId, catatan }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
      if (res.data.ok) {
        onSuccess(res.data.message);
        onClose();
      } else {
        setError(res.data.message);
      }
    } catch (err) { setError(err.response?.data?.message || 'Terjadi kesalahan.'); }
    setLoading(false);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(460px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden'}}>
        <div style={{height:4,background:'linear-gradient(90deg,#3A8FE0,#22C97A)'}}/>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><RefreshCw size={15}/> {isSuperAdmin ? 'Pindah Project Massal' : 'Ajukan Mutasi Massal'}</div>
            <div style={{fontSize:11,color:'var(--muted)',marginTop:2}}>{count} karyawan terpilih</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Project Tujuan *</label>
              <select style={inp} value={toProjectId} onChange={e=>setToProjectId(e.target.value)}>
                <option value="">— Pilih Project —</option>
                {projects.map(p=><option key={p.id} value={p.id}>{p.nama} ({p.kode})</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan {isSuperAdmin ? '(opsional)' : '(alasan mutasi)'}</label>
              <textarea style={{...inp,minHeight:80,resize:'vertical'}} value={catatan} onChange={e=>setCatatan(e.target.value)} placeholder={isSuperAdmin ? 'Alasan pemindahan...' : 'Jelaskan alasan pengajuan mutasi...'} />
            </div>
            {!isSuperAdmin && (
              <div style={{padding:'10px 14px',borderRadius:8,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',fontSize:11.5,color:'var(--muted2)'}}>
                <Loader2 size={12} style={{verticalAlign:-2}}/> Pengajuan akan dikirim ke Super Admin untuk disetujui. Karyawan belum pindah sampai disetujui.
              </div>
            )}
            {error && <div style={{padding:'8px 12px',borderRadius:8,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.2)',fontSize:12,color:'#E04545',display:'flex',alignItems:'center',gap:6}}><TriangleAlert size={13}/> {error}</div>}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:isSuperAdmin?'linear-gradient(135deg,#3A8FE0,#1A5FA0)':'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : isSuperAdmin ? <span style={{display:'inline-flex',alignItems:'center',gap:6}}><RefreshCw size={14}/>Pindahkan Sekarang</span> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Download size={14}/>Kirim Pengajuan</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL PINDAH UNIT HO ──
function PindahUnitHoModal({ employee, onClose, onSuccess }) {
  const [toUnit,  setToUnit]  = useState('');
  const [catatan, setCatatan] = useState('');
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');

  const availableUnits = ['HO-1', 'HO-2'].filter(u => u !== employee.ho_unit);
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  async function submit(e) {
    e.preventDefault();
    if (!toUnit) { setError('Pilih unit tujuan.'); return; }
    setLoading(true); setError('');
    try {
      const res = await axios.post(`/employees/${employee.id}/pindah-unit-ho`, { unit: toUnit, catatan }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
      if (res.data.ok) {
        onSuccess(res.data.message);
        onClose();
      } else {
        setError(res.data.message);
      }
    } catch (err) { setError(err.response?.data?.message || 'Terjadi kesalahan.'); }
    setLoading(false);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(460px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden'}}>
        <div style={{height:4,background:'linear-gradient(90deg,#3A8FE0,#22C97A)'}}/>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Building2 size={15}/> Pindah Unit HO</div>
            <div style={{fontSize:11,color:'var(--muted)',marginTop:2}}>{employee.nama_lengkap} · {employee.jabatan}</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div style={{padding:'10px 14px',borderRadius:9,background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',fontSize:12}}>
              <div style={{color:'var(--muted)',marginBottom:4}}>Unit saat ini:</div>
              <div style={{fontWeight:700,color:'var(--blue)'}}>{employee.ho_unit || 'Belum ditentukan'}</div>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Unit Tujuan *</label>
              <select style={inp} value={toUnit} onChange={e=>setToUnit(e.target.value)}>
                <option value="">— Pilih Unit —</option>
                {availableUnits.map(u=><option key={u} value={u}>{u}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan (opsional)</label>
              <textarea style={{...inp,minHeight:70,resize:'vertical'}} value={catatan} onChange={e=>setCatatan(e.target.value)} placeholder="Alasan pemindahan unit..." />
            </div>
            {error && <div style={{padding:'8px 12px',borderRadius:8,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.2)',fontSize:12,color:'#E04545',display:'flex',alignItems:'center',gap:6}}><TriangleAlert size={13}/> {error}</div>}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><RefreshCw size={14}/>Pindahkan Sekarang</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL APPROVE/REJECT ──
function ApprovalModal({ transfer, action, onClose, onSuccess }) {
  const [catatan, setCatatan] = useState('');
  const [loading, setLoading] = useState(false);
  const isApprove = action === 'approve';
  const inp = { background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)', borderRadius:8, padding:'8px 11px', fontSize:12.5, fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box' };
  async function submit(e) {
    e.preventDefault();
    setLoading(true);
    try {
      const res = await axios.post(`/employees/transfers/${transfer.id}/${action}`, { catatan_approval: catatan }, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
      if (res.data.ok) { onSuccess(res.data.message); onClose(); }
    } catch (err) { alert(err.response?.data?.message || 'Terjadi kesalahan.'); }
    setLoading(false);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(440px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden'}}>
        <div style={{height:4,background:isApprove?'linear-gradient(90deg,#22C97A,#148050)':'linear-gradient(90deg,#E04545,#A01010)'}}/>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}>{isApprove ? <><CheckCircle2 size={15}/> Setujui Mutasi</> : <><XCircle size={15}/> Tolak Mutasi</>}</div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div style={{padding:'10px 14px',borderRadius:9,background:'var(--bg3)',border:'1px solid var(--border)',fontSize:12}}>
              <div style={{fontWeight:600,marginBottom:4}}>{transfer.employee_name}</div>
              <div style={{color:'var(--muted)'}}>{transfer.from_project} → <span style={{color:isApprove?'#22C97A':'#E04545',fontWeight:700}}>{transfer.to_project}</span></div>
              {transfer.catatan && <div style={{marginTop:6,fontSize:11,color:'var(--muted2)'}}>Alasan: {transfer.catatan}</div>}
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan {isApprove ? '(opsional)' : '(alasan penolakan)'}</label>
              <textarea style={{...inp,minHeight:70,resize:'vertical'}} value={catatan} onChange={e=>setCatatan(e.target.value)} placeholder={isApprove ? 'Catatan persetujuan...' : 'Jelaskan alasan penolakan...'} />
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:isApprove?'linear-gradient(135deg,#22C97A,#148050)':'linear-gradient(135deg,#E04545,#A01010)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?<Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>:isApprove?<span style={{display:'inline-flex',alignItems:'center',gap:6}}><CheckCircle2 size={14}/>Setujui</span>:<span style={{display:'inline-flex',alignItems:'center',gap:6}}><XCircle size={14}/>Tolak</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── TAB PINDAH PROJECT ──
function TabPindahProject({ isSuperAdmin, userProjectId, isViewer }) {
  const [data,          setData]          = useState(null);
  const [loading,       setLoading]       = useState(true);
  const [approvalModal, setApprovalModal] = useState(null);
  const [flashMsg,      setFlashMsg]      = useState('');
  const [filterStatus,  setFilterStatus]  = useState('pending');

  useEffect(() => { fetchData(); }, []);

  async function fetchData() {
    setLoading(true);
    try { const res = await axios.get('/employees/transfers'); setData(res.data); } catch {}
    setLoading(false);
  }

  function showFlash(msg) { setFlashMsg(msg); fetchData(); setTimeout(() => setFlashMsg(''), 4000); }

  const statusColor = {
    pending:  { bg:'rgba(232,160,32,.12)', color:'var(--accent)', label:'Menunggu' },
    approved: { bg:'rgba(34,201,122,.12)', color:'#22C97A',       label:'Disetujui' },
    rejected: { bg:'rgba(224,69,69,.12)',  color:'#E04545',        label:'Ditolak' },
  };
  const filtered = (data?.transfers||[]).filter(t => filterStatus === 'all' ? true : t.status === filterStatus);

  if (loading) return <div style={{padding:40,textAlign:'center',color:'var(--muted)',display:'flex',alignItems:'center',justifyContent:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/>Memuat...</div>;

  return (
    <div>
      {flashMsg && <div style={{marginBottom:14,padding:'10px 16px',borderRadius:8,background:'rgba(34,201,122,.1)',border:'1px solid rgba(34,201,122,.25)',fontSize:12.5,color:'#22C97A',display:'flex',alignItems:'center',gap:6}}><CheckCircle2 size={14}/> {flashMsg}</div>}
      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:16,flexWrap:'wrap',gap:10}}>
        <div>
          <div style={{fontSize:13,fontWeight:600}}>Pengajuan Pindah Project</div>
          <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>{isSuperAdmin ? 'Semua pengajuan mutasi dari project user' : 'Pengajuan mutasi yang kamu ajukan'}</div>
        </div>
        <div style={{display:'flex',gap:4}}>
          {[
            {key:'pending',  label:`Menunggu (${(data?.transfers||[]).filter(t=>t.status==='pending').length})`},
            {key:'approved', label:'Disetujui'},
            {key:'rejected', label:'Ditolak'},
            {key:'all',      label:'Semua'},
          ].map(f=>(
            <button key={f.key} onClick={()=>setFilterStatus(f.key)}
              style={{padding:'5px 12px',borderRadius:99,border:'none',fontSize:11.5,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
                background:filterStatus===f.key?'linear-gradient(135deg,#E8A020,#A06010)':'var(--bg3)',
                color:filterStatus===f.key?'#0C0F14':'var(--muted2)'}}>
              {f.label}
            </button>
          ))}
        </div>
      </div>
      {isSuperAdmin && data?.pending_count > 0 && (
        <div style={{marginBottom:14,padding:'10px 16px',borderRadius:8,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.25)',fontSize:12.5,color:'var(--accent)',display:'flex',alignItems:'center',gap:8}}>
          <Bell size={13} style={{verticalAlign:-2}}/> Ada <b>{data.pending_count}</b> pengajuan mutasi yang menunggu persetujuan kamu.
        </div>
      )}
      <div className="panel" style={{overflow:'hidden'}}>
        {filtered.length === 0 ? (
          <div style={{padding:32,textAlign:'center',color:'var(--muted)',fontSize:12}}>{filterStatus === 'pending' ? 'Tidak ada pengajuan yang menunggu' : 'Tidak ada data'}</div>
        ) : (
          <table className="kar-table">
            <thead>
              <tr>
                <th>Karyawan</th><th>Dari Project</th><th>Ke Project</th>
                <th>Pengaju</th><th>Catatan</th><th>Status</th><th>Waktu</th>
                {!isViewer && (isSuperAdmin || userProjectId) && <th style={{textAlign:'center'}}>Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {filtered.map((t,i)=>{
                const sc = statusColor[t.status]||statusColor.pending;
                return (
                  <tr key={i}>
                    <td>
                      <div style={{fontWeight:600}}>{t.employee_name}</div>
                      <div style={{fontSize:10.5,color:'var(--muted)',fontFamily:'monospace'}}>{t.employee_badge}</div>
                      <div style={{fontSize:10.5,color:'var(--muted2)'}}>{t.employee_jabatan}</div>
                    </td>
                    <td>
                      <span style={{background:'rgba(224,69,69,.1)',color:'#E04545',padding:'2px 8px',borderRadius:99,fontSize:11,fontWeight:600}}>{t.from_project_kode?.toUpperCase()}</span>
                      <div style={{fontSize:10.5,color:'var(--muted)',marginTop:2}}>{t.from_project}</div>
                    </td>
                    <td>
                      <span style={{background:'rgba(34,201,122,.1)',color:'#22C97A',padding:'2px 8px',borderRadius:99,fontSize:11,fontWeight:600}}>{t.to_project_kode?.toUpperCase()}</span>
                      <div style={{fontSize:10.5,color:'var(--muted)',marginTop:2}}>{t.to_project}</div>
                    </td>
                    <td style={{fontSize:12,color:'var(--muted2)'}}>{t.requested_by}</td>
                    <td style={{fontSize:11.5,color:'var(--muted2)',maxWidth:160}}>
                      {t.catatan||'—'}
                      {t.catatan_approval && <div style={{marginTop:4,fontSize:10.5,color:t.status==='approved'?'#22C97A':'#E04545'}}>Catatan: {t.catatan_approval}</div>}
                    </td>
                    <td>
                      <span style={{background:sc.bg,color:sc.color,padding:'3px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{sc.label}</span>
                      {t.approved_by && <div style={{fontSize:10,color:'var(--muted)',marginTop:2}}>oleh {t.approved_by}</div>}
                    </td>
                    <td style={{fontSize:11,color:'var(--muted2)',whiteSpace:'nowrap'}}>{t.created_at}</td>
                    {!isViewer && (isSuperAdmin || userProjectId == t.to_project_id_raw) && (
                      <td style={{textAlign:'center'}}>
                        {t.status === 'pending' ? (
                          <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                            <button onClick={()=>setApprovalModal({transfer:t,action:'approve'})} style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(34,201,122,.12)',color:'#22C97A',border:'1px solid rgba(34,201,122,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><CheckCircle2 size={12}/>Setujui</button>
                            <button onClick={()=>setApprovalModal({transfer:t,action:'reject'})}  style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><XCircle size={12}/>Tolak</button>
                          </div>
                        ) : <span style={{fontSize:11,color:'var(--muted)'}}>—</span>}
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
      {approvalModal && <ApprovalModal transfer={approvalModal.transfer} action={approvalModal.action} onClose={()=>setApprovalModal(null)} onSuccess={showFlash} />}
    </div>
  );
}

// ── TABEL AKTIF ──
function TabAktif({ data, prevUrl, nextUrl, links, curPage, lastPage, total, jabatan_list, projects, search, jabatan, stats, onTerminate, onPindah, isSuperAdmin, activeProjectId, project_info}) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const isHo = project_info?.tipe_gaji === 'ho';
  const [showAdd,    setShowAdd]    = useState(false);
  const [showPicker, setShowPicker] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [searchVal,  setSearchVal]  = useState(search);
  const [jabatanVal, setJabatanVal] = useState(jabatan);
  const [hoUnitTab,  setHoUnitTab]  = useState('all');
  const [selectedIds,   setSelectedIds]   = useState([]);
  const [showBulkPindah, setShowBulkPindah] = useState(false);
  const [bulkFlash,     setBulkFlash]     = useState('');
  const projectColsKey = `emp-table-cols-${project_info?.kode || 'all'}`;
  const [activeCols, setActiveCols] = useState(() => {
    try { const saved = localStorage.getItem(projectColsKey); return saved ? JSON.parse(saved) : DEFAULT_COLS; }
    catch { return DEFAULT_COLS; }
  });
  // Pengaturan kolom disimpan terpisah per project (kunci localStorage beda per kode
  // project) supaya tidak tercampur — misalnya kolom yang dipilih untuk HO tidak ikut
  // muncul saat pindah ke Giam/Khawista/Purnama/MD, begitu juga sebaliknya.
  useEffect(() => {
    try { const saved = localStorage.getItem(projectColsKey); setActiveCols(saved ? JSON.parse(saved) : DEFAULT_COLS); }
    catch { setActiveCols(DEFAULT_COLS); }
  }, [projectColsKey]);

  // Reset seleksi checkbox tiap kali data berubah (ganti halaman/filter/reload)
  useEffect(() => { setSelectedIds([]); }, [data]);

  // HO tidak punya compliance (SIM/SIO/MCU/KP/Badge) — sembunyikan kolom itu meski tersimpan di preferensi user.
  const visibleCols = isHo
    ? activeCols.filter(k => !['sim','sio_k3','mcu','kp','badge'].includes(k))
    : activeCols.filter(k => k !== 'masa_kerja');
  const displayData = isHo && hoUnitTab !== 'all' ? data.filter(e => e.ho_unit === hoUnitTab) : data;

  function applyCols(cols) { setActiveCols(cols); localStorage.setItem(projectColsKey, JSON.stringify(cols)); }
  function doFilter(s, j) { router.get('/employees', {search:s, jabatan:j}, {preserveState:true, replace:true}); }
  function toggleOne(id) { setSelectedIds(prev => prev.includes(id) ? prev.filter(x=>x!==id) : [...prev, id]); }
  function toggleAll() {
    const pageIds = displayData.map(e=>e.id);
    const allSelected = pageIds.length > 0 && pageIds.every(id => selectedIds.includes(id));
    setSelectedIds(allSelected ? selectedIds.filter(id => !pageIds.includes(id)) : [...new Set([...selectedIds, ...pageIds])]);
  }

  const FIXED_FILTERS = ['', 'Driver Dump Truck', 'Spotter', 'HES Man'];
  const isCustomFilter = jabatanVal && !FIXED_FILTERS.includes(jabatanVal) && jabatanVal !== 'PMCOW';

  // HO tidak punya jabatan lapangan tetap seperti project lain — card jabatan menyesuaikan
  // jabatan yang paling banyak muncul di unit (Semua/HO-1/HO-2) yang sedang dipilih.
  const hoStats = isHo ? (stats.ho?.[hoUnitTab] || { total: 0, top_jabatan: [] }) : null;
  const HO_CARD_COLORS = ['#E8A020', '#22C97A', '#9B59B6', '#E06A20'];
  const quickStats = isHo ? (() => {
    const cards = [
      { label:'Total Aktif', val: hoStats.total || 0, color:'#3A8FE0', filter: '' },
      ...hoStats.top_jabatan.map((j,i) => ({ label:j.label, val:j.val, color:HO_CARD_COLORS[i % HO_CARD_COLORS.length], filter:j.label })),
    ];
    if (jabatanVal && !hoStats.top_jabatan.some(j => j.label === jabatanVal)) {
      cards.push({ label: jabatanVal, val: total, color:'#9B59B6', filter: jabatanVal });
    }
    return cards;
  })() : [
    { label:'Total Aktif',       val: stats.total      || 0, color:'#3A8FE0', filter: '' },
    { label:'Driver Dump Truck', val: stats.dump_truck || 0, color:'#E8A020', filter: 'Driver Dump Truck' },
    { label:'Spotter',           val: stats.spotter    || 0, color:'#22C97A', filter: 'Spotter' },
    { label:'PMCOW',             val: stats.pmcow      || 0, color:'#3A8FE0', filter: 'PMCOW' },
    isCustomFilter
      ? { label: jabatanVal, val: total, color:'#9B59B6', filter: jabatanVal }
      : { label:'HES Man',  val: stats.hes || 0, color:'#E06A20', filter: 'HES Man' },
  ];

  function renderCell(e, colKey) {
    switch(colKey) {
      case 'sim':       return <td key={colKey}>{simPill(e.sim_status,e.type_sim)}</td>;
      case 'sio_k3':    return <td key={colKey}><Pill type={e.sio_k3==='YES'?'green':'gray'}>{e.sio_k3==='YES'?'Ada':'—'}</Pill></td>;
      case 'mcu':       return <td key={colKey}><Pill type={e.mcu_status==='expired'?'red':e.mcu_status==='warning'?'warn':e.mcu_status==='valid'?'green':'gray'}>{e.status_mcu||'—'}</Pill></td>;
      case 'kp':        return <td key={colKey}><Pill type={e.status_kp?'green':'gray'}>{e.status_kp?'Ada':'—'}</Pill></td>;
      case 'badge':     return <td key={colKey}><Pill type={e.badge_status==='expired'?'red':e.badge_status==='warning'?'warn':e.badge_status==='valid'?'green':'gray'}>{e.badge_status==='expired'?'Expired':e.badge_status==='warning'?'<30hr':e.badge_status==='valid'?'Valid':'—'}</Pill></td>;
      case 'tgl_masuk': return <td key={colKey} style={{fontSize:11.5,color:'var(--muted2)',whiteSpace:'nowrap'}}>{e.tanggal_masuk||'—'}</td>;
      case 'masa_kerja': return <td key={colKey} style={{fontSize:11.5,color:'var(--muted2)',whiteSpace:'nowrap'}}>{e.masa_kerja||'—'}</td>;
      case 'agama':     return <td key={colKey} style={{fontSize:11.5,color:'var(--muted2)'}}>{e.agama||'—'}</td>;
      case 'alamat':    return <td key={colKey} style={{fontSize:11,color:'var(--muted)',maxWidth:160,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{e.alamat||'—'}</td>;
      case 'umur':      return (<td key={colKey} style={{textAlign:'center',whiteSpace:'nowrap'}}>{e.umur!=null?(<span style={{fontSize:12,fontWeight:600,color:e.umur>=56?'#E04545':e.umur>=53?'var(--accent)':'var(--text)'}}>{e.umur} thn{e.umur>=56&&<span style={{marginLeft:4,display:'inline-flex'}}><TriangleAlert size={10}/></span>}</span>):<span style={{color:'var(--muted)'}}>—</span>}</td>);
      default: {
        if (colKey.startsWith('ho_')) {
          return <td key={colKey} style={{fontSize:11.5,color:'var(--muted2)'}}>{e[colKey]||'—'}</td>;
        }
        const docDef = DOC_TIPES.find(d => d.key === colKey);
        if (docDef) {
          const hasDoc = (e.docs||[]).includes(docDef.tipe);
          return <td key={colKey} style={{textAlign:'center'}}>{hasDoc ? <span style={{color:'#22C97A',display:'flex',justifyContent:'center'}}><Check size={14}/></span> : <span style={{color:'var(--muted)',display:'flex',justifyContent:'center'}}><X size={13}/></span>}</td>;
        }
        return <td key={colKey}>—</td>;
      }
    }
  }
  function renderHeader(colKey) {
    const col = OPTIONAL_COLS.find(c => c.key === colKey);
    const compact = colKey !== 'alamat';
    return <th key={colKey} style={{textAlign:colKey.startsWith('doc_')?'center':undefined, whiteSpace:compact?'nowrap':undefined, width:compact?'1%':undefined}}>{col?.label||colKey}</th>;
  }

  return (
    <>
      {showAdd    && <TambahModal jabatan_list={jabatan_list} projects={projects} onClose={()=>setShowAdd(false)} isHo={isHo} isSuperAdmin={isSuperAdmin} />}
      {showImport && <ImportModal onClose={()=>setShowImport(false)} isHo={isHo} />}
      {showPicker && <ColPickerModal selected={activeCols} onClose={()=>setShowPicker(false)} onApply={applyCols} hideCompliance={isHo} />}
      {showBulkPindah && (
        <BulkPindahProjectModal
          employeeIds={selectedIds}
          count={selectedIds.length}
          projects={projects}
          isSuperAdmin={isSuperAdmin}
          onClose={()=>setShowBulkPindah(false)}
          onSuccess={(msg)=>{
            setBulkFlash(msg);
            setSelectedIds([]);
            router.visit(window.location.pathname + window.location.search, {
              preserveScroll: true,
              onSuccess: () => setTimeout(() => setBulkFlash(''), 5000),
            });
          }}
        />
      )}
      <div style={{display:'flex',gap:12,marginBottom:14}}>
        {quickStats.map((q,i)=>{
          const isActive = jabatanVal === q.filter;
          return (
            <div key={i} onClick={()=>{setJabatanVal(q.filter);doFilter(searchVal,q.filter);}}
              style={{flex:1,borderRadius:9,padding:'12px 14px',textAlign:'center',cursor:'pointer',transition:'all .15s',
                background:isActive?'rgba(232,160,32,.1)':'var(--card)',
                border:`1px solid ${isActive?'var(--accent)':'var(--border)'}`,
                boxShadow:isActive?'0 0 0 1px var(--accent)':'none'}}>
              <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:isActive?'var(--accent)':q.color}}>{q.val}</div>
              <div style={{fontSize:10.5,color:isActive?'var(--accent)':'var(--muted)',marginTop:2,fontWeight:isActive?600:400}}>{q.label}</div>
              {isActive && <div style={{width:20,height:3,borderRadius:99,background:'var(--accent)',margin:'5px auto 0'}}/>}
            </div>
          );
        })}
      </div>
      <div className="panel">
        <div className="panel-head">
          <div className="panel-title" style={{display:"flex",alignItems:"center",gap:6}}><User size={14}/> Data Karyawan Aktif — PT. Andalas Karya Mulia</div>
          <div style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap'}}>
            <select value={jabatanVal} onChange={e=>{setJabatanVal(e.target.value);doFilter(searchVal,e.target.value);}}>
              <option value="">Semua Jabatan</option>
              {jabatan_list.map((j,i)=><option key={i} value={typeof j==='string'?j:j.nama_jabatan}>{typeof j==='string'?j:j.nama_jabatan}</option>)}
            </select>
            <button type="button" onClick={()=>setShowPicker(true)} style={{padding:'6px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><Settings size={13}/> Kolom</button>
            {!isViewer && <button type="button" onClick={()=>setShowAdd(true)} style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><Plus size={13}/> Tambah</button>}
            {!isViewer && (!isSuperAdmin || activeProjectId) && (
              <button type="button" onClick={()=>setShowImport(true)}
                style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                <Upload size={13}/> Import Excel
              </button>
            )}
            <a href="/export/karyawan" style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><Download size={13}/> Export</a>
          </div>
        </div>
        <div style={{padding:'14px 16px'}}>
          <div style={{position:'relative',marginBottom:14}}>
            <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={15}/></span>
            <input className="search-input" type="text" value={searchVal} placeholder="Cari nama, NIK, jabatan, ID Badge..."
              onChange={e=>{setSearchVal(e.target.value);doFilter(e.target.value,jabatanVal);}} style={{paddingLeft:'36px'}} />
          </div>
          {isHo && (
            <div style={{display:'flex',gap:6,marginBottom:14}}>
              {[{key:'all',label:'Semua',icon:Users},{key:'HO-1',label:'HO-1',icon:Building2},{key:'HO-2',label:'HO-2',icon:Building2}].map(t=>(
                <div key={t.key} onClick={()=>setHoUnitTab(t.key)}
                  style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,
                    display:'flex',alignItems:'center',gap:6,
                    background:hoUnitTab===t.key?'rgba(232,160,32,.15)':'var(--bg3)',
                    color:hoUnitTab===t.key?'var(--accent)':'var(--muted2)',
                    border:`1px solid ${hoUnitTab===t.key?'var(--accent)':'var(--border)'}`}}>
                  <t.icon size={13}/> {t.label}
                </div>
              ))}
            </div>
          )}
          {bulkFlash && (
            <div style={{marginBottom:12,padding:'10px 14px',borderRadius:8,background:'rgba(34,201,122,.08)',border:'1px solid rgba(34,201,122,.25)',fontSize:12.5,color:'var(--green)',display:'flex',alignItems:'center',gap:8}}>
              <CheckCircle2 size={13}/> {bulkFlash}
            </div>
          )}
          {!isViewer && selectedIds.length > 0 && (
            <div style={{marginBottom:12,padding:'10px 14px',borderRadius:8,background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.25)',display:'flex',alignItems:'center',justifyContent:'space-between',gap:10,flexWrap:'wrap'}}>
              <div style={{fontSize:12.5,color:'var(--blue)',fontWeight:600}}>{selectedIds.length} karyawan terpilih</div>
              <div style={{display:'flex',gap:8}}>
                <button type="button" onClick={()=>setSelectedIds([])} style={{padding:'6px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal Pilih</button>
                <button type="button" onClick={()=>setShowBulkPindah(true)} style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><RefreshCw size={13}/> {isSuperAdmin ? 'Pindahkan Project' : 'Ajukan Mutasi'}</button>
              </div>
            </div>
          )}
          <div style={{overflowX:'auto'}}>
            <table className="kar-table" style={{width:'auto'}}>
              <thead>
                <tr>
                  {!isViewer && (
                    <th style={{width:'1%',whiteSpace:'nowrap',textAlign:'center'}}>
                      <input type="checkbox" checked={displayData.length>0 && displayData.every(e=>selectedIds.includes(e.id))} onChange={toggleAll} style={{cursor:'pointer'}}/>
                    </th>
                  )}
                  <th style={{width:'1%',whiteSpace:'nowrap'}}>NIK</th>
                  {!isHo && <th style={{width:'1%',whiteSpace:'nowrap'}}>Badge</th>}
                  <th>Nama</th><th>Jabatan</th>
                  {visibleCols.map(k => renderHeader(k))}
                  {!isViewer && <th style={{width:'1%',whiteSpace:'nowrap',textAlign:'center'}}>Aksi</th>}
                </tr>
              </thead>
              <tbody>
                {displayData.map((e,i)=>(
                  <tr key={i} data-emp-id={e.id}>
                  {!isViewer && (
                    <td style={{textAlign:'center'}}>
                      <input type="checkbox" checked={selectedIds.includes(e.id)} onChange={()=>toggleOne(e.id)} style={{cursor:'pointer'}}/>
                    </td>
                  )}
                  <td>
                    <span style={{fontSize:11.5,whiteSpace:'nowrap'}}>{e.no_ktp||'—'}</span>
                  </td>
                  {!isHo && (
                    <td>
                      <div style={{display:'flex',alignItems:'center',gap:8}}>
                        <div style={{width:24,height:24,borderRadius:6,background:getAvColor(e.id_badge),display:'flex',alignItems:'center',justifyContent:'center',fontSize:9,fontWeight:700,color:'#fff',flexShrink:0}}>{getAv(e.nama_lengkap)}</div>
                        <span style={{fontSize:11.5,whiteSpace:'nowrap'}}>{e.id_badge||'—'}</span>
                      </div>
                    </td>
                  )}
                  <td style={{fontWeight:500}}>{e.nama_lengkap}</td>
                  <td style={{maxWidth:220,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{e.jabatan}</td>
                    {visibleCols.map(k => renderCell(e, k))}
                    {!isViewer && (
                      <td>
                        <div style={{display:'flex',gap:6,justifyContent:'center'}}>
                          <Link href={`/employees/${e.id}/edit`} style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',textDecoration:'none',border:'1px solid rgba(232,160,32,.25)',whiteSpace:'nowrap',display:'inline-flex',alignItems:'center',gap:5}}><Pencil size={11}/> Edit</Link>
                          <button type="button" onClick={()=>onPindah(e)} style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(58,143,224,.1)',color:'var(--blue)',border:'1px solid rgba(58,143,224,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap',display:'flex',alignItems:'center',gap:5}}><RefreshCw size={11}/> Pindah</button>
                          <button type="button" onClick={()=>onTerminate(e)} style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap',display:'flex',alignItems:'center',gap:5}}><LogOut size={11}/> Terminate</button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
                {displayData.length===0 && <tr><td colSpan={(isHo?3:4)+visibleCols.length+(isViewer?0:2)} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Tidak ada data karyawan</td></tr>}
              </tbody>
            </table>
          </div>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginTop:14,flexWrap:'wrap',gap:8}}>
            <div style={{fontSize:11,color:'var(--muted)'}}>Halaman {curPage} dari {lastPage} · {data.length} dari {total} karyawan</div>
            <div style={{display:'flex',gap:4,flexWrap:'wrap'}}>
              <button disabled={!prevUrl} onClick={()=>prevUrl&&router.get(prevUrl)} style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:prevUrl?'var(--text)':'var(--muted)',cursor:prevUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>← Prev</button>
              {links.map((l,i)=>(<button key={i} disabled={!l.url} onClick={()=>l.url&&router.get(l.url)} dangerouslySetInnerHTML={{__html:l.label}} style={{padding:'5px 10px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:l.active?'var(--accent)':'var(--card)',color:l.active?'#0C0F14':l.url?'var(--text)':'var(--muted)',cursor:l.url?'pointer':'default',fontFamily:"'Outfit',sans-serif",fontWeight:l.active?700:400,minWidth:34}}/>))}
              <button disabled={!nextUrl} onClick={()=>nextUrl&&router.get(nextUrl)} style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:nextUrl?'var(--text)':'var(--muted)',cursor:nextUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>Next →</button>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

// ── TABEL TERMINATED ──
function TabTerminated({ terminated }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [editEmp, setEditEmp] = useState(null);
  const [confirmHapus, setConfirmHapus] = useState(null);
  const [confirmReaktif, setConfirmReaktif] = useState(null);
  const ALASAN_COLOR = {
    'RESIGN':          { bg:'rgba(58,143,224,.1)',  color:'var(--blue)' },
    'PHK':             { bg:'rgba(224,69,69,.1)',   color:'#E04545' },
    'KONTRAK HABIS':   { bg:'rgba(232,160,32,.1)',  color:'var(--accent)' },
    'MENINGGAL DUNIA': { bg:'rgba(128,128,128,.1)', color:'var(--muted2)' },
    'MUTASI':          { bg:'rgba(34,201,122,.1)',  color:'var(--green)' },
    'LAINNYA':         { bg:'rgba(128,128,128,.1)', color:'var(--muted2)' },
  };
  function reactivate(id) {
    if (!window.confirm('Aktifkan kembali karyawan ini?')) return;
    router.post(`/employees/${id}/reactivate`, {}, { preserveScroll: true });
  }
  function hapusPermanent(emp) {
    router.delete(`/employees/${emp.id}`, { preserveScroll: true });
    setConfirmHapus(null);
  }
  return (
    <>
      {editEmp && <EditTerminatedModal employee={editEmp} onClose={()=>setEditEmp(null)} />}
      <ConfirmModal
        open={!!confirmHapus}
        onCancel={() => setConfirmHapus(null)}
        onConfirm={() => hapusPermanent(confirmHapus)}
        title="Hapus Permanen Karyawan"
        message={confirmHapus ? <>Data <b style={{color:'var(--text)'}}>{confirmHapus.nama}</b> akan dihapus permanen dari sistem.<br/>Data yang dihapus <b style={{color:'#E04545'}}>tidak bisa dikembalikan</b>.</> : ''}
        confirmLabel="Ya, Hapus Permanen"
        type="danger"
      />

      <ConfirmModal
        open={!!confirmReaktif}
        onCancel={()=>setConfirmReaktif(null)}
        onConfirm={()=>{
          router.post(`/employees/${confirmReaktif.id}/reactivate`, {}, { preserveScroll: true });
          setConfirmReaktif(null);
        }}
        title="Aktifkan Kembali Karyawan"
        message={confirmReaktif ? <>Aktifkan kembali <b style={{color:'var(--text)'}}>{confirmReaktif.nama}</b> ke tab Karyawan Aktif?</> : ''}
        confirmLabel="Ya, Aktifkan"
        type="warning"
      />

      <div className="panel">
        <div className="panel-head">
          <div className="panel-title" style={{display:"flex",alignItems:"center",gap:6}}><Archive size={14}/> Arsip Karyawan Terminated</div>
          <div style={{fontSize:11.5,color:'var(--muted)'}}>{terminated.length} karyawan</div>
        </div>
        <div style={{padding:'14px 16px'}}>
          {terminated.length === 0 ? (
            <div style={{padding:40,textAlign:'center',color:'var(--muted)',fontSize:13}}>Belum ada karyawan yang di-terminate</div>
          ) : (
            <div style={{overflowX:'auto'}}>
              <table className="kar-table">
                <thead><tr><th>NIK</th><th>Nama</th><th>Jabatan</th><th>Tgl Keluar</th><th>Alasan</th><th>Catatan</th>{!isViewer && <th style={{textAlign:'center'}}>Aksi</th>}</tr></thead>
                <tbody>
                  {terminated.map((e,i)=>{
                    const ac = ALASAN_COLOR[e.alasan_keluar]||ALASAN_COLOR['LAINNYA'];
                    return (
                      <tr key={i} style={{opacity:.85}}>
                        <td><div style={{display:'flex',alignItems:'center',gap:8}}><div style={{width:24,height:24,borderRadius:6,background:'rgba(128,128,128,.2)',display:'flex',alignItems:'center',justifyContent:'center',fontSize:9,fontWeight:700,color:'var(--muted)',flexShrink:0}}>{getAv(e.nama_lengkap)}</div><span style={{fontSize:11.5,color:'var(--muted2)'}}>{e.no_ktp||'—'}</span></div></td>
                        <td style={{fontWeight:500,color:'var(--muted2)'}}>{e.nama_lengkap}</td>
                        <td style={{fontSize:12,color:'var(--muted)'}}>{e.jabatan}</td>
                        <td style={{fontSize:12,whiteSpace:'nowrap'}}>{fmtDate(e.tanggal_keluar)}</td>
                        <td><span style={{background:ac.bg,color:ac.color,padding:'3px 9px',borderRadius:99,fontSize:11,fontWeight:600}}>{e.alasan_keluar||'—'}</span></td>
                        <td style={{fontSize:11.5,color:'var(--muted)',maxWidth:200,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{e.catatan_keluar||'—'}</td>
                        {!isViewer && (
                          <td style={{textAlign:'center'}}>
                            <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                              <button type="button" onClick={()=>setEditEmp(e)} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap',display:'flex',alignItems:'center',gap:5}}><Pencil size={11}/> Edit</button>
                              <button type="button" onClick={()=>setConfirmReaktif({id:e.id, nama:e.nama_lengkap})} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(34,201,122,.1)',color:'var(--green)',border:'1px solid rgba(34,201,122,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap',display:'flex',alignItems:'center',gap:5}}><RotateCcw size={11}/> Aktifkan</button>
                              <button type="button" onClick={()=>setConfirmHapus({id:e.id,nama:e.nama_lengkap})} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",whiteSpace:'nowrap',display:'flex',alignItems:'center',gap:5}}><Trash2 size={11}/> Hapus</button>
                            </div>
                          </td>
                        )}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

// ── MAIN COMPONENT ──
export default function EmployeeIndex({
  employees = {data:[],total:0,links:[],current_page:1,last_page:1},
  terminated = [],
  jabatan_list = [],
  stats = {},
  projects = [],
  auth = {},
  project_info = null,
}) {
  const [activeTab,    setActiveTab]    = useState('aktif');
  const [terminateEmp, setTerminateEmp] = useState(null);
  const [pindahEmp,    setPindahEmp]    = useState(null);
  const [pindahHoEmp,  setPindahHoEmp]  = useState(null);
  const [flashTransfer,setFlashTransfer]= useState('');
  const [pendingCount, setPendingCount] = useState(0);

  // Auto scroll + highlight dari ?highlight=id
  const { url } = usePage();

  useEffect(() => {
    const params = new URLSearchParams(url.split('?')[1] || '');
    const highlightId = params.get('highlight');
    if (!highlightId) return;

    let attempts = 0;
    const interval = setInterval(() => {
        const row = document.querySelector(`tr[data-emp-id="${highlightId}"]`);
        if (row) {
            clearInterval(interval);
            setTimeout(() => row.scrollIntoView({ behavior:'smooth', block:'center' }), 50);
            setTimeout(() => {
                row.style.transition = 'background 0.3s ease';
                row.style.background = 'rgba(232,160,32,.3)';
            }, 300);
            setTimeout(() => {
                row.style.transition = 'background 1.5s ease';
                row.style.background = '';
            }, 3000);

            params.delete('highlight');
            const newQuery = params.toString();
            const newUrl = url.split('?')[0] + (newQuery ? '?' + newQuery : '');
            window.history.replaceState({}, '', newUrl);
        } else if (attempts++ > 20) {
            clearInterval(interval);
        }
    }, 150);

    return () => clearInterval(interval);
}, [url]);

  const isSuperAdmin = auth?.user?.can?.is_super_admin || false;

  // Fetch pending transfer count
  useEffect(() => {
    if (!isSuperAdmin) return;
    axios.get('/employees/transfers/pending-count')
      .then(r => setPendingCount(r.data.count || 0))
      .catch(() => {});
  }, []);

  // Sync pindahEmp dengan data terbaru setelah router.reload()
  // Supaya modal selalu tampilkan project yang benar
  useEffect(() => {
    if (!pindahEmp || !employees.data) return;
    const updated = employees.data.find(e => e.id === pindahEmp.id);
    if (updated && (updated.project_id !== pindahEmp.project_id || updated.project_nama !== pindahEmp.project_nama)) {
      setPindahEmp(prev => ({
        ...prev,
        project_id:   updated.project_id,
        project_nama: updated.project_nama,
      }));
    }
  }, [employees.data]);

  const data     = employees.data         || [];
  const total    = employees.total        || 0;
  const links    = (employees.links||[]).filter(l=>l.label!=='&laquo; Previous'&&l.label!=='Next &raquo;');
  const prevUrl  = employees.prev_page_url;
  const nextUrl  = employees.next_page_url;
  const curPage  = employees.current_page || 1;
  const lastPage = employees.last_page    || 1;

  const tabs = [
    { key:'aktif',      label:'Karyawan Aktif',     count: total, icon: User },
    { key:'terminated', label:'Terminated',          count: terminated.length, icon: Archive },
    { key:'transfer',   label:'Pindah Project',      count: pendingCount > 0 ? pendingCount : null, isAlert: pendingCount > 0, icon: RefreshCw },
  ];

  return (
    <AppLayout title="Data" subtitle="Karyawan">
      {/* Modal terminate */}
      {terminateEmp && <TerminationModal employee={terminateEmp} onClose={()=>setTerminateEmp(null)} />}

      {/* Modal pindah project */}
      {pindahEmp && (
        <PindahProjectModal
          employee={pindahEmp}
          projects={projects}
          isSuperAdmin={isSuperAdmin}
          onClose={() => setPindahEmp(null)}
          onSuccess={(msg, newProjectId, newProjectNama) => {
            setFlashTransfer(msg);
            setPindahEmp(null);
            // Hard refresh supaya data project di tabel selalu fresh
            router.visit(window.location.pathname + window.location.search, {
              preserveScroll: true,
              onSuccess: () => setTimeout(() => setFlashTransfer(''), 4000),
            });
          }}
        />
      )}

      {/* Modal pindah unit HO */}
      {pindahHoEmp && (
        <PindahUnitHoModal
          employee={pindahHoEmp}
          onClose={() => setPindahHoEmp(null)}
          onSuccess={(msg) => {
            setFlashTransfer(msg);
            setPindahHoEmp(null);
            router.visit(window.location.pathname + window.location.search, {
              preserveScroll: true,
              onSuccess: () => setTimeout(() => setFlashTransfer(''), 4000),
            });
          }}
        />
      )}

      {/* Flash transfer success */}
      {flashTransfer && (
        <div style={{position:'fixed',bottom:80,right:16,zIndex:9999,padding:'12px 16px',borderRadius:10,
          background:'rgba(58,143,224,.15)',border:'1px solid rgba(58,143,224,.3)',
          fontSize:12.5,color:'var(--blue)',maxWidth:360,boxShadow:'0 8px 24px rgba(0,0,0,.2)'}}>
          <CheckCircle2 size={13} style={{verticalAlign:-2}}/> {flashTransfer}
        </div>
      )}

      {/* Tab switcher */}
      <div style={{display:'flex',gap:4,marginBottom:20}}>
        {tabs.map(t => (
          <div key={t.key} onClick={()=>setActiveTab(t.key)}
            style={{padding:'9px 20px',borderRadius:9,cursor:'pointer',fontSize:13,fontWeight:600,transition:'all .15s',
              background:activeTab===t.key?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)',
              color:activeTab===t.key?'#0C0F14':'var(--muted)',
              border:`1px solid ${activeTab===t.key?'transparent':'var(--border)'}`,
              display:'flex',alignItems:'center',gap:8,position:'relative'}}>
            <t.icon size={14}/> {t.label}
            {t.count !== null && t.count !== undefined && t.count > 0 && (
              <span style={{
                background: t.isAlert
                  ? (activeTab===t.key?'rgba(0,0,0,.2)':'#E04545')
                  : (activeTab===t.key?'rgba(0,0,0,.2)':'var(--bg3)'),
                color: t.isAlert
                  ? '#fff'
                  : (activeTab===t.key?'#0C0F14':'var(--muted2)'),
                fontSize:10.5,fontWeight:700,padding:'1px 7px',borderRadius:99,minWidth:20,textAlign:'center',
              }}>
                {t.count}
              </span>
            )}
          </div>
        ))}
      </div>

      {activeTab === 'aktif' && (
        <TabAktif
          data={data} prevUrl={prevUrl} nextUrl={nextUrl}
          links={links} curPage={curPage} lastPage={lastPage}
          total={total} jabatan_list={jabatan_list} projects={projects}
          search={''} jabatan={''} stats={stats}
          isSuperAdmin={isSuperAdmin}
          activeProjectId={usePage().props.active_project_id}
          project_info={project_info}
          onTerminate={emp => setTerminateEmp(emp)}
          onPindah={emp => (project_info?.tipe_gaji === 'ho' || emp.ho_unit)
            ? setPindahHoEmp({
                id: emp.id,
                nama_lengkap: emp.nama_lengkap,
                jabatan: emp.jabatan,
                ho_unit: emp.ho_unit,
              })
            : setPindahEmp({
                id: emp.id,
                nama_lengkap: emp.nama_lengkap,
                jabatan: emp.jabatan,
                project_id: emp.project_id,
                project_nama: emp.project_nama,
              })}
        />
      )}
      {activeTab === 'terminated' && <TabTerminated terminated={terminated} />}
      {activeTab === 'transfer'   && <TabPindahProject isSuperAdmin={isSuperAdmin} userProjectId={auth?.user?.project_id} isViewer={auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false} />}
    </AppLayout>
  );
}