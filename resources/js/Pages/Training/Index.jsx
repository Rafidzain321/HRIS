// resources>js>Pages>Training>Index.jsx
import { ConfirmModal } from '@/Layouts/AppLayout';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import ImportModal from '@/Components/ImportModal';

const STATUS_CONFIG = {
  expired:  { label:'Expired',      bg:'rgba(224,69,69,.12)',   color:'#E04545' },
  warning:  { label:'< 30 Hari',    bg:'rgba(232,160,32,.12)',  color:'var(--accent)' },
  valid:    { label:'Valid',         bg:'rgba(34,201,122,.12)',  color:'#22C97A' },
  lifetime: { label:'Seumur Hidup', bg:'rgba(58,143,224,.12)',  color:'var(--blue)' },
  no_date:  { label:'Tgl Kosong',   bg:'rgba(128,128,128,.12)', color:'var(--muted2)' },
};

function StatusPill({ status }) {
  const c = STATUS_CONFIG[status] || STATUS_CONFIG.lifetime;
  return (
    <span style={{ background:c.bg, color:c.color, padding:'2px 9px', borderRadius:99, fontSize:10.5, fontWeight:600 }}>
      {c.label}
    </span>
  );
}


// ── EDIT TRAINING MODAL ─────────────────────────────────────
function EditTrainingModal({ training, types, onClose, onSaved }) {
  const [form, setForm] = React.useState({
    tanggal:      training.tanggal      || '',
    nama_trainer: training.nama_trainer || '',
    nilai:        training.nilai        || '',
    status:       training.status       || '',
    expired_date: training.expired_date || '',
    catatan:      training.catatan      || '',
  });
  const [loading, setLoading] = React.useState(false);
  const [error,   setError]   = React.useState('');

  const selectedType = types.find(t => t.nama === training.jenis);

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
  };

  function submit(e) {
    e.preventDefault();
    setLoading(true); setError('');
    const token = document.querySelector('meta[name=csrf-token]')?.content;
    fetch(`/employees/trainings/${training.id}`, {
      method: 'PUT',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify(form),
    })
    .then(r => r.json())
    .then(() => { onSaved(); onClose(); })
    .catch(() => setError('Gagal menyimpan.'))
    .finally(() => setLoading(false));
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(500px,calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'14px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:14,fontWeight:700}}>✏️ Edit Training — {training.nama_lengkap}</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:17,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:12}}>
            {error && <div style={{background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.2)',borderRadius:8,padding:'8px 12px',fontSize:12,color:'#E04545'}}>⚠️ {error}</div>}

            {/* Jenis training - readonly */}
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Jenis Training</label>
              <div style={{...inp,background:'var(--bg3)',color:'var(--accent)',fontWeight:600,cursor:'default'}}>
                {training.jenis}
                {selectedType && <span style={{marginLeft:8,fontSize:10.5,color:'var(--muted)',fontWeight:400}}>· {selectedType.masa_berlaku_label}</span>}
              </div>
            </div>

            <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'10px 14px'}}>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal Training</label>
                <input type="date" style={inp} value={form.tanggal}
                  onChange={e=>setForm(p=>({...p,tanggal:e.target.value}))} />
              </div>
              <div>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Nama Trainer</label>
                <input type="text" style={inp} value={form.nama_trainer}
                  placeholder="Nama trainer" onChange={e=>setForm(p=>({...p,nama_trainer:e.target.value}))} />
              </div>

              {selectedType?.has_nilai && (
                <div>
                  <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Nilai Post Test</label>
                  <input type="text" style={inp} value={form.nilai}
                    placeholder="cth: 93" onChange={e=>setForm(p=>({...p,nilai:e.target.value}))} />
                </div>
              )}

              <div>
                <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Status</label>
                <select style={inp} value={form.status} onChange={e=>setForm(p=>({...p,status:e.target.value}))}>
                  <option value="">— Pilih —</option>
                  <option value="PASS">PASS</option>
                  <option value="FAIL">FAIL</option>
                  <option value="HADIR">HADIR</option>
                </select>
              </div>

              {selectedType?.has_expired && !selectedType?.masa_berlaku_tahun && (
                <div>
                  <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal Expired</label>
                  <input type="date" style={inp} value={form.expired_date}
                    onChange={e=>setForm(p=>({...p,expired_date:e.target.value}))} />
                </div>
              )}
            </div>

            {selectedType?.masa_berlaku_tahun && (
              <div style={{fontSize:11,color:'var(--muted)',background:'var(--bg3)',borderRadius:8,padding:'8px 12px'}}>
                💡 Expired otomatis dihitung: Tgl Training + {selectedType.masa_berlaku_tahun} tahun
              </div>
            )}

            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Catatan</label>
              <textarea style={{...inp,minHeight:56,resize:'vertical'}} value={form.catatan}
                placeholder="Catatan tambahan (opsional)"
                onChange={e=>setForm(p=>({...p,catatan:e.target.value}))} />
            </div>
          </div>

          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose}
              style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
              Batal
            </button>
            <button type="submit" disabled={loading}
              style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
              {loading ? '⏳...' : '💾 Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// ── MODAL JENIS TRAINING ─────────────────────────────────────
function TrainingTypeModal({ mode, item, onClose }) {
  const [form, setForm] = useState({
    nama:               item?.nama               || '',
    deskripsi:          item?.deskripsi          || '',
    masa_berlaku_tahun: item?.masa_berlaku_tahun || '',
    has_nilai:          item?.has_nilai          || false,
  });
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');
  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
  };
  function submit(e) {
    e.preventDefault();
    if (!form.nama.trim()) { setError('Nama training wajib diisi.'); return; }
    setLoading(true); setError('');
    const url    = mode==='add'?'/pengaturan/training-types':`/pengaturan/training-types/${item.id}`;
    const method = mode==='add'?'post':'put';
    router[method](url, {...form, masa_berlaku_tahun:form.masa_berlaku_tahun||null}, {
      onSuccess:()=>{ setLoading(false); onClose(); },
      onError:(err)=>{ setLoading(false); setError(err.nama||'Gagal menyimpan.'); },
    });
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(420px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>{mode==='add'?'➕ Tambah Jenis Training':'✏️ Edit Jenis Training'}</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <form onSubmit={submit}>
          <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:13}}>
            {error&&<div style={{background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.2)',borderRadius:8,padding:'8px 12px',fontSize:12,color:'#E04545'}}>⚠️ {error}</div>}
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Nama Training *</label>
              <input type="text" style={{...inp,borderColor:error?'#E04545':'var(--border)'}}
                value={form.nama} onChange={e=>setForm(p=>({...p,nama:e.target.value}))}
                placeholder="cth: SWP Hot Work" autoFocus />
            </div>
            <div>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Masa Berlaku</label>
              <select style={inp} value={form.masa_berlaku_tahun} onChange={e=>setForm(p=>({...p,masa_berlaku_tahun:e.target.value}))}>
                <option value="">∞ Seumur Hidup</option>
                {[1,2,3,4,5].map(n=><option key={n} value={n}>{n} Tahun</option>)}
              </select>
            </div>
            <div style={{display:'flex',alignItems:'center',gap:10}}>
              <div onClick={()=>setForm(p=>({...p,has_nilai:!p.has_nilai}))}
                style={{width:40,height:22,borderRadius:11,background:form.has_nilai?'#22C97A':'var(--bg3)',border:'1px solid var(--border)',position:'relative',cursor:'pointer',transition:'background .2s'}}>
                <div style={{position:'absolute',top:2,left:form.has_nilai?20:2,width:16,height:16,borderRadius:'50%',background:'#fff',boxShadow:'0 1px 3px rgba(0,0,0,.3)',transition:'left .2s'}}/>
              </div>
              <label style={{fontSize:12.5,cursor:'pointer'}} onClick={()=>setForm(p=>({...p,has_nilai:!p.has_nilai}))}>Ada nilai / post test</label>
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

export default function TrainingIndex({ trainings=[], types=[], filters={}, stats={} }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [showImport, setShowImport] = useState(false);
  const [mainTab, setMainTab] = useState('data');
  const [search,     setSearch]     = useState(filters.search || '');
  const [typeFilter, setTypeFilter] = useState(filters.typeFilter || '');
  const [statusFilter,setStatusFilter] = useState(filters.statusFilter || '');
  const [editModal,   setEditModal]    = useState(null);
  const [trainingTypeModal, setTrainingTypeModal] = useState(null);
  const [confirmHapusType, setConfirmHapusType] = useState(null);

  function handleDeleteType(t) {
    setConfirmHapusType(t);
  }

  function reloadPage() {
    router.reload({ preserveScroll: true });
  }

  function doFilter(s, t, st) {
    router.get('/training', { search:s, type:t, status:st }, { preserveState:true, replace:true });
  }

  const statCards = [
    { label:'Expired',      val: stats.expiredCount  || 0, color:'#E04545',        key:'expired'  },
    { label:'< 30 Hari',    val: stats.warningCount  || 0, color:'var(--accent)',   key:'warning'  },
    { label:'Valid',        val: stats.validCount    || 0, color:'#22C97A',         key:'valid'    },
    { label:'Seumur Hidup', val: stats.lifetimeCount || 0, color:'var(--blue)',     key:'lifetime' },
    { label:'Tgl Kosong',   val: stats.noDeteCount   || 0, color:'var(--muted2)',   key:'no_date'  },
  ];

  return (
    <AppLayout title="Data" subtitle="Training">
      {editModal && (
        <EditTrainingModal
          training={editModal}
          types={types}
          onClose={()=>setEditModal(null)}
          onSaved={reloadPage}
        />
      )}

      {trainingTypeModal && (
        <TrainingTypeModal
          mode={trainingTypeModal.mode}
          item={trainingTypeModal.item}
          onClose={()=>setTrainingTypeModal(null)}
        />
      )}

      <ConfirmModal
        open={!!confirmHapusType}
        onCancel={()=>setConfirmHapusType(null)}
        onConfirm={()=>{
          router.delete(`/pengaturan/training-types/${confirmHapusType.id}`, { preserveScroll: true });
          setConfirmHapusType(null);
        }}
        title="Hapus Jenis Training"
        message={confirmHapusType ? <>Hapus jenis training <b style={{color:'var(--text)'}}>{confirmHapusType.nama}</b>? Tindakan ini tidak bisa dibatalkan.</> : ''}
        confirmLabel="Ya, Hapus"
        type="danger"
      />

      {showImport && (
        <ImportModal
          onClose={()=>setShowImport(false)}
          importUrl="/training/import"
          templateUrl="/training/template-import"
          title="Import Data Training"
        />
      )}

      {/* Tab switcher utama */}
      <div style={{display:'flex',gap:4,marginBottom:16}}>
        {[
          {key:'data',  label:'📚 Data Training'},
          {key:'jenis', label:'⚙️ Jenis Training', count:types.length},
        ].map(t=>(
          <div key={t.key} onClick={()=>setMainTab(t.key)}
            style={{
              padding:'8px 18px',borderRadius:8,cursor:'pointer',
              fontSize:13,fontWeight:600,transition:'all .15s',
              display:'flex',alignItems:'center',gap:8,
              background:mainTab===t.key?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)',
              color:mainTab===t.key?'#0C0F14':'var(--muted)',
              border:`1px solid ${mainTab===t.key?'transparent':'var(--border)'}`,
            }}>
            {t.label}
            {t.count!==undefined&&(
              <span style={{background:mainTab===t.key?'rgba(0,0,0,.2)':'var(--bg3)',color:mainTab===t.key?'#0C0F14':'var(--muted2)',fontSize:10.5,fontWeight:700,padding:'1px 7px',borderRadius:99}}>
                {t.count}
              </span>
            )}
          </div>
        ))}
      </div>

{mainTab==='data' && (<>
      {/* Stat Cards */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:12, marginBottom:16 }} className="stat-grid-5">
        {statCards.map((s,i) => {
          const isActive = statusFilter === s.key;
          return (
            <div key={i}
              onClick={()=>{ setStatusFilter(isActive?'':s.key); doFilter(search, typeFilter, isActive?'':s.key); }}
              style={{
                borderRadius:10, padding:'14px 16px', textAlign:'center',
                cursor:'pointer', transition:'all .15s',
                background: isActive ? `rgba(${s.color==='#E04545'?'224,69,69':s.color==='var(--accent)'?'232,160,32':s.color==='#22C97A'?'34,201,122':s.color==='var(--muted2)'?'128,128,128':'58,143,224'},.1)` : 'var(--card)',
                border: `1px solid ${isActive ? s.color : 'var(--border)'}`,
                boxShadow: isActive ? `0 0 0 1px ${s.color}` : 'none',
              }}>
              <div style={{ fontFamily:'Syne,sans-serif', fontSize:28, fontWeight:700, color:s.color }}>{s.val}</div>
              <div style={{ fontSize:10.5, color:isActive?s.color:'var(--muted)', marginTop:3, fontWeight:isActive?600:400 }}>{s.label}</div>
              {isActive && <div style={{ width:20, height:3, borderRadius:99, background:s.color, margin:'5px auto 0' }}/>}
            </div>
          );
        })}
      </div>

      {/* Filter by Training Type */}
      <div style={{ display:'flex', gap:8, marginBottom:16, flexWrap:'wrap' }}>
        <div onClick={()=>{ setTypeFilter(''); doFilter(search,'',statusFilter); }}
          style={{ padding:'6px 14px', borderRadius:7, cursor:'pointer', fontSize:12, fontWeight:600, transition:'all .15s', background:typeFilter===''?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)', color:typeFilter===''?'#0C0F14':'var(--muted)', border:`1px solid ${typeFilter===''?'transparent':'var(--border)'}` }}>
          Semua ({trainings.length})
        </div>
        {types.map((t,i) => (
          <div key={i}
            onClick={()=>{ setTypeFilter(String(t.id)); doFilter(search, String(t.id), statusFilter); }}
            style={{ padding:'6px 14px', borderRadius:7, cursor:'pointer', fontSize:12, fontWeight:600, transition:'all .15s', background:typeFilter===String(t.id)?'linear-gradient(135deg,#E8A020,#A06010)':'var(--card)', color:typeFilter===String(t.id)?'#0C0F14':'var(--muted)', border:`1px solid ${typeFilter===String(t.id)?'transparent':'var(--border)'}` }}>
            {t.nama}
            <span style={{ marginLeft:5, fontSize:10.5, opacity:.75 }}>({t.trainings_count})</span>
          </div>
        ))}
      </div>

      <div className="panel">
        <div className="panel-head">
          <div className="panel-title">📚 Data Training Karyawan</div>
          <div style={{display:'flex',gap:10,alignItems:'center',flexWrap:'wrap'}}>
            <div style={{ fontSize:11.5, color:'var(--muted)' }}>{trainings.length} record</div>
            {!isViewer && (
              <button onClick={()=>setShowImport(true)}
                style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                📥 Import
              </button>
            )}
            <a href="/export/training" style={{fontSize:11.5,color:'var(--accent)',cursor:'pointer',textDecoration:'none'}}>📤 Export</a>
          </div>
        </div>

        {/* Search */}
        <div style={{ padding:'14px 16px 0' }}>
          <div style={{ position:'relative', marginBottom:14 }}>
            <span style={{ position:'absolute', left:11, top:'50%', transform:'translateY(-50%)', fontSize:15, color:'var(--muted)' }}>🔍</span>
            <input className="search-input" type="text" value={search}
              placeholder="Cari nama atau NIK..."
              onChange={e=>{ setSearch(e.target.value); doFilter(e.target.value, typeFilter, statusFilter); }}
              style={{ paddingLeft:'36px' }} />
          </div>
        </div>

        <div style={{ overflowX:'auto' }}>
          <table className="kar-table" style={{ minWidth:900 }}>
            <thead>
              <tr>
                <th style={{ paddingLeft:16 }}>NIK</th>
                <th>Nama Karyawan</th>
                <th>Jabatan</th>
                <th>Jenis Training</th>
                <th style={{ textAlign:'center' }}>Tgl Training</th>
                <th>Trainer</th>
                <th style={{ textAlign:'center' }}>Nilai</th>
                <th style={{ textAlign:'center' }}>Status</th>
                <th style={{ textAlign:'center' }}>Expired</th>
                <th style={{ textAlign:'center' }}>Sisa</th>
                <th style={{ textAlign:'center' }}>Ket.</th>
              </tr>
            </thead>
            <tbody>
              {trainings.map((t,i) => (
                <tr key={i}
                  onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                  onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                  <td style={{ fontFamily:'monospace', fontSize:11, color:'var(--accent)', paddingLeft:16 }}>{t.no_ktp || '—'}</td>
                  <td style={{ fontWeight:500 }}>{t.nama_lengkap}</td>
                  <td style={{ fontSize:11.5, color:'var(--muted2)' }}>{t.jabatan}</td>
                  <td>
                    <span style={{ background:'rgba(232,160,32,.08)', color:'var(--accent)', padding:'2px 8px', borderRadius:6, fontSize:11, fontWeight:600 }}>
                      {t.jenis}
                    </span>
                  </td>
                  <td style={{ textAlign:'center', fontSize:11.5, color:'var(--muted2)' }}>{t.tanggal || '—'}</td>
                  <td style={{ fontSize:11.5, color:'var(--muted2)' }}>{t.nama_trainer || '—'}</td>
                  <td style={{ textAlign:'center' }}>
                    {t.nilai
                      ? <span style={{ background:'rgba(58,143,224,.1)', color:'var(--blue)', padding:'2px 8px', borderRadius:99, fontSize:11.5, fontWeight:700 }}>{t.nilai}</span>
                      : <span style={{ color:'var(--muted)' }}>—</span>}
                  </td>
                  <td style={{ textAlign:'center' }}>
                    {t.status
                      ? <span style={{ background:t.status==='PASS'?'rgba(34,201,122,.1)':'rgba(224,69,69,.1)', color:t.status==='PASS'?'#22C97A':'#E04545', padding:'2px 8px', borderRadius:99, fontSize:11, fontWeight:600 }}>{t.status}</span>
                      : <span style={{ color:'var(--muted)' }}>—</span>}
                  </td>
                  <td style={{ textAlign:'center', fontSize:11, color:'var(--muted2)', whiteSpace:'nowrap' }}>
                    {t.auto_expired_fmt || '—'}
                  </td>
                  <td style={{ textAlign:'center' }}>
                    {t.status_expired === 'lifetime'
                      ? <span style={{ fontSize:11, color:'var(--muted)' }}>∞</span>
                      : t.status_expired === 'no_date'
                      ? <span style={{ fontSize:11, color:'var(--muted)' }}>—</span>
                      : t.sisa_hari !== null
                        ? <span style={{ fontSize:11, fontWeight:600, color: t.sisa_hari < 0 ? '#E04545' : t.sisa_hari <= 30 ? 'var(--accent)' : '#22C97A' }}>
                            {t.sisa_hari < 0 ? `${Math.abs(t.sisa_hari)}hr lalu` : `${t.sisa_hari}hr`}
                          </span>
                        : <span style={{ color:'var(--muted)' }}>—</span>}
                  </td>
                  <td style={{ textAlign:'center' }}>
                    <StatusPill status={t.status_expired} />
                  </td>
                  <td style={{textAlign:'center'}}>
                    {!isViewer && (
                      <a href={`/employees/${t.employee_id}/edit`}
                        style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',textDecoration:'none',display:'inline-block'}}>
                        ✏️ Edit
                      </a>
                    )}
                  </td>
                </tr>
              ))}
              {trainings.length === 0 && (
                <tr><td colSpan={11} style={{ padding:32, textAlign:'center', color:'var(--muted)' }}>Tidak ada data training</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
      </>)}

      {mainTab==='jenis' && (
        <div className="panel">
          <div className="panel-head">
            <div className="panel-title">⚙️ Jenis Training</div>
            {!isViewer && (
              <button onClick={()=>setTrainingTypeModal({mode:'add'})}
                style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                ➕ Tambah Jenis
              </button>
            )}
          </div>
          <div style={{padding:'14px 16px'}}>
            <table className="kar-table">
              <thead>
                <tr>
                  <th>Nama Training</th>
                  <th style={{textAlign:'center'}}>Masa Berlaku</th>
                  <th style={{textAlign:'center'}}>Nilai</th>
                  <th style={{textAlign:'center'}}>Karyawan</th>
                  {!isViewer && <th style={{textAlign:'center'}}>Aksi</th>}
                </tr>
              </thead>
              <tbody>
                {types.map((t,i)=>(
                  <tr key={i}
                    onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                    onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                    <td style={{fontWeight:500}}>{t.nama}</td>
                    <td style={{textAlign:'center'}}>
                      <span style={{background:t.masa_berlaku_tahun?'rgba(232,160,32,.1)':'rgba(58,143,224,.1)',color:t.masa_berlaku_tahun?'var(--accent)':'var(--blue)',padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>
                        {t.masa_berlaku_tahun?`${t.masa_berlaku_tahun} Tahun`:'∞ Seumur Hidup'}
                      </span>
                    </td>
                    <td style={{textAlign:'center',fontSize:13}}>{t.has_nilai?'✅':'—'}</td>
                    <td style={{textAlign:'center'}}>
                      <span style={{background:'rgba(58,143,224,.1)',color:'var(--blue)',padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{t.trainings_count} karyawan</span>
                    </td>
                    {!isViewer && (
                      <td>
                        <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                          <button onClick={()=>setTrainingTypeModal({mode:'edit',item:t})}
                            style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>✏️</button>
                          <button disabled={t.trainings_count>0}
                            onClick={()=>{ if(t.trainings_count>0) return; handleDeleteType(t); }}
                            style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:t.trainings_count>0?'rgba(128,128,128,.08)':'rgba(224,69,69,.1)',color:t.trainings_count>0?'var(--muted)':'#E04545',border:`1px solid ${t.trainings_count>0?'var(--border)':'rgba(224,69,69,.2)'}`,cursor:t.trainings_count>0?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:t.trainings_count>0?.5:1}}>🗑️</button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
                {types.length===0&&<tr><td colSpan={5} style={{padding:24,textAlign:'center',color:'var(--muted)'}}>Belum ada jenis training</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </AppLayout>
  );
}
