// resources>js>Pages>Compliance>Ppe.jsx
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';

function CheckPill({ val, tgl }) {
  if (!val) return <span className="pill pill-gray">—</span>;
  return (
    <div>
      <span className="pill pill-green">Ada</span>
      {tgl && <div style={{fontSize:9.5,color:'var(--muted)',marginTop:2}}>{tgl}</div>}
    </div>
  );
}

function EditPpeModal({ employee, onClose }) {
  const [form, setForm] = useState({
    frc:            employee.frc            ?? '',
    safety_shoes:   employee.safety_shoes   ?? '',
    white_helmet:   !!employee.white_helmet,
    helmet:         !!employee.helmet,
    safety_glass:   employee.safety_glass   ?? false,
    safety_vest:    employee.safety_vest    ?? false,
    ear_plug:       employee.ear_plug       ?? false,
    tgl_frc:        employee.tgl_frc        ?? '',
    tgl_frc_2:      employee.tgl_frc_2      ?? '',
    tgl_sepatu:     employee.tgl_sepatu     ?? '',
    tgl_helm:         employee.tgl_helm         ?? '',
    tgl_helm_orange:  employee.tgl_helm_orange  ?? '',
    tgl_glass:        employee.tgl_glass        ?? '',
    tgl_glass_2:      employee.tgl_glass_2      ?? '',
    tgl_vest:         employee.tgl_vest         ?? '',
    tgl_ear_plug:     employee.tgl_ear_plug     ?? '',
    tgl_ear_plug_2:   employee.tgl_ear_plug_2   ?? '',
    tgl_frc_3:        employee.tgl_frc_3        ?? '',
    tgl_frc_4:        employee.tgl_frc_4        ?? '',
    tgl_sepatu_2:     employee.tgl_sepatu_2     ?? '',
    tgl_sepatu_3:     employee.tgl_sepatu_3     ?? '',
    catatan:          employee.catatan          ?? '',
  });

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)',
    color:'var(--text)', borderRadius:8, padding:'8px 11px',
    fontSize:12.5, fontFamily:"'Outfit',sans-serif",
    outline:'none', width:'100%', boxSizing:'border-box',
  };

  function submit(e) {
    e.preventDefault();
    router.put(`/compliance/ppe/${employee.id}`, form, {
      preserveScroll: true,
      onSuccess: onClose,
    });
  }

  const Row = ({ label, children }) => (
    <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'8px 14px',marginBottom:8}}>
      {children}
    </div>
  );

  const F = ({ label, k, type='text', opts }) => (
    <div>
      <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>{label}</label>
      {opts ? (
        <select style={inp} value={form[k]||''} onChange={e=>setForm(f=>({...f,[k]:e.target.value}))}>
          <option value="">— Pilih —</option>
          {opts.map(o=><option key={o} value={o}>{o}</option>)}
        </select>
      ) : (
        <input type={type} style={inp} value={form[k]||''} onChange={e=>setForm(f=>({...f,[k]:e.target.value}))} />
      )}
    </div>
  );

  const Toggle = ({ label, k, tglKey }) => (
    <div style={{display:'flex',flexDirection:'column',gap:6}}>
      <label style={{fontSize:10.5,color:'var(--muted)',display:'block'}}>{label}</label>
      <div style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap'}}>
        <div onClick={()=>setForm(f=>({...f,[k]:!f[k]}))}
          style={{
            padding:'6px 14px',borderRadius:7,cursor:'pointer',fontSize:12,fontWeight:600,
            background: form[k] ? 'rgba(34,201,122,.15)' : 'rgba(128,128,128,.08)',
            color: form[k] ? 'var(--green)' : 'var(--muted)',
            border: `1px solid ${form[k] ? 'rgba(34,201,122,.3)' : 'var(--border)'}`,
            userSelect:'none', display:'flex', alignItems:'center', gap:6,
            transition:'all .15s',
          }}>
          {form[k] ? '✅ Ada' : '— Tidak Ada'}
          <span style={{fontSize:9.5,opacity:.6,fontWeight:400}}>{form[k] ? '(klik untuk hapus)' : '(klik untuk tandai)'}</span>
        </div>
        {form[k] && tglKey && (
          <div style={{display:'flex',flexDirection:'column',gap:3,flex:1,minWidth:140}}>
            <label style={{fontSize:10,color:'var(--muted)',letterSpacing:'.02em'}}>📅 Tgl Pengambilan</label>
            <input type="date" style={{...inp,width:'100%'}}
              defaultValue={form[tglKey]||''} onBlur={e=>setForm(f=>({...f,[tglKey]:e.target.value}))} />
          </div>
        )}
      </div>
    </div>
  );

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(560px, calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        {/* Header */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:10}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>
            🦺 Edit PPE — {employee.nama_lengkap}
          </div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>

        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:16}}>

            {/* Ukuran */}
            <div>
              <div style={{fontSize:11,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.06em',marginBottom:10,paddingBottom:6,borderBottom:'1px solid var(--border)'}}>
                👕 Ukuran
              </div>
              <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'8px 14px'}}>
                <div>
                  <F label="Ukuran FRC (Baju)" k="frc" opts={['S','M','L','XL','XXL','XXXL']} />
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Ambil FRC (ke-1)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_frc||''} onBlur={e=>setForm(f=>({...f,tgl_frc:e.target.value}))} />
                  </div>
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Ambil FRC (ke-2)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_frc_2||''} onBlur={e=>setForm(f=>({...f,tgl_frc_2:e.target.value}))} />
                  </div>
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Ambil FRC (ke-3)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_frc_3||''} onBlur={e=>setForm(f=>({...f,tgl_frc_3:e.target.value}))} />
                  </div>
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Ambil FRC (ke-4)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_frc_4||''} onBlur={e=>setForm(f=>({...f,tgl_frc_4:e.target.value}))} />
                  </div>
                </div>
                <div>
                  <F label="Ukuran Sepatu" k="safety_shoes" opts={['4','5','6','7','8','9','10','11','12']} />
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Pengambilan Sepatu (2024)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_sepatu||''} onBlur={e=>setForm(f=>({...f,tgl_sepatu:e.target.value}))} />
                  </div>
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Pengambilan Sepatu (2025)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_sepatu_2||''} onBlur={e=>setForm(f=>({...f,tgl_sepatu_2:e.target.value}))} />
                  </div>
                  <div style={{marginTop:8}}>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Tgl Pengambilan Sepatu (2026)</label>
                    <input type="date" style={inp} defaultValue={form.tgl_sepatu_3||''} onBlur={e=>setForm(f=>({...f,tgl_sepatu_3:e.target.value}))} />
                  </div>
                </div>
              </div>
            </div>

            {/* Atribut */}
            <div>
              <div style={{fontSize:11,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.06em',marginBottom:10,paddingBottom:6,borderBottom:'1px solid var(--border)'}}>
                🪖 Atribut Keselamatan
              </div>
              <div style={{display:'flex',flexDirection:'column',gap:10}}>
                <Toggle label="Helmet (Putih)" k="white_helmet" tglKey="tgl_helm" />
                <Toggle label="Helm (Orange)" k="helmet" tglKey="tgl_helm_orange" />
                <Toggle label="Safety Glass"  k="safety_glass" tglKey="tgl_glass" />
                <Toggle label="Safety Vest"   k="safety_vest"  tglKey="tgl_vest" />
                <Toggle label="Ear Plug"      k="ear_plug"     tglKey="tgl_ear_plug" />
              </div>
            </div>

            {/* Catatan */}
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Catatan</label>
              <textarea style={{...inp,minHeight:60,resize:'vertical'}}
                value={form.catatan||''} onChange={e=>setForm(f=>({...f,catatan:e.target.value}))}
                placeholder="Catatan tambahan..." />
            </div>
          </div>

          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
            <button type="button" onClick={onClose}
              style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
              Batal
            </button>
            <button type="submit"
              style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
              💾 Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function PpePage({ employees={data:[],total:0,links:[],current_page:1,last_page:1}, stats={}, search='', filter='all' }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [searchVal, setSearchVal] = useState(search);
  const [editEmp,   setEditEmp]   = useState(null);

  function doFilter(f, s) {
    router.get('/compliance/ppe', {filter:f, search:s}, {preserveState:true, replace:true});
  }

  const data     = employees.data      || [];
  const total    = employees.total     || 0;
  const prevUrl  = employees.prev_page_url;
  const nextUrl  = employees.next_page_url;
  const curPage  = employees.current_page || 1;
  const lastPage = employees.last_page    || 1;
  const pageLinks = (employees.links||[]).filter(l=>l.label!=='&laquo; Previous'&&l.label!=='Next &raquo;');

  const frcSizes = [
    {k:'frc_L',   label:'FRC (L)'},
    {k:'frc_XL',  label:'FRC (XL)'},
    {k:'frc_M',   label:'FRC (M)'},
    {k:'frc_XXL', label:'FRC (XXL)'},
    {k:'frc_XXXL',label:'FRC (XXXL)'},
    {k:'has_frc', label:'FRC (Total)'},
  ];

  const filterTabs = [
    {key:'all',    label:'Semua',      val:stats.total},
    {key:'has_frc',label:'Punya FRC',  val:stats.has_frc},
    {key:'no_frc', label:'Belum FRC',  val:stats.no_frc},
  ];

  return (
    <AppLayout title="PPE &" subtitle="Atribut">
      {editEmp && <EditPpeModal employee={editEmp} onClose={()=>setEditEmp(null)} />}

      {/* Stats FRC size — clickable cards, warna fix untuk light & dark */}
      <div className="stat-grid-6" style={{display:'grid',gridTemplateColumns:'repeat(6,1fr)',gap:10,marginBottom:16}}>
        {frcSizes.map((s,i)=>{
          const COLORS = ['#3A8FE0','#22C97A','#E8A020','#E06A20','#9B59B6','#E04545'];
          const frcFilterMap = {
            'frc_L':'L','frc_M':'M','frc_XL':'XL','frc_XXL':'XXL','frc_XXXL':'XXXL','has_frc':'has_frc'
          };
          const frcFilter = frcFilterMap[s.k];
          const isActive  = filter === frcFilter;
          const col = COLORS[i];
          return (
            <div key={i}
              onClick={()=>doFilter(frcFilter, searchVal)}
              style={{
                textAlign:'center', padding:'14px 8px', borderRadius:9, cursor:'pointer',
                transition:'all .15s',
                background: isActive ? `${col}18` : 'var(--card)',
                border: `1px solid ${isActive ? col : 'var(--border)'}`,
                boxShadow: isActive ? `0 0 0 1px ${col}` : 'none',
              }}>
              <div style={{fontSize:20,marginBottom:4}}>👕</div>
              <div style={{fontSize:10,color:isActive?col:'var(--muted)',marginBottom:4,fontWeight:isActive?600:400}}>
                {s.label}
              </div>
              <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:col}}>
                {stats[s.k] ?? 0}
              </div>
              {isActive && <div style={{width:20,height:3,borderRadius:99,background:col,margin:'5px auto 0'}}/>}
            </div>
          );
        })}
      </div>

      <div className="panel" style={{marginTop:8}}>
        <div className="panel-head">
          <div className="panel-title">🦺 Data PPE & Atribut Keselamatan</div>
          <div style={{display:'flex',gap:10,alignItems:'center'}}>
            <a href="/export/ppe" style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif"}}>📤 Export</a>
          </div>
        </div>

        {/* Search - sejajar dengan menu lain */}
        <div style={{padding:'14px 16px 0'}}>
          <div style={{position:'relative',marginBottom:14}}>
            <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',fontSize:15,color:'var(--muted)'}}>🔍</span>
            <input className="search-input" type="text" value={searchVal}
              placeholder="Cari nama atau NIK..."
              onChange={e=>{setSearchVal(e.target.value); doFilter(filter, e.target.value);}}
              style={{paddingLeft:'36px'}} />
          </div>
        </div>

        <div style={{overflowX:'auto'}}>
            <table className="kar-table" style={{minWidth:1100}}>
            <thead>
                <tr>
                <th style={{paddingLeft:16}}>NIK</th>
                <th style={{paddingLeft:8}}>Nama</th>
                <th>Jabatan</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>FRC</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>FRC Ke-1</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>FRC Ke-2</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Sepatu</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Tgl Sepatu</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Helmet</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Safety Glass</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Safety Vest</th>
                <th style={{textAlign:'center',padding:'12px 8px'}}>Ear Plug</th>
                {!isViewer && <th style={{textAlign:'center',padding:'12px 8px'}}>Aksi</th>}
              </tr>
            </thead>
            <tbody>
              {data.map((e,i)=>{
                const isTerminated = e.status && e.status !== 'AKTIF';
                return (
                  <tr key={i}
                    style={isTerminated ? {background:'rgba(224,69,69,.04)',opacity:.7} : {}}
                    onMouseEnter={ev=>{Array.from(ev.currentTarget.cells).forEach(c=>c.style.background=isTerminated?'rgba(224,69,69,.08)':'rgba(232,160,32,.04)');}}
                    onMouseLeave={ev=>{Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='');}}>
                    <td style={{fontFamily:'monospace',fontSize:11.5,color:isTerminated?'#E04545':'var(--accent)',paddingLeft:16}}>
                      {e.no_ktp || '—'}
                      {isTerminated && <div style={{fontSize:9,color:'#E04545',fontWeight:700,letterSpacing:'.05em'}}>TERMINATED</div>}
                    </td>
                    <td style={{fontWeight:500,paddingLeft:8}}>{e.nama_lengkap}</td>
                    <td style={{fontSize:12,color:'var(--muted2)'}}>{e.jabatan}</td>
                    <td style={{textAlign:'center'}}>
                      {e.frc
                        ? <span style={{background:'rgba(58,143,224,.1)',color:'var(--blue)',padding:'2px 10px',borderRadius:99,fontSize:12,fontWeight:700}}>{e.frc}</span>
                        : <span style={{color:'var(--muted)'}}>—</span>}
                    </td>
                    <td style={{textAlign:'center',fontSize:11,color:'var(--muted2)'}}>{e.tgl_frc_fmt||'—'}</td>
                    <td style={{textAlign:'center',fontSize:11,color:'var(--muted2)'}}>{e.tgl_frc_2_fmt||'—'}</td>
                    <td style={{textAlign:'center'}}>
                      {e.safety_shoes
                        ? <span style={{background:'rgba(232,160,32,.1)',color:'var(--accent)',padding:'2px 10px',borderRadius:99,fontSize:12,fontWeight:700}}>{e.safety_shoes}</span>
                        : <span style={{color:'var(--muted)'}}>—</span>}
                    </td>
                    <td style={{textAlign:'center',fontSize:11,color:'var(--muted2)'}}>{e.tgl_sepatu_fmt||'—'}</td>
                    <td style={{textAlign:'center'}}><CheckPill val={e.white_helmet} tgl={e.tgl_helm_fmt}/></td>
                    <td style={{textAlign:'center'}}><CheckPill val={e.safety_glass} tgl={e.tgl_glass_fmt}/></td>
                    <td style={{textAlign:'center'}}><CheckPill val={e.safety_vest} tgl={e.tgl_vest_fmt}/></td>
                    <td style={{textAlign:'center'}}><CheckPill val={e.ear_plug} tgl={e.tgl_ear_plug_fmt}/></td>
                    {!isViewer && (
                      <td style={{textAlign:'center'}}>
                        {e.ppe_id
                          ? (
                            <button onClick={()=>setEditEmp(e)}
                              style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                              ✏️ Edit
                            </button>
                          ) : (
                            <button onClick={()=>setEditEmp(e)}
                              style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(34,201,122,.1)',color:'var(--green)',border:'1px solid rgba(34,201,122,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                              ➕ Tambah
                            </button>
                          )}
                      </td>
                    )}
                  </tr>
                );
              })}
              {data.length===0&&(
                <tr><td colSpan={isViewer?12:13} style={{padding:32,textAlign:'center',color:'var(--muted)'}}>Tidak ada data PPE</td></tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'12px 16px',borderTop:'1px solid var(--border)',flexWrap:'wrap',gap:8}}>
          <div style={{fontSize:11,color:'var(--muted)'}}>Halaman {curPage} dari {lastPage} · {total} karyawan</div>
          <div style={{display:'flex',gap:4}}>
            <button disabled={!prevUrl} onClick={()=>prevUrl&&router.get(prevUrl)}
              style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:prevUrl?'var(--text)':'var(--muted)',cursor:prevUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>← Prev</button>
            {pageLinks.map((l,i)=>(
              <button key={i} disabled={!l.url} onClick={()=>l.url&&router.get(l.url)}
                dangerouslySetInnerHTML={{__html:l.label}}
                style={{padding:'5px 10px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:l.active?'var(--accent)':'var(--card)',color:l.active?'#0C0F14':l.url?'var(--text)':'var(--muted)',cursor:l.url?'pointer':'default',fontFamily:"'Outfit',sans-serif",fontWeight:l.active?700:400,minWidth:34}}/>
            ))}
            <button disabled={!nextUrl} onClick={()=>nextUrl&&router.get(nextUrl)}
              style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:nextUrl?'var(--text)':'var(--muted)',cursor:nextUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>Next →</button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
