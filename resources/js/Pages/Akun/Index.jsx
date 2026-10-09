import React, { useRef, useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import { User, Camera, Trash2, Save, Loader2, Lock, Eye, EyeOff, TriangleAlert, Info, Phone, Mail, FolderKanban } from 'lucide-react';

const INP = {
  background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
  borderRadius:8, padding:'8px 11px', fontSize:12.5,
  fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
};
const LBL = { fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' };
const BTN = {
  padding:'8px 18px', borderRadius:8, border:'none', fontSize:12.5, fontWeight:700, cursor:'pointer',
  fontFamily:"'Outfit',sans-serif", display:'inline-flex', alignItems:'center', gap:6,
};
const MAKS_FOTO_MB = 2;

function ErrorText({ children }) {
  if (!children) return null;
  return <div style={{display:'flex',alignItems:'center',gap:4,fontSize:10.5,color:'#E04545',marginTop:4}}><TriangleAlert size={12}/>{children}</div>;
}

function PasswordInput({ value, onChange, placeholder, hasError }) {
  const [show, setShow] = useState(false);
  return (
    <div style={{position:'relative'}}>
      <input type={show?'text':'password'} style={{...INP,paddingRight:32,borderColor:hasError?'#E04545':'var(--border)'}}
        value={value} placeholder={placeholder} onChange={onChange} autoComplete="new-password" />
      <button type="button" onClick={()=>setShow(s=>!s)} tabIndex={-1}
        style={{position:'absolute',right:8,top:'50%',transform:'translateY(-50%)',background:'none',border:'none',padding:2,color:'var(--muted)',cursor:'pointer',display:'flex'}}>
        {show ? <EyeOff size={15}/> : <Eye size={15}/>}
      </button>
    </div>
  );
}

export default function AkunIndex({ akun, project_kantor = [] }) {
  const { auth } = usePage().props;
  const fotoUrl  = auth?.user?.foto_url;
  const initials = (akun.name || '?').trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase();

  // ── Foto profil ──
  const fileRef = useRef(null);
  const [fotoLoading, setFotoLoading] = useState(false);
  const [fotoError,   setFotoError]   = useState('');
  const [confirmHapus, setConfirmHapus] = useState(false);
  function pilihFoto(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > MAKS_FOTO_MB * 1024 * 1024) { setFotoError(`Ukuran foto maksimal ${MAKS_FOTO_MB} MB.`); return; }
    setFotoLoading(true); setFotoError('');
    router.post('/akun/foto', { foto: file }, {
      forceFormData: true, preserveScroll: true,
      onError: err => setFotoError(err.foto || 'Gagal mengupload foto.'),
      onFinish: () => setFotoLoading(false),
    });
  }
  function hapusFoto() {
    setConfirmHapus(false);
    router.delete('/akun/foto', { preserveScroll: true });
  }

  // ── Data akun ──
  const [form, setForm] = useState({ name: akun.name || '', email: akun.email || '', no_wa: akun.no_wa || '' });
  const [errors, setErrors]   = useState({});
  const [loading, setLoading] = useState(false);
  const [confirmEmail, setConfirmEmail] = useState(false);
  const emailBaru = form.email.trim().toLowerCase();
  const emailBerubah = emailBaru !== (akun.email || '');
  const berubah = form.name !== (akun.name || '') || emailBerubah || form.no_wa !== (akun.no_wa || '');
  function simpanAkun(e) {
    e?.preventDefault();
    if (!form.name.trim()) { setErrors({ name: 'Nama wajib diisi.' }); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailBaru)) { setErrors({ email: 'Format email tidak valid.' }); return; }
    // Email dipakai untuk login — minta konfirmasi dulu supaya salah ketik tidak bikin terkunci.
    if (emailBerubah && !confirmEmail) { setConfirmEmail(true); return; }
    setConfirmEmail(false);
    setLoading(true); setErrors({});
    router.put('/akun', { ...form, email: emailBaru }, {
      preserveScroll: true,
      onError: err => setErrors(err),
      onFinish: () => setLoading(false),
    });
  }

  // ── Password (aturan sama dengan Pengaturan: tanpa password lama) ──
  const [pw, setPw] = useState({ password: '', password_confirmation: '' });
  const [pwError, setPwError]     = useState('');
  const [pwLoading, setPwLoading] = useState(false);
  function simpanPassword(e) {
    e.preventDefault();
    if (!pw.password) return;
    if (pw.password !== pw.password_confirmation) { setPwError('Konfirmasi password tidak sama.'); return; }
    setPwLoading(true); setPwError('');
    router.post('/pengaturan/change-password', pw, {
      preserveScroll: true,
      onSuccess: () => setPw({ password: '', password_confirmation: '' }),
      onError: err => setPwError(err.password || err.password_confirmation || 'Gagal menyimpan.'),
      onFinish: () => setPwLoading(false),
    });
  }

  const info = [
    ['Kantor Utama', akun.kantor_utama || '—'],
    ['Akses Kantor', akun.akses_kantor === null ? 'Semua kantor' : (akun.akses_kantor.join(', ') || '—')],
  ];

  return (
    <AppLayout title="Akun" subtitle="Saya">
      <style>{`
        input[type="password"]::-ms-reveal { display: none !important; }
        input[type="password"]::-ms-clear { display: none !important; }
        .akun-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap:16px; align-items:start; max-width:980px; }
      `}</style>
      <ConfirmModal open={confirmHapus} onCancel={()=>setConfirmHapus(false)} onConfirm={hapusFoto}
        title="Hapus Foto Profil" message="Foto profil akan dihapus dan diganti inisial nama. Lanjutkan?"
        confirmLabel="Ya, Hapus" icon={<Trash2 size={28} color="#E04545"/>} />
      <ConfirmModal open={confirmEmail} onCancel={()=>setConfirmEmail(false)} onConfirm={()=>simpanAkun()} type="warning"
        title="Ganti Email Login"
        message={<>Email login akan diganti dari <b>{akun.email}</b> menjadi <b>{emailBaru}</b>. Pastikan sudah benar — login berikutnya wajib pakai email baru ini.</>}
        confirmLabel="Ya, Ganti Email" icon={<Mail size={28} color="#E8A020"/>} />

      {/* ── Kartu profil ── */}
      <div className="panel" style={{maxWidth:980,marginBottom:16}}>
        <div style={{padding:'20px 24px',display:'flex',alignItems:'center',gap:18,flexWrap:'wrap'}}>
          <div style={{position:'relative',flexShrink:0}}>
            {fotoUrl
              ? <img src={fotoUrl} alt={akun.name} style={{width:84,height:84,borderRadius:'50%',objectFit:'cover',border:'2px solid var(--border)'}} />
              : <div style={{width:84,height:84,borderRadius:'50%',background:'linear-gradient(135deg,#E8A020,#A06010)',display:'flex',alignItems:'center',justifyContent:'center',fontFamily:'Syne,sans-serif',fontSize:28,fontWeight:700,color:'#0C0F14'}}>{initials}</div>}
            <button type="button" title="Ganti foto" onClick={()=>fileRef.current?.click()} disabled={fotoLoading}
              style={{position:'absolute',right:-2,bottom:-2,width:30,height:30,borderRadius:'50%',border:'2px solid var(--bg2)',background:'var(--accent)',color:'#0C0F14',cursor:'pointer',display:'flex',alignItems:'center',justifyContent:'center'}}>
              {fotoLoading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <Camera size={14}/>}
            </button>
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pilihFoto} style={{display:'none'}} />
          </div>
          <div style={{minWidth:0,flex:1}}>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:18,fontWeight:700}}>{akun.name}</div>
            <div style={{fontSize:12.5,color:'var(--muted2)',marginTop:2}}>{akun.email}</div>
            <div style={{display:'flex',gap:6,marginTop:8,flexWrap:'wrap'}}>
              {akun.kantor_utama && <span style={{fontSize:11,fontWeight:600,padding:'3px 10px',borderRadius:99,background:'var(--bg3)',color:'var(--muted2)'}}>{akun.kantor_utama}</span>}
            </div>
          </div>
          <div style={{display:'flex',flexDirection:'column',gap:6,alignItems:'flex-end'}}>
            <button type="button" onClick={()=>fileRef.current?.click()} disabled={fotoLoading}
              style={{...BTN,padding:'7px 14px',fontSize:12,background:'var(--bg3)',color:'var(--text)',border:'1px solid var(--border)'}}>
              <Camera size={13}/> {fotoUrl ? 'Ganti Foto' : 'Upload Foto'}
            </button>
            {fotoUrl && (
              <button type="button" onClick={()=>setConfirmHapus(true)}
                style={{...BTN,padding:'7px 14px',fontSize:12,background:'rgba(224,69,69,.06)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)'}}>
                <Trash2 size={13}/> Hapus Foto
              </button>
            )}
          </div>
        </div>
        <div style={{padding:'0 24px 14px',fontSize:10.5,color:'var(--muted)'}}>
          Foto JPG, PNG, atau WEBP, maksimal {MAKS_FOTO_MB} MB.
          <ErrorText>{fotoError}</ErrorText>
        </div>
      </div>

      <div className="akun-grid">
        {/* ── Data akun ── */}
        <div className="panel">
          <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><User size={14}/> Data Akun</div></div>
          <form onSubmit={simpanAkun} style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:12}}>
            <div>
              <label style={LBL}>Nama Tampilan *</label>
              <input style={{...INP,borderColor:errors.name?'#E04545':'var(--border)'}} value={form.name} maxLength={100}
                onChange={e=>{setForm(f=>({...f,name:e.target.value})); setErrors(er=>({...er,name:null}));}} />
              <ErrorText>{errors.name}</ErrorText>
            </div>
            <div>
              <label style={LBL}><Mail size={10} style={{verticalAlign:'-1px'}}/> Email Login</label>
              <input type="email" style={{...INP,borderColor:errors.email?'#E04545':'var(--border)'}} value={form.email} maxLength={255} autoComplete="email"
                onChange={e=>{setForm(f=>({...f,email:e.target.value})); setErrors(er=>({...er,email:null}));}} />
              <ErrorText>{errors.email}</ErrorText>
              <div style={{fontSize:10,color:emailBerubah?'var(--accent)':'var(--muted)',marginTop:4}}>
                {emailBerubah ? 'Setelah disimpan, login berikutnya pakai email baru ini.' : 'Email ini dipakai untuk login.'}
              </div>
            </div>
            <div>
              <label style={LBL}><Phone size={10} style={{verticalAlign:'-1px'}}/> Nomor WhatsApp</label>
              <input style={{...INP,borderColor:errors.no_wa?'#E04545':'var(--border)'}} value={form.no_wa} inputMode="tel" maxLength={20}
                placeholder="cth: 081234567890" onChange={e=>{setForm(f=>({...f,no_wa:e.target.value.replace(/[^\d+]/g,'')})); setErrors(er=>({...er,no_wa:null}));}} />
              <ErrorText>{errors.no_wa}</ErrorText>
            </div>
            <div>
              <button type="submit" disabled={loading || !berubah}
                style={{...BTN,background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',opacity:(loading||!berubah)?.55:1,cursor:(loading||!berubah)?'not-allowed':'pointer'}}>
                {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <Save size={14}/>} Simpan
              </button>
            </div>
          </form>
        </div>

        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {/* ── Password ── */}
          <div className="panel">
            <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><Lock size={14}/> Ganti Password</div></div>
            <form onSubmit={simpanPassword} style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:12}}>
              <div>
                <label style={LBL}>Password Baru</label>
                <PasswordInput value={pw.password} placeholder="Minimal 6 karakter" hasError={!!pwError}
                  onChange={e=>{setPw(p=>({...p,password:e.target.value})); setPwError('');}} />
              </div>
              <div>
                <label style={LBL}>Ulangi Password Baru</label>
                <PasswordInput value={pw.password_confirmation} placeholder="Ketik ulang password baru" hasError={!!pwError}
                  onChange={e=>{setPw(p=>({...p,password_confirmation:e.target.value})); setPwError('');}} />
                <ErrorText>{pwError}</ErrorText>
              </div>
              <div>
                <button type="submit" disabled={pwLoading || !pw.password}
                  style={{...BTN,background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',opacity:(pwLoading||!pw.password)?.55:1,cursor:(pwLoading||!pw.password)?'not-allowed':'pointer'}}>
                  {pwLoading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <Save size={14}/>} Simpan Password
                </button>
              </div>
            </form>
          </div>

          {/* ── Info akun ── */}
          <div className="panel">
            <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><Info size={14}/> Info Akun</div></div>
            <div style={{padding:'6px 20px 12px'}}>
              {info.map(([k, v]) => (
                <div key={k} style={{display:'flex',gap:12,padding:'8px 0',borderBottom:'1px solid var(--border)',fontSize:12.5}}>
                  <div style={{width:130,flexShrink:0,color:'var(--muted)'}}>{k}</div>
                  <div style={{fontWeight:500,minWidth:0,wordBreak:'break-word'}}>{v}</div>
                </div>
              ))}
              <div style={{fontSize:10,color:'var(--muted)',marginTop:8}}>Akses kantor diatur oleh admin.</div>
            </div>
          </div>

          {/* ── Project di kantor user ── */}
          <div className="panel">
            <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><FolderKanban size={14}/> Project di Kantor Saya</div></div>
            <div style={{padding:'6px 20px 14px'}}>
              {project_kantor.map(k => (
                <div key={k.nama} style={{padding:'10px 0',borderBottom:'1px solid var(--border)'}}>
                  <div style={{fontSize:12.5,fontWeight:600,marginBottom:6}}>{k.nama} <span style={{fontWeight:400,color:'var(--muted)'}}>· {k.projects.length} project</span></div>
                  {k.projects.length ? (
                    <div style={{display:'flex',flexWrap:'wrap',gap:5}}>
                      {k.projects.map(kode => (
                        <span key={kode} style={{fontSize:11,fontWeight:600,padding:'3px 9px',borderRadius:99,background:'rgba(232,160,32,.12)',color:'var(--accent)'}}>{kode}</span>
                      ))}
                    </div>
                  ) : <div style={{fontSize:11.5,color:'var(--muted)'}}>Belum ada project.</div>}
                </div>
              ))}
              <div style={{display:'flex',alignItems:'flex-start',gap:6,fontSize:11,color:'var(--muted2)',marginTop:10,padding:'8px 10px',borderRadius:8,background:'var(--bg3)'}}>
                <Info size={13} style={{flexShrink:0,marginTop:1}}/>
                {auth?.user?.can?.is_admin_settings
                  ? <span>Project bisa ditambah atau diubah di <b>Pengaturan → Data Project</b>.</span>
                  : <span>Project yang Anda butuhkan belum ada? Hubungi <b>admin</b> untuk menambahkan project ke kantor Anda.</span>}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
