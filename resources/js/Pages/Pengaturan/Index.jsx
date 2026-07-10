// resources/js/Pages/Pengaturan/Index.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';

// ── CONFIRM MODAL (ganti window.confirm) ────────────────────
function ConfirmModal({ title, message, confirmLabel, confirmColor='#E04545', confirmBg='rgba(224,69,69,.12)', onConfirm, onClose }) {
  return (
    <div style={{position:'fixed',inset:0,zIndex:500,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(400px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden'}}>
        {/* Header strip */}
        <div style={{height:4,background:`linear-gradient(90deg,${confirmColor},${confirmColor}88)`}}/>
        <div style={{padding:'20px 22px 16px'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,marginBottom:8}}>{title}</div>
          <div style={{fontSize:13,color:'var(--muted2)',lineHeight:1.6}}>{message}</div>
        </div>
        <div style={{display:'flex',gap:8,justifyContent:'flex-end',padding:'12px 22px 18px'}}>
          <button onClick={onClose} style={{
            padding:'8px 18px',borderRadius:8,border:'1px solid var(--border)',
            background:'var(--bg3)',color:'var(--muted2)',
            fontSize:12.5,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
          }}>Batal</button>
          <button onClick={()=>{onConfirm();onClose();}} style={{
            padding:'8px 20px',borderRadius:8,border:`1px solid ${confirmColor}44`,
            background:confirmBg,color:confirmColor,
            fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
          }}>{confirmLabel}</button>
        </div>
      </div>
    </div>
  );
}

const ROLE_COLOR = {
  'super-admin':  { bg:'rgba(224,69,69,.12)',   color:'#E04545' },
  'hr-staff':     { bg:'rgba(232,160,32,.12)',  color:'var(--accent)' },
  'manager':      { bg:'rgba(58,143,224,.12)',  color:'var(--blue)' },
  'project-user': { bg:'rgba(34,201,122,.12)',  color:'#22C97A' },
  'viewer':       { bg:'rgba(128,128,128,.12)', color:'var(--muted2)' },
};
const ACTION_COLOR = {
  'login':    { bg:'rgba(34,201,122,.1)',  color:'#22C97A' },
  'logout':   { bg:'rgba(128,128,128,.1)',color:'var(--muted2)' },
  'create':   { bg:'rgba(58,143,224,.1)', color:'var(--blue)' },
  'update':   { bg:'rgba(232,160,32,.1)', color:'var(--accent)' },
  'delete':   { bg:'rgba(224,69,69,.1)',  color:'#E04545' },
  'upload':   { bg:'rgba(155,89,182,.1)', color:'#9B59B6' },
  'download': { bg:'rgba(34,201,122,.1)', color:'#22C97A' },
};

const INP = {
  background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
  borderRadius:8, padding:'8px 11px', fontSize:12.5,
  fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
};

// ── MODAL USER ──────────────────────────────────────────────
function UserModal({ mode, user, roles, projects, onClose }) {
  const [form, setForm] = useState({
    name:       user?.name       || '',
    email:      user?.email      || '',
    password:   '',
    role:       user?.role       || roles[0] || '',
    project_id: user?.project_id || '',
  });
  const [loading, setLoading] = useState(false);
  const [errors,  setErrors]  = useState({});

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    const url    = mode==='add' ? '/pengaturan/users' : `/pengaturan/users/${user.id}`;
    const method = mode==='add' ? 'post' : 'put';
    router[method](url, {...form, project_id: form.project_id||null}, {
      onSuccess: ()=>{ setLoading(false); onClose(); },
      onError:   (err)=>{ setLoading(false); setErrors(err); },
    });
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(460px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>
            {mode==='add'?'➕ Tambah User':'✏️ Edit User'}
          </div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:13}}>
            {[
              {label:'Nama Lengkap *', key:'name',     type:'text',     ph:'Admin HR'},
              {label:'Email *',        key:'email',    type:'email',    ph:'hr@akm-wur.com'},
              {label:mode==='add'?'Password *':'Password Baru (kosongkan jika tidak ganti)', key:'password', type:'password', ph:'min. 6 karakter'},
            ].map(f=>(
              <div key={f.key}>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>{f.label}</label>
                <input type={f.type} style={{...INP,borderColor:errors[f.key]?'#E04545':'var(--border)'}}
                  value={form[f.key]} placeholder={f.ph}
                  onChange={e=>setForm(p=>({...p,[f.key]:e.target.value}))} />
                {errors[f.key]&&<div style={{fontSize:10.5,color:'#E04545',marginTop:3}}>⚠️ {errors[f.key]}</div>}
              </div>
            ))}
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Role *</label>
              <select style={INP} value={form.role} onChange={e=>setForm(p=>({...p,role:e.target.value}))}>
                {roles.map(r=><option key={r} value={r}>{r}</option>)}
              </select>
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Project</label>
              <select style={INP} value={form.project_id||''} onChange={e=>setForm(p=>({...p,project_id:e.target.value}))}>
                <option value="">— Semua Project (Super Admin) —</option>
                {projects.map(p=><option key={p.id} value={p.id}>{p.nama}</option>)}
              </select>
              <div style={{fontSize:10,color:'var(--muted)',marginTop:3}}>Kosongkan untuk super admin yang bisa akses semua project</div>
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?'⏳...':mode==='add'?'➕ Tambah':'💾 Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL RESET PASSWORD (Super Admin) ──────────────────────
function ResetPasswordModal({ user, onClose }) {
  const [form, setForm] = useState({ password:'', password_confirmation:'' });
  const [loading, setLoading] = useState(false);
  const [errors,  setErrors]  = useState({});

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    router.post(`/pengaturan/users/${user.id}/reset-password`, form, {
      onSuccess: ()=>{ setLoading(false); onClose(); },
      onError:   (err)=>{ setLoading(false); setErrors(err); },
    });
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(420px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}><i className="fa-solid fa-key"/> Reset Password — {user.name}</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:13}}>
            <div style={{background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',borderRadius:8,padding:'10px 12px',fontSize:12,color:'var(--accent)'}}>
              ⚠️ Password baru akan langsung berlaku. Beritahu user password barunya.
            </div>
            {[
              {label:'Password Baru *',       key:'password',              ph:'min. 6 karakter'},
              {label:'Konfirmasi Password *',  key:'password_confirmation', ph:'ulangi password baru'},
            ].map(f=>(
              <div key={f.key}>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>{f.label}</label>
                <input type="password" style={{...INP,borderColor:errors[f.key]?'#E04545':'var(--border)'}}
                  value={form[f.key]} placeholder={f.ph}
                  onChange={e=>setForm(p=>({...p,[f.key]:e.target.value}))} />
                {errors[f.key]&&<div style={{fontSize:10.5,color:'#E04545',marginTop:3}}>⚠️ {errors[f.key]}</div>}
              </div>
            ))}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E04545,#A01010)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?'⏳...':'🔑 Reset Password'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL GANTI PASSWORD SENDIRI ────────────────────────────
function ChangePasswordModal({ onClose }) {
  const [form, setForm] = useState({ current_password:'', password:'', password_confirmation:'' });
  const [loading, setLoading] = useState(false);
  const [errors,  setErrors]  = useState({});

  function submit(e) {
    e.preventDefault();
    setLoading(true); setErrors({});
    router.post('/pengaturan/change-password', form, {
      onSuccess: ()=>{ setLoading(false); onClose(); },
      onError:   (err)=>{ setLoading(false); setErrors(err); },
    });
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(420px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>🔒 Ganti Password</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:13}}>
            {[
              {label:'Password Lama *',        key:'current_password',      ph:'password yang sekarang'},
              {label:'Password Baru *',         key:'password',              ph:'min. 6 karakter'},
              {label:'Konfirmasi Password *',   key:'password_confirmation', ph:'ulangi password baru'},
            ].map(f=>(
              <div key={f.key}>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>{f.label}</label>
                <input type="password" style={{...INP,borderColor:errors[f.key]?'#E04545':'var(--border)'}}
                  value={form[f.key]} placeholder={f.ph}
                  onChange={e=>setForm(p=>({...p,[f.key]:e.target.value}))} />
                {errors[f.key]&&<div style={{fontSize:10.5,color:'#E04545',marginTop:3}}>⚠️ {errors[f.key]}</div>}
              </div>
            ))}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?'⏳...':'🔒 Simpan Password'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL JABATAN ────────────────────────────────────────────
function JabatanModal({ mode, position, onClose }) {
  const [nama,    setNama]    = useState(position?.nama_jabatan||'');
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');
  function submit(e) {
    e.preventDefault();
    if (!nama.trim()) { setError('Nama jabatan wajib diisi.'); return; }
    setLoading(true); setError('');
    const url    = mode==='add'?'/pengaturan/positions':`/pengaturan/positions/${position.id}`;
    const method = mode==='add'?'post':'put';
    router[method](url, {nama_jabatan:nama}, {
      onSuccess:()=>{ setLoading(false); onClose(); },
      onError:(err)=>{ setLoading(false); setError(err.nama_jabatan||'Gagal menyimpan.'); },
    });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(380px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>{mode==='add'?'➕ Tambah Jabatan':'✏️ Edit Jabatan'}</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px'}}>
            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Nama Jabatan *</label>
            <input type="text" style={{...INP,borderColor:error?'#E04545':'var(--border)'}}
              value={nama} onChange={e=>setNama(e.target.value)} placeholder="cth: Driver Dump Truck" autoFocus />
            {error&&<div style={{fontSize:10.5,color:'#E04545',marginTop:4}}>⚠️ {error}</div>}
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading?'⏳...':mode==='add'?'➕ Tambah':'💾 Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════
export default function PengaturanIndex({ users=[], roles=[], projects=[], positions=[], logs=[] }) {
  const { auth }     = usePage().props;
  const isSuperAdmin = auth?.user?.can?.is_super_admin;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [activeTab,      setActiveTab]      = useState(isSuperAdmin ? 'users' : 'jabatan');
  const [modal,          setModal]          = useState(null);
  const [resetPwModal,   setResetPwModal]   = useState(null);
  const [changePwModal,  setChangePwModal]  = useState(false);
  const [jabatanModal,   setJabatanModal]   = useState(null);
  const [confirmModal,   setConfirmModal]   = useState(null);
  const [showPw, setShowPw] = useState({});
  // confirmModal: { title, message, confirmLabel, confirmColor, confirmBg, onConfirm }

  // Log state
  const [logSearch,   setLogSearch]   = useState('');
  const [logPage,     setLogPage]     = useState(1);
  const [logModule,   setLogModule]   = useState('');
  const [logAction,   setLogAction]   = useState('');
  const LOG_PER_PAGE = 50;

  function toggleActive(user) {
    setConfirmModal({
      title: user.is_active ? `Nonaktifkan User` : `Aktifkan User`,
      message: user.is_active
        ? `User "${user.name}" akan dinonaktifkan dan tidak bisa login. Yakin?`
        : `User "${user.name}" akan diaktifkan kembali. Yakin?`,
      confirmLabel: user.is_active ? '🚫 Nonaktifkan' : '✅ Aktifkan',
      confirmColor: user.is_active ? '#E04545' : '#22C97A',
      confirmBg: user.is_active ? 'rgba(224,69,69,.12)' : 'rgba(34,201,122,.12)',
      onConfirm: () => router.post(`/pengaturan/users/${user.id}/toggle`, {}, {preserveScroll:true}),
    });
  }
  function handleLogout() {
    setConfirmModal({
      title: 'Logout',
      message: 'Kamu akan keluar dari sistem. Yakin mau logout?',
      confirmLabel: 'Ya, Logout',
      confirmColor: '#E04545',
      confirmBg: 'rgba(224,69,69,.12)',
      onConfirm: () => router.post('/logout'),
    });
  }

  // Filter + paginate logs
  const modules = [...new Set(logs.map(l=>l.module))].sort();
  const actions  = [...new Set(logs.map(l=>l.action))].sort();

  const filteredLogs = logs.filter(l => {
    const q = logSearch.toLowerCase();
    const matchSearch = !q ||
      l.user_name.toLowerCase().includes(q) ||
      l.module.toLowerCase().includes(q) ||
      l.action.toLowerCase().includes(q) ||
      (l.target_name||'').toLowerCase().includes(q) ||
      (l.description||'').toLowerCase().includes(q);
    const matchModule = !logModule || l.module === logModule;
    const matchAction = !logAction || l.action === logAction;
    return matchSearch && matchModule && matchAction;
  });

  const totalLogPages = Math.ceil(filteredLogs.length / LOG_PER_PAGE) || 1;
  const pagedLogs     = filteredLogs.slice((logPage-1)*LOG_PER_PAGE, logPage*LOG_PER_PAGE);

  const TABS = [
    ...(isSuperAdmin ? [{key:'users', label:'👤 Manajemen User', count:users.length}] : []),
    {key:'jabatan',        label:'💼 Jabatan',        count:positions.length},
    {key:'log',            label:'📋 Log Aktivitas',   count:logs.length},
  ];

  const tabBtn = (active) => ({
    padding:'8px 18px', borderRadius:8, cursor:'pointer',
    fontSize:13, fontWeight:600, transition:'all .15s',
    display:'flex', alignItems:'center', gap:8,
    background: active?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)',
    color:       active?'#0C0F14':'var(--muted)',
    border:`1px solid ${active?'transparent':'var(--border)'}`,
  });

  return (
    <AppLayout title="Pengaturan" subtitle="Sistem">
      {modal        && <UserModal mode={modal.mode} user={modal.user} roles={roles} projects={projects} onClose={()=>setModal(null)}/>}
      {resetPwModal && <ResetPasswordModal user={resetPwModal} onClose={()=>setResetPwModal(null)}/>}
      {changePwModal&& <ChangePasswordModal onClose={()=>setChangePwModal(false)}/>}
      {confirmModal && (
        <ConfirmModal
          title={confirmModal.title}
          message={confirmModal.message}
          confirmLabel={confirmModal.confirmLabel}
          confirmColor={confirmModal.confirmColor}
          confirmBg={confirmModal.confirmBg}
          onConfirm={confirmModal.onConfirm}
          onClose={()=>setConfirmModal(null)}
        />
      )}

      {/* Header */}
      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:20,flexWrap:'wrap',gap:10}}>
        <div>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:16,fontWeight:700}}>⚙️ Pengaturan Sistem</div>
          <div style={{fontSize:12,color:'var(--muted)',marginTop:2}}>Manajemen user, jabatan, dan log aktivitas</div>
        </div>
        <div style={{display:'flex',gap:8}}>
          <button onClick={()=>setChangePwModal(true)} style={{
            padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',
            background:'var(--card)',color:'var(--text)',
            fontSize:12.5,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
          }}>
            🔒 Ganti Password
          </button>
          <button onClick={handleLogout} style={{
            padding:'8px 16px',borderRadius:8,border:'1px solid rgba(224,69,69,.3)',
            background:'rgba(224,69,69,.08)',color:'#E04545',
            fontSize:12.5,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
          }}>
            🚪 Logout
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div style={{display:'flex',gap:4,marginBottom:20,flexWrap:'wrap'}}>
        {TABS.map(t=>(
          <div key={t.key} onClick={()=>setActiveTab(t.key)} style={tabBtn(activeTab===t.key)}>
            {t.label}
            <span style={{
              background:activeTab===t.key?'rgba(0,0,0,.2)':'var(--bg3)',
              color:activeTab===t.key?'#0C0F14':'var(--muted2)',
              fontSize:10.5,fontWeight:700,padding:'1px 7px',borderRadius:99,
            }}>{t.count}</span>
          </div>
        ))}
      </div>

      {/* ── TAB USERS ── */}
      {activeTab==='users' && (
        <div className="panel">
          <div className="panel-head">
            <div className="panel-title">👤 Daftar User</div>
            <button onClick={()=>setModal({mode:'add'})} style={{
              padding:'6px 14px',borderRadius:7,border:'none',
              background:'linear-gradient(135deg,#E8A020,#A06010)',
              color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",
            }}>➕ Tambah User</button>
          </div>
          <div style={{padding:'14px 16px'}}>
            <div style={{overflowX:'auto'}}>
            <table className="kar-table">
                  <thead>
                    <tr>
                      <th>Nama</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th>Project</th>
                      <th>Password</th>
                      <th>Terdaftar</th>
                      <th>Last Login</th>
                      <th>Status</th>
                      <th style={{textAlign:'center'}}>Aksi</th>
                    </tr>
                  </thead>
              <tbody>
                {users.map((u,i)=>{
                  const rc   = ROLE_COLOR[u.role]||ROLE_COLOR['viewer'];
                  const isSelf = auth?.user?.id===u.id;
                  return (
                    <tr key={i} style={!u.is_active?{opacity:.5}:{}}
                      onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                      onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                      <td style={{fontWeight:600}}>
                        {u.name}
                        {isSelf&&<span style={{marginLeft:6,fontSize:9,background:'rgba(34,201,122,.15)',color:'#22C97A',padding:'1px 6px',borderRadius:99,fontWeight:700}}>KAMU</span>}
                      </td>
                      <td style={{fontSize:12,color:'var(--muted2)'}}>{u.email}</td>
                      <td><span style={{background:rc.bg,color:rc.color,padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{u.role}</span></td>
                      <td style={{fontSize:12,color:'var(--muted2)'}}>{u.project_nama}</td>
                      <td>
                        <div style={{display:'flex',alignItems:'center',gap:5}}>
                          <span style={{fontSize:12,fontFamily:'monospace',color:'var(--text)',letterSpacing:showPw[u.id]?'normal':'2px'}}>
                            {showPw[u.id] ? (u.plain_password||'—') : '••••••'}
                          </span>
                          <button onClick={()=>setShowPw(p=>({...p,[u.id]:!p[u.id]}))}
                            style={{padding:'1px 6px',borderRadius:4,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted)',fontSize:10,cursor:'pointer',fontFamily:"'Outfit',sans-serif",flexShrink:0}}>
                            <i className={`fa-solid ${showPw[u.id]?'fa-eye-slash':'fa-eye'}`}/>
                          </button>
                        </div>
                      </td>
                      <td style={{fontSize:12,color:'var(--muted2)'}}>{u.created_at}</td>
                      <td style={{fontSize:12,color:'var(--muted2)'}}>{u.last_login}</td>
                      <td>
                        <span style={{
                          background:u.is_active?'rgba(34,201,122,.1)':'rgba(128,128,128,.1)',
                          color:u.is_active?'#22C97A':'var(--muted2)',
                          padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600,
                        }}>{u.is_active?'Aktif':'Nonaktif'}</span>
                      </td>
                      <td>
                        <div style={{display:'flex',gap:4,justifyContent:'center',flexWrap:'wrap'}}>
                          <button onClick={()=>setModal({mode:'edit',user:u})}
                            style={{padding:'3px 8px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            <i className="fa-solid fa-pen"/>
                          </button>
                          {!isSelf&&(
                            <button onClick={()=>setResetPwModal(u)}
                              style={{padding:'3px 8px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(58,143,224,.12)',color:'var(--blue)',border:'1px solid rgba(58,143,224,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                              🔑
                            </button>
                          )}
                          {!isSelf&&(
                            <button onClick={()=>toggleActive(u)}
                              style={{padding:'3px 8px',borderRadius:6,fontSize:11,fontWeight:600,
                                background:u.is_active?'rgba(224,69,69,.1)':'rgba(34,201,122,.1)',
                                color:u.is_active?'#E04545':'#22C97A',
                                border:`1px solid ${u.is_active?'rgba(224,69,69,.2)':'rgba(34,201,122,.2)'}`,
                                cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                              <i className={`fa-solid ${u.is_active?'fa-ban':'fa-check'}`}/>
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            </div>
          </div>
        </div>
      )}

      {/* ── TAB JABATAN ── */}
      {activeTab==='jabatan'&&(
        <>
          {jabatanModal&&<JabatanModal mode={jabatanModal.mode} position={jabatanModal.position} onClose={()=>setJabatanModal(null)}/>}
          <div className="panel">
            <div className="panel-head">
              <div className="panel-title">💼 Daftar Jabatan</div>
              {!isViewer && <button onClick={()=>setJabatanModal({mode:'add'})} style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>➕ Tambah Jabatan</button>}
            </div>
            <div style={{padding:'14px 16px'}}>
              <div style={{overflowX:'auto'}}>
              <table className="kar-table">
                <thead><tr><th>Nama Jabatan</th><th style={{textAlign:'center'}}>Jumlah Karyawan</th><th style={{textAlign:'center'}}>Aksi</th></tr></thead>
                <tbody>
                  {positions.map((p,i)=>(
                    <tr key={i}
                      onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                      onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                      <td style={{fontWeight:500}}>{p.nama_jabatan}</td>
                      <td style={{textAlign:'center'}}>
                        <span style={{background:p.employees_count>0?'rgba(58,143,224,.1)':'var(--bg3)',color:p.employees_count>0?'var(--blue)':'var(--muted)',padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{p.employees_count} karyawan</span>
                      </td>
                      <td>
                        <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                          {!isViewer && <button onClick={()=>setJabatanModal({mode:'edit',position:p})} style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>✏️</button>}
                          {!isViewer && p.employees_count===0&&(
                            <button onClick={()=>setConfirmModal({
                              title: '🗑️ Hapus Jabatan',
                              message: `Jabatan "${p.nama_jabatan}" akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`,
                              confirmLabel: '🗑️ Hapus Permanen',
                              confirmColor: '#E04545',
                              confirmBg: 'rgba(224,69,69,.12)',
                              onConfirm: () => router.delete(`/pengaturan/positions/${p.id}`,{preserveScroll:true}),
                            })}
                              style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>🗑️</button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                  {positions.length===0&&<tr><td colSpan={3} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Belum ada jabatan</td></tr>}
                </tbody>
              </table>
              </div>
            </div>
          </div>
        </>
      )}

      {/* ── TAB LOG AKTIVITAS ── */}
      {activeTab==='log'&&(
        <div className="panel">
          <div className="panel-head">
            <div className="panel-title">
              📋 Log Aktivitas
              <span style={{marginLeft:8,fontSize:11,fontWeight:400,color:'var(--muted)'}}>
                {filteredLogs.length} entri {!isSuperAdmin&&'(project kamu)'}
              </span>
            </div>
          </div>
          <div style={{padding:'14px 16px'}}>
            {/* Filter bar */}
            <div style={{display:'flex',gap:8,marginBottom:14,flexWrap:'wrap'}}>
              <div style={{position:'relative',flex:1,minWidth:200}}>
                <span style={{position:'absolute',left:10,top:'50%',transform:'translateY(-50%)',fontSize:14,color:'var(--muted)'}}>🔍</span>
                <input type="text" value={logSearch} placeholder="Cari user, modul, aksi, target..."
                  onChange={e=>{setLogSearch(e.target.value);setLogPage(1);}}
                  style={{...INP,paddingLeft:32}} />
              </div>
              <select value={logModule} onChange={e=>{setLogModule(e.target.value);setLogPage(1);}}
                style={{...INP,width:'auto',minWidth:140}}>
                <option value="">Semua Modul</option>
                {modules.map(m=><option key={m} value={m}>{m}</option>)}
              </select>
              <select value={logAction} onChange={e=>{setLogAction(e.target.value);setLogPage(1);}}
                style={{...INP,width:'auto',minWidth:130}}>
                <option value="">Semua Aksi</option>
                {actions.map(a=><option key={a} value={a}>{a}</option>)}
              </select>
              {(logSearch||logModule||logAction)&&(
                <button onClick={()=>{setLogSearch('');setLogModule('');setLogAction('');setLogPage(1);}}
                  style={{padding:'7px 14px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                  ✕ Reset
                </button>
              )}
            </div>

            {/* Tabel */}
            <div style={{overflowX:'auto'}}>
              <table className="kar-table">
                <thead>
                  <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    {isSuperAdmin&&<th>Project</th>}
                    <th>Aksi</th>
                    <th>Modul</th>
                    <th>Target</th>
                    <th>Keterangan</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedLogs.map((l,i)=>{
                    const ac=ACTION_COLOR[l.action]||{bg:'rgba(128,128,128,.1)',color:'var(--muted2)'};
                    return (
                      <tr key={i}
                        onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                        onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                        <td style={{fontSize:11,color:'var(--muted2)',whiteSpace:'nowrap'}}>{l.created_at}</td>
                        <td style={{fontWeight:500,fontSize:12}}>{l.user_name}</td>
                        {isSuperAdmin&&<td style={{fontSize:11,color:'var(--muted2)'}}>{l.user_project}</td>}
                        <td>
                          <span style={{background:ac.bg,color:ac.color,padding:'2px 8px',borderRadius:99,fontSize:11,fontWeight:600,textTransform:'capitalize'}}>{l.action}</span>
                        </td>
                        <td style={{fontSize:12,color:'var(--muted2)'}}>{l.module}</td>
                        <td style={{fontSize:12}}>{l.target_name||'—'}</td>
                        <td title={l.description||''}
                          style={{fontSize:11.5,color:'var(--muted)',maxWidth:200,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap',cursor:'default'}}>
                          {l.description||'—'}
                        </td>
                        <td style={{fontSize:11,fontFamily:'monospace',color:'var(--muted2)'}}>{l.ip_address||'—'}</td>
                      </tr>
                    );
                  })}
                  {pagedLogs.length===0&&(
                    <tr><td colSpan={isSuperAdmin?8:7} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Tidak ada log yang sesuai filter</td></tr>
                  )}
                </tbody>
              </table>
            </div>

            {/* Pagination */}
            {totalLogPages > 1 && (
              <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginTop:14,flexWrap:'wrap',gap:8}}>
                <div style={{fontSize:12,color:'var(--muted)'}}>
                  Halaman {logPage} dari {totalLogPages} · {filteredLogs.length} entri
                </div>
                <div style={{display:'flex',gap:4}}>
                  <button disabled={logPage===1} onClick={()=>setLogPage(1)}
                    style={{padding:'4px 10px',borderRadius:6,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:logPage===1?'not-allowed':'pointer',opacity:logPage===1?.4:1}}>«</button>
                  <button disabled={logPage===1} onClick={()=>setLogPage(p=>p-1)}
                    style={{padding:'4px 10px',borderRadius:6,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:logPage===1?'not-allowed':'pointer',opacity:logPage===1?.4:1}}>‹ Prev</button>
                  {Array.from({length:Math.min(5,totalLogPages)},(_,i)=>{
                    let page = logPage<=3 ? i+1 : logPage+i-2;
                    if(page>totalLogPages) return null;
                    return (
                      <button key={page} onClick={()=>setLogPage(page)}
                        style={{padding:'4px 10px',borderRadius:6,border:'1px solid var(--border)',fontSize:12,cursor:'pointer',
                          background:page===logPage?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)',
                          color:page===logPage?'#0C0F14':'var(--muted2)',fontWeight:page===logPage?700:400}}>
                        {page}
                      </button>
                    );
                  })}
                  <button disabled={logPage===totalLogPages} onClick={()=>setLogPage(p=>p+1)}
                    style={{padding:'4px 10px',borderRadius:6,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:logPage===totalLogPages?'not-allowed':'pointer',opacity:logPage===totalLogPages?.4:1}}>Next ›</button>
                  <button disabled={logPage===totalLogPages} onClick={()=>setLogPage(totalLogPages)}
                    style={{padding:'4px 10px',borderRadius:6,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:logPage===totalLogPages?'not-allowed':'pointer',opacity:logPage===totalLogPages?.4:1}}>»</button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </AppLayout>
  );
}