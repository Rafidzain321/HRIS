import React, { useState } from 'react';
import { router } from '@inertiajs/react';

export default function ImportModal({ onClose, importUrl, templateUrl, title = 'Import Excel' }) {
  const [file,    setFile]    = useState(null);
  const [loading, setLoading] = useState(false);
  const [result,  setResult]  = useState(null);
  const [error,   setError]   = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    if (!file) { alert('Pilih file Excel terlebih dahulu!'); return; }
    setLoading(true); setError(''); setResult(null);
    const fd = new FormData();
    fd.append('file', file);
    router.post(importUrl, fd, {
      forceFormData: true, preserveScroll: true, preserveState: true,
      onSuccess: (page) => {
        setLoading(false);
        const r = page.props?.flash?.import_result;
        if (r) setResult(r);
        else setResult({ imported: 0, skipped: 0, errors: ['Tidak ada response dari server.'], skipped_list: [] });
      },
      onError: (errors) => {
        setLoading(false);
        setError(errors?.file || 'Upload gagal. Pastikan file adalah .xlsx dan ukuran maks 10MB.');
      },
      onFinish: () => setLoading(false),
    });
  }

  const inp = {
    background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
    borderRadius:8, padding:'9px 12px', fontSize:13,
    fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%',
  };

  return (
    <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:520,maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div>
            <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>📥 {title}</div>
            <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>Gunakan template resmi HRIS AKM</div>
          </div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>✕</div>
        </div>

        {error && (
          <div style={{margin:'14px 20px 0',padding:'10px 14px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)',fontSize:12,color:'#E04545'}}>
            ❌ {error}
          </div>
        )}

        {result && (
          <div style={{margin:'16px 20px 0',display:'flex',flexDirection:'column',gap:10}}>

            {result.unit !== undefined ? (
              // ── Hasil import Equipment (2 sheet sekaligus) ──
              <>
                {/* Unit */}
                <div style={{fontSize:12,fontWeight:700,color:'var(--text)',paddingBottom:6,borderBottom:'1px solid var(--border)'}}>
                  📦 Equipment Unit
                </div>
                <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:10}}>
                  <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(34,201,122,.1)',border:'1px solid rgba(34,201,122,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'#22C97A'}}>{result.unit.imported}</div>
                    <div style={{fontSize:11,color:'var(--muted)'}}>Berhasil</div>
                  </div>
                  <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(232,160,32,.1)',border:'1px solid rgba(232,160,32,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'var(--accent)'}}>{result.unit.skipped}</div>
                    <div style={{fontSize:11,color:'var(--muted)'}}>Di-skip</div>
                  </div>
                  <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'#E04545'}}>{result.unit.errors?.length||0}</div>
                    <div style={{fontSize:11,color:'var(--muted)'}}>Error</div>
                  </div>
                </div>
                {result.unit.skipped_list?.length>0 && (
                  <div style={{background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',borderRadius:9,padding:'10px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'var(--accent)',marginBottom:6}}>⚠️ Unit di-skip (sudah ada):</div>
                    <div style={{maxHeight:80,overflowY:'auto'}}>
                      {result.unit.skipped_list.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--muted2)'}}>• {s}</div>)}
                    </div>
                  </div>
                )}
                {result.unit.errors?.length>0 && (
                  <div style={{background:'rgba(224,69,69,.08)',border:'1px solid rgba(224,69,69,.2)',borderRadius:9,padding:'10px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'#E04545',marginBottom:6}}>❌ Error Unit:</div>
                    <div style={{maxHeight:80,overflowY:'auto'}}>
                      {result.unit.errors.map((e,i)=><div key={i} style={{fontSize:11,color:'#E04545'}}>• {e}</div>)}
                    </div>
                  </div>
                )}

                {/* Operator */}
                {result.operator && (
                  <>
                    <div style={{fontSize:12,fontWeight:700,color:'var(--text)',paddingBottom:6,borderBottom:'1px solid var(--border)',marginTop:4}}>
                      👷 Operator
                    </div>
                    <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:10}}>
                      <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(34,201,122,.1)',border:'1px solid rgba(34,201,122,.25)'}}>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'#22C97A'}}>{result.operator.imported}</div>
                        <div style={{fontSize:11,color:'var(--muted)'}}>Berhasil</div>
                      </div>
                      <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(232,160,32,.1)',border:'1px solid rgba(232,160,32,.25)'}}>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'var(--accent)'}}>{result.operator.skipped}</div>
                        <div style={{fontSize:11,color:'var(--muted)'}}>Di-skip</div>
                      </div>
                      <div style={{textAlign:'center',padding:'10px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)'}}>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:22,fontWeight:700,color:'#E04545'}}>{result.operator.errors?.length||0}</div>
                        <div style={{fontSize:11,color:'var(--muted)'}}>Error</div>
                      </div>
                    </div>
                    {result.operator.errors?.length>0 && (
                      <div style={{background:'rgba(224,69,69,.08)',border:'1px solid rgba(224,69,69,.2)',borderRadius:9,padding:'10px 14px'}}>
                        <div style={{fontSize:11.5,fontWeight:600,color:'#E04545',marginBottom:6}}>❌ Error Operator:</div>
                        <div style={{maxHeight:80,overflowY:'auto'}}>
                          {result.operator.errors.map((e,i)=><div key={i} style={{fontSize:11,color:'#E04545'}}>• {e}</div>)}
                        </div>
                      </div>
                    )}
                    {result.operator.not_registered?.length>0 && (
                      <div style={{background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',borderRadius:9,padding:'10px 14px'}}>
                        <div style={{fontSize:11.5,fontWeight:600,color:'var(--blue)',marginBottom:6}}>⚠️ Operator belum di Data Karyawan:</div>
                        <div style={{maxHeight:80,overflowY:'auto'}}>
                          {result.operator.not_registered.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--blue)'}}>• {s}</div>)}
                        </div>
                      </div>
                    )}
                  </>
                )}
              </>
            ) : (
              // ── Hasil import biasa (CCPM, Driver, Training) ──
              <>
                <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:10}}>
                  <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(34,201,122,.1)',border:'1px solid rgba(34,201,122,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'#22C97A'}}>{result.imported}</div>
                    <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Berhasil Import</div>
                  </div>
                  <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(232,160,32,.1)',border:'1px solid rgba(232,160,32,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'var(--accent)'}}>{result.skipped}</div>
                    <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Di-skip (duplikat)</div>
                  </div>
                  <div style={{textAlign:'center',padding:'12px',borderRadius:9,background:'rgba(224,69,69,.1)',border:'1px solid rgba(224,69,69,.25)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:26,fontWeight:700,color:'#E04545'}}>{result.errors?.length||0}</div>
                    <div style={{fontSize:11,color:'var(--muted)',marginTop:3}}>Error</div>
                  </div>
                </div>
                {result.skipped_list?.length>0 && (
                  <div style={{background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',borderRadius:9,padding:'12px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'var(--accent)',marginBottom:8}}>⚠️ Di-skip (sudah ada):</div>
                    <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                      {result.skipped_list.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--muted2)'}}>• {s}</div>)}
                    </div>
                  </div>
                )}
                {result.errors?.length>0 && (
                  <div style={{background:'rgba(224,69,69,.08)',border:'1px solid rgba(224,69,69,.2)',borderRadius:9,padding:'12px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'#E04545',marginBottom:8}}>❌ Error:</div>
                    <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                      {result.errors.map((err,i)=><div key={i} style={{fontSize:11,color:'#E04545'}}>• {err}</div>)}
                    </div>
                  </div>
                )}
                {result.name_warning?.length>0 && (
                  <div style={{background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',borderRadius:9,padding:'12px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'var(--accent)',marginBottom:8}}>⚠️ Diimport tapi perlu dicek manual:</div>
                    <div style={{fontSize:11,color:'var(--muted2)',marginBottom:6}}>Data berikut diimport karena tidak ada HES Passport/Badge, tapi nama sudah ada — kemungkinan duplikat.</div>
                    <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                      {result.name_warning.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--accent)'}}>• {s}</div>)}
                    </div>
                  </div>
                )}
                {result.not_registered?.length>0 && (
                  <div style={{background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',borderRadius:9,padding:'12px 14px'}}>
                    <div style={{fontSize:11.5,fontWeight:600,color:'var(--blue)',marginBottom:8}}>⚠️ Operator berikut belum terdaftar di Data Karyawan:</div>
                    <div style={{fontSize:11,color:'var(--muted2)',marginBottom:6}}>Data operator berhasil diimport, namun perlu ditambahkan manual di menu <b>Data Karyawan</b>.</div>
                    <div style={{display:'flex',flexDirection:'column',gap:4,maxHeight:120,overflowY:'auto'}}>
                      {result.not_registered.map((s,i)=><div key={i} style={{fontSize:11,color:'var(--blue)'}}>• {s}</div>)}
                    </div>
                  </div>
                )}
                {result.imported>0 && (
                  <div style={{background:'rgba(34,201,122,.08)',border:'1px solid rgba(34,201,122,.2)',borderRadius:9,padding:'10px 14px',fontSize:12,color:'#22C97A',fontWeight:600}}>
                    ✅ {result.imported} data berhasil ditambahkan ke sistem!
                  </div>
                )}
              </>
            )}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:14}}>
            <div style={{background:'rgba(58,143,224,.08)',border:'1px solid rgba(58,143,224,.2)',borderRadius:9,padding:'12px 14px'}}>
              <div style={{fontSize:12,fontWeight:600,color:'var(--blue)',marginBottom:6}}>📋 Sebelum import, pastikan:</div>
              <div style={{fontSize:11.5,color:'var(--muted2)',display:'flex',flexDirection:'column',gap:4}}>
                <div>✓ Gunakan template resmi (download di bawah)</div>
                <div>✓ Data diisi mulai baris ke-5</div>
                <div>✓ Format tanggal: DD-MM-YYYY (ketik sebagai teks)</div>
                <div>✓ Kolom wajib tidak boleh kosong</div>
                <div>✓ Data yang sudah ada akan otomatis di-skip</div>
              </div>
              <a href={templateUrl}
                style={{display:'inline-flex',alignItems:'center',gap:6,marginTop:10,padding:'6px 14px',borderRadius:7,background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12,fontWeight:600,textDecoration:'none'}}>
                📥 Download Template Excel
              </a>
            </div>
            <div>
              <label style={{fontSize:11.5,color:'var(--muted)',marginBottom:6,display:'block'}}>Pilih File Excel (.xlsx)</label>
              <input type="file" accept=".xlsx,.xls"
                style={{...inp,cursor:'pointer',padding:'7px 12px'}}
                onChange={e=>{setFile(e.target.files[0]);setResult(null);setError('');}} />
              {file && <div style={{fontSize:11,color:'var(--green)',marginTop:4}}>✓ {file.name} ({(file.size/1024).toFixed(1)} KB)</div>}
            </div>
          </div>
          <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
            <button type="button" onClick={onClose}
              style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
              {result ? 'Tutup' : 'Batal'}
            </button>
            <button type="submit" disabled={loading||!file}
              style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:loading||!file?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading||!file?0.6:1}}>
              {loading ? '⏳ Mengimport...' : '📥 Import Sekarang'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}