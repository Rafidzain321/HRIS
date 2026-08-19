// resources/js/Pages/Timesheet/Index.jsx
import AppLayout from '@/Layouts/AppLayout';
import { router, usePage } from '@inertiajs/react';
import React, { useState, useRef, useEffect } from 'react';
import axios from 'axios';
import { SUB_GROUP_OPTIONS, subGroupMeta } from './subGroupOptions';
import {
    Calendar, X, Loader2, Save, Plus, Search, Check, TriangleAlert, Pencil, Trash2,
    GripVertical, Circle, Users, User, ClipboardList, Upload, Download, Clock, Lightbulb,
    Timer, CalendarX2,
} from 'lucide-react';

const BULAN_NAMA = ['','Januari','Februari','Maret','April','Mei','Juni',
                    'Juli','Agustus','September','Oktober','November','Desember'];

const STATUS = {
    'I':   { dark: { bg:'#1A3A6A', color:'#BDD7EE' }, light: { bg:'#BDD7EE', color:'#1F497D' }, label:'Izin' },
    'S':   { dark: { bg:'#4A3A00', color:'#FFD966' }, light: { bg:'#FFD966', color:'#7F6000' }, label:'Sakit' },
    'A':   { dark: { bg:'#5A1A1A', color:'#FF7C80' }, light: { bg:'#FF7C80', color:'#9C0006' }, label:'Alpha' },
    'C':   { dark: { bg:'#1A4A2A', color:'#C6EFCE' }, light: { bg:'#C6EFCE', color:'#276221' }, label:'Cuti' },
    'STB': { dark: { bg:'#2A2A4A', color:'#A0A0FF' }, light: { bg:'#E8E8FF', color:'#3A3A9C' }, label:'Standby' },
};

const HOLIDAY_COLORS = {
    libur_nasional: { dark: { bg:'#3A1A4A', color:'#C070F0' }, light: { bg:'#F3E8FF', color:'#7E22CE' } },
    cuti_bersama:   { dark: { bg:'#1A3A4A', color:'#50C0D0' }, light: { bg:'#E0F2FE', color:'#0369A1' } },
    libur_khusus:   { dark: { bg:'#1A3A4A', color:'#50C0D0' }, light: { bg:'#E0F2FE', color:'#0369A1' } },
};

const TIPE_CONFIG = {
    libur_nasional: { label:'Libur Nasional', bg:'rgba(192,112,240,.12)', color:'#C070F0' },
    cuti_bersama:   { label:'Cuti Bersama',   bg:'rgba(80,192,208,.12)',  color:'#50C0D0' },
    libur_khusus:   { label:'Libur Khusus',   bg:'rgba(58,143,224,.12)', color:'var(--blue)' },
};


// ── TAB HARI LIBUR ────────────────────────────────────────────
function TambahLiburModal({ onClose }) {
    const [form, setForm] = useState({ tanggal:'', keterangan:'', tipe:'libur_nasional' });
    const [loading, setLoading] = useState(false);
    const inp = {
        background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
        borderRadius:8, padding:'8px 11px', fontSize:12.5,
        fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
    };
    function submit(e) {
        e.preventDefault();
        if (!form.tanggal || !form.keterangan) { alert('Tanggal dan keterangan wajib diisi!'); return; }
        setLoading(true);
        router.post('/pengaturan/holidays', form, {
            onSuccess: () => { setLoading(false); onClose(); },
            onError:   () => setLoading(false),
        });
    }
    return (
        <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}>
            <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(440px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
                    <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><CalendarX2 size={15}/> Tambah Hari Libur</div>
                    <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
                </div>
                <form onSubmit={submit}>
                    <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tanggal *</label>
                            <input type="date" style={inp} value={form.tanggal} onChange={e=>setForm(f=>({...f,tanggal:e.target.value}))} />
                        </div>
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Keterangan *</label>
                            <input type="text" style={inp} value={form.keterangan} placeholder="cth: Hari Raya Idul Fitri" onChange={e=>setForm(f=>({...f,keterangan:e.target.value}))} />
                        </div>
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tipe</label>
                            <select style={inp} value={form.tipe} onChange={e=>setForm(f=>({...f,tipe:e.target.value}))}>
                                <option value="libur_nasional">Libur Nasional</option>
                                <option value="cuti_bersama">Cuti Bersama</option>
                                <option value="libur_khusus">Libur Khusus</option>
                            </select>
                        </div>
                    </div>
                    <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
                        <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
                        <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
                            {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Save size={14}/>Simpan</span>}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function TabHariLibur({ all_holidays, isDark, isViewer }) {
    const [showAdd,     setShowAdd]     = useState(false);
    const [filterTahun, setFilterTahun] = useState(new Date().getFullYear());

    function hapus(id, keterangan) {
        if (!window.confirm(`Hapus hari libur "${keterangan}"?`)) return;
        router.delete(`/pengaturan/holidays/${id}`, { preserveScroll: true });
    }

    const tahunList = [];
    const currentYear = new Date().getFullYear();
    for (let y = 2024; y <= currentYear + 5; y++) tahunList.push(y);

    const filtered = (all_holidays||[]).filter(h => h.tanggal.startsWith(String(filterTahun)));
    const grouped  = {};
    filtered.forEach(h => {
        const bln = parseInt(h.tanggal.split('-')[1]);
        if (!grouped[bln]) grouped[bln] = [];
        grouped[bln].push(h);
    });

    const inp = {
        background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
        borderRadius:7, padding:'6px 10px', fontSize:12,
        fontFamily:"'Outfit',sans-serif", outline:'none',
    };

    return (
        <>
            {showAdd && <TambahLiburModal onClose={()=>setShowAdd(false)} isDark={isDark} />}
            <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:16,flexWrap:'wrap',gap:10}}>
                <div style={{fontSize:12,color:'var(--muted)'}}>
                    Kelola hari libur nasional, cuti bersama, dan libur khusus — otomatis ditandai merah di Timesheet
                </div>
                <div style={{display:'flex',gap:8,alignItems:'center'}}>
                    <select style={inp} value={filterTahun} onChange={e=>setFilterTahun(+e.target.value)}>
                        {tahunList.map(y=><option key={y} value={y}>{y}</option>)}
                    </select>
                    {!isViewer && (
                        <button onClick={()=>setShowAdd(true)} style={{padding:'7px 14px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            <Plus size={13} style={{verticalAlign:-2}}/> Tambah Hari Libur
                        </button>
                    )}
                </div>
            </div>
            <div className="stat-grid-4" style={{display:'grid',gridTemplateColumns:'repeat(4,1fr)',gap:12,marginBottom:16}}>
                {[
                    { label:'Total',          val: filtered.length,                                    color:'var(--text)' },
                    { label:'Libur Nasional', val: filtered.filter(h=>h.tipe==='libur_nasional').length, color:'#C070F0' },
                    { label:'Cuti Bersama',   val: filtered.filter(h=>h.tipe==='cuti_bersama').length,   color:'#50C0D0' },
                    { label:'Libur Khusus',   val: filtered.filter(h=>h.tipe==='libur_khusus').length,   color:'var(--blue)' },
                ].map((s,i)=>(
                    <div key={i} className="panel" style={{padding:'12px 16px',textAlign:'center'}}>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:24,fontWeight:700,color:s.color}}>{s.val}</div>
                        <div style={{fontSize:10.5,color:'var(--muted)',marginTop:3}}>{s.label}</div>
                    </div>
                ))}
            </div>
            {Object.keys(grouped).length === 0 ? (
                <div className="panel" style={{padding:40,textAlign:'center',color:'var(--muted)'}}>
                    Belum ada hari libur untuk tahun {filterTahun}
                </div>
            ) : (
                <div className="panel" style={{overflow:'hidden'}}>
                    <table style={{width:'100%',borderCollapse:'collapse',fontSize:12.5}}>
                        <thead>
                            <tr>
                                <th style={{...thHoliday(isDark),width:140,textAlign:'left',paddingLeft:14}}>Tanggal</th>
                                <th style={{...thHoliday(isDark),textAlign:'left',paddingLeft:10}}>Keterangan</th>
                                <th style={{...thHoliday(isDark),width:160,textAlign:'left',paddingLeft:10}}>Tipe</th>
                                <th style={{...thHoliday(isDark),width:90,textAlign:'center'}}>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Object.keys(grouped).sort((a,b)=>+a-+b).map(bulan=>(
                                <React.Fragment key={bulan}>
                                    <tr>
                                        <td colSpan={4} style={{
                                            padding:'9px 14px',
                                            background: isDark ? 'rgba(232,160,32,.08)' : 'rgba(232,160,32,.06)',
                                            borderTop: `2px solid ${isDark?'rgba(232,160,32,.25)':'rgba(232,160,32,.3)'}`,
                                            borderBottom: `1px solid ${isDark?'rgba(255,255,255,.06)':'rgba(0,0,0,.07)'}`,
                                        }}>
                                            <span style={{fontFamily:'Syne,sans-serif',fontSize:12.5,fontWeight:700,color:'var(--accent)'}}>
                                                <Calendar size={13} style={{verticalAlign:-2}}/> {BULAN_NAMA[+bulan]} {filterTahun}
                                            </span>
                                            <span style={{marginLeft:10,fontSize:11,color:'var(--muted)',fontWeight:400}}>
                                                — {grouped[bulan].length} hari libur
                                            </span>
                                        </td>
                                    </tr>
                                    {grouped[bulan].map((h,i)=>{
                                        const tc = TIPE_CONFIG[h.tipe] || TIPE_CONFIG.libur_khusus;
                                        return (
                                            <tr key={i}
                                                onMouseEnter={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='rgba(232,160,32,.04)')}
                                                onMouseLeave={ev=>Array.from(ev.currentTarget.cells).forEach(c=>c.style.background='')}>
                                                <td style={{padding:'10px 14px',fontWeight:600,whiteSpace:'nowrap',borderBottom:`1px solid ${isDark?'rgba(255,255,255,.05)':'rgba(0,0,0,.06)'}`}}>{h.tanggal_fmt}</td>
                                                <td style={{padding:'10px 10px',borderBottom:`1px solid ${isDark?'rgba(255,255,255,.05)':'rgba(0,0,0,.06)'}`}}>{h.keterangan}</td>
                                                <td style={{padding:'10px 10px',borderBottom:`1px solid ${isDark?'rgba(255,255,255,.05)':'rgba(0,0,0,.06)'}`}}>
                                                    <span style={{background:tc.bg,color:tc.color,padding:'3px 10px',borderRadius:99,fontSize:11,fontWeight:600}}>{tc.label}</span>
                                                </td>
                                                <td style={{padding:'8px 10px',textAlign:'center',borderBottom:`1px solid ${isDark?'rgba(255,255,255,.05)':'rgba(0,0,0,.06)'}`}}>
                                                    {!isViewer && (
                                                        <button onClick={()=>hapus(h.id,h.keterangan)} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center'}}><Trash2 size={12}/></button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}

// ── HELPER FUNCTIONS ──────────────────────────────────────────
function getCellStyle(val, isSunday, isHoliday, holidayTipe, isDark) {
    if (isSunday) return isDark
        ? { background:'#2A1A1A', color:'#8A4040' }
        : { background:'#FEE2E2', color:'#B91C1C' };
    if (isHoliday) {
        const tipe = holidayTipe || 'libur_nasional';
        const hc = HOLIDAY_COLORS[tipe] || HOLIDAY_COLORS.libur_nasional;
        const t = isDark ? hc.dark : hc.light;
        return { background: t.bg, color: t.color };
    }
    if (!val || val === '') return {};
    const up = String(val).toUpperCase();
    if (STATUS[up]) {
        const t = isDark ? STATUS[up].dark : STATUS[up].light;
        return { background: t.bg, color: t.color, fontWeight: 700 };
    }
    if (!isNaN(val) && val !== '') {
        const n = parseFloat(val);
        if (isDark) {
            if (n >= 10) return { background:'#0D2E1A', color:'#30D080', fontWeight:500 };
            if (n >= 8)  return { background:'#3A1A1A', color:'#FF7C80', fontWeight:500 };
            return { background:'#5A0A0A', color:'#FF6666', fontWeight:500 };
        } else {
            if (n >= 10) return { background:'#CCFFCC', color:'#276221', fontWeight:500 };
            if (n >= 8)  return { background:'#FFCCCC', color:'#9C0006', fontWeight:500 };
            return { background:'#FF6666', color:'#FFFFFF', fontWeight:500 };
        }
    }
    return {};
}

function calcSummary(days) {
    let hadir=0, izin=0, sakit=0, alpa=0, cuti=0, stb=0;
    Object.values(days).forEach(v => {
        if (!v) return;
        const up = String(v).toUpperCase();
        if      (up==='I')   izin++;
        else if (up==='S')   sakit++;
        else if (up==='A')   alpa++;
        else if (up==='C')   cuti++;
        else if (up==='STB') stb++;
        else if (!isNaN(v) && v!=='') hadir++;
    });
    return { hadir, izin, sakit, alpa, cuti, stb };
}

function EditableCell({ employeeId, tahun, bulan, hari, value, isSunday, isHoliday, holidayTipe, onSaved, isDark, isViewer }) {
    const [editing, setEditing] = useState(false);
    const [val,     setVal]     = useState(value ?? '');
    const [saving,  setSaving]  = useState(false);
    const inputRef = useRef(null);

    useEffect(() => { setVal(value ?? ''); }, [value]);

    function startEdit() {
        if (isViewer) return;
        setEditing(true);
        setTimeout(() => inputRef.current?.select(), 30);
    }

    function save(rawVal) {
        let v = String(rawVal ?? '').trim();
        setEditing(false);
        const up = v.toUpperCase();
        if (['I','S','A','C','STB'].includes(up)) v = up;
        else if (v !== '' && isNaN(v)) v = '';
        setVal(v);
        setSaving(true);
        onSaved(hari, v === '' ? null : v);
        axios.post('/timesheet/update', {
            employee_id: employeeId, tahun, bulan, hari,
            nilai: v === '' ? null : v,
        }, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }
        }).finally(() => setSaving(false));
    }

    function onKeyDown(e) {
        if (e.key==='Enter'||e.key==='Tab') { e.preventDefault(); save(e.target.value); }
        if (e.key==='Escape') { setVal(value??''); setEditing(false); }
        if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)) {
            e.preventDefault();
            save(e.target.value);
            const allCells = Array.from(document.querySelectorAll('td[data-emp][data-hari]'));
            const current  = allCells.find(td => td.dataset.emp == employeeId && td.dataset.hari == hari);
            if (!current) return;
            const idx = allCells.indexOf(current);
            const totalDays = document.querySelectorAll('td[data-emp="'+employeeId+'"][data-hari]').length;
            let target = null;
            if (e.key==='ArrowRight') target = allCells[idx + 1];
            if (e.key==='ArrowLeft')  target = allCells[idx - 1];
            if (e.key==='ArrowDown')  target = allCells[idx + totalDays];
            if (e.key==='ArrowUp')    target = allCells[idx - totalDays];
            if (target) target.click();
        }
    }

    const cellStyle = getCellStyle(val, isSunday, isHoliday, holidayTipe, isDark);
    const borderColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.1)';

    return (
        <td onClick={startEdit} data-emp={employeeId} data-hari={hari} style={{
            width:36, minWidth:36, height:30, padding:0,
            textAlign:'center', fontSize:11, position:'relative',
            cursor: isViewer ? 'default' : 'pointer',
            borderRight: `1px solid ${borderColor}`,
            borderBottom: `1px solid ${borderColor}`,
            ...cellStyle,
        }}>
            {editing ? (
                <input ref={inputRef} defaultValue={val}
                    onBlur={e=>save(e.target.value)} onKeyDown={onKeyDown}
                    style={{
                        width:'100%', height:'100%', border:'none', textAlign:'center',
                        fontSize:11, fontWeight:700,
                        background: isDark ? 'rgba(232,160,32,.25)' : 'rgba(232,160,32,.2)',
                        color: isDark ? '#E8A020' : '#92400E',
                        outline: '2px solid #E8A020',
                        fontFamily:"'Outfit',sans-serif", textTransform:'uppercase',
                    }}
                />
            ) : (
                <span style={{opacity:saving?.4:1, display:'block', lineHeight:'30px'}}>{val}</span>
            )}
        </td>
    );
}

function useIsDark() {
    const [isDark, setIsDark] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('akm-theme') !== 'light';
        return true;
    });
    useEffect(() => {
        const interval = setInterval(() => {
            setIsDark(localStorage.getItem('akm-theme') !== 'light');
        }, 300);
        return () => clearInterval(interval);
    }, []);
    return isDark;
}

// ── MODAL TAMBAH ANGGOTA (multi-select) ───────────────────────
function TambahAnggotaModal({ onClose, availableEmployees = [], projectKode = '' }) {
    const [selected,     setSelected]     = useState({});
    const [loading,      setLoading]      = useState(false);
    const [search,       setSearch]       = useState('');
    const [subGroupBulk, setSubGroupBulk] = useState('');
    const [kelompokBulk, setKelompokBulk] = useState('per_jam');

    const filtered = search.trim()
        ? availableEmployees.filter(e =>
            e.nama_lengkap.toLowerCase().includes(search.toLowerCase()) ||
            e.id_badge.toLowerCase().includes(search.toLowerCase()) ||
            (e.jabatan||'').toLowerCase().includes(search.toLowerCase())
          )
        : availableEmployees;

    const selectedBadges    = Object.keys(selected).filter(k => selected[k]);
    const allFilteredSelected = filtered.length > 0 && filtered.every(e => selected[e.id_badge]);

    function toggleOne(id_badge) {
        setSelected(prev => ({ ...prev, [id_badge]: !prev[id_badge] }));
    }

    function toggleAll() {
        if (allFilteredSelected) {
            const next = { ...selected };
            filtered.forEach(e => { next[e.id_badge] = false; });
            setSelected(next);
        } else {
            const next = { ...selected };
            filtered.forEach(e => { next[e.id_badge] = true; });
            setSelected(next);
        }
    }

    const inp = {
        background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
        borderRadius:8, padding:'8px 11px', fontSize:12.5,
        fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
    };

    function submit(e) {
        e.preventDefault();
        if (selectedBadges.length === 0) { alert('Pilih minimal satu karyawan!'); return; }
        setLoading(true);
        router.post('/timesheet/members/bulk', {
            badges:    selectedBadges,
            sub_group: subGroupBulk || null,
            kelompok:  kelompokBulk,
        }, {
            onSuccess: () => { setLoading(false); onClose(); },
            onError:   () => setLoading(false),
        });
    }

    return (
        <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
            onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
            <div onClick={e=>e.stopPropagation()} style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(520px, calc(100vw - 24px))',maxHeight:'88vh',display:'flex',flexDirection:'column',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>

                {/* Header */}
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)',flexShrink:0}}>
                    <div>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Plus size={15}/> Tambah Anggota Timesheet</div>
                        <div style={{fontSize:11,color:'var(--muted)',marginTop:2}}>Bisa pilih banyak sekaligus</div>
                    </div>
                    <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
                </div>

                <form onSubmit={submit} style={{display:'flex',flexDirection:'column',flex:1,overflow:'hidden'}}>
                    <div style={{padding:'16px 20px',display:'flex',flexDirection:'column',gap:12,flex:1,overflow:'hidden'}}>

                        {/* Search */}
                        <div style={{position:'relative',flexShrink:0}}>
                            <span style={{position:'absolute',left:10,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={14}/></span>
                            <input style={{...inp,paddingLeft:32}} value={search}
                                onChange={e=>setSearch(e.target.value)}
                                placeholder="Cari nama atau badge..." />
                        </div>

                        {/* Info selected + toggle all */}
                        <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',flexShrink:0}}>
                            <div style={{fontSize:12,color:'var(--muted)'}}>
                                {filtered.length} karyawan {search ? 'ditemukan' : 'tersedia'}
                                {selectedBadges.length > 0 && (
                                    <span style={{marginLeft:8,color:'var(--accent)',fontWeight:700}}>
                                        · {selectedBadges.length} dipilih
                                    </span>
                                )}
                            </div>
                            {filtered.length > 0 && (
                                <button type="button" onClick={toggleAll}
                                    style={{fontSize:11,padding:'3px 10px',borderRadius:6,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                                    {allFilteredSelected ? 'Deselect Semua' : 'Pilih Semua'}
                                </button>
                            )}
                        </div>

                        {/* List karyawan — scrollable */}
                        <div style={{flex:1,overflowY:'auto',border:'1px solid var(--border)',borderRadius:8,background:'var(--bg3)'}}>
                            {filtered.length === 0 && (
                                <div style={{padding:'20px 14px',fontSize:12,color:'var(--muted)',textAlign:'center'}}>
                                    {search ? 'Tidak ada karyawan yang cocok' : 'Semua karyawan sudah menjadi anggota'}
                                </div>
                            )}
                            {filtered.map(emp => {
                                const isChecked = !!selected[emp.id_badge];
                                return (
                                    <div key={emp.id_badge}
                                        onClick={e => { e.stopPropagation(); toggleOne(emp.id_badge); }}
                                        style={{
                                            padding:'9px 14px', cursor:'pointer', fontSize:12.5,
                                            background: isChecked ? 'rgba(232,160,32,.12)' : 'transparent',
                                            borderBottom:'1px solid var(--border)',
                                            display:'flex', alignItems:'center', gap:12,
                                            transition:'background .1s',
                                        }}
                                        onMouseEnter={e=>{ if(!isChecked) e.currentTarget.style.background='rgba(232,160,32,.05)'; }}
                                        onMouseLeave={e=>{ if(!isChecked) e.currentTarget.style.background='transparent'; }}>
                                        <div style={{
                                            width:18, height:18, borderRadius:4, flexShrink:0,
                                            border: isChecked ? '2px solid var(--accent)' : '2px solid var(--border)',
                                            background: isChecked ? 'var(--accent)' : 'transparent',
                                            display:'flex', alignItems:'center', justifyContent:'center',
                                            transition:'all .15s',
                                        }}>
                                            {isChecked && <span style={{color:'#0C0F14',display:'flex'}}><Check size={11}/></span>}
                                        </div>
                                        <div style={{flex:1}}>
                                            <div style={{fontWeight:600,color: isChecked ? 'var(--accent)' : 'var(--text)'}}>{emp.nama_lengkap}</div>
                                            <div style={{fontSize:11,color:'var(--muted)'}}>{emp.jabatan}</div>
                                        </div>
                                        <div style={{fontFamily:'monospace',fontSize:11,color:'var(--accent)',flexShrink:0}}>{emp.id_badge}</div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Preview yang dipilih */}
                        {selectedBadges.length > 0 && (
                            <div style={{flexShrink:0,padding:'10px 12px',borderRadius:8,background:'rgba(232,160,32,.08)',border:'1px solid rgba(232,160,32,.2)'}}>
                                <div style={{fontSize:11,color:'var(--accent)',fontWeight:600,marginBottom:6}}>
                                    <Check size={12} style={{verticalAlign:-2}}/> {selectedBadges.length} karyawan akan ditambahkan:
                                </div>
                                <div style={{display:'flex',flexWrap:'wrap',gap:4}}>
                                    {selectedBadges.map(badge => {
                                        const emp = availableEmployees.find(e => e.id_badge === badge);
                                        return (
                                            <span key={badge} style={{
                                                fontSize:10.5, padding:'2px 8px', borderRadius:99,
                                                background:'rgba(232,160,32,.15)', color:'var(--accent)',
                                                border:'1px solid rgba(232,160,32,.3)',
                                                display:'flex', alignItems:'center', gap:4,
                                            }}>
                                                {emp?.nama_lengkap || badge}
                                                <span onClick={e=>{e.stopPropagation();toggleOne(badge);}}
                                                    style={{cursor:'pointer',opacity:.7,display:'flex'}}><X size={10}/></span>
                                            </span>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div style={{padding:'12px 20px',borderTop:'1px solid var(--border)',flexShrink:0,background:'var(--bg2)',display:'flex',flexDirection:'column',gap:10}}>
                        <div className="form-grid-2" style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:10}}>
                            {/* Sub-Group — sesuai pengelompokan project */}
                            {SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()] && (
                                <div>
                                    <label style={{fontSize:11,color:'var(--muted)',marginBottom:4,display:'block'}}>Sub-Group:</label>
                                    <select value={subGroupBulk} onChange={e=>setSubGroupBulk(e.target.value)}
                                        style={{background:'var(--bg3)',border:'1px solid var(--border)',color:'var(--text)',borderRadius:8,padding:'7px 11px',fontSize:12.5,fontFamily:"'Outfit',sans-serif",outline:'none',width:'100%'}}>
                                        <option value="">— Tidak ada —</option>
                                        {SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()].map(o => (
                                            <option key={o.value} value={o.value}>{o.emoji} {o.label}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {/* Kelompok Lembur — hanya non-MD */}
                            {(projectKode||'').toLowerCase() !== 'md' && (
                                <div>
                                    <label style={{fontSize:11,color:'var(--muted)',marginBottom:4,display:'block'}}>Kelompok Lembur:</label>
                                    <select value={kelompokBulk} onChange={e=>setKelompokBulk(e.target.value)}
                                        style={{background:'var(--bg3)',border:'1px solid var(--border)',color:'var(--text)',borderRadius:8,padding:'7px 11px',fontSize:12.5,fontFamily:"'Outfit',sans-serif",outline:'none',width:'100%'}}>
                                        <option value="per_jam">Per Jam</option>
                                        <option value="flat">Flat</option>
                                    </select>
                                </div>
                            )}
                        </div>
                        <div style={{display:'flex',gap:8,justifyContent:'flex-end'}}>
                            <button type="button" onClick={onClose}
                                style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                                Batal
                            </button>
                            <button type="submit" disabled={loading||selectedBadges.length===0}
                                style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,
                                    cursor:loading||selectedBadges.length===0?'not-allowed':'pointer',
                                    fontFamily:"'Outfit',sans-serif",opacity:loading||selectedBadges.length===0?.5:1}}>
                                {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Plus size={14}/>Tambah {selectedBadges.length > 0 ? selectedBadges.length+' ' : ''}Anggota</span>}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── MODAL EDIT ANGGOTA ────────────────────────────────────────
function EditAnggotaModal({ member, onClose, projectKode }) {
    const [namaOverride, setNamaOverride] = useState(member.nama_override || '');
    const [urutan,       setUrutan]       = useState(member.urutan || 0);
    const [aktif,        setAktif]        = useState(member.aktif);
    const [tipe,         setTipe]         = useState(member.tipe || '7jam');
    const [subGroup,     setSubGroup]     = useState(member.sub_group || '');
    const [kelompok,     setKelompok]     = useState(member.kelompok || 'per_jam');
    const [loading,      setLoading]      = useState(false);

    const inp = {
        background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
        borderRadius:8, padding:'8px 11px', fontSize:12.5,
        fontFamily:"'Outfit',sans-serif", outline:'none', width:'100%', boxSizing:'border-box',
    };

    function submit(e) {
        e.preventDefault();
        setLoading(true);
        router.put(`/timesheet/members/${member.id}`, {
            nama_override: namaOverride.trim() || null,
            urutan:        parseInt(urutan) || 0,
            aktif,
            tipe,
            sub_group: subGroup || null,
            kelompok,
        }, {
            onSuccess: () => { setLoading(false); onClose(); },
            onError:   () => setLoading(false),
        });
    }

    return (
        <div style={{position:'fixed',inset:0,zIndex:300,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}>
            <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:16,width:'min(460px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
                    <div>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,display:'flex',alignItems:'center',gap:8}}><Pencil size={15}/> Edit Anggota</div>
                        <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>{member.id_badge}</div>
                    </div>
                    <div onClick={onClose} style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
                </div>
                <form onSubmit={submit}>
                    <div style={{padding:'18px 20px',display:'flex',flexDirection:'column',gap:14}}>

                        {/* Nama Override */}
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Nama Override</label>
                            <input style={inp} value={namaOverride}
                                onChange={e=>setNamaOverride(e.target.value)}
                                placeholder="Kosongkan untuk pakai nama dari data karyawan" />
                        </div>

                        {/* Urutan */}
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Urutan</label>
                            <input type="number" style={inp} value={urutan} onChange={e=>setUrutan(e.target.value)} />
                        </div>

                        {/* Tipe Timesheet */}
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Tipe Timesheet</label>
                            <select style={inp} value={tipe} onChange={e=>setTipe(e.target.value)}>
                                <option value="7jam">7 Jam (Giam / Khawista / Purnama)</option>
                                <option value="8jam">8 Jam (MD — Multi Disiplin)</option>
                            </select>
                            {tipe === '8jam' && (
                                <div style={{fontSize:11,color:'var(--accent)',marginTop:4}}>
                                    <TriangleAlert size={12} style={{verticalAlign:-2}}/> Tipe 8 jam: OT dihitung dari jam ke-9, Sabtu OT dari jam pertama
                                </div>
                            )}
                        </div>

                        {/* Sub-Group — sesuai pengelompokan project */}
                        {SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()] && (
                          <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Sub-Group</label>
                            <select style={inp} value={subGroup} onChange={e=>setSubGroup(e.target.value)}>
                              <option value="">— Tidak ada (tampil di semua tab) —</option>
                              {SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()].map(o => (
                                <option key={o.value} value={o.value}>{o.emoji} {o.label}</option>
                              ))}
                            </select>
                            {subGroup && (
                              <div style={{fontSize:10.5,color:'var(--accent)',marginTop:3}}>
                                Muncul di tab {subGroupMeta(projectKode, subGroup).label}
                              </div>
                            )}
                          </div>
                        )}

                        {/* Kelompok Lembur */}
                        <div>
                            <label style={{fontSize:10.5,color:'var(--muted)',marginBottom:4,display:'block'}}>Kelompok Lembur</label>
                            <select style={inp} value={kelompok} onChange={e=>setKelompok(e.target.value)}>
                                <option value="per_jam">Per Jam (lembur dihitung dari timesheet)</option>
                                <option value="flat">Flat</option>
                            </select>
                            <div style={{fontSize:10.5,color:'var(--muted)',marginTop:3}}>
                                {kelompok==='flat'
                                    ? 'Lembur flat: tidak bergantung jabatan, dihitung manual per hari'
                                    : 'Lembur per jam: dihitung otomatis dari total jam di timesheet'}
                            </div>
                        </div>

                        {/* Status Aktif */}
                        <div style={{display:'flex',alignItems:'center',gap:10}}>
                            <label style={{fontSize:10.5,color:'var(--muted)'}}>Status:</label>
                            <div onClick={()=>setAktif(!aktif)} style={{display:'flex',alignItems:'center',gap:6,cursor:'pointer'}}>
                                <div style={{width:36,height:20,borderRadius:10,background:aktif?'var(--green)':'var(--muted)',position:'relative',transition:'background .2s'}}>
                                    <div style={{position:'absolute',top:2,left:aktif?18:2,width:16,height:16,borderRadius:'50%',background:'#fff',transition:'left .2s'}}/>
                                </div>
                                <span style={{fontSize:12,fontWeight:600,color:aktif?'var(--green)':'var(--muted)'}}>{aktif?'Aktif':'Nonaktif'}</span>
                            </div>
                        </div>
                    </div>
                    <div style={{display:'flex',gap:10,justifyContent:'flex-end',padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
                        <button type="button" onClick={onClose} style={{padding:'9px 18px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12.5,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
                        <button type="submit" disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12.5,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Save size={14}/>Simpan</span>}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── TAB KELOLA ANGGOTA (dengan drag-drop + grouping per jam/flat) ─
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableRow({ member, orderNo, projectKode, isViewer, onEdit, onDelete, hideSubGroupCol=false }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: member.id });
    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        background: isDragging ? 'rgba(232,160,32,.08)' : undefined,
    };
    const hasSubGroupOptions = !hideSubGroupCol && !!SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()];

    return (
        <tr ref={setNodeRef} style={style}
            onMouseEnter={ev => !isDragging && Array.from(ev.currentTarget.cells).forEach(c => c.style.background = 'rgba(232,160,32,.04)')}
            onMouseLeave={ev => !isDragging && Array.from(ev.currentTarget.cells).forEach(c => c.style.background = '')}>
            <td style={{padding:'10px 8px',borderBottom:'1px solid var(--border)',textAlign:'center',width:60}}>
                <div style={{display:'flex',alignItems:'center',gap:8,justifyContent:'center'}}>
                    {!isViewer && (
                        <div {...attributes} {...listeners} style={{cursor:'grab',color:'var(--muted)',fontSize:14,userSelect:'none',touchAction:'none'}} title="Drag untuk mengatur urutan">
                            ⋮⋮
                        </div>
                    )}
                    <span style={{
                        fontFamily:'monospace',fontSize:12,fontWeight:700,
                        color:'var(--accent)',
                        background:'rgba(232,160,32,.1)',
                        padding:'2px 8px',borderRadius:6,
                        minWidth:24,textAlign:'center',
                    }}>
                        {orderNo}
                    </span>
                </div>
            </td>
            <td style={{padding:'10px 12px',fontFamily:'monospace',color:'var(--accent)',borderBottom:'1px solid var(--border)',fontWeight:600}}>{member.id_badge}</td>
            <td style={{padding:'10px 12px',borderBottom:'1px solid var(--border)'}}>
                <div style={{fontWeight:600}}>{member.nama_override || member.nama_lengkap || '—'}</div>
                {member.nama_override && <div style={{fontSize:10.5,color:'var(--muted)'}}>Override dari: {member.nama_lengkap}</div>}
            </td>
            <td style={{padding:'10px 12px',borderBottom:'1px solid var(--border)',color:'var(--muted2)',fontSize:11.5}}>{member.jabatan}</td>
            {hasSubGroupOptions && (
                <td style={{padding:'10px 12px',borderBottom:'1px solid var(--border)'}}>
                    {member.sub_group ? (() => {
                        const opts = SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()] || [];
                        const idx = opts.findIndex(o => o.value === member.sub_group);
                        const meta = subGroupMeta(projectKode, member.sub_group);
                        const isSecond = idx === 1;
                        return (
                            <span style={{
                                padding:'2px 10px', borderRadius:99, fontSize:11, fontWeight:700,
                                display:'inline-flex', alignItems:'center', gap:4,
                                background: isSecond ? 'rgba(58,143,224,.12)' : 'rgba(232,160,32,.12)',
                                color: isSecond ? 'var(--blue)' : 'var(--accent)',
                            }}>
                                {meta.icon && <meta.icon size={11}/>} {meta.label}
                            </span>
                        );
                    })() : <span style={{color:'var(--muted)',fontSize:11}}>—</span>}
                </td>
            )}
            <td style={{padding:'10px 12px',borderBottom:'1px solid var(--border)'}}>
                <span style={{padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600,
                    background:member.tipe==='8jam'?'rgba(34,201,122,.12)':'rgba(58,143,224,.12)',
                    color:member.tipe==='8jam'?'var(--green)':'var(--blue)'}}>
                    {member.tipe === '8jam' ? '8 Jam (MD)' : '7 Jam'}
                </span>
            </td>
            <td style={{padding:'10px 12px',borderBottom:'1px solid var(--border)'}}>
                <span style={{padding:'2px 10px',borderRadius:99,fontSize:11,fontWeight:600,
                    background:member.aktif?'rgba(34,201,122,.12)':'rgba(128,128,128,.12)',
                    color:member.aktif?'var(--green)':'var(--muted)'}}>
                    {member.aktif ? <span style={{display:'inline-flex',alignItems:'center',gap:4}}><Check size={11}/>Aktif</span> : <span style={{display:'inline-flex',alignItems:'center',gap:4}}><X size={11}/>Nonaktif</span>}
                </span>
            </td>
            <td style={{padding:'8px 12px',borderBottom:'1px solid var(--border)',textAlign:'center'}}>
                <div style={{display:'flex',gap:5,justifyContent:'center'}}>
                    {!isViewer && (
                        <button onClick={()=>onEdit(member)} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(232,160,32,.12)',color:'var(--accent)',border:'1px solid rgba(232,160,32,.25)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            <Pencil size={11} style={{verticalAlign:-2}}/> Edit
                        </button>
                    )}
                    {!isViewer && (
                        <button onClick={()=>onDelete(member.id, member.id_badge)} style={{padding:'3px 10px',borderRadius:6,fontSize:11,fontWeight:600,background:'rgba(224,69,69,.1)',color:'#E04545',border:'1px solid rgba(224,69,69,.2)',cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            <Trash2 size={11}/>
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function GroupSection({ title, icon, color, members, startNumber, projectKode, isViewer, onEdit, onDelete, onDragEnd, hideSubGroupCol=false }) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));
    const hasSubGroupOptions = !hideSubGroupCol && !!SUB_GROUP_OPTIONS[(projectKode||'').toLowerCase()];

    return (
        <div style={{marginBottom:20}}>
            <div style={{
                display:'flex',alignItems:'center',gap:10,padding:'8px 14px',
                background:`${color}12`,border:`1px solid ${color}30`,
                borderRadius:'8px 8px 0 0',borderBottom:'none',
            }}>
                <span style={{fontSize:14}}>{icon}</span>
                <span style={{fontFamily:'Syne,sans-serif',fontSize:13,fontWeight:700,color}}>{title}</span>
                <span style={{fontSize:11,color:'var(--muted)',marginLeft:'auto'}}>{members.length} anggota</span>
            </div>
            <div className="panel" style={{overflow:'hidden',borderRadius:'0 0 8px 8px',border:`1px solid ${color}30`,borderTop:'none'}}>
                <table style={{width:'100%',borderCollapse:'collapse',fontSize:12.5}}>
                    <thead>
                        <tr>
                            <th style={{...thHoliday(true),textAlign:'center',width:60}}>No</th>
                            <th style={{...thHoliday(true),textAlign:'left'}}>ID Badge</th>
                            <th style={{...thHoliday(true),textAlign:'left'}}>Nama</th>
                            <th style={{...thHoliday(true),textAlign:'left'}}>Jabatan</th>
                            {hasSubGroupOptions && <th style={{...thHoliday(true),textAlign:'left'}}>Sub-Group</th>}
                            <th style={{...thHoliday(true),textAlign:'left'}}>Tipe</th>
                            <th style={{...thHoliday(true),textAlign:'left'}}>Status</th>
                            <th style={{...thHoliday(true),textAlign:'center',width:130}}>Aksi</th>
                        </tr>
                    </thead>
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                        <SortableContext items={members.map(m=>m.id)} strategy={verticalListSortingStrategy}>
                            <tbody>
                                {members.length === 0 && (
                                    <tr><td colSpan={hasSubGroupOptions?8:7} style={{padding:20,textAlign:'center',color:'var(--muted)',fontSize:11.5}}>
                                        Belum ada anggota di kelompok ini
                                    </td></tr>
                                )}
                                {members.map((m, i) => (
                                    <SortableRow key={m.id} member={m}
                                        orderNo={(startNumber || 1) + i}
                                        projectKode={projectKode} isViewer={isViewer}
                                        onEdit={onEdit} onDelete={onDelete}
                                        hideSubGroupCol={hideSubGroupCol} />
                                ))}
                            </tbody>
                        </SortableContext>
                    </DndContext>
                </table>
            </div>
        </div>
    );
}

function TabKelolaAnggota({ members = [], availableEmployees = [], isViewer = false, project_info = null }) {
    const [showAdd,     setShowAdd]     = useState(false);
    const [editItem,    setEditItem]    = useState(null);
    const [search,      setSearch]      = useState('');
    const [localList,   setLocalList]   = useState(members);
    const [subGroupTab, setSubGroupTab] = useState('all');

    useEffect(() => { setLocalList(members); }, [members]);

    function hapus(id, badge) {
        if (!window.confirm(`Hapus ${badge} dari daftar timesheet?`)) return;
        router.delete(`/timesheet/members/${id}`, { preserveScroll: true });
    }

    const searched = search.trim()
        ? localList.filter(m =>
            m.id_badge?.toLowerCase().includes(search.toLowerCase()) ||
            (m.nama_override || '').toLowerCase().includes(search.toLowerCase()) ||
            (m.nama_lengkap  || '').toLowerCase().includes(search.toLowerCase()) ||
            (m.jabatan       || '').toLowerCase().includes(search.toLowerCase())
          )
        : localList;

    // Project yang punya sub-group (mis. GIAM: staff/mechanical/construction) — tab pemisah
    // di atas, lalu di dalam tab yang aktif tetap dipisah lagi per_jam vs flat seperti biasa.
    const subGroupOpts = SUB_GROUP_OPTIONS[(project_info?.kode||'').toLowerCase()];
    const filtered = subGroupOpts && subGroupTab !== 'all'
        ? searched.filter(m => m.sub_group === subGroupTab)
        : searched;

    const perJam = filtered.filter(m => (m.kelompok || 'per_jam') === 'per_jam');
    const flat   = filtered.filter(m => m.kelompok === 'flat');

    function handleDragEnd(kelompok) {
        return (event) => {
            const { active, over } = event;
            if (!over || active.id === over.id) return;

            const groupList = kelompok === 'per_jam' ? perJam : flat;
            const oldIdx = groupList.findIndex(m => m.id === active.id);
            const newIdx = groupList.findIndex(m => m.id === over.id);
            if (oldIdx === -1 || newIdx === -1) return;

            // Reorder di dalam grup yang di-drag saja; anggota lain (termasuk sub-group/kelompok
            // lain yang sedang tidak tampil) tetap di posisi relatifnya di localList.
            const reordered = arrayMove(groupList, oldIdx, newIdx);
            let ptr = 0;
            const reorderedIds = new Set(reordered.map(m => m.id));
            const newLocal = localList.map(m => reorderedIds.has(m.id) ? reordered[ptr++] : m);

            setLocalList(newLocal);

            // Kirim urutan baru ke server
            const orderPayload = newLocal.map((m, i) => ({ id: m.id, urutan: i + 1 }));
            axios.post('/timesheet/members/reorder', { members: orderPayload }, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }
            }).catch(() => {
                alert('Gagal menyimpan urutan. Halaman akan direfresh.');
                router.reload({ preserveScroll: true });
            });
        };
    }

    return (
        <>
            {showAdd && (
                <TambahAnggotaModal
                    onClose={()=>setShowAdd(false)}
                    availableEmployees={availableEmployees.filter(e => !localList.some(m => m.id_badge === e.id_badge))}
                    projectKode={project_info?.kode}
                />
            )}
            {editItem && <EditAnggotaModal member={editItem} onClose={()=>setEditItem(null)} projectKode={project_info?.kode} />}

            <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:16,flexWrap:'wrap',gap:10}}>
                <div>
                    <div style={{fontSize:13,fontWeight:600}}>Kelola Anggota Timesheet</div>
                    <div style={{fontSize:11.5,color:'var(--muted)',marginTop:2}}>
                        Total: <b style={{color:'var(--accent)'}}>{localList.filter(m=>m.aktif).length}</b> aktif · Drag <b style={{color:'var(--accent)'}}>⋮⋮</b> untuk mengatur urutan
                    </div>
                </div>
                {!isViewer && (
                    <button onClick={()=>setShowAdd(true)} style={{padding:'7px 14px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#E8A020,#A06010)',color:'#0C0F14',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                        <Plus size={13} style={{verticalAlign:-2}}/> Tambah Anggota
                    </button>
                )}
            </div>

            <div style={{position:'relative',marginBottom:14}}>
                <span style={{position:'absolute',left:11,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={15}/></span>
                <input className="search-input" type="text" value={search}
                    placeholder="Cari badge, nama, atau jabatan..."
                    onChange={e => setSearch(e.target.value)}
                    style={{paddingLeft:'36px', width:'100%'}} />
            </div>

            {subGroupOpts && (
                <div style={{display:'flex',gap:4,marginBottom:14,flexWrap:'wrap'}}>
                    {[
                        { key:'all', label:`Semua (${searched.length})`, icon:null },
                        ...subGroupOpts.map(o => ({ key:o.value, label:`${o.label} (${searched.filter(m=>m.sub_group===o.value).length})`, icon:o.icon })),
                    ].map(t => (
                        <div key={t.key} onClick={() => setSubGroupTab(t.key)}
                            style={{
                                padding:'7px 16px', borderRadius:8, cursor:'pointer',
                                fontSize:12.5, fontWeight:600, transition:'all .15s',
                                display:'inline-flex', alignItems:'center', gap:6,
                                background: subGroupTab===t.key ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--card)',
                                color:       subGroupTab===t.key ? '#0C0F14' : 'var(--muted)',
                                border: `1px solid ${subGroupTab===t.key ? 'transparent' : 'var(--border)'}`,
                            }}>
                            {t.icon && <t.icon size={12}/>} {t.label}
                        </div>
                    ))}
                </div>
            )}

            {localList.length === 0 ? (
                <div className="panel" style={{padding:32,textAlign:'center',color:'var(--muted)'}}>
                    Belum ada anggota. Klik <b>Tambah Anggota</b> untuk menambahkan.
                </div>
            ) : filtered.length === 0 ? (
                <div className="panel" style={{padding:24,textAlign:'center',color:'var(--muted)',fontSize:12}}>
                    Tidak ada anggota yang cocok dengan pencarian/tab ini
                </div>
            ) : (
                <>
                    <GroupSection title="Per Jam" icon={<Timer size={14}/>} color="#22C97A"
                        members={perJam} startNumber={1}
                        projectKode={project_info?.kode} isViewer={isViewer}
                        onEdit={setEditItem} onDelete={hapus}
                        onDragEnd={handleDragEnd('per_jam')}
                        hideSubGroupCol={subGroupTab !== 'all'} />
                    <GroupSection title="Flat" icon={<ClipboardList size={14}/>} color="#9B59B6"
                        members={flat} startNumber={perJam.length + 1}
                        projectKode={project_info?.kode} isViewer={isViewer}
                        onEdit={setEditItem} onDelete={hapus}
                        onDragEnd={handleDragEnd('flat')}
                        hideSubGroupCol={subGroupTab !== 'all'} />
                </>
            )}

            {localList.length > 0 && (
                <div style={{fontSize:11,color:'var(--muted)',marginTop:8,textAlign:'right'}}>
                    {search ? `${filtered.length} dari ` : ''}{localList.length} anggota
                </div>
            )}
        </>
    );
}

// ── MODAL TAMBAH / HAPUS LIBUR DARI HEADER ────────────────────
function AddHolidayFromHeaderModal({ day, bulan, tahun, isSaturday, onClose, onSaved }) {
    const tgl = `${tahun}-${String(bulan).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    const [loading, setLoading] = useState(false);
    function doToggle() {
        setLoading(true);
        const keterangan = isSaturday ? 'Libur (Sabtu)' : 'Libur';
        router.post('/pengaturan/holidays', { tanggal: tgl, keterangan, tipe: 'libur_khusus' }, {
            onSuccess: () => { onSaved(); onClose(); },
            onError:   () => setLoading(false),
        });
    }
    return (
        <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
            onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
            <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:14,width:'min(340px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',padding:'22px 24px',textAlign:'center'}}>
                <div style={{marginBottom:10,display:'flex',justifyContent:'center'}}><Calendar size={28}/></div>
                <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700,marginBottom:6}}>Tandai sebagai Libur?</div>
                <div style={{fontSize:13,color:'var(--muted2)',marginBottom:20}}>
                    <b style={{color:'var(--accent)'}}>{String(day).padStart(2,'0')} {BULAN_NAMA[bulan]} {tahun}</b>
                    {isSaturday && <span style={{marginLeft:8,fontSize:11,color:'#22C97A'}}>(Sabtu)</span>}
                    <br/>
                    <span style={{fontSize:11.5,color:'var(--muted)'}}>Tanggal ini akan menjadi <b style={{color:'#C070F0'}}>L</b> di timesheet</span>
                </div>
                <div style={{display:'flex',gap:10,justifyContent:'center'}}>
                    <button onClick={onClose} style={{padding:'9px 20px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
                    <button onClick={doToggle} disabled={loading} style={{padding:'9px 22px',borderRadius:8,border:'none',background:'linear-gradient(135deg,#C070F0,#7C3AED)',color:'#fff',fontSize:12,fontWeight:700,cursor:loading?'not-allowed':'pointer',fontFamily:"'Outfit',sans-serif",opacity:loading?.7:1}}>
                        {loading ? <Loader2 size={14} style={{animation:'spin .8s linear infinite'}}/> : <span style={{display:'inline-flex',alignItems:'center',gap:6}}><Check size={14}/>Jadikan Libur</span>}
                    </button>
                </div>
            </div>
        </div>
    );
}

function DeleteHolidayFromHeaderModal({ holiday, onClose, onSaved }) {
    function doDelete() {
        router.delete(`/pengaturan/holidays/${holiday.id}`, {
            preserveScroll: true,
            onSuccess: () => { onSaved(); onClose(); },
        });
    }
    return (
        <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',display:'flex',alignItems:'center',justifyContent:'center'}}
            onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
            <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',borderRadius:14,width:'min(360px, calc(100vw - 24px))',boxShadow:'0 24px 80px rgba(0,0,0,.5)',padding:'20px 22px'}}>
                <div style={{fontFamily:'Syne,sans-serif',fontSize:14,fontWeight:700,marginBottom:10,display:'flex',alignItems:'center',gap:8}}><Trash2 size={14}/> Hapus Hari Libur?</div>
                <div style={{fontSize:12.5,color:'var(--muted2)',marginBottom:16}}>
                    <b style={{color:'var(--accent)'}}>{holiday.tanggal_fmt}</b><br/>
                    <span>{holiday.keterangan}</span>
                </div>
                <div style={{display:'flex',gap:10,justifyContent:'flex-end'}}>
                    <button onClick={onClose} style={{padding:'8px 16px',borderRadius:8,border:'1px solid var(--border)',background:'var(--bg3)',color:'var(--muted2)',fontSize:12,cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>Batal</button>
                    <button onClick={doDelete} style={{padding:'8px 18px',borderRadius:8,border:'none',background:'rgba(224,69,69,.85)',color:'#fff',fontSize:12,fontWeight:700,cursor:'pointer',fontFamily:"'Outfit',sans-serif",display:'flex',alignItems:'center',gap:6}}><Trash2 size={13}/>Hapus</button>
                </div>
            </div>
        </div>
    );
}

    function TtdModal({ tahun, bulan, ttdHr, ttdPm, onChangeTtdHr, onChangeTtdPm, onClose }) {
        const inp = {
            background:'var(--bg3)', border:'1px solid var(--border)', color:'var(--text)',
            borderRadius:8, padding:'8px 12px', fontSize:13,
            fontFamily:"'Outfit',sans-serif", outline:'none',
            width:'100%', boxSizing:'border-box',
        };
        return (
            <div style={{position:'fixed',inset:0,zIndex:400,background:'rgba(0,0,0,.65)',
                display:'flex',alignItems:'center',justifyContent:'center'}}
                onMouseDown={e=>{e.currentTarget.dataset.downOutside=e.target===e.currentTarget;}} onClick={e=>{e.target===e.currentTarget&&e.currentTarget.dataset.downOutside==='true'&&onClose();}}>
                <div style={{background:'var(--bg2)',border:'1px solid var(--border2)',
                    borderRadius:16,width:'min(420px,calc(100vw - 24px))',
                    boxShadow:'0 24px 80px rgba(0,0,0,.5)'}}>

                    <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',
                        padding:'16px 20px',borderBottom:'1px solid var(--border)'}}>
                        <div style={{fontFamily:'Syne,sans-serif',fontSize:15,fontWeight:700}}>
                            <ClipboardList size={14} style={{verticalAlign:-2}}/> Export Timesheet
                        </div>
                        <div onClick={onClose}
                            style={{cursor:'pointer',color:'var(--muted)',display:'flex'}}><X size={18}/></div>
                    </div>

                    <div style={{padding:'20px 20px',display:'flex',flexDirection:'column',gap:14}}>
                        <div style={{fontSize:12,color:'var(--muted)',padding:'8px 12px',
                            borderRadius:8,background:'rgba(58,143,224,.06)',
                            border:'1px solid rgba(58,143,224,.15)'}}>
                            Nama penanda tangan akan tampil di bagian bawah file Excel.
                        </div>

                        <div>
                            <label style={{fontSize:11,color:'var(--muted)',marginBottom:5,display:'block'}}>
                                <User size={12} style={{verticalAlign:-2}}/> HR Project (TTD kiri)
                            </label>
                            <input
                                style={inp}
                                value={ttdHr}
                                onChange={e => onChangeTtdHr(e.target.value)}
                                placeholder="Nama HR Project..."
                            />
                        </div>

                        <div>
                            <label style={{fontSize:11,color:'var(--muted)',marginBottom:5,display:'block'}}>
                                <User size={12} style={{verticalAlign:-2}}/> Project Manager (TTD kanan)
                            </label>
                            <input
                                style={inp}
                                value={ttdPm}
                                onChange={e => onChangeTtdPm(e.target.value)}
                                placeholder="Nama Project Manager..."
                            />
                        </div>
                    </div>

                    <div style={{display:'flex',gap:8,justifyContent:'flex-end',
                        padding:'12px 20px',borderTop:'1px solid var(--border)'}}>
                        <button onClick={onClose}
                            style={{padding:'8px 18px',borderRadius:8,
                                border:'1px solid var(--border)',background:'var(--bg3)',
                                color:'var(--muted2)',fontSize:12.5,cursor:'pointer',
                                fontFamily:"'Outfit',sans-serif"}}>
                            Batal
                        </button>
                        <button onClick={()=>{
                                onClose();
                                const url = `/timesheet/export?tahun=${tahun}&bulan=${bulan}`
                                    + `&ttd_hr=${encodeURIComponent(ttdHr)}`
                                    + `&ttd_pm=${encodeURIComponent(ttdPm)}`;
                                window.location.href = url;
                            }}
                            style={{padding:'8px 20px',borderRadius:8,border:'none',
                                background:'linear-gradient(135deg,#3A8FE0,#1A5FA0)',
                                color:'#fff',fontSize:12.5,fontWeight:700,
                                cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                            <Download size={13} style={{verticalAlign:-2}}/> Download Excel
                        </button>
                    </div>
                </div>
            </div>
        );
    }


// ── MAIN COMPONENT ────────────────────────────────────────────
export default function TimesheetPage({
    grid=[], days=[], tahun, bulan,
    search='', bulan_list={}, days_in_month=31,
    project_info=null,
    all_holidays=[], members=[], available_employees=[],
}) {
    const isMd = project_info?.tipe_timesheet === '8jam';

    const [searchVal,         setSearchVal]         = useState(search);
    const [selTahun,          setSelTahun]          = useState(tahun);
    const [selBulan,          setSelBulan]          = useState(bulan);
    const [activeTab,         setActiveTab]         = useState('timesheet');
    const [holidayClickModal, setHolidayClickModal] = useState(null);
    const [gridTab,           setGridTab]           = useState('all');
    const [showTtdModal, setShowTtdModal] = useState(false);
    const [ttdHr, setTtdHr] = useState('');
    const [ttdPm, setTtdPm] = useState('');

    useEffect(() => { setSelBulan(bulan); }, [bulan]);
    useEffect(() => { setSelTahun(tahun); }, [tahun]);

    const isDark   = useIsDark();
    const { auth } = usePage().props;
    const isViewer = auth?.user?.can?.is_viewer || auth?.user?.can?.is_project_readonly || false;

    const hasSubGroup  = grid.some(e => e.sub_group);
    const activeGrid   = gridTab === 'all' ? grid : grid.filter(e => e.sub_group === gridTab);

    const [localDays, setLocalDays] = useState(() => {
        const m = {};
        grid.forEach(emp => { m[emp.id] = {...emp.days}; });
        return m;
    });

    useEffect(() => {
        const m = {};
        grid.forEach(emp => { m[emp.id] = {...emp.days}; });
        setLocalDays(m);
    }, [grid]);

    function handleSaved(empId, hari, nilai) {
        setLocalDays(prev => ({...prev, [empId]: {...prev[empId], [hari]: nilai}}));
    }

    function navigate(newTahun, newBulan, newSearch) {
        router.get('/timesheet', {tahun:newTahun, bulan:newBulan, search:newSearch}, {preserveState:true, replace:true});
    }

    const tahunList = [];
    for (let y = 2024; y <= 2030; y++) tahunList.push(y);

    const cardBg      = isDark ? 'var(--card)' : '#FFFFFF';
    const cardBg2     = isDark ? 'var(--bg3)'  : '#F8FAFC';
    const headerBg    = isDark ? 'var(--bg2)'  : '#F1F5F9';
    const borderClr   = isDark ? 'rgba(255,255,255,.1)'  : 'rgba(0,0,0,.1)';
    const thickBorder = isDark ? 'rgba(255,255,255,.25)' : 'rgba(0,0,0,.2)';
    const sunBg       = isDark ? '#2A1A1A' : '#FEE2E2';
    const satBg       = isDark ? '#1A1A2A' : '#EEF2FF';

    const inp = {
        background: isDark ? 'var(--bg3)' : '#F1F5F9',
        border: `1px solid ${isDark ? 'var(--border)' : '#CBD5E1'}`,
        color:'var(--text)', borderRadius:7, padding:'6px 10px',
        fontSize:12, fontFamily:"'Outfit',sans-serif", outline:'none',
    };

    const isNk = (project_info?.kode||'').toLowerCase() === 'nk';

    const sumCols = [
        { key:'hadir', label:'Hadir', bg: isDark?'rgba(13,46,26,.8)':'#D4EDDA',  color: isDark?'#30D080':'#155724' },
        { key:'izin',  label:'Izin',  bg: isDark?'rgba(26,58,106,.8)':'#BDD7EE', color: isDark?'#5AAAF5':'#1F497D' },
        { key:'sakit', label:'Sakit', bg: isDark?'rgba(74,58,0,.8)':'#FFD966',   color: isDark?'#FFD966':'#7F6000' },
        { key:'alpa',  label:'Alpha', bg: isDark?'rgba(90,26,26,.8)':'#FF7C80',  color: isDark?'#FF7C80':'#9C0006' },
        { key:'cuti',  label:'Cuti',  bg: isDark?'rgba(26,74,42,.8)':'#C6EFCE',  color: isDark?'#C6EFCE':'#276221' },
        ...(isNk  ? [{ key:'stb', label:'STB', bg: isDark?'rgba(42,42,74,.8)':'#E8E8FF', color: isDark?'#A0A0FF':'#3A3A9C' }] : []),
        ...(isMd  ? [{ key:'total_ot_jam', label:'OT Jam', bg: isDark?'rgba(80,60,10,.8)':'#FFF0D0', color: isDark?'#E8A020':'#A06010' }] : []),
    ];

    const holidayDays = days.filter(d => d.is_holiday && !d.is_sunday);
    const holidayMap  = {};
    all_holidays.forEach(h => {
        const d = new Date(h.tanggal);
        if (d.getFullYear() === tahun && d.getMonth() + 1 === bulan) {
            holidayMap[d.getDate()] = h;
        }
    });

    return (
        <AppLayout title="Timesheet" subtitle={`${BULAN_NAMA[bulan]} ${tahun}`}>
            <style>{`
                @media (max-width: 768px), (max-height: 500px) and (orientation: landscape) {
                    .ts-col-no { display: none !important; }
                    .ts-col-nama {
                        width: 110px !important; min-width: 110px !important;
                        left: 0 !important;
                    }
                    .ts-col-nama > div:first-child { max-width: 95px !important; font-size: 10.5px !important; }
                    .ts-col-nama > div:last-child { display: none !important; }
                    .ts-col-badge {
                        width: 70px !important; min-width: 70px !important;
                        left: 110px !important; font-size: 9px !important;
                    }
                }
            `}</style>

            {holidayClickModal && !holidayClickModal.existing && (
                <AddHolidayFromHeaderModal
                    day={holidayClickModal.day} bulan={bulan} tahun={tahun}
                    isSaturday={holidayClickModal.isSaturday || false}
                    onClose={() => setHolidayClickModal(null)}
                    onSaved={() => { setHolidayClickModal(null); router.reload({ preserveScroll: true }); }}
                />
            )}
            {holidayClickModal && holidayClickModal.existing && (
                <DeleteHolidayFromHeaderModal
                    holiday={holidayClickModal.existing}
                    onClose={() => setHolidayClickModal(null)}
                    onSaved={() => { setHolidayClickModal(null); router.reload({ preserveScroll: true }); }}
                />
            )}

            {showTtdModal && (
                <TtdModal
                    tahun={tahun}
                    bulan={bulan}
                    ttdHr={ttdHr}
                    ttdPm={ttdPm}
                    onChangeTtdHr={setTtdHr}
                    onChangeTtdPm={setTtdPm}
                    onClose={()=>setShowTtdModal(false)}
                />
            )}

            {/* Project info badge */}
            {project_info && (
                <div style={{display:'inline-flex',alignItems:'center',gap:8,marginBottom:12,padding:'5px 14px',borderRadius:8,
                    background:`${project_info.warna}15`,border:`1px solid ${project_info.warna}30`}}>
                    <div style={{width:8,height:8,borderRadius:'50%',background:project_info.warna}}/>
                    <span style={{fontSize:12,fontWeight:700,color:project_info.warna}}>{project_info.nama}</span>
                    <span style={{fontSize:11,color:'var(--muted)'}}>·</span>
                    <span style={{fontSize:11,color:'var(--muted)'}}>
                        {isMd ? '8 Jam / Hari · OT 5:2' : '7 Jam / Hari · 6:1'}
                    </span>
                </div>
            )}

            {/* TAB SWITCHER */}
            <div style={{display:'flex',gap:4,marginBottom:16}}>
                {[
                    { key:'timesheet',      label:'Timesheet', icon:Calendar },
                    { key:'hari_libur',     label:'Hari Libur', icon:CalendarX2,     count: all_holidays.length },
                    { key:'kelola_anggota', label:'Kelola Anggota', icon:User, count: members.length },
                ].map(t=>(
                    <div key={t.key} onClick={()=>setActiveTab(t.key)}
                        style={{
                            padding:'8px 18px', borderRadius:8, cursor:'pointer',
                            fontSize:13, fontWeight:600, transition:'all .15s', display:'flex', alignItems:'center', gap:8,
                            background: activeTab===t.key ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--card)',
                            color:       activeTab===t.key ? '#0C0F14' : 'var(--muted)',
                            border: `1px solid ${activeTab===t.key ? 'transparent' : 'var(--border)'}`,
                        }}>
                        <t.icon size={14}/> {t.label}
                        {t.count !== undefined && (
                            <span style={{
                                background: activeTab===t.key ? 'rgba(0,0,0,.2)' : 'var(--bg3)',
                                color: activeTab===t.key ? '#0C0F14' : 'var(--muted2)',
                                fontSize:10.5, fontWeight:700, padding:'1px 7px', borderRadius:99,
                            }}>{t.count}</span>
                        )}
                    </div>
                ))}
            </div>

            {/* TAB HARI LIBUR */}
            {activeTab === 'hari_libur' && (
                <TabHariLibur all_holidays={all_holidays} isDark={isDark} isViewer={isViewer} />
            )}

            {/* TAB KELOLA ANGGOTA */}
            {activeTab === 'kelola_anggota' && (
                <TabKelolaAnggota members={members} availableEmployees={available_employees} isViewer={isViewer} project_info={project_info} />
            )}

            {/* TAB TIMESHEET */}
            {activeTab === 'timesheet' && (<>

                {/* Controls */}
                <div style={{display:'flex',alignItems:'center',gap:10,marginBottom:14,flexWrap:'wrap'}}>
                    <select style={inp} value={selBulan}
                        onChange={e=>{setSelBulan(+e.target.value); navigate(selTahun,e.target.value,searchVal);}}>
                        {Object.entries(bulan_list).map(([k,v])=><option key={k} value={k}>{v}</option>)}
                    </select>
                    <select style={inp} value={selTahun}
                        onChange={e=>{setSelTahun(+e.target.value); navigate(e.target.value,selBulan,searchVal);}}>
                        {tahunList.map(y=><option key={y} value={y}>{y}</option>)}
                    </select>
                    <div style={{position:'relative',flex:1,minWidth:180}}>
                        <span style={{position:'absolute',left:9,top:'50%',transform:'translateY(-50%)',color:'var(--muted)',display:'flex'}}><Search size={13}/></span>
                        <input type="text" style={{...inp,width:'100%',paddingLeft:28}}
                            value={searchVal} placeholder="Cari nama / badge..."
                            onChange={e=>{
                                const val = e.target.value;
                                setSearchVal(val);
                                clearTimeout(window._tsSearchTimer);
                                window._tsSearchTimer = setTimeout(() => navigate(selTahun, selBulan, val), 500);
                            }}
                        />
                    </div>
                    {/* Legend */}
                    <div style={{display:'flex',gap:5,alignItems:'center',flexWrap:'wrap'}}>
                        {Object.entries(STATUS).map(([k,v])=>{
                            const t = isDark ? v.dark : v.light;
                            return (
                                <span key={k} style={{fontSize:10.5,padding:'3px 9px',borderRadius:99,background:t.bg,color:t.color,fontWeight:700,border:`1px solid ${t.color}40`}}>
                                    {k} = {v.label}
                                </span>
                            );
                        })}
                        <span style={{fontSize:10.5,padding:'3px 9px',borderRadius:99,background:isDark?'#0D2E1A':'#CCFFCC',color:isDark?'#30D080':'#276221',fontWeight:700}}>Angka = Jam</span>
                        {isMd && <span style={{fontSize:10.5,padding:'3px 9px',borderRadius:99,background:isDark?'rgba(80,60,10,.8)':'#FFF0D0',color:isDark?'#E8A020':'#A06010',fontWeight:700}}>8 Jam Reguler · OT dari Jam ke-9</span>}
                        <span style={{fontSize:10.5,padding:'3px 9px',borderRadius:99,background:isDark?'rgba(58,26,74,.8)':'#F3E8FF',color:isDark?'#C070F0':'#7E22CE',fontWeight:700,display:'inline-flex',alignItems:'center',gap:4}}><Circle size={7} fill="currentColor"/> Libur</span>
                    </div>
                    <button onClick={()=>setShowTtdModal(true)}
                        style={{padding:'6px 14px',borderRadius:7,border:'1px solid rgba(58,143,224,.3)',
                            background:'rgba(58,143,224,.08)',color:'var(--blue)',fontSize:12,fontWeight:700,
                            cursor:'pointer',fontFamily:"'Outfit',sans-serif"}}>
                        <Upload size={13} style={{verticalAlign:-2}}/> Export
                    </button>
                </div>

                {/* Holiday info bar */}
                {holidayDays.length > 0 && (
                    <div style={{display:'flex',alignItems:'center',gap:8,flexWrap:'wrap',marginBottom:10,padding:'8px 14px',borderRadius:8,background:isDark?'rgba(58,26,74,.3)':'#FAF5FF',border:`1px solid ${isDark?'rgba(192,112,240,.2)':'#E9D5FF'}`,fontSize:11.5,color:isDark?'#C070F0':'#7E22CE'}}>
                        <span style={{display:'inline-flex',alignItems:'center',gap:5}}><CalendarX2 size={12}/> Hari libur bulan ini:</span>
                        {holidayDays.map((d,i)=>(
                            <span key={i} style={{background:isDark?'rgba(58,26,74,.5)':'#F3E8FF',padding:'2px 8px',borderRadius:99,fontWeight:600}}>
                                {d.day} — {d.holiday_label}
                            </span>
                        ))}
                    </div>
                )}

                {hasSubGroup && (
                    <div style={{display:'flex',gap:4,marginBottom:10,flexWrap:'wrap'}}>
                        {[
                            { key:'all', label:`Semua (${grid.length})`, icon:null },
                            ...Array.from(new Set(grid.map(e=>e.sub_group).filter(Boolean))).map(sg => {
                                const meta = subGroupMeta(project_info?.kode, sg);
                                return { key: sg, label: `${meta.label} (${grid.filter(e=>e.sub_group===sg).length})`, icon: meta.icon };
                            }),
                        ].map(t => (
                            <div key={t.key} onClick={() => setGridTab(t.key)}
                                style={{
                                    padding:'7px 16px', borderRadius:8, cursor:'pointer',
                                    fontSize:12.5, fontWeight:600, transition:'all .15s',
                                    display:'inline-flex', alignItems:'center', gap:6,
                                    background: gridTab===t.key ? 'linear-gradient(135deg,#E8A020,#A06010)' : 'var(--card)',
                                    color:       gridTab===t.key ? '#0C0F14' : 'var(--muted)',
                                    border: `1px solid ${gridTab===t.key ? 'transparent' : 'var(--border)'}`,
                                }}>
                                {t.icon && <t.icon size={12}/>} {t.label}
                            </div>
                        ))}
                    </div>
                )}

                <div style={{fontSize:11,color:'var(--muted)',marginBottom:10}}>
                    <Lightbulb size={12} style={{verticalAlign:-2}}/> Klik sel untuk edit · <b style={{color:'var(--accent)'}}>Enter/Tab</b> simpan · <b style={{color:'var(--accent)'}}>Esc</b> batal · Ketik angka atau <b>I·S·A·C</b>
                    {isMd && <span style={{marginLeft:8,color:'var(--accent)'}}>· MD: Sabtu = OT dari jam pertama · Minggu = OT 2x</span>}
                </div>

                <div className="panel" style={{overflow:'hidden',border:`2px solid ${borderClr}`}}>
                    <div style={{overflowX:'auto',overflowY:'auto',maxHeight:'calc(100vh - 310px)'}}>
                        <table style={{borderCollapse:'collapse',whiteSpace:'nowrap',fontSize:11}}>
                            <thead style={{position:'sticky',top:0,zIndex:20}}>
                                <tr>
                                    <th className="ts-col-no" style={{...thS(isDark),width:30,minWidth:30,position:'sticky',left:0,zIndex:30,borderRight:`3px solid ${thickBorder}`}}>#</th>
                                    <th className="ts-col-nama" style={{...thS(isDark),width:210,minWidth:210,textAlign:'left',paddingLeft:10,position:'sticky',left:30,zIndex:30,borderRight:`3px solid ${thickBorder}`}}>Nama Karyawan</th>
                                    <th className="ts-col-badge" style={{...thS(isDark),width:110,minWidth:110,position:'sticky',left:240,zIndex:30,borderRight:`3px solid ${thickBorder}`}}>Badge</th>
                                    {days.map(d=>{
                                        let bgH = headerBg, colorH = 'var(--muted)';
                                        if (d.is_sunday) { bgH = sunBg; colorH = isDark?'#E04545':'#B91C1C'; }
                                        else if (d.is_holiday) {
                                            const hc = HOLIDAY_COLORS[d.holiday_tipe||'libur_nasional'] || HOLIDAY_COLORS.libur_nasional;
                                            bgH = isDark ? hc.dark.bg : hc.light.bg;
                                            colorH = isDark ? hc.dark.color : hc.light.color;
                                        } else if (d.is_saturday) { bgH = satBg; colorH = isDark?'#8888BB':'#4338CA'; }
                                        const existingHoliday = holidayMap[d.day];
                                        const canClick = !d.is_sunday;
                                        return (
                                            <th key={d.day}
                                                title={d.is_holiday ? `${d.holiday_label} — klik untuk hapus` : d.is_sunday ? 'Minggu' : 'Klik untuk tandai hari libur'}
                                                onClick={() => canClick && !isViewer && setHolidayClickModal({ day: d.day, existing: existingHoliday || null, isSaturday: d.is_saturday || false })}
                                                style={{
                                                    ...thS(isDark), width:36, minWidth:36,
                                                    background: bgH, color: colorH,
                                                    borderRight: d.is_saturday||d.day===days_in_month ? `2px solid ${thickBorder}` : `1px solid ${borderClr}`,
                                                    borderBottom: `3px solid ${thickBorder}`,
                                                    cursor: canClick ? 'pointer' : 'default',
                                                    userSelect: 'none',
                                                    transition: 'filter .1s',
                                                }}
                                                onMouseEnter={e => { if(canClick) e.currentTarget.style.filter='brightness(1.2)'; }}
                                                onMouseLeave={e => { e.currentTarget.style.filter='none'; }}>
                                                <div style={{fontSize:9,fontWeight:600,lineHeight:1.2}}>{d.day_name}</div>
                                                <div style={{fontSize:11,fontWeight:800}}>{d.day}</div>
                                                {d.is_holiday && !d.is_sunday && <div style={{display:'flex',justifyContent:'center'}}><Circle size={7} fill="currentColor"/></div>}
                                            </th>
                                        );
                                    })}
                                    {sumCols.map((sc,i)=>(
                                        <th key={i} style={{...thS(isDark),minWidth:44,background:sc.bg,color:sc.color,borderLeft:i===0?`3px solid ${thickBorder}`:`1px solid ${borderClr}`,borderBottom:`3px solid ${thickBorder}`,fontWeight:700}}>{sc.label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {activeGrid.map((emp, idx) => {
                                    const curDays = localDays[emp.id] || emp.days;
                                    const sum     = calcSummary(curDays);
                                    const rowBg   = idx%2===0 ? cardBg : cardBg2;
                                    return (
                                        <tr key={emp.id}>
                                            <td className="ts-col-no" style={{...tdS(isDark),textAlign:'center',position:'sticky',left:0,zIndex:10,background:rowBg,color:'var(--muted)',fontSize:10,borderRight:`3px solid ${thickBorder}`}}>{idx+1}</td>
                                            <td className="ts-col-nama" style={{...tdS(isDark),position:'sticky',left:30,zIndex:10,background:rowBg,borderRight:`3px solid ${thickBorder}`,paddingLeft:10}}>
                                                <div style={{fontWeight:600,fontSize:12,overflow:'hidden',textOverflow:'ellipsis',maxWidth:195}}>{emp.nama_lengkap}</div>
                                                <div style={{fontSize:10,color:'var(--muted)'}}>{emp.jabatan}</div>
                                            </td>
                                            <td className="ts-col-badge" style={{...tdS(isDark),position:'sticky',left:240,zIndex:10,background:rowBg,borderRight:`3px solid ${thickBorder}`,fontFamily:'monospace',fontSize:10.5,color:'var(--accent)',textAlign:'center'}}>{emp.id_badge}</td>
                                            {days.map(d=>(
                                                <EditableCell key={d.day}
                                                    employeeId={emp.id} tahun={tahun} bulan={bulan} hari={d.day}
                                                    value={curDays[d.day]}
                                                    isSunday={d.is_sunday}
                                                    isHoliday={d.is_holiday}
                                                    holidayTipe={d.holiday_tipe}
                                                    isDark={isDark}
                                                    isViewer={isViewer}
                                                    onSaved={(hari,nilai)=>handleSaved(emp.id,hari,nilai)}
                                                />
                                            ))}
                                            {sumCols.map((sc,i)=>{
                                                const v = sc.key === 'hadir'        ? sum.hadir
                                                        : sc.key === 'izin'         ? sum.izin
                                                        : sc.key === 'sakit'        ? sum.sakit
                                                        : sc.key === 'alpa'         ? sum.alpa
                                                        : sc.key === 'cuti'         ? sum.cuti
                                                        : sc.key === 'stb'          ? sum.stb
                                                        : sc.key === 'total_ot_jam' ? (emp.total_ot_jam ?? 0)
                                                        : 0;
                                                return (
                                                    <td key={i} style={{...tdS(isDark),textAlign:'center',fontWeight:i===0?700:400,color:sc.color,background:v?sc.bg:rowBg,borderLeft:i===0?`3px solid ${thickBorder}`:`1px solid ${borderClr}`}}>
                                                        {v || ''}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                                {grid.length === 0 && (
                                    <tr>
                                        <td colSpan={days_in_month+sumCols.length+3} style={{padding:32,textAlign:'center',color:'var(--muted)'}}>
                                            Tidak ada data karyawan
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style={{fontSize:11,color:'var(--muted)',marginTop:8}}>
                    {activeGrid.length} karyawan · {BULAN_NAMA[bulan]} {tahun}
                    {gridTab !== 'all' && <span style={{color:'var(--accent)'}}> · {gridTab}</span>}
                    {holidayDays.length > 0 && <span> · {holidayDays.length} hari libur</span>}
                    {isMd && <span style={{marginLeft:8,color:'var(--accent)'}}>· Timesheet 8 Jam (MD)</span>}
                </div>
            </>)}

        </AppLayout>
    );
}

// ── STYLE HELPERS ─────────────────────────────────────────────
function thS(isDark) {
    return {
        padding:'6px 4px', textAlign:'center', fontSize:10, fontWeight:700,
        background: isDark ? 'var(--bg2)' : '#F1F5F9',
        borderBottom: `2px solid ${isDark?'rgba(255,255,255,.15)':'rgba(0,0,0,.15)'}`,
        borderRight: `1px solid ${isDark?'rgba(255,255,255,.08)':'rgba(0,0,0,.08)'}`,
        color:'var(--muted)', userSelect:'none', position:'sticky', top:0,
    };
}

function tdS(isDark) {
    return {
        padding:'4px 4px',
        borderBottom:`1px solid ${isDark?'rgba(255,255,255,.06)':'rgba(0,0,0,.07)'}`,
        borderRight:`1px solid ${isDark?'rgba(255,255,255,.06)':'rgba(0,0,0,.07)'}`,
        fontSize:12, verticalAlign:'middle',
    };
}

function thHoliday(isDark) {
    return {
        padding:'11px 10px',
        background: isDark ? 'var(--bg2)' : '#F1F5F9',
        borderBottom: `2px solid ${isDark?'rgba(255,255,255,.12)':'rgba(0,0,0,.12)'}`,
        color: 'var(--muted)', fontSize:10.5, fontWeight:700,
        textTransform:'uppercase', letterSpacing:'.06em',
        userSelect:'none',
    };
}