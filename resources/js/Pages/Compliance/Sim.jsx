// resources>js>Pages>Compliance>Sim.jsx
import React, { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import { CreditCard, X, Save, Search, Pencil, Download, Car } from 'lucide-react';

function DaysLeft({ days }){
  if(days===null||days===undefined) return <span style={{color:'var(--muted)',fontSize:11}}>—</span>;
  if(days<0) return <span style={{color:'#E04545',fontSize:11,fontWeight:600}}>{Math.abs(days)} hr lalu</span>;
  if(days===0) return <span style={{color:'#E04545',fontSize:11,fontWeight:600}}>Hari ini!</span>;
  if(days<=30) return <span style={{color:'#E8A020',fontSize:11,fontWeight:600}}>{days} hr lagi</span>;
  return <span style={{color:'#22C97A',fontSize:11}}>{days} hr lagi</span>;
}
function StatusPill({ status }){
  if(status==='expired') return <span className="pill pill-red">Expired</span>;
  if(status==='warning') return <span className="pill pill-warn">&lt;30hr</span>;
  if(status==='valid')   return <span className="pill pill-green">Valid</span>;
  return <span className="pill pill-gray">N/A</span>;
}

function EditModal({ employee, onClose }){
  const [form, setForm] = useState({
    type_sim:        employee.type_sim        || '',
    no_sim:          employee.no_sim          || '',
    sim_kota_keluar: employee.sim_kota_keluar || '',
    expired_sim:     employee.expired_sim     || '',
    rfid:            employee.rfid            || '',
  });
  const inp = {background:'var(--bg3)',border:'1px solid var(--border)',color:'var(--text)',borderRadius:8,padding:'8px 11px',fontSize:12.5,fontFamily:"'Outfit',sans-serif",outline:'none',width:'100%',boxSizing:'border-box'};
  function submit(e){ e.preventDefault(); router.put(`/compliance/sim/${employee.id}`, form, {preserveScroll:true, onSuccess:onClose}); }
  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(440px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><CreditCard size={16}/> Edit SIM — {employee.nama_lengkap}</div>
          <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:12}}>
            <div><label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Type SIM</label>
              <select style={inp} value={form.type_sim} onChange={e=>setForm(f=>({...f,type_sim:e.target.value}))}>
                <option value="">— Pilih —</option>
                {['A','BI','BII','BIIU','C'].map(t=><option key={t} value={t}>{t}</option>)}
              </select>
            </div>
            <div><label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>No. SIM</label><input type="text" style={inp} value={form.no_sim} onChange={e=>setForm(f=>({...f,no_sim:e.target.value}))}/></div>
            <div><label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Kota Dikeluarkan (sesuai KTP)</label><input type="text" style={inp} placeholder="cth: Pekanbaru" value={form.sim_kota_keluar} onChange={e=>setForm(f=>({...f,sim_kota_keluar:e.target.value}))}/></div>
            <div><label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Expired SIM</label><input type="date" style={inp} value={form.expired_sim} onChange={e=>setForm(f=>({...f,expired_sim:e.target.value}))}/></div>
            <div><label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>RFID</label><input type="text" style={inp} value={form.rfid} onChange={e=>setForm(f=>({...f,rfid:e.target.value}))}/></div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
            <button type="submit" style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:6}}><Save size={14}/> Simpan</button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function SimPage({ employees={data:[],total:0,links:[],current_page:1,last_page:1}, stats={}, filter='all', search='', highlight=null }){
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;
  const [searchVal, setSearchVal] = useState(search);
  const [editEmp,   setEditEmp]   = useState(null);
  
  useEffect(() => {
    const highlightId = highlight ? String(highlight) : null;
    if (!highlightId) return;

    const timer = setTimeout(() => {
      const row = document.querySelector(`tr[data-id="${highlightId}"]`);
      if (!row) return;

      // Scroll ke baris
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });

      // Highlight efek
      row.style.transition = 'background 0.3s';
      row.style.background = 'rgba(232,160,32,.35)';
      setTimeout(() => {
        row.style.background = 'rgba(232,160,32,.15)';
        setTimeout(() => { row.style.background = ''; }, 2500);
      }, 600);

      // Hapus highlight dari URL supaya tidak trigger lagi saat kembali
      const url = new URL(window.location.href);
      url.searchParams.delete('highlight');
      window.history.replaceState({}, '', url.toString());
    }, 400);

    return () => clearTimeout(timer);
  }, [highlight]);

  function doFilter(f, s){ router.get('/compliance/sim', {filter:f, search:s}, {preserveState:true, replace:true}); }

  const data=employees.data||[]; const total=employees.total||0;
  const links=employees.links||[]; const prevUrl=employees.prev_page_url; const nextUrl=employees.next_page_url;
  const curPage=employees.current_page||1; const lastPage=employees.last_page||1;
  const pageLinks=links.filter(l=>l.label!=='&laquo; Previous'&&l.label!=='Next &raquo;');

  const filterTabs=[
    {key:'all',label:'Punya SIM',val:stats.total,color:'var(--text)'},
    {key:'expired',label:'Expired',val:stats.expired,color:'#E04545'},
    {key:'warning',label:'< 30 hari',val:stats.warning,color:'#E8A020'},
    {key:'ok',label:'Valid',val:stats.ok,color:'#22C97A'},
    {key:'no_data',label:'No Exp Data',val:stats.no_data,color:'var(--muted)'},
  ];

  return (
    <AppLayout title="Compliance" subtitle="SIM">
      {editEmp && <EditModal employee={editEmp} onClose={()=>setEditEmp(null)} />}

      <div className="stat-grid-5" style={{display:'grid',gridTemplateColumns:'repeat(5,1fr)',gap:12,marginBottom:20}}>
        {filterTabs.map((f,i)=>(
          <div key={i} onClick={()=>doFilter(f.key, searchVal)}
            style={{background:filter===f.key?'rgba(232,160,32,.1)':'var(--card)',border:`1px solid ${filter===f.key?'var(--accent)':'var(--border)'}`,borderRadius:10,padding:'14px 16px',textAlign:'center',cursor:'pointer',transition:'all .15s'}}>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:f.color}}>{f.val??0}</div>
            <div style={{fontSize:10.5,color:'var(--muted)',marginTop:4}}>{f.label}</div>
          </div>
        ))}
      </div>

      <div className="panel">
        <div className="panel-head">
          <div className="panel-title" style={{display:'flex',alignItems:'center',gap:8}}><Car size={15}/> Data SIM Karyawan</div>
          <a href="/export/sim" style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif",display:'inline-flex',alignItems:'center',gap:6}}><Download size={14}/> Export</a>
        </div>
        <div style={{padding:'14px 16px'}}>
          <div style={{position:'relative',marginBottom:14}}>
            <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={15}/></span>
            <input className="search-input" type="text" value={searchVal} placeholder="Cari nama, badge, no. SIM..."
              onChange={e=>{setSearchVal(e.target.value); doFilter(filter, e.target.value);}} style={{paddingLeft:'36px'}}/>
          </div>
          <div style={{overflowX:'auto'}}>
            <table className="kar-table">
              <thead>
                <tr><th>NIK</th><th>Nama Karyawan</th><th>Jabatan</th><th>Type SIM</th><th>No. SIM</th><th>RFID</th><th>Expired SIM</th><th>Sisa Hari</th><th>Status</th>{!isViewer && <th style={{textAlign:'center'}}>Aksi</th>}</tr>
              </thead>
              <tbody>
                {data.map((e,i)=>(
                    <tr key={i} data-id={e.id}
                      onMouseEnter={ev=>{Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)');}}
                      onMouseLeave={ev=>{Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='');}}>
                    <td style={{fontFamily:'monospace',fontSize:11.5,color:'var(--accent)'}}>{e.no_ktp || '—'}</td>
                    <td style={{fontWeight:500}}>{e.nama_lengkap}</td>
                    <td style={{fontSize:12,color:'var(--muted2)'}}>{e.jabatan}</td>
                    <td><span style={{background:'rgba(58,143,224,.1)',color:'var(--blue)',padding:'2px 8px',borderRadius:99,fontSize:11,fontWeight:600}}>{e.type_sim||'—'}</span></td>
                    <td style={{fontFamily:'monospace',fontSize:11}}>{e.no_sim||'—'}</td>
                    <td style={{fontFamily:'monospace',fontSize:11,color:'var(--muted2)'}}>{e.rfid||'—'}</td>
                    <td style={{whiteSpace:'nowrap'}}>{e.expired_sim_fmt||'—'}</td>
                    <td><DaysLeft days={e.days_left}/></td>
                    <td><StatusPill status={e.sim_status}/></td>
                    {!isViewer && (
                      <td style={{textAlign:'center'}}>
                        <button onClick={()=>setEditEmp(e)} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}><Pencil size={11}/> Edit</button>
                      </td>
                    )}
                  </tr>
                ))}
                {data.length===0&&(<tr><td colSpan={isViewer?9:10} style={{padding:32,textAlign:'center',color:'var(--muted)'}}>Tidak ada data SIM</td></tr>)}
              </tbody>
            </table>
          </div>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginTop:14,flexWrap:'wrap',gap:8}}>
            <div style={{fontSize:11,color:'var(--muted)'}}>Halaman {curPage} dari {lastPage} · {data.length} dari {total} karyawan</div>
            <div style={{display:'flex',gap:4}}>
              <button disabled={!prevUrl} onClick={()=>prevUrl&&router.get(prevUrl)} style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:prevUrl?'var(--text)':'var(--muted)',cursor:prevUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>← Prev</button>
              {pageLinks.map((l,i)=>(<button key={i} disabled={!l.url} onClick={()=>l.url&&router.get(l.url)} dangerouslySetInnerHTML={{__html:l.label}} style={{padding:'5px 10px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:l.active?'var(--accent)':'var(--card)',color:l.active?'#0C0F14':l.url?'var(--text)':'var(--muted)',cursor:l.url?'pointer':'default',fontFamily:"'Outfit',sans-serif",fontWeight:l.active?700:400,minWidth:34}}/>))}
              <button disabled={!nextUrl} onClick={()=>nextUrl&&router.get(nextUrl)} style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:nextUrl?'var(--text)':'var(--muted)',cursor:nextUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>Next →</button>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
