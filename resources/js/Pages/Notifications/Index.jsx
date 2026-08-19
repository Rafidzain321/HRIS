// resources/js/Pages/Notifications/Index.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert, Circle, Bell } from 'lucide-react';

const TAB_CONFIG = [
  { key:'mcu',        label:'MCU',              color:'#E06A20' },
  { key:'badge',      label:'Badge',             color:'#3A8FE0' },
  { key:'kp',         label:'KP',               color:'#22C97A' },
  { key:'sim',        label:'SIM',              color:'#9B59B6' },
  { key:'equip_unit', label:'Equipment Unit',   color:'#E04545' },
  { key:'equip_op',   label:'Equipment Operator',color:'#C97A22' },
  { key:'pensiun',    label:'Pensiun',           color:'#E04545' },
];

// Tab yang tidak relevan untuk Kantor Pusat (HO) — karyawan HO tidak punya
// MCU/Badge/KP/SIM/Equipment karena bukan pekerja lapangan.
const HO_HIDDEN_TABS = ['mcu', 'badge', 'kp', 'sim', 'equip_unit', 'equip_op'];

function DaysCell({ days, level }) {
  if (days === null || days === undefined) return <span style={{color:'var(--muted)'}}>—</span>;
  if (level === 'expired') {
    return (
      <span style={{color:'#E04545',fontWeight:700}}>
        {Math.abs(days)} hr lalu
      </span>
    );
  }
  if (days === 0) return <span style={{color:'#E04545',fontWeight:700}}>Hari ini!</span>;
  if (days <= 7)  return <span style={{color:'#E04545',fontWeight:700}}>{days} hr lagi</span>;
  if (days <= 30) return <span style={{color:'#E8A020',fontWeight:600}}>{days} hr lagi</span>;
  return <span style={{color:'#22C97A',fontWeight:500}}>{days} hr lagi</span>;
}

function LevelPill({ level }) {
  if (level === 'expired') return (
    <span style={{background:'rgba(224,69,69,.12)',color:'#E04545',padding:'2px 9px',borderRadius:99,fontSize:10.5,fontWeight:700}}>
      Expired
    </span>
  );
  return (
    <span style={{background:'rgba(232,160,32,.12)',color:'var(--accent)',padding:'2px 9px',borderRadius:99,fontSize:10.5,fontWeight:700}}>
      &lt;30hr
    </span>
  );
}

function NotifTable({ items, emptyMsg, isPensiun }) {
  if (!items || items.length === 0) {
    return (
      <div style={{padding:'48px 16px',textAlign:'center',color:'var(--muted)',fontSize:13,display:'flex',alignItems:'center',justifyContent:'center',gap:6}}>
        <CheckCircle2 size={14}/> {emptyMsg || 'Tidak ada notifikasi'}
      </div>
    );
  }

  const warnings = items.filter(i => i.level === 'warning');
  const expireds = items.filter(i => i.level === 'expired');

  const colSpanTotal = isPensiun ? 10 : 8;

  const RowItem = ({ item, i, prefix }) => (
    <tr key={`${prefix}-${i}`}
      style={prefix==='e' ? {background:'rgba(224,69,69,.03)'} : {}}
      onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background=prefix==='e'?'rgba(224,69,69,.07)':'rgba(232,160,32,.04)')}
      onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
      <td style={{fontWeight:500,paddingLeft:16}}>{item.nama}</td>
      <td style={{fontFamily:'monospace',fontSize:11.5,color:'var(--accent)'}}>{item.badge}</td>
      <td style={{fontSize:12,color:'var(--muted2)'}}>{item.jabatan}</td>
      <td>
        <span style={{
          background: prefix==='e' ? 'rgba(224,69,69,.1)' : 'rgba(232,160,32,.1)',
          color: prefix==='e' ? '#E04545' : 'var(--accent)',
          padding:'2px 8px', borderRadius:6, fontSize:11, fontWeight:600,
        }}>
          {item.label}
        </span>
      </td>
      {isPensiun && (
        <td style={{textAlign:'center',fontSize:12,color:'var(--muted2)'}}>
          {item.tanggal_lahir}
        </td>
      )}
      {isPensiun && (
        <td style={{textAlign:'center',fontWeight:700,color: prefix==='e'?'#E04545':'var(--accent)'}}>
          {Math.floor(item.usia ?? 0)} thn
        </td>
      )}
      <td style={{fontSize:12,whiteSpace:'nowrap',color:prefix==='e'?'#E04545':'var(--text)'}}>
        {item.date}
      </td>
      <td style={{textAlign:'center'}}><DaysCell days={item.days} level={item.level}/></td>
      <td style={{textAlign:'center'}}><LevelPill level={item.level}/></td>
      <td style={{textAlign:'center'}}>
        <button onClick={()=>{
          const sep = item.href.includes('?') ? '&' : '?';
          router.visit(`${item.href}${sep}highlight=${item.id}`);
        }}
          style={{
            padding:'5px 12px', borderRadius:7, fontSize:11, fontWeight:700,
            background: prefix==='e'
              ? 'linear-gradient(135deg,#E04545,#A01010)'
              : 'linear-gradient(135deg,#E8A020,#A06010)',
            color: '#fff',
            border: 'none', cursor:'pointer',
            display:'inline-flex', alignItems:'center', gap:5,
            boxShadow: prefix==='e' ? '0 2px 8px rgba(224,69,69,.3)' : '0 2px 8px rgba(232,160,32,.3)',
            transition:'all .15s',
            fontFamily:"'Outfit',sans-serif",
          }}
          onMouseEnter={e=>{e.currentTarget.style.transform='translateY(-1px)';e.currentTarget.style.boxShadow=prefix==='e'?'0 4px 12px rgba(224,69,69,.4)':'0 4px 12px rgba(232,160,32,.4)';}}
          onMouseLeave={e=>{e.currentTarget.style.transform='';e.currentTarget.style.boxShadow=prefix==='e'?'0 2px 8px rgba(224,69,69,.3)':'0 2px 8px rgba(232,160,32,.3)';}}
        >
          <i className="fa-solid fa-arrow-up-right-from-square" style={{fontSize:9}}/>
          Lihat
        </button>
      </td>
    </tr>
  );

  return (
    <div style={{overflowX:'auto'}}>
      <table className="kar-table" style={{minWidth:700}}>
        <thead>
          <tr>
            <th style={{paddingLeft:16}}>Nama</th>
            <th>Badge</th>
            <th>Jabatan</th>
            <th>Dokumen</th>
            {isPensiun && <th style={{textAlign:'center'}}>Tgl Lahir</th>}
            {isPensiun && <th style={{textAlign:'center'}}>Usia</th>}
            <th>{isPensiun ? 'Tgl Pensiun (56 thn)' : 'Tgl Expired'}</th>
            <th style={{textAlign:'center'}}>Sisa Hari</th>
            <th style={{textAlign:'center'}}>Status</th>
            <th style={{textAlign:'center'}}>Aksi</th>
          </tr>
        </thead>
        <tbody>
          {warnings.length > 0 && (
            <tr>
              <td colSpan={colSpanTotal} style={{
                padding:'8px 16px',
                background:'rgba(232,160,32,.07)',
                borderTop:'2px solid rgba(232,160,32,.25)',
                borderBottom:'1px solid rgba(232,160,32,.15)',
                fontSize:11, fontWeight:700, color:'var(--accent)',
                textTransform:'uppercase', letterSpacing:'.07em',
              }}>
                <TriangleAlert size={11} style={{verticalAlign:-2}}/> {isPensiun ? `Akan Pensiun dalam 3 Bulan (${warnings.length})` : `Akan Expired (${warnings.length})`}
              </td>
            </tr>
          )}
          {warnings.map((item, i) => <RowItem key={i} item={item} i={i} prefix="w" />)}

          {expireds.length > 0 && (
            <tr>
              <td colSpan={colSpanTotal} style={{
                padding:'8px 16px',
                background:'rgba(224,69,69,.07)',
                borderTop:'2px solid rgba(224,69,69,.25)',
                borderBottom:'1px solid rgba(224,69,69,.15)',
                fontSize:11, fontWeight:700, color:'#E04545',
                textTransform:'uppercase', letterSpacing:'.07em',
              }}>
                <Circle size={9} fill="currentColor" style={{verticalAlign:1}}/> {isPensiun ? `Sudah Melewati Usia 56 (${expireds.length})` : `Sudah Expired (${expireds.length})`}
              </td>
            </tr>
          )}
          {expireds.map((item, i) => <RowItem key={i} item={item} i={i} prefix="e" />)}
        </tbody>
      </table>
    </div>
  );
}

export default function NotificationsPage({ tabs = {}, summary = {}, project_info = null }) {
  const isHo = project_info?.tipe_gaji === 'ho';
  const visibleTabs = isHo ? TAB_CONFIG.filter(t => !HO_HIDDEN_TABS.includes(t.key)) : TAB_CONFIG;
  const [activeTab, setActiveTab] = useState(isHo ? 'pensiun' : 'mcu');

  const totalAll = summary.total || 0;

  return (
    <AppLayout title="Notifikasi" subtitle="Pemberitahuan">

      {/* Header */}
      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:20,flexWrap:'wrap',gap:12}}>
        <div>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:16,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Bell size={16}/> Pusat Pemberitahuan</div>
          <div style={{fontSize:12,color:'var(--muted)',marginTop:2}}>
            Semua dokumen yang expired atau akan expired dalam 30 hari
          </div>
        </div>
        {totalAll > 0 && (
          <div style={{
            background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)',
            borderRadius:10,padding:'10px 20px',textAlign:'center',
          }}>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:28,fontWeight:700,color:'#E04545',lineHeight:1}}>{totalAll}</div>
            <div style={{fontSize:11,color:'#E04545',marginTop:3}}>Total Alert</div>
          </div>
        )}
      </div>

      {/* Tab buttons */}
      <div style={{display:'flex',gap:6,marginBottom:20,flexWrap:'wrap'}}>
        {visibleTabs.map(t => {
          const count = summary[t.key] || 0;
          const isActive = activeTab === t.key;
          return (
            <div key={t.key} onClick={() => setActiveTab(t.key)}
              style={{
                padding:'8px 16px',borderRadius:8,cursor:'pointer',
                fontSize:12.5,fontWeight:600,transition:'all .15s',
                display:'flex',alignItems:'center',gap:7,
                background: isActive ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--card)',
                color: isActive ? '#0C0F14' : 'var(--muted)',
                border: `1px solid ${isActive ? 'transparent' : 'var(--border)'}`,
              }}>
              {t.label}
              <span style={{
                background: isActive ? 'rgba(0,0,0,.2)' : count > 0 ? 'rgba(224,69,69,.15)' : 'var(--bg3)',
                color: isActive ? '#0C0F14' : count > 0 ? '#E04545' : 'var(--muted2)',
                fontSize:10.5,fontWeight:700,padding:'1px 7px',borderRadius:99,minWidth:20,textAlign:'center',
              }}>{count}</span>
            </div>
          );
        })}
      </div>

      {/* Content */}
      <div className="panel" style={{overflow:'hidden'}}>
        <div className="panel-head">
          <div className="panel-title">
            {TAB_CONFIG.find(t => t.key === activeTab)?.label}
            <span style={{marginLeft:8,fontSize:11,color:'var(--muted)',fontWeight:400}}>
              — {summary[activeTab] || 0} item
            </span>
          </div>
          <div style={{fontSize:11,color:'var(--muted)',display:'flex',alignItems:'center',gap:5}}>
            <TriangleAlert size={11}/> Warning dulu · <Circle size={8} fill="currentColor"/> Expired di bawah
          </div>
        </div>
        <NotifTable
          items={tabs[activeTab] || []}
          emptyMsg={`Tidak ada notifikasi untuk ${TAB_CONFIG.find(t=>t.key===activeTab)?.label}`}
          isPensiun={activeTab === 'pensiun'}
        />
      </div>

    </AppLayout>
  );
}
