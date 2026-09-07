// resources/js/Pages/Dashboard/Index.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { KaryawanPerProjectChart, PengeluaranGajiChart, LengthOfServiceChart } from '@/Components/DashboardCharts';
import {
  TriangleAlert, X, User, Building2, Briefcase, CreditCard, Stethoscope, Siren,
  CheckCircle2, BarChart3, Users, ClipboardList, FileClock,
} from 'lucide-react';

const avatarColors = ['#3A8FE0','#22C97A','#E8A020','#E04545','#9B59B6','#E06A20','#3ABCDE','#C97A22'];
function getAv(nama){ return (nama||'?').split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase(); }
function getAvColor(badge){ const i=parseInt((badge||'').replace(/\D/g,''))%avatarColors.length; return avatarColors[i||0]; }

function useSessionAlert(key) {
  const [show, setShow] = useState(() => {
    try { return sessionStorage.getItem(key) !== 'closed'; }
    catch { return true; }
  });
  function close() {
    try { sessionStorage.setItem(key, 'closed'); } catch {}
    setShow(false);
  }
  return [show, close];
}

function getSisaUrgent(e) {
  const candidates = [];
  if ((e.sim_status==='warning'||e.sim_status==='expired') && (e.sisa_sim??9999) >= -90)
    candidates.push(e.sisa_sim);
  if ((e.mcu_status==='warning'||e.mcu_status==='expired') && (e.sisa_mcu??9999) >= -90)
    candidates.push(e.sisa_mcu);
  if ((e.badge_status==='warning'||e.badge_status==='expired') && (e.sisa_badge??9999) >= -90)
    candidates.push(e.sisa_badge);
  if (candidates.length === 0) return 9999;
  return Math.min(...candidates);
}

const JABATAN_COLORS = ['#3A8FE0','#22C97A','#E8A020','#9B59B6','#E04545','#3ABCDE','#8A90A8'];

export default function Dashboard({
  stats={},
  alert_employees=[],
  jabatan_stats=[],
  masa_kerja_stats=[],
  contract_probation_alerts=[],
  karyawanPerProject=[],
  pengeluaranGaji=[],
  projectKeys=[],
  isMultiProject=false,
  project_info=null,
}){
  const isHo = project_info?.tipe_gaji === 'ho';
  const [showAlert, closeAlert] = useSessionAlert('dashboard_alert_closed');

  const s = {
    total:      stats.total_karyawan || 0,
    simExp:     stats.sim_expired    || 0,
    mcuExp:     stats.mcu_expired    || 0,
    badgeAlert: (stats.badge_expired||0) + (stats.badge_warning||0),
    badgeExp:   stats.badge_expired  || 0,
    badgeWarn:  stats.badge_warning  || 0,
  };

  const jabatanTotal = jabatan_stats.reduce((acc, j) => acc + (j.total || 0), 0) || 1;
  const jabatanTop = jabatan_stats.slice(0, 6);
  const jabatanSisa = jabatan_stats.slice(6).reduce((acc, j) => acc + (j.total || 0), 0);
  const jabatanSegments = [
    ...jabatanTop.map((j, i) => ({ label: j.jabatan || '—', total: j.total, color: JABATAN_COLORS[i % JABATAN_COLORS.length] })),
    ...(jabatanSisa > 0 ? [{ label: 'Lainnya', total: jabatanSisa, color: '#4A5070' }] : []),
  ];

  const filteredAlerts = alert_employees
    .filter(e => getSisaUrgent(e) < 9999)
    .sort((a, b) => {
      const sisaA = getSisaUrgent(a);
      const sisaB = getSisaUrgent(b);
      const aExpired = sisaA < 0;
      const bExpired = sisaB < 0;
      if (!aExpired && bExpired) return -1;
      if (aExpired && !bExpired) return 1;
      if (!aExpired && !bExpired) return sisaA - sisaB;
      return sisaB - sisaA;
    });

  const hasAlert = !isHo && (s.simExp > 0 || s.mcuExp > 0 || s.badgeExp > 0 || s.badgeWarn > 0);

  const totalGaji12Bulan = pengeluaranGaji.reduce((acc, m) => acc + (m.total||0), 0);
  const totalKaryawanAktif = karyawanPerProject.reduce((acc, p) => acc + (p.total||0), 0);

  return (
    <AppLayout title="Dashboard" subtitle="HRIS">

      {/* BANNER ALERT */}
      {showAlert && hasAlert && (
        <div style={{display:'flex',alignItems:'center',gap:12,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)',borderRadius:10,padding:'11px 16px',marginBottom:20}}>
          <span style={{display:'flex'}}><TriangleAlert size={18}/></span>
          <div style={{fontSize:12.5,color:'#F09090',flex:1}}>
            {s.simExp   > 0 && <><b style={{color:'#E04545'}}>{s.simExp} SIM</b> sudah expired · </>}
            {s.mcuExp   > 0 && <><b style={{color:'#E04545'}}>{s.mcuExp} MCU</b> expired · </>}
            {s.badgeExp > 0 && <><b style={{color:'#E04545'}}>{s.badgeExp} Badge</b> expired · </>}
            {s.badgeWarn> 0 && <><b style={{color:'#E04545'}}>{s.badgeWarn} Badge</b> akan expired dalam 30 hari</>}
          </div>
          <div onClick={closeAlert} style={{fontSize:11,color:'#6B7494',cursor:'pointer',padding:'4px 10px',borderRadius:6,border:'1px solid rgba(224,69,69,.2)',background:'rgba(224,69,69,.08)',display:'flex',alignItems:'center',gap:4,transition:'all .15s',userSelect:'none'}}
            onMouseEnter={e=>{e.currentTarget.style.background='rgba(224,69,69,.15)';e.currentTarget.style.color='#E04545';}}
            onMouseLeave={e=>{e.currentTarget.style.background='rgba(224,69,69,.08)';e.currentTarget.style.color='#6B7494';}}
          ><X size={12}/> Tutup</div>
        </div>
      )}

      {/* STATS CARDS */}
      <div style={{display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:14,marginBottom:20}} className="stat-grid-4">
        {(isHo ? [
          { label:'Total Manpower',  val:s.total,                          sub:'Karyawan aktif — Kantor Pusat',   color:'#3A8FE0', icon:User, pct:100, href:'/employees' },
          { label:'Karyawan HO-1',   val:stats.ho_unit?.['HO-1'] || 0,     sub:'Karyawan aktif di unit HO-1',     color:'#22C97A', icon:Building2, pct:Math.round((stats.ho_unit?.['HO-1']||0)/Math.max(s.total,1)*100), href:'/employees' },
          { label:'Karyawan HO-2',   val:stats.ho_unit?.['HO-2'] || 0,     sub:'Karyawan aktif di unit HO-2',     color:'#9B59B6', icon:Building2, pct:Math.round((stats.ho_unit?.['HO-2']||0)/Math.max(s.total,1)*100), href:'/employees' },
          { label:'Jabatan Terbanyak', val:stats.ho_top_jabatan?.val || 0, sub:stats.ho_top_jabatan?.label || '—', color:'#E8A020', icon:Briefcase, pct:Math.round((stats.ho_top_jabatan?.val||0)/Math.max(s.total,1)*100), href:'/employees' },
        ] : [
          { label:'Total Manpower',   val:s.total,      sub:'Semua aktif — GROUP AKM',                              color:'#3A8FE0', icon:User, pct:100,                                                href:'/employees' },
          { label:'SIM Expired',      val:s.simExp,     sub:'Perlu perpanjangan segera',                             color:'#E04545', icon:CreditCard, pct:Math.round(s.simExp/Math.max(s.total,1)*100),    href:'/compliance/sim' },
          { label:'MCU Expired',      val:s.mcuExp,     sub:'Medical check-up lewat masa berlaku',                   color:'#E06A20', icon:Stethoscope, pct:Math.round(s.mcuExp/Math.max(s.total,1)*100),    href:'/compliance/mcu' },
          { label:'Badge & KP Alert', val:s.badgeAlert, sub:`${s.badgeExp} expired + ${s.badgeWarn} dalam 30 hari`, color:'#E8C030', icon:Siren, pct:Math.round(s.badgeAlert/Math.max(s.total,1)*100), href:'/compliance/badge' },
        ]).map((item,i)=>(
          <div key={i} className="stat" onClick={()=>router.visit(item.href)}
            style={{animationDelay:`${i*0.05+0.05}s`,animation:'fadeUp .5s both',cursor:'pointer'}}>
            <span style={{position:'absolute',top:16,right:16,opacity:.25,display:'flex'}}><item.icon size={22}/></span>
            <div style={{fontSize:10.5,color:'#6B7494',textTransform:'uppercase',letterSpacing:'.07em',fontWeight:600}}>{item.label}</div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:34,fontWeight:700,lineHeight:1.1,margin:'6px 0 4px',color:item.color}}>{item.val}</div>
            <div style={{fontSize:11,color:'#6B7494'}}>{item.sub}</div>
            <div style={{height:3,borderRadius:99,background:'rgba(255,255,255,.07)',marginTop:10,overflow:'hidden'}}>
              <div style={{height:'100%',borderRadius:99,background:item.color,width:`${item.pct}%`,transition:'width 1.5s'}}/>
            </div>
            <div style={{position:'absolute',bottom:0,left:0,right:0,height:2,background:item.color,opacity:.4}}/>
          </div>
        ))}
      </div>

      {/* ROW: ALERTS + COMPLIANCE + CHART KARYAWAN */}
      <div className="dash-grid-2" style={{display:'grid',gridTemplateColumns:isHo?'1fr':'3fr 2fr',gap:16,marginBottom:16,alignItems:'start'}}>

        {/* ALERT TABLE */}
        {!isHo && (
        <div className="panel" style={{display:'flex',flexDirection:'column',paddingBottom:0}}>
          <div className="panel-head" style={{flexShrink:0}}>
            <div className="panel-title">
              <Siren size={13} style={{verticalAlign:-2}}/> Compliance Alerts
              <span style={{marginLeft:6,background:'rgba(224,69,69,.15)',color:'#E04545',fontSize:10,fontWeight:700,borderRadius:99,padding:'1px 7px',display:'inline-block'}}>
                {filteredAlerts.length}
              </span>
            </div>
            <a href="/compliance/sim" style={{fontSize:11.5,color:'#E8A020',textDecoration:'none'}}>Lihat Semua →</a>
          </div>

          <div className="table-wrap" style={{overflowY:'auto',overflowX:'auto',flex:1,minHeight:0,maxHeight:560}}>
            <table className="kar-table" style={{minWidth:560}}>
              <thead style={{position:'sticky',top:0,zIndex:2,background:'var(--card)'}}>
                <tr>
                  <th style={{padding:'10px 16px'}}>Karyawan</th>
                  <th style={{padding:'10px 8px'}}>Jabatan</th>
                  <th style={{padding:'10px 8px'}}>Alert</th>
                  <th style={{padding:'10px 8px',textAlign:'center'}}>Sisa Hari</th>
                  <th style={{padding:'10px 8px'}}>Tgl Expired</th>
                </tr>
              </thead>
              <tbody>
                {filteredAlerts.map((e,i)=>{
                  const sisaUrgent = getSisaUrgent(e);
                  const isExpired  = sisaUrgent < 0;

                  let tglExp = '—';
                  if (sisaUrgent === (e.sisa_sim??9999)   && (e.sisa_sim??9999)   >= -90) tglExp = e.expired_sim_fmt  || '—';
                  else if (sisaUrgent === (e.sisa_mcu??9999)   && (e.sisa_mcu??9999)   >= -90) tglExp = e.exp_mcu_fmt      || '—';
                  else if (sisaUrgent === (e.sisa_badge??9999) && (e.sisa_badge??9999) >= -90) tglExp = e.expire_badge_fmt || '—';

                  function handleClick() {
                    if (sisaUrgent === (e.sisa_sim??9999)   && (e.sisa_sim??9999)   >= -90) return router.visit(`/compliance/sim?highlight=${e.id}`);
                    if (sisaUrgent === (e.sisa_mcu??9999)   && (e.sisa_mcu??9999)   >= -90) return router.visit(`/compliance/mcu?highlight=${e.id}`);
                    if (sisaUrgent === (e.sisa_badge??9999) && (e.sisa_badge??9999) >= -90) return router.visit(`/compliance/badge?highlight=${e.id}`);
                    router.visit(`/compliance/sim?highlight=${e.id}`);
                  }

                  return (
                    <tr key={i}
                      style={{...(isExpired?{background:'rgba(224,69,69,.04)'}:{}),cursor:'pointer'}}
                      onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background=isExpired?'rgba(224,69,69,.08)':'rgba(232,160,32,.04)')}
                      onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}
                      onClick={handleClick}>
                      <td style={{padding:'10px 16px'}}>
                        <div style={{display:'flex',alignItems:'center',gap:9}}>
                          <div style={{width:30,height:30,borderRadius:7,background:getAvColor(e.id_badge),display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,color:'#fff',flexShrink:0}}>
                            {getAv(e.nama)}
                          </div>
                          <div>
                            <div style={{fontSize:12.5,fontWeight:600}}>{e.nama}</div>
                            <div style={{fontSize:10.5,color:'#6B7494'}}>{e.id_badge}</div>
                          </div>
                        </div>
                      </td>
                      <td style={{padding:'10px 8px',fontSize:12,color:'var(--muted2)'}}>{e.jabatan}</td>
                      <td style={{padding:'10px 8px'}}>
                        <div style={{display:'flex',gap:5}}>
                          {(()=>{
                            const sUrgent = sisaUrgent;
                            if (sUrgent === (e.sisa_sim??9999)   && (e.sisa_sim??9999)   >= -90)
                              return e.sim_status==='expired'
                                ? <span className="pill pill-red">SIM</span>
                                : <span className="pill pill-warn">SIM</span>;
                            if (sUrgent === (e.sisa_mcu??9999)   && (e.sisa_mcu??9999)   >= -90)
                              return e.mcu_status==='expired'
                                ? <span className="pill pill-red">MCU</span>
                                : <span className="pill pill-warn">MCU</span>;
                            if (sUrgent === (e.sisa_badge??9999) && (e.sisa_badge??9999) >= -90)
                              return e.badge_status==='expired'
                                ? <span className="pill pill-red">Badge</span>
                                : <span className="pill pill-warn">Badge</span>;
                          })()}
                        </div>
                      </td>
                      <td style={{padding:'10px 8px',textAlign:'center'}}>
                        <span style={{fontSize:13,fontWeight:700,color:isExpired?'#E04545':sisaUrgent<=7?'#E04545':'var(--accent)'}}>
                          {sisaUrgent===0?'Hari ini!':sisaUrgent>0?`${sisaUrgent} hari lagi`:`${Math.abs(sisaUrgent)} hr lalu`}
                        </span>
                      </td>
                      <td style={{padding:'10px 8px',fontSize:12,color:isExpired?'#E04545':'var(--muted2)',whiteSpace:'nowrap'}}>{tglExp}</td>
                    </tr>
                  );
                })}
                {filteredAlerts.length===0&&(
                  <tr><td colSpan={5} style={{padding:'24px 16px',textAlign:'center',color:'#6B7494'}}><CheckCircle2 size={13} style={{verticalAlign:-2}}/> Tidak ada compliance alert</td></tr>
                )}
              </tbody>
            </table>
          </div>

          {filteredAlerts.length > 0 && (
            <div style={{padding:'8px 16px',borderTop:'1px solid var(--border)',fontSize:11,color:'var(--muted)',display:'flex',justifyContent:'space-between',flexShrink:0}}>
              <span>
                <b style={{color:'#E04545'}}>{filteredAlerts.filter(e=>getSisaUrgent(e)<0).length}</b> expired
                {' · '}
                <b style={{color:'var(--accent)'}}>{filteredAlerts.filter(e=>getSisaUrgent(e)>=0).length}</b> akan expired
              </span>
              <span>Total {filteredAlerts.length} alert</span>
            </div>
          )}
        </div>
        )}

        {/* RIGHT COL — Status Compliance + Karyawan per Project */}
        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {!isHo && (
          <div className="panel">
            <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><BarChart3 size={13}/> Status Compliance</div></div>
            <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:12,padding:'14px 16px'}}>
              {[
                { label:'KP Status',   pct: s.total>0?Math.round((stats.kp_ada||0)/s.total*100):0,    color:'#22C97A' },
                { label:'SIM Expired', pct: s.total>0?Math.round(s.simExp/s.total*100):0,             color:'#E04545' },
                { label:'MCU OK',      pct: s.total>0?Math.round((stats.mcu_ok||0)/s.total*100):0,    color:'#3A8FE0' },
                { label:'SIO Valid',   pct: s.total>0?Math.round((s.total-(stats.sio_expired||0))/s.total*100):0, color:'#E8A020' },
              ].map((c,i)=>{
                const r=28,circ=2*Math.PI*r,dash=(c.pct/100)*circ;
                return (
                  <div key={i} style={{textAlign:'center'}}>
                    <div style={{position:'relative',width:72,height:72,margin:'0 auto 8px'}}>
                      <svg width="72" height="72" viewBox="0 0 72 72" style={{transform:'rotate(-90deg)'}}>
                        <circle cx="36" cy="36" r={r} fill="none" stroke="rgba(255,255,255,.07)" strokeWidth="10"/>
                        <circle cx="36" cy="36" r={r} fill="none" stroke={c.color} strokeWidth="10"
                          strokeDasharray={`${dash} ${circ-dash}`} strokeLinecap="round"/>
                      </svg>
                      <div style={{position:'absolute',inset:0,display:'flex',alignItems:'center',justifyContent:'center',fontFamily:'Syne,sans-serif',fontSize:14,fontWeight:700,color:c.color}}>
                        {c.pct}%
                      </div>
                    </div>
                    <div style={{fontSize:10.5,color:'#6B7494',fontWeight:500}}>{c.label}</div>
                  </div>
                );
              })}
            </div>
          </div>
          )}

          {/* ── PANEL BARU: Karyawan per Project ── */}
          <div className="panel">
            <div className="panel-head">
              <div className="panel-title">
                <Users size={13} style={{verticalAlign:-2}}/> Karyawan per Project
                <span style={{marginLeft:6,fontSize:11,color:'var(--muted)',fontWeight:400}}>
                  — {totalKaryawanAktif} aktif
                </span>
              </div>
            </div>
            <div style={{padding:'10px 8px 14px'}}>
              <KaryawanPerProjectChart data={karyawanPerProject} />
            </div>
          </div>
        </div>
      </div>

      {/* ROW: JABATAN (compact) + MASA KERJA + PENGELUARAN GAJI */}
      <div className="dash-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:16,alignItems:'stretch'}}>
        <div style={{display:'flex',flexDirection:'column',gap:16}}>
          {/* ── Komposisi Jabatan — compact: 1 stacked bar + legend list ── */}
          <div className="panel">
            <div className="panel-head">
              <div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><ClipboardList size={13}/> Komposisi Jabatan</div>
              <a href="/employees" style={{fontSize:11.5,color:'#E8A020',textDecoration:'none'}}>Detail →</a>
            </div>
            <div style={{padding:'14px 16px'}}>
              <div style={{display:'flex',height:10,borderRadius:99,overflow:'hidden',marginBottom:6}}>
                {jabatanSegments.map((s,i)=>(
                  <div key={i} title={`${s.label}: ${s.total}`} style={{width:`${(s.total/jabatanTotal)*100}%`,background:s.color,transition:'width 1.2s'}}/>
                ))}
              </div>
              <div style={{display:'flex',justifyContent:'space-between',fontSize:10,color:'#6B7494',marginBottom:12}}>
                <span>0%</span><span>Total {jabatanTotal}</span><span>100%</span>
              </div>
              <div style={{display:'flex',flexDirection:'column',gap:8}}>
                {jabatanSegments.map((s,i)=>(
                  <div key={i} style={{display:'flex',alignItems:'center',gap:8}}>
                    <span style={{width:9,height:9,borderRadius:3,background:s.color,flexShrink:0}}/>
                    <span style={{fontSize:12,color:'var(--text)',flex:1,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{s.label}</span>
                    <span style={{fontSize:11.5,fontWeight:600,color:'#8A90A8'}}>{s.total}</span>
                    <span style={{fontSize:10.5,color:'#6B7494',width:38,textAlign:'right'}}>{((s.total/jabatanTotal)*100).toFixed(1)}%</span>
                  </div>
                ))}
                {jabatan_stats.length===0&&<div style={{color:'#6B7494',fontSize:12}}>Belum ada data jabatan</div>}
              </div>
            </div>
          </div>

          {/* ── PANEL BARU: Masa Kerja (Length of Service) ── */}
          <div className="panel">
            <div className="panel-head"><div className="panel-title" style={{display:'flex',alignItems:'center',gap:6}}><BarChart3 size={13}/> Masa Kerja</div></div>
            <div style={{padding:'10px 8px 14px'}}>
              <LengthOfServiceChart data={masa_kerja_stats} />
            </div>
          </div>
        </div>

        {/* ── PANEL BARU: Pengeluaran Gaji 12 Bulan ── */}
        <div className="panel">
          <div className="panel-head">
            <div className="panel-title">
              Pengeluaran Gaji 12 Bulan Terakhir
              <span style={{marginLeft:6,fontSize:11,color:'var(--muted)',fontWeight:400}}>
                — Total Rp {Number(totalGaji12Bulan).toLocaleString('id-ID')}
              </span>
            </div>
          </div>
          <div style={{padding:'10px 8px 14px'}}>
            <PengeluaranGajiChart
                data={pengeluaranGaji}
                projectKeys={projectKeys}
                isMultiProject={isMultiProject}
            />
          </div>
        </div>
      </div>

      {/* ── PANEL BARU: Contract & Probation Reminder ── */}
      <div className="panel" style={{marginTop:16}}>
        <div className="panel-head">
          <div className="panel-title">
            <FileClock size={13} style={{verticalAlign:-2}}/> Contract & Probation
            <span style={{marginLeft:6,background:'rgba(232,160,32,.15)',color:'#E8A020',fontSize:10,fontWeight:700,borderRadius:99,padding:'1px 7px',display:'inline-block'}}>
              {contract_probation_alerts.length}
            </span>
          </div>
        </div>
        <div className="table-wrap" style={{overflowX:'auto',maxHeight:420,overflowY:'auto'}}>
          <table className="kar-table" style={{minWidth:560}}>
            <thead style={{position:'sticky',top:0,zIndex:2,background:'var(--card)'}}>
              <tr>
                <th style={{padding:'10px 16px'}}>Karyawan</th>
                <th style={{padding:'10px 8px'}}>Jabatan</th>
                <th style={{padding:'10px 8px'}}>Status</th>
                <th style={{padding:'10px 8px',textAlign:'center'}}>Sisa Hari</th>
                <th style={{padding:'10px 8px'}}>Tgl Berakhir</th>
              </tr>
            </thead>
            <tbody>
              {contract_probation_alerts.map((c,i)=>{
                const isOver = c.sisa_hari < 0;
                return (
                  <tr key={i}
                    style={{...(isOver?{background:'rgba(224,69,69,.04)'}:{}),cursor:'pointer'}}
                    onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(cell=>cell.style.background=isOver?'rgba(224,69,69,.08)':'rgba(232,160,32,.04)')}
                    onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(cell=>cell.style.background='')}
                    onClick={()=>router.visit(`/employees?highlight=${c.id}`)}>
                    <td style={{padding:'10px 16px'}}>
                      <div style={{display:'flex',alignItems:'center',gap:9}}>
                        <div style={{width:30,height:30,borderRadius:7,background:getAvColor(c.id_badge),display:'flex',alignItems:'center',justifyContent:'center',fontSize:11,fontWeight:700,color:'#fff',flexShrink:0}}>
                          {getAv(c.nama)}
                        </div>
                        <div>
                          <div style={{fontSize:12.5,fontWeight:600}}>{c.nama}</div>
                          <div style={{fontSize:10.5,color:'#6B7494'}}>{c.id_badge}</div>
                        </div>
                      </div>
                    </td>
                    <td style={{padding:'10px 8px',fontSize:12,color:'var(--muted2)'}}>{c.jabatan}</td>
                    <td style={{padding:'10px 8px'}}>
                      <span className={c.tipe==='Kontrak' ? 'pill pill-blue' : 'pill pill-orange'}>{c.tipe}</span>
                    </td>
                    <td style={{padding:'10px 8px',textAlign:'center'}}>
                      <span style={{fontSize:13,fontWeight:700,color:isOver?'#E04545':c.sisa_hari<=7?'#E04545':'var(--accent)'}}>
                        {c.sisa_hari===0?'Hari ini!':c.sisa_hari>0?`${c.sisa_hari} hari lagi`:`${Math.abs(c.sisa_hari)} hr lalu`}
                      </span>
                    </td>
                    <td style={{padding:'10px 8px',fontSize:12,color:isOver?'#E04545':'var(--muted2)',whiteSpace:'nowrap'}}>{c.tanggal_fmt}</td>
                  </tr>
                );
              })}
              {contract_probation_alerts.length===0&&(
                <tr><td colSpan={5} style={{padding:'24px 16px',textAlign:'center',color:'#6B7494'}}><CheckCircle2 size={13} style={{verticalAlign:-2}}/> Tidak ada kontrak/probation yang mau berakhir</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

    </AppLayout>
  );
}