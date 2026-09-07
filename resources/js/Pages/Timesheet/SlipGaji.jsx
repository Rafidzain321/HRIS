// resources/js/Pages/Timesheet/SlipGaji.jsx
import React, { useState, useRef, useEffect, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';

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
  const potOksigen  = num(slip.pot_tabung_oksigen);
  const pph21       = num(slip.pph21); // info saja (ditanggung perusahaan) — sengaja TIDAK ikut totalPot
  const totalPot    = potJht + potPensiun + potKes + potAlpa + potInsentif + potOksigen;
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

  // Rincian formula gaji kotor — subtotal tiap section, dipakai untuk catatan "= A + B + C" di bawah total.
  const perolehanSubtotal = gajiPokok + tunjTetap + kompPwt;
  const tttSubtotal = isMd
    ? num(slip.ttt_total) + num(slip.tunj_makan_total) + num(slip.com_day_total) + num(slip.insentif)
    : tttRows.reduce((s,t)=>s+num(t.val),0);
  const lemburSubtotal = isMd ? upahLembur : (slip.kelompok==='flat' ? num(slip.total_lembur_flat) : upahLembur);

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
          <tr>
            <td colSpan={4} style={{fontSize:'6.5pt',color:'#888',fontStyle:'italic',paddingBottom:2}}>
              {'= ' + [
                `${rpC(perolehanSubtotal)} (Perolehan)`,
                tttSubtotal > 0 && `${rpC(tttSubtotal)} (TTT)`,
                lemburSubtotal > 0 && `${rpC(lemburSubtotal)} (Lembur)`,
              ].filter(Boolean).join(' + ')}
            </td>
          </tr>

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
          {potOksigen>0&&<Row no="6." label="Potongan Tabung Oksigen" val={potOksigen}/>}
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
                {pph21>0 && (
                  <div style={{display:'flex',justifyContent:'space-between',fontSize:'6.5pt',color:'#888',fontStyle:'italic',marginTop:2}}>
                    <span>Estimasi PPh 21 bulan ini (ditanggung perusahaan, TER {slip.ptkp||'—'})</span><span>{rpC(pph21)}</span>
                  </div>
                )}
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

function EmployeeSearchSelect({ employeeList, value, onChange, style }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const boxRef = useRef(null);

  const selected = employeeList.find(e => String(e.id) === String(value));

  useEffect(() => {
    function onClickOutside(e) {
      if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return employeeList;
    return employeeList.filter(e => (e.nama_lengkap||'').toLowerCase().includes(q) || (e.jabatan||'').toLowerCase().includes(q));
  }, [query, employeeList]);

  return (
    <div ref={boxRef} style={{position:'relative',...style}}>
      <input
        type="text"
        value={open ? query : (selected ? `${selected.nama_lengkap} (${selected.jabatan})` : '')}
        placeholder="-- Cari Karyawan --"
        onFocus={() => { setQuery(''); setOpen(true); }}
        onChange={e => setQuery(e.target.value)}
        style={{padding:'7px 11px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:12.5,fontFamily:"'Outfit',sans-serif",width:'100%',boxSizing:'border-box'}}
      />
      {open && (
        <div style={{position:'absolute',top:'calc(100% + 4px)',left:0,right:0,maxHeight:260,overflowY:'auto',background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:8,boxShadow:'0 12px 32px rgba(0,0,0,.35)',zIndex:50}}>
          {value && (
            <div onClick={() => { onChange(''); setOpen(false); setQuery(''); }}
              style={{padding:'8px 12px',fontSize:12,color:'var(--muted)',cursor:'pointer',borderBottom:'1px solid var(--border)'}}>
              -- Pilih Karyawan --
            </div>
          )}
          {filtered.length === 0 && (
            <div style={{padding:'10px 12px',fontSize:12,color:'var(--muted)'}}>Tidak ditemukan</div>
          )}
          {filtered.map(e => (
            <div key={e.id} onClick={() => { onChange(e.id); setOpen(false); setQuery(''); }}
              style={{padding:'8px 12px',fontSize:12.5,cursor:'pointer',background: String(e.id)===String(value) ? 'rgba(232,160,32,.12)' : 'transparent'}}
              onMouseEnter={ev=>ev.currentTarget.style.background='rgba(232,160,32,.08)'}
              onMouseLeave={ev=>ev.currentTarget.style.background= String(e.id)===String(value) ? 'rgba(232,160,32,.12)' : 'transparent'}>
              {e.nama_lengkap} ({e.jabatan})
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ── MAIN ──────────────────────────────────────────────────────
export default function SlipGaji({
  tahun, bulan, bulan_nama, bulan_list,
  employee_id, employee_list=[], project_id,
  slip, hari_details=[],
}) {
  const [selTahun,setSelTahun]=useState(tahun);
  const [selBulan,setSelBulan]=useState(bulan);
  const [selEmp,setSelEmp]=useState(employee_id||'');
  const [showRincianJam,setShowRincianJam]=useState(false);
  const [loadingPdf,setLoadingPdf]=useState(false);
  const slipRef=useRef(null);

  const isDark=false;
  const currentYear = new Date().getFullYear();
  const tahunList = Array.from({length: currentYear - 2024 + 6}, (_, i) => 2024 + i);
  const inpStyle={padding:'7px 11px',borderRadius:8,border:'1px solid var(--border)',background:'var(--card)',color:'var(--text)',fontSize:12.5,fontFamily:"'Outfit',sans-serif"};

  function navigate(t,b,empId){ router.get('/timesheet/slip-gaji',{tahun:t,bulan:b,employee_id:empId||''},{preserveState:false}); }

  // Persentase BPJS & TTD sudah dihitung backend sesuai konfigurasi project (lihat PayrollController::getSlipData) —
  // tidak perlu dihitung ulang di sini, supaya layar/PDF/Excel selalu sama dengan yang tersimpan.
  const slipFinal = slip;
  const bpjsPct = { jht: slip?.pct_jht ?? 2, pensiun: slip?.pct_pensiun ?? 1, kes: slip?.pct_kes ?? 1 };

  // TTD — 2 slot dari konfigurasi project + kolom "Diterima Oleh" otomatis untuk karyawan yang dilihat.
  const ttdDisplay = [
    ...(slip?.ttd_list?.length ? slip.ttd_list : [
      {label:'Disetujui Oleh,',name:'H. Syahrul Akmal',jabatan:'Direktur Utama'},
      {label:'Dibayar Oleh,',  name:'Yulhamdani',       jabatan:'Finance'},
    ]),
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
    // BPJS % & TTD sudah diresolve backend dari konfigurasi project — tidak perlu dikirim dari sini lagi.
    const params=new URLSearchParams({ tahun,bulan,employee_id });
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
      <div style={{display:'flex',gap:10,marginBottom:16,flexWrap:'wrap',alignItems:'center'}}>
        <select value={selBulan} onChange={e=>{setSelBulan(+e.target.value);navigate(selTahun,e.target.value,selEmp);}} style={inpStyle}>
          {Object.entries(bulan_list).map(([k,v])=><option key={k} value={k}>{v}</option>)}
        </select>
        <select value={selTahun} onChange={e=>{setSelTahun(+e.target.value);navigate(e.target.value,selBulan,selEmp);}} style={inpStyle}>
          {tahunList.map(y=><option key={y} value={y}>{y}</option>)}
        </select>
        <EmployeeSearchSelect
          employeeList={employee_list}
          value={selEmp}
          onChange={val=>{setSelEmp(val);navigate(selTahun,selBulan,val);}}
          style={{flex:1,minWidth:240,maxWidth:400}}
        />
      </div>

      {!slipFinal && (
        <div className="panel" style={{padding:48,textAlign:'center',color:'var(--muted)'}}>
          <div style={{marginBottom:12,display:'flex',justifyContent:'center'}}><ClipboardList size={32}/></div>
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
          <div style={{flex:1}}/>
          <button onClick={handleExcel} style={{padding:'8px 20px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#22C97A,#148050)',color:'#fff',fontSize:12.5,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Export Excel</button>
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
            <div style={{background:slipFinal.tipe_project==='ho'?'rgba(107,114,128,.2)':slipFinal.kelompok==='flat'?'rgba(58,143,224,.2)':'rgba(34,201,122,.2)',color:slipFinal.tipe_project==='ho'?'var(--muted2)':slipFinal.kelompok==='flat'?'var(--blue)':'#22C97A',padding:'4px 14px',borderRadius:99,fontSize:11,fontWeight:700}}>
              {slipFinal.tipe_project==='ho'?'Kantor Pusat (HO)':slipFinal.kelompok==='flat'?'Sistem Flat':'Sistem Per Jam'}
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
                {(slipFinal.tipe_project==='ho'
                  ? [['Gaji Pokok',rp(slipFinal.gaji_pokok)],['Tunj. Tetap',rp(slipFinal.tunj_tetap)],['Kompensasi PWT',rp(slipFinal.kompensasi_pwt)]]
                  : [['Gaji Pokok',rp(slipFinal.gaji_pokok)],['Tunj. Tetap',rp(slipFinal.tunj_tetap)],['Upah Lembur',rp(slipFinal.upah_lembur||slipFinal.total_lembur_flat)],
                     ...(slipFinal.kelompok==='flat' ? [] : [['Nilai OT/jam','Rp '+Number(slipFinal.nilai_lembur_per_jam||0).toFixed(3)]])]
                ).map(([k,v],i)=>(
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
                    {(slipFinal.pot_tabung_oksigen||0)>0&&<RpRow no={6} label="Potongan Tabung Oksigen" val={slipFinal.pot_tabung_oksigen} color="#E04545"/>}
                  </tbody>
                </table>
                {slipFinal.tipe_project==='ho' && ((slipFinal.potongan_simulasi||0)+(slipFinal.potongan_custom_sum||0))>0 && (
                  <div style={{marginTop:8,padding:'8px 12px',borderRadius:8,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)',fontSize:11,color:'var(--muted2)'}}>
                    ℹ️ Info: potensi potongan cuti/alpa & lainnya (belum dipotong) = {rp((slipFinal.potongan_simulasi||0)+(slipFinal.potongan_custom_sum||0))}
                  </div>
                )}
                <div style={{marginTop:16,padding:'14px 16px',borderRadius:10,background:'rgba(34,201,122,.08)',border:'1px solid rgba(34,201,122,.2)'}}>
                  <div style={{display:'flex',justifyContent:'space-between',alignItems:'center'}}>
                    <div style={{fontSize:13,fontWeight:700}}>Gaji Bersih (Netto)</div>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:18,fontWeight:700,color:'#22C97A'}}>{rp(slipFinal.gaji_bersih)}</div>
                  </div>
                </div>
                {(slipFinal.pph21||0)>0 && (
                  <div style={{marginTop:8,padding:'8px 12px',borderRadius:8,background:'var(--bg3)',border:'1px solid var(--border)',fontSize:10.5,color:'var(--muted)',fontStyle:'italic'}}>
                    Estimasi PPh 21 bulan ini (TER, PTKP {slipFinal.ptkp||'—'}): <b style={{color:'var(--muted2)'}}>{rp(slipFinal.pph21)}</b> — ditanggung perusahaan, tidak mengurangi gaji bersih di atas.
                  </div>
                )}
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
            <SlipCetak slip={slipFinal} bulan_nama={bulan_nama} tahun={tahun} ttd={ttdDisplay}/>
          </div>
        </div>
      </>)}
    </AppLayout>
  );
}