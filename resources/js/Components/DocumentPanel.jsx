// resources/js/Components/DocumentPanel.jsx
import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import {
  Camera, CreditCard, Users, Stethoscope, Pill, ClipboardList, ShieldCheck,
  ScrollText, HardHat, FileText, Folder, X, Download, Upload, Loader2, Eye, Trash2,
} from 'lucide-react';

const TIPE_CONFIG = {
  foto:           { label:'Foto Karyawan',        color:'#E8A020', icon:Camera },
  ktp:            { label:'KTP',                  color:'#3A8FE0', icon:CreditCard },
  kk:             { label:'Kartu Keluarga',       color:'#22C97A', icon:Users },
  bpjs:           { label:'BPJS Ketenagakerjaan', color:'#9B59B6', icon:Stethoscope },
  bpjs_kesehatan: { label:'BPJS Kesehatan',       color:'#3ABCDE', icon:Pill },
  cv:             { label:'CV / Curriculum Vitae',color:'#22C97A', icon:ClipboardList },
  skck:           { label:'SKCK',                 color:'#E04545', icon:ShieldCheck },
  sio:            { label:'Sertifikat SIO',       color:'#E06A20', icon:ScrollText },
  k3u:            { label:'Sertifikat K3U',       color:'#E8A020', icon:HardHat },
  lainnya:        { label:'Dokumen Lainnya',      color:'#6B7494', icon:FileText },
};

export default function DocumentPanel({ employeeId, employeeName }) {
  const [docs,      setDocs]      = useState([]);
  const [loading,   setLoading]   = useState(true);
  const [uploading, setUploading] = useState(false);
  const [tipe,      setTipe]      = useState('foto');
  const [preview,   setPreview]   = useState(null); // {url, nama}
  const fileRef = useRef(null);

  useEffect(() => { fetchDocs(); }, [employeeId]);

  function fetchDocs() {
    setLoading(true);
    axios.get(`/employees/${employeeId}/documents`)
      .then(r => setDocs(r.data))
      .finally(() => setLoading(false));
  }

  function handleUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    const isFoto = tipe === 'foto';
    const allowedTypes = isFoto
      ? ['application/pdf','image/jpeg','image/jpg','image/png']
      : ['application/pdf'];
    if (!allowedTypes.includes(file.type)) {
      alert(isFoto ? 'File harus PDF, JPG, atau PNG.' : 'Hanya file PDF yang diizinkan.');
      return;
    }
    if (file.size > 5 * 1024 * 1024) { alert('Ukuran file maksimal 5MB.'); return; }

    const fd = new FormData();
    fd.append('file', file);
    fd.append('tipe', tipe);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');

    setUploading(true);
    axios.post(`/employees/${employeeId}/documents`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    .then(r => { setDocs(prev => [...prev, r.data]); })
    .catch(err => alert(err.response?.data?.message || 'Upload gagal.'))
    .finally(() => { setUploading(false); fileRef.current.value = ''; });
  }

  function handleDelete(docId, nama) {
    if (!window.confirm(`Hapus file "${nama}"?`)) return;
    axios.delete(`/employees/documents/${docId}`, {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }
    })
    .then(() => setDocs(prev => prev.filter(d => d.id !== docId)))
    .catch(() => alert('Gagal menghapus file.'));
  }

  // Group docs by tipe
  const grouped = {};
  docs.forEach(d => {
    if (!grouped[d.tipe]) grouped[d.tipe] = [];
    grouped[d.tipe].push(d);
  });

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'8px 11px', fontSize:12.5,
    fontFamily:"'Outfit',sans-serif", outline:'none',
  };

  return (
    <>
      {/* PDF Preview Modal */}
      {preview && (
        <div style={{position:'fixed',top:0,right:0,bottom:0,left:'var(--sidebar-w, 220px)',zIndex:400,background:'rgba(0,0,0,.8)',display:'flex',flexDirection:'column'}}
          onClick={e=>e.target===e.currentTarget&&setPreview(null)}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'12px 20px',background:'var(--bg2)',borderBottom:'1px solid var(--border)'}}>
            <div style={{fontSize:13,fontWeight:600,display:'flex',alignItems:'center',gap:6}}><FileText size={15}/> {preview.nama}</div>
            <div style={{display:'flex',gap:8}}>
              <a href={preview.url.replace('/preview','/download')} download
                style={{padding:'6px 14px',borderRadius:7,background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12,fontWeight:600,textDecoration:'none',display:'flex',alignItems:'center',gap:6}}>
                <Upload size={14}/> Download
              </a>
              <button type="button" onClick={()=>setPreview(null)}
                style={{padding:'6px 12px',borderRadius:7,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted)',cursor:'pointer',fontSize:12,display:'flex',alignItems:'center',gap:6}}>
                <X size={13}/> Tutup
              </button>
            </div>
          </div>
          {preview.isImage
            ? <img src={preview.url} alt={preview.nama} style={{flex:1,objectFit:'contain',background:'#1a1a1a',padding:16}} />
            : <iframe src={preview.url} style={{flex:1,border:'none',background:'#fff'}} title={preview.nama} />
          }
        </div>
      )}

      <div style={{marginBottom:24}}>
        <div style={{fontSize:12,fontWeight:600,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.08em',marginBottom:14,paddingBottom:8,borderBottom:'1px solid var(--border)',display:'flex',alignItems:'center',gap:6}}>
          <Folder size={14}/> Dokumen Karyawan
        </div>

        {/* Upload Area */}
        <div style={{display:'flex',gap:10,alignItems:'center',marginBottom:16,padding:'12px 14px',borderRadius:9,background:'var(--bg3)',border:'1px solid var(--border)'}}>
          <select style={{...inp,width:180}} value={tipe} onChange={e=>setTipe(e.target.value)}>
            <option value="foto">Foto Karyawan</option>
            <option value="ktp">KTP</option>
            <option value="kk">Kartu Keluarga</option>
            <option value="bpjs">BPJS Ketenagakerjaan</option>
            <option value="bpjs_kesehatan">BPJS Kesehatan</option>
            <option value="cv">CV / Curriculum Vitae</option>
            <option value="skck">SKCK</option>
            <option value="sio">Sertifikat SIO</option>
            <option value="k3u">Sertifikat K3U</option>
            <option value="lainnya">Lainnya</option>
          </select>
          <label style={{
            padding:'8px 16px', borderRadius:8, cursor:'pointer', fontSize:12.5, fontWeight:600,
            background:'linear-gradient(135deg,#E8A020,#A06010)', color:'#0C0F14',
            display:'flex', alignItems:'center', gap:6,
            opacity: uploading ? .6 : 1,
          }}>
            {uploading
              ? <><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> Mengupload...</>
              : <><Download size={14}/> {tipe === 'foto' ? 'Upload Foto' : 'Upload PDF'}</>}
            <input ref={fileRef} type="file"
              accept={tipe==='foto' ? '.pdf,.jpg,.jpeg,.png' : '.pdf'}
              onChange={handleUpload} disabled={uploading}
              style={{display:'none'}} />
          </label>
          <span style={{fontSize:11,color:'var(--muted)'}}>
            {tipe==='foto' ? 'Maks. 5MB · PDF / JPG / PNG' : 'Maks. 5MB · PDF only'}
          </span>
        </div>

        {/* Docs List */}
        {loading ? (
          <div style={{padding:20,textAlign:'center',color:'var(--muted)',fontSize:12,display:'flex',alignItems:'center',justifyContent:'center',gap:6}}><Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> Memuat dokumen...</div>
        ) : docs.length === 0 ? (
          <div style={{padding:20,textAlign:'center',color:'var(--muted)',fontSize:12,borderRadius:9,border:'1px dashed var(--border)'}}>
            Belum ada dokumen. Upload KTP atau Sertifikat SIO di atas.
          </div>
        ) : (
          <div style={{display:'flex',flexDirection:'column',gap:6}}>
            {/* Group by tipe */}
            {Object.entries(grouped).map(([tipeKey, tipeDocs]) => {
              const tc = TIPE_CONFIG[tipeKey] || TIPE_CONFIG.lainnya;
              return (
                <div key={tipeKey}>
                  <div style={{fontSize:10.5,fontWeight:700,color:tc.color,textTransform:'uppercase',letterSpacing:'.06em',marginBottom:6,marginTop:8,display:'flex',alignItems:'center',gap:5}}>
                    <tc.icon size={12}/> {tc.label} ({tipeDocs.length})
                  </div>
                  {tipeDocs.map((doc,i) => (
                    <div key={i} style={{
                      display:'flex', alignItems:'center', gap:10,
                      padding:'10px 14px', borderRadius:8,
                      background:'var(--bg3)', border:'1px solid var(--border)',
                      marginBottom:4,
                    }}>
                      <span style={{flexShrink:0, display:'flex'}}><FileText size={18}/></span>
                      <div style={{flex:1, minWidth:0}}>
                        <div style={{fontSize:12.5, fontWeight:500, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}}>
                          {doc.nama_file}
                        </div>
                        <div style={{fontSize:10.5, color:'var(--muted)', marginTop:2}}>
                          {doc.size_formatted} · Diupload {doc.created_at} oleh {doc.uploaded_by}
                        </div>
                      </div>
                      <div style={{display:'flex', gap:6, flexShrink:0}}>
                        <button
                          type="button" onClick={()=>setPreview({url:`/employees/documents/${doc.id}/preview`, nama:doc.nama_file})}
                          style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(58,143,224,.1)',color:'var(--blue)',border:'1px solid rgba(58,143,224,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                          <Eye size={11}/> Lihat
                        </button>
                        <a href={`/employees/documents/${doc.id}/download`}
                          style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(34,201,122,.1)',color:'#22C97A',border:'1px solid rgba(34,201,122,.25)',textDecoration:'none',display:'inline-flex',alignItems:'center'}}>
                          <Download size={11}/>
                        </a>
                        <button
                          type="button" onClick={()=>handleDelete(doc.id, doc.nama_file)}
                          style={{padding:'4px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                          <Trash2 size={11}/>
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}
