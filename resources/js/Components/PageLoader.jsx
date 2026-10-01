import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

// Indikator pindah halaman (Inertia): bar progress di atas + kapsul persentase.
// Progress server tidak bisa diukur, jadi naik bertahap sampai ~90% lalu ke 100% saat halaman selesai dimuat.
// Muncul hanya kalau loading > 150ms supaya navigasi cepat tidak berkedip.
export default function PageLoader() {
  const [visible, setVisible] = useState(false);
  const [progress, setProgress] = useState(0);
  const timers = useRef({});

  useEffect(() => {
    const clear = () => {
      clearTimeout(timers.current.delay);
      clearInterval(timers.current.trickle);
      clearTimeout(timers.current.hide);
    };

    const offStart = router.on('start', (event) => {
      // Reload parsial (router.reload({ only: [...] }), mis. refresh data setelah simpan) tidak perlu indikator.
      if (event.detail.visit.only?.length) return;
      clear();
      setProgress(0);
      timers.current.delay = setTimeout(() => {
        setVisible(true);
        setProgress(8);
        timers.current.trickle = setInterval(() => {
          setProgress(p => (p >= 90 ? p : p + Math.max(1, (90 - p) * 0.08)));
        }, 120);
      }, 150);
    });

    const offProgress = router.on('progress', (event) => {
      const pct = event.detail.progress?.percentage;
      if (pct != null) setProgress(p => Math.max(p, Math.min(95, pct)));
    });

    const offFinish = router.on('finish', () => {
      clear();
      setProgress(p => (p > 0 ? 100 : 0));
      timers.current.hide = setTimeout(() => { setVisible(false); setProgress(0); }, 350);
    });

    return () => { clear(); offStart(); offProgress(); offFinish(); };
  }, []);

  if (!visible) return null;

  const pct = Math.round(progress);
  return (
    <>
      <style>{`
        @keyframes pl-spin { to { transform: rotate(360deg); } }
        @keyframes pl-shine { 0% { transform: translateX(-100%); } 100% { transform: translateX(300%); } }
      `}</style>
      <div style={{ position: 'fixed', top: 0, left: 0, right: 0, height: 3, zIndex: 9999, background: 'rgba(0,0,0,.06)' }}>
        <div style={{
          height: '100%', width: `${progress}%`, position: 'relative', overflow: 'hidden',
          background: 'linear-gradient(90deg, var(--accent, #E8A020), #F5C451)',
          boxShadow: '0 0 10px var(--accent, #E8A020)', transition: 'width .25s ease',
        }}>
          <div style={{ position: 'absolute', inset: 0, width: '30%', background: 'linear-gradient(90deg, transparent, rgba(255,255,255,.6), transparent)', animation: 'pl-shine 1.1s linear infinite' }} />
        </div>
      </div>
      <div style={{
        position: 'fixed', top: 14, left: '50%', transform: 'translateX(-50%)', zIndex: 9999,
        display: 'flex', alignItems: 'center', gap: 8, padding: '6px 14px 6px 10px', borderRadius: 99,
        background: 'var(--card, #fff)', border: '1px solid var(--border, #E2E7F0)',
        boxShadow: '0 6px 24px rgba(0,0,0,.15)', fontFamily: "'Outfit',sans-serif",
        fontSize: 12, fontWeight: 600, color: 'var(--text, #1A1A2E)', pointerEvents: 'none',
      }}>
        <span style={{
          width: 14, height: 14, borderRadius: '50%', border: '2px solid var(--bg3, #E2E7F0)',
          borderTopColor: 'var(--accent, #E8A020)', animation: 'pl-spin .7s linear infinite',
        }} />
        Memuat halaman… <span style={{ color: 'var(--accent, #E8A020)', minWidth: 32, textAlign: 'right' }}>{pct}%</span>
      </div>
    </>
  );
}
