// resources/js/Pages/Timesheet/DataGaji.jsx
import React, { useState, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { SUB_GROUP_OPTIONS } from './subGroupOptions';
import {
  Pencil, Search, X, Settings, TriangleAlert, Plus, Check, CheckCircle2,
  ClipboardList, Trash2, Save, Loader2, HardHat, Calendar, Receipt, Wallet,
  BookOpen, PenLine, BarChart3, Eye, Upload, Download, Stethoscope, User,
  CreditCard, XCircle, Building2, RefreshCw, Shield, Truck, Bell, Users,
  Siren, LogOut, Factory, Construction, Wrench, Cog, Calculator, Folder,
  Timer, AlarmClock, Pause, Lightbulb, Info,
} from 'lucide-react';

function rp(val) {
  if (val === null || val === undefined || val === '' || isNaN(val)) return '-';
  return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}
function rpFmt(val) {
  if (!val && val !== 0) return 'Rp -';
  return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

// ── Modal konfigurasi TTT ─────────────────────────────────────
function TttConfigModal({ items, onSave, onClose }) {
  const [draft, setDraft] = useState(items.map(c => ({ ...c })));
  const [newLabel, setNewLabel] = useState('');
  const [adding, setAdding] = useState(false);
  const [loadingId, setLoadingId] = useState(null);
  const token = document.querySelector('meta[name=csrf-token]')?.content;

  function toggleAktif(i) {
    const item = draft[i];
    setLoadingId(item.id);
    axios.put(`/ttt-config/${item.id}`, { aktif: !item.aktif }, { headers: { 'X-CSRF-TOKEN': token } })
      .then(() => {
        setDraft(prev => prev.map((c, idx) => idx === i ? { ...c, aktif: !c.aktif } : c));
      })
      .finally(() => setLoadingId(null));
  }

  function setLabel(i, val) {
    setDraft(prev => prev.map((c, idx) => idx === i ? { ...c, label: val } : c));
  }

  function saveLabel(i) {
    const item = draft[i];
    axios.put(`/ttt-config/${item.id}`, { label: item.label }, { headers: { 'X-CSRF-TOKEN': token } });
  }

  function addItem() {
    if (!newLabel.trim()) return;
    setAdding(true);
    axios.post('/ttt-config', { label: newLabel.trim() }, { headers: { 'X-CSRF-TOKEN': token } })
      .then(res => {
        setDraft(prev => [...prev, res.data.item]);
        setNewLabel('');
      })
      .finally(() => setAdding(false));
  }

  function deleteItem(i) {
    const item = draft[i];
    if (item.is_default) return;
    setLoadingId(item.id);
    axios.delete(`/ttt-config/${item.id}`, { headers: { 'X-CSRF-TOKEN': token } })
      .then(() => setDraft(prev => prev.filter((_, idx) => idx !== i)))
      .finally(() => setLoadingId(null));
  }

  const inp = {
    background: 'var(--bg3)', border: '1px solid var(--border)', color: 'var(--text)',
    borderRadius: 7, padding: '6px 10px', fontSize: 12.5,
    fontFamily: "'Outfit',sans-serif", outline: 'none', width: '100%', boxSizing: 'border-box',
  };

  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(540px,calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>

        {/* Header */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Settings size={15}/> Konfigurasi Tunjangan Tidak Tetap</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>

        <div style={{padding:'12px 20px',fontSize:11.5,color:'var(--muted)',background:'rgba(232,160,32,.06)',borderBottom:'1px solid var(--border)'}}>
          Aktifkan/nonaktifkan kolom TTT, rename label, atau tambah item baru. Perubahan langsung tersimpan ke database.
        </div>

        {/* Daftar item */}
        <div style={{padding:'14px 20px',display:'flex',flexDirection:'column',gap:8}}>
          {draft.map((c, i) => (
            <div key={c.id || c.key} style={{
              display:'flex',alignItems:'center',gap:10,padding:'10px 14px',borderRadius:9,
              background:c.aktif?'rgba(34,201,122,.06)':'var(--bg3)',
              border:`1px solid ${c.aktif?'rgba(34,201,122,.2)':'var(--border)'}`,
              opacity: loadingId === c.id ? 0.6 : 1,
              transition:'all .15s',
            }}>
              {/* Toggle */}
              <div onClick={()=>toggleAktif(i)}
                style={{width:38,height:21,borderRadius:99,flexShrink:0,cursor:'pointer',
                  background:c.aktif?'#22C97A':'var(--bg3)',border:'1px solid var(--border)',
                  position:'relative',transition:'background .2s'}}>
                <div style={{position:'absolute',top:2,left:c.aktif?18:2,width:15,height:15,
                  borderRadius:'50%',background:'#fff',boxShadow:'0 1px 3px rgba(0,0,0,.3)',transition:'left .2s'}}/>
              </div>

              {/* Key label */}
              <div style={{fontSize:10,color:'var(--muted)',fontFamily:'monospace',width:100,flexShrink:0,overflow:'hidden',textOverflow:'ellipsis'}}>
                {c.key}
              </div>

              {/* Label input */}
              <input style={{...inp,flex:1,opacity:c.aktif?1:0.5}}
                value={c.label}
                onChange={e=>setLabel(i,e.target.value)}
                onBlur={()=>saveLabel(i)}
                onKeyDown={e=>e.key==='Enter'&&saveLabel(i)}
                placeholder="Nama kolom..."
              />

              {/* Status badge */}
              <div style={{fontSize:10,fontWeight:700,padding:'2px 8px',borderRadius:99,flexShrink:0,minWidth:52,textAlign:'center',
                background:c.aktif?'rgba(34,201,122,.12)':'rgba(128,128,128,.1)',
                color:c.aktif?'#22C97A':'var(--muted)'}}>
                {c.aktif?'Aktif':'Nonaktif'}
              </div>

              {/* Hapus — hanya untuk item custom */}
              {!c.is_default && (
                <button onClick={()=>deleteItem(i)}
                  style={{padding:'3px 8px',borderRadius:6,border:'1px solid rgba(224,69,69,.2)',
                    background:'rgba(224,69,69,.08)',color:'#E04545',fontSize:11,cursor:'pointer',
                    fontFamily:"'Outfit',sans-serif",flexShrink:0,display:'flex'}}>
                  <Trash2 size={13}/>
                </button>
              )}
            </div>
          ))}

          {/* Tambah item baru */}
          <div style={{marginTop:8,padding:'12px 14px',borderRadius:9,border:'2px dashed var(--border)',background:'var(--bg3)'}}>
            <div style={{fontSize:11.5,fontWeight:600,color:'var(--accent)',marginBottom:8,display:'flex',alignItems:'center',gap:6}}><Plus size={14}/> Tambah Tunjangan Baru</div>
            <div style={{display:'flex',gap:8}}>
              <input style={{...inp,flex:1}} value={newLabel}
                onChange={e=>setNewLabel(e.target.value)}
                onKeyDown={e=>e.key==='Enter'&&addItem()}
                placeholder="Nama tunjangan, misal: Tunj. Proyek" />
              <button onClick={addItem} disabled={adding||!newLabel.trim()}
                style={{padding:'7px 16px',borderRadius:8,border:'none',
                  background:newLabel.trim()?'linear-gradient(135deg,#E8A020,#A06010)':'var(--bg3)',
                  color:newLabel.trim()?'#0C0F14':'var(--muted)',
                  fontSize:12,fontWeight:700,cursor:newLabel.trim()?'pointer':'not-allowed',
                  fontFamily:"'Outfit',sans-serif",flexShrink:0,
                  display:'flex',alignItems:'center',gap:5,
                  opacity:adding?0.7:1}}>
                {adding?<Loader2 size={13} style={{animation:'spin .8s linear infinite'}}/>:null}Tambah
              </button>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div style={{display:'flex',justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={()=>{onSave(draft);onClose();}}
            style={{padding:'9px 22px',borderRadius:8,border:'none',
              background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',
              fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
              display:'flex',alignItems:'center',gap:6}}>
            <Check size={14}/> Selesai
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Konfigurasi BPJS % & TTD — per project, berlaku per tanggal ──
function BpjsTtdConfigModal({ onClose, onSaved }) {
  const [loading, setLoading] = useState(true);
  const [bpjsHistory, setBpjsHistory] = useState([]);
  const [bpjsForm, setBpjsForm] = useState({ berlaku_mulai:'', pct_jht:2, pct_pensiun:1, pct_kes:1 });
  const [ttdForm, setTtdForm] = useState({
    list:[{label:'Disetujui Oleh,',name:'',jabatan:''},{label:'Dibayar Oleh,',name:'',jabatan:''}],
  });
  const [savingBpjs, setSavingBpjs] = useState(false);
  const [savingTtd,  setSavingTtd]  = useState(false);
  const token = document.querySelector('meta[name=csrf-token]')?.content;

  function loadAll() {
    setLoading(true);
    Promise.all([axios.get('/bpjs-config'), axios.get('/ttd-config')])
      .then(([b,t]) => {
        setBpjsHistory(b.data||[]);
        if (t.data?.ttd_list?.length) setTtdForm({ list: t.data.ttd_list });
      })
      .finally(() => setLoading(false));
  }
  useEffect(() => { loadAll(); }, []);

  function submitBpjs(e) {
    e.preventDefault();
    if (!bpjsForm.berlaku_mulai) { alert('Pilih tanggal berlaku dulu.'); return; }
    setSavingBpjs(true);
    axios.post('/bpjs-config', bpjsForm, { headers:{'X-CSRF-TOKEN':token} })
      .then(() => { loadAll(); onSaved?.(); setBpjsForm(f=>({...f, berlaku_mulai:''})); })
      .catch(err => alert(err.response?.data?.message || 'Gagal menyimpan.'))
      .finally(() => setSavingBpjs(false));
  }
  function deleteBpjs(id) {
    if (!window.confirm('Hapus versi konfigurasi BPJS ini?')) return;
    axios.delete(`/bpjs-config/${id}`, { headers:{'X-CSRF-TOKEN':token} })
      .then(() => { loadAll(); onSaved?.(); })
      .catch(err => alert(err.response?.data?.message || 'Gagal menghapus.'));
  }

  function updateTtdSlot(i, field, val) {
    setTtdForm(f => ({ ...f, list: f.list.map((s,idx)=>idx===i?{...s,[field]:val}:s) }));
  }
  function submitTtd(e) {
    e.preventDefault();
    setSavingTtd(true);
    axios.post('/ttd-config', { ttd_list: ttdForm.list }, { headers:{'X-CSRF-TOKEN':token} })
      .then(() => { loadAll(); onSaved?.(); })
      .catch(err => alert(err.response?.data?.message || 'Gagal menyimpan.'))
      .finally(() => setSavingTtd(false));
  }

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:7, padding:'6px 10px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
  };
  const th = {padding:'5px 8px',fontSize:10.5,fontWeight:700,textAlign:'left',color:'var(--muted)',borderBottom:'1px solid var(--border)'};
  const td = {padding:'5px 8px',fontSize:11.5,borderBottom:'1px solid var(--border)'};

  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(680px,calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Settings size={15}/> Konfigurasi BPJS & TTD</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>

        <div style={{padding:'12px 20px',fontSize:11.5,color:'var(--muted)',background:'rgba(232,160,32,.06)',borderBottom:'1px solid var(--border)'}}>
          BPJS: perubahan berlaku mulai bulan yang dipilih ke depan — periode gaji sebelumnya tetap pakai versi lama. Hanya versi terbaru yang bisa dihapus. TTD: cukup 1 konfigurasi aktif, langsung berubah di semua slip begitu disimpan.
        </div>

        {loading ? (
          <div style={{padding:40,textAlign:'center',color:'var(--muted)',display:'flex',alignItems:'center',justifyContent:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> Memuat...</div>
        ) : (
        <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:22}}>

          {/* ── BPJS ── */}
          <div>
            <div style={{fontSize:12,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.06em',marginBottom:8,display:'flex',alignItems:'center',gap:6}}><Wallet size={13}/> Persentase BPJS</div>
            {bpjsHistory.length > 0 && (
              <div style={{overflowX:'auto',marginBottom:10}}>
                <table style={{width:'100%',borderCollapse:'collapse'}}>
                  <thead><tr><th style={th}>Berlaku Mulai</th><th style={th}>JHT</th><th style={th}>Pensiun</th><th style={th}>Kes</th><th style={th}></th></tr></thead>
                  <tbody>
                    {bpjsHistory.map((h,i) => (
                      <tr key={h.id}>
                        <td style={td}>{h.berlaku_mulai}</td>
                        <td style={td}>{h.pct_jht}%</td>
                        <td style={td}>{h.pct_pensiun}%</td>
                        <td style={td}>{h.pct_kes}%</td>
                        <td style={{...td,textAlign:'right'}}>
                          {i===0 && (
                            <button onClick={()=>deleteBpjs(h.id)} style={{padding:'2px 8px',borderRadius:5,border:'1px solid rgba(224,69,69,.2)',background:'rgba(224,69,69,.08)',color:'#E04545',fontSize:10.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex'}}><Trash2 size={12}/></button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <form onSubmit={submitBpjs} style={{padding:'12px 14px',borderRadius:9,border:'2px dashed var(--border)',background:'var(--bg3)',display:'flex',gap:8,flexWrap:'wrap',alignItems:'flex-end'}}>
              <div style={{minWidth:130}}>
                <label style={{fontSize:10,color:'var(--muted)',marginBottom:3,display:'block'}}>Berlaku Mulai</label>
                <input type="date" style={inp} value={bpjsForm.berlaku_mulai} onChange={e=>setBpjsForm(f=>({...f,berlaku_mulai:e.target.value}))} />
              </div>
              {[{k:'pct_jht',l:'JHT %'},{k:'pct_pensiun',l:'Pensiun %'},{k:'pct_kes',l:'Kes %'}].map(f=>(
                <div key={f.k} style={{width:80}}>
                  <label style={{fontSize:10,color:'var(--muted)',marginBottom:3,display:'block'}}>{f.l}</label>
                  <input type="number" min="0" max="100" step="0.5" style={inp} value={bpjsForm[f.k]} onChange={e=>setBpjsForm(p=>({...p,[f.k]:parseFloat(e.target.value)||0}))} />
                </div>
              ))}
              <button type="submit" disabled={savingBpjs} style={{padding:'7px 16px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",opacity:savingBpjs?0.7:1,display:'flex',alignItems:'center',gap:5}}>
                {savingBpjs?<Loader2 size={13} style={{animation:'spin .8s linear infinite'}}/>:null}{savingBpjs?'...':'+ Tambah'}
              </button>
            </form>
          </div>

          {/* ── TTD ── */}
          <div>
            <div style={{fontSize:12,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.06em',marginBottom:8,display:'flex',alignItems:'center',gap:6}}><PenLine size={13}/> Tanda Tangan Slip Gaji</div>
            <form onSubmit={submitTtd} style={{padding:'12px 14px',borderRadius:9,border:'2px dashed var(--border)',background:'var(--bg3)',display:'flex',flexDirection:'column',gap:10}}>
              <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:10}}>
                {ttdForm.list.map((slot,i) => (
                  <div key={i} style={{padding:'10px 12px',borderRadius:8,background:'var(--bg2)',border:'1px solid var(--border)',display:'flex',flexDirection:'column',gap:6}}>
                    <div style={{fontSize:10.5,fontWeight:700,color:'var(--muted2)'}}>Kolom {i+1}</div>
                    <input style={inp} placeholder="Label (cth: Disetujui Oleh,)" value={slot.label} onChange={e=>updateTtdSlot(i,'label',e.target.value)} />
                    <input style={inp} placeholder="Nama" value={slot.name} onChange={e=>updateTtdSlot(i,'name',e.target.value)} />
                    <input style={inp} placeholder="Jabatan" value={slot.jabatan} onChange={e=>updateTtdSlot(i,'jabatan',e.target.value)} />
                  </div>
                ))}
              </div>
              <div style={{fontSize:10.5,color:'var(--muted)'}}>Kolom "Diterima Oleh," untuk nama karyawan otomatis ditambahkan — tidak perlu diisi di sini.</div>
              <button type="submit" disabled={savingTtd} style={{alignSelf:'flex-start',padding:'7px 16px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",opacity:savingTtd?0.7:1,display:'flex',alignItems:'center',gap:5}}>
                {savingTtd?<Loader2 size={13} style={{animation:'spin .8s linear infinite'}}/>:<Save size={13}/>} Simpan
              </button>
            </form>
          </div>

        </div>
        )}

        <div style={{display:'flex',justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={onClose}
            style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:6}}>
            <Check size={14}/> Selesai
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Tab Panduan Perhitungan ────────────────────────────────────
function TabPanduan({ projectIds=[], isSuperAdmin=false }) {
  const [section, setSection] = useState('umum');

  const S = ({ children }) => (
    <pre style={{
      fontSize:11, color:'var(--muted)', fontFamily:'monospace',
      background:'var(--bg3)', border:'1px solid var(--border)',
      borderRadius:6, padding:'8px 12px', margin:'6px 0',
      lineHeight:1.7, whiteSpace:'pre-wrap', overflowX:'auto',
    }}>
      {children}
    </pre>
  );
  const H = ({ children }) => (
    <div style={{fontFamily:'Syne,sans-serif',fontSize:13,fontWeight:700,color:'var(--accent)',margin:'18px 0 8px',borderBottom:'1px solid var(--border)',paddingBottom:4}}>
      {children}
    </div>
  );
  const H2 = ({ children }) => (
    <div style={{fontSize:12,fontWeight:700,color:'var(--text)',margin:'12px 0 4px'}}>{children}</div>
  );
  const P = ({ children }) => (
    <div style={{fontSize:12,color:'var(--muted2)',lineHeight:1.7,marginBottom:6}}>{children}</div>
  );
  const Pill = ({ children, color='var(--accent)', bg }) => (
    <span style={{display:'inline-block',background:bg||'rgba(232,160,32,.15)',color,padding:'1px 8px',borderRadius:99,fontSize:10.5,fontWeight:700,margin:'0 2px'}}>{children}</span>
  );
  const Box = ({ title, color, children }) => (
    <div style={{border:`1px solid ${color}33`,borderRadius:10,overflow:'hidden',marginBottom:14}}>
      <div style={{background:`${color}18`,padding:'8px 14px',fontWeight:700,fontSize:12,color}}>{title}</div>
      <div style={{padding:'10px 14px'}}>{children}</div>
    </div>
  );

  const showGiam     = isSuperAdmin || projectIds.includes(1) || projectIds.includes(4);
  const showMd       = isSuperAdmin || projectIds.includes(2);
  const showKhawista = isSuperAdmin || projectIds.includes(3);
  const showNk       = isSuperAdmin || projectIds.includes(5);
  const showFlat     = isSuperAdmin || projectIds.includes(1) || projectIds.includes(3) || projectIds.includes(4) || projectIds.includes(5);
  const showPurnama = isSuperAdmin || projectIds.includes(4);
  const showHo       = isSuperAdmin || projectIds.includes(6);
  const isGiamStrict = isSuperAdmin || projectIds.includes(1); // GIAM doang, di luar Purnama

  // Gabungin nama project jadi label dinamis — cuma nyebut project yang BENERAN kepunyaan
  // viewer ini (atau semua kalau admin/viewer), supaya user 1 project nggak ikut lihat nama
  // project lain di judul section yang sebetulnya nggak berlaku buat dia.
  const projLabel = (...items) => items.filter(Boolean).join(' / ');

  const tabs = [
    {key:'umum',     label:'Umum', icon:ClipboardList},
    ...(showGiam && !showPurnama ? [{key:'time7', label:'Sistem 7 Jam', icon:Timer}] : []),
    ...(showPurnama && !showGiam ? [{key:'purnama', label:'Purnama', icon:Factory}]    : []),
    ...(showGiam && showPurnama  ? [{key:'time7',   label:'Sistem 7 Jam', icon:Timer}] : []),
    ...(showMd       ? [{key:'time8',    label:'Sistem 8 Jam', icon:AlarmClock}]   : []),
    ...(showFlat     ? [{key:'flat',     label:'Sistem Flat', icon:ClipboardList}]    : []),
    ...(showKhawista ? [{key:'khawista', label:'Khawista', icon:Construction}]      : []),
    ...(showNk       ? [{key:'nk',       label:'NK', icon:Wrench}]             : []),
    ...(showHo       ? [{key:'ho',       label:'Kantor Pusat (HO)', icon:Building2}] : []),
    {key:'bpjs',     label:'BPJS & Potongan', icon:Stethoscope},
    {key:'rumus',    label:'Ringkasan Rumus', icon:Calculator},
  ];

  return (
  <div className="panduan-layout" style={{display:'flex',height:'calc(100vh - 340px)',minHeight:400}}>
    {/* Sidebar nav */}
    <div className="panduan-sidebar" style={{width:180,flexShrink:0,borderRight:'1px solid var(--border)',padding:'12px 8px',display:'flex',flexDirection:'column',gap:4,overflowY:'auto'}}>
        {tabs.map(t=>(
          <button key={t.key} onClick={()=>setSection(t.key)}
            style={{padding:'8px 12px',borderRadius:7,border:'none',textAlign:'left',cursor:'pointer',fontSize:12,fontWeight:section===t.key?700:400,
              background:section===t.key?'linear-gradient(135deg,#E8A020,#A06010)':'transparent',
              color:section===t.key?'#0C0F14':'var(--muted2)',fontFamily:"'Outfit',sans-serif",transition:'all .15s',
              display:'flex',alignItems:'center',gap:8}}>
            <t.icon size={13}/> {t.label}
          </button>
        ))}
      </div>

      {/* Content */}
      <div style={{flex:1,overflowY:'auto',padding:'16px 24px'}}>

        {/* ── UMUM ── */}
        {section==='umum' && (<div>
        <H><ClipboardList size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Konsep Dasar Penggajian AKM</H>
        <P>Panduan ini menjelaskan sistem penggajian yang berlaku untuk project Anda.</P>

        <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'12px 0'}}>
          {showGiam && (
            <Box title={<><Timer size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem 7 Jam — {projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama')} (6:1)</>} color="#22C97A">
              <P>Hari kerja <b>Senin–Sabtu</b> (6 hari kerja, 1 hari libur = Minggu).</P>
              <P>Jam reguler per hari = <b>7 jam</b>.</P>
              <P>Lembur dihitung <b>per jam</b> berdasarkan jam aktual di timesheet.</P>
            </Box>
          )}
          {showMd && (
            <Box title={<><AlarmClock size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem 8 Jam — MD (5:2)</>} color="#3A8FE0">
              <P>Hari kerja <b>Senin–Jumat</b> (5 hari kerja, 2 hari libur = Sabtu & Minggu).</P>
              <P>Jam reguler per hari = <b>8 jam</b>.</P>
              <P>Gaji dihitung berdasarkan <b>H.Basic, U.Basic, U.Kerja</b>.</P>
              <P>Sabtu bisa menjadi <b>Come Day</b> atau <b>lembur per jam</b>.</P>
            </Box>
          )}
          {showHo && (
            <Box title={<><Building2 size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Kantor Pusat (HO)</>} color="#6B7280">
              <P>Tidak memakai timesheet — <b>tidak ada lembur</b> dan <b>tidak ada potongan alpa</b>.</P>
              <P>Gaji Pokok & Tunjangan Tetap diisi manual per karyawan, bukan dari master jabatan.</P>
              <P>Gaji dihitung dari <b>Gaji Pokok + Tunjangan Tetap + Kompensasi PWT + TTT Custom</b> saja.</P>
            </Box>
          )}
        </div>

        {showFlat && (
          <Box title={<><ClipboardList size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem Flat — Kelompok Khusus</>} color="#9B59B6">
            <P>Berlaku untuk jabatan: <Pill color="#9B59B6" bg="rgba(155,89,182,.1)">SPOTTER</Pill> <Pill color="#9B59B6" bg="rgba(155,89,182,.1)">HELPER</Pill> <Pill color="#9B59B6" bg="rgba(155,89,182,.1)">FLAGMAN</Pill> <Pill color="#9B59B6" bg="rgba(155,89,182,.1)">SWAMPER</Pill> dan variannya.</P>
            <P>Lembur <b>tidak dihitung per jam</b>, melainkan menggunakan tarif flat per hari.</P>
            {showKhawista && <P>Untuk Piling Khawista: tarif per orang berbeda, diinput via tab <AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime.</P>}
          </Box>
        )}

        <H><Folder size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Komponen Gaji</H>
        <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:8,margin:'8px 0'}}>
          {[
            {label:'Gaji Pokok',      desc:'Upah dasar sesuai jabatan, dapat diedit di tabel'},
            {label:'Tunjangan Tetap', desc:'Tunjangan transport (default Rp 200.000), dapat diedit'},
            {label:'Kompensasi PWT',  desc:'Dihitung otomatis = (Gapok + Tunj) ÷ 12'},
            {label:'TTT',             desc:'8 field standar (Uang Makan, Produksi, Lapangan, Kehadiran, Pulsa, Komp. Kontrak, Insentif, Com Day) + TTT custom per-project — bisa aktif/nonaktif via tombol TTT'},
            {label:'Upah Lembur',     desc:'Dihitung otomatis dari jam timesheet atau input flat'},
            {label:'BPJS & Potongan', desc:'JHT 2%, Pensiun 1%, Kesehatan 1% dari Upah Penuh'},
            {label:'Gaji Bersih',     desc:'Gaji Kotor dikurangi semua potongan'},
            ...(showMd ? [{label:'U.Basic / U.Kerja', desc:'Komponen khusus MD — lihat tab Sistem 8 Jam'}] : []),
          ].map((c,i)=>(
            <div key={i} style={{padding:'8px 12px',borderRadius:8,background:'var(--bg3)',border:'1px solid var(--border)'}}>
              <div style={{fontSize:11.5,fontWeight:700,color:'var(--accent)',marginBottom:2}}>{c.label}</div>
              <div style={{fontSize:11,color:'var(--muted2)'}}>{c.desc}</div>
            </div>
          ))}
        </div>
      </div>)}

        {/* ── SISTEM 7 JAM ── */}
        {section==='time7' && (<div>
          <H><Timer size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem 7 Jam — GIAM (6 hari kerja : 1 hari libur)</H>

          <P>Sistem ini berlaku untuk karyawan yang bekerja 6 hari seminggu (Senin–Sabtu), libur hari Minggu. Jam standar per hari adalah <b>7 jam</b>.</P>

          <H2>Nilai Lembur Per Jam</H2>
          <P>Dasar Upah Lembur (DUL) = Gaji Pokok + Tunjangan Tetap (Upah Penuh).</P>
          <S>Nilai OT/jam = Upah Penuh ÷ 173{'\n\n'}Contoh: Rp 4.025.000 ÷ 173 = Rp 23.266/jam</S>
          <P style={{fontSize:11,color:'var(--muted)'}}>Angka 173 adalah standar Kemnaker RI untuk konversi upah bulanan ke per jam (rata-rata jam kerja sebulan).</P>

          <H2>Penghitungan Lembur — Hari Biasa (Senin–Jumat)</H2>
          <S>Jam reguler = 7 jam (tidak dihitung lembur){'\n'}Jam ke-8       → OT 1,5× (1 jam pertama){'\n'}Jam ke-9, 10+  → OT 2×{'\n\n'}Contoh: masuk jam 07:00, pulang 17:00 = 10 jam kerja{'\n'}  Reguler  = 7 jam (tidak bayar extra){'\n'}  OT 1,5×  = 1 jam  → 1 × 1,5 × Rp23.266 = Rp 34.899{'\n'}  OT 2×    = 2 jam  → 2 × 2,0 × Rp23.266 = Rp 93.064{'\n'}  Total OT hari itu = Rp 127.963</S>

          <H2>Penghitungan Lembur — Hari Sabtu</H2>
          <S>Sabtu dianggap hari pendek (5 jam reguler){'\n'}Jam ke-6       → OT 1,5× (1 jam pertama){'\n'}Jam ke-7, 8+   → OT 2×{'\n\n'}Contoh: Sabtu 8 jam kerja{'\n'}  Reguler  = 5 jam{'\n'}  OT 1,5×  = 1 jam  → 1 × 1,5 × Rp23.266 = Rp 34.899{'\n'}  OT 2×    = 2 jam  → 2 × 2   × Rp23.266 = Rp 93.064</S>

          <H2>Penghitungan Lembur — Hari Libur / Minggu</H2>
          <S>Semua jam dihitung lembur (tidak ada jam reguler){'\n'}Jam 1–7   → OT 2×{'\n'}Jam ke-8  → OT 3×{'\n'}Jam ke-9+ → OT 4×{'\n\n'}Contoh: Minggu 9 jam kerja{'\n'}  OT 2×  = 7 jam  → 7 × 2 × Rp23.266 = Rp 325.724{'\n'}  OT 3×  = 1 jam  → 1 × 3 × Rp23.266 = Rp  69.798{'\n'}  OT 4×  = 1 jam  → 1 × 4 × Rp23.266 = Rp  93.064{'\n'}  Total  = Rp 488.586</S>

          <H2>Rumus Upah Lembur Total</H2>
          <S>Upah Lembur = Nilai OT/jam × Total Jam Lembur Terbobot{'\n\n'}Total Jam Terbobot = (jam1.5× × 1.5) + (jam2× × 2) + (jam3× × 3) + (jam4× × 4)</S>

          <H2>Contoh Slip Gaji — Sistem 7 Jam</H2>
          <S>Gaji Pokok              = Rp  3.825.000{'\n'}Tunjangan Tetap       = Rp    200.000{'\n'}Upah Penuh            = Rp  4.025.000{'\n'}Kompensasi PWT        = Rp    335.417  (÷ 12){'\n'}Tunj. Produksi        = Rp    400.000{'\n'}Tunj. Lapangan        = Rp    300.000{'\n'}Upah Lembur           = Rp  2.919.870  (125,5 jam terbobot){'\n'}─────────────────────────────────────{'\n'}GAJI KOTOR            = Rp  8.180.287{'\n\n'}BPJS JHT  2%          = Rp     80.500  (2% × 4.025.000){'\n'}BPJS Pensiun 1%       = Rp     40.250{'\n'}BPJS Kes  1%          = Rp     40.250{'\n'}─────────────────────────────────────{'\n'}GAJI BERSIH           = Rp  8.019.287</S>
        </div>)}

        {section==='purnama' && (<div>
          <H><Factory size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Purnama — Sistem 7 Jam</H>
          <P>Berlaku untuk project <b>Purnama</b>. Hari kerja Senin–Sabtu, libur Minggu. Jam reguler = <b>7 jam/hari</b>.</P>

          <H2>Lembur Per Jam</H2>
          <S>{`Nilai OT/jam = Upah Penuh / 173

        Hari Biasa : Jam>7  → OT 1.5x, Jam>8  → OT 2x
        Hari Sabtu : Jam>5  → OT 1.5x, Jam>6  → OT 2x
        Hari Libur : Jam 1–7 = OT 2x, Jam ke-8 = OT 3x, Jam ke-9+ = OT 4x`}</S>

          <H2>Gaji Kotor — Purnama</H2>
          <S>{`Gaji Kotor = Gapok + Tunj + KompPWT + TTT + Upah Lembur`}</S>

          <H2>Potongan Alpa — Purnama</H2>
          <S>{`Potongan Alpa = Upah Penuh / 25 × Alpa
        (Izin TIDAK dipotong untuk Purnama — sama seperti Khawista)`}</S>

          <H2>Gaji Bersih</H2>
          <S>{`Gaji Bersih = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes - Pot.Alpa + Kekurangan Bln Lalu`}</S>
        </div>)}

        {/* ── SISTEM 8 JAM ── */}
        {section==='time8' && (<div>
        <H><AlarmClock size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem 8 Jam — MD / Multi Disiplin (5 hari kerja : 2 hari libur)</H>
        <P>Berlaku untuk project <b>MD</b>. Hari kerja Senin–Jumat, libur Sabtu & Minggu.</P>

        <H2>Komponen Gaji Kotor MD</H2>
        <S>{`Gaji Kotor = U.Basic + Komp.PWT + U.Kerja + Com Day + Upah Lembur + Tunj.Pulsa + Kekurangan Bln Lalu

      U.Basic  = (Gaji Pokok + Tunj.Transport) / 17 × min(H.Basic, 17)
      U.Kerja  = (Tunj.Makan + Tunj.Kehadiran) × H.Kerja Aktif
      Com Day  = Com Day/hari × H.Sabtu (hanya berlaku untuk Helper — lihat bagian khusus di bawah)
      Komp.PWT = (Gaji Pokok + Tunj.Transport) / 12`}</S>

        <H2>Definisi Hari</H2>
        <S>{`H.Basic      = H.Hadir + Sakit + Cuti  (senin-jumat, maks 17)
      H.Hadir     = H.Basic - Sakit - Cuti
      H.Kerja Aktif = H.Hadir + H.Sabtu
      H.Sabtu     = jumlah hari sabtu masuk`}</S>

        <H2>Lembur MD — Karyawan Non-Helper</H2>
        <S>{`Lembur Hari Biasa (Senin–Jumat):
        Jam reguler = 8 jam
        Jam ke-9    → OT 1,5×
        Jam ke-10+  → OT 2×

      Lembur Sabtu / Minggu / Libur Nasional:
        Jam 1–8   → OT 2×
        Jam ke-9  → OT 3×
        Jam ke-10+ → OT 4×

      Nilai OT/jam = Upah Penuh / 173`}</S>

        <Box title={<><HardHat size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Lembur MD — Khusus Helper (semua jenis Helper)</>} color="#9B59B6">
          <P>Jabatan apapun yang mengandung kata <b>"Helper"</b> (Helper Piping, Helper Lokal, Helper Welder, Helper Coating, Helper Electric, Helper Civil, Helper Piling, dll) memiliki aturan lembur sendiri.</P>
          <H2 style={{marginTop:8}}>Hari Reguler (Senin–Jumat)</H2>
          <S>{`Sama persis dengan karyawan non-Helper:
        Jam 1-8   = reguler (Basic)
        Jam ke-9  → OT 1,5×
        Jam ke-10+ → OT 2×`}</S>
          <H2 style={{marginTop:8}}>Sabtu & Minggu / Libur Nasional</H2>
          <S>{`Tergantung apakah Come Day diisi untuk karyawan tersebut di bulan ini:

      JIKA Com Day diisi (> 0):
        → OT MATI total, tidak dihitung lembur sama sekali
        → Hanya mendapat Com Day flat (default Rp 200.000/hari masuk)
        → Com Day Total = Com Day/hari × H.Sabtu

      JIKA Com Day = 0 (tidak diisi):
        → OT HIDUP normal, dihitung sama seperti non-Helper:
          Jam 1–8   → OT 2×
          Jam ke-9  → OT 3×
          Jam ke-10+ → OT 4×`}</S>
          <P style={{fontSize:11,color:'var(--muted)',marginTop:6}}>Kolom Com Day per karyawan diinput manual di tabel Data Gaji — HR yang menentukan apakah seorang Helper memakai sistem Come Day atau OT normal di bulan tertentu.</P>
        </Box>

        <H2>Rumus Upah Lembur Total</H2>
        <S>{`Upah Lembur = Nilai OT/jam × Total Jam Lembur Terbobot

      Total Jam Terbobot = (jam1.5× × 1.5) + (jam2× × 2) + (jam3× × 3) + (jam4× × 4)`}</S>

        <H2>Gaji Bersih MD</H2>
        <S>{`Gaji Bersih = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes - Pot.Alpa
      (Kekurangan Bulan Lalu sudah masuk ke Gaji Kotor, tidak ditambah lagi di Gaji Bersih)`}</S>

        <H2>Contoh — Riky Ferdinan (Non-Helper, Maret 2026)</H2>
        <S>{`Gaji Pokok       = Rp  3.960.000
      Tunj. Transport  = Rp    200.000
      Tunj. Pulsa      = Rp    100.000
      Tunj. Makan      = Rp     15.000/hari
      Tunj. Kehadiran  = Rp     10.000/hari
      H.Basic = 17, H.Sabtu = 0, Sakit = 0, Cuti = 0
      H.Hadir = 17, H.Kerja Aktif = 17

      U.Basic  = (3.960.000 + 200.000) / 17 × 17 = Rp 4.160.000
      Komp.PWT = 4.160.000 / 12               = Rp   346.667
      U.Kerja  = (15.000 + 10.000) × 17       = Rp   425.000
      Upah Lembur                              = Rp   769.480
      ──────────────────────────────────────────────────────
      GAJI KOTOR = 4.160.000 + 346.667 + 425.000 + 769.480 + 100.000
                = Rp 5.851.147

      BPJS JHT  2% = Rp  83.200
      BPJS Pensiun = Rp  41.600
      BPJS Kes  1% = Rp  41.600
      ──────────────────────────────────────────────────────
      GAJI BERSIH  = Rp 5.684.747`}</S>
      </div>)}

        {/* ── SISTEM FLAT ── */}
        {section==='flat' && (<div>
          <H><ClipboardList size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sistem Lembur Flat — Kelompok Khusus</H>
          <P>Kelompok jabatan tertentu tidak dihitung lemburnya per jam:</P>
          <div style={{display:'flex',flexWrap:'wrap',gap:6,margin:'8px 0 16px'}}>
            {['SPOTTER','HELPER','HELPER SURVEY','FLAGMAN','SWAMPER','SWAMPER FUEL TANK','SWAMPER LOW BOY','SWAMPER WATER TRUCK','SWAMPER FT'].map(j=>(
              <span key={j} style={{background:'rgba(155,89,182,.1)',color:'#9B59B6',padding:'3px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{j}</span>
            ))}
          </div>

          {/* GIAM / Purnama / NK — tarif standar */}
          {(showGiam || showNk) && !showKhawista && (
            <Box title={<><Wallet size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Tarif Lembur Flat — {projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', showNk&&'NK')}</>} color="#22C97A">
              <S>Lembur Sabtu      = Rp  75.000 / hari{'\n'}Lembur Libur      = Rp 200.000 / hari{'\n'}Lembur Biasa      = Rp  20.000 / hari</S>
              <P>Input jumlah hari di tab <b><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime</b>. Nilai total dihitung otomatis.</P>
            </Box>
          )}

          {/* Khawista — ada 2 sub-group */}
          {showKhawista && (
            <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'8px 0'}}>
              <Box title={<><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Construction — Flat Standar</>} color="#E8A020">
                <S>Lembur Sabtu  = Rp  75.000 / hari{'\n'}Lembur Libur  = Rp 200.000 / hari{'\n'}Lembur Biasa  = Rp  20.000 / hari</S>
                <P>Input di tab <b><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime</b> sama seperti GIAM.</P>
              </Box>
              <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Piling — Flat Custom per Orang</>} color="#3A8FE0">
                <S>Upah Lembur = (Tarif Sabtu × H.Sabtu){'\n'}             + (Tarif Minggu × H.Minggu)</S>
                <P>Tarif <b>berbeda per karyawan</b>. Input via tab <b><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime</b> — kolom Tarif/Hari bisa diisi manual.</P>
              </Box>
            </div>
          )}

          {/* Jika ada keduanya (super admin) */}
          {showGiam && showKhawista && (
            <Box title={<><Wallet size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Tarif Flat Standar ({projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', showNk&&'NK Construction')})</>} color="#22C97A">
              <S>Lembur Sabtu = Rp  75.000 / hari{'\n'}Lembur Libur = Rp 200.000 / hari{'\n'}Lembur Biasa = Rp  20.000 / hari</S>
            </Box>
          )}

          <H2>Rumus Total Lembur Flat (Standar)</H2>
          <S>Total Lembur = (L Sabtu × 75.000) + (L Libur × 200.000) + (L Biasa × 20.000)</S>

          <H2>Komponen Gaji Kotor — Sistem Flat</H2>
          <S>Gaji Kotor = Gapok + Tunj Tetap + Komp PWT + TTT + Total Lembur Flat + Uang Hadir</S>

          <H><AlarmClock size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Alur Tab Overtime per Project</H>
          <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'8px 0'}}>
            {(showGiam || showNk) && !showKhawista && (
              <Box title={<><ClipboardList size={13} style={{verticalAlign:'-2px',marginRight:4}}/>{projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', showNk&&'NK')} — Overtime Standar</>} color="#22C97A">
                <P>Input: jumlah hari <b>L Sabtu</b>, <b>L Libur</b>, <b>Lembur Biasa</b>.</P>
                <S>{`Total = (L.Sabtu × 75.000)
              + (L.Libur × 200.000)
              + (L.Biasa × 20.000)

        Disimpan ke → total_lembur_flat
        Masuk ke Gaji Kotor sebagai Total Lembur Flat`}</S>
              </Box>
            )}
            {showKhawista && (
              <Box title={<><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Khawista Construction — Overtime Standar</>} color="#E8A020">
                <P>Input: jumlah hari <b>L Sabtu</b>, <b>L Libur</b>, <b>Lembur Biasa</b>.</P>
                <S>{`Total = (L.Sabtu × 75.000)
              + (L.Libur × 200.000)
              + (L.Biasa × 20.000)

        Disimpan ke → total_lembur_flat
        Masuk ke Gaji Kotor sebagai Total Lembur Flat`}</S>
              </Box>
            )}
            {showKhawista && (
              <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Khawista Piling — Overtime Custom per Orang</>} color="#3A8FE0">
                <P>Input: <b>Tarif/Hari Sabtu</b> + <b>Jml Hari Sabtu</b>, <b>Tarif/Hari Minggu</b> + <b>Jml Hari Minggu</b>.</P>
                <P>Tarif <b>berbeda tiap karyawan</b> — tidak ada tarif standar.</P>
                <S>{`Subtotal Sabtu  = Tarif Sabtu  × H.Sabtu
        Subtotal Minggu = Tarif Minggu × H.Minggu
        Total Lembur    = Subtotal Sabtu + Subtotal Minggu

        Disimpan ke → upah_lembur + total_lembur_flat
                    (via overtime_custom.lump_sum)
        Masuk ke Gaji Kotor sebagai Upah Lembur`}</S>
                <P>Jumlah hari otomatis dari timesheet, bisa dioverride manual. Klik <b>↩</b> untuk reset ke otomatis.</P>
              </Box>
            )}
            {showNk && showKhawista && (
              <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>NK — Overtime Custom (sama seperti Khawista)</>} color="#9B59B6">
                <P>NK menggunakan tampilan overtime custom yang sama dengan Khawista.</P>
                <S>{`Total = (Tarif Sabtu × H.Sabtu)
              + (Tarif Minggu × H.Minggu)

        Disimpan ke → lump_sum → upah_lembur
        Masuk ke Gaji Kotor sebagai Upah Lembur`}</S>
                <P>Bedanya: NK tidak punya sub-group Construction/Piling.</P>
              </Box>
            )}
          </div>

          {showKhawista && (
          <div style={{marginTop:12,padding:'10px 14px',borderRadius:9,background:'rgba(232,160,32,.06)',border:'1px solid rgba(232,160,32,.2)',fontSize:11.5,color:'var(--muted2)'}}>
            <Lightbulb size={12} style={{verticalAlign:'-2px'}}/> <b>Ringkasan perbedaan:</b> {projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', 'Construction')} input <i>jumlah hari</i> dengan tarif tetap. {projLabel('Piling', showNk&&'NK')} input <i>tarif per orang</i> yang bisa berbeda-beda.
          </div>
          )}
        </div>)}

        {/* ── BPJS & POTONGAN ── */}
        {section==='bpjs' && (<div>
          <H><Stethoscope size={13} style={{verticalAlign:'-2px',marginRight:4}}/>BPJS & Potongan Wajib</H>

          <P>Semua potongan dihitung dari <b>Upah Penuh</b> (Gaji Pokok + Tunjangan Tetap), bukan dari Gaji Kotor.</P>

          <Box title={<><BarChart3 size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Tabel Potongan BPJS</>} color="#E04545">
            <table style={{width:'100%',borderCollapse:'collapse',fontSize:12}}>
              <thead>
                <tr style={{background:'rgba(224,69,69,.1)'}}>
                  {['Jenis','Persentase','Dasar Hitung','Keterangan'].map(h=>(
                    <th key={h} style={{padding:'6px 10px',textAlign:'left',fontWeight:700,fontSize:11,borderBottom:'1px solid var(--border)'}}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {[
                  ['BPJS TK – JHT',    '2%',   'Upah Penuh','Jaminan Hari Tua — ditanggung karyawan'],
                  ['BPJS TK – Pensiun','1%',   'Upah Penuh','Jaminan Pensiun — ditanggung karyawan'],
                  ['BPJS Kesehatan',   '1%',   'Upah Penuh','Jaminan Kesehatan — potongan karyawan'],
                ].map(([j,p,d,k],i)=>(
                  <tr key={i} style={{borderBottom:'1px solid var(--border)'}}>
                    <td style={{padding:'7px 10px',fontWeight:600}}>{j}</td>
                    <td style={{padding:'7px 10px',color:'#E04545',fontWeight:700}}>{p}</td>
                    <td style={{padding:'7px 10px',color:'var(--accent)'}}>{d}</td>
                    <td style={{padding:'7px 10px',color:'var(--muted2)',fontSize:11}}>{k}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Box>

          <S>Contoh (Upah Penuh = Rp 4.025.000):{'\n'}  BPJS JHT     = 2% × 4.025.000 = Rp  80.500{'\n'}  BPJS Pensiun = 1% × 4.025.000 = Rp  40.250{'\n'}  BPJS Kes     = 1% × 4.025.000 = Rp  40.250{'\n'}  Total BPJS   =                   Rp 161.000</S>

          <H2>Persentase Bisa Diubah</H2>
          <P>Di halaman Slip Gaji, klik tombol <b><Settings size={12} style={{verticalAlign:'-2px'}}/> BPJS</b> untuk mengubah persentase JHT, Pensiun, dan Kesehatan sesuai kebutuhan. Perubahan tersimpan di browser dan langsung mempengaruhi perhitungan slip.</P>

          <H2>Potongan Alpa / Prorata</H2>
          <P>Karyawan yang tidak masuk tanpa keterangan (Alpa) atau izin tidak dibayar dikenakan potongan prorata. Rumusnya beda per tipe project:</P>
          {(isGiamStrict || showNk) && (
            <P style={{fontSize:11,color:'var(--muted)'}}>Untuk project <b>{projLabel(isGiamStrict&&'GIAM', showNk&&'NK')}</b>: Potongan Alpa = Upah Penuh / 25 × (Izin + Alpa). Sakit dan Cuti tidak dipotong.</P>
          )}
          {(showKhawista || showPurnama) && (
            <P style={{fontSize:11,color:'var(--muted)'}}>Untuk project <b>{projLabel(showKhawista&&'Khawista', showPurnama&&'Purnama')}</b>: Potongan Alpa = Upah Penuh / 25 × Alpa saja — Izin tidak dipotong.</P>
          )}
          {showMd && (
            <P style={{fontSize:11,color:'var(--muted)'}}>Untuk project <b>MD</b>: Potongan Alpa = Upah Penuh / 25 × Alpa saja — Izin tidak dipotong.</P>
          )}
          {showHo && (
            <P style={{fontSize:11,color:'var(--muted)'}}>Untuk <b>Kantor Pusat (HO)</b>: tidak ada potongan alpa — tidak memakai timesheet, jadi tidak ada konsep hari alpa.</P>
          )}
          {(isGiamStrict || showNk) && (
            <S>Contoh ({projLabel(isGiamStrict&&'GIAM', showNk&&'NK')}): Alpa 2 hari, Upah Penuh Rp 4.025.000{'\n'}  = 4.025.000 ÷ 25 × 2{'\n'}  = Rp 161.000 × 2{'\n'}  = Rp 322.000</S>
          )}
          {(showKhawista || showPurnama || showMd) && (
            <S>Contoh: Alpa 2 hari, Upah Penuh Rp 4.025.000{'\n'}  = 4.025.000 ÷ 25 × 2{'\n'}  = Rp 322.000{'\n'}  (Izin tidak ikut dipotong)</S>
          )}

          <H2>Menonaktifkan Potongan Per Karyawan</H2>
          <P>Di tabel Data Gaji, setiap kolom BPJS memiliki tombol <b><X size={12} style={{verticalAlign:'-2px'}}/></b> untuk menonaktifkan potongan tersebut khusus untuk karyawan bersangkutan. Klik <b>↩</b> untuk mengaktifkan kembali.</P>
        </div>)}

        {/* ── RINGKASAN RUMUS ── */}
        {section==='rumus' && (()=>{
          const showPerJam7 = showGiam || showKhawista || showNk;
          return (<div>
          <H><Calculator size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Ringkasan Rumus — {isSuperAdmin ? 'Semua Project' : 'Project Kamu'}</H>

          <H2>1. Komponen Dasar</H2>
          <S>{`Upah Penuh     = Gaji Pokok + Tunjangan Tetap
        Kompensasi PWT = Upah Penuh / 12${showPerJam7 || showMd ? '\n        Nilai OT/jam   = Upah Penuh / 173' : ''}`}</S>
          {(showPerJam7 || showMd) && <P style={{fontSize:11,color:'var(--muted)'}}>Nilai OT/jam cuma dipakai untuk kelompok <b>Per Jam</b>. Kelompok <b>Flat</b> pakai tarif harian tetap (lihat tab Sistem Flat), jadi nilai ini tidak relevan untuk mereka.</P>}

          {showPerJam7 && (<>
            <H2>2. Lembur 7 Jam ({projLabel(isGiamStrict&&'GIAM', showKhawista&&'Khawista', showPurnama&&'Purnama', showNk&&'NK')})</H2>
            <S>{`Hari Biasa : Jam > 7  → OT 1.5×, Jam > 8  → OT 2×
        Hari Sabtu : Jam > 5  → OT 1.5×, Jam > 6  → OT 2×
        Hari Libur : Jam 1–7  = OT 2×,   Jam ke-8  = OT 3×,  Jam ke-9+ = OT 4×`}</S>
          </>)}

          {showMd && (<>
            <H2>3. Lembur 8 Jam (MD)</H2>
            <S>{`Non-Helper:
          Hari Biasa : Jam > 8  → OT 1.5×, Jam > 9 → OT 2×
          Sabtu/Minggu/Libur (sama perlakuan) : Jam 1–8 → OT 2×, Jam ke-9 → OT 3×, Jam ke-10+ → OT 4×

Helper:
          Hari Biasa : sama seperti non-Helper (Jam>8 → OT1.5×, Jam>9 → OT2×)
          Sabtu/Minggu/Libur, JIKA Com Day diisi (>0) : OT mati, hanya dapat Com Day flat
          Sabtu/Minggu/Libur, JIKA Com Day = 0        : OT hidup, sama seperti non-Helper`}</S>
          </>)}

          {showPerJam7 && (<>
            <H2>4. Lembur Flat Standar ({projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', showKhawista&&'Khawista Construction', showNk&&'NK Construction')})</H2>
            <S>{`Total Flat = (L.Sabtu  × Rp 75.000)
                  + (L.Libur  × Rp 200.000)
                  + (L.Biasa  × Rp 20.000)

        Disimpan ke → total_lembur_flat
        Masuk Gaji Kotor sebagai Total Lembur Flat`}</S>
          </>)}

          {(showKhawista || showNk) && (<>
            <H2>4b. Lembur Flat Custom ({projLabel(showKhawista&&'Khawista Piling', showNk&&'NK Flat')})</H2>
            <S>{`Upah Lembur = (Tarif Sabtu  × H.Sabtu) + (Tarif Minggu × H.Minggu)

        Tarif berbeda per karyawan — diinput manual di tab Overtime
        Jumlah hari otomatis dari timesheet (bisa dioverride manual)

        Alur penyimpanan:
          overtime_custom (sabtu_tarif, sabtu_hari, minggu_tarif, minggu_hari)
          → lump_sum     = Subtotal Sabtu + Subtotal Minggu
          → upah_lembur  = lump_sum
          → total_lembur_flat = lump_sum
          → masuk Gaji Kotor sebagai Upah Lembur`}</S>
          </>)}

          <H2>5. Gaji Kotor</H2>
          {showPerJam7 && <S>{`Per Jam (${projLabel(isGiamStrict&&'GIAM', showKhawista&&'Khawista', showPurnama&&'Purnama', showNk&&'NK')}) :
          Gaji Kotor = Gapok + Tunj + KompPWT + TTT + Upah Lembur`}</S>}
          {showPerJam7 && <S>{`Flat Standar (${projLabel(isGiamStrict&&'GIAM', showPurnama&&'Purnama', showKhawista&&'Khawista Construction', showNk&&'NK Construction')}) :
          Gaji Kotor = Gapok + Tunj + KompPWT + TTT + Total Flat + U.Hadir`}</S>}
          {(showKhawista || showNk) && <S>{`Flat Custom (${projLabel(showKhawista&&'Khawista Piling', showNk&&'NK')}) :
          Gaji Kotor = Gapok + Tunj + KompPWT + TTT + Upah Lembur Custom`}</S>}
          {showMd && <S>{`MD (Multi Disiplin) :
          Gaji Kotor = U.Basic + KompPWT + U.Kerja + ComDay + Upah Lembur + Tunj.Pulsa + Kekurangan`}</S>}
          {showHo && <S>{`Kantor Pusat (HO) — tidak ada timesheet/lembur :
          Gaji Kotor = Gapok + Tunj + KompPWT + TTT Custom`}</S>}
          {(showPerJam7 || showMd) && <P style={{fontSize:11,color:'var(--muted)'}}>TTT = jumlah 8 field standar (Uang Makan, Produksi, Lapangan, Kehadiran, Pulsa, Komp.Kontrak, Insentif, Com Day) + TTT custom per-project.</P>}

          <H2>6. Potongan</H2>
          <S>{`BPJS JHT     = Upah Penuh × 2%
        BPJS Pensiun = Upah Penuh × 1%
        BPJS Kes     = Upah Penuh × 1%`}</S>
          {(isGiamStrict || showNk) && <S>{`Pot. Alpa (${projLabel(isGiamStrict&&'GIAM', showNk&&'NK')}) = Upah Penuh / 25 × (Izin + Alpa)`}</S>}
          {showMd && <S>{`Pot. Alpa (MD) = Upah Penuh / 25 × Alpa   ← Izin tidak dipotong`}</S>}
          {(showKhawista || showPurnama) && <S>{`Pot. Alpa (${projLabel(showKhawista&&'Khawista', showPurnama&&'Purnama')}) = Upah Penuh / 25 × Alpa   ← Izin tidak dipotong`}</S>}
          {showHo && <S>{`Pot. Alpa (HO) = tidak ada — HO tidak pakai timesheet`}</S>}
          {showKhawista && <S>{`Pot. Insentif (Khawista Construction) = Insentif / 25 × (Izin + Sakit + Cuti)
        Pot. Insentif (Khawista Piling)       = Tunj.Lapangan / 25 × Izin`}</S>}
          {showNk && <S>{`Pot. Insentif (NK) = Insentif / 25 × (Izin + Sakit + Cuti + STB)`}</S>}
          {showPerJam7 && <P style={{fontSize:11,color:'var(--muted)'}}>Pot. Tabung Oksigen — nominal manual per karyawan, diinput langsung di tabel.</P>}

          <H2>7. Gaji Bersih</H2>
          {showPerJam7 && <S>{`Non-MD, Non-HO = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes
                       - Pot.Alpa - Pot.Insentif - Pot.Tabung Oksigen + Kekurangan Bln Lalu`}</S>}
          {showMd && <S>{`MD = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes - Pot.Alpa
        (Kekurangan sudah masuk ke Gaji Kotor MD, tidak ditambah lagi)`}</S>}
          {showHo && <S>{`HO = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes + Kekurangan Bln Lalu
        (Potongan cuti/alpa/custom HO cuma ditampilkan sebagai info,
         TIDAK ikut mengurangi Gaji Bersih)`}</S>}

          {(showPerJam7 || showMd) && (<>
            <H2>8. Upah Lembur Per Jam (Total Terbobot)</H2>
            <S>{`Total Jam Terbobot = (jam1.5× × 1.5) + (jam2× × 2) + (jam3× × 3) + (jam4× × 4)
        Upah Lembur        = (Upah Penuh / 173) × Total Jam Terbobot`}</S>
          </>)}

          {showMd && (<>
            <H2>9. Komponen Khusus MD</H2>
            <S>{`H.Basic      = H.Hadir + Sakit + Cuti  (maks 17)
        H.Hadir      = H.Basic - Sakit - Cuti
        H.Kerja Aktif = H.Hadir + H.Sabtu

        U.Basic = (Gapok + Tunj) / 17 × min(H.Basic, 17)
        U.Kerja = (Tunj.Makan + Tunj.Kehadiran) × H.Kerja Aktif
        Com Day = Com Day/hari × H.Sabtu`}</S>
          </>)}

          <div style={{marginTop:16,padding:'12px 16px',borderRadius:10,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',fontSize:11.5,color:'var(--muted2)',lineHeight:1.7}}>
            <Lightbulb size={12} style={{verticalAlign:'-2px'}}/> Semua perhitungan dilakukan otomatis dari data timesheet. Klik sel <span style={{background:'rgba(255,252,200,.6)',padding:'0 4px',borderRadius:3,border:'1px solid #E8C030',fontSize:11}}>kuning</span> di tabel untuk edit manual. Perubahan tersimpan otomatis ke database.
          </div>
        </div>);
        })()}
        
            {section==='khawista' && (<div>
              <H><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Khawista — Construction vs Piling</H>
              <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'12px 0'}}>
                <Box title={<><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sub-Group: Construction</>} color="#E8A020">
                  <P>Sistem <b>7 jam</b> standar. Lembur dihitung per jam.</P>
                  <P>Kelompok Flat: lembur menggunakan tarif standar.</P>
                  <P>Potongan insentif:</P>
                  <S>Pot. Insentif = Insentif / 25 × (Izin + Sakit + Cuti)</S>
                </Box>
                <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Sub-Group: Piling</>} color="#3A8FE0">
                  <P>Sistem <b>7 jam</b>. Lembur menggunakan <b>tarif flat custom</b> per orang.</P>
                  <P>Potongan insentif:</P>
                  <S>Pot. Insentif = Tunj. Lapangan / 25 × Izin</S>
                  <P>Lembur diinput via tab <b><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime</b> dengan tarif Sabtu dan Minggu berbeda per karyawan.</P>
                </Box>
              </div>

              <H2>Potongan Alpa — Khawista</H2>
              <S>Potongan Alpa = Upah Penuh / 25 × Alpa{'\n'}(Izin tidak dipotong untuk Khawista)</S>

              <H2>Komponen Gaji Kotor — Khawista</H2>
              <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'8px 0'}}>
                <Box title={<><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Construction — Per Jam</>} color="#E8A020">
                  <S>{`Gaji Kotor = Gapok + Tunj + KompPWT
                      + TTT + Upah Lembur`}</S>
                  <P>Upah Lembur dihitung dari jam timesheet × nilai OT/jam.</P>
                </Box>
                <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Piling — Flat Custom</>} color="#3A8FE0">
                  <S>{`Gaji Kotor = Gapok + Tunj + KompPWT
                      + TTT + Upah Lembur Custom

            Upah Lembur = (Tarif Sabtu × H.Sabtu)
                      + (Tarif Minggu × H.Minggu)`}</S>
                  <P>Tarif per orang berbeda, diinput via tab <b><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime</b>.</P>
                </Box>
              </div>

              <H><AlarmClock size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Tab Overtime — Detail per Sub-Group</H>
              <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'8px 0'}}>
                <Box title={<><Construction size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Construction — Tab Overtime Standar</>} color="#E8A020">
                  <P>Tampilan: input <b>L Sabtu</b>, <b>L Libur</b>, <b>Lembur Biasa</b> (jumlah hari).</P>
                  <S>{`Total Flat = (L.Sabtu  × 75.000)
                      + (L.Libur  × 200.000)
                      + (L.Biasa  × 20.000)

            Disimpan ke → total_lembur_flat
            Masuk Gaji Kotor sebagai Total Lembur Flat`}</S>
                </Box>
                <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Piling — Tab Overtime Custom</>} color="#3A8FE0">
                  <P>Tampilan berbeda — setiap baris punya kolom <b>Tarif/Hari</b> yang bisa berbeda per orang.</P>
                  <S>{`Kolom input:
              Tarif Sabtu/Hari (edit) | Jml Hari Sabtu (edit)
              Tarif Minggu/Hari (edit) | Jml Hari Minggu (edit)

            Subtotal Sabtu  = Tarif Sabtu  × H.Sabtu
            Subtotal Minggu = Tarif Minggu × H.Minggu
            Total           = Subtotal Sabtu + Subtotal Minggu

            Disimpan ke → upah_lembur + total_lembur_flat
                        (via overtime_custom.lump_sum)
            Masuk Gaji Kotor sebagai Upah Lembur`}</S>
                  <P>Jumlah hari otomatis dari timesheet (H.Sabtu dari timesheet). Bisa dioverride manual — klik <b>↩</b> untuk reset ke otomatis.</P>
                </Box>
              </div>

              <div style={{marginTop:12,padding:'10px 14px',borderRadius:9,background:'rgba(58,143,224,.06)',border:'1px solid rgba(58,143,224,.2)',fontSize:11.5,color:'var(--muted2)'}}>
                <TriangleAlert size={12} style={{verticalAlign:'-2px'}}/> <b>Penting:</b> Di tab Semua/Flat, kolom Lembur untuk Piling tampil <b>—</b> (tidak tampil) karena lembur disimpan ke <code>upah_lembur</code> bukan ke <code>total_lembur_flat</code> yang ditampilkan di kolom tabel. Nilai sudah masuk ke Gaji Kotor via Upah Lembur.
              </div>
            </div>)}

        {section==='nk' && (<div>
          <H><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>NK — Nindya Karya</H>
          <P>Sistem <b>7 jam</b> standar. Lembur dihitung per jam (Per Jam) atau flat custom (Flat).</P>

          <H2>Komponen Khusus NK</H2>
          <Box title={<><Pause size={13} style={{verticalAlign:'-2px',marginRight:4}}/>STB (Standby)</>} color="#9B59B6">
            <P>STB = karyawan standby, tidak hadir kerja aktif tapi tetap dihitung untuk potongan insentif.</P>
            <S>Pot. Insentif = Insentif / 25 × (Izin + Sakit + Cuti + STB)</S>
          </Box>

          <H2>Potongan Alpa — NK</H2>
          <S>Potongan Alpa = Upah Penuh / 25 × (Izin + Alpa)</S>

          <H2>Komponen Gaji Kotor — NK</H2>
          <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,margin:'8px 0'}}>
            <Box title={<><Timer size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Per Jam</>} color="#22C97A">
              <S>{`Gaji Kotor = Gapok + Tunj + KompPWT
                  + TTT + Upah Lembur`}</S>
              <P>Upah Lembur dihitung dari jam timesheet × nilai OT/jam.</P>
            </Box>
            <Box title={<><ClipboardList size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Flat (Spotter, Helper, dll)</>} color="#9B59B6">
              <S>{`Gaji Kotor = Gapok + Tunj + KompPWT
                  + TTT + Total Lembur Custom

        Total Custom = (Tarif Sabtu × H.Sabtu)
                    + (Tarif Minggu × H.Minggu)`}</S>
              <P>{showKhawista ? 'NK Flat menggunakan overtime custom — sama seperti Khawista Piling.' : 'NK Flat menggunakan overtime custom (tarif per orang, bukan tarif standar).'}</P>
            </Box>
          </div>

          <H><AlarmClock size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Tab Overtime — NK</H>
          <Box title={<><Wrench size={13} style={{verticalAlign:'-2px',marginRight:4}}/>NK Flat — Overtime Custom</>} color="#9B59B6">
            <P>NK menggunakan <b>TabelOvertimeCustom</b> untuk input tarif per karyawan.</P>
            <S>{`Kolom input per karyawan:
          Tarif Sabtu/Hari (edit) | Jml Hari Sabtu (edit)
          Tarif Minggu/Hari (edit) | Jml Hari Minggu (edit)

        Total = (Tarif Sabtu × H.Sabtu)
              + (Tarif Minggu × H.Minggu)

        Disimpan ke → overtime_custom.lump_sum
                  → upah_lembur + total_lembur_flat
        Masuk Gaji Kotor sebagai Upah Lembur`}</S>
            <P>Semua karyawan flat NK masuk satu daftar overtime yang sama (tidak ada pembagian sub-group).</P>
            <P>Jumlah hari otomatis dari timesheet. Bisa dioverride manual — klik <b>↩</b> untuk reset ke otomatis.</P>
          </Box>

          {showKhawista && (
          <div style={{marginTop:12,padding:'10px 14px',borderRadius:9,background:'rgba(155,89,182,.06)',border:'1px solid rgba(155,89,182,.2)',fontSize:11.5,color:'var(--muted2)'}}>
            <Lightbulb size={12} style={{verticalAlign:'-2px'}}/> <b>Ringkasan NK vs Khawista Piling:</b> Alur overtime dan penyimpanan sama persis. Bedanya NK tidak ada sub-group, dan potongan insentif NK memperhitungkan STB sedangkan Khawista tidak.
          </div>
          )}
        </div>)}

        {section==='ho' && (<div>
          <H><Building2 size={13} style={{verticalAlign:'-2px',marginRight:4}}/>Kantor Pusat (HO)</H>
          <P>Karyawan Kantor Pusat <b>tidak punya timesheet</b> — jadi tidak ada lembur, tidak ada jam kerja, dan tidak ada potongan alpa. Perhitungannya jauh lebih sederhana dibanding project lapangan.</P>

          <H2>Gaji Pokok & Tunjangan Tetap</H2>
          <P>Berbeda dengan project lain (yang defaultnya ambil dari master jabatan), untuk HO kedua nilai ini <b>wajib diisi manual</b> per karyawan di tabel Data Gaji — kalau belum pernah diisi, nilainya 0.</P>

          <H2>Komponen Gaji Kotor — HO</H2>
          <S>{`Gaji Kotor = Gaji Pokok + Tunjangan Tetap + Kompensasi PWT + TTT Custom

        Kompensasi PWT = (Gaji Pokok + Tunjangan Tetap) / 12
        TTT Custom     = jumlah item TTT custom yang dikonfigurasi khusus untuk project HO
                         (bukan 8 field TTT standar seperti project lapangan)`}</S>

          <H2>Gaji Bersih — HO</H2>
          <S>{`Gaji Bersih = Gaji Kotor - BPJS JHT - BPJS Pensiun - BPJS Kes + Kekurangan Bulan Lalu`}</S>

          <div style={{marginTop:12,padding:'10px 14px',borderRadius:9,background:'rgba(107,114,128,.08)',border:'1px solid rgba(107,114,128,.25)',fontSize:11.5,color:'var(--muted2)'}}>
            <TriangleAlert size={12} style={{verticalAlign:'-2px'}}/> <b>Penting:</b> Kolom Izin/Sakit/Alpa/Cuti dan Potongan Custom untuk HO tetap bisa diisi dan ditampilkan di tabel, tapi sifatnya <b>hanya informasi</b> ("potensi potongan") — nilainya <b>tidak dikurangkan</b> dari Gaji Bersih HO seperti di project lapangan.
          </div>
        </div>)}
      </div>
    </div>
  );
}

// ── Hitung ulang ──────────────────────────────────────────────
function recalc(row, activeTttKeys=[], bpjsPct={jht:2,pensiun:1,kes:1}) {
  const isFlat     = row.kelompok === 'flat';
  const isMd       = row.tipe_project === 'md';
  const isHo       = row.tipe_project === 'ho';
  const gajiPokok  = parseFloat(row.gaji_pokok)    || 0;
  const tunjTetap  = parseFloat(row.tunj_tetap)    || 0;
  // tunj_jabatan: info tambahan saja, sudah termasuk dalam tunj_tetap — jangan dijumlah lagi ke upah_penuh/gaji_kotor
  const upahPenuh  = gajiPokok + tunjTetap;
  const kompPwt = Math.round(upahPenuh / 12);
  const insentif          = parseFloat(row.insentif)           || 0;
  const tunjLap           = parseFloat(row.tunj_lapangan)      || 0;
  const tunjPulsa         = parseFloat(row.tunj_pulsa)         || 0;
  const uangHadir         = parseFloat(row.uang_hadir)         || 0;
  const lSabtu      = parseInt(row.l_sabtu)      || 0;
  const lLibur      = parseInt(row.l_libur)      || 0;
  const lemburBiasa = parseInt(row.lembur_biasa) || 0;
  const isKhawistaFlat = isFlat && ['khawista', 'nk'].includes((row.project_kode||'').toLowerCase());
  const isPiling = (row.sub_group||'').toLowerCase() === 'piling';
  const lumpSum = parseFloat(row.lump_sum) || 0;
  const savedTotalFlat = parseFloat(row.total_lembur_flat) || 0;
  const totalFlat = isFlat
    ? (isKhawistaFlat && !isPiling
        ? lumpSum
        : isKhawistaFlat && isPiling
          ? savedTotalFlat
          : (lSabtu*75000)+(lLibur*200000)+(lemburBiasa*20000))
    : savedTotalFlat;
  const upahLemburPilingFlat = isFlat && isKhawistaFlat && isPiling ? lumpSum : 0;
  const nilaiPerJam  = upahPenuh / 173;
  const jmlJamLembur = parseFloat(row.jml_jam_lembur) || 0;
  const upahLembur = isMd
    ? (parseFloat(row.upah_lembur) || 0)
    : isFlat
      ? (isKhawistaFlat && isPiling ? upahLemburPilingFlat : totalFlat)
      : Math.round(nilaiPerJam * jmlJamLembur);
  const DEFAULT_TTT_KEYS =['com_day','insentif','tunj_makan','tunj_produksi','tunj_lapangan','tunj_kehadiran','tunj_pulsa','kompensasi_kontrak'];
  const activeTttSum = DEFAULT_TTT_KEYS
    .filter(k => activeTttKeys.includes(k))
    .reduce((s, k) => s + (parseFloat(row[k]) || 0), 0);
  const DEFAULT_FIELD_KEYS = [
    'employee_id','id_badge','nama_lengkap','jabatan','kelompok','tipe_project','ptkp',
    'tahun','bulan','gaji_pokok','tunj_tetap','tunj_jabatan','kompensasi_pwt','upah_penuh',
    'ttt_perhari','com_day','insentif','tunj_makan','tunj_produksi','tunj_lapangan',
    'tunj_kehadiran','tunj_pulsa','kompensasi_kontrak','jml_jam_lembur','upah_lembur',
    'l_sabtu','l_libur','lembur_biasa','total_lembur_flat','uang_hadir','h_kerja',
    'gaji_kotor','potongan_jht','potongan_pensiun','potongan_kes','potongan_alpa',
    'kekurangan_bulan_lalu','gaji_bersih','izin','sakit','alpa','cuti',
    'sub_group','urutan','project_kode','_no_jht','_no_pensiun','_no_kes','_no_alpa',
    'dul','nilai_lembur_per_jam','tarif_sabtu','tarif_libur','tarif_biasa','lump_sum',
  ];
  const activeCustomSum = Object.entries(row)
    .filter(([k]) => !DEFAULT_FIELD_KEYS.includes(k) && activeTttKeys.includes(k))
    .reduce((s, [, v]) => s + (parseFloat(v) || 0), 0);
  let uBasicMd = parseFloat(row.u_basic) || 0;
  let uKerjaMd = parseFloat(row.u_kerja) || 0;
  let gajiKotor;
  if (isMd) {
    const hBasic      = parseInt(row.h_basic) || 0;
    const hSabtu      = parseInt(row.h_sabtu) || 0;
    const sakitMd     = parseInt(row.sakit)   || 0;
    const cutiMd      = parseInt(row.cuti)    || 0;
    const hHadir      = hBasic - sakitMd - cutiMd;
    const hKerjaAktif = hHadir + hSabtu;
    uBasicMd          = Math.round((gajiPokok + tunjTetap) / 17 * Math.min(hBasic, 17));
    const tMakan      = parseFloat(row.tunj_makan)     || 0;
    const tKehdr      = parseFloat(row.tunj_kehadiran) || 0;
    const tPulsa      = parseFloat(row.tunj_pulsa)     || 0;
    uKerjaMd          = Math.round((tMakan + tKehdr) * hKerjaAktif);
    const comDayTotal = Math.round((parseFloat(row.com_day) || 0) * hSabtu);
    const kekMd       = parseFloat(row.kekurangan_bulan_lalu) || 0;
    gajiKotor = uBasicMd + Math.round(kompPwt) + uKerjaMd + comDayTotal + Math.round(upahLembur) + tPulsa + kekMd;
  } else {
    const sumKomponen = gajiPokok + tunjTetap + Math.round(kompPwt) + activeTttSum + activeCustomSum;
    gajiKotor = isFlat
      ? Math.round(sumKomponen) + upahLembur + uangHadir
      : Math.round(sumKomponen) + upahLembur;
  }
  const jht=upahPenuh*(bpjsPct.jht/100), pensiun=upahPenuh*(bpjsPct.pensiun/100), kes=upahPenuh*(bpjsPct.kes/100);
  const izinCount=parseInt(row.izin)||0, alpaCount=parseInt(row.alpa)||0;
  const projectKode = (row.project_kode || '').toLowerCase();
  const izinDipotong = !['khawista', 'purnama'].includes(projectKode);
  const potonganAlpa = upahPenuh/25*(alpaCount+(izinDipotong?izinCount:0));
  const kekurangan   = parseFloat(row.kekurangan_bulan_lalu)||0;
  const finalJht=row._no_jht?0:Math.round(jht), finalPensiun=row._no_pensiun?0:Math.round(pensiun);
  const finalKes=row._no_kes?0:Math.round(kes);
  // HO: alpa/izin cuma disimulasikan untuk info (lihat potongan_simulasi), tidak dipotong beneran.
  const finalAlpa=isHo?0:(row._no_alpa?0:Math.round(potonganAlpa));
  const sakit = parseInt(row.sakit)||0;
  const cuti  = parseInt(row.cuti)||0;
  const subGroup = (row.sub_group||'').toLowerCase();
  let potonganInsentif = 0;
  if (!isHo && projectKode === 'khawista') {
    if (subGroup === 'construction') {
      potonganInsentif = Math.round(insentif / 25 * (izinCount + sakit + cuti));
    } else if (subGroup === 'piling') {
      potonganInsentif = Math.round(tunjLap / 25 * izinCount);
    }
  } else if (!isHo && projectKode === 'nk') {
    const stb = parseInt(row.stb) || 0;
    potonganInsentif = Math.round(insentif / 25 * (izinCount + sakit + cuti + stb));
  }
  const potTabungOksigen = isHo ? 0 : (parseFloat(row.pot_tabung_oksigen)||0);
  const potonganSimulasi = isHo ? Math.round(upahPenuh/25*(alpaCount+cuti)) : (parseFloat(row.potongan_simulasi)||0);
  const finalBersih = isMd
    ? Math.round(gajiKotor - finalJht - finalPensiun - finalKes - finalAlpa - potonganInsentif - potTabungOksigen)
    : Math.round(gajiKotor - finalJht - finalPensiun - finalKes - finalAlpa - potonganInsentif - potTabungOksigen + kekurangan);
  return { ...row, kompensasi_pwt:Math.round(kompPwt), upah_penuh:upahPenuh, u_basic:uBasicMd, u_kerja:uKerjaMd, total_lembur_flat:isFlat?Math.round(totalFlat):(parseFloat(row.total_lembur_flat)||0), upah_lembur:upahLembur, gaji_kotor:Math.round(gajiKotor), potongan_jht:finalJht, potongan_pensiun:finalPensiun, potongan_kes:finalKes, potongan_alpa:finalAlpa, potongan_insentif:potonganInsentif, pot_tabung_oksigen:potTabungOksigen, potongan_simulasi:potonganSimulasi, gaji_bersih:finalBersih };
}

function EditCell({ val, onSave, width=80 }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [editing,setEditing]=useState(false), [draft,setDraft]=useState(val||0), ref=useRef(null);
  useEffect(()=>{ if(!editing) setDraft(val||0); },[val,editing]);
  function start(){
    if (isViewer) return;
    setDraft(val||0); setEditing(true); setTimeout(()=>ref.current?.select(),20);
  }
  function save(){ setEditing(false); onSave(parseFloat(draft)||0); }
  if(editing) return <input ref={ref} type="number" value={draft} onChange={e=>setDraft(e.target.value)} onBlur={save} onKeyDown={e=>{if(e.key==='Enter'||e.key==='Tab'){e.preventDefault();save();}if(e.key==='Escape')setEditing(false);}} style={{width,border:'2px solid var(--accent)',background:'var(--bg2)',color:'var(--text)',textAlign:'right',fontSize:10.5,outline:'none',padding:'1px 3px',fontFamily:"'Outfit',sans-serif"}}/>;
  return <div onClick={start} title={isViewer ? '' : 'Klik untuk edit'} style={{cursor:isViewer?'default':'pointer',padding:'1px 3px',borderRadius:3,textAlign:'right',minWidth:width,color:val>0?'var(--text)':'var(--muted)'}}>{val>0?rp(val):<span style={{fontSize:8.5,opacity:.4}}>edit</span>}</div>;
}

function BpjsCell({ val, overridden, onToggle }) {
  if(overridden) return <div style={{display:'flex',alignItems:'center',gap:3,justifyContent:'flex-end'}}><span style={{fontSize:9,color:'var(--muted)',textDecoration:'line-through'}}>{rp(val)}</span><button onClick={onToggle} style={{fontSize:9,padding:'1px 5px',borderRadius:4,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.1)',color:'#22C97A',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>↩</button></div>;
  return <div style={{display:'flex',alignItems:'center',gap:3,justifyContent:'flex-end'}}><span style={{color:'#E04545',fontSize:10}}>{rp(val)}</span><button onClick={onToggle} style={{fontSize:8,padding:'1px 4px',borderRadius:4,border:'1px solid rgba(224,69,69,.2)',background:'rgba(224,69,69,.06)',color:'#E04545',cursor:'pointer',opacity:.7,fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center'}}><X size={9}/></button></div>;
}

function TabelPerJam({ data, activeTTT, onEdit, onScheduleSave, isDark, projectKode='', isMd=false, bpjsPct={jht:2,pensiun:1,kes:1} }) {
  const solidBg0=isDark?'#121620':'#FFFFFF', solidBg1=isDark?'#181E2A':'#F8F9FC';
  const thB={padding:'5px 5px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1={...thB,background:'#F4A010',color:'#1A0A00'}, th2={...thB,background:'#FFF2CC',color:'#7B5E00'};
  const th3={...thB,background:'#E2EFDA',color:'#276221'}, th4={...thB,background:'#FCE4D6',color:'#9C0006'};
  const th5={...thB,background:'#DAEEF3',color:'#0C4B6E'}, thE={...thB,background:'#FFFACD',color:'#7B5E00'};
  function td(c,b,bg){return{padding:'3px 5px',fontSize:10,textAlign:'right',border:'1px solid var(--border)',color:c||'var(--text)',fontWeight:b?700:400,background:bg||'transparent',whiteSpace:'nowrap'};}
  function handleEdit(id,f,v){onEdit(id,f,v);onScheduleSave(id);}
  const isKhawistaPiling = projectKode === 'khawista';
  const [selectedRow,setSelectedRow] = useState(null);
  const containerRef = useRef(null);
  useEffect(()=>{
    function handleClickOutside(e){ if(containerRef.current && !containerRef.current.contains(e.target)) setSelectedRow(null); }
    document.addEventListener('mousedown', handleClickOutside);
    return ()=>document.removeEventListener('mousedown', handleClickOutside);
  },[]);
  return (
    <div ref={containerRef} style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 210px)'}}>
      <table style={{borderCollapse:'collapse',fontSize:10.5,whiteSpace:'nowrap',width:'100%'}}>
        <thead style={{position:'sticky',top:0,zIndex:20}}>
          <tr>
            <th style={{...th1,width:30,minWidth: 30, position:'sticky',left:0,zIndex:30,background:'#F4A010'}} rowSpan={2}>#</th>
            <th style={{...th1,minWidth:160,textAlign:'left',position:'sticky',left:25,zIndex:30,background:'#F4A010'}} rowSpan={2}>NAMA KARYAWAN</th>
            <th style={{...th1,minWidth:90,textAlign:'left'}} rowSpan={2}>JABATAN</th>
            <th style={th1} rowSpan={2}>PTKP</th>
            <th style={thE} rowSpan={2}>GAJI POKOK <Pencil size={9} style={{verticalAlign:2}}/></th>
            <th style={th2} colSpan={isMd ? 2 : 3}>TUNJANGAN TETAP</th>
            {activeTTT.length>0&&<th style={th2} colSpan={activeTTT.length}>TUNJANGAN TIDAK TETAP <Pencil size={9} style={{verticalAlign:2}}/></th>}
            {isMd && <th style={th5} colSpan={4}>KOMPONEN MD</th>}
            <th style={th3} colSpan={2}>LEMBUR (Per Jam)</th>
            <th style={th1} rowSpan={2}>H.KERJA</th>
            {isMd && <th style={thE} rowSpan={2}>Kekurangan Bln Lalu <Pencil size={9} style={{verticalAlign:2}}/></th>}
            <th style={th1} rowSpan={2}>GAJI KOTOR</th>
            <th style={th4} colSpan={isKhawistaPiling?6:5}>POTONGAN</th>
            <th style={th1} rowSpan={2}>GAJI BERSIH</th>
            <th style={th5} colSpan={4}>ABSENSI</th>
          </tr>
          <tr>
            <th style={thE}>Transport <Pencil size={9} style={{verticalAlign:2}}/></th><th style={thE}>Tunj. Jabatan <Pencil size={9} style={{verticalAlign:2}}/></th>{!isMd && <th style={th2}>Upah Penuh</th>}
            {activeTTT.map(f=><th key={f.key} style={thE}>{f.label}{f.key !== 'kompensasi_pwt' ? <Pencil size={9} style={{verticalAlign:2,marginLeft:2}}/> : null}</th>)}
            {isMd && <><th style={th5}>H.Basic</th><th style={th5}>U.Basic</th><th style={th5}>H.Kerja</th><th style={th5}>U.Kerja</th></>}
            <th style={th3}>Jml Jam Lembur</th><th style={th3}>Upah Lembur</th>
            <th style={th4}>BPJS JHT {bpjsPct.jht}%</th><th style={th4}>BPJS Pensiun {bpjsPct.pensiun}%</th><th style={th4}>BPJS Kes {bpjsPct.kes}%</th><th style={{...th4,background:'#FFCDD2'}}>Alpa/Prorata</th><th style={{...th4,background:'#FFCDD2'}}>Pot. Insentif</th>{isKhawistaPiling&&<th style={{...th4,background:'#FFCDD2'}}>Pot. Tabung O₂ <Pencil size={9} style={{verticalAlign:2}}/></th>}
            <th style={th5}>Izin</th><th style={th5}>Sakit</th><th style={th5}>Alpa</th><th style={th5}>Cuti</th>
          </tr>
        </thead>
        <tbody>
          {data.map((r,idx)=>{
            const isSel=selectedRow===r.employee_id;
            const bg=isSel?'rgba(58,143,224,.16)':(idx%2===0?'':'rgba(0,0,0,.025)');
            const stickyBg=isSel?(isDark?'#1B3A5C':'#DCEBFB'):(idx%2===0?solidBg0:solidBg1);
            return (<tr key={r.employee_id} onClick={()=>setSelectedRow(r.employee_id)} style={{cursor:'pointer',boxShadow:isSel?'inset 0 0 0 1.5px var(--blue)':'none'}}>
              <td style={{...td(),textAlign:'center',color:'var(--muted)',background:stickyBg,position:'sticky',left:0,zIndex:5}}>{idx+1}</td>
              <td style={{...td(),textAlign:'left',background:stickyBg,fontWeight:600,paddingLeft:8,position:'sticky',left:28,zIndex:5}}>{r.nama_lengkap}</td>
              <td style={{...td(),textAlign:'left',color:'var(--muted2)',fontSize:9,background:bg}}>{r.jabatan}</td>
              <td style={{...td(),textAlign:'center',fontSize:9,background:bg}}>{r.ptkp||'—'}</td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.gaji_pokok||0} onSave={v=>handleEdit(r.employee_id,'gaji_pokok',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_tetap||0} onSave={v=>handleEdit(r.employee_id,'tunj_tetap',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_jabatan||0} onSave={v=>handleEdit(r.employee_id,'tunj_jabatan',v)}/></td>
              {!isMd && <td style={td('var(--accent)',true,bg)}>{rp(r.upah_penuh)}</td>}
              {activeTTT.map(f=>(
                <td key={f.key} style={{
                  ...td(),
                  background: f.key==='kompensasi_pwt' ? 'transparent' : 'rgba(255,252,180,.25)',
                  border: f.key==='kompensasi_pwt' ? '1px solid var(--border)' : '1px solid rgba(200,160,0,.2)'
                }}>
                  {f.key==='kompensasi_pwt' || f.key==='lump_sum'
                    ? <span style={{padding:'1px 3px',fontSize:10,color:'var(--muted2)'}}>{rp(r[f.key]||0)}</span>
                    : <EditCell val={r[f.key]||0} onSave={v=>handleEdit(r.employee_id,f.key,v)}/>
                  }
                </td>
              ))}
              {isMd && <>
                <td style={{...td(),textAlign:'center',background:bg}}>{r.h_basic||0}</td>
                <td style={{...td(),textAlign:'right',background:bg}}>{rp(r.u_basic||0)}</td>
                <td style={{...td(),textAlign:'center',background:bg}}>{r.h_kerja||0}</td>
                <td style={{...td(),textAlign:'right',background:bg}}>{rp(r.u_kerja||0)}</td>
              </>}
              <td style={td('#22C97A',true,bg)}>{(r.jml_jam_lembur||0).toFixed(1)} jam</td>
              <td style={td('#22C97A',true,bg)}>{rp(r.upah_lembur)}</td>
              <td style={{...td(),textAlign:'center',fontWeight:700,background:bg}}>{r.h_kerja||0}</td>
              {isMd && <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.kekurangan_bulan_lalu||0} onSave={v=>handleEdit(r.employee_id,'kekurangan_bulan_lalu',v)}/></td>}
              <td style={td('var(--accent)',true,bg)}>{rp(r.gaji_kotor)}</td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_jht} overridden={r._no_jht} onToggle={()=>handleEdit(r.employee_id,'_no_jht',!r._no_jht)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_pensiun} overridden={r._no_pensiun} onToggle={()=>handleEdit(r.employee_id,'_no_pensiun',!r._no_pensiun)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_kes} overridden={r._no_kes} onToggle={()=>handleEdit(r.employee_id,'_no_kes',!r._no_kes)}/></td>
              <td style={{...td(),padding:'2px 4px',background:r.potongan_alpa>0?'rgba(255,205,205,.15)':bg}}><BpjsCell val={r.potongan_alpa} overridden={r._no_alpa} onToggle={()=>handleEdit(r.employee_id,'_no_alpa',!r._no_alpa)}/></td>
              <td style={{...td(),textAlign:'right',background:r.potongan_insentif>0?'rgba(255,205,205,.15)':bg,color:'#E04545'}}>{r.potongan_insentif>0?rp(r.potongan_insentif):'—'}</td>
              {isKhawistaPiling&&<td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.pot_tabung_oksigen||0} onSave={v=>handleEdit(r.employee_id,'pot_tabung_oksigen',v)}/></td>}
              <td style={td('#22C97A',true,bg)}>{rp(r.gaji_bersih)}</td>
              <td style={{...td(),textAlign:'center',color:r.izin>0?'var(--blue)':'var(--muted)',background:bg}}>{r.izin||''}</td>
              <td style={{...td(),textAlign:'center',color:r.sakit>0?'var(--accent)':'var(--muted)',background:bg}}>{r.sakit||''}</td>
              <td style={{...td(),textAlign:'center',color:r.alpa>0?'#E04545':'var(--muted)',fontWeight:r.alpa>0?700:400,background:bg}}>{r.alpa||''}</td>
              <td style={{...td(),textAlign:'center',color:r.cuti>0?'#22C97A':'var(--muted)',background:bg}}>{r.cuti||''}</td>
            </tr>);
          })}
        </tbody>
        <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
          <tr>
            <td colSpan={2} style={{...th1,textAlign:'right',paddingRight:8,position:'sticky',left:0,zIndex:30,fontSize:9}}>TOTAL ({data.length} karyawan)</td>
            <td style={th1}>—</td>
            <td style={th1}>—</td>
            <td style={th1}>{rp(data.reduce((s,r)=>s+(r.gaji_pokok||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_tetap||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_jabatan||0),0))}</td>
            {!isMd && <td style={th1}>{rp(data.reduce((s,r)=>s+(r.upah_penuh||0),0))}</td>}
            {activeTTT.map(f=>(<td key={f.key} style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r[f.key]||0),0))}</td>))}
            {isMd && <><td style={th5}>—</td><td style={th5}>—</td><td style={th5}>—</td><td style={th5}>—</td></>}
            <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>—</td>
            <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>{rp(data.reduce((s,r)=>s+(r.upah_lembur||0),0))}</td>
            <td style={th1}>{data.reduce((s,r)=>s+(r.h_kerja||0),0)}</td>
            {isMd && <td style={{...thB,background:'#FFFACD',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.kekurangan_bulan_lalu||0),0))}</td>}
            <td style={{...th1,fontWeight:700}}>{rp(data.reduce((s,r)=>s+(r.gaji_kotor||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_jht||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_pensiun||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_kes||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_alpa||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_insentif||0),0))}</td>
            {isKhawistaPiling&&<td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.pot_tabung_oksigen||0),0))}</td>}
            <td style={{...thB,background:'#E2EFDA',color:'#276221',fontWeight:700,fontSize:9.5}}>{rp(data.reduce((s,r)=>s+(r.gaji_bersih||0),0))}</td>
            <td colSpan={4} style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}}>—</td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

function TabelFlat({ data, activeTTT, onEdit, onScheduleSave, isDark, projectKode='', bpjsPct={jht:2,pensiun:1,kes:1} }) {
  const solidBg0=isDark?'#121620':'#FFFFFF', solidBg1=isDark?'#181E2A':'#F8F9FC';
  const thB={padding:'5px 5px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1={...thB,background:'#F4A010',color:'#1A0A00'}, th2={...thB,background:'#FFF2CC',color:'#7B5E00'};
  const th3={...thB,background:'#E2EFDA',color:'#276221'}, th4={...thB,background:'#FCE4D6',color:'#9C0006'};
  const th5={...thB,background:'#DAEEF3',color:'#0C4B6E'}, thE={...thB,background:'#FFFACD',color:'#7B5E00'};
  function td(c,b,bg){return{padding:'3px 5px',fontSize:10,textAlign:'right',border:'1px solid var(--border)',color:c||'var(--text)',fontWeight:b?700:400,background:bg||'transparent',whiteSpace:'nowrap'};}
  function handleEdit(id,f,v){onEdit(id,f,v);onScheduleSave(id);}
  const isKhawista = ['khawista', 'nk'].includes(projectKode);
  const [selectedRow,setSelectedRow] = useState(null);
  const containerRef = useRef(null);
  useEffect(()=>{
    function handleClickOutside(e){ if(containerRef.current && !containerRef.current.contains(e.target)) setSelectedRow(null); }
    document.addEventListener('mousedown', handleClickOutside);
    return ()=>document.removeEventListener('mousedown', handleClickOutside);
  },[]);
  return (
    <div ref={containerRef} style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 210px)'}}>
      <table style={{borderCollapse:'collapse',fontSize:10.5,whiteSpace:'nowrap',width:'100%'}}>
        <thead style={{position:'sticky',top:0,zIndex:20}}>
          <tr>
            <th style={{...th1,width:30,minWidth: 30, position:'sticky',left:0,zIndex:30,background:'#F4A010'}} rowSpan={2}>#</th>
            <th style={{...th1,minWidth:160,textAlign:'left',position:'sticky',left:25,zIndex:30,background:'#F4A010'}} rowSpan={2}>NAMA KARYAWAN</th>
            <th style={{...th1,minWidth:90,textAlign:'left'}} rowSpan={2}>JABATAN</th>
            <th style={th1} rowSpan={2}>PTKP</th>
            <th style={thE} rowSpan={2}>GAJI POKOK <Pencil size={9} style={{verticalAlign:2}}/></th>
            <th style={th2} colSpan={3}>TUNJANGAN TETAP</th>
            {activeTTT.length>0&&<th style={th2} colSpan={activeTTT.length}>TUNJANGAN TIDAK TETAP <Pencil size={9} style={{verticalAlign:2}}/></th>}
            {!isKhawista && <th style={th3} colSpan={3}>LEMBUR FLAT</th>}
            <th style={thE} rowSpan={2}>U. HADIR <Pencil size={9} style={{verticalAlign:2}}/></th>
            <th style={th1} rowSpan={2}>H.KERJA</th><th style={th1} rowSpan={2}>GAJI KOTOR</th>
            <th style={th4} colSpan={5}>POTONGAN</th>
            <th style={th1} rowSpan={2}>GAJI BERSIH</th>
            <th style={th5} colSpan={4}>ABSENSI</th>
          </tr>
          <tr>
            <th style={thE}>Transport <Pencil size={9} style={{verticalAlign:2}}/></th><th style={thE}>Tunj. Jabatan <Pencil size={9} style={{verticalAlign:2}}/></th><th style={th2}>Upah Penuh</th>
            {activeTTT.map(f=><th key={f.key} style={thE}>{f.label}{f.key !== 'kompensasi_pwt' ? <Pencil size={9} style={{verticalAlign:2,marginLeft:2}}/> : null}</th>)}
            {!isKhawista && <><th style={{...th3,background:'#C6EFCE'}}>L Sabtu (×75rb)</th><th style={{...th3,background:'#C6EFCE'}}>L Libur (×200rb)</th><th style={th3}>Total Lembur</th></>}
            <th style={th4}>BPJS JHT {bpjsPct.jht}%</th><th style={th4}>BPJS Pensiun {bpjsPct.pensiun}%</th><th style={th4}>BPJS Kes {bpjsPct.kes}%</th><th style={{...th4,background:'#FFCDD2'}}>Alpa/Prorata</th><th style={{...th4,background:'#FFCDD2'}}>Pot. Insentif</th>
            <th style={th5}>Izin</th><th style={th5}>Sakit</th><th style={th5}>Alpa</th><th style={th5}>Cuti</th>
          </tr>
        </thead>
        <tbody>
          {data.map((r,idx)=>{
            const isSel=selectedRow===r.employee_id;
            const bg=isSel?'rgba(58,143,224,.16)':(idx%2===0?'':'rgba(0,0,0,.025)');
            const stickyBg=isSel?(isDark?'#1B3A5C':'#DCEBFB'):(idx%2===0?solidBg0:solidBg1);
            return (<tr key={r.employee_id} onClick={()=>setSelectedRow(r.employee_id)} style={{cursor:'pointer',boxShadow:isSel?'inset 0 0 0 1.5px var(--blue)':'none'}}>
              <td style={{...td(),textAlign:'center',color:'var(--muted)',background:stickyBg,position:'sticky',left:0,zIndex:5}}>{idx+1}</td>
              <td style={{...td(),textAlign:'left',background:stickyBg,fontWeight:600,paddingLeft:8,position:'sticky',left:28,zIndex:5}}>{r.nama_lengkap}</td>
              <td style={{...td(),textAlign:'left',color:'var(--muted2)',fontSize:9,background:bg}}>{r.jabatan}</td>
              <td style={{...td(),textAlign:'center',fontSize:9,background:bg}}>{r.ptkp||'—'}</td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.gaji_pokok||0} onSave={v=>handleEdit(r.employee_id,'gaji_pokok',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_tetap||0} onSave={v=>handleEdit(r.employee_id,'tunj_tetap',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_jabatan||0} onSave={v=>handleEdit(r.employee_id,'tunj_jabatan',v)}/></td>
              <td style={td('var(--accent)',true,bg)}>{rp(r.upah_penuh)}</td>
              {activeTTT.map(f=>(
                <td key={f.key} style={{
                  ...td(),
                  background: f.key==='kompensasi_pwt' ? 'transparent' : 'rgba(255,252,180,.25)',
                  border: f.key==='kompensasi_pwt' ? '1px solid var(--border)' : '1px solid rgba(200,160,0,.2)'
                }}>
                  {f.key==='kompensasi_pwt' || f.key==='lump_sum'
                    ? <span style={{padding:'1px 3px',fontSize:10,color:'var(--muted2)'}}>{rp(r[f.key]||0)}</span>
                    : <EditCell val={r[f.key]||0} onSave={v=>handleEdit(r.employee_id,f.key,v)}/>
                  }
                </td>
              ))}
              {!isKhawista && <>
                <td style={{...td(),textAlign:'center',color:'#22C97A',fontWeight:700,background:bg}}>{r.l_sabtu||0}</td>
                <td style={{...td(),textAlign:'center',color:'#22C97A',fontWeight:700,background:bg}}>{r.l_libur||0}</td>
                <td style={td('#22C97A',true,bg)}>{rp(r.total_lembur_flat)}</td>
              </>}
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.uang_hadir||0} onSave={v=>handleEdit(r.employee_id,'uang_hadir',v)}/></td>
              <td style={{...td(),textAlign:'center',fontWeight:700,background:bg}}>{r.h_kerja||0}</td>
              <td style={td('var(--accent)',true,bg)}>{rp(r.gaji_kotor)}</td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_jht} overridden={r._no_jht} onToggle={()=>handleEdit(r.employee_id,'_no_jht',!r._no_jht)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_pensiun} overridden={r._no_pensiun} onToggle={()=>handleEdit(r.employee_id,'_no_pensiun',!r._no_pensiun)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_kes} overridden={r._no_kes} onToggle={()=>handleEdit(r.employee_id,'_no_kes',!r._no_kes)}/></td>
              <td style={{...td(),padding:'2px 4px',background:r.potongan_alpa>0?'rgba(255,205,205,.15)':bg}}><BpjsCell val={r.potongan_alpa} overridden={r._no_alpa} onToggle={()=>handleEdit(r.employee_id,'_no_alpa',!r._no_alpa)}/></td>
              <td style={{...td(),textAlign:'right',background:r.potongan_insentif>0?'rgba(255,205,205,.15)':bg,color:'#E04545'}}>{r.potongan_insentif>0?rp(r.potongan_insentif):'—'}</td>
              <td style={td('#22C97A',true,bg)}>{rp(r.gaji_bersih)}</td>
              <td style={{...td(),textAlign:'center',color:r.izin>0?'var(--blue)':'var(--muted)',background:bg}}>{r.izin||''}</td>
              <td style={{...td(),textAlign:'center',color:r.sakit>0?'var(--accent)':'var(--muted)',background:bg}}>{r.sakit||''}</td>
              <td style={{...td(),textAlign:'center',color:r.alpa>0?'#E04545':'var(--muted)',fontWeight:r.alpa>0?700:400,background:bg}}>{r.alpa||''}</td>
              <td style={{...td(),textAlign:'center',color:r.cuti>0?'#22C97A':'var(--muted)',background:bg}}>{r.cuti||''}</td>
            </tr>);
          })}
        </tbody>
        <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
          <tr>
            <td colSpan={2} style={{...th1,textAlign:'right',paddingRight:8,position:'sticky',left:0,zIndex:30,fontSize:9}}>TOTAL ({data.length} karyawan)</td>
            <td style={th1}>—</td>
            <td style={th1}>—</td>
            <td style={th1}>{rp(data.reduce((s,r)=>s+(r.gaji_pokok||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_tetap||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_jabatan||0),0))}</td>
            <td style={th1}>{rp(data.reduce((s,r)=>s+(r.upah_penuh||0),0))}</td>
            {activeTTT.map(f=>(<td key={f.key} style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r[f.key]||0),0))}</td>))}
            {!isKhawista && <>
              <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>{data.reduce((s,r)=>s+(r.l_sabtu||0),0)}</td>
              <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>{data.reduce((s,r)=>s+(r.l_libur||0),0)}</td>
              <td style={{...thB,background:'#E2EFDA',color:'#276221',fontWeight:700}}>{rp(data.reduce((s,r)=>s+(r.total_lembur_flat||0),0))}</td>
            </>}
            <td style={{...thB,background:'#FFFACD',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.uang_hadir||0),0))}</td>
            <td style={th1}>{data.reduce((s,r)=>s+(r.h_kerja||0),0)}</td>
            <td style={{...th1,fontWeight:700}}>{rp(data.reduce((s,r)=>s+(r.gaji_kotor||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_jht||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_pensiun||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_kes||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_alpa||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_insentif||0),0))}</td>
            <td style={{...thB,background:'#E2EFDA',color:'#276221',fontWeight:700,fontSize:9.5}}>{rp(data.reduce((s,r)=>s+(r.gaji_bersih||0),0))}</td>
            <td colSpan={4} style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}}>—</td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

// ── TABEL HO — kantor pusat: tanpa timesheet, tanpa lembur sama sekali ──
// Dipisah per unit (HO-1/HO-2, sama seperti 2 sheet terpisah di Excel sumbernya) karena
// masing-masing unit punya riwayat gaji dengan nama kolom sendiri-sendiri. Setiap blok unit
// nampilin: identitas -> riwayat gaji 2018-2026 apa adanya (read-only, arsip) -> kolom Gaji
// [periode aktif] yang BISA diedit -> Gaji Kotor/BPJS/Gaji Bersih (masih dihitung otomatis,
// dipertahankan karena tetap dipakai buat proses payroll bulan berjalan).
const PTKP_OPTIONS = ['TK/0','TK/1','TK/2','TK/3','K/0','K/1','K/2','K/3'];
const STATUS_KARYAWAN_OPTIONS = ['PKWTT','PKWT','OWNER','Umum'];
// Sama persis dengan daftar bank di halaman Edit Karyawan (resources/js/Pages/Employee/Edit.jsx)
const BANK_OPTIONS = ['BCA','Mandiri','BRI','BNI','BSI','CIMB Niaga','Danamon','Permata','BTN','Mega','Bank Riau Kepri'];

// 'd-m-Y' (format tampilan backend) <-> 'Y-m-d' (format input type=date)
function toIsoDate(ddmmyyyy) {
  if (!ddmmyyyy) return '';
  const parts = ddmmyyyy.split('-');
  if (parts.length !== 3) return '';
  const [d, m, y] = parts;
  return `${y}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`;
}
function fromIsoDate(isoDate) {
  if (!isoDate) return '';
  const [y, m, d] = isoDate.split('-');
  return `${d}-${m}-${y}`;
}

function TabelHo({ data, activeTTT, onEdit, onScheduleSave, onEditInfo, isDark, bpjsPct={jht:2,pensiun:1,kes:1}, salaryMatrix={}, bulanNama='', tahun, token }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const solidBg0=isDark?'#121620':'#FFFFFF', solidBg1=isDark?'#181E2A':'#F8F9FC';
  const thB={padding:'5px 5px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1={...thB,background:'#F4A010',color:'#1A0A00'}, th2={...thB,background:'#FFF2CC',color:'#7B5E00'};
  const th3={...thB,background:'#EAEAEA',color:'#333'}, th4={...thB,background:'#FCE4D6',color:'#9C0006'}, thE={...thB,background:'#FFFACD',color:'#7B5E00'};
  const thHist={...thB,background:'#F0F0F0',color:'#555',fontWeight:600,maxWidth:90,overflow:'hidden',textOverflow:'ellipsis'};
  function td(c,b,bg){return{padding:'3px 5px',fontSize:10,textAlign:'right',border:'1px solid var(--border)',color:c||'var(--text)',fontWeight:b?700:400,background:bg||'transparent',whiteSpace:'nowrap'};}
  function tdEdit(){return{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'};}
  function handleEdit(id,f,v){onEdit(id,f,v);onScheduleSave(id);}
  const inpStyle={width:'100%',minWidth:82,border:'1px solid var(--border)',background:'var(--bg2)',color:'var(--text)',fontSize:9,textAlign:'center',outline:'none',padding:'2px 3px',borderRadius:3,fontFamily:"'Outfit',sans-serif"};
  const selStyle={...inpStyle,minWidth:64,cursor:'pointer'};

  const [histValues, setHistValues] = useState(()=>JSON.parse(JSON.stringify(salaryMatrix?.values||{})));
  useEffect(()=>{ setHistValues(JSON.parse(JSON.stringify(salaryMatrix?.values||{}))); },[salaryMatrix]);

  const histSaveTimer=useRef({});
  function handleHistoryEdit(empId,label,val){
    setHistValues(prev=>({ ...prev, [empId]:{...(prev[empId]||{}),[label]:val} }));
    const timerKey=`${empId}::${label}`;
    clearTimeout(histSaveTimer.current[timerKey]);
    histSaveTimer.current[timerKey]=setTimeout(()=>{
      const period=salaryMatrix?.periods?.[label];
      const req=period
        ? axios.put(`/timesheet/payroll/${empId}/manual`,{tahun:period.tahun,bulan:period.bulan,gaji_pokok:val},{headers:{'X-CSRF-TOKEN':token}})
        : axios.put(`/timesheet/payroll/${empId}/salary-history`,{label,nominal:val},{headers:{'X-CSRF-TOKEN':token}});
      req.catch(e=>console.error('Gagal simpan riwayat gaji',e));
    },500);
  }

  const labels = salaryMatrix?.labels || [];
  const rows = [...data].sort((a,b)=>(a.ho_unit||'').localeCompare(b.ho_unit||'') || (a.nama_lengkap||'').localeCompare(b.nama_lengkap||''));
  const [selectedRow,setSelectedRow] = useState(null);
  const containerRef = useRef(null);
  useEffect(()=>{
    function handleClickOutside(e){ if(containerRef.current && !containerRef.current.contains(e.target)) setSelectedRow(null); }
    document.addEventListener('mousedown', handleClickOutside);
    return ()=>document.removeEventListener('mousedown', handleClickOutside);
  },[]);

  return (
    <div>
      <div ref={containerRef} style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 270px)',border:'1px solid var(--border)',borderRadius:8}}>
        <table style={{borderCollapse:'collapse',fontSize:10.5,whiteSpace:'nowrap',width:'100%'}}>
          <thead style={{position:'sticky',top:0,zIndex:20}}>
            <tr>
              <th style={{...th1,width:30,minWidth:30,position:'sticky',left:0,zIndex:30,background:'#F4A010'}} rowSpan={2}>#</th>
              <th style={{...th1,minWidth:160,textAlign:'left',position:'sticky',left:25,zIndex:30,background:'#F4A010'}} rowSpan={2}>NAMA KARYAWAN</th>
              <th style={th3} rowSpan={2}>UNIT</th>
              <th style={{...th1,minWidth:90,textAlign:'left'}} rowSpan={2}>JABATAN</th>
              <th style={th3} rowSpan={2}>STATUS</th>
              <th style={th3} rowSpan={2}>TGL MASUK</th>
              <th style={th3} rowSpan={2}>PTKP</th>
              <th style={{...th3,minWidth:110}} rowSpan={2}>NO. REKENING</th>
              <th style={th3} rowSpan={2}>BANK</th>
              {labels.length>0 && <th style={{...thB,background:'#DDD'}} colSpan={labels.length}>RIWAYAT GAJI (ARSIP) <Pencil size={9} style={{verticalAlign:2}}/></th>}
              <th style={thE} rowSpan={2}>GAJI {(bulanNama||'').toUpperCase()} {tahun} <Pencil size={9} style={{verticalAlign:2}}/></th>
              <th style={th2} colSpan={2}>TUNJANGAN TETAP</th>
              {activeTTT.length>0&&<th style={th2} colSpan={activeTTT.length}>TUNJANGAN TIDAK TETAP <Pencil size={9} style={{verticalAlign:2}}/></th>}
              <th style={th1} rowSpan={2}>GAJI KOTOR</th>
              <th style={th4} colSpan={3}>POTONGAN WAJIB</th>
              <th style={th1} rowSpan={2}>GAJI BERSIH</th>
            </tr>
            <tr>
              {labels.map(l=><th key={l} title={l} style={thHist}>{l}</th>)}
              <th style={thE}>Tunj. Tetap <Pencil size={9} style={{verticalAlign:2}}/></th><th style={th2}>Komp. PWT</th>
              {activeTTT.map(f=><th key={f.key} style={thE}>{f.label}<Pencil size={9} style={{verticalAlign:2,marginLeft:2}}/></th>)}
              <th style={th4}>BPJS JHT {bpjsPct.jht}%</th><th style={th4}>BPJS Pensiun {bpjsPct.pensiun}%</th><th style={th4}>BPJS Kes {bpjsPct.kes}%</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r,idx)=>{
              const isSel=selectedRow===r.employee_id;
              const bg=isSel?'rgba(58,143,224,.16)':(idx%2===0?'':'rgba(0,0,0,.025)');
              const stickyBg=isSel?(isDark?'#1B3A5C':'#DCEBFB'):(idx%2===0?solidBg0:solidBg1);
              const rowValues = histValues[r.employee_id] || {};
              return (<tr key={r.employee_id} onClick={()=>setSelectedRow(r.employee_id)} style={{cursor:'pointer',boxShadow:isSel?'inset 0 0 0 1.5px var(--blue)':'none'}}>
                <td style={{...td(),textAlign:'center',color:'var(--muted)',background:stickyBg,position:'sticky',left:0,zIndex:5}}>{idx+1}</td>
                <td style={{...td(),textAlign:'left',background:stickyBg,fontWeight:600,paddingLeft:8,position:'sticky',left:28,zIndex:5}}>{r.nama_lengkap}</td>
                <td style={{...td(),textAlign:'center',fontSize:9,fontWeight:700,background:bg}}>{r.ho_unit||'—'}</td>
                <td style={{...td(),textAlign:'left',color:'var(--muted2)',fontSize:9,background:bg}}>{r.jabatan}</td>
                <td style={{...td(),textAlign:'center',fontSize:9,background:bg,padding:'2px 3px'}}>
                  {isViewer ? (r.ho_status_karyawan||'—') : (
                    <select value={r.ho_status_karyawan||''} onChange={e=>onEditInfo(r.employee_id,'ho_status_karyawan',e.target.value)} onClick={e=>e.stopPropagation()} style={selStyle}>
                      <option value="">—</option>
                      {[...new Set([...STATUS_KARYAWAN_OPTIONS, ...(r.ho_status_karyawan?[r.ho_status_karyawan]:[])])].map(s=><option key={s} value={s}>{s}</option>)}
                    </select>
                  )}
                </td>
                <td style={{...td(),textAlign:'center',fontSize:9,background:bg,padding:'2px 3px'}}>
                  {isViewer ? (r.tanggal_masuk||'—') : (
                    <input type="date" value={toIsoDate(r.tanggal_masuk)} onChange={e=>onEditInfo(r.employee_id,'tanggal_masuk',fromIsoDate(e.target.value))} onClick={e=>e.stopPropagation()} style={inpStyle}/>
                  )}
                </td>
                <td style={{...td(),textAlign:'center',fontSize:9,background:bg,padding:'2px 3px'}}>
                  {isViewer ? (r.ptkp||'—') : (
                    <select value={r.ptkp||''} onChange={e=>onEditInfo(r.employee_id,'ptkp',e.target.value)} onClick={e=>e.stopPropagation()} style={selStyle}>
                      <option value="">—</option>
                      {[...new Set([...PTKP_OPTIONS, ...(r.ptkp&&r.ptkp!=='—'?[r.ptkp]:[])])].map(p=><option key={p} value={p}>{p}</option>)}
                    </select>
                  )}
                </td>
                <td style={{...td(),textAlign:'center',fontSize:9,background:bg,padding:'2px 3px'}}>
                  {isViewer ? (r.no_rekening||'—') : (
                    <input type="text" value={r.no_rekening||''} onChange={e=>onEditInfo(r.employee_id,'no_rekening',e.target.value)} onClick={e=>e.stopPropagation()} style={inpStyle} placeholder="No. Rekening"/>
                  )}
                </td>
                <td style={{...td(),textAlign:'center',fontSize:9,background:bg,padding:'2px 3px'}}>
                  {isViewer ? (r.nama_bank||'—') : (
                    <select value={r.nama_bank||''} onChange={e=>onEditInfo(r.employee_id,'nama_bank',e.target.value)} onClick={e=>e.stopPropagation()} style={selStyle}>
                      <option value="">—</option>
                      {[...new Set([...BANK_OPTIONS, ...(r.nama_bank?[r.nama_bank]:[])])].map(b=><option key={b} value={b}>{b}</option>)}
                    </select>
                  )}
                </td>
                {labels.map(l=>(
                  <td key={l} style={{...td('var(--muted2)',false,bg),fontSize:9,background:'rgba(255,252,180,.15)'}}>
                    <EditCell val={rowValues[l]||0} onSave={v=>handleHistoryEdit(r.employee_id,l,v)} width={62}/>
                  </td>
                ))}
                <td style={tdEdit()}><EditCell val={r.gaji_pokok||0} onSave={v=>handleEdit(r.employee_id,'gaji_pokok',v)}/></td>
                <td style={tdEdit()}><EditCell val={r.tunj_tetap||0} onSave={v=>handleEdit(r.employee_id,'tunj_tetap',v)}/></td>
                <td style={td('var(--muted2)',false,bg)}>{rp(r.kompensasi_pwt)}</td>
                {activeTTT.map(f=>(
                  <td key={f.key} style={tdEdit()}><EditCell val={r[f.key]||0} onSave={v=>handleEdit(r.employee_id,f.key,v)}/></td>
                ))}
                <td style={td('var(--accent)',true,bg)}>{rp(r.gaji_kotor)}</td>
                <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_jht} overridden={r._no_jht} onToggle={()=>handleEdit(r.employee_id,'_no_jht',!r._no_jht)}/></td>
                <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_pensiun} overridden={r._no_pensiun} onToggle={()=>handleEdit(r.employee_id,'_no_pensiun',!r._no_pensiun)}/></td>
                <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_kes} overridden={r._no_kes} onToggle={()=>handleEdit(r.employee_id,'_no_kes',!r._no_kes)}/></td>
                <td style={td('#22C97A',true,bg)}>{rp(r.gaji_bersih)}</td>
              </tr>);
            })}
          </tbody>
          <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
            <tr>
              <td colSpan={2} style={{...th1,textAlign:'right',paddingRight:8,position:'sticky',left:0,zIndex:30,fontSize:9}}>TOTAL ({rows.length})</td>
              <td style={th3}>—</td>
              <td style={th1}>—</td>
              <td style={th3}>—</td>
              <td style={th3}>—</td>
              <td style={th3}>—</td>
              <td style={th3}>—</td>
              <td style={th3}>—</td>
              {labels.map(l=><td key={l} style={{...thB,background:'#DDD',fontSize:8}}>{rp(rows.reduce((s,r)=>s+((histValues[r.employee_id]||{})[l]||0),0))}</td>)}
              <td style={th1}>{rp(rows.reduce((s,r)=>s+(r.gaji_pokok||0),0))}</td>
              <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(rows.reduce((s,r)=>s+(r.tunj_tetap||0),0))}</td>
              <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(rows.reduce((s,r)=>s+(r.kompensasi_pwt||0),0))}</td>
              {activeTTT.map(f=>(<td key={f.key} style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(rows.reduce((s,r)=>s+(r[f.key]||0),0))}</td>))}
              <td style={{...th1,fontWeight:700}}>{rp(rows.reduce((s,r)=>s+(r.gaji_kotor||0),0))}</td>
              <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(rows.reduce((s,r)=>s+(r.potongan_jht||0),0))}</td>
              <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(rows.reduce((s,r)=>s+(r.potongan_pensiun||0),0))}</td>
              <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(rows.reduce((s,r)=>s+(r.potongan_kes||0),0))}</td>
              <td style={{...thB,background:'#E2EFDA',color:'#276221',fontWeight:700,fontSize:9.5}}>{rp(rows.reduce((s,r)=>s+(r.gaji_bersih||0),0))}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
}

function TabelGabung({ data, activeTTT, onEdit, onScheduleSave, isDark, projectKode='', isMd=false, bpjsPct={jht:2,pensiun:1,kes:1} }) {
  const solidBg0=isDark?'#121620':'#FFFFFF', solidBg1=isDark?'#181E2A':'#F8F9FC';
  const thB={padding:'5px 5px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1={...thB,background:'#F4A010',color:'#1A0A00'}, th2={...thB,background:'#FFF2CC',color:'#7B5E00'};
  const th3={...thB,background:'#E2EFDA',color:'#276221'}, th4={...thB,background:'#FCE4D6',color:'#9C0006'};
  const th5={...thB,background:'#DAEEF3',color:'#0C4B6E'}, thE={...thB,background:'#FFFACD',color:'#7B5E00'};
  function td(c,b,bg){return{padding:'3px 5px',fontSize:10,textAlign:'right',border:'1px solid var(--border)',color:c||'var(--text)',fontWeight:b?700:400,background:bg||'transparent',whiteSpace:'nowrap'};}
  function handleEdit(id,f,v){onEdit(id,f,v);onScheduleSave(id);}
  function fmtFlatLembur(r){
    const parts=[];
    if(r.l_sabtu)      parts.push(`${r.l_sabtu} Sabtu`);
    if(r.l_libur)      parts.push(`${r.l_libur} Libur`);
    if(r.lembur_biasa) parts.push(`${r.lembur_biasa} Biasa`);
    return parts.length?parts.join(' · '):'—';
  }
  const isKhawista = ['khawista', 'nk'].includes(projectKode);
  const [selectedRow,setSelectedRow] = useState(null);
  const containerRef = useRef(null);
  useEffect(()=>{
    function handleClickOutside(e){ if(containerRef.current && !containerRef.current.contains(e.target)) setSelectedRow(null); }
    document.addEventListener('mousedown', handleClickOutside);
    return ()=>document.removeEventListener('mousedown', handleClickOutside);
  },[]);
  return (
    <div ref={containerRef} style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 210px)'}}>
      <table style={{borderCollapse:'collapse',fontSize:10.5,whiteSpace:'nowrap',width:'100%'}}>
        <thead style={{position:'sticky',top:0,zIndex:20}}>
          <tr>
            <th style={{...th1,width:30,minWidth: 30, position:'sticky',left:0,zIndex:30,background:'#F4A010'}} rowSpan={2}>#</th>
            <th style={{...th1,minWidth:160,textAlign:'left',position:'sticky',left:25,zIndex:30,background:'#F4A010'}} rowSpan={2}>NAMA KARYAWAN</th>
            <th style={{...th1,minWidth:85,textAlign:'left'}} rowSpan={2}>JABATAN</th>
            <th style={th1} rowSpan={2}>PTKP</th>
            <th style={thE} rowSpan={2}>GAJI POKOK <Pencil size={9} style={{verticalAlign:2}}/></th>
            <th style={th2} colSpan={isMd ? 2 : 3}>TUNJANGAN TETAP</th>
            {activeTTT.length>0&&<th style={th2} colSpan={activeTTT.length}>TUNJANGAN TIDAK TETAP <Pencil size={9} style={{verticalAlign:2}}/></th>}
            {isMd && <th style={th5} colSpan={4}>KOMPONEN MD</th>}
            <th style={th3} colSpan={2}>LEMBUR</th>
            <th style={th1} rowSpan={2}>H.KERJA</th>
            {isMd && <th style={thE} rowSpan={2}>Kekurangan Bln Lalu <Pencil size={9} style={{verticalAlign:2}}/></th>}
            <th style={th1} rowSpan={2}>GAJI KOTOR</th>
            <th style={th4} colSpan={5}>POTONGAN</th>
            <th style={th1} rowSpan={2}>GAJI BERSIH</th>
            <th style={th5} colSpan={4}>ABSENSI</th>
          </tr>
          <tr>
            <th style={thE}>Transport <Pencil size={9} style={{verticalAlign:2}}/></th><th style={thE}>Tunj. Jabatan <Pencil size={9} style={{verticalAlign:2}}/></th>{!isMd && <th style={th2}>Upah Penuh</th>}
            {activeTTT.map(f=><th key={f.key} style={thE}>{f.label}{f.key !== 'kompensasi_pwt' ? <Pencil size={9} style={{verticalAlign:2,marginLeft:2}}/> : null}</th>)}
            {isMd && <><th style={th5}>H.Basic</th><th style={th5}>U.Basic</th><th style={th5}>H.Kerja</th><th style={th5}>U.Kerja</th></>}
            <th style={th3}>Jam / Flat</th><th style={th3}>Upah Lembur</th>
            <th style={th4}>BPJS JHT {bpjsPct.jht}%</th><th style={th4}>BPJS Pensiun {bpjsPct.pensiun}%</th><th style={th4}>BPJS Kes {bpjsPct.kes}%</th><th style={{...th4,background:'#FFCDD2'}}>Alpa/Prorata</th><th style={{...th4,background:'#FFCDD2'}}>Pot. Insentif</th>
            <th style={th5}>Izin</th><th style={th5}>Sakit</th><th style={th5}>Alpa</th><th style={th5}>Cuti</th>
          </tr>
        </thead>
        <tbody>
          {data.map((r,idx)=>{
            const isFlat=r.kelompok==='flat';
            const isSel=selectedRow===r.employee_id;
            const bg=isSel?'rgba(58,143,224,.16)':(idx%2===0?'':'rgba(0,0,0,.025)');
            const stickyBg=isSel?(isDark?'#1B3A5C':'#DCEBFB'):(idx%2===0?solidBg0:solidBg1);
            return (<tr key={r.employee_id} onClick={()=>setSelectedRow(r.employee_id)} style={{cursor:'pointer',boxShadow:isSel?'inset 0 0 0 1.5px var(--blue)':'none'}}>
              <td style={{...td(),textAlign:'center',color:'var(--muted)',background:stickyBg,position:'sticky',left:0,zIndex:5}}>{idx+1}</td>
              <td style={{...td(),textAlign:'left',background:stickyBg,fontWeight:600,paddingLeft:8,position:'sticky',left:28,zIndex:5}}>{r.nama_lengkap}</td>
              <td style={{...td(),textAlign:'left',color:'var(--muted2)',fontSize:9,background:bg}}>{r.jabatan}</td>
              <td style={{...td(),textAlign:'center',fontSize:9,background:bg}}>{r.ptkp||'—'}</td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.gaji_pokok||0} onSave={v=>handleEdit(r.employee_id,'gaji_pokok',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_tetap||0} onSave={v=>handleEdit(r.employee_id,'tunj_tetap',v)}/></td>
              <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.tunj_jabatan||0} onSave={v=>handleEdit(r.employee_id,'tunj_jabatan',v)}/></td>
              {!isMd && <td style={td('var(--accent)',true,bg)}>{rp(r.upah_penuh)}</td>}{activeTTT.map(f=>(<td key={f.key} style={{...td(),background: f.key==='kompensasi_pwt' ? 'transparent' : 'rgba(255,252,180,.25)',border: f.key==='kompensasi_pwt' ? '1px solid var(--border)' : '1px solid rgba(200,160,0,.2)'}}>{f.key==='kompensasi_pwt'||f.key==='lump_sum'? <span style={{padding:'1px 3px',fontSize:10,color:'var(--muted2)'}}>{rp(r[f.key]||0)}</span>: <EditCell val={r[f.key]||0} onSave={v=>handleEdit(r.employee_id,f.key,v)}/>}</td>))}
              {isMd && <>
                <td style={{...td(),textAlign:'center',background:bg}}>{r.h_basic||0}</td>
                <td style={{...td(),textAlign:'right',background:bg}}>{rp(r.u_basic||0)}</td>
                <td style={{...td(),textAlign:'center',background:bg}}>{r.h_kerja||0}</td>
                <td style={{...td(),textAlign:'right',background:bg}}>{rp(r.u_kerja||0)}</td>
              </>}
              <td style={{...td('#22C97A',true,bg),fontSize:9}}>{isFlat?(isKhawista?'—':fmtFlatLembur(r)):((r.jml_jam_lembur||0)===0?'—':`${(r.jml_jam_lembur||0).toFixed(1)} jam`)}</td>
              <td style={td('#22C97A',true,bg)}>{isFlat&&isKhawista?'—':rp(isFlat?r.total_lembur_flat:r.upah_lembur)}</td>
              <td style={{...td(),textAlign:'center',fontWeight:700,background:bg}}>{r.h_kerja||0}</td>
              {isMd && <td style={{...td(),background:'rgba(255,252,180,.25)',border:'1px solid rgba(200,160,0,.2)'}}><EditCell val={r.kekurangan_bulan_lalu||0} onSave={v=>handleEdit(r.employee_id,'kekurangan_bulan_lalu',v)}/></td>}
              <td style={td('var(--accent)',true,bg)}>{rp(r.gaji_kotor)}</td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_jht} overridden={r._no_jht} onToggle={()=>handleEdit(r.employee_id,'_no_jht',!r._no_jht)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_pensiun} overridden={r._no_pensiun} onToggle={()=>handleEdit(r.employee_id,'_no_pensiun',!r._no_pensiun)}/></td>
              <td style={{...td(),padding:'2px 4px',background:bg}}><BpjsCell val={r.potongan_kes} overridden={r._no_kes} onToggle={()=>handleEdit(r.employee_id,'_no_kes',!r._no_kes)}/></td>
              <td style={{...td(),padding:'2px 4px',background:r.potongan_alpa>0?'rgba(255,205,205,.15)':bg}}><BpjsCell val={r.potongan_alpa} overridden={r._no_alpa} onToggle={()=>handleEdit(r.employee_id,'_no_alpa',!r._no_alpa)}/></td>
              <td style={{...td(),textAlign:'right',background:r.potongan_insentif>0?'rgba(255,205,205,.15)':bg,color:'#E04545'}}>{r.potongan_insentif>0?rp(r.potongan_insentif):'—'}</td>
              <td style={td('#22C97A',true,bg)}>{rp(r.gaji_bersih)}</td>
              <td style={{...td(),textAlign:'center',color:r.izin>0?'var(--blue)':'var(--muted)',background:bg}}>{r.izin||''}</td>
              <td style={{...td(),textAlign:'center',color:r.sakit>0?'var(--accent)':'var(--muted)',background:bg}}>{r.sakit||''}</td>
              <td style={{...td(),textAlign:'center',color:r.alpa>0?'#E04545':'var(--muted)',fontWeight:r.alpa>0?700:400,background:bg}}>{r.alpa||''}</td>
              <td style={{...td(),textAlign:'center',color:r.cuti>0?'#22C97A':'var(--muted)',background:bg}}>{r.cuti||''}</td>
            </tr>);
          })}
        </tbody>
        <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
          <tr>
            <td colSpan={2} style={{...th1,textAlign:'right',paddingRight:8,position:'sticky',left:0,zIndex:30,fontSize:9}}>TOTAL ({data.length} karyawan)</td>
            <td style={th1}>—</td>
            <td style={th1}>—</td>
            <td style={th1}>{rp(data.reduce((s,r)=>s+(r.gaji_pokok||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_tetap||0),0))}</td>
            <td style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.tunj_jabatan||0),0))}</td>
            {!isMd && <td style={th1}>{rp(data.reduce((s,r)=>s+(r.upah_penuh||0),0))}</td>}
            {activeTTT.map(f=>(<td key={f.key} style={{...thB,background:'#FFF2CC',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r[f.key]||0),0))}</td>))}
            {isMd && <>
              <td style={th5}>—</td>
              <td style={th5}>{rp(data.reduce((s,r)=>s+(r.u_basic||0),0))}</td>
              <td style={th5}>—</td>
              <td style={th5}>{rp(data.reduce((s,r)=>s+(r.u_kerja||0),0))}</td>
            </>}
            <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>—</td>
            <td style={{...thB,background:'#E2EFDA',color:'#276221'}}>{rp(data.reduce((s,r)=>s+(r.upah_lembur||0),0))}</td>
            <td style={th1}>{data.reduce((s,r)=>s+(r.h_kerja||0),0)}</td>
            {isMd && <td style={{...thB,background:'#FFFACD',color:'#7B5E00'}}>{rp(data.reduce((s,r)=>s+(r.kekurangan_bulan_lalu||0),0))}</td>}
            <td style={{...th1,fontWeight:700}}>{rp(data.reduce((s,r)=>s+(r.gaji_kotor||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_jht||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_pensiun||0),0))}</td>
            <td style={{...thB,background:'#FCE4D6',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_kes||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_alpa||0),0))}</td>
            <td style={{...thB,background:'#FFCDD2',color:'#9C0006'}}>{rp(data.reduce((s,r)=>s+(r.potongan_insentif||0),0))}</td>
            <td style={{...thB,background:'#E2EFDA',color:'#276221',fontWeight:700,fontSize:9.5}}>{rp(data.reduce((s,r)=>s+(r.gaji_bersih||0),0))}</td>
            <td colSpan={4} style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}}>—</td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

function TarifInput({ val, onChange, color='#276221' }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState('');
  const ref = useRef(null);

  function startEdit() {
    setDraft(String(val || 0));
    setEditing(true);
    setTimeout(() => ref.current?.select(), 20);
  }

  function commit() {
    setEditing(false);
    const num = Math.max(0, parseInt(String(draft).replace(/\D/g, '')) || 0);
    onChange(num);
  }

  if (editing) {
    return (
      <input ref={ref} type="text" value={draft}
        onChange={e => setDraft(e.target.value)}
        onBlur={commit}
        onKeyDown={e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); commit(); }
          if (e.key === 'Escape') setEditing(false);
        }}
        style={{
          width:'100%', textAlign:'right', fontSize:11, fontWeight:700,
          background:'transparent', border:'none', outline:'none',
          fontFamily:"'Outfit',sans-serif", color, padding:'2px 4px',
        }}
      />
    );
  }

  return (
    <div onClick={startEdit}
      style={{
        textAlign:'right', fontSize:11, fontWeight:700, cursor:'pointer',
        color: val > 0 ? color : 'var(--muted)', padding:'2px 4px',
        fontFamily:"'Outfit',sans-serif",
      }}>
      {val > 0 ? rp(val) : <span style={{fontSize:8.5, opacity:.4}}>edit</span>}
    </div>
  );
}

function HariInput({ val, onChange, color='#276221' }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState('');
  const ref = useRef(null);

  function startEdit() {
    setDraft(String(val || 0));
    setEditing(true);
    setTimeout(() => ref.current?.select(), 20);
  }

  function commit() {
    setEditing(false);
    const num = Math.max(0, parseInt(String(draft).replace(/\D/g, '')) || 0);
    onChange(num);
  }

  if (editing) {
    return (
      <input ref={ref} type="text" value={draft}
        onChange={e => setDraft(e.target.value)}
        onBlur={commit}
        onKeyDown={e => {
          if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); commit(); }
          if (e.key === 'Escape') setEditing(false);
        }}
        style={{
          width:'100%', textAlign:'right', fontSize:11, fontWeight:700,
          background:'transparent', border:'none', outline:'none',
          fontFamily:"'Outfit',sans-serif", color, padding:'2px 4px',
        }}
      />
    );
  }

  return (
    <div onClick={startEdit}
      style={{
        textAlign:'right', fontSize:11, fontWeight:700, cursor:'pointer',
        color: val > 0 ? color : 'var(--muted)', padding:'2px 4px',
        fontFamily:"'Outfit',sans-serif",
      }}>
      {val > 0 ? val : <span style={{fontSize:8.5, opacity:.4}}>edit</span>}
    </div>
  );
}

function TabelOvertimeCustom({ tahun, bulan, token, isViewer=false, subGroup='all', searchQuery='', flatIds=[] }) {
  const [allRows, setAllRows] = useState([]);
  const [members, setMembers] = useState([]);
  const [showAdd, setShowAdd] = useState(false);
  const [loading, setLoading] = useState(true);
  const saveTimer = useRef({});

  useEffect(() => {
    setLoading(true);
    axios.get('/overtime-custom', { params: { tahun, bulan } })
      .then(res => {
        const rows = res.data.rows || [];
        setAllRows(rows);
        setMembers(
          flatIds.length > 0
            ? rows.filter(r => flatIds.includes(r.employee_id)).map(r => r.employee_id)
            : rows.map(r => r.employee_id)
        );
      })
      .finally(() => setLoading(false));
  }, [tahun, bulan, flatIds.join(',')]);

  function autoSave(empId, kategori, tarif, hari) {
    const key = `${empId}_${kategori}`;
    clearTimeout(saveTimer.current[key]);
    saveTimer.current[key] = setTimeout(async () => {
      try {
        await axios.put(`/overtime-custom/${empId}`, {
          tahun, bulan, kategori,
          tarif_per_hari: tarif,
          jumlah_hari: hari,
        }, { headers: { 'X-CSRF-TOKEN': token } });

        // Hitung total semua kategori lalu simpan ke ttt_custom.lump_sum
        const row = allRows.find(r => r.employee_id === empId);
        if (row) {
          const sabtuTarif  = kategori === 'sabtu'  ? tarif : (row.sabtu_tarif  || 0);
          const sabtuHari   = kategori === 'sabtu'  ? hari  : (row.sabtu_hari   || 0);
          const mingguTarif = kategori === 'minggu' ? tarif : (row.minggu_tarif || 0);
          const mingguHari  = kategori === 'minggu' ? hari  : (row.minggu_hari  || 0);
          const totalLumpSum = (sabtuTarif * sabtuHari) + (mingguTarif * mingguHari);

          const row = allRows.find(r => r.employee_id === empId);
          const isPiling = (row?.sub_group||'').toLowerCase() === 'piling';
          if (isPiling) {
            await axios.put(`/timesheet/payroll/${empId}/manual`, {
              tahun, bulan,
              upah_lembur: totalLumpSum,
              total_lembur_flat: totalLumpSum,
            }, { headers: { 'X-CSRF-TOKEN': token } });
          } else {
            await axios.put(`/timesheet/payroll/${empId}/manual`, {
              tahun, bulan,
              ttt_custom: { lump_sum: totalLumpSum },
            }, { headers: { 'X-CSRF-TOKEN': token } });
          }
        }
      } catch(e) { console.error('save overtime custom failed', e); }
    }, 600);
  }

  function setField(empId, field, val) {
    const num = Math.max(0, parseInt(val) || 0);
    setAllRows(prev => prev.map(r => {
      if (r.employee_id !== empId) return r;
      const updated = { ...r, [field]: num };
      if (field === 'sabtu_tarif' || field === 'sabtu_hari') {
        autoSave(empId, 'sabtu', 
          field === 'sabtu_tarif' ? num : updated.sabtu_tarif,
          field === 'sabtu_hari'  ? num : updated.sabtu_hari
        );
      } else if (field === 'minggu_tarif' || field === 'minggu_hari') {
        autoSave(empId, 'minggu',
          field === 'minggu_tarif' ? num : updated.minggu_tarif,
          field === 'minggu_hari'  ? num : updated.minggu_hari
        );
      }
      return updated;
    }));
  }

  function addMember(empId) {
    if (members.includes(empId)) return;
    setMembers(prev => [...prev, empId]);
    setShowAdd(false);
  }

  function removeMember(empId) {
    setMembers(prev => prev.filter(id => id !== empId));
    // Reset tarif & hari ke 0 di DB
    axios.put(`/overtime-custom/${empId}`, {
      tahun, bulan, kategori: 'sabtu', tarif_per_hari: 0, jumlah_hari: 0,
    }, { headers: { 'X-CSRF-TOKEN': token } });
    axios.put(`/overtime-custom/${empId}`, {
      tahun, bulan, kategori: 'minggu', tarif_per_hari: 0, jumlah_hari: 0,
    }, { headers: { 'X-CSRF-TOKEN': token } });
  }

  const searchLow = searchQuery.toLowerCase();
  const displayed = allRows
      .filter(r => members.includes(r.employee_id))
      .filter(r => subGroup === 'all' || r.sub_group === subGroup || !r.sub_group)
      .filter(r => !searchQuery ||
        r.nama_lengkap?.toLowerCase().includes(searchLow) ||
        r.jabatan?.toLowerCase().includes(searchLow)
      );

  const notAdded = allRows
    .filter(r => !members.includes(r.employee_id))
    .filter(r => subGroup === 'all' || r.sub_group === subGroup || !r.sub_group);

  const totalSabtu  = displayed.reduce((s, r) => s + (r.sabtu_tarif  * r.sabtu_hari),  0);
  const totalMinggu = displayed.reduce((s, r) => s + (r.minggu_tarif * r.minggu_hari), 0);
  const totalAll    = totalSabtu + totalMinggu;

  const thB = {padding:'5px 8px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1 = {...thB,background:'#F4A010',color:'#1A0A00'};
  const th3 = {...thB,background:'#E2EFDA',color:'#276221'};
  const thE = {...thB,background:'#FFFACD',color:'#7B5E00'};
  const inpStyle = {
    width:'100%',textAlign:'right',fontSize:11,fontWeight:700,
    background:'transparent',border:'none',outline:'none',
    fontFamily:"'Outfit',sans-serif",color:'#22C97A',padding:'2px 4px',
  };

  if (loading) return (
    <div style={{padding:40,textAlign:'center',color:'var(--muted)',fontSize:12,display:'flex',alignItems:'center',justifyContent:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> Memuat data overtime...</div>
  );

  return (
    <div>
      {/* Header */}
      <div style={{padding:'10px 16px',background:'rgba(232,160,32,.06)',borderBottom:'1px solid var(--border)',display:'flex',alignItems:'center',justifyContent:'space-between',gap:12,flexWrap:'wrap'}}>
        <div style={{fontSize:11.5,color:'var(--muted2)'}}>
          <b style={{color:'var(--accent)'}}><AlarmClock size={12} style={{verticalAlign:'-2px'}}/> Overtime Custom — KHAWISTA</b>
          <span style={{marginLeft:10}}>Tarif per orang bisa berbeda · Hari otomatis dari timesheet</span>
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <span style={{fontSize:11,color:'var(--muted)'}}>{displayed.length} dari {members.length} anggota</span>
          {!isViewer && (
            <button onClick={()=>setShowAdd(v=>!v)}
              style={{padding:'5px 12px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:11.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}>
              <Plus size={12}/> Tambah
            </button>
          )}
        </div>
      </div>

      {/* Panel tambah anggota */}
      {showAdd && (
        <div style={{padding:'10px 16px',background:'var(--bg3)',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontSize:11,color:'var(--muted)',marginBottom:8}}>Pilih karyawan untuk ditambahkan ke daftar overtime:</div>
          <div style={{display:'flex',flexWrap:'wrap',gap:6}}>
            {notAdded.length === 0
              ? <span style={{fontSize:11,color:'var(--muted)'}}>Semua karyawan sudah ada di daftar</span>
              : notAdded.map(r => (
                <button key={r.employee_id} onClick={() => addMember(r.employee_id)}
                  style={{padding:'4px 10px',borderRadius:99,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:11,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                  + {r.nama_lengkap} <span style={{fontSize:9,color:'var(--muted)'}}>({r.jabatan})</span>
                </button>
              ))
            }
          </div>
        </div>
      )}

      {/* Tabel */}
      <div style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 280px)'}}>
        <table style={{borderCollapse:'collapse',fontSize:11,width:'100%',minWidth:900}}>
          <thead style={{position:'sticky',top:0,zIndex:20}}>
            <tr>
              <th style={{...th1,width:32}} rowSpan={2}>#</th>
              <th style={{...th1,minWidth:200,textAlign:'left'}} rowSpan={2}>NAMA KARYAWAN</th>
              <th style={{...th1,minWidth:100,textAlign:'left'}} rowSpan={2}>JABATAN</th>
              <th style={{...thB,background:'#C6EFCE',color:'#276221'}} colSpan={3}>LEMBUR SABTU</th>
              <th style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}} colSpan={3}>LEMBUR MINGGU</th>
              <th style={th1} rowSpan={2}>TOTAL LEMBUR</th>
              {!isViewer && <th style={{...thB,background:'var(--bg2)',color:'var(--muted)',width:50}} rowSpan={2}>Hapus</th>}
            </tr>
            <tr>
              <th style={{...thE,background:'#C6EFCE',color:'#276221'}}>Tarif/Hari <Pencil size={9} style={{verticalAlign:2}}/></th>
              <th style={{...thE,background:'#C6EFCE',color:'#276221'}}>Jml Hari <Pencil size={9} style={{verticalAlign:2}}/></th>
              <th style={{...th3,background:'#C6EFCE'}}>Subtotal</th>
              <th style={{...thE,background:'#DAEEF3',color:'#0C4B6E'}}>Tarif/Hari <Pencil size={9} style={{verticalAlign:2}}/></th>
              <th style={{...thE,background:'#DAEEF3',color:'#0C4B6E'}}>Jml Hari <Pencil size={9} style={{verticalAlign:2}}/></th>
              <th style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}}>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {displayed.map((r, idx) => {
              const subtotalSabtu  = (r.sabtu_tarif  || 0) * (r.sabtu_hari  || 0);
              const subtotalMinggu = (r.minggu_tarif || 0) * (r.minggu_hari || 0);
              const total = subtotalSabtu + subtotalMinggu;
              const bg = idx % 2 === 0 ? 'var(--card)' : 'var(--bg3)';
              return (
                <tr key={r.employee_id}>
                  <td style={{padding:'4px 8px',textAlign:'center',color:'var(--muted)',fontSize:10,border:'1px solid var(--border)',background:bg}}>{idx+1}</td>
                  <td style={{padding:'5px 10px',fontWeight:600,fontSize:12,border:'1px solid var(--border)',background:bg}}>{r.nama_lengkap}</td>
                  <td style={{padding:'5px 8px',color:'var(--muted2)',fontSize:10,border:'1px solid var(--border)',background:bg}}>{r.jabatan}</td>
                  {/* Sabtu tarif - input */}
                  <td style={{border:'1px solid rgba(100,180,100,.3)',background:'rgba(198,239,206,.2)',padding:'2px 4px'}}>
                    {isViewer
                      ? <span style={{padding:'1px 4px',fontSize:11,color:'#276221'}}>{rp(r.sabtu_tarif||0)}</span>
                      : <TarifInput val={r.sabtu_tarif||0} color='#276221'
                          onChange={v=>setField(r.employee_id,'sabtu_tarif',v)}/>
                    }
                  </td>
                  {/* Sabtu hari - editable, auto dari timesheet */}
                  <td style={{border:'1px solid rgba(100,180,100,.3)',background:'rgba(198,239,206,.2)',padding:'2px 4px'}}>
                    <div style={{display:'flex',alignItems:'center',gap:3}}>
                      {isViewer
                        ? <span style={{fontSize:11,fontWeight:700,color:'#276221',padding:'2px 4px'}}>{r.sabtu_hari||0}</span>
                        : <HariInput val={r.sabtu_hari||0} color='#276221'
                          onChange={v=>setField(r.employee_id,'sabtu_hari',v)}/>
                      }
                      {!isViewer && r.sabtu_hari !== r.sabtu_hari_auto && (
                        <button onClick={()=>setField(r.employee_id,'sabtu_hari', r.sabtu_hari_auto)}
                          title="Reset ke otomatis"
                          style={{fontSize:9,padding:'1px 4px',borderRadius:4,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted)',cursor:'pointer',flexShrink:0}}>
                          ↩
                        </button>
                      )}
                    </div>
                  </td>
                  {/* Sabtu subtotal */}
                  <td style={{padding:'5px 8px',textAlign:'right',fontWeight:600,color:'#276221',fontSize:11,border:'1px solid var(--border)',background:bg}}>
                    {subtotalSabtu>0?rp(subtotalSabtu):'—'}
                  </td>
                  {/* Minggu tarif - input */}
                  <td style={{border:'1px solid rgba(58,143,224,.25)',background:'rgba(218,238,243,.2)',padding:'2px 4px'}}>
                    {isViewer
                      ? <span style={{padding:'1px 4px',fontSize:11,color:'var(--blue)'}}>{rp(r.minggu_tarif||0)}</span>
                      : <TarifInput val={r.minggu_tarif||0} color='var(--blue)'
                          onChange={v=>setField(r.employee_id,'minggu_tarif',v)}/>
                    }
                  </td>
                  {/* Minggu hari - editable, auto dari timesheet */}
                  <td style={{border:'1px solid rgba(58,143,224,.25)',background:'rgba(218,238,243,.2)',padding:'2px 4px'}}>
                    <div style={{display:'flex',alignItems:'center',gap:3}}>
                      {isViewer
                        ? <span style={{fontSize:11,fontWeight:700,color:'var(--blue)',padding:'2px 4px'}}>{r.minggu_hari||0}</span>
                        : <HariInput val={r.minggu_hari||0} color='var(--blue)'
                          onChange={v=>setField(r.employee_id,'minggu_hari',v)}/>
                      }
                      {!isViewer && r.minggu_hari !== r.minggu_hari_auto && (
                        <button onClick={()=>setField(r.employee_id,'minggu_hari', r.minggu_hari_auto)}
                          title="Reset ke otomatis"
                          style={{fontSize:9,padding:'1px 4px',borderRadius:4,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted)',cursor:'pointer',flexShrink:0}}>
                          ↩
                        </button>
                      )}
                    </div>
                  </td>
                  {/* Minggu subtotal */}
                  <td style={{padding:'5px 8px',textAlign:'right',fontWeight:600,color:'var(--blue)',fontSize:11,border:'1px solid var(--border)',background:bg}}>
                    {subtotalMinggu>0?rp(subtotalMinggu):'—'}
                  </td>
                  <td style={{padding:'5px 10px',textAlign:'right',fontWeight:700,fontSize:12,
                    color:total>0?'var(--accent)':'var(--muted)',
                    border:'1px solid var(--border)',
                    background:total>0?'rgba(232,160,32,.08)':bg}}>
                    {total>0?rp(total):'—'}
                  </td>
                  {!isViewer && (
                    <td style={{padding:'4px',textAlign:'center',border:'1px solid var(--border)',background:bg}}>
                      <button onClick={()=>removeMember(r.employee_id)}
                        style={{padding:'2px 8px',borderRadius:6,border:'1px solid rgba(224,69,69,.3)',background:'rgba(224,69,69,.08)',color:'#E04545',fontSize:11,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center'}}>
                        <Trash2 size={11}/>
                      </button>
                    </td>
                  )}
                </tr>
              );
            })}
            {displayed.length===0 && (
              <tr>
                <td colSpan={!isViewer?11:10} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>
                  {members.length===0 ? 'Belum ada anggota. Klik Tambah untuk menambahkan.' : 'Tidak ada hasil pencarian.'}
                </td>
              </tr>
            )}
          </tbody>
          <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
            <tr>
              <td colSpan={3} style={{...th1,textAlign:'right',paddingRight:10,fontSize:9}}>TOTAL ({displayed.length} karyawan)</td>
              <td colSpan={2} style={{...thB,background:'#C6EFCE',color:'#276221'}}>—</td>
              <td style={{...thB,background:'#C6EFCE',color:'#276221',fontWeight:700}}>{rp(totalSabtu)}</td>
              <td colSpan={2} style={{...thB,background:'#DAEEF3',color:'#0C4B6E'}}>—</td>
              <td style={{...thB,background:'#DAEEF3',color:'#0C4B6E',fontWeight:700}}>{rp(totalMinggu)}</td>
              <td style={{...th1,fontWeight:700,fontSize:11}}>{rp(totalAll)}</td>
              {!isViewer && <td style={th1}>—</td>}
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
}


function TabelOvertime({ dataFlat, allFlat, onOvertimeChange, token, tahun, bulan, isViewer=false }) {
  const [members,setMembers]=React.useState(()=>dataFlat.map(r=>r.employee_id));
  const [showAdd,setShowAdd]=React.useState(false);
  const [ot,setOt]=React.useState(()=>{const m={};dataFlat.forEach(r=>{m[r.employee_id]={l_sabtu:r.l_sabtu||0,l_libur:r.l_libur||0,lembur_biasa:r.lembur_biasa||0};});return m;});
  const saveTimer=React.useRef({});
  function autoSave(empId,newOt){
    clearTimeout(saveTimer.current[empId]);
    saveTimer.current[empId]=setTimeout(async()=>{
      const row=newOt[empId]||{l_sabtu:0,l_libur:0,lembur_biasa:0};
      const total=(row.l_sabtu*75000)+(row.l_libur*200000)+(row.lembur_biasa*20000);
      try{await axios.put(`/timesheet/payroll/${empId}/manual`,{tahun,bulan,lembur_biasa:row.lembur_biasa,l_sabtu:row.l_sabtu,l_libur:row.l_libur},{headers:{'X-CSRF-TOKEN':token}});}catch(e){console.error('save overtime failed',e);}
      onOvertimeChange(empId,{l_sabtu:row.l_sabtu,l_libur:row.l_libur,lembur_biasa:row.lembur_biasa,total_lembur_flat:total});
    },600);
  }
  function setField(empId,field,val){const num=Math.max(0,parseInt(val)||0);setOt(prev=>{const updated={...prev,[empId]:{...(prev[empId]||{l_sabtu:0,l_libur:0,lembur_biasa:0}),[field]:num}};autoSave(empId,updated);return updated;});}
  function removeMember(empId){setMembers(prev=>prev.filter(id=>id!==empId));setOt(prev=>({...prev,[empId]:{l_sabtu:0,l_libur:0,lembur_biasa:0}}));onOvertimeChange(empId,{l_sabtu:0,l_libur:0,lembur_biasa:0,total_lembur_flat:0});}
  function addMember(empId){if(members.includes(empId))return;setMembers(prev=>[...prev,empId]);setShowAdd(false);if(!ot[empId])setOt(prev=>({...prev,[empId]:{l_sabtu:0,l_libur:0,lembur_biasa:0}}));}
  const displayed=allFlat.filter(r=>members.includes(r.employee_id));
  const notAdded=allFlat.filter(r=>!members.includes(r.employee_id));
  const totalSabtu=displayed.reduce((s,r)=>s+(ot[r.employee_id]?.l_sabtu||0),0);
  const totalLibur=displayed.reduce((s,r)=>s+(ot[r.employee_id]?.l_libur||0),0);
  const totalBiasa=displayed.reduce((s,r)=>s+(ot[r.employee_id]?.lembur_biasa||0),0);
  const totalLembur=(totalSabtu*75000)+(totalLibur*200000)+(totalBiasa*20000);
  const thB={padding:'6px 8px',fontSize:8.5,fontWeight:700,textAlign:'center',border:'1px solid #BDBDBD',whiteSpace:'nowrap'};
  const th1={...thB,background:'#F4A010',color:'#1A0A00'}, th3={...thB,background:'#E2EFDA',color:'#276221'}, thE={...thB,background:'#FFFACD',color:'#7B5E00'};
  return (
    <div>
      <div style={{padding:'10px 16px',background:'rgba(232,160,32,.06)',borderBottom:'1px solid var(--border)',display:'flex',alignItems:'center',justifyContent:'space-between',gap:12}}>
        <div style={{fontSize:11.5,color:'var(--muted2)'}}><b style={{color:'var(--accent)',display:'inline-flex',alignItems:'center',gap:5}}><AlarmClock size={13}/> Overtime — Kelompok FLAT</b><span style={{marginLeft:10}}>L Sabtu ×75rb · L Libur ×200rb · Lembur Biasa ×20rb</span></div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <span style={{fontSize:11,color:'var(--muted)'}}>{displayed.length} anggota</span>
          {!isViewer && <button onClick={()=>setShowAdd(v=>!v)} style={{padding:'5px 12px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:11.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><Plus size={12}/> Tambah</button>}
        </div>
      </div>
      {showAdd&&(<div style={{padding:'10px 16px',background:'var(--bg3)',borderBottom:'1px solid var(--border)'}}>
        <div style={{display:'flex',flexWrap:'wrap',gap:6}}>
          {notAdded.length===0?<span style={{fontSize:11,color:'var(--muted)'}}>Semua karyawan FLAT sudah ada di daftar</span>
            :notAdded.map(r=>(<button key={r.employee_id} onClick={()=>addMember(r.employee_id)} style={{padding:'4px 10px',borderRadius:99,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:11,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>+ {r.nama_lengkap} <span style={{fontSize:9,color:'var(--muted)'}}>({r.jabatan})</span></button>))}
        </div>
      </div>)}
      <div style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 200px)'}}>
        <table style={{borderCollapse:'collapse',fontSize:11,width:'100%'}}>
          <thead style={{position:'sticky',top:0,zIndex:20}}>
            <tr>
              <th style={{...th1,width:32}}>#</th><th style={{...th1,minWidth:200,textAlign:'left'}}>NAMA KARYAWAN</th><th style={{...th1,minWidth:100,textAlign:'left'}}>JABATAN</th>
              <th style={thE}>L Sabtu <Pencil size={9} style={{verticalAlign:2}}/></th><th style={th3}>× Rp75.000</th>
              <th style={thE}>L Libur <Pencil size={9} style={{verticalAlign:2}}/></th><th style={th3}>× Rp200.000</th>
              <th style={thE}>Lembur Biasa <Pencil size={9} style={{verticalAlign:2}}/></th><th style={th3}>× Rp20.000</th>
              <th style={th1}>TOTAL LEMBUR</th><th style={{...thB,background:'var(--bg2)',color:'var(--muted)',width:50}}>Hapus</th>
            </tr>
          </thead>
          <tbody>
            {displayed.map((r,idx)=>{
              const o=ot[r.employee_id]||{l_sabtu:0,l_libur:0,lembur_biasa:0};
              const bs=o.l_sabtu*75000, bl=o.l_libur*200000, bb=o.lembur_biasa*20000, tt=bs+bl+bb;
              const bg=idx%2===0?'var(--card)':'var(--bg3)';
              const inp={width:'100%',textAlign:'center',fontSize:14,fontWeight:700,background:'transparent',border:'none',outline:'none',fontFamily:"'Outfit',sans-serif",color:'#22C97A',padding:'2px 0'};
              return (<tr key={r.employee_id}>
                <td style={{padding:'4px 8px',textAlign:'center',color:'var(--muted)',fontSize:10,border:'1px solid var(--border)',background:bg}}>{idx+1}</td>
                <td style={{padding:'5px 10px',fontWeight:600,fontSize:12,border:'1px solid var(--border)',background:bg}}>{r.nama_lengkap}</td>
                <td style={{padding:'5px 8px',color:'var(--muted2)',fontSize:10,border:'1px solid var(--border)',background:bg}}>{r.jabatan}</td>
                <td style={{border:'1px solid rgba(200,160,0,.3)',background:'rgba(255,252,180,.3)',padding:'2px 4px'}}><input type="number" min="0" max="10" value={o.l_sabtu}
                    onChange={e=>{ if(!isViewer) setField(r.employee_id,'l_sabtu',e.target.value); }}
                    disabled={isViewer}
                    style={{...inp, cursor:isViewer?'default':'text', opacity:isViewer?0.6:1}}/></td>
                <td style={{padding:'5px 8px',textAlign:'right',fontWeight:600,color:'#22C97A',fontSize:11,border:'1px solid var(--border)',background:bg}}>{bs>0?rp(bs):'—'}</td>
                <td style={{border:'1px solid rgba(200,160,0,.3)',background:'rgba(255,252,180,.3)',padding:'2px 4px'}}><input type="number" min="0" max="10" value={o.l_libur}
                  onChange={e=>{ if(!isViewer) setField(r.employee_id,'l_libur',e.target.value); }}
                  disabled={isViewer}
                  style={{...inp, color:'var(--blue)', cursor:isViewer?'default':'text', opacity:isViewer?0.6:1}}/></td>
                <td style={{padding:'5px 8px',textAlign:'right',fontWeight:600,color:'var(--blue)',fontSize:11,border:'1px solid var(--border)',background:bg}}>{bl>0?rp(bl):'—'}</td>
                <td style={{border:'1px solid rgba(200,160,0,.3)',background:'rgba(255,252,180,.3)',padding:'2px 4px'}}><input type="number" min="0" max="31" value={o.lembur_biasa}
                  onChange={e=>{ if(!isViewer) setField(r.employee_id,'lembur_biasa',e.target.value); }}
                  disabled={isViewer}
                  style={{...inp, color:'var(--muted2)', cursor:isViewer?'default':'text', opacity:isViewer?0.6:1}}/></td>
                <td style={{padding:'5px 8px',textAlign:'right',fontWeight:600,color:'var(--muted2)',fontSize:11,border:'1px solid var(--border)',background:bg}}>{bb>0?rp(bb):'—'}</td>
                <td style={{padding:'5px 10px',textAlign:'right',fontWeight:700,fontSize:12,color:tt>0?'var(--accent)':'var(--muted)',border:'1px solid var(--border)',background:tt>0?'rgba(232,160,32,.08)':bg}}>{tt>0?rp(tt):'—'}</td>
                <td style={{padding:'4px',textAlign:'center',border:'1px solid var(--border)',background:bg}}><button onClick={()=>removeMember(r.employee_id)} style={{padding:'2px 8px',borderRadius:6,border:'1px solid rgba(224,69,69,.3)',background:'rgba(224,69,69,.08)',color:'#E04545',fontSize:11,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center'}}><Trash2 size={11}/></button></td>
              </tr>);
            })}
            {displayed.length===0&&<tr><td colSpan={11} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Belum ada anggota. Klik <b style={{display:'inline-flex',alignItems:'center',gap:3}}><Plus size={11}/> Tambah</b>.</td></tr>}
          </tbody>
          <tfoot style={{position:'sticky',bottom:0,zIndex:20}}>
            <tr>
              <td colSpan={3} style={{...th1,textAlign:'right',paddingRight:10,fontSize:9}}>TOTAL OVERTIME ({displayed.length})</td>
              <td style={{...th1,textAlign:'center'}}>{totalSabtu}</td><td style={{...th3,fontWeight:700}}>{rp(totalSabtu*75000)}</td>
              <td style={{...th1,textAlign:'center'}}>{totalLibur}</td><td style={{...th3,fontWeight:700}}>{rp(totalLibur*200000)}</td>
              <td style={{...th1,textAlign:'center'}}>{totalBiasa}</td><td style={{...th3,fontWeight:700}}>{rp(totalBiasa*20000)}</td>
              <td style={{...th1,fontWeight:700,fontSize:11}}>{rp(totalLembur)}</td><td style={th1}>—</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
}

// ── MAIN ─────────────────────────────────────────────────────
export default function DataGaji({ tahun, bulan, bulan_nama, bulan_list, rows=[], ttt_items=[], project_info=null, bpjs_pct=null, ttd_list=null, salary_matrix=null }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const bpjsPct = bpjs_pct || {jht:2,pensiun:1,kes:1};
  const [selTahun,setSelTahun]=useState(tahun), [selBulan,setSelBulan]=useState(bulan);
  const [data,setData]=useState(()=>{
    const kode=(project_info?.kode||'').toLowerCase();
    const keys=ttt_items.filter(t=>t.aktif).map(t=>t.key);
    return rows.map(r=>recalc({...r,project_kode:kode},keys,bpjsPct));
  });
  const dataRef=useRef([]);
  const [search,setSearch]=useState('');
  const [isDark]=useState(()=>typeof window!=='undefined'&&localStorage.getItem('akm-theme')!=='light');
  const hasSubGroup = data.some(r => r.sub_group);
  const [subGroupTab, setSubGroupTab] = useState('all');
  const [activeTab,setActiveTab]=useState('semua');
  const [tttItems, setTttItems] = useState(ttt_items);
  const [showTttModal, setShowTttModal] = useState(false);
  const [showBpjsTtdModal, setShowBpjsTtdModal] = useState(false);
  // Komp. PWT sengaja dibuang dari daftar ini walau ke-tandai "aktif" di config TTT bawaan —
  // dia sudah punya kolom sendiri di grup Tunjangan Tetap, jadi kalau ikut masuk ke sini
  // kolomnya (dan totalnya di baris paling bawah) kehitung dobel.
  const activeTTT = tttItems.filter(t => t.aktif && t.key !== 'kompensasi_pwt');

  function handleSaveTtt(newItems){ setTttItems(newItems); }

  const currentYear = new Date().getFullYear();
  const tahunList = Array.from({length: currentYear - 2024 + 6}, (_, i) => 2024 + i);
  const token=document.querySelector('meta[name=csrf-token]')?.content;

  useEffect(()=>{
    const kode=(project_info?.kode||'').toLowerCase();
    const keys=tttItems.filter(t=>t.aktif).map(t=>t.key);
    setData(rows.map(r=>recalc({...r,project_kode:kode},keys,bpjsPct)));
  },[rows]);
  useEffect(()=>{dataRef.current=data;},[data]);

  function navigate(t,b){router.get('/timesheet/data-gaji',{tahun:t,bulan:b},{preserveState:false});}

  // Update state lokal untuk field teks — tanpa koersi ke angka & tanpa recalc gaji, beda dari updateField.
  function updateTextField(empId,field,val){
    setData(prev=>{
      const next=prev.map(r=>r.employee_id===empId?{...r,[field]:val}:r);
      dataRef.current=next;
      return next;
    });
  }

  // Field induk karyawan (tanggal masuk, status karyawan HO, PTKP, no rekening, bank) — disimpan
  // ke tabel employees / employee_ho_details lewat endpoint terpisah, BUKAN snapshot payroll per
  // bulan, supaya nyambung juga ke halaman Edit Karyawan (bukan cuma kelihatan di Data Gaji).
  const infoSaveTimerRef=useRef({});
  function scheduleInfoSave(empId,field,val){
    updateTextField(empId,field,val);
    const timerKey=`${empId}::${field}`;
    clearTimeout(infoSaveTimerRef.current[timerKey]);
    infoSaveTimerRef.current[timerKey]=setTimeout(()=>{
      axios.put(`/timesheet/payroll/${empId}/info`,{[field]:val},{headers:{'X-CSRF-TOKEN':token}})
        .catch(e=>console.error('Gagal simpan data karyawan',e));
    },500);
  }

  function updateField(empId,field,val){
    setData(prev=>{
      const next=prev.map(r=>{
        if(r.employee_id!==empId) return r;
        const newVal=typeof val==='boolean'?val:(parseFloat(val)||0);
        const keys=dataRef.current.find(x=>x.employee_id===empId)?.project_kode
          ? tttItems.filter(t=>t.aktif).map(t=>t.key) : [];
        return recalc({...r,[field]:newVal},tttItems.filter(t=>t.aktif).map(t=>t.key),bpjsPct);
      });
      dataRef.current=next;
      return next;
    });
  }

  const saveTimerRef=useRef({});
  useEffect(() => {
    return () => {
      // Jalankan semua save yang masih pending
      Object.keys(saveTimerRef.current).forEach(empId => {
        clearTimeout(saveTimerRef.current[empId]);
        doSaveRow(parseInt(empId));
      });
    };
  }, []);
  function scheduleSave(empId){
    clearTimeout(saveTimerRef.current[empId]);
    saveTimerRef.current[empId]=setTimeout(()=>doSaveRow(empId),500);
  }

  function handleOvertimeChange(empId,overtimeFields){
    const keys=tttItems.filter(t=>t.aktif).map(t=>t.key);
    setData(prev=>prev.map(r=>r.employee_id!==empId?r:recalc({...r,...overtimeFields},keys,bpjsPct)));
    scheduleSave(empId);
  }

  async function doSaveRow(empId){
    const row=dataRef.current.find(r=>r.employee_id===empId);
    if(!row) return;
    try{
      await axios.put(`/timesheet/payroll/${empId}/manual`,{
        tahun, bulan,
        gaji_pokok:            row.gaji_pokok            || 0,
        tunj_tetap:            row.tunj_tetap             || 0,
        tunj_jabatan:          row.tunj_jabatan           || 0,
        lembur_biasa:          row.lembur_biasa           || 0,
        potongan_alpa:         row.potongan_alpa          || 0,
        kekurangan_bulan_lalu: row.kekurangan_bulan_lalu  || 0,
        tunj_makan:            row.tunj_makan             || 0,
        tunj_produksi:         row.tunj_produksi          || 0,
        tunj_lapangan:         row.tunj_lapangan          || 0,
        tunj_kehadiran:        row.tunj_kehadiran         || 0,
        tunj_pulsa:            row.tunj_pulsa             || 0,
        kompensasi_kontrak:    row.kompensasi_kontrak     || 0,
        insentif:              row.insentif               || 0,
        com_day:               row.com_day                || 0,
        uang_hadir:            row.uang_hadir             || 0,
        izin:                  row.izin                   || 0,
        sakit:                 row.sakit                  || 0,
        alpa:                  row.alpa                   || 0,
        cuti:                  row.cuti                   || 0,
        ttt_custom: (() => {
          const custom = {};
          tttItems.filter(t => !t.is_default).forEach(t => {
            custom[t.key] = row[t.key] || 0;
          });
          return Object.keys(custom).length > 0 ? custom : undefined;
        })(),
      },{headers:{'X-CSRF-TOKEN':token}});
    }catch(e){console.error('Save failed',e);}
  }

  const searchLow=search.toLowerCase();
  const filterFn=r=>!search||r.nama_lengkap?.toLowerCase().includes(searchLow)||r.jabatan?.toLowerCase().includes(searchLow)||r.id_badge?.toLowerCase().includes(searchLow);

  const sgFilter = r => subGroupTab === 'all' || r.sub_group === subGroupTab || !r.sub_group;
  const dataJam   = data.filter(r => r.kelompok !== 'flat').filter(filterFn).filter(sgFilter).sort((a,b) => (a.urutan??999)-(b.urutan??999));
  const dataFlat  = data.filter(r => r.kelompok === 'flat').filter(filterFn).filter(sgFilter).sort((a,b) => (a.urutan??999)-(b.urutan??999));
  const dataSemua = data.filter(filterFn).filter(sgFilter).sort((a,b) => (a.urutan??999)-(b.urutan??999));
  const aktif    =activeTab==='semua'?dataSemua:activeTab==='jam'?dataJam:dataFlat;

  const totalKotor  =aktif.reduce((s,r)=>s+(r.gaji_kotor||0),0);
  const totalBersih =aktif.reduce((s,r)=>s+(r.gaji_bersih||0),0);
  const totalPot    =aktif.reduce((s,r)=>s+(r.potongan_jht||0)+(r.potongan_pensiun||0)+(r.potongan_kes||0)+(r.potongan_alpa||0),0);

  const isMdProject = (project_info?.tipe_gaji||'') === 'md';
  const isHoProject = (project_info?.tipe_gaji||'') === 'ho';

  const TABS = [
      {key:'semua',    label:`Semua (${dataSemua.length})`,  icon:Users,       bg:'linear-gradient(135deg,#E8A020,#A06010)'},
      ...(!isHoProject ? [{key:'jam', label:`Per Jam (${dataJam.length})`, icon:Timer, bg:'linear-gradient(135deg,#22C97A,#148050)'}] : []),
      ...(!isMdProject && !isHoProject ? [
          {key:'flat',     label:`Flat (${dataFlat.length})`,    icon:ClipboardList, bg:'linear-gradient(135deg,#3A8FE0,#1A5FA0)'},
          {key:'overtime', label:`Overtime (${dataFlat.length})`,   icon:AlarmClock, bg:'linear-gradient(135deg,#9B59B6,#6C3483)'},
      ] : []),
      {key:'panduan',  label:'Panduan Perhitungan',           icon:BookOpen, bg:'linear-gradient(135deg,#E04545,#901010)'},
  ];

  return (
    <AppLayout title="Timesheet" subtitle="Data Gaji">
      {showTttModal && <TttConfigModal items={tttItems} onSave={handleSaveTtt} onClose={()=>setShowTttModal(false)}/>}
      {showBpjsTtdModal && <BpjsTtdConfigModal onSaved={()=>router.reload({only:['bpjs_pct','ttd_list']})} onClose={()=>setShowBpjsTtdModal(false)}/>}

      <div style={{display:'flex',gap:10,marginBottom:16,flexWrap:'wrap',alignItems:'center'}}>
        <select value={selBulan} onChange={e=>{setSelBulan(+e.target.value);navigate(selTahun,e.target.value);}}
          style={{padding:'7px 11px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:12.5,fontFamily:"'Outfit',sans-serif"}}>
          {Object.entries(bulan_list).map(([k,v])=><option key={k} value={k}>{v}</option>)}
        </select>
        <select value={selTahun} onChange={e=>{setSelTahun(+e.target.value);navigate(e.target.value,selBulan);}}
          style={{padding:'7px 11px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:12.5,fontFamily:"'Outfit',sans-serif"}}>
          {tahunList.map(y=><option key={y} value={y}>{y}</option>)}
        </select>
        <div style={{position:'relative',flex:1,minWidth:180,maxWidth:280}}>
          <span style={{position:'absolute',left:9,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={13}/></span>
          <input className="search-input" type="text" value={search} onChange={e=>setSearch(e.target.value)}
            placeholder="Cari nama / jabatan / badge..." style={{paddingLeft:28,width:'100%'}}/>
          {search && <span onClick={()=>setSearch('')} style={{position:'absolute',right:9,top:'50%',transform:'translateY(-50%)',cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={13}/></span>}
        </div>
        <div style={{marginLeft:'auto',display:'flex',gap:8,alignItems:'center'}}>
        <a href={`/timesheet/data-gaji/export?tahun=${tahun}&bulan=${bulan}`}
          style={{padding:'8px 14px',borderRadius:8,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:600,textDecoration:'none',display:'flex',alignItems:'center',gap:6}}>
          <Download size={13}/> Export Excel
        </a>
        <a href={`/timesheet/slip-gaji?tahun=${tahun}&bulan=${bulan}`}
          style={{padding:'8px 14px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,fontWeight:600,textDecoration:'none'}}>
          Slip Gaji
        </a>
        </div>
      </div>

      <div className="stat-grid-4" style={{display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:12,marginBottom:16}}>
        {[
          {l:'Total Karyawan',    v:`${aktif.length} orang`, c:'var(--blue)'},
          {l:'Total Gaji Kotor',  v:rpFmt(totalKotor),       c:'var(--accent)'},
          {l:'Total Potongan',    v:rpFmt(totalPot),          c:'#E04545'},
          {l:'Total Gaji Bersih', v:rpFmt(totalBersih),       c:'#22C97A'},
        ].map((s,i)=>(
          <div key={i} className="panel" style={{padding:'12px 16px'}}>
            <div style={{fontSize:10,color:'var(--muted)',textTransform:'uppercase',letterSpacing:'.06em',marginBottom:4}}>{s.l}</div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:16,fontWeight:700,color:s.c}}>{s.v}</div>
          </div>
        ))}
      </div>

      <div className="panel" style={{overflow:'hidden',display:'flex',flexDirection:'column'}}>
        <div className="panel-head" style={{flexShrink:0}}>
          <div style={{display:'flex',gap:6,alignItems:'center',flexWrap:'wrap'}}>
            <div className="panel-title">Data Gaji {bulan_nama} {tahun}</div>
            <div style={{display:'flex',gap:4,marginLeft:16,flexWrap:'wrap'}}>
              {TABS.map(t=>(
                <button key={t.key} onClick={()=>setActiveTab(t.key)}
                  style={{padding:'5px 14px',borderRadius:99,border:'none',fontSize:11.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
                    display:'flex',alignItems:'center',gap:6,
                    background:activeTab===t.key?t.bg:'var(--bg3)',
                    color:activeTab===t.key?'#fff':'var(--muted2)'}}>
                  <t.icon size={13}/> {t.label}
                </button>
              ))}
            </div>
          </div>
          {activeTab!=='panduan' && (
            <div style={{display:'flex',alignItems:'center',gap:10}}>
              <button onClick={()=>setShowTttModal(true)}
                style={{padding:'5px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--card)',
                  color:'var(--muted2)',fontSize:11.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
                  display:'flex',alignItems:'center',gap:5}}>
                TTT
                <span style={{fontSize:10,background:'rgba(232,160,32,.15)',color:'var(--accent)',padding:'1px 6px',borderRadius:99,fontWeight:700}}>
                  {activeTTT.length} aktif
                </span>
              </button>
              <button onClick={()=>setShowBpjsTtdModal(true)}
                style={{padding:'5px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--card)',
                  color:'var(--muted2)',fontSize:11.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
                  display:'flex',alignItems:'center',gap:5}}>
                BPJS & TTD
                <span style={{fontSize:10,background:'rgba(232,160,32,.15)',color:'var(--accent)',padding:'1px 6px',borderRadius:99,fontWeight:700}}>
                  JHT {bpjsPct.jht}% P {bpjsPct.pensiun}% Kes {bpjsPct.kes}%
                </span>
              </button>
              <span style={{fontSize:10.5,color:'var(--muted)'}}>
                Sel <span style={{background:'rgba(255,252,200,.6)',padding:'0 4px',borderRadius:3,border:'1px solid #E8C030'}}>kuning</span> = klik untuk edit
              </span>
            </div>
          )}
        </div>

        {hasSubGroup && (() => {
          const subGroupOpts = SUB_GROUP_OPTIONS[(project_info?.kode||'').toLowerCase()] || [];
          const SG_GRADIENTS = [
            'linear-gradient(135deg,#E8A020,#A06010)',
            'linear-gradient(135deg,#3A8FE0,#1A5FA0)',
            'linear-gradient(135deg,#22C97A,#148050)',
            'linear-gradient(135deg,#9B59B6,#6B3D80)',
          ];
          const tabs = [
            { key:'all', label:'Semua', icon:null },
            ...subGroupOpts.map((o, i) => ({ key:o.value, label:o.label, icon:o.icon, gradient: SG_GRADIENTS[i % SG_GRADIENTS.length] })),
          ];
          return (
            <div style={{display:'flex',gap:4,padding:'8px 16px',borderBottom:'1px solid var(--border)',background:'var(--bg3)',alignItems:'center'}}>
              <span style={{fontSize:11,color:'var(--muted)',marginRight:4}}>Sub-Group:</span>
              {tabs.map(t => (
                <button key={t.key} onClick={() => setSubGroupTab(t.key)}
                  style={{
                    padding:'4px 12px', borderRadius:99,
                    fontSize:11.5, fontWeight:600, cursor:'pointer',
                    fontFamily:"'Outfit',sans-serif",
                    display:'inline-flex', alignItems:'center', gap:5,
                    background: subGroupTab===t.key ? (t.gradient || 'var(--bg2)') : 'var(--bg3)',
                    color: subGroupTab===t.key && t.key!=='all' ? '#fff' : subGroupTab===t.key ? 'var(--text)' : 'var(--muted2)',
                    border: subGroupTab===t.key ? 'none' : '1px solid var(--border)',
                  }}>
                  {t.icon && <t.icon size={12}/>} {t.label}
                </button>
              ))}
            </div>
          );
        })()}
        {activeTab==='semua' && (
          isHoProject
            ? <TabelHo data={dataSemua} activeTTT={activeTTT} onEdit={updateField} onScheduleSave={scheduleSave} onEditInfo={scheduleInfoSave} isDark={isDark} bpjsPct={bpjsPct} salaryMatrix={salary_matrix} bulanNama={bulan_nama} tahun={tahun} token={token}/>
            : <TabelGabung data={dataSemua} activeTTT={activeTTT} onEdit={updateField} onScheduleSave={scheduleSave} isDark={isDark} projectKode={(project_info?.kode||'').toLowerCase()} isMd={(project_info?.tipe_gaji||'')==='md'} bpjsPct={bpjsPct}/>
        )}
        {activeTab==='jam' && <TabelPerJam data={dataJam} activeTTT={activeTTT} onEdit={updateField} onScheduleSave={scheduleSave} isDark={isDark} projectKode={(project_info?.kode||'').toLowerCase()} isMd={(project_info?.tipe_gaji||'')==='md'} bpjsPct={bpjsPct}/>}
        {activeTab==='flat'     && <TabelFlat     data={dataFlat}  activeTTT={activeTTT} onEdit={updateField} onScheduleSave={scheduleSave} isDark={isDark} projectKode={(project_info?.kode||'').toLowerCase()} bpjsPct={bpjsPct}/>}
        {activeTab==='overtime' && (
          ['khawista', 'nk'].includes(project_info?.kode?.toLowerCase())
            ? <TabelOvertimeCustom tahun={tahun} bulan={bulan} token={token} isViewer={isViewer} subGroup={subGroupTab} searchQuery={search} flatIds={data.filter(r=>r.kelompok==='flat').map(r=>r.employee_id)}/>
            : <TabelOvertime dataFlat={dataFlat} allFlat={dataFlat} onOvertimeChange={handleOvertimeChange} token={token} tahun={tahun} bulan={bulan} isViewer={isViewer}/>
        )}
        {activeTab==='panduan' && <TabPanduan
          projectIds={(() => {
            const u = auth?.user;
            if (!u) return [];
            // Super-admin & viewer lihat semua project (termasuk HO) — bukan cuma yang tidak punya project_id.
            if (u.can?.is_super_admin || u.can?.is_viewer) return [1,2,3,4,5,6];
            if (u.project_ids && u.project_ids.length > 0) return u.project_ids;
            if (u.project_id) return [u.project_id];
            return [];
          })()}
          isSuperAdmin={auth?.user?.can?.is_super_admin || false}
        />}
      </div>

      <div style={{fontSize:10.5,color:'var(--muted)',marginTop:10,display:'flex',gap:16,flexWrap:'wrap'}}>
        <span>Alpa/Prorata = (Gapok+Tunj) / 25 x (Izin + Alpa)</span>
        <span>Kompensasi PWT & Upah Penuh dihitung otomatis saat Gapok/Tunj diubah</span>
        <span>Konfigurasi kolom TTT via tombol TTT - tersimpan ke database per project</span>
      </div>
    </AppLayout>
  );
}