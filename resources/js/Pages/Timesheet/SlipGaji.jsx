// resources/js/Pages/Timesheet/SlipGaji.jsx
import React, { useState, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';

function rp(val) {
  if (val === null || val === undefined || val === '' || isNaN(val)) return 'Rp -';
  return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

function TabelHariPerJam({ details, isDark }) {
  const thStyle = { padding:'5px 6px', fontSize:9.5, fontWeight:700, textAlign:'center', border:'1px solid #BDBDBD', background:'#F4A010', color:'#1A0A00', whiteSpace:'nowrap' };
  const tdStyle = (hl, bg) => ({ padding:'4px 6px', fontSize:10, textAlign:'center', border:`1px solid ${isDark?'rgba(255,255,255,.1)':'#E0E0E0'}`, background: bg||(hl?(isDark?'rgba(34,201,122,.15)':'#E8F5E9'):'transparent'), fontWeight: hl ? 700 : 400 });
  return (
    <div style={{overflowX:'auto',borderRadius:8,border:`1px solid ${isDark?'rgba(255,255,255,.1)':'#E0E0E0'}`}}>
      <table style={{borderCollapse:'collapse',fontSize:10.5,minWidth:700,width:'100%'}}>
        <thead><tr>{['No','Hari','Tanggal','Jam','Status','Reguler','1.5x','2x','3x','4x','Total OT','Nominal'].map((h,i)=><th key={i} style={thStyle}>{h}</th>)}</tr></thead>
        <tbody>
          {details.map((d,i)=>{
            const isSun=d.status_hari==='L'&&d.is_sunday,isSat=d.status_hari==='P',isHol=d.is_holiday&&!d.is_sunday;
            const rowBg=isSun?(isDark?'rgba(180,0,0,.15)':'#FFEBEE'):isHol?(isDark?'rgba(150,80,0,.15)':'#FFF8E1'):isSat?(isDark?'rgba(20,80,20,.15)':'#F1F8E9'):i%2===0?'':(isDark?'rgba(255,255,255,.02)':'#FAFAFA');
            const txtSun=isSun?'#E04545':'';
            return (<tr key={d.no}>
              <td style={{...tdStyle(false,rowBg),color:'var(--muted)',fontSize:9}}>{d.no}</td>
              <td style={{...tdStyle(false,rowBg),color:txtSun||'var(--text)',fontWeight:isSun?700:400}}>{d.hari}</td>
              <td style={{...tdStyle(false,rowBg),color:txtSun||'var(--text)',whiteSpace:'nowrap'}}>{d.tanggal_fmt}</td>
              <td style={{...tdStyle(d.jam_kerja>0,rowBg),color:d.jam_kerja>=10?'#22C97A':d.jam_kerja>=8?'#E04545':'var(--text)'}}>{isSun&&d.jam_kerja===0?<span style={{color:'#E04545',fontSize:9}}>L</span>:(d.jam_kerja||'')}</td>
              <td style={{...tdStyle(false,rowBg),fontSize:9,color:d.status_hari==='L'?'#E04545':d.status_hari==='P'?'#22C97A':'var(--muted)'}}>{d.status_hari}{d.holiday_label&&<div style={{fontSize:8,color:'#F4A010',lineHeight:1}}>{d.holiday_label}</div>}</td>
              <td style={tdStyle(false,rowBg)}>{d.jam_reguler||''}</td>
              <td style={tdStyle(d.lembur_1_5x>0,rowBg)}>{d.lembur_1_5x||''}</td>
              <td style={tdStyle(d.lembur_2x>0,rowBg)}>{d.lembur_2x||''}</td>
              <td style={tdStyle(d.lembur_3x>0,rowBg)}>{d.lembur_3x||''}</td>
              <td style={tdStyle(d.lembur_4x>0,rowBg)}>{d.lembur_4x||''}</td>
              <td style={{...tdStyle(d.total_jam_lembur>0,rowBg),color:'#E8A020',fontWeight:d.total_jam_lembur>0?700:400}}>{d.total_jam_lembur>0?d.total_jam_lembur.toFixed(1):''}</td>
              <td style={{...tdStyle(d.nominal_lembur>0,rowBg),color:'#22C97A',fontWeight:d.nominal_lembur>0?700:400}}>{d.nominal_lembur>0?'Rp '+Math.round(d.nominal_lembur).toLocaleString('id-ID'):''}</td>
            </tr>);
          })}
          <tr style={{background:isDark?'rgba(232,160,32,.15)':'#FFF8E1'}}>
            <td colSpan={5} style={{...thStyle,textAlign:'right',background:'rgba(232,160,32,.2)'}}>TOTAL</td>
            {['jam_reguler','lembur_1_5x','lembur_2x','lembur_3x','lembur_4x'].map(k=>(<td key={k} style={{...thStyle,background:'rgba(232,160,32,.15)',color:'var(--accent)'}}>{details.reduce((s,d)=>s+(d[k]||0),0).toFixed(k==='jam_reguler'?0:1)}</td>))}
            <td style={{...thStyle,background:'rgba(232,160,32,.2)',color:'#E8A020',fontWeight:700}}>{details.reduce((s,d)=>s+(d.total_jam_lembur||0),0).toFixed(1)}</td>
            <td style={{...thStyle,background:'rgba(34,201,122,.2)',color:'#22C97A',fontWeight:700}}>Rp {Math.round(details.reduce((s,d)=>s+(d.nominal_lembur||0),0)).toLocaleString('id-ID')}</td>
          </tr>
        </tbody>
      </table>
    </div>
  );
}

function TabelFlat({ slip }) {
  const Row = ({label,val,color}) => (
    <div style={{display:'flex',alignItems:'center',padding:'8px 0',borderBottom:'1px solid var(--border)'}}>
      <div style={{flex:1,fontSize:12.5,color:'var(--muted2)'}}>{label}</div>
      <div style={{fontSize:13,fontWeight:600,color:color||'var(--text)'}}>{val}</div>
    </div>
  );
  return (
    <div>
      <Row label="Jumlah Sabtu Kerja" val={`${slip.l_sabtu||0} hari`} color="var(--accent)"/>
      <Row label={`x Tarif Sabtu (Rp ${(slip.tarif_sabtu||0).toLocaleString('id-ID')})`} val={rp((slip.l_sabtu||0)*(slip.tarif_sabtu||0))} color="#22C97A"/>
      <Row label="Jumlah Libur/Minggu Kerja" val={`${slip.l_libur||0} hari`} color="var(--accent)"/>
      <Row label={`x Tarif Libur (Rp ${(slip.tarif_libur||0).toLocaleString('id-ID')})`} val={rp((slip.l_libur||0)*(slip.tarif_libur||0))} color="#22C97A"/>
      <Row label="Lembur Biasa (manual)" val={`${slip.lembur_biasa||0} hari`} color="var(--muted)"/>
      <Row label={`x Tarif Biasa (Rp ${(slip.tarif_biasa||0).toLocaleString('id-ID')})`} val={rp((slip.lembur_biasa||0)*(slip.tarif_biasa||0))} color="#22C97A"/>
      <div style={{display:'flex',alignItems:'center',padding:'10px 12px',background:'rgba(34,201,122,.08)',borderRadius:8,marginTop:8}}>
        <div style={{flex:1,fontSize:13,fontWeight:700}}>Total Lembur</div>
        <div style={{fontSize:15,fontWeight:700,color:'#22C97A'}}>{rp(slip.total_lembur_flat)}</div>
      </div>
    </div>
  );
}

const TTT_LABELS = {
  tunj_makan:'Uang Makan', tunj_produksi:'Tunj. Produksi', tunj_lapangan:'Tunj. Lapangan',
  tunj_kehadiran:'Tunj. Kehadiran', tunj_pulsa:'Tunj. Pulsa',
  kompensasi_kontrak:'Komp. Kontrak', insentif:'Insentif', com_day:'Com Day',
};

function SlipCetak({ slip, bulan_nama, tahun, ttd }) {
  if (!slip) return null;
  const num = (v) => Number(v) || 0;  // Cast semua nilai jadi number, cegah string concat bug
  const rpC = (n) => 'Rp ' + Math.round(num(n)).toLocaleString('id-ID');
  const isMd = slip.tipe_project === 'md';
  const gajiPokok  = num(slip.gaji_pokok);
  const tunjTetap  = num(slip.tunj_tetap);
  const kompPwt    = num(slip.kompensasi_pwt);
  const upahPenuh  = gajiPokok + tunjTetap;
  const upahLembur = num(slip.upah_lembur);
  const gajiKotor  = num(slip.gaji_kotor);
  const pctJht     = slip.pct_jht     ?? 2;
  const pctPensiun = slip.pct_pensiun ?? 1;
  const pctKes     = slip.pct_kes     ?? 1;
  const potJht      = num(slip.potongan_jht);
  const potPensiun  = num(slip.potongan_pensiun);
  const potKes      = num(slip.potongan_kes);
  const potAlpa     = num(slip.potongan_alpa);
  const potInsentif = num(slip.potongan_insentif);
  const totalPot    = potJht + potPensiun + potKes + potAlpa + potInsentif;
  const gajiBersih  = num(slip.gaji_bersih);

  const today=new Date().toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});

  const ttdList = ttd && ttd.length > 0 ? ttd.map((t,i,arr)=>({
    ...t,
    name: (!t.name && i===arr.length-1)?(slip.nama_lengkap||''):t.name,
    jabatan: (!t.jabatan && i===arr.length-1)?(slip.jabatan||''):t.jabatan,
  })) : [
    {label:'Disetujui Oleh,',name:'H. Syahrul Akmal',jabatan:'Direktur Utama'},
    {label:'Dibayar Oleh,',name:'Yulhamdani',jabatan:'Finance'},
    {label:'Diterima Oleh,',name:slip.nama_lengkap,jabatan:slip.jabatan},
  ];

  const tttRows = Object.entries(TTT_LABELS).filter(([k])=>(slip[k]||0)>0).map(([k,l])=>({label:l,val:slip[k]}));

  const base = {
    fontFamily:'Arial,Helvetica,sans-serif',
    color:'#000',
    fontSize:'8pt',
    lineHeight:1.5,
    width:'132mm',
    margin:'0 auto',
    background:'#fff',
    padding:'6mm 8mm',
    boxSizing:'border-box',
  };

  const Row = ({no,label,val}) => (
    <tr>
      <td style={{width:'6mm',fontSize:'8pt',color:'#444',verticalAlign:'top',paddingBottom:2}}>{no}</td>
      <td style={{fontSize:'8pt',verticalAlign:'top',paddingBottom:2}}>{label}</td>
      <td style={{width:'6mm',textAlign:'center',color:'#666',verticalAlign:'top',paddingBottom:2}}>=</td>
      <td style={{width:'28mm',textAlign:'right',fontWeight:600,verticalAlign:'top',paddingBottom:2,whiteSpace:'nowrap'}}>
        {val!=null?rpC(val):''}
      </td>
    </tr>
  );

  const SecHead = ({letter,title}) => (
    <tr>
      <td colSpan={4} style={{
        fontWeight:'bold',fontSize:'8pt',
        paddingTop:6,paddingBottom:2,
        borderBottom:'1px solid #bbb',
      }}>
        {letter&&<span style={{marginRight:4}}>{letter}.</span>}{title}
      </td>
    </tr>
  );

  const TotRow = ({label,val,bold}) => (
    <tr style={{borderTop:bold?'2px solid #000':'1px solid #aaa'}}>
      <td colSpan={3} style={{fontWeight:bold?'bold':'600',fontSize:'8pt',paddingTop:3,paddingBottom:3}}>{label}</td>
      <td style={{textAlign:'right',fontWeight:bold?'bold':'600',fontSize:'8pt',paddingTop:3,paddingBottom:3,whiteSpace:'nowrap'}}>{rpC(val)}</td>
    </tr>
  );

  return (
    <div style={base}>
      {/* HEADER */}
      <div style={{display:'flex',justifyContent:'space-between',alignItems:'flex-start',borderBottom:'2px solid #000',paddingBottom:5,marginBottom:5}}>
        <div style={{display:'flex',alignItems:'center',gap:8}}>
          <img src="/images/logo-akm.png" alt="AKM" style={{height:32,width:'auto',objectFit:'contain'}} onError={e=>{e.target.style.display='none';}}/>
          <div>
            <div style={{fontWeight:'bold',fontSize:'10pt'}}>PT. ANDALAS KARYA MULIA</div>
            <div style={{fontSize:'6.5pt',color:'#555',marginTop:1}}>Jl. Wonosari, Komplek Wonosari Regency Blok B No.1</div>
            <div style={{fontSize:'6.5pt',color:'#555'}}>Tangkerang Selatan, Pekanbaru - Riau</div>
          </div>
        </div>
        <div style={{textAlign:'right'}}>
          <div style={{border:'2px solid #000',padding:'3px 10px',fontWeight:'bold',fontSize:'9.5pt',letterSpacing:1}}>SLIP GAJI</div>
          <div style={{fontSize:'7pt',color:'#444',marginTop:2}}>{bulan_nama} {tahun}</div>
        </div>
      </div>

      {/* IDENTITAS + NETTO */}
      <div style={{display:'flex',gap:8,marginBottom:5}}>
        <div style={{flex:1}}>
          {[
            ['No. Badge', slip.id_badge||'-'],
            ['Nama Karyawan', slip.nama_lengkap],
            ['Jabatan', slip.jabatan],
            ['No. Rekening', slip.no_rekening||'-'],
            ['PTKP', slip.ptkp||'-'],
          ].map(([k,v])=>(
            <div key={k} style={{display:'flex',fontSize:'7.5pt',marginBottom:2,alignItems:'baseline'}}>
              <div style={{width:'26mm',flexShrink:0,color:'#555'}}>{k}</div>
              <div style={{width:'4mm',flexShrink:0,color:'#555',textAlign:'center'}}>:</div>
              <div style={{fontWeight:'bold',flex:1}}>{v||'-'}</div>
            </div>
          ))}
        </div>
        <div style={{border:'1px solid #888',padding:'5px 8px',textAlign:'center',minWidth:90,display:'flex',flexDirection:'column',justifyContent:'center'}}>
          <div style={{fontSize:'6pt',color:'#555',letterSpacing:0.3}}>NETTO DITERIMA</div>
          <div style={{fontWeight:'bold',fontSize:'12pt',marginTop:2}}>{rpC(gajiBersih)}</div>
        </div>
      </div>

      <div style={{borderTop:'2px solid #000',marginBottom:3}}/>

      {/* TABEL GAJI */}
      <table style={{width:'100%',borderCollapse:'collapse'}}>
        <tbody>
          <SecHead letter="A" title="PEROLEHAN"/>
          <Row no="1." label="Gaji Pokok / Upah" val={gajiPokok}/>
          <Row no="2." label="Tunjangan Tetap (Tunj. Jabatan)" val={tunjTetap}/>
          <Row no="3." label="Kompensasi PWT" val={kompPwt}/>

          {isMd ? (<>
            <SecHead letter="B" title="TUNJANGAN TIDAK TETAP"/>
            <Row no="1." label={`TTT per Hari × ${slip.h_kerja||0} hari`} val={slip.ttt_total||0}/>
            <Row no="2." label={`Uang Makan × ${slip.h_kerja||0} hari`} val={slip.tunj_makan_total||0}/>
            <Row no="3." label={`Com Day × ${slip.h_kerja||0} hari`} val={slip.com_day_total||0}/>
            {(slip.insentif||0)>0&&<Row no="4." label="Insentif" val={slip.insentif}/>}
            {upahLembur > 0 && (<>
              <SecHead letter="C" title="LEMBUR"/>
              {(slip.total_ot_15x||0)>0 && <Row no="1." label={`OT 1,5× — ${slip.total_ot_15x||0} jam`} val={null}/>}
              {(slip.total_ot_2x||0)>0  && <Row no="2." label={`OT 2× — ${slip.total_ot_2x||0} jam`} val={null}/>}
              <Row no="" label="Total Upah Lembur" val={upahLembur}/>
            </>)}
          </>) : (<>
            {tttRows.length>0 && (<>
              <SecHead letter="B" title="TUNJANGAN TIDAK TETAP"/>
              {tttRows.map((t,i)=><Row key={i} no={`${i+1}.`} label={t.label} val={t.val}/>)}
            </>)}
            {slip.kelompok==='flat' ? (
              (slip.total_lembur_flat||0) > 0 && (<>
                <SecHead letter={tttRows.length>0 ? "C" : "B"} title="LEMBUR"/>
                <Row no="1." label={`Lembur Sabtu × ${slip.l_sabtu||0} hari`} val={(slip.l_sabtu||0)*(slip.tarif_sabtu||0)}/>
                <Row no="2." label={`Lembur Libur × ${slip.l_libur||0} hari`} val={(slip.l_libur||0)*(slip.tarif_libur||0)}/>
                {(slip.lembur_biasa||0)>0&&<Row no="3." label={`Lembur Biasa × ${slip.lembur_biasa||0} hari`} val={(slip.lembur_biasa||0)*(slip.tarif_biasa||0)}/>}
                <Row no="" label="Total Lembur Flat" val={slip.total_lembur_flat||0}/>
              </>)
            ) : (
              upahLembur > 0 && (<>
                <SecHead letter={tttRows.length>0 ? "C" : "B"} title="LEMBUR"/>
                <Row no="1." label={`Upah Lembur (${slip.jml_jam_lembur||0} jam)`} val={upahLembur}/>
              </>)
            )}
          </>)}

          <TotRow label="GAJI SEBULAN (KOTOR)" val={gajiKotor} bold/>

          <SecHead letter={(() => {
            const adaTTT = !isMd && tttRows.length > 0;
            const adaLembur = upahLembur > 0 || (slip.total_lembur_flat||0) > 0;
            if (isMd) return adaLembur ? 'D' : 'C';
            if (adaTTT && adaLembur) return 'D';
            if (adaTTT || adaLembur) return 'C';
            return 'B';
          })()} title="POTONGAN WAJIB"/>
          <Row no="1." label={`BPJS TK - JHT (${pctJht}%)`} val={potJht}/>
          <Row no="2." label={`BPJS TK - Pensiun (${pctPensiun}%)`} val={potPensiun}/>
          <Row no="3." label={`BPJS Kesehatan (${pctKes}%)`} val={potKes}/>
          {potAlpa>0 && (() => {
            const projectKode = (slip.project_kode || '').toLowerCase();
            const izinDipotong = !['khawista','purnama'].includes(projectKode);
            const alpaHari = slip.alpa || 0;
            const izinHari = izinDipotong ? (slip.izin || 0) : 0;
            const totalHari = alpaHari + izinHari;
            const labelHari = izinDipotong && izinHari > 0
              ? (alpaHari > 0 ? `${alpaHari} alpa + ${izinHari} izin` : `${izinHari} izin`)
              : `${alpaHari} hari alpa`;
            return <Row no="4." label={`Potongan Alpa (${labelHari})`} val={potAlpa}/>;
          })()}
          {potInsentif>0&&<Row no="5." label={`Pot. Insentif (${slip.sub_group||''})`} val={potInsentif}/>}
          <TotRow label="TOTAL POTONGAN" val={totalPot}/>

          {/* RINCIAN NETTO */}
          <tr><td colSpan={4} style={{paddingTop:4}}></td></tr>
          <tr>
            <td colSpan={4}>
              <div style={{background:'#f5f5f5',border:'1px solid #ddd',borderRadius:3,padding:'4px 8px'}}>
                <div style={{fontSize:'7pt',color:'#555',marginBottom:2}}>Rincian Penghasilan Bersih:</div>
                <div style={{display:'flex',justifyContent:'space-between',fontSize:'7.5pt'}}>
                  <span>Gaji Kotor</span><span>{rpC(gajiKotor)}</span>
                </div>
                <div style={{display:'flex',justifyContent:'space-between',fontSize:'7.5pt'}}>
                  <span>Total Potongan</span><span>- {rpC(totalPot)}</span>
                </div>
                {(slip.kekurangan_bulan_lalu||0)!==0&&(
                  <div style={{display:'flex',justifyContent:'space-between',fontSize:'7.5pt',color:'#555'}}>
                    <span>Kekurangan Bulan Lalu</span>
                    <span>{slip.kekurangan_bulan_lalu>0?'+ ':''}{rpC(slip.kekurangan_bulan_lalu)}</span>
                  </div>
                )}
                <div style={{display:'flex',justifyContent:'space-between',fontSize:'8.5pt',fontWeight:'bold',borderTop:'1px solid #bbb',marginTop:3,paddingTop:3}}>
                  <span>NETTO DITERIMA</span><span>{rpC(gajiBersih)}</span>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      {/* TTD */}
      <div style={{marginTop:8,fontSize:'7.5pt'}}>
        <div style={{textAlign:'right',marginBottom:5}}>Pekanbaru, {today}</div>
        <div style={{display:'flex',justifyContent:'space-between'}}>
          {ttdList.map((t,i)=>(
            <div key={i} style={{
              textAlign:'center',
              width:`${Math.floor(100/ttdList.length)-2}%`,
            }}>
              <div style={{fontWeight:'bold',marginBottom:40}}>{t.label}</div>
              <div style={{
                borderTop:'1px solid #888',
                paddingTop:3,
                fontWeight:'bold',
                marginTop:0,
              }}>{t.name}</div>
              <div style={{color:'#555',fontSize:'6.5pt'}}>{t.jabatan}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ── Modal TTD — hanya tampilkan kolom yang bisa diedit (bukan kolom otomatis karyawan) ──
function TtdSettingsModal({ ttd, onSave, onClose }) {
  const defaultList = [
    {label:'Disetujui Oleh,',name:'H. Syahrul Akmal',jabatan:'Direktur Utama'},
    {label:'Dibayar Oleh,',  name:'Yulhamdani',       jabatan:'Finance'},
  ];
  // Ambil hanya kolom yang bukan "otomatis karyawan" (bukan kolom terakhir default)
  const initList = ttd && ttd.length > 0
    ? ttd.filter((_,i,arr) => i < arr.length - 1) // buang kolom terakhir (otomatis)
    : defaultList;

  const [list, setList] = useState(initList);

  const inp = {
    background:'var(--bg3)',border:'1px solid var(--border)',color:'var(--text)',
    borderRadius:7,padding:'7px 10px',fontSize:12.5,
    fontFamily:"'Outfit',sans-serif",outline:'none',width:'100%',boxSizing:'border-box',
  };

  function update(i,field,val) { setList(prev=>prev.map((item,idx)=>idx===i?{...item,[field]:val}:item)); }
  function addKolom() { if(list.length>=4) return; setList(prev=>[...prev,{label:'',name:'',jabatan:''}]); }
  function hapusKolom(i) { if(list.length<=1) return; setList(prev=>prev.filter((_,idx)=>idx!==i)); }

  function handleSave() {
    // Selalu append kolom karyawan di akhir
    const full = [...list, {label:'Diterima Oleh,',name:'',jabatan:''}];
    onSave(full);
    onClose();
  }

  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
      onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(520px,calc(100vw - 24px))',maxHeight:'90vh',overflow:'auto',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',position:'sticky',top:0,background:'var(--bg2)',zIndex:1}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>Pengaturan Tanda Tangan</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>x</div>
        </div>
        <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
          {list.map((item,i)=>(
            <div key={i} style={{background:'var(--bg3)',borderRadius:9,padding:'12px 14px',border:'1px solid var(--border)'}}>
              <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:10}}>
                <div style={{fontSize:11,fontWeight:700,color:'var(--accent)'}}>Kolom {i+1}</div>
                {list.length > 1 && (
                  <button type="button" onClick={()=>hapusKolom(i)}
                    style={{fontSize:11,padding:'2px 8px',borderRadius:5,border:'1px solid rgba(224,69,69,.3)',background:'rgba(224,69,69,.08)',color:'#E04545',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                    Hapus
                  </button>
                )}
              </div>
              <div style={{display:'flex',flexDirection:'column',gap:8}}>
                <div>
                  <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Label (cth: Disetujui Oleh,)</label>
                  <input style={inp} value={item.label} onChange={e=>update(i,'label',e.target.value)}/>
                </div>
                <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:8}}>
                  <div>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Nama</label>
                    <input style={inp} value={item.name} onChange={e=>update(i,'name',e.target.value)}/>
                  </div>
                  <div>
                    <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:3,display:'block'}}>Jabatan</label>
                    <input style={inp} value={item.jabatan} onChange={e=>update(i,'jabatan',e.target.value)}/>
                  </div>
                </div>
              </div>
            </div>
          ))}

          {/* Kolom karyawan — hanya info, tidak bisa diedit */}
          <div style={{background:'rgba(34,201,122,.06)',borderRadius:9,padding:'12px 14px',border:'1px solid rgba(34,201,122,.2)'}}>
            <div style={{fontSize:11,fontWeight:700,color:'#22C97A',marginBottom:4}}>Kolom {list.length+1} — Diterima Oleh (Otomatis)</div>
            <div style={{fontSize:11,color:'var(--muted2)'}}>Kolom ini otomatis diisi nama dan jabatan karyawan yang bersangkutan</div>
          </div>

          {list.length < 4 && (
            <button type="button" onClick={addKolom}
              style={{padding:'10px',borderRadius:8,border:'1px dashed var(--border)',background:'transparent',color:'var(--muted)',fontSize:12,cursor:'pointer',width:'100%',fontFamily:"'Outfit',sans-serif"}}>
              + Tambah Kolom ({list.length}/4 — maks 4 + 1 otomatis)
            </button>
          )}
        </div>
        <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)',position:'sticky',bottom:0,background:'var(--bg2)'}}>
          <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
          <button type="button" onClick={handleSave} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Simpan</button>
        </div>
      </div>
    </div>
  );
}

// ── Modal BPJS ────────────────────────────────────────────────
function BpjsSettingsModal({ pct, onSave, onClose }) {
  const [form,setForm]=useState({jht:pct?.jht??2,pensiun:pct?.pensiun??1,kes:pct?.kes??1});
  const inp={background:'var(--bg3)',border:'1px solid var(--border)',color:'var(--text)',borderRadius:7,padding:'7px 10px',fontSize:12.5,fontFamily:"'Outfit',sans-serif",outline:'none',width:'100%',boxSizing:'border-box'};
  return (
    <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}} onClick={e=>e.target===e.currentTarget&&onClose()}>
      <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(420px,calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
          <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>Pengaturan Potongan BPJS</div>
          <div onClick={onClose} style={{cursor:'pointer',fontSize:18,color:'var(--muted)'}}>x</div>
        </div>
        <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
          {[{key:'jht',label:'BPJS TK - JHT (%)'},{key:'pensiun',label:'BPJS TK - Pensiun (%)'},{key:'kes',label:'BPJS Kesehatan (%)'}].map(f=>(
            <div key={f.key}>
              <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>{f.label}</label>
              <div style={{display:'flex',alignItems:'center',gap:8}}>
                <input type="number" min="0" max="100" step="0.5" style={{...inp,flex:1}} value={form[f.key]} onChange={e=>setForm(p=>({...p,[f.key]:parseFloat(e.target.value)||0}))}/>
                <span style={{fontSize:13,fontWeight:700,color:'var(--muted2)'}}>%</span>
              </div>
            </div>
          ))}
          <div style={{fontSize:11,color:'var(--muted)',background:'rgba(232,160,32,.08)',borderRadius:8,padding:'8px 12px'}}>
            Dihitung dari Upah Penuh (Gapok + Tunj. Tetap)
          </div>
        </div>
        <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
          <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
          <button type="button" onClick={()=>{onSave(form);onClose();}} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Terapkan</button>
        </div>
      </div>
    </div>
  );
}

// ── MAIN ──────────────────────────────────────────────────────
export default function SlipGaji({
  tahun, bulan, bulan_nama, bulan_list,
  employee_id, employee_list=[], project_id,
  slip, hari_details=[],
}) {
  const { auth } = usePage().props;
  const isViewer = auth?.user?.can?.is_viewer || false;
  const [selTahun,setSelTahun]=useState(tahun);
  const [selBulan,setSelBulan]=useState(bulan);
  const [selEmp,setSelEmp]=useState(employee_id||'');
  const [showRincianJam,setShowRincianJam]=useState(false);
  const [loadingPdf,setLoadingPdf]=useState(false);
  const [showTtdModal,setShowTtdModal]=useState(false);
  const [showBpjsModal,setShowBpjsModal]=useState(false);
  const slipRef=useRef(null);

  // Storage key per project — settings TTD berbeda per project
  const ttdKey  = `slip-ttd-${project_id||'default'}`;
  const bpjsKey = `slip-bpjs-pct-${project_id||'default'}`;

  const [ttd,setTtd]=useState(()=>{ try{const s=localStorage.getItem(ttdKey);return s?JSON.parse(s):null;}catch{return null;} });
  const [bpjsPct,setBpjsPct]=useState(()=>{ try{const s=localStorage.getItem(bpjsKey);return s?JSON.parse(s):{jht:2,pensiun:1,kes:1};}catch{return {jht:2,pensiun:1,kes:1};} });

  const [isDark]=useState(()=>typeof window!=='undefined'&&localStorage.getItem('akm-theme')!=='light');
  const currentYear = new Date().getFullYear();
  const tahunList = Array.from({length: currentYear - 2024 + 6}, (_, i) => 2024 + i);
  const inpStyle={padding:'7px 11px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:12.5,fontFamily:"'Outfit',sans-serif"};

  function navigate(t,b,empId){ router.get('/timesheet/slip-gaji',{tahun:t,bulan:b,employee_id:empId||''},{preserveState:false}); }
  function saveTtd(list){ setTtd(list); localStorage.setItem(ttdKey,JSON.stringify(list)); }
  function saveBpjsPct(pct){ setBpjsPct(pct); localStorage.setItem(bpjsKey,JSON.stringify(pct)); }

  const slipWithPct = slip ? {
    ...slip,
    pct_jht:bpjsPct.jht, pct_pensiun:bpjsPct.pensiun, pct_kes:bpjsPct.kes,
    potongan_jht:     Math.round((slip.gaji_pokok+slip.tunj_tetap)*bpjsPct.jht/100),
    potongan_pensiun: Math.round((slip.gaji_pokok+slip.tunj_tetap)*bpjsPct.pensiun/100),
    potongan_kes:     Math.round((slip.gaji_pokok+slip.tunj_tetap)*bpjsPct.kes/100),
  } : null;

  const slipFinal = slipWithPct ? {
    ...slipWithPct,
    gaji_bersih: slipWithPct.gaji_kotor - slipWithPct.potongan_jht - slipWithPct.potongan_pensiun - slipWithPct.potongan_kes - (slipWithPct.potongan_alpa||0) - (slipWithPct.potongan_insentif||0) + (slipWithPct.kekurangan_bulan_lalu||0),
  } : null;

  // TTD final — kolom terakhir otomatis diisi karyawan
  const ttdFinal = ttd && ttd.length > 0 ? ttd.map((item,i,arr)=>({
    ...item,
    name:    (!item.name    && i===arr.length-1)?(slip?.nama_lengkap||''):item.name,
    jabatan: (!item.jabatan && i===arr.length-1)?(slip?.jabatan||''):item.jabatan,
  })) : null;

  // TTD untuk preview layar
  const ttdDisplay = ttdFinal || [
    {label:'Disetujui Oleh,',name:'H. Syahrul Akmal',jabatan:'Direktur Utama'},
    {label:'Dibayar Oleh,',  name:'Yulhamdani',       jabatan:'Finance'},
    {label:'Diterima Oleh,', name:slipFinal?.nama_lengkap||'',jabatan:slipFinal?.jabatan||''},
  ];

  async function handleDownloadPdf(){
    if(!slipRef.current||!slipFinal) return;
    setLoadingPdf(true);
    try{
      const [{default:html2canvas},{default:jsPDF}]=await Promise.all([import('html2canvas'),import('jspdf')]);
      const canvas=await html2canvas(slipRef.current,{scale:3,useCORS:true,backgroundColor:'#ffffff',logging:false,windowWidth:420,scrollX:0,scrollY:0});
      const pdf=new jsPDF({orientation:'portrait',unit:'mm',format:'a5'});
      const pdfW=148,pdfH=210,margin=8;
      const usableW=pdfW-margin*2;
      const imgRatio=canvas.height/canvas.width;
      const imgH=usableW*imgRatio;
      const finalH=Math.min(imgH,pdfH-margin*2);
      const finalW=finalH===imgH?usableW:usableW*(finalH/imgH);
      pdf.addImage(canvas.toDataURL('image/png'),'PNG',margin,margin,usableW,Math.min(imgH,pdfH-margin*2));
      pdf.save(`SlipGaji_${slip.id_badge}_${bulan_nama}_${tahun}.pdf`);
    }catch(err){console.error('PDF error:',err);alert('Gagal generate PDF.');}
    setLoadingPdf(false);
  }

  function handleExcel(){
    const params=new URLSearchParams({
      tahun,bulan,employee_id,
      pct_jht:bpjsPct.jht,pct_pensiun:bpjsPct.pensiun,pct_kes:bpjsPct.kes,
      ttd:JSON.stringify(ttdFinal||[]),
    });
    window.location.href=`/timesheet/slip-gaji/export-excel?${params}`;
  }

  const RpRow=({no,label,val,color,bold,sub})=>(
    <tr>
      <td style={{padding:'4px 8px',color:'var(--muted)',fontSize:11,width:20,verticalAlign:'top'}}>{no}.</td>
      <td style={{padding:'4px 8px',fontSize:12,verticalAlign:'top'}}>{label}{sub&&<div style={{fontSize:10,color:'var(--muted)',marginTop:1}}>{sub}</div>}</td>
      <td style={{padding:'4px 8px',textAlign:'right',fontWeight:bold?700:400,color:color||'var(--text)',fontSize:12,whiteSpace:'nowrap',verticalAlign:'top'}}>{rp(val)}</td>
    </tr>
  );

  const tttLayar = slipFinal ? Object.entries(TTT_LABELS).filter(([k])=>(slipFinal[k]||0)>0).map(([k,l])=>({key:k,label:l,val:slipFinal[k]})) : [];

  // Preview TTD layar — font size menyesuaikan jumlah kolom
  const ttdCount=ttdDisplay.length;
  const ttdLayarFontSize=ttdCount<=3?12:ttdCount===4?11:10;

  return (
    <AppLayout title="Timesheet" subtitle="Slip Gaji">
      {showTtdModal  && <TtdSettingsModal  ttd={ttdFinal} onSave={saveTtd}     onClose={()=>setShowTtdModal(false)}/>}
      {showBpjsModal && <BpjsSettingsModal pct={bpjsPct}  onSave={saveBpjsPct} onClose={()=>setShowBpjsModal(false)}/>}

      <div style={{display:'flex',gap:10,marginBottom:16,flexWrap:'wrap',alignItems:'center'}}>
        <select value={selBulan} onChange={e=>{setSelBulan(+e.target.value);navigate(selTahun,e.target.value,selEmp);}} style={inpStyle}>
          {Object.entries(bulan_list).map(([k,v])=><option key={k} value={k}>{v}</option>)}
        </select>
        <select value={selTahun} onChange={e=>{setSelTahun(+e.target.value);navigate(e.target.value,selBulan,selEmp);}} style={inpStyle}>
          {tahunList.map(y=><option key={y} value={y}>{y}</option>)}
        </select>
        <select value={selEmp} onChange={e=>{setSelEmp(e.target.value);navigate(selTahun,selBulan,e.target.value);}} style={{...inpStyle,flex:1,minWidth:240,maxWidth:400}}>
          <option value="">-- Pilih Karyawan --</option>
          {employee_list.map(e=>(<option key={e.id} value={e.id}>{e.nama_lengkap} ({e.jabatan})</option>))}
        </select>
      </div>

      {!slipFinal && (
        <div className="panel" style={{padding:48,textAlign:'center',color:'var(--muted)'}}>
          <div style={{fontSize:32,marginBottom:12}}>📋</div>
          <div style={{fontSize:14,fontWeight:600}}>Pilih karyawan untuk melihat slip gaji</div>
          <div style={{fontSize:12,marginTop:6}}>Slip gaji dihitung otomatis dari data timesheet</div>
        </div>
      )}

      {slipFinal && (<>
        <div style={{display:'flex',gap:8,marginBottom:12,alignItems:'center',flexWrap:'wrap'}}>
          <label style={{display:'flex',alignItems:'center',gap:8,cursor:'pointer',fontSize:12.5,color:'var(--muted2)',fontFamily:"'Outfit',sans-serif",padding:'7px 14px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',userSelect:'none'}}>
            <input type="checkbox" checked={showRincianJam} onChange={e=>setShowRincianJam(e.target.checked)} style={{cursor:'pointer',accentColor:'#E8A020',width:14,height:14}}/>
            Rincian Jam
          </label>
          {!isViewer && (
            <button onClick={()=>setShowBpjsModal(true)} style={{padding:'7px 12px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:5}}>
              BPJS <span style={{fontSize:10,color:'var(--accent)'}}>JHT {bpjsPct.jht}% P {bpjsPct.pensiun}% Kes {bpjsPct.kes}%</span>
            </button>
          )}
          {!isViewer && (
            <button onClick={()=>setShowTtdModal(true)} style={{padding:'7px 12px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
              Tanda Tangan <span style={{fontSize:10,color:'var(--muted)'}}>({ttdDisplay.length} kolom)</span>
            </button>
          )}
          <div style={{flex:1}}/>
          <button onClick={handleExcel} style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Excel</button>
          <button onClick={handleDownloadPdf} disabled={loadingPdf} style={{padding:'8px 20px',borderRadius:8,border:'none',background:loadingPdf?'#666':'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loadingPdf?'wait':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loadingPdf?0.7:1}}>
            {loadingPdf?'Generating...':'Download PDF'}
          </button>
        </div>

        <div className="panel" style={{overflow:'hidden',marginBottom:16}}>
          <div style={{background:isDark?'#1A1A2E':'#F4A010',padding:'12px 20px',display:'flex',alignItems:'center',justifyContent:'space-between'}}>
            <div>
              <div style={{fontFamily:'Syne,sans-serif',fontSize:14,fontWeight:700,color:isDark?'#E8A020':'#1A0A00'}}>PT. ANDALAS KARYA MULIA</div>
              <div style={{fontSize:11,color:isDark?'#A06010':'#3D1F00',marginTop:2}}>SLIP GAJI KARYAWAN - Periode: {bulan_nama} {tahun}</div>
            </div>
            <div style={{background:slipFinal.kelompok==='flat'?'rgba(58,143,224,.2)':'rgba(34,201,122,.2)',color:slipFinal.kelompok==='flat'?'var(--blue)':'#22C97A',padding:'4px 14px',borderRadius:99,fontSize:11,fontWeight:700}}>
              {slipFinal.kelompok==='flat'?'Sistem Flat':'Sistem Per Jam'}
            </div>
          </div>

          <div style={{padding:'20px'}}>
            <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'8px 32px',marginBottom:20}}>
            <div>
              {[['Nama',slipFinal.nama_lengkap],['Bagian',slipFinal.jabatan],['NIK',slipFinal.no_ktp||'-'],['No. Rek.',slipFinal.no_rekening||'-'],['PTKP',slipFinal.ptkp||'-']].map(([k,v],i)=>(
                  <div key={i} style={{display:'flex',gap:12,marginBottom:6,fontSize:12.5}}>
                    <span style={{width:70,color:'var(--muted)',flexShrink:0}}>{k}</span>
                    <span>: {v}</span>
                  </div>
                ))}
              </div>
              <div>
                {[['Gaji Pokok',rp(slipFinal.gaji_pokok)],['Tunj. Tetap',rp(slipFinal.tunj_tetap)],['Upah Lembur',rp(slipFinal.upah_lembur||slipFinal.total_lembur_flat)],['Nilai OT/jam','Rp '+Number(slipFinal.nilai_lembur_per_jam||0).toFixed(3)]].map(([k,v],i)=>(
                  <div key={i} style={{display:'flex',justifyContent:'space-between',marginBottom:6,fontSize:12}}>
                    <span style={{color:'var(--muted)'}}>{k}</span>
                    <span style={{fontWeight:600,color:i===2?'#22C97A':'var(--text)'}}>{v}</span>
                  </div>
                ))}
              </div>
            </div>

            {slipFinal.kelompok==='per_jam'&&hari_details.length>0&&showRincianJam&&(
              <div style={{marginBottom:20}}>
                <div style={{fontSize:11,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.07em',marginBottom:10}}>Rincian Jam Kerja & Lembur</div>
                <TabelHariPerJam details={hari_details} isDark={isDark}/>
              </div>
            )}
            {slipFinal.kelompok==='flat'&&showRincianJam&&(
              <div style={{marginBottom:20}}>
                <div style={{fontSize:11,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.07em',marginBottom:10}}>Rincian Lembur (Sistem Flat)</div>
                <TabelFlat slip={slipFinal}/>
              </div>
            )}

            <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:20}}>
            <div>
              <div style={{fontSize:11,fontWeight:700,color:'var(--accent)',textTransform:'uppercase',letterSpacing:'.07em',marginBottom:10}}>Pendapatan</div>
                <table style={{width:'100%',borderCollapse:'collapse'}}>
                  <tbody>
                    <RpRow no={1} label="Upah pokok" val={slipFinal.gaji_pokok}/>
                    <RpRow no={2} label="Tunjangan tetap" val={slipFinal.tunj_tetap}/>
                    <RpRow no={3} label="Kompensasi PWT" sub={`(${rp(slipFinal.gaji_pokok+slipFinal.tunj_tetap)}) / 12`} val={slipFinal.kompensasi_pwt}/>
                    {tttLayar.map((t,i)=>(<RpRow key={t.key} no={4+i} label={t.label} val={t.val} color="#22C97A"/>))}
                    <RpRow no={4+tttLayar.length}
                      label={slipFinal.kelompok==='flat'?'Total Lembur (Flat)':'Upah lembur'}
                      sub={slipFinal.kelompok==='flat'?`${slipFinal.l_sabtu||0} Sabtu + ${slipFinal.l_libur||0} Libur`:`${slipFinal.jml_jam_lembur||0} jam x Rp ${Math.round(slipFinal.nilai_lembur_per_jam||0).toLocaleString('id-ID')}/jam`}
                      val={slipFinal.kelompok==='flat'?slipFinal.total_lembur_flat:slipFinal.upah_lembur} color="#22C97A"/>
                  </tbody>
                  <tfoot>
                    <tr style={{borderTop:'2px solid var(--accent)'}}>
                      <td/><td style={{padding:'8px',fontSize:13,fontWeight:700}}>Total Kotor</td>
                      <td style={{padding:'8px',textAlign:'right',fontSize:13,fontWeight:700,color:'var(--accent)',whiteSpace:'nowrap'}}>{rp(slipFinal.gaji_kotor)}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div>
                <div style={{fontSize:11,fontWeight:700,color:'#E04545',textTransform:'uppercase',letterSpacing:'.07em',marginBottom:10}}>Potongan</div>
                <table style={{width:'100%',borderCollapse:'collapse'}}>
                  <tbody>
                    <RpRow no={1} label={`BPJS TK - JHT (${bpjsPct.jht}%)`} sub={`${bpjsPct.jht}% x ${rp(slipFinal.gaji_pokok+slipFinal.tunj_tetap)}`} val={slipFinal.potongan_jht} color="#E04545"/>
                    <RpRow no={2} label={`BPJS Pensiun (${bpjsPct.pensiun}%)`} sub={`${bpjsPct.pensiun}% x ${rp(slipFinal.gaji_pokok+slipFinal.tunj_tetap)}`} val={slipFinal.potongan_pensiun} color="#E04545"/>
                    <RpRow no={3} label={`BPJS Kesehatan (${bpjsPct.kes}%)`} sub={`${bpjsPct.kes}% x ${rp(slipFinal.gaji_pokok+slipFinal.tunj_tetap)}`} val={slipFinal.potongan_kes} color="#E04545"/>
                    {(slipFinal.potongan_alpa||0)>0&&<RpRow no={4} label={`Alpa (${slipFinal.alpa} hari)`} sub={`/ 25 x ${slipFinal.alpa} hari`} val={slipFinal.potongan_alpa} color="#E04545"/>}
                    {(slipFinal.potongan_insentif||0)>0&&<RpRow no={5} label={`Pot. Insentif (${slipFinal.sub_group||''})`} sub={slipFinal.sub_group==='construction'?`Insentif / 25 × (Izin+Sakit+Cuti)`:`Tunj. Lapangan / 25 × Izin`} val={slipFinal.potongan_insentif} color="#E04545"/>}
                  </tbody>
                </table>
                <div style={{marginTop:16,padding:'14px 16px',borderRadius:10,background:'rgba(34,201,122,.08)',border:'1px solid rgba(34,201,122,.2)'}}>
                  <div style={{display:'flex',justifyContent:'space-between',alignItems:'center'}}>
                    <div style={{fontSize:13,fontWeight:700}}>Gaji Bersih (Netto)</div>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:18,fontWeight:700,color:'#22C97A'}}>{rp(slipFinal.gaji_bersih)}</div>
                  </div>
                </div>
                <div className="stat-grid-5" style={{marginTop:12,display:'grid',gridTemplateColumns:'repeat(5,1fr)',gap:5}}>
                  {[{l:'Hadir',v:slipFinal.h_kerja,bg:'rgba(34,201,122,.1)',c:'#22C97A'},{l:'Izin',v:slipFinal.izin,bg:'rgba(58,143,224,.1)',c:'var(--blue)'},{l:'Sakit',v:slipFinal.sakit,bg:'rgba(232,160,32,.1)',c:'var(--accent)'},{l:'Alpa',v:slipFinal.alpa,bg:'rgba(224,69,69,.1)',c:'#E04545'},{l:'Cuti',v:slipFinal.cuti,bg:'rgba(34,201,122,.08)',c:'#22C97A'}].map((s,i)=>(
                    <div key={i} style={{textAlign:'center',padding:'6px 4px',borderRadius:8,background:s.bg,border:`1px solid ${s.c}30`}}>
                      <div style={{fontFamily:'Syne,sans-serif',fontSize:16,fontWeight:700,color:s.c}}>{s.v}</div>
                      <div style={{fontSize:9,color:'var(--muted)',marginTop:1}}>{s.l}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* TTD preview — rapi, font menyesuaikan jumlah kolom */}
            <div style={{marginTop:24,paddingTop:16,borderTop:'1px solid var(--border)'}}>
              <div className="ttd-preview-grid" style={{display:'grid',gridTemplateColumns:`repeat(${ttdCount},1fr)`,gap:8,textAlign:'center'}}>
                {ttdDisplay.map((t,i)=>(
                  <div key={i} style={{padding:'0 4px'}}>
                    <div style={{fontSize:ttdLayarFontSize,color:'var(--muted)',marginBottom:28,whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{t.label}</div>
                    <div style={{borderTop:'1px solid var(--border)',paddingTop:6,fontSize:ttdLayarFontSize,fontWeight:600,whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{t.name}</div>
                    <div style={{fontSize:Math.max(9,ttdLayarFontSize-2),color:'var(--muted)',marginTop:2,whiteSpace:'nowrap',overflow:'hidden',textOverflow:'ellipsis'}}>{t.jabatan}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div style={{display:'flex',gap:10,justifyContent:'flex-end'}}>
          <a href={`/timesheet/data-gaji?tahun=${tahun}&bulan=${bulan}`}
            style={{padding:'9px 20px',borderRadius:8,background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',color:'#fff',fontSize:12.5,fontWeight:700,textDecoration:'none'}}>
            Data Gaji Semua Karyawan
          </a>
        </div>

        {/* Area PDF */}
        <div style={{position:'fixed',top:0,left:'-9999px',width:510,overflow:'visible',zIndex:-1,background:'#fff'}}>
          <div ref={slipRef}>
            <SlipCetak slip={slipFinal} bulan_nama={bulan_nama} tahun={tahun} ttd={ttdFinal}/>
          </div>
        </div>
      </>)}
    </AppLayout>
  );
}