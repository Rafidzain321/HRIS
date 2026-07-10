// resources>js>Pages>Ccpm>Index.jsx
import { ConfirmModal } from '@/Layouts/AppLayout';
import React, { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import ImportModal from '@/Components/ImportModal';

const CCPM_COLS = [
  { key:'id_card',          label:'NIK / KTP' },
  { key:'hes_passport',     label:'HES Passport' },
  { key:'birth_place',      label:'Tempat Lahir' },
  { key:'birth_date',       label:'Tgl Lahir' },
  { key:'ffd_valid_date',   label:'FFD Valid' },
  { key:'badge_valid_date', label:'Badge Valid' },
  { key:'job_title',        label:'Jabatan' },
  { key:'team_assignment',  label:'Team' },
  { key:'status',           label:'Status' },
  { key:'status_medical',   label:'Status Medical' },
];
const DEFAULT_CCPM_COLS = ['hes_passport','job_title','badge_valid_date','ffd_valid_date','status','status_medical'];

function ColPickerModal({ selected, onClose, onApply }) {
  const [sel, setSel] = useState([...selected]);
  function toggle(key) {
    setSel(prev => prev.includes(key) ? prev.filter(k=>k!==key) : [...prev, key]);
  }
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(480px, calc(100vw - 24px))',maxHeight:'80vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>⚙️ Pilih Kolom Tampilan</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>
        <div style={{padding:'16px 20px'}}>
          <div style={{fontSize:11.5,color:'var(--muted)',marginBottom:16,padding:'8px 12px',background:'var(--bg3)',borderRadius:8}}>
            Kolom No. dan Nama selalu tampil. Pilih kolom tambahan:
          </div>
          <div style={{display:'flex',flexWrap:'wrap',gap:8}}>
            {CCPM_COLS.map(col=>(
              <div key={col.key} onClick={()=>toggle(col.key)}
                style={{padding:'6px 14px',borderRadius:8,cursor:'pointer',fontSize:12,fontWeight:600,transition:'all .15s',
                  background:sel.includes(col.key)?'rgba(232,160,32,.15)':'var(--bg3)',
                  color:sel.includes(col.key)?'var(--accent)':'var(--muted2)',
                  border:`1px solid ${sel.includes(col.key)?'var(--accent)':'var(--border)'}`}}>
                {sel.includes(col.key)?'✓ ':''}{col.label}
              </div>
            ))}
          </div>
        </div>
        <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={()=>setSel(DEFAULT_CCPM_COLS)}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Reset Default
          </button>
          <button type="button" onClick={onClose}
            style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            Batal
          </button>
          <button type="button" onClick={()=>{ onApply(sel); onClose(); }}
            style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
            ✓ Terapkan
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Format tanggal helper ────────────────────────────────────
function fmtDate(val) {
  if (!val) return '—';
  try {
    const d = new Date(val);
    if (isNaN(d.getTime())) return '—';
    const m = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return `${String(d.getDate()).padStart(2,'0')} ${m[d.getMonth()]} ${d.getFullYear()}`;
  } catch {
    return '—';
  }
}

// ── Status pills ─────────────────────────────────────────────
function statusPill(status) {
  const s = (status || '').toLowerCase();
  if (s === 'complete')                return <span className="pill pill-green">Complete</span>;
  if (s.includes('in progress'))       return <span className="pill pill-warn">In Progress</span>;
  if (s.includes('reject by medical')) return <span className="pill pill-red">Reject Medical</span>;
  if (s.includes('reject'))            return <span className="pill pill-red">Rejected</span>;
  if (s.includes('waiting'))           return <span className="pill pill-blue">Waiting</span>;
  return <span className="pill pill-gray">{status || '—'}</span>;
}

function medicalPill(status) {
  const s = (status || '').toLowerCase();
  if (s === 'completed')         return <span className="pill pill-green">Completed</span>;
  if (s.includes('reject'))      return <span className="pill pill-red">Reject</span>;
  if (s.includes('waiting'))     return <span className="pill pill-warn">Waiting</span>;
  if (!status || s === 'nan')    return <span className="pill pill-gray">—</span>;
  return <span className="pill pill-blue">{status}</span>;
}

// ── Modal Tambah/Edit ────────────────────────────────────────
function CcpmModal({ mode, data, onClose, onSave }) {
  const [form, setForm] = useState(data || {
    badge:'', id_card:'', hes_passport:'', name:'',
    birth_place:'', birth_date:'', ffd_valid_date:'', badge_valid_date:'',
    job_title:'', team_assignment:'FACILITY ENGINEERING', status:'', status_medical:'',
  });

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }));

  function handleSave() {
    if (!form.name || !form.name.trim()) {
        alert('Nama wajib diisi!');
        return;
    }
    onSave(form);
  }

  const inputStyle = {
    background:'var(--bg3, #1a1d23)', border:'1px solid var(--border, #333)', color:'var(--text, #fff)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%',
  };

  return (
    <div style={{
      position:'fixed', inset:0, zIndex:300,
      background:'rgba(0,0,0,.65)', display:'flex',
      alignItems:'center', justifyContent:'center',
    }} onClick={e => e.target===e.currentTarget && onClose()}>
      <div style={{
        background:'var(--bg2, #111418)', border:'1px solid var(--border2, #222)',
        borderRadius:16, width:'min(640px, calc(100vw - 24px))', maxHeight:'90vh',
        overflow:'auto', boxShadow:'0 24px 80px rgba(0,0,0,.5)',
      }}>
        {/* Header */}
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'16px 20px', borderBottom:'1px solid var(--border)' }}>
          <div style={{ fontFamily:'Syne,sans-serif', fontSize:15, fontWeight:700, color:'var(--text)' }}>
            {mode === 'add' ? '➕ Tambah Manpower CCPM' : '✏️ Edit Manpower CCPM'}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', fontSize:18, color:'var(--muted)', padding:'0 4px' }}>✕</div>
        </div>

        {/* Form */}
        <div style={{ padding:'20px', display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px 16px' }}>
          {[
            { key:'badge',           label:'Badge ID',           placeholder:'AKM - 724229846' },
            { key:'id_card',         label:'No. ID Card (KTP)', placeholder:'1403092606960004' },
            { key:'hes_passport',    label:'HES Passport',      placeholder:'PHR-WKROKAN-D-0064727' },
            { key:'name',            label:'Nama Lengkap *',    placeholder:'NAMA KARYAWAN', full:true },
            { key:'birth_place',     label:'Tempat Lahir',      placeholder:'DURI' },
            { key:'birth_date',      label:'Tanggal Lahir',      type:'date' },
            { key:'ffd_valid_date',  label:'FFD Valid Date',    type:'date' },
            { key:'badge_valid_date',label:'Badge Valid Date',  type:'date' },
            { key:'job_title',       label:'Jabatan / Job Title', placeholder:'Driver Dump Truck' },
            { key:'team_assignment', label:'Team Assignment',   placeholder:'FACILITY ENGINEERING' },
          ].map(f => (
            <div key={f.key} style={{ gridColumn: f.full ? '1 / -1' : 'auto' }}>
              <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>{f.label}</label>
              <input
                type={f.type || 'text'}
                style={inputStyle}
                value={form[f.key] || ''}
                placeholder={f.placeholder || ''}
                onChange={e => set(f.key, e.target.value)}
              />
            </div>
          ))}

          <div>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Status</label>
            <select style={inputStyle} value={form.status || ''} onChange={e => set('status', e.target.value)}>
              <option value="">— Pilih —</option>
              <option>Complete</option>
              <option>In Progress</option>
              <option>In Progress (Revisi)</option>
              <option>Reject By Medical</option>
              <option>Waiting for Pengawas Pekerjaan Approval</option>
              <option>Rejected by Contract Reviewer</option>
            </select>
          </div>

          <div>
            <label style={{ fontSize:11, color:'var(--muted)', marginBottom:4, display:'block' }}>Status Medical</label>
            <select style={inputStyle} value={form.status_medical || ''} onChange={e => set('status_medical', e.target.value)}>
              <option value="">— Pilih —</option>
              <option>Completed</option>
              <option>Reject By Medical</option>
              <option>Waiting Approval Medical</option>
            </select>
          </div>
        </div>

        {/* Actions */}
        <div style={{ display:'flex', gap:10, justifyContent:'flex-end', padding:'12px 20px', borderTop:'1px solid var(--border)' }}>
          <button onClick={onClose} style={{
            padding:'9px 18px', borderRadius:8, border:'1px solid var(--border)',
            background:'var(--bg3)', color:'var(--muted2)', fontSize:12.5,
            cursor:'pointer', fontFamily:"'Outfit',sans-serif",
          }}>Batal</button>
          <button onClick={handleSave} style={{
            padding:'9px 22px', borderRadius:8, border:'none',
            background:'linear-gradient(135deg,#E8A020,#A06010)',
            color:'#0C0F14', fontSize:12.5, fontWeight:700,
            cursor:'pointer', fontFamily:"'Outfit',sans-serif",
          }}>
            {mode === 'add' ? '➕ Tambah' : '💾 Simpan'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── MAIN PAGE ────────────────────────────────────────────────
export default function CcpmIndex({ manpower = { data:[], total:0 }, filters = {} }) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [showImport, setShowImport] = useState(false);
  const [search, setSearch]       = useState(filters.search || '');
  const [statusFilter, setStatus] = useState(filters.status || '');
  const [jobFilter, setJob]       = useState(filters.job    || '');
  const [modal, setModal]         = useState(null);
  const [confirmHapus, setConfirmHapus] = useState(null);

  const [showColPicker, setShowColPicker] = useState(false);
  const [activeCols, setActiveCols] = useState(() => {
    try { const s = localStorage.getItem('ccpm-cols'); return s ? JSON.parse(s) : DEFAULT_CCPM_COLS; }
    catch { return DEFAULT_CCPM_COLS; }
  });
  function applyCols(cols) {
    setActiveCols(cols);
    localStorage.setItem('ccpm-cols', JSON.stringify(cols));
  }

  function doFilter(s, st, j) {
    router.get('/ccpm', { search:s, status:st, job:j }, { preserveState:true, replace:true });
  }

  function openAdd()         { setModal({ mode:'add' }); }
  function openEdit(row)     { setModal({ mode:'edit', data:{ ...row } }); }
  function closeModal()      { setModal(null); }

  function handleSave(form) {
    if (modal.mode === 'add') {
      router.post('/ccpm', form, {
        onSuccess: () => { closeModal(); },
        onError: e => alert('Gagal: ' + JSON.stringify(e)),
      });
    } else {
      router.put(`/ccpm/${form.id}`, form, {
        onSuccess: () => { closeModal(); },
        onError: e => alert('Gagal: ' + JSON.stringify(e)),
      });
    }
  }

  const data  = manpower?.data  || [];
  const total = manpower?.total || 0;

  const links = (manpower?.links || []).filter(l =>
    l.label !== '&laquo; Previous' && l.label !== 'Next &raquo;'
  );
  const prevUrl = manpower?.prev_page_url;
  const nextUrl = manpower?.next_page_url;

  return (
    <AppLayout title="Data" subtitle="CCPM">
      {modal && (
        <CcpmModal
          mode={modal.mode}
          data={modal.data}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}

      {showColPicker && (
        <ColPickerModal
          selected={activeCols}
          onClose={()=>setShowColPicker(false)}
          onApply={applyCols}
        />
      )}

      <ConfirmModal
        open={!!confirmHapus}
        onCancel={()=>setConfirmHapus(null)}
        onConfirm={()=>{
        const s = search, st = statusFilter, j = jobFilter;
        router.delete(`/ccpm/${confirmHapus.id}`, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => doFilter(s, st, j),
        });
        setConfirmHapus(null);
      }}
        title="Hapus Data CCPM"
        message={confirmHapus ? <>Data <b style={{color:'var(--text)'}}>{confirmHapus.name}</b> akan dihapus permanen dari sistem.</> : ''}
        confirmLabel="Ya, Hapus"
        type="danger"
      />

      {showImport && (
        <ImportModal
          onClose={()=>setShowImport(false)}
          importUrl="/ccpm/import"
          templateUrl="/ccpm/template-import"
          title="Import Data CCPM"
        />
      )}

      {/* STATS — clickable filter cards */}
      <div className="stat-grid-4" style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:14, marginBottom:20 }}>
        {[
          { label:'Total Manpower',   val: total,                      color:'var(--blue, #3a8fe0)',   fStatus:''          },
          { label:'Complete (Status)', val: manpower.complete    || 0, color:'var(--green, #22c97a)', fStatus:'complete'  },
          { label:'In Progress',      val: manpower.in_progress || 0, color:'var(--accent, #e8a020)',fStatus:'in_progress'},
          { label:'Reject Medical',   val: manpower.reject      || 0, color:'var(--red, #e04545)',    fStatus:'reject'    },
        ].map((s, i) => {
          const isActive = statusFilter === s.fStatus;
          return (
            <div key={i}
              onClick={()=>{ setStatus(s.fStatus); doFilter(search, s.fStatus, jobFilter); }}
              style={{
                borderRadius:10, padding:'14px 16px', textAlign:'center',
                cursor:'pointer', transition:'all .15s',
                background: isActive
                  ? (s.color === 'var(--blue, #3a8fe0)'   ? 'rgba(58,143,224,.1)'
                  : s.color === 'var(--green, #22c97a)'  ? 'rgba(34,201,122,.1)'
                  : s.color === 'var(--accent, #e8a020)' ? 'rgba(232,160,32,.1)'
                  : 'rgba(224,69,69,.1)')
                  : 'var(--card, #161a1f)',
                border: `1px solid ${isActive ? s.color : 'var(--border, #333)'}`,
                boxShadow: isActive ? `0 0 0 1px ${s.color}` : 'none',
              }}>
              <div style={{ fontFamily:'Syne,sans-serif', fontSize:32, fontWeight:700, color:s.color, lineHeight:1.1, margin:'6px 0 4px' }}>{s.val}</div>
              <div style={{ fontSize:10.5, color:isActive?s.color:'var(--muted)', textTransform:'uppercase', letterSpacing:'.07em', fontWeight:isActive?700:600 }}>{s.label}</div>
              {isActive && <div style={{width:20,height:3,borderRadius:99,background:s.color,margin:'6px auto 0'}}/>}
            </div> // <-- PERBAIKAN: Tag penutup div tadinya hilang
          );
        })}
      </div>

      <div className="panel">
        <div className="panel-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '15px', flexWrap: 'wrap', gap: 10 }}>
          <div className="panel-title" style={{ fontWeight: 700 }}>📋 Data Manpower CCPM — Facility Engineering</div>
          <div style={{ display:'flex', gap:8, alignItems:'center', flexWrap:'wrap' }}>
            <select value={statusFilter} onChange={e => { setStatus(e.target.value); doFilter(search, e.target.value, jobFilter); }}>
              <option value="">Semua Status</option>
              <option value="complete">Complete</option>
              <option value="in_progress">In Progress</option>
              <option value="reject">Reject</option>
              <option value="waiting">Waiting</option>
            </select>
            <select value={jobFilter} onChange={e => { setJob(e.target.value); doFilter(search, statusFilter, e.target.value); }}>
              <option value="">Semua Jabatan</option>
              {(manpower?.job_titles || []).map(j => <option key={j} value={j}>{j}</option>)}
            </select>
            {!isViewer && (
              <button onClick={openAdd} style={{padding:'6px 14px',borderRadius:7,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>➕ Tambah</button>
            )}
            {!isViewer && (
              <button onClick={()=>setShowImport(true)}
                style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(34,201,122,.3)',background:'rgba(34,201,122,.08)',color:'var(--green)',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                📥 Import
              </button>
            )}
            <button onClick={()=>setShowColPicker(true)}
              style={{padding:'6px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}>
              ⚙️ Kolom
            </button>
          <a href="/export/ccpm" style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,cursor:'pointer',textDecoration:'none',fontFamily:"'Outfit',sans-serif"}}>📤 Export</a>
          </div>
        </div>

        <div style={{ padding:'14px 16px' }}>
          {/* Search */}
          <div style={{ position:'relative', marginBottom:14 }}>
            <span style={{ position:'absolute', left:11, top:'50%', transform:'translateY(-50%)', fontSize:15, color:'var(--muted)', pointerEvents:'none' }}>🔍</span>
            <input className="search-input" type="text" value={search}
              placeholder="Cari nama, badge, HES passport, jabatan..."
              onChange={e => { setSearch(e.target.value); doFilter(e.target.value, statusFilter, jobFilter); }}
              style={{paddingLeft:'36px', width: '100%', boxSizing: 'border-box'}}
            />
          </div>

          {/* Table */}
          <div style={{ overflowX:'auto' }}>
            <table className="kar-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ textAlign: 'left', borderBottom: '1px solid var(--border)' }}>
                  <th style={{ padding: '10px' }}>#</th>
                  <th>Nama</th>
                  {activeCols.map(k => {
                    const col = CCPM_COLS.find(c=>c.key===k);
                    return <th key={k}>{col?.label||k}</th>;
                  })}
                  {!isViewer && <th style={{ textAlign:'center' }}>Aksi</th>}
                </tr>
              </thead>
              <tbody>
                {data.map((e, i) => (
                  <tr key={e.id || i} style={{ borderBottom: '1px solid var(--border)' }}>
                    <td style={{ color:'var(--muted)', fontSize:11, padding: '10px' }}>{i + 1}</td>
                    <td style={{ minWidth:160 }}>
                      <div style={{fontWeight:500, fontSize:12.5}}>{e.name}</div>
                      <div style={{fontSize:11,fontFamily:'monospace',color:'var(--accent)',marginTop:1}}>{e.badge||'—'}</div>
                    </td>
                    {activeCols.map(k => {
                      const isDate = k.endsWith('_date');
                      const val = e[k];
                      if (k === 'status')         return <td key={k}>{statusPill(val)}</td>;
                      if (k === 'status_medical') return <td key={k}>{medicalPill(val)}</td>;
                      return (
                        <td key={k} style={{fontSize:12, whiteSpace:isDate?'nowrap':undefined}}>
                          {isDate ? fmtDate(val) : (val||'—')}
                        </td>
                      );
                    })}
                    {!isViewer && (
                      <td>
                        <div style={{ display:'flex', gap:5, justifyContent:'center' }}>
                          <button onClick={() => openEdit(e)} style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer'}}>✏️ Edit</button>
                          <button onClick={()=>setConfirmHapus(e)}
                            style={{padding:'3px 9px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer'}}>
                            🗑️ Hapus
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
                {data.length === 0 && (
                  <tr><td colSpan={2 + activeCols.length + (isViewer?0:1)} style={{ padding:24, textAlign:'center', color:'var(--muted)' }}>Tidak ada data</td></tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginTop:14 }}>
            <div style={{ fontSize:11, color:'var(--muted)' }}>
              Menampilkan {data.length} dari {total} manpower
            </div>
            <div style={{ display:'flex', gap:4, alignItems:'center' }}>
              <button
                disabled={!prevUrl}
                onClick={() => prevUrl && router.get(prevUrl)}
                style={{
                  padding:'5px 12px', borderRadius:6, fontSize:12, cursor:prevUrl?'pointer':'default',
                  border:'1px solid var(--border)', background:'var(--card)', color:prevUrl?'var(--text)':'var(--muted)',
                }}>← Prev</button>

              {links.map((link, i) => (
                <button key={i}
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url)}
                  style={{
                    padding:'5px 10px', borderRadius:6, fontSize:12,
                    border:'1px solid var(--border)',
                    background: link.active ? 'var(--accent)' : 'var(--card)',
                    color: link.active ? '#0C0F14' : link.url ? 'var(--text)' : 'var(--muted)',
                    cursor: link.url ? 'pointer' : 'default',
                    fontWeight: link.active ? 700 : 400,
                  }}
                >{link.label}</button>
              ))}

              <button
                disabled={!nextUrl}
                onClick={() => nextUrl && router.get(nextUrl)}
                style={{
                  padding:'5px 12px', borderRadius:6, fontSize:12, cursor:nextUrl?'pointer':'default',
                  border:'1px solid var(--border)', background:'var(--card)', color:nextUrl?'var(--text)':'var(--muted)',
                }}>Next →</button>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
