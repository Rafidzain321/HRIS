// resources/js/Pages/SlipGaji/Print.jsx
import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';

function fmt(n) {
  if (!n && n !== 0) return '—';
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}
function fmtNum(n) {
  if (!n && n !== 0) return '—';
  return Number(n).toLocaleString('id-ID');
}

export default function SlipGajiPrint({ payroll, employee, periode }) {
  const [showRincianJam, setShowRincianJam] = useState(false);

  const totalPerolehan =
    (payroll.gaji_pokok        || 0) +
    (payroll.tunj_jabatan      || 0);

  const totalTTT =
    (payroll.tunj_lapangan     || 0) +
    (payroll.tunj_transport    || 0) +
    (payroll.uang_makan        || 0) +
    (payroll.insentif          || 0) +
    (payroll.tunj_perumahan    || 0) +
    (payroll.tunj_hp           || 0) +
    (payroll.tunj_special      || 0) +
    (payroll.tunj_produksi     || 0) +
    (payroll.kenaikan          || 0);

  const totalLainLain =
    (payroll.lembur_biasa      || 0) +
    (payroll.lembur_libur      || 0) +
    (payroll.lain_lain         || 0);

  const gajiSebulan  = totalPerolehan + totalTTT + totalLainLain;
  const potJabatan   = payroll.pot_jabatan_pct
    ? Math.round(gajiSebulan * (payroll.pot_jabatan_pct / 100))
    : 0;
  const penghasilanKotor = gajiSebulan - potJabatan;

  const totalPotongan =
    (payroll.bpjs_jht          || 0) +
    (payroll.pph21             || 0) +
    (payroll.bpjs_kesehatan    || 0) +
    (payroll.bpjs_jp           || 0);

  const penghasilanBersih   = penghasilanKotor - totalPotongan;
  const totalPotPinjaman    = payroll.pot_pinjaman || 0;
  const netto = penghasilanBersih
    + (payroll.pph_ditanggung || 0)
    + (payroll.pembayaran_jabatan || 0)
    - totalPotPinjaman;

  function handlePrintPDF() { window.print(); }
  function handleExcelDownload() {
    window.location.href = `/slip-gaji/${payroll.id}/export-excel`;
  }

  const bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'];
  const periodeLabel = `${bulanNama[periode?.bulan] || ''} ${periode?.tahun || ''}`;

  return (
    <div style={{ fontFamily: "'Arial', 'Calibri', sans-serif", fontSize: 11 }}>

      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #E5E7EB; font-family: Arial, sans-serif; font-size: 11px; }

        .control-bar {
          position: fixed; top: 0; left: 0; right: 0; z-index: 100;
          background: #1E2436; padding: 10px 14px;
          display: flex; align-items: center; gap: 8px;
          box-shadow: 0 2px 8px rgba(0,0,0,.4);
          flex-wrap: wrap;
        }
        @media (max-width: 640px) {
          .control-bar { padding: 8px 12px; }
          .control-bar .title { width: 100%; order: -1; margin-bottom: 4px; font-size: 12px; }
          .toggle-jam { font-size: 10.5px; }
          .btn-ctrl { padding: 6px 12px; font-size: 11px; }
        }
        .control-bar .title {
          color: #E8ECF8; font-family: 'Outfit', sans-serif;
          font-size: 13px; font-weight: 600; flex: 1;
        }
        .btn-ctrl {
          padding: 7px 16px; border-radius: 7px; border: none;
          font-size: 12px; font-weight: 600; cursor: pointer;
          font-family: 'Outfit', sans-serif;
          display: flex; align-items: center; gap: 6px;
          transition: opacity .15s;
        }
        .btn-ctrl:hover { opacity: .85; }
        .btn-pdf   { background: linear-gradient(135deg,#E8A020,#C07010); color:#fff; }
        .btn-excel { background: linear-gradient(135deg,#22C97A,#148050); color:#fff; }
        .btn-back  { background: rgba(255,255,255,.1); color: #B0B8D0; border: 1px solid rgba(255,255,255,.12); }
        .toggle-jam {
          display: flex; align-items: center; gap: 7px;
          color: #B0B8D0; font-size: 11.5px;
          font-family: 'Outfit', sans-serif;
          cursor: pointer; user-select: none; margin-left: 6px;
        }
        .toggle-jam input { cursor: pointer; accent-color: #E8A020; width: 15px; height: 15px; }

        .paper-wrapper {
          margin-top: 56px; display: flex; justify-content: center;
          padding: 30px 16px 50px;
          overflow-x: auto;
        }
        @media (max-width: 640px) {
          .paper-wrapper { margin-top: 90px; padding: 16px 8px 40px; }
        }

        /* A5 = 148mm x 210mm */
        .slip-paper {
          width: 148mm;
          min-height: 210mm;
          background: #fff;
          box-shadow: 0 4px 24px rgba(0,0,0,.2);
          padding: 8mm 10mm 8mm 10mm;
          position: relative;
        }
        @media (max-width: 480px) {
          .slip-paper { transform: scale(0.85); transform-origin: top center; }
        }

        .slip-header {
          border-bottom: 2px solid #000;
          padding-bottom: 4px; margin-bottom: 5px;
          display: flex; align-items: center; gap: 8px;
        }
        .slip-header img { height: 30px; width: auto; object-fit: contain; }
        .slip-header-text h1 {
          font-size: 10px; font-weight: 700;
          letter-spacing: .04em; text-transform: uppercase;
        }
        .slip-header-text p { font-size: 7px; color: #555; line-height: 1.3; }
        .slip-title-right { margin-left: auto; text-align: right; }
        .slip-title-right .slip-label {
          font-size: 13px; font-weight: 700; letter-spacing: .05em;
          border: 2px solid #000; padding: 2px 10px; display: inline-block;
        }
        .slip-title-right .periode { font-size: 8px; color: #555; margin-top: 2px; }

        .identitas-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 9px; }
        .identitas-table td { padding: 1px 2px; vertical-align: top; }
        .identitas-table .lbl { width: 28mm; color: #333; font-weight: 500; }
        .identitas-table .sep { width: 3mm; text-align: center; }
        .identitas-table .val { font-weight: 600; }
        .identitas-netto { text-align: right; }
        .identitas-netto .netto-label { font-size: 8px; color: #555; text-transform: uppercase; letter-spacing: .06em; }
        .identitas-netto .netto-value { font-size: 14px; font-weight: 700; color: #000; letter-spacing: .01em; }

        .divider { border: none; border-top: 1px solid #ccc; margin: 4px 0; }
        .divider-bold { border: none; border-top: 2px solid #000; margin: 4px 0; }

        .gaji-table { width: 100%; border-collapse: collapse; font-size: 9px; }
        .gaji-table td { padding: 1.2px 2px; vertical-align: middle; }
        .gaji-table .section-header td { font-weight: 700; font-size: 9px; padding-top: 4px; padding-bottom: 1px; }
        .gaji-table .total-row td { font-weight: 700; border-top: 1px solid #999; padding-top: 2px; }
        .gaji-table .bold-row td {
          font-weight: 700; border-top: 2px solid #000;
          border-bottom: 2px solid #000; padding: 2px 2px;
        }
        .col-no   { width: 5mm; color: #666; }
        .col-sep  { width: 4mm; text-align: center; color: #666; }
        .col-val  { width: 22mm; text-align: right; }
        .col-eq   { width: 4mm; text-align: center; color: #666; }
        .col-total{ width: 22mm; text-align: right; font-weight: 500; }
        .muted    { color: #777; }
        .text-right { text-align: right; }

        .rincian-jam-table {
          width: 100%; border-collapse: collapse; font-size: 8.5px; margin-top: 3px;
        }
        .rincian-jam-table th {
          background: #f0f0f0; border: 1px solid #ccc;
          padding: 2px 4px; text-align: center; font-weight: 600;
        }
        .rincian-jam-table td { border: 1px solid #ddd; padding: 1.5px 4px; text-align: center; }

        .ttd-section {
          display: grid; grid-template-columns: 1fr 1fr 1fr;
          gap: 4px; margin-top: 8px;
        }
        .ttd-box { text-align: center; font-size: 8px; }
        .ttd-box .ttd-label { font-weight: 600; margin-bottom: 18px; }
        .ttd-box .ttd-name { border-top: 1px solid #999; padding-top: 2px; font-weight: 700; }
        .ttd-box .ttd-role { color: #555; }

        /* ── PRINT MODE — paksa A5 ── */
        @media print {
          @page {
            size: A5 portrait;
            margin: 0;
          }
          body { background: white; }
          .control-bar { display: none !important; }
          .paper-wrapper { margin-top: 0; padding: 0; display: block; }
          .slip-paper {
            box-shadow: none;
            width: 100%;
            min-height: 100vh;
            padding: 8mm 10mm;
          }
        }
      `}</style>

      {/* CONTROL BAR */}
      <div className="control-bar">
        <button className="btn-ctrl btn-back" onClick={() => router.visit('/slip-gaji')}>
          ← Kembali
        </button>
        <div className="title">
          Slip Gaji — {employee?.nama_lengkap} · {periodeLabel}
        </div>
        <label className="toggle-jam">
          <input
            type="checkbox"
            checked={showRincianJam}
            onChange={e => setShowRincianJam(e.target.checked)}
          />
          Tampilkan Rincian Jam
        </label>
        <button className="btn-ctrl btn-excel" onClick={handleExcelDownload}>
          <Download size={13}/> Excel
        </button>
        <button className="btn-ctrl btn-pdf" onClick={handlePrintPDF}>
          <Printer size={13}/> Print / PDF
        </button>
      </div>

      {/* PAPER */}
      <div className="paper-wrapper">
        <div className="slip-paper">

          {/* HEADER */}
          <div className="slip-header">
            <img src="/images/logo-akm.png" alt="AKM" />
            <div className="slip-header-text">
              <h1>PT. Andalas Karya Mulia</h1>
              <p>Jl. Wonosari, Komplek Wonosari Regency Blok B No.1</p>
              <p>Tangkerang Selatan, Pekanbaru – Riau</p>
            </div>
            <div className="slip-title-right">
              <div className="slip-label">SLIP GAJI</div>
              <div className="periode">{periodeLabel}</div>
            </div>
          </div>

          {/* IDENTITAS + NETTO */}
          <table className="identitas-table">
            <tbody>
              <tr>
                <td style={{verticalAlign:'top', width:'60%'}}>
                  <table className="identitas-table" style={{marginBottom:0}}>
                    <tbody>
                      {[
                        ['No. Register',    employee?.id_badge],
                        ['Nama Karyawan',   employee?.nama_lengkap],
                        ['Jabatan',         employee?.jabatan],
                        ['Daerah Operasi',  employee?.daerah_operasi || employee?.project_nama || '—'],
                        ['No. Rekening',    employee?.no_rekening || '—'],
                        ['Mulai Bergabung', employee?.tanggal_masuk || '—'],
                      ].map(([lbl, val]) => (
                        <tr key={lbl}>
                          <td className="lbl">{lbl}</td>
                          <td className="sep">:</td>
                          <td className="val">{val || '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </td>
                <td style={{verticalAlign:'middle', textAlign:'right', paddingLeft:6}}>
                  <div className="identitas-netto">
                    <div className="netto-label">Netto Diterima</div>
                    <div className="netto-value">{fmt(netto)}</div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <hr className="divider-bold" />

          {/* TABEL GAJI */}
          <table className="gaji-table">
            <tbody>

              {/* A. PEROLEHAN */}
              <tr className="section-header">
                <td className="col-no">A.</td>
                <td colSpan={4} style={{fontWeight:700}}>PEROLEHAN</td>
              </tr>
              <tr>
                <td className="col-no muted">1.</td>
                <td className="col-name">Gaji Pokok / Upah</td>
                <td className="col-sep">:</td>
                <td className="col-val">{fmtNum(payroll.gaji_pokok)}</td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.gaji_pokok)}</td>
              </tr>
              <tr>
                <td className="col-no muted">2.</td>
                <td className="col-name">Tunjangan Tetap</td>
                <td className="col-sep"></td>
                <td className="col-val"></td>
                <td className="col-eq"></td>
                <td className="col-total"></td>
              </tr>
              <tr>
                <td className="col-no"></td>
                <td className="col-name" style={{paddingLeft:4}}>— Tunj. Jabatan</td>
                <td className="col-sep">:</td>
                <td className="col-val">{fmtNum(payroll.tunj_jabatan)}</td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.tunj_jabatan)}</td>
              </tr>

              {/* B. TTT */}
              <tr className="section-header">
                <td className="col-no">B.</td>
                <td colSpan={5} style={{fontWeight:700}}>TUNJANGAN TIDAK TETAP</td>
              </tr>
              {[
                ['1.', 'T. Lapangan',    payroll.tunj_lapangan],
                ['2.', 'T. Transport',   payroll.tunj_transport],
                ['3.', 'U. Makan',       payroll.uang_makan],
                ['4.', 'Insentif',       payroll.insentif],
                ['5.', 'T. Perumahan',   payroll.tunj_perumahan],
                ['6.', 'T. HP',          payroll.tunj_hp],
                ['7.', 'T. Special',     payroll.tunj_special],
                ['8.', 'T. Produksi',    payroll.tunj_produksi],
                ['9.', 'Kenaikan',       payroll.kenaikan],
              ].filter(([,, v]) => v).map(([no, nama, val]) => (
                <tr key={nama}>
                  <td className="col-no muted">{no}</td>
                  <td className="col-name">{nama}</td>
                  <td className="col-sep">:</td>
                  <td className="col-val">{fmtNum(val)}</td>
                  <td className="col-eq">=</td>
                  <td className="col-total">{fmtNum(val)}</td>
                </tr>
              ))}

              {/* C. LAIN-LAIN */}
              <tr className="section-header">
                <td className="col-no">C.</td>
                <td colSpan={5} style={{fontWeight:700}}>LAIN-LAIN</td>
              </tr>
              <tr>
                <td className="col-no muted">1.</td>
                <td className="col-name">Lembur Hari Biasa</td>
                <td className="col-sep">:</td>
                <td className="col-val">{fmtNum(payroll.lembur_biasa)}</td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.lembur_biasa)}</td>
              </tr>
              <tr>
                <td className="col-no muted">2.</td>
                <td className="col-name">Lembur Libur Nasional/Minggu</td>
                <td className="col-sep">:</td>
                <td className="col-val">{fmtNum(payroll.lembur_libur)}</td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.lembur_libur)}</td>
              </tr>
              {payroll.lain_lain > 0 && (
                <tr>
                  <td className="col-no muted">3.</td>
                  <td className="col-name">Lain-Lain</td>
                  <td className="col-sep">:</td>
                  <td className="col-val">{fmtNum(payroll.lain_lain)}</td>
                  <td className="col-eq">=</td>
                  <td className="col-total">{fmtNum(payroll.lain_lain)}</td>
                </tr>
              )}

              {/* Rincian jam (collapsible) */}
              {showRincianJam && payroll.rincian_jam && (
                <tr>
                  <td colSpan={6} style={{paddingTop:4}}>
                    <div style={{fontSize:8,fontWeight:700,marginBottom:2,color:'#555'}}>
                      Rincian Jam Kerja &amp; Lembur:
                    </div>
                    <table className="rincian-jam-table">
                      <thead>
                        <tr>
                          <th>Hari</th><th>H. Kerja</th><th>H. Sabtu</th>
                          <th>H. Minggu/Libur</th><th>Total OT 1.5x</th><th>Total OT 2x</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Jumlah</td>
                          <td>{payroll.rincian_jam.h_kerja || 0}</td>
                          <td>{payroll.rincian_jam.h_sabtu || 0}</td>
                          <td>{payroll.rincian_jam.h_minggu_libur || 0}</td>
                          <td>{payroll.rincian_jam.total_jam_ot_15x || 0} jam</td>
                          <td>{payroll.rincian_jam.total_jam_ot_2x || 0} jam</td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              )}

              {/* GAJI SEBULAN */}
              <tr className="bold-row">
                <td className="col-no"></td>
                <td colSpan={4} style={{fontWeight:700}}>GAJI SEBULAN</td>
                <td className="col-total" style={{fontWeight:700}}>{fmtNum(gajiSebulan)}</td>
              </tr>

              {/* D. POTONGAN JABATAN */}
              <tr className="section-header">
                <td className="col-no">D.</td>
                <td className="col-name">POTONGAN JABATAN</td>
                <td className="col-sep">:</td>
                <td className="col-val">{fmtNum(gajiSebulan)} × {payroll.pot_jabatan_pct || 0}%</td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(potJabatan)}</td>
              </tr>

              {/* PENGHASILAN KOTOR */}
              <tr className="total-row">
                <td className="col-no"></td>
                <td colSpan={4} style={{fontWeight:700}}>PENGHASILAN KOTOR SEBULAN</td>
                <td className="col-total" style={{fontWeight:700}}>{fmtNum(penghasilanKotor)}</td>
              </tr>

              {/* E. POTONGAN WAJIB */}
              <tr className="section-header">
                <td className="col-no">E.</td>
                <td colSpan={5} style={{fontWeight:700}}>POTONGAN WAJIB</td>
              </tr>
              {[
                ['1.', `PTKP — ${employee?.ptkp || '—'}`, null],
                ['2.', 'BPJS TK : JHT (2%)',     payroll.bpjs_jht],
                ['3.', 'PPh 21 Sebulan',           payroll.pph21],
                ['4.', 'BPJS Kesehatan (1%)',      payroll.bpjs_kesehatan],
                ['5.', 'BPJS JP (1%)',             payroll.bpjs_jp],
              ].map(([no, nama, val]) => (
                <tr key={nama}>
                  <td className="col-no muted">{no}</td>
                  <td className="col-name">{nama}</td>
                  <td className="col-sep">{val !== null ? ':' : ''}</td>
                  <td className="col-val"></td>
                  <td className="col-eq">{val !== null ? '=' : ''}</td>
                  <td className="col-total">{val !== null ? fmtNum(val) : ''}</td>
                </tr>
              ))}
              <tr className="total-row">
                <td></td><td colSpan={4}>TOTAL POTONGAN SEBULAN</td>
                <td className="col-total">{fmtNum(totalPotongan)}</td>
              </tr>
              <tr className="total-row">
                <td></td><td colSpan={4}>PENGHASILAN BERSIH SETELAH POT. WAJIB</td>
                <td className="col-total">{fmtNum(penghasilanBersih)}</td>
              </tr>

              {/* F. PINJAMAN */}
              <tr className="section-header">
                <td className="col-no">F.</td>
                <td colSpan={5} style={{fontWeight:700}}>PINJAMAN BULAN INI</td>
              </tr>
              <tr>
                <td className="col-no muted">1.</td><td>Jumlah Pinjaman</td>
                <td></td><td className="col-val">{fmtNum(payroll.jumlah_pinjaman)}</td>
                <td></td><td></td>
              </tr>
              <tr>
                <td className="col-no muted">2.</td><td>Pot / Bulan</td>
                <td></td><td className="col-val">{fmtNum(payroll.pot_pinjaman)}</td>
                <td></td><td></td>
              </tr>
              <tr>
                <td className="col-no muted">3.</td><td>Pot. Ke</td>
                <td></td><td className="col-val">{payroll.pot_pinjaman_ke || '—'}</td>
                <td></td><td></td>
              </tr>
              <tr>
                <td className="col-no muted">4.</td><td>Sisa Pinjaman</td>
                <td></td><td className="col-val">{fmtNum(payroll.sisa_pinjaman)}</td>
                <td></td><td></td>
              </tr>
              <tr className="total-row">
                <td></td><td colSpan={4}>TOTAL POT. PINJAMAN SEBULAN</td>
                <td className="col-total">{fmtNum(totalPotPinjaman)}</td>
              </tr>

              {/* G. PPh Ditanggung Pemerintah */}
              <tr>
                <td className="col-no" style={{fontWeight:700}}>G.</td>
                <td style={{fontWeight:700}}>PPh DITANGGUNG PEMERINTAH</td>
                <td className="col-sep"></td><td></td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.pph_ditanggung || 0)}</td>
              </tr>

              {/* H. Pembayaran Jabatan */}
              <tr>
                <td className="col-no" style={{fontWeight:700}}>H.</td>
                <td style={{fontWeight:700}}>PEMBAYARAN JABATAN</td>
                <td className="col-sep"></td><td></td>
                <td className="col-eq">=</td>
                <td className="col-total">{fmtNum(payroll.pembayaran_jabatan || 0)}</td>
              </tr>

              {/* NETTO */}
              <tr className="bold-row">
                <td></td>
                <td colSpan={4} style={{fontWeight:700,fontSize:10}}>
                  PENGHASILAN BERSIH (NETTO) SETELAH POT. PINJAMAN
                </td>
                <td className="col-total" style={{fontWeight:700,fontSize:10}}>
                  {fmtNum(netto)}
                </td>
              </tr>

            </tbody>
          </table>

          <hr className="divider" style={{marginTop:6}} />

          {/* TANDA TANGAN */}
          <div style={{fontSize:8, marginBottom:4, color:'#555'}}>
            Pekanbaru, {new Date().toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'})}
          </div>
          <div className="ttd-section">
            {[
              { label:'Disetujui Oleh,', name:'H.Syahrul Akmal', role:'Direktur Utama' },
              { label:'Dibayar Oleh,',   name:'Yulhamdani',      role:'Finance' },
              { label:'Diterima Oleh,',  name: employee?.nama_lengkap, role: employee?.jabatan },
            ].map((t,i) => (
              <div key={i} className="ttd-box">
                <div className="ttd-label">{t.label}</div>
                <div className="ttd-name">{t.name}</div>
                <div className="ttd-role">{t.role}</div>
              </div>
            ))}
          </div>

          {/* No. Rekening — ganti Note section */}
          <div style={{
            marginTop:6, fontSize:8, borderTop:'1px dashed #ccc', paddingTop:4,
            display:'flex', alignItems:'center', gap:6,
          }}>
            <span style={{fontWeight:700, color:'#333'}}>No. Rekening:</span>
            <span style={{color:'#555'}}>{employee?.no_rekening || '—'}</span>
          </div>

        </div>
      </div>
    </div>
  );
}