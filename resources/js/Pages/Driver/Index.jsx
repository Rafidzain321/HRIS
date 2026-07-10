// resources>js>Pages>Driver>Index.jsx
import React, { useState } from 'react';
import AppLayout, { ConfirmModal } from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import ImportModal from '@/Components/ImportModal';

// ── Helper: strip trailing .0 dari angka yang disimpan sebagai float ──
function stripDotZero(val) {
  if (val === null || val === undefined) return '';
  return String(val).replace(/\.0+$/, '');
}

function fmtDate(val) {
  if (!val) return '—';
  try {
    const d = new Date(val);
    if (isNaN(d.getTime())) return '—';
    const m = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return `${String(d.getDate()).padStart(2,'0')} ${m[d.getMonth()]} ${d.getFullYear()}`;
  } catch { return '—'; }
}

function daysDiff(val) {
  if (!val) return null;
  return Math.floor((new Date(val) - new Date()) / (1000*60*60*24));
}

function PermitPill({ dateStr, status }) {
  if (!dateStr || status === 'na') return <span className="pill pill-gray">—</span>;
  if (status === 'expired') {
    const d = Math.abs(daysDiff(dateStr));
    return <span className="pill pill-red">Expired ({d}hr)</span>;
  }
  if (status === 'warning') {
    const d = daysDiff(dateStr);
    return <span className="pill pill-warn">&lt;30hr ({d}hr)</span>;
  }
  return <span className="pill pill-green">Valid</span>;
}

function DriverStatusPill({ status }) {
  const s = (status || '').toLowerCase();
  if (s === 'approved') return <span className="pill pill-green">Approved</span>;
  if (s.includes('waiting')) return <span className="pill pill-blue">Waiting</span>;
  if (s.includes('in progress')) return <span className="pill pill-warn">In Progress</span>;
  if (s.includes('reject')) return <span className="pill pill-red">Rejected</span>;
  return <span className="pill pill-gray">{status || '—'}</span>;
}

function PosttestPill({ status }) {
  if (!status || status === '—') return <span className="pill pill-gray">—</span>;
  if (status === 'Pass') return <span className="pill pill-green">Pass</span>;
  if (status === 'Not Pass') return <span className="pill pill-red">Not Pass</span>;
  return <span className="pill pill-gray">{status}</span>;
}

function DriverModal({ mode, data, onClose, onSave }) {
  // ── FIX: strip .0 dari semua field string yang mungkin tersimpan sebagai float ──
  const clean = (d) => d ? {
    ...d,
    license_no: stripDotZero(d.license_no),
    id_card:    stripDotZero(d.id_card),
    rfid:       stripDotZero(d.rfid),
    badge:      d.badge || '',
  } : {
    name:'', id_card:'', badge:'', license_type:'', license_no:'', rfid:'',
    posttest_schedule:'', driver_status:'', permit_expired_date:'',
    posttest_schedule_status:'', posttest_status:'', date_approve_posttest:'', dvp_status:'',
  };

  const [form, setForm] = useState(clean(data));
  const set = (k, v) => setForm(f => ({...f, [k]:v}));
  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%',
  };

  return (
    <div style={{ position:'fixed', inset:0, zIndex:300, background:'rgba(0,0,0,.65)', display:'flex', alignItems:'center', justifyContent:'center' }}
      onClick={e => e.target===e.currentTarget && onClose()}>
      <div style={{ background:'var(--bg2)', border:'1px solid var(--border2)', borderRadius:16, width:'min(660px, calc(100vw - 24px))', maxHeight:'90vh', overflow:'auto', boxShadow:'0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 20px', borderBottom:'1px solid var(--border)' }}>
          <div style={{ fontFamily:'Syne,sans-serif', fontSize:15, fontWeight:700, color:'var(--text)' }}>
            {mode==='add' ? '➕ Tambah Driver' : '✏️ Edit Driver'}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', fontSize:18, color:'var(--muted)' }}>✕</div>
        </div>

        <div style={{ padding:'18px 20px', display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px 16px' }}>
          {/* Nama - full width */}
          <div style={{ gridColumn:'1 / -1' }}>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Nama Lengkap *</label>
            <input style={inp} value={form.name||''} onChange={e=>set('name',e.target.value)} placeholder="NAMA DRIVER" />
          </div>

          {/* Field string — semua pakai tipe text biasa supaya 0 di depan tidak hilang */}
          {[
            {k:'badge',       l:'Badge ID',    p:'AKM-EW-0001'},
            {k:'id_card',     l:'No. ID Card', p:'1407101709010004'},
            {k:'license_type',l:'Tipe SIM',    p:'BIIU / A / BII'},
            {k:'license_no',  l:'No. SIM',     p:'1407101709010004'},
            {k:'rfid',        l:'RFID',        p:'C44B85BE'},
          ].map(f => (
            <div key={f.k}>
              <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>{f.l}</label>
              <input
                type="text"
                inputMode="text"
                style={inp}
                value={form[f.k]||''}
                onChange={e=>set(f.k, e.target.value)}
                placeholder={f.p}
              />
            </div>
          ))}

          {[
            {k:'posttest_schedule',     l:'Jadwal Post Test'},
            {k:'permit_expired_date',   l:'Permit Expired Date'},
            {k:'date_approve_posttest', l:'Tgl Approve Post Test'},
          ].map(f => (
            <div key={f.k}>
              <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>{f.l}</label>
              <input type="date" style={inp} value={form[f.k]||''} onChange={e=>set(f.k,e.target.value)} />
            </div>
          ))}

          <div>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Driver Status</label>
            <select style={inp} value={form.driver_status||''} onChange={e=>set('driver_status',e.target.value)}>
              <option value="">— Pilih —</option>
              <option>Approved</option>
              <option>In Progress</option>
              <option>Waiting for Pengawas Pekerjaan Approval</option>
              <option>Waiting for Pengawas Pekerjaan Approval (Revisi)</option>
              <option>Rejected by Contract Reviewer</option>
            </select>
          </div>

          <div>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Status Post Test</label>
            <select style={inp} value={form.posttest_status||''} onChange={e=>set('posttest_status',e.target.value)}>
              <option value="">— Pilih —</option>
              <option>Pass</option>
              <option>Not Pass</option>
            </select>
          </div>

          <div>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Posttest Schedule Status</label>
            <select style={inp} value={form.posttest_schedule_status||''} onChange={e=>set('posttest_schedule_status',e.target.value)}>
              <option value="">— Pilih —</option>
              <option>Confirmed</option>
              <option>Not Confirmed</option>
            </select>
          </div>

          <div>
            <label style={{fontSize:11, color:'var(--muted)', marginBottom:4, display:'block'}}>DVP Status</label>
            <select style={inp} value={form.dvp_status||''} onChange={e=>set('dvp_status',e.target.value)}>
              <option value="">— Pilih —</option>
              <option>KP has been exist</option>
              <option>Probation</option>
              <option>Not Yet</option>
              <option>On Process</option>
            </select>
          </div>
        </div>

        <div style={{ display:'flex', gap:10, justifyContent:'flex-end', padding:'12px 20px', borderTop:'1px solid var(--border)' }}>
          <button onClick={onClose}
            style={{ padding:'9px 18px', borderRadius:8, border:'1px solid var(--border)', background:'var(--bg3)', color:'var(--muted2)', fontSize:12.5, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
            Batal
          </button>
          <button onClick={() => { if(!form.name.trim()){alert('Nama wajib diisi!');return;} onSave(form); }}
            style={{ padding:'9px 22px', borderRadius:8, border:'none', background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14', fontSize:12.5, fontWeight:700, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
            {mode==='add' ? '➕ Tambah' : '💾 Simpan'}
          </button>
        </div>
      </div>
    </div>
  );
}

export default function DriverIndex({ drivers = {data:[],total:0,stats:{}}, filters = {} }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [search, setSearch]   = useState(filters.search  || '');
  const [status, setStatus]   = useState(filters.status  || '');
  const [license, setLicense] = useState(filters.license || '');
  const [permit, setPermit]   = useState(filters.permit  || '');
  const [showImport, setShowImport] = useState(false);
  const [modal, setModal]     = useState(null);
  const [confirmHapus, setConfirmHapus] = useState(null);

  function doFilter(s, st, li, pe) {
    router.get('/driver', {search:s,status:st,license:li,permit:pe}, {preserveState:true,replace:true});
  }

  function handleSave(form) {
    if (modal.mode === 'add') {
      router.post('/driver', form, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { setModal(null); doFilter(search, status, license, permit); },
      });
    } else {
      router.put(`/driver/${form.id}`, form, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { setModal(null); doFilter(search, status, license, permit); },
      });
    }
  }

  const data    = drivers.data  || [];
  const total   = drivers.total || 0;
  const s       = drivers.stats || {};
  const prevUrl = drivers.prev_page_url;
  const nextUrl = drivers.next_page_url;
  const links   = (drivers.links||[]).filter(l => l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;');

  return (
    <AppLayout title="Data" subtitle="Driver">
      {modal && <DriverModal mode={modal.mode} data={modal.data} onClose={()=>setModal(null)} onSave={handleSave} />}

      <ConfirmModal
        open={!!confirmHapus}
        onCancel={()=>setConfirmHapus(null)}
        onConfirm={()=>{
          const id = confirmHapus.id;
          const s = search, st = status, li = license, pe = permit;
          setConfirmHapus(null);
          router.delete(`/driver/${id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => doFilter(s, st, li, pe),
          });
        }}
        title="Hapus Data Driver"
        message={confirmHapus ? <>Data driver <b style={{color:'var(--text)'}}>{confirmHapus.name}</b> akan dihapus permanen dari sistem.</> : ''}
        confirmLabel="Ya, Hapus"
        type="danger"
      />

      {showImport && (
        <ImportModal
          onClose={()=>setShowImport(false)}
          importUrl="/driver/import"
          templateUrl="/driver/template-import"
          title="Import Data Driver"
        />
      )}

      {/* STATS */}
      <div className="stat-grid-4" style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:14, marginBottom:20 }}>
        {[
          {l:'Total Driver',   v:total,              c:'var(--blue)',   fStatus:'',         fPermit:''},
          {l:'Approved',       v:s.approved||0,      c:'var(--green)',  fStatus:'approved', fPermit:''},
          {l:'Permit Expired', v:s.permit_expired||0,c:'var(--red)',    fStatus:'',         fPermit:'expired'},
          {l:'Permit <30hr',   v:s.permit_warning||0,c:'var(--accent)', fStatus:'',         fPermit:'warning'},
        ].map((st,i) => {
          const isActive = st.fStatus !== ''
            ? status === st.fStatus
            : st.fPermit !== ''
              ? permit === st.fPermit
              : status === '' && permit === '';
          return (
            <div key={i}
              onClick={() => { setStatus(st.fStatus); setPermit(st.fPermit); doFilter(search,st.fStatus,license,st.fPermit); }}
              style={{
                borderRadius:10, padding:'14px 16px', textAlign:'center',
                cursor:'pointer', transition:'all .15s',
                background: isActive ? `${st.c==='var(--blue)'?'rgba(58,143,224':st.c==='var(--green)'?'rgba(34,201,122':st.c==='var(--red)'?'rgba(224,69,69':'rgba(232,160,32'},.1)` : 'var(--card)',
                border: `1px solid ${isActive ? st.c : 'var(--border)'}`,
                boxShadow: isActive ? `0 0 0 1px ${st.c}` : 'none',
              }}>
              <div style={{fontFamily:'Syne,sans-serif',fontSize:32,fontWeight:700,color:st.c,lineHeight:1.1,margin:'6px 0 4px'}}>{st.v}</div>
              <div style={{fontSize:10.5,color:isActive?st.c:'var(--muted)',textTransform:'uppercase',letterSpacing:'.07em',fontWeight:isActive?700:600}}>{st.l}</div>
              {isActive && <div style={{width:20,height:3,borderRadius:99,background:st.c,margin:'6px auto 0'}}/>}
            </div>
          );
        })}
      </div>

      <div className="panel">
        <div className="panel-head">
          <div className="panel-title">🚛 Data Driver — PT. Andalas Karya Mulia</div>
          <div style={{display:'flex',gap:8,alignItems:'center',flexWrap:'wrap'}}>
            <select value={status} onChange={e=>{setStatus(e.target.value);doFilter(search,e.target.value,license,permit);}}>
              <option value="">Semua Status</option>
              <option value="approved">Approved</option>
              <option value="in_progress">In Progress</option>
              <option value="waiting">Waiting</option>
              <option value="rejected">Rejected</option>
            </select>
            <select value={license} onChange={e=>{setLicense(e.target.value);doFilter(search,status,e.target.value,permit);}}>
              <option value="">Semua SIM</option>
              {['A','BI','BII','BIU','BIIU'].map(l=><option key={l} value={l}>{l}</option>)}
            </select>
            <select value={permit} onChange={e=>{setPermit(e.target.value);doFilter(search,status,license,e.target.value);}}>
              <option value="">Semua Permit</option>
              <option value="expired">Expired</option>
              <option value="warning">&lt;30 Hari</option>
            </select>
            {!isViewer && (
              <button onClick={()=>setModal({mode:'add'})}
                style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                ➕ Tambah
              </button>
            )}

            {!isViewer && (
              <button onClick={()=>setShowImport(true)}
                style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                📥 Import
              </button>
            )}
            <a href="/export/driver" style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif"}}>📤 Export</a>
          </div>
        </div>

        <div style={{padding:'14px 16px'}}>
          <div style={{position:'relative',marginBottom:14}}>
            <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',fontSize:15,color:'var(--muted)'}}>🔍</span>
            <input className="search-input" type="text" value={search}
              placeholder="Cari nama, NIK, no. SIM..."
              onChange={e=>{setSearch(e.target.value);doFilter(e.target.value,status,license,permit);}}
              style={{paddingLeft:'36px'}} />
          </div>

          <div style={{overflowX:'auto'}}>
            <table className="kar-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nama Driver</th>
                  <th>NIK</th>
                  <th>SIM</th>
                  <th>No. SIM</th>
                  <th>RFID</th>
                  <th>Post Test</th>
                  <th>Permit Expired</th>
                  <th>Status</th>
                  <th>Posttest</th>
                  <th>DVP</th>
                  {!isViewer && <th style={{textAlign:'center'}}>Aksi</th>}
                </tr>
              </thead>
              <tbody>
                {data.map((d,i) => (
                  <tr key={d.id||i}>
                    <td style={{color:'var(--muted)',fontSize:11}}>{i+1}</td>
                    <td>
                      <div style={{fontWeight:500,fontSize:13}}>{d.name}</div>
                      <div style={{fontSize:10,color:'var(--muted)'}}>{d.badge||''}</div>
                    </td>
                    {/* NIK — strip .0 supaya 0 depan tidak hilang */}
                    <td style={{fontSize:11,fontFamily:'monospace',color:'var(--muted2)'}}>
                      {stripDotZero(d.id_card) || '—'}
                    </td>
                    <td>
                      <span style={{background:'rgba(58,143,224,.12)',color:'var(--blue)',padding:'2px 8px',borderRadius:99,fontSize:11,fontWeight:600}}>
                        {d.license_type||'—'}
                      </span>
                    </td>
                    {/* No. SIM — strip .0 */}
                    <td style={{fontSize:11,fontFamily:'monospace'}}>
                      {stripDotZero(d.license_no) || '—'}
                    </td>
                    <td style={{fontSize:11,fontFamily:'monospace',color:'var(--muted2)'}}>
                      {stripDotZero(d.rfid) || '—'}
                    </td>
                    <td style={{fontSize:12,whiteSpace:'nowrap'}}>{fmtDate(d.posttest_schedule)}</td>
                    <td style={{whiteSpace:'nowrap'}}>
                      <div style={{fontSize:12}}>{fmtDate(d.permit_expired_date)}</div>
                      <PermitPill dateStr={d.permit_expired_date} status={d.permit_status} />
                    </td>
                    <td><DriverStatusPill status={d.driver_status} /></td>
                    <td><PosttestPill status={d.posttest_status} /></td>
                    <td>
                      <span style={{fontSize:10.5,color:d.dvp_status==='KP has been exist'?'var(--green)':'var(--muted2)'}}>
                        {d.dvp_status==='KP has been exist'?'✅ Ada':d.dvp_status||'—'}
                      </span>
                    </td>
                    {!isViewer && (
                      <td>
                        <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                          <button onClick={()=>setModal({mode:'edit',data:{...d}})}
                            style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            ✏️ Edit
                          </button>
                          <button onClick={()=>setConfirmHapus(d)}
                            style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            🗑️ Hapus
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
                {data.length===0 && (
                  <tr><td colSpan={isViewer?11:12} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Tidak ada data driver</td></tr>
                )}
              </tbody>
            </table>
          </div>

          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginTop:14}}>
            <div style={{fontSize:11,color:'var(--muted)'}}>Menampilkan {data.length} dari {total} driver</div>
            <div style={{display:'flex',gap:4}}>
              <button disabled={!prevUrl} onClick={()=>prevUrl&&router.get(prevUrl)}
                style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:prevUrl?'var(--text)':'var(--muted)',cursor:prevUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>← Prev</button>
              {links.map((l,i) => (
                <button key={i} disabled={!l.url} onClick={()=>l.url&&router.get(l.url)}
                  style={{padding:'5px 10px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:l.active?'var(--accent)':'var(--card)',color:l.active?'#0C0F14':l.url?'var(--text)':'var(--muted)',cursor:l.url?'pointer':'default',fontFamily:"'Outfit',sans-serif",fontWeight:l.active?700:400}}>
                  {l.label}
                </button>
              ))}
              <button disabled={!nextUrl} onClick={()=>nextUrl&&router.get(nextUrl)}
                style={{padding:'5px 12px',borderRadius:6,fontSize:12,border:'1px solid var(--border)',background:'var(--card)',color:nextUrl?'var(--text)':'var(--muted)',cursor:nextUrl?'pointer':'default',fontFamily:"'Outfit',sans-serif"}}>Next →</button>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}