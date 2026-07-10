// resources/js/Components/TrainingPanel.jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

const STATUS_CONFIG = {
  expired:  { label:'Expired',      bg:'rgba(224,69,69,.12)',   color:'#E04545' },
  warning:  { label:'< 30 Hari',    bg:'rgba(232,160,32,.12)',  color:'var(--accent)' },
  valid:    { label:'Valid',         bg:'rgba(34,201,122,.12)',  color:'#22C97A' },
  lifetime: { label:'Seumur Hidup', bg:'rgba(58,143,224,.12)',  color:'var(--blue)' },
  no_date:  { label:'Tgl Kosong',   bg:'rgba(128,128,128,.12)', color:'var(--muted2)' },
};

function TrainingModal({ mode, training, types, employeeId, onClose, onSaved }) {
  const [form, setForm] = useState({
    training_type_id: training?.training_type_id || '',
    tanggal:          training?.tanggal          || '',
    nama_trainer:     training?.nama_trainer      || '',
    nilai:            training?.nilai             || '',
    status:           training?.status            || '',
    expired_date:     training?.expired_date      || '',
    catatan:          training?.catatan           || '',
  });
  const [loading, setLoading] = useState(false);
  const [error,   setError]   = useState('');

  const selectedType = types.find(t => String(t.id) === String(form.training_type_id));

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'7px 10px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
  };

  function submit(e) {
    e.preventDefault();
    if (!form.training_type_id) { setError('Pilih jenis training.'); return; }
    setLoading(true); setError('');
    const token = document.querySelector('meta[name=csrf-token]')?.content;
    const headers = { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' };

    const req = mode === 'add'
      ? axios.post(`/employees/${employeeId}/trainings`, form, { headers })
      : axios.put(`/employees/trainings/${training.id}`, form, { headers });

    req.then(r => { onSaved(); onClose(); })
       .catch(err => { setError(err.response?.data?.message || 'Gagal menyimpan.'); })
       .finally(() => setLoading(false));
  }

  return (
    <div style={{position:'fixed', inset:0, zIndex:400, background:'rgba(0,0,0,.65)', display:'flex', alignItems:'center', justifyContent:'center'}}>
      <div style={{ background:'var(--bg2)', border:'1px solid var(--border2)', borderRadius:16, width:'min(480px,calc(100vw - 24px))', maxHeight:'90vh', overflow:'auto', boxShadow:'0 24px 80px rgba(0,0,0,.5)' }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', padding:'14px 18px', borderBottom:'1px solid var(--border)', position:'sticky', top:0, background:'var(--bg2)', zIndex:1 }}>
          <div style={{ fontFamily:'Syne,sans-serif', fontSize:14, fontWeight:700 }}>
            {mode==='add' ? '➕ Tambah Training' : '✏️ Edit Training'}
          </div>
          <div onClick={onClose} style={{ cursor:'pointer', fontSize:17, color:'var(--muted)' }}>✕</div>
        </div>

        <form onSubmit={submit}>
          <div style={{ padding:'16px 18px', display:'flex', flexDirection:'column', gap:12 }}>
            {error && <div style={{ background:'rgba(224,69,69,.1)', border:'1px solid rgba(224,69,69,.2)', borderRadius:8, padding:'8px 12px', fontSize:12, color:'#E04545' }}>⚠️ {error}</div>}

            {/* Jenis Training */}
            <div>
              <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Jenis Training *</label>
              <select style={inp} value={form.training_type_id}
                onChange={e=>setForm(p=>({...p, training_type_id:e.target.value, status:'', nilai:''}))}
                disabled={mode==='edit'}>
                <option value="">— Pilih Jenis Training —</option>
                {types.map(t => <option key={t.id} value={t.id}>{t.nama}</option>)}
              </select>
              {selectedType && (
                <div style={{ fontSize:10, color:'var(--muted)', marginTop:3 }}>
                  Masa berlaku: <b style={{ color:'var(--accent)' }}>{selectedType.masa_berlaku_label}</b>
                  {selectedType.masa_berlaku_tahun && ' · Expired otomatis dihitung dari tanggal training'}
                </div>
              )}
            </div>

            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'10px 14px' }}>
              {/* Tanggal Training */}
              <div>
                <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Tanggal Training</label>
                <input type="date" style={inp} value={form.tanggal}
                  onChange={e=>setForm(p=>({...p, tanggal:e.target.value}))} />
              </div>

              {/* Nama Trainer */}
              <div>
                <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Nama Trainer</label>
                <input type="text" style={inp} value={form.nama_trainer}
                  placeholder="Nama trainer" onChange={e=>setForm(p=>({...p, nama_trainer:e.target.value}))} />
              </div>

              {/* Nilai Post Test - hanya tampil kalau has_nilai */}
              {selectedType?.has_nilai && (
                <div>
                  <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Nilai Post Test</label>
                  <input type="text" style={inp} value={form.nilai}
                    placeholder="cth: 93" onChange={e=>setForm(p=>({...p, nilai:e.target.value}))} />
                </div>
              )}

              {/* Status */}
              <div>
                <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Status</label>
                <select style={inp} value={form.status} onChange={e=>setForm(p=>({...p, status:e.target.value}))}>
                  <option value="">— Pilih —</option>
                  <option value="PASS">PASS</option>
                  <option value="FAIL">FAIL</option>
                  <option value="HADIR">HADIR</option>
                </select>
              </div>

              {/* Expired Date - hanya tampil kalau has_expired tapi masa_berlaku null (custom expired) */}
              {selectedType?.has_expired && !selectedType?.masa_berlaku_tahun && (
                <div>
                  <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Tanggal Expired</label>
                  <input type="date" style={inp} value={form.expired_date}
                    onChange={e=>setForm(p=>({...p, expired_date:e.target.value}))} />
                </div>
              )}
            </div>

            {/* Catatan */}
            <div>
              <label style={{ fontSize:10.5, color:'var(--muted)', marginBottom:4, display:'block' }}>Catatan</label>
              <textarea style={{ ...inp, minHeight:56, resize:'vertical' }} value={form.catatan}
                placeholder="Catatan tambahan (opsional)"
                onChange={e=>setForm(p=>({...p, catatan:e.target.value}))} />
            </div>
          </div>

          <div style={{ display:'flex', gap:10, justifyContent:'flex-end', padding:'12px 18px', borderTop:'1px solid var(--border)' }}>
            <button type="button" onClick={onClose}
              style={{ padding:'8px 16px', borderRadius:8, border:'1px solid var(--border)', background:'var(--bg3)', color:'var(--muted2)', fontSize:12, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
              Batal
            </button>
            <button type="submit" disabled={loading}
              style={{ padding:'8px 20px', borderRadius:8, border:'none', background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14', fontSize:12, fontWeight:700, cursor:loading?'not-allowed':'pointer', fontFamily:"'Outfit',sans-serif", opacity:loading?.7:1 }}>
              {loading ? '⏳...' : mode==='add' ? '➕ Tambah' : '💾 Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function TrainingPanel({ employeeId }) {
  const [trainings, setTrainings] = useState([]);
  const [types,     setTypes]     = useState([]);
  const [loading,   setLoading]   = useState(true);
  const [modal,     setModal]     = useState(null); // null | {mode, training?}

  useEffect(() => {
    fetchAll();
    axios.get('/training/types').then(r => setTypes(r.data)).catch(() => {});
  }, [employeeId]);

  function fetchAll() {
    setLoading(true);
    axios.get(`/employees/${employeeId}/trainings`)
      .then(r => setTrainings(r.data))
      .finally(() => setLoading(false));
  }

  function handleDelete(t) {
    if (!window.confirm(`Hapus training "${t.jenis}"?`)) return;
    axios.delete(`/employees/trainings/${t.id}`, {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }
    }).then(() => fetchAll()).catch(() => alert('Gagal menghapus.'));
  }

  const STATUS_CFG = STATUS_CONFIG;

  return (
    <>
      {modal && (
        <TrainingModal
          mode={modal.mode} training={modal.training}
          types={types} employeeId={employeeId}
          onClose={()=>setModal(null)} onSaved={fetchAll}
        />
      )}

      <div style={{ marginBottom:24 }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:14, paddingBottom:8, borderBottom:'1px solid var(--border)' }}>
          <div style={{ fontSize:12, fontWeight:600, color:'var(--accent)', textTransform:'uppercase', letterSpacing:'.08em' }}>
            📚 Training Karyawan
          </div>
          <button type="button" onClick={()=>setModal({mode:'add'})}
            style={{ padding:'6px 14px', borderRadius:7, border:'none', background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14', fontSize:11.5, fontWeight:700, cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
            ➕ Tambah Training
          </button>
        </div>

        {loading ? (
          <div style={{ padding:20, textAlign:'center', color:'var(--muted)', fontSize:12 }}>⏳ Memuat training...</div>
        ) : trainings.length === 0 ? (
          <div style={{ padding:20, textAlign:'center', color:'var(--muted)', fontSize:12, borderRadius:9, border:'1px dashed var(--border)' }}>
            Belum ada data training. Klik ➕ untuk menambahkan.
          </div>
        ) : (
          <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
            {trainings.map((t,i) => {
              const sc = STATUS_CFG[t.status_expired] || STATUS_CFG.lifetime;
              return (
                <div key={i} style={{ display:'flex', alignItems:'center', gap:12, padding:'10px 14px', borderRadius:9, background:'var(--bg3)', border:'1px solid var(--border)' }}>
                  {/* Jenis Training */}
                  <div style={{ flex:'0 0 180px', minWidth:0 }}>
                    <div style={{ fontSize:12, fontWeight:600, color:'var(--accent)' }}>{t.jenis}</div>
                    <div style={{ fontSize:10, color:'var(--muted)', marginTop:1 }}>{t.masa_berlaku_label}</div>
                  </div>

                  {/* Tanggal & Trainer */}
                  <div style={{ flex:1, minWidth:0 }}>
                    <div style={{ fontSize:11.5, color:'var(--text)' }}>
                      {t.tanggal_fmt || <span style={{ color:'var(--muted)' }}>—</span>}
                      {t.nama_trainer && <span style={{ color:'var(--muted2)', marginLeft:6 }}>· {t.nama_trainer}</span>}
                    </div>
                    {t.nilai && (
                      <div style={{ fontSize:10.5, marginTop:2 }}>
                        Nilai: <b style={{ color:'var(--blue)' }}>{t.nilai}</b>
                        {t.status && <span style={{ marginLeft:6, color:t.status==='PASS'?'#22C97A':'#E04545', fontWeight:600 }}>{t.status}</span>}
                      </div>
                    )}
                    {!t.nilai && t.status && (
                      <div style={{ fontSize:10.5, marginTop:2, color:t.status==='PASS'?'#22C97A':'#E04545', fontWeight:600 }}>{t.status}</div>
                    )}
                  </div>

                {/* Expired */}
                <div style={{ flex:'0 0 140px', textAlign:'right' }}>
                {t.status_expired === 'lifetime' ? null
                : t.status_expired === 'no_date' ? (
                  <div style={{ fontSize:10, color:'var(--muted)', fontStyle:'italic' }}>
                    Tanggal belum diisi
                  </div>
                ) : (
                  <>
                  <div style={{ fontSize:10.5, color:'var(--muted2)' }}>{t.auto_expired_fmt || '—'}</div>
                  <div style={{ fontSize:10, fontWeight:600, color:sc.color, marginTop:2 }}>
                    {t.sisa_hari !== null
                      ? t.sisa_hari < 0
                        ? `Expired ${Math.abs(t.sisa_hari)}hr lalu`
                        : `${t.sisa_hari} hari lagi`
                      : ''}
                  </div>
                  </>
                )}
                </div>

                {/* Status pill */}
                <div style={{ flex:'0 0 90px', textAlign:'center' }}>
                <span style={{ background:sc.bg, color:sc.color, padding:'2px 8px', borderRadius:99, fontSize:10.5, fontWeight:600, whiteSpace:'nowrap' }}>
                    {sc.label}
                </span>
                </div>

                  {/* Actions */}
                  <div style={{ display:'flex', gap:5, flexShrink:0 }}>
                    <button type="button" onClick={()=>setModal({mode:'edit', training:t})}
                      style={{ padding:'3px 8px', borderRadius:6, fontSize:11, fontWeight:600, background:'rgba(232,160,32,.12)', color:'var(--accent)', border:'1px solid rgba(232,160,32,.25)', cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
                      ✏️
                    </button>
                    <button type="button" onClick={()=>handleDelete(t)}
                      style={{ padding:'3px 8px', borderRadius:6, fontSize:11, fontWeight:600, background:'rgba(224,69,69,.1)', color:'#E04545', border:'1px solid rgba(224,69,69,.2)', cursor:'pointer', fontFamily:"'Outfit',sans-serif" }}>
                      🗑️
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}
