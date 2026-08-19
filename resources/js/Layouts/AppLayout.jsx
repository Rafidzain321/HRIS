// resources>js>Layouts>AppLayout.jsx
import React, { useState, useEffect, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PROJECT_COLORS } from '@/Constants/projectColors';
import {
  LayoutDashboard, User, Shield, Stethoscope, CreditCard, HardHat,
  ClipboardList, Car, Truck, Calendar, Receipt, Wallet, BookOpen, Bell,
  Settings, Moon, Sun, X, Loader2, CheckCircle2, XCircle, TriangleAlert,
  Trash2, LogOut, Menu, Eye, Building2, Check, Target,
} from 'lucide-react';

const NAV = [
  { section: 'Utama' },
  { key: 'dashboard',  icon: LayoutDashboard, label: 'Dashboard',     href: '/' },
  { key: 'karyawan',   icon: User, label: 'Data Karyawan', href: '/employees', badge: null },
  { section: 'Compliance' },
  { key: 'sim',        icon: Shield, label: 'SIM Karyawan',  href: '/compliance/sim',     badge: null },
  { key: 'mcu',        icon: Stethoscope, label: 'MCU',           href: '/compliance/mcu',     badge: null },
  { key: 'badge',      icon: CreditCard, label: 'Badge / KP',    href: '/compliance/badge',   badge: null },
  { key: 'ppe',        icon: HardHat, label: 'PPE & Atribut', href: '/compliance/ppe' },
  { section: 'Operasional' },
  { key: 'ccpm',       icon: ClipboardList, label: 'Data CCPM',     href: '/ccpm',       badge: null },
  { key: 'driver',     icon: Car, label: 'Data Driver',   href: '/driver' },
  { key: 'equipment', icon: Truck, label: 'Equipment & Operator', href: '/equipment' },
  { key: 'timesheet',  icon: Calendar, label: 'Timesheet',     href: '/timesheet' },
  { key: 'slip-gaji',  icon: Receipt, label: 'Slip Gaji',     href: '/timesheet/slip-gaji' },
  { key: 'data-gaji',  icon: Wallet, label: 'Data Gaji',     href: '/timesheet/data-gaji' },
  { key: 'training',   icon: BookOpen, label: 'Training',      href: '/training' },
  { key: 'kpi',        icon: Target, label: 'KPI',           href: '/kpi' },
  { section: 'Sistem' },
  { key: 'notifications', icon: Bell, label: 'Notifikasi', href: '/notifications' },
  { key: 'pengaturan', icon: Settings, label: 'Pengaturan',   href: '/pengaturan' },
];

function SnowCanvas({ dark }) {
  const canvasRef = useRef(null);
  const animRef   = useRef(null);
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W = canvas.width  = window.innerWidth;
    let H = canvas.height = window.innerHeight;
    const COUNT = 55;
    const dots = Array.from({ length: COUNT }, () => ({
      x: Math.random() * W, y: Math.random() * H,
      r: Math.random() * 2 + 1,
      vx: (Math.random() - 0.5) * 0.4,
      vy: (Math.random() - 0.5) * 0.4,
      opacity: Math.random() * 0.6 + 0.5,
    }));
    const CONNECT_DIST = 140;
    const DOT_COLOR  = dark ? '232,160,32' : '100,140,220';
    const LINE_COLOR = dark ? '232,160,32' : '100,140,220';
    function draw() {
      ctx.clearRect(0, 0, W, H);
      for (let i = 0; i < dots.length; i++) {
        const d = dots[i];
        d.x += d.vx; d.y += d.vy;
        if (d.x < 0) d.x = W; if (d.x > W) d.x = 0;
        if (d.y < 0) d.y = H; if (d.y > H) d.y = 0;
        ctx.beginPath();
        ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(${DOT_COLOR},${d.opacity * 0.7})`;
        ctx.fill();
        for (let j = i + 1; j < dots.length; j++) {
          const d2 = dots[j];
          const dx = d.x - d2.x, dy = d.y - d2.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < CONNECT_DIST) {
            ctx.beginPath();
            ctx.moveTo(d.x, d.y); ctx.lineTo(d2.x, d2.y);
            ctx.strokeStyle = `rgba(${LINE_COLOR},${(1 - dist / CONNECT_DIST) * 0.15})`;
            ctx.lineWidth = 0.8;
            ctx.stroke();
          }
        }
      }
      animRef.current = requestAnimationFrame(draw);
    }
    draw();
    const onResize = () => {
      W = canvas.width  = window.innerWidth;
      H = canvas.height = window.innerHeight;
    };
    window.addEventListener('resize', onResize);
    return () => { cancelAnimationFrame(animRef.current); window.removeEventListener('resize', onResize); };
  }, [dark]);
  return <canvas ref={canvasRef} style={{ position:'fixed', inset:0, zIndex:0, pointerEvents:'none', opacity:0.5 }} />;
}

function RealtimeClock() {
  const [now, setNow] = useState(new Date());
  useEffect(() => { const t = setInterval(() => setNow(new Date()), 1000); return () => clearInterval(t); }, []);
  const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const mons = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  return (
    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
      <div style={{ background:'var(--card)', border:'1px solid var(--border)', borderRadius:8, padding:'5px 10px', fontSize:11, color:'var(--muted2)', display:'inline-flex', alignItems:'center', gap:5 }} className="hide-mobile">
        <Calendar size={13}/> <b style={{ color:'var(--accent2)' }}>{days[now.getDay()]}, {String(now.getDate()).padStart(2,'0')} {mons[now.getMonth()]} {now.getFullYear()}</b>
      </div>
      <div className="hide-mobile" style={{ background:'var(--card)', border:'1px solid var(--border)', borderRadius:8, padding:'5px 10px', fontSize:13, fontFamily:'monospace', fontWeight:700, color:'var(--accent)', minWidth:78, textAlign:'center' }}>
        {String(now.getHours()).padStart(2,'0')}:{String(now.getMinutes()).padStart(2,'0')}:{String(now.getSeconds()).padStart(2,'0')}
      </div>
    </div>
  );
}

function ThemeToggle({ dark, onToggle }) {
  return (
    <div onClick={onToggle} style={{ display:'flex', alignItems:'center', gap:6, cursor:'pointer', userSelect:'none' }}>
      <span style={{ display:'inline-flex' }}>{dark ? <Moon size={14}/> : <Sun size={14}/>}</span>
      <div style={{ width:40, height:22, borderRadius:11, background: dark ? '#3A8FE0' : '#E8C030', position:'relative', transition:'background .3s', border:'1px solid rgba(255,255,255,.15)', flexShrink:0 }}>
        <div style={{ position:'absolute', top:2, left: dark ? 20 : 2, width:16, height:16, borderRadius:'50%', background:'#fff', boxShadow:'0 1px 4px rgba(0,0,0,.3)', transition:'left .25s cubic-bezier(.22,.9,.25,1)' }}/>
      </div>
      <span style={{ fontSize:11, fontWeight:600, color:'var(--muted2)', minWidth:28 }} className="hide-mobile">{dark ? 'Dark' : 'Light'}</span>
    </div>
  );
}

function NotifPanel({ open, onClose }) {
  const [data,    setData]    = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    if (!open) return;
    setLoading(true);
    fetch('/notifications/data').then(r=>r.json()).then(d=>{setData(d);setLoading(false);}).catch(()=>setLoading(false));
    const interval = setInterval(() => {
      fetch('/notifications/data').then(r=>r.json()).then(d=>setData(d)).catch(()=>{});
    }, 60000);
    return () => clearInterval(interval);
  }, [open]);
  const typeIcon  = { sim:Car, mcu:Stethoscope, badge:CreditCard, kp:ClipboardList };
  const typeColor = { sim:'#3A8FE0', mcu:'#E06A20', badge:'#E8A020', kp:'#22C97A' };
  if (!open) return null;
  return (
    <>
      <div onClick={onClose} style={{ position:'fixed', inset:0, zIndex:199 }} />
      <div style={{ position:'fixed', top:58, right:12, width:'min(380px, calc(100vw - 24px)', maxHeight:'70vh', zIndex:200, background:'var(--bg2)', border:'1px solid var(--border2)', borderRadius:14, boxShadow:'0 20px 60px rgba(0,0,0,.4)', display:'flex', flexDirection:'column', animation:'fadeUp .2s both' }}>
        <div style={{ padding:'12px 14px', borderBottom:'1px solid var(--border)', display:'flex', alignItems:'center', justifyContent:'space-between', flexShrink:0 }}>
          <div style={{ fontWeight:600, fontSize:13, display:'flex', alignItems:'center', gap:6 }}>
            <Bell size={14}/> Notifikasi Compliance
            {data?.summary?.total > 0 && <span style={{ marginLeft:6, background:'var(--red)', color:'#fff', fontSize:9.5, fontWeight:700, borderRadius:99, padding:'1px 6px' }}>{data.summary.total}</span>}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', color:'var(--muted)', padding:'2px 6px', display:'flex' }}><X size={16}/></div>
        </div>
        <div style={{ overflowY:'auto', flex:1 }}>
          {loading ? (
            <div style={{ padding:20, textAlign:'center', color:'var(--muted)', fontSize:12, display:'flex', alignItems:'center', justifyContent:'center', gap:6 }}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> Memuat...</div>
          ) : !data?.items?.length ? (
            <div style={{ padding:20, textAlign:'center', color:'var(--muted)', fontSize:12, display:'flex', alignItems:'center', justifyContent:'center', gap:6 }}><CheckCircle2 size={14} color="#22C97A"/> Tidak ada compliance alert</div>
          ) : data.items.map((item, i) => (
            <a key={i} href={item.href} onClick={e=>{
                e.preventDefault();
                onClose();
                const sep = item.href.includes('?') ? '&' : '?';
                router.visit(`${item.href}${sep}highlight=${item.id}`);
              }}
              style={{ display:'flex', alignItems:'flex-start', gap:10, padding:'11px 14px', borderBottom:'1px solid var(--border)', textDecoration:'none', transition:'background .12s', cursor:'pointer' }}
              onMouseEnter={e=>e.currentTarget.style.background='rgba(232,160,32,.05)'}
              onMouseLeave={e=>e.currentTarget.style.background=''}>
              <span style={{ flexShrink:0, display:'flex' }}>{(() => { const Ic = typeIcon[item.type] || TriangleAlert; return <Ic size={18} color={typeColor[item.type]||'var(--accent)'}/>; })()}</span>
              <div style={{ flex:1, minWidth:0 }}>
                <div style={{ fontSize:12, fontWeight:600, color:'var(--text)', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{item.nama}</div>
                <div style={{ fontSize:10.5, color:'var(--muted2)', marginTop:1 }}>{item.jabatan}</div>
              </div>
              <div style={{ flexShrink:0, textAlign:'right' }}>
                <span style={{ fontSize:10, fontWeight:700, padding:'2px 7px', borderRadius:99, background: item.level==='expired'?'rgba(224,69,69,.15)':'rgba(232,160,32,.15)', color: item.level==='expired'?'var(--red)':'var(--accent)' }}>
                  {item.label} {item.level==='expired'?'Expired':'<30hr'}
                </span>
                <div style={{ fontSize:9.5, color:'var(--muted)', marginTop:3 }}>{item.date}</div>
              </div>
            </a>
          ))}
        </div>
        {data?.summary && (
          <div style={{ padding:'10px 14px', borderTop:'1px solid var(--border)', display:'flex', gap:10, flexShrink:0 }}>
            {Object.entries(data.summary.by_type||{}).map(([k,v])=> v>0 && (
              <span key={k} style={{ fontSize:10.5, color: typeColor[k]||'var(--muted2)', display:'inline-flex', alignItems:'center', gap:3 }}>
                {(() => { const Ic = typeIcon[k]; return Ic ? <Ic size={12}/> : null; })()} {v}
              </span>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

function FlashNotif() {
  const props = usePage().props;
  const [msg,     setMsg]     = useState(null);
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const flash = props.flash?.success || props.flash?.error;
    const type  = props.flash?.success ? 'success' : 'error';
    if (flash) { setMsg({ text: flash, type }); setVisible(true); const t = setTimeout(() => setVisible(false), 5000); return () => clearTimeout(t); }
  }, [props.flash]);
  if (!msg) return null;
  const isSuccess = msg.type === 'success';
  return (
    <div style={{ position:'fixed', bottom:20, right:16, zIndex:9999, display:'flex', alignItems:'center', gap:10, background: isSuccess?'rgba(34,201,122,.15)':'rgba(224,69,69,.15)', border:`1px solid ${isSuccess?'rgba(34,201,122,.4)':'rgba(224,69,69,.4)'}`, borderRadius:12, padding:'12px 16px', boxShadow:'0 8px 32px rgba(0,0,0,.35)', backdropFilter:'blur(8px)', width:'min(360px, calc(100vw - 32px))', transition:'all .3s', opacity:visible?1:0, transform:visible?'translateY(0)':'translateY(16px)', pointerEvents:visible?'auto':'none' }}>
      <span style={{ flexShrink:0, display:'flex' }}>{isSuccess ? <CheckCircle2 size={18} color="#22C97A"/> : <XCircle size={18} color="#E04545"/>}</span>
      <div style={{ flex:1 }}>
        <div style={{ fontSize:12, fontWeight:600, color:isSuccess?'#22C97A':'#E04545' }}>{isSuccess?'Berhasil!':'Gagal!'}</div>
        <div style={{ fontSize:11.5, color:'var(--muted2)', marginTop:2, lineHeight:1.4 }}>{msg.text}</div>
      </div>
      <div onClick={()=>setVisible(false)} style={{ cursor:'pointer', color:'var(--muted)', padding:'2px 5px', borderRadius:5, border:'1px solid rgba(255,255,255,.07)', flexShrink:0, display:'flex' }}><X size={13}/></div>
      <div style={{ position:'absolute', bottom:0, left:0, height:3, borderRadius:'0 0 12px 12px', background:isSuccess?'#22C97A':'#E04545', animation:visible?'shrink 5s linear forwards':'none', width:'100%' }}/>
    </div>
  );
}

export function ConfirmModal({ open, onConfirm, onCancel, title, message, confirmLabel='Hapus', type='danger', icon }) {
  if (!open) return null;

  const colors = {
    danger:  { bg:'rgba(224,69,69,.1)',  border:'rgba(224,69,69,.3)',  btn:'linear-gradient(135deg,#E04545,#A03030)', icon:<Trash2 size={28} color="#E04545"/>, iconBg:'rgba(224,69,69,.15)' },
    warning: { bg:'rgba(232,160,32,.1)', border:'rgba(232,160,32,.3)', btn:'linear-gradient(135deg,#E8A020,#A06010)', icon:<TriangleAlert size={28} color="#E8A020"/>, iconBg:'rgba(232,160,32,.15)' },
  };
  const c = colors[type] || colors.danger;
  const displayIcon = icon || c.icon;

  return (
    <div style={{position:'fixed',inset:0,zIndex:500,display:'flex',alignItems:'center',justifyContent:'center',background:'rgba(0,0,0,.6)',backdropFilter:'blur(4px)',animation:'fadeUp .15s both'}}>
      <div style={{background:'var(--bg2)',border:`1px solid ${c.border}`,borderRadius:16,width:'min(420px,calc(100vw - 32px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',overflow:'hidden',animation:'fadeUp .2s both'}}>
        {/* Top accent bar */}
        <div style={{height:4,background:c.btn}}/>

        <div style={{padding:'28px 28px 20px',textAlign:'center'}}>
          {/* Icon */}
          <div style={{width:56,height:56,borderRadius:14,background:c.iconBg,display:'flex',alignItems:'center',justifyContent:'center',margin:'0 auto 16px'}}>
            {displayIcon}
          </div>

          {/* Title */}
          <div style={{fontFamily:'Syne,sans-serif',fontSize:17,fontWeight:700,color:'var(--text)',marginBottom:10}}>
            {title || 'Konfirmasi Hapus'}
          </div>

          {/* Message */}
          <div style={{fontSize:13,color:'var(--muted2)',lineHeight:1.6,marginBottom:24}}>
            {message || 'Apakah kamu yakin ingin menghapus data ini? Tindakan ini tidak bisa dibatalkan.'}
          </div>

          {/* Buttons */}
          <div style={{display:'flex',gap:10,justifyContent:'center'}}>
            <button onClick={onCancel}
              style={{padding:'10px 24px',borderRadius:9,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:13,fontWeight:600,cursor:'pointer',fontFamily:"'Outfit',sans-serif",minWidth:100}}>
              Batal
            </button>
            <button onClick={onConfirm}
              style={{padding:'10px 24px',borderRadius:9,border:'none',background:c.btn,color:'#fff',fontSize:13,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",minWidth:100,boxShadow:`0 4px 14px rgba(224,69,69,.3)`}}>
              {confirmLabel}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ── Profile + Logout ────────────────────────────────────────
function ProfileMenu({ authUser, onLogout }) {
  return (
    <div style={{ padding:'10px 8px', borderTop:'1px solid var(--border)', display:'flex', flexDirection:'column', gap:6 }}>
      <div style={{ display:'flex', alignItems:'center', gap:8, padding:'8px 10px', borderRadius:8, background:'var(--bg3)', border:'1px solid var(--border)' }}>
        <div style={{ width:28, height:28, borderRadius:7, background:'linear-gradient(135deg,#3A8FE0,#22C97A)', display:'flex', alignItems:'center', justifyContent:'center', fontSize:10, fontWeight:700, color:'#fff', flexShrink:0 }}>
          {(authUser?.name || 'HR').split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase()}
        </div>
        <div style={{ minWidth:0, flex:1 }}>
          <div style={{ fontSize:11, fontWeight:600, color:'var(--text)', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{authUser?.name || 'Admin HR'}</div>
          <div style={{ fontSize:9.5, color:'var(--muted)' }}>
            {authUser?.role === 'super-admin' || authUser?.can?.is_super_admin ? 'Super Admin' :
            authUser?.can?.is_viewer ? 'Viewer' :
            authUser?.project?.nama || 'Project User'}
          </div>
        </div>
      </div>
      <div onClick={onLogout} style={{
        display:'flex', alignItems:'center', justifyContent:'center', gap:8, padding:'8px 10px', borderRadius:8,
        fontSize:12, fontWeight:600, color:'#E04545', cursor:'pointer',
        background:'rgba(224,69,69,.06)', border:'1px solid rgba(224,69,69,.18)', transition:'background .15s',
      }}
        onMouseEnter={e=>e.currentTarget.style.background='rgba(224,69,69,.12)'}
        onMouseLeave={e=>e.currentTarget.style.background='rgba(224,69,69,.06)'}>
        <LogOut size={14}/> Keluar
      </div>
    </div>
  );
}

// ── Project Dropdown (Mobile Topbar) ──
function ProjectDropdown({ authUser }) {
  const { projects, active_project_id: activeProjectId } = usePage().props;
  const [open, setOpen] = useState(false);
  const isSuperAdmin = authUser?.can?.is_super_admin;
  const isViewer = authUser?.can?.is_viewer;
  const isMultiProject = !isSuperAdmin && authUser?.project_ids?.length > 1;
  const multiProjects = isMultiProject ? projects?.filter(p => authUser.project_ids.includes(p.id)) : [];

  const canFilter = (isSuperAdmin || isViewer) && projects?.length > 0;
  const showMulti = isMultiProject && multiProjects.length > 0;

  if (!canFilter && !showMulti) return null;

  const activeProject = projects?.find(p => p.id === activeProjectId);
  const activeLabel = activeProject ? activeProject.kode.toUpperCase() : 'SEMUA';
  const activeColor = activeProject ? (PROJECT_COLORS[activeProject.kode] || activeProject.warna) : 'var(--accent)';

  function selectProject(kode) {
    setOpen(false);
    router.post('/switch-project', { project: kode });
  }

  return (
    <div className="project-dropdown-mobile">
      <div onClick={() => setOpen(true)}
        style={{
          display: 'flex', alignItems: 'center', gap: 5,
          padding: '6px 10px', borderRadius: 8,
          background: 'var(--card)', border: `1px solid ${activeColor}50`,
          cursor: 'pointer', flexShrink: 0,
        }}>
        <div style={{ width: 6, height: 6, borderRadius: '50%', background: activeColor }} />
        <span style={{ fontSize: 11, fontWeight: 700, color: activeColor }}>{activeLabel}</span>
        <span style={{ fontSize: 9, color: 'var(--muted)' }}>▼</span>
      </div>

      {open && (
        <>
          <div onClick={() => setOpen(false)} style={{
            position: 'fixed', inset: 0, zIndex: 199,
            background: 'rgba(0,0,0,.5)', animation: 'fadeUp .15s both',
          }} />
          <div style={{
            position: 'fixed', bottom: 0, left: 0, right: 0, zIndex: 200,
            background: 'var(--bg2)', borderRadius: '16px 16px 0 0',
            padding: '8px 0 max(20px, env(safe-area-inset-bottom))',
            boxShadow: '0 -8px 30px rgba(0,0,0,.3)',
            animation: 'slideUp .25s cubic-bezier(.22,.9,.25,1) both',
          }}>
            <div style={{ width: 36, height: 4, background: 'var(--border2)', borderRadius: 99, margin: '0 auto 14px' }} />
            <div style={{ padding: '0 18px', marginBottom: 12 }}>
              <div style={{ fontSize: 15, fontWeight: 700, fontFamily: 'Syne,sans-serif', color: 'var(--text)' }}>Pilih Project</div>
              <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 2 }}>Filter data berdasarkan project</div>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', padding: '0 10px', maxHeight: '50vh', overflowY: 'auto' }}>
              {!showMulti && (
                <div onClick={() => { setOpen(false); router.post('/switch-project', { project: 'all' }); }}
                  style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    padding: '12px 10px', borderRadius: 10,
                    background: !activeProjectId ? 'rgba(232,160,32,.12)' : 'transparent',
                  }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <div style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--accent)' }} />
                    <span style={{ fontSize: 14, fontWeight: !activeProjectId ? 700 : 400, color: !activeProjectId ? 'var(--accent)' : 'var(--text)' }}>Semua Project</span>
                  </div>
                  {!activeProjectId && <Check size={16} color="var(--accent)"/>}
                </div>
              )}
              {(() => {
                const list = showMulti ? multiProjects : projects;
                const fieldList = list.filter(p => p.kode.toUpperCase() !== 'HO');
                const hoItem = list.find(p => p.kode.toUpperCase() === 'HO');
                const renderRow = p => {
                  const isActive = activeProjectId === p.id;
                  const warna = PROJECT_COLORS[p.kode] || p.warna;
                  return (
                    <div key={p.id} onClick={() => selectProject(p.kode)}
                      style={{
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        padding: '12px 10px', borderRadius: 10,
                        background: isActive ? `${warna}18` : 'transparent',
                      }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{ width: 8, height: 8, borderRadius: '50%', background: warna }} />
                        <span style={{ fontSize: 14, fontWeight: isActive ? 700 : 400, color: isActive ? warna : 'var(--text)' }}>{p.nama}</span>
                      </div>
                      {isActive && <Check size={16} color={warna}/>}
                    </div>
                  );
                };
                return (
                  <>
                    {fieldList.map(renderRow)}
                    {hoItem && (
                      <>
                        <div style={{ display:'flex', alignItems:'center', gap:8, margin:'10px 10px 2px' }}>
                          <span style={{ fontSize:10.5, fontWeight:700, color:'var(--muted)', textTransform:'uppercase', letterSpacing:'.06em', whiteSpace:'nowrap', display:'inline-flex', alignItems:'center', gap:4 }}>
                            <Building2 size={11}/> Kantor Pusat
                          </span>
                          <div style={{ flex:1, borderTop:'1px solid var(--border)' }} />
                        </div>
                        {renderRow(hoItem)}
                      </>
                    )}
                  </>
                );
              })()}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

export default function AppLayout({ children, title='Dashboard', subtitle='HRIS' }) {
  const { url } = usePage();
  const props    = usePage().props;
  const [confirmLogout, setConfirmLogout] = useState(false);

  const [dark, setDark] = useState(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem('akm-theme');
      return saved ? saved === 'dark' : true;
    }
    return true;
  });

  function toggleTheme() {
    const next = !dark;
    setDark(next);
    localStorage.setItem('akm-theme', next ? 'dark' : 'light');
  }

  const [notifOpen,   setNotifOpen]   = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(false); // mobile drawer

  // Close sidebar when navigating on mobile
  useEffect(() => { setSidebarOpen(false); }, [url]);

  const CSS_VARS = dark ? `
    --bg:#0C0F14; --bg2:#121620; --bg3:#181E2A; --card:#161C28;
    --border:rgba(255,255,255,0.07); --border2:rgba(255,255,255,0.15);
    --text:#E8ECF5; --muted:#6B7494; --muted2:#8A90A8;
    --accent:#E8A020; --accent2:#F5C050;
    --green:#22C97A; --red:#E04545; --blue:#3A8FE0;
    --sidebar-bg:#121620; --topbar-bg:#0C0F14;
  ` : `
    --bg:#EEF1F8; --bg2:#FFFFFF; --bg3:#E2E7F0; --card:#FFFFFF;
    --border:rgba(0,0,0,0.08); --border2:rgba(0,0,0,0.18);
    --text:#1A1D2E; --muted:#7A80A0; --muted2:#4A5070;
    --accent:#C88010; --accent2:#B87010;
    --green:#148050; --red:#C03030; --blue:#1A60C0;
    --sidebar-bg:#FFFFFF; --topbar-bg:#EEF1F8;
  `;

  const authUser = props.auth?.user;

  return (
    <div style={{ display:'flex', minHeight:'100vh', background:'var(--bg)', color:'var(--text)', fontFamily:"'Outfit',sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Outfit:wght@300;400;500;600&display=swap');
        :root { ${CSS_VARS} }
        * { margin:0; padding:0; box-sizing:border-box; }
        ::-webkit-scrollbar{width:5px;}
        ::-webkit-scrollbar-track{background:transparent;}
        ::-webkit-scrollbar-thumb{background:var(--border2);border-radius:99px;}
        .hide-scrollbar::-webkit-scrollbar{display:none;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
        @keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
        @keyframes shrink{from{width:100%}to{width:0%}}
        @keyframes slideIn{from{transform:translateX(-100%)}to{transform:translateX(0)}}
        @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
        @keyframes spin{to{transform:rotate(360deg)}}

        .nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;font-size:12.5px;font-weight:500;color:var(--muted2);cursor:pointer;transition:all .18s;position:relative;text-decoration:none;}
        .nav-item:hover{color:var(--text);background:rgba(128,128,128,.08);}
        .nav-item.active{color:var(--accent);background:rgba(232,160,32,.12);}
        .nav-item.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;border-radius:0 3px 3px 0;background:var(--accent);}

        .pill{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:99px;font-size:10.5px;font-weight:600;white-space:nowrap;}
        .pill::before{content:'';display:inline-block;width:5px;height:5px;border-radius:50%;}
        .pill-red{background:rgba(224,69,69,.13);color:var(--red);}.pill-red::before{background:var(--red);}
        .pill-warn{background:rgba(232,160,32,.15);color:var(--accent);}.pill-warn::before{background:var(--accent);}
        .pill-green{background:rgba(34,201,122,.13);color:var(--green);}.pill-green::before{background:var(--green);}
        .pill-blue{background:rgba(58,143,224,.13);color:var(--blue);}.pill-blue::before{background:var(--blue);}
        .pill-gray{background:rgba(128,128,128,.12);color:var(--muted2);}.pill-gray::before{background:var(--muted);}
        .pill-orange{background:rgba(224,106,32,.13);color:#E06A20;}.pill-orange::before{background:#E06A20;}

        .panel{background:var(--card);border:1px solid var(--border);border-radius:12px;}
        .panel-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:8px;}
        .panel-title{font-size:13px;font-weight:600;color:var(--text);}

        .kar-table{width:100%;border-collapse:collapse;}
        .kar-table th{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600;text-align:left;padding:10px 8px;border-bottom:1px solid var(--border);}
        .kar-table td{padding:10px 8px;border-bottom:1px solid rgba(128,128,128,.06);font-size:12px;vertical-align:middle;color:var(--text);}
        .kar-table tr:last-child td{border-bottom:none;}
        .kar-table tr:hover td{background:rgba(232,160,32,.04);}
        .kar-table tr{cursor:pointer;transition:background .12s;}

        .stat{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px 16px;position:relative;overflow:hidden;cursor:pointer;transition:transform .2s,border-color .2s,box-shadow .2s;}
        .stat:hover{transform:translateY(-3px);border-color:var(--border2);box-shadow:0 8px 30px rgba(0,0,0,.12);}

        .search-input{width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:9px 14px 9px 36px;font-size:12.5px;color:var(--text);font-family:'Outfit',sans-serif;outline:none;transition:border .18s;}
        .search-input:focus{border-color:var(--accent);}
        .search-input::placeholder{color:var(--muted);}

        select{background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:7px;padding:6px 10px;font-size:11.5px;font-family:'Outfit',sans-serif;outline:none;cursor:pointer;}
        .tab{padding:7px 16px;border-radius:7px;font-size:12px;font-weight:500;color:var(--muted2);cursor:pointer;transition:all .15s;}
        .tab.active{background:rgba(232,160,32,.12);color:var(--accent);}
        .icon-btn{width:36px;height:36px;border-radius:8px;background:var(--card);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;transition:all .18s;position:relative;}
        .icon-btn:hover{background:var(--bg3);border-color:var(--border2);}
        input[type="text"],input[type="email"],input[type="date"],input[type="password"],input[type="number"],textarea{background:var(--bg3);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'Outfit',sans-serif;outline:none;width:100%;transition:border .18s;}
        input:focus,textarea:focus{border-color:var(--accent);}
        label{font-size:11.5px;color:var(--muted);margin-bottom:4px;display:block;}

        /* ── RESPONSIVE ─────────────────────────────────────────── */

        /* Tablet & Mobile: sidebar hidden by default */
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
          .sidebar-desktop { display: none !important; }
          .main-content { margin-left: 0 !important; }
          .hide-mobile { display: none !important; }
          .topbar-title { font-size: 15px !important; }

          /* Cards grid: 2 kolom di tablet/HP */
          .stat-grid-4 { grid-template-columns: repeat(2, 1fr) !important; }
          .stat-grid-5 { grid-template-columns: repeat(2, 1fr) !important; }
          .stat-grid-6 { grid-template-columns: repeat(2, 1fr) !important; }

          /* Table: scroll horizontal */
          .table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch; }

          /* Panel head: wrap items */
          .panel-head { flex-wrap: wrap; gap: 8px; }

          /* Main padding kecilkan */
          .main-pad { padding: 16px 14px !important; }

          /* Modal: full width */
          .modal-box { width: calc(100vw - 24px) !important; max-width: 100% !important; margin: 0 12px; }

          /* Form grid: 1 kolom */
          .form-grid-2 { grid-template-columns: 1fr !important; }

          /* Topbar padding */
          .topbar { padding: 10px 14px !important; }

          /* Notif bell position */
          .notif-panel { right: 8px !important; }

          /* Dashboard: Alert+Compliance dan Jabatan+Pensiun jadi 1 kolom */
          .dash-grid-2 { grid-template-columns: 1fr !important; }
          .dash-grid-2 > * { min-width: 0 !important; }

          /* TabPanduan (Data Gaji): sidebar nav jadi row horizontal-scroll */
          .panduan-layout { flex-direction: column !important; height: auto !important; }
          .panduan-sidebar {
            width: 100% !important; flex-direction: row !important;
            flex-wrap: wrap; border-right: none !important;
            border-bottom: 1px solid var(--border);
          }

          /* Equipment & Operator: scrollbar fixed full lebar (bukan offset sidebar) */
          .equipment-scrollbar { left: 0 !important; }

          /* Slip Gaji: TTD preview grid jadi 2 kolom */
          .ttd-preview-grid { grid-template-columns: repeat(2, 1fr) !important; }

          /* Timesheet: sembunyikan kolom # dan persempit Nama/Badge */
          .ts-col-no { display: none !important; }
          .ts-col-nama {
            width: 110px !important; min-width: 110px !important;
            left: 0 !important;
          }
          .ts-col-nama > div:first-child { max-width: 95px !important; font-size: 10.5px !important; }
          .ts-col-nama > div:last-child { display: none !important; }
          .ts-col-badge {
            width: 70px !important; min-width: 70px !important;
            left: 110px !important; font-size: 9px !important;
          }
        }

        @media (max-width: 480px) {
          .stat-grid-4 { grid-template-columns: repeat(2, 1fr) !important; }
          .stat-grid-5 { grid-template-columns: repeat(2, 1fr) !important; }
          .hide-sm { display: none !important; }
        }

        /* Mobile sidebar overlay */
        .sidebar-overlay {
          display: none;
          position: fixed; inset: 0; z-index: 49;
          background: rgba(0,0,0,.5);
          backdrop-filter: blur(2px);
        }
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
          .sidebar-overlay.open { display: block; }
        }

        /* Mobile sidebar drawer */
        .sidebar-mobile {
          display: none;
          position: fixed; top: 0; left: 0; bottom: 0;
          width: 260px; z-index: 50;
          background: var(--sidebar-bg);
          border-right: 1px solid var(--border);
          flex-direction: column;
          transform: translateX(-100%);
          transition: transform .25s cubic-bezier(.22,.9,.25,1);
        }
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
          .sidebar-mobile { display: flex; }
          .sidebar-mobile.open { transform: translateX(0); }
        }

        /* Hamburger button */
        .hamburger {
          display: none;
          width: 36px; height: 36px; border-radius: 8px;
          background: var(--card); border: 1px solid var(--border);
          align-items: center; justify-content: center;
          cursor: pointer; font-size: 18px; flex-shrink: 0;
        }
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
          .hamburger { display: flex; }
        }

        .project-dropdown-mobile { display: none; }
        @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
          .project-dropdown-mobile { display: block !important; }
          .theme-toggle-desktop { display: none !important; }
        }
      `}</style>

      <ConfirmModal
        open={confirmLogout}
        onCancel={()=>setConfirmLogout(false)}
        onConfirm={()=>router.post('/logout')}
        title="Logout"
        icon={<LogOut size={28} color="#E04545"/>}
        message="Kamu akan keluar dari sistem. Yakin mau logout?"
        confirmLabel="Ya, Logout"
        type="danger"
      />

      <SnowCanvas dark={dark} />

      {/* ── DESKTOP SIDEBAR ───────────────────────────── */}
      <aside className="sidebar-desktop" style={{ width:220, flexShrink:0, background:'var(--sidebar-bg)', borderRight:'1px solid var(--border)', display:'flex', flexDirection:'column', position:'fixed', top:0, left:0, bottom:0, zIndex:50 }}>
        <SidebarContent url={url} authUser={authUser} onLogout={()=>setConfirmLogout(true)} />
      </aside>

      {/* ── MOBILE SIDEBAR OVERLAY ─────────────────────── */}
      <div className={`sidebar-overlay ${sidebarOpen?'open':''}`} onClick={()=>setSidebarOpen(false)} />

      {/* ── MOBILE SIDEBAR DRAWER ─────────────────────── */}
      <aside className={`sidebar-mobile ${sidebarOpen?'open':''}`}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 14px', borderBottom:'1px solid var(--border)' }}>
          <div style={{ display:'flex', alignItems:'center', gap:10 }}>
            <img src="/images/logo-akm.png" alt="PT. AKM"
              style={{height:32,width:'auto',objectFit:'contain'}} />
          </div>
          <div onClick={()=>setSidebarOpen(false)} style={{ cursor:'pointer', color:'var(--muted)', padding:'4px 8px', display:'flex' }}><X size={18}/></div>
        </div>
        <SidebarContent url={url} authUser={authUser} onLogout={()=>setConfirmLogout(true)} />
      </aside>

      {/* ── MAIN CONTENT ──────────────────────────────── */}
      <main className="main-content" style={{ marginLeft:220, flex:1, minHeight:'100vh', display:'flex', flexDirection:'column', position:'relative', zIndex:1, minWidth:0, overflow:'clip' }}>

        {/* TOPBAR */}
        <div className="topbar" style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'11px 24px', borderBottom:'1px solid var(--border)', background:'var(--topbar-bg)', position:'sticky', top:0, zIndex:40, gap:10 }}>

          {/* Left: hamburger + title */}
          <div style={{ display:'flex', alignItems:'center', gap:10, minWidth:0 }}>
            <div className="hamburger" onClick={()=>setSidebarOpen(true)}><Menu size={20}/></div>
            <div className="topbar-title" style={{ fontFamily:'Syne,sans-serif', fontSize:17, fontWeight:700, color:'var(--text)', whiteSpace:'nowrap', overflow:'visible', textOverflow:'ellipsis' }}>
              {title} <span style={{ color:'var(--accent)' }}>{subtitle}</span>
            </div>
          </div>

          {/* Right: clock + toggle + notif */}
          <div style={{ display:'flex', alignItems:'center', gap:8, flexShrink:0 }}>
            <RealtimeClock />
            <div className="theme-toggle-desktop">
              <ThemeToggle dark={dark} onToggle={toggleTheme} />
            </div>
            <ProjectDropdown authUser={authUser} />
            <div className="icon-btn" onClick={()=>setNotifOpen(!notifOpen)}>
              <Bell size={18}/>
              <div style={{ position:'absolute', top:6, right:6, width:7, height:7, borderRadius:'50%', background:'var(--red)', animation:'blink 1.5s infinite' }}/>
            </div>
          </div>
        </div>

        <NotifPanel open={notifOpen} onClose={()=>setNotifOpen(false)} />
        <FlashNotif />

        <div className="main-pad" style={{ padding:'22px 24px', flex:1, position:'relative', minWidth:0 }}>
          {children}
        </div>
      </main>
    </div>
  );
}

// ── Sidebar content (shared desktop & mobile) ──────────────
function SidebarContent({ url, authUser, onLogout }) {
  const { projects, active_project_id: activeProjectId } = usePage().props;
  const permissions = authUser?.permissions || [];
  const userProject = authUser?.project;
  const isSuperAdmin = authUser?.can?.is_super_admin;
  const isViewer = authUser?.can?.is_viewer;
  const isMultiProject = !isSuperAdmin && authUser?.project_ids?.length > 1;
  const multiProjects = isMultiProject ? projects?.filter(p => authUser.project_ids.includes(p.id)) : [];

  // Project HO (kantor pusat) tidak punya compliance/training/timesheet — sembunyikan menu itu.
  const currentProjectKode = (projects?.find(p => p.id === activeProjectId)?.kode || userProject?.kode || '').toLowerCase();
  const isHoProject = currentProjectKode === 'ho';
  const HO_HIDDEN_KEYS = ['sim', 'mcu', 'badge', 'ppe', 'ccpm', 'driver', 'equipment', 'timesheet', 'training'];
  // Kebalikannya: KPI cuma dipakai untuk Head Office, jadi sembunyikan menunya
  // kalau project yang lagi difilter/aktif BUKAN HO (mis. admin lagi lihat project Giam).
  const NON_HO_HIDDEN_KEYS = ['kpi'];
  const restrictPayroll = authUser?.can?.restrict_payroll;
  const PAYROLL_KEYS = ['slip-gaji', 'data-gaji'];
  // Menu 'pengaturan' sengaja tidak dicek lewat permission matriks (tetap kelihatan untuk semua,
  // aksesnya sendiri sudah digerbang lewat hasRole('super-admin') di controller-nya).
  const canViewMenu = (key) => key === 'pengaturan' || permissions.includes(`view-${key}`);
  const filteredNav = (isHoProject
      ? NAV.filter(item => !item.key || !HO_HIDDEN_KEYS.includes(item.key))
      : NAV.filter(item => !item.key || !NON_HO_HIDDEN_KEYS.includes(item.key)))
    .filter(item => !restrictPayroll || !item.key || !PAYROLL_KEYS.includes(item.key))
    .filter(item => !item.key || canViewMenu(item.key));
  // Buang judul section yang jadi kosong setelah item-nya difilter (mis. "Compliance" tanpa isi).
  const navItems = filteredNav.filter((item, i) => {
    if (!item.section) return true;
    const next = filteredNav[i + 1];
    return next && !next.section;
  });

  return (
    <>
      <div className="hide-mobile" style={{padding:'20px 16px 16px', borderBottom:'1px solid var(--border)'}}>
        <img src="/images/logo-akm.png" alt="PT. Andalas Karya Mulia"
          style={{height:58,width:'auto',objectFit:'contain',marginBottom:8,display:'block'}} />
        
        {/* Badge project aktif */}
        {userProject && !isMultiProject ? (() => {
          const warna = PROJECT_COLORS[userProject.kode] || userProject.warna;
          return (
          <div style={{marginTop:6,display:'inline-flex',alignItems:'center',gap:5,padding:'3px 8px',borderRadius:6,background:`${warna}20`,border:`1px solid ${warna}40`}}>
            <div style={{width:6,height:6,borderRadius:'50%',background:warna}}/>
            <span style={{fontSize:10,fontWeight:700,color:warna}}>{userProject.nama}</span>
          </div>
          );
        })() : isSuperAdmin ? (
          <div style={{marginTop:6,display:'inline-flex',alignItems:'center',gap:5,padding:'3px 8px',borderRadius:6,background:'rgba(232,160,32,.1)',border:'1px solid rgba(232,160,32,.2)'}}>
            <span style={{fontSize:10,fontWeight:700,color:'var(--accent)'}}>Project</span>
          </div>
        ) : isViewer ? (
          <div style={{marginTop:6,display:'inline-flex',alignItems:'center',gap:5,padding:'3px 8px',borderRadius:6,background:'rgba(58,143,224,.1)',border:'1px solid rgba(58,143,224,.2)'}}>
            <Eye size={11} color="var(--blue)"/>
            <span style={{fontSize:10,fontWeight:700,color:'var(--blue)'}}>Viewer</span>
          </div>
        ) : null}

        {/* Kalau super admin, tampilkan semua project sebagai switcher */}
        {(isSuperAdmin || isViewer) && projects?.length > 0 && (() => {
          const pillBase = {
            appearance:'none', WebkitAppearance:'none', outline:'none', lineHeight:'normal',
            fontFamily:"'Outfit',sans-serif",
            fontSize:9, padding:'2px 6px', borderRadius:4, fontWeight:700,
            cursor:'pointer', transition:'all .15s',
          };
          const fieldProjects = projects.filter(p => p.kode.toUpperCase() !== 'HO');
          const hoProject = projects.find(p => p.kode.toUpperCase() === 'HO');
          return (
            <>
              <div style={{marginTop:8,display:'flex',flexWrap:'wrap',gap:4}}>
                {/* Tombol "Semua" */}
                <button onClick={()=>router.post('/switch-project',{project:'all'})}
                  style={{
                    ...pillBase, padding:'2px 8px',
                    background: !activeProjectId ? 'var(--accent)' : 'var(--border2)',
                    color: !activeProjectId ? '#0C0F14' : 'var(--text)',
                    border: !activeProjectId ? '1px solid var(--accent)' : '1px solid var(--border2)',
                  }}>
                  SEMUA
                </button>
                {fieldProjects.map(p => {
                  const isActive = activeProjectId === p.id;
                  const warna = PROJECT_COLORS[p.kode] || p.warna;
                  return (
                    <button key={p.id} onClick={()=>router.post('/switch-project',{project:p.kode})}
                      style={{
                        ...pillBase,
                        background: isActive ? warna : 'var(--border2)',
                        color: isActive ? '#fff' : 'var(--text)',
                        border: isActive ? `1px solid ${warna}` : '1px solid var(--border2)',
                        boxShadow: isActive ? `0 0 8px ${warna}60` : 'none',
                      }}>
                      {p.kode.toUpperCase()}
                    </button>
                  );
                })}
              </div>
              {hoProject && (() => {
                const isActive = activeProjectId === hoProject.id;
                const warna = PROJECT_COLORS[hoProject.kode] || hoProject.warna;
                return (
                  <div style={{marginTop:7,paddingTop:7,borderTop:'1px solid var(--border)',display:'flex',alignItems:'center',gap:6}}>
                    <span style={{fontSize:8.5,fontWeight:700,color:'var(--muted)',textTransform:'uppercase',letterSpacing:'.05em',whiteSpace:'nowrap',display:'inline-flex',alignItems:'center',gap:4}}>
                      <Building2 size={10}/> Kantor Pusat
                    </span>
                    <button onClick={()=>router.post('/switch-project',{project:hoProject.kode})}
                      title="Kantor Pusat — bukan project lapangan"
                      style={{
                        ...pillBase,
                        background: isActive ? warna : 'transparent',
                        color: isActive ? '#fff' : warna,
                        border: `1px solid ${warna}`,
                        boxShadow: isActive ? `0 0 8px ${warna}60` : 'none',
                      }}>
                      {hoProject.kode.toUpperCase()}
                    </button>
                  </div>
                );
              })()}
            </>
          );
        })()}
        {/* Multi-project user switcher (Dedi dll) */}
        {isMultiProject && multiProjects.length > 0 && (
          <div style={{marginTop:8,display:'flex',flexWrap:'wrap',gap:4}}>
            {multiProjects.map(p => {
              const isActive = activeProjectId === p.id;
              const warna = PROJECT_COLORS[p.kode] || p.warna;
              return (
                <button key={p.id} onClick={()=>router.post('/switch-project',{project:p.kode})}
                  style={{
                    appearance:'none', WebkitAppearance:'none', outline:'none', lineHeight:'normal',
                    fontFamily:"'Outfit',sans-serif",
                    fontSize:9, padding:'2px 6px', borderRadius:4, fontWeight:700,
                    background: isActive ? warna : 'var(--border2)',
                    color: isActive ? '#fff' : 'var(--text)',
                    border: isActive ? `1px solid ${warna}` : '1px solid var(--border2)',
                    cursor:'pointer',
                    boxShadow: isActive ? `0 0 8px ${warna}60` : 'none',
                    transition:'all .15s',
                  }}>
                  {p.kode.toUpperCase()}
                </button>
              );
            })}
          </div>
        )}
      </div>

      <nav style={{ flex:1, padding:'12px 8px', display:'flex', flexDirection:'column', gap:2, overflowY:'auto' }}>
        {navItems.map((item, i) => {

          return item.section ? (
            <div key={i} style={{fontSize:9.5,color:'var(--muted)',textTransform:'uppercase',letterSpacing:'.1em',fontWeight:600,padding:'10px 8px 4px'}}>{item.section}</div>
          ) : (
            <Link key={item.key} href={item.href}
              className={`nav-item ${url===item.href||(item.href!=='/'&&url.startsWith(item.href))?'active':''}`}>
              <span style={{width:18,display:'flex',justifyContent:'center',flexShrink:0}}><item.icon size={15}/></span>
              {item.label}
              {item.badge && <span style={{marginLeft:'auto',background:'var(--blue)',color:'#fff',fontSize:9.5,fontWeight:700,borderRadius:99,padding:'1px 6px'}}>{item.badge}</span>}
            </Link>
          );
        })}
      </nav>

      <ProfileMenu authUser={authUser} onLogout={onLogout} />
    </>
  );
}
