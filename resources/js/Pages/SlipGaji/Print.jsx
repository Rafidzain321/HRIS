// resources/js/Pages/SlipGaji/Print.jsx
import React from 'react';
import { router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { SlipCetak } from '@/Pages/Timesheet/SlipGaji';

const BULAN_NAMA = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export default function SlipGajiPrint({ slip, periode }) {
  if (!slip) {
    return <div style={{ padding: 40, fontFamily: "'Outfit',sans-serif" }}>Data payroll tidak ditemukan.</div>;
  }

  const bulanNama = BULAN_NAMA[periode?.bulan] || '';
  const periodeLabel = `${bulanNama} ${periode?.tahun || ''}`;

  const ttdDisplay = [
    ...(slip.ttd_list?.length ? slip.ttd_list : [
      { label: 'Disetujui Oleh,', name: 'H. Syahrul Akmal', jabatan: 'Direktur Utama' },
      { label: 'Dibayar Oleh,', name: 'Yulhamdani', jabatan: 'Finance' },
    ]),
    { label: 'Diterima Oleh,', name: slip.nama_lengkap || '', jabatan: slip.jabatan || '' },
  ];

  function handlePrintPDF() { window.print(); }
  function handleExcelDownload() {
    window.location.href = `/slip-gaji/${slip.id}/export-excel`;
  }

  return (
    <div style={{ fontFamily: "'Outfit', sans-serif" }}>
      <style>{`
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #E5E7EB; }

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

        .paper-wrapper {
          margin-top: 56px; display: flex; justify-content: center;
          padding: 30px 16px 50px;
          overflow-x: auto;
        }
        @media (max-width: 640px) {
          .paper-wrapper { margin-top: 90px; padding: 16px 8px 40px; }
        }
        .slip-paper {
          background: #fff;
          box-shadow: 0 4px 24px rgba(0,0,0,.2);
        }

        @media print {
          @page { size: A5 portrait; margin: 0; }
          body { background: white; }
          .control-bar { display: none !important; }
          .paper-wrapper { margin-top: 0; padding: 0; display: block; }
          .slip-paper { box-shadow: none; }
        }
      `}</style>

      {/* CONTROL BAR */}
      <div className="control-bar">
        <button className="btn-ctrl btn-back" onClick={() => router.visit('/timesheet/slip-gaji')}>
          ← Kembali
        </button>
        <div className="title">
          Slip Gaji — {slip.nama_lengkap} · {periodeLabel}
        </div>
        <button className="btn-ctrl btn-excel" onClick={handleExcelDownload}>
          <Download size={13} /> Excel
        </button>
        <button className="btn-ctrl btn-pdf" onClick={handlePrintPDF}>
          <Printer size={13} /> Print / PDF
        </button>
      </div>

      {/* PAPER — pakai layout SlipCetak yang sama dengan halaman Slip Gaji (satu sumber, selalu sinkron) */}
      <div className="paper-wrapper">
        <div className="slip-paper">
          <SlipCetak slip={slip} bulan_nama={bulanNama} tahun={periode?.tahun} ttd={ttdDisplay} />
        </div>
      </div>
    </div>
  );
}
