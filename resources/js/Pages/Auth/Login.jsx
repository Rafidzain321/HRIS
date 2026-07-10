// resources>js>Pages>Auth>Login.jsx
import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';

export default function Login() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
  });
  const [showPass, setShowPass] = useState(false);

  function submit(e) {
    e.preventDefault();
    post('/login');
  }

  return (
    <div style={{
      minHeight: '100vh',
      background: '#2A3147',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontFamily: "'Outfit', sans-serif",
    }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Outfit:wght@300;400;500;600&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
        .login-card { animation: fadeUp .35s both; }
        .login-input {
          width: 100%;
          background: rgba(255,255,255,0.07);
          border: 1.5px solid rgba(255,255,255,0.15);
          color: #E8ECF5;
          border-radius: 9px;
          padding: 12px 16px;
          font-size: 13.5px;
          font-family: 'Outfit', sans-serif;
          outline: none;
          transition: border .18s, background .18s;
        }
        .login-input:focus {
          border-color: rgba(232,160,32,0.7);
          background: rgba(232,160,32,0.05);
        }
        .login-input::placeholder { color: rgba(255,255,255,0.3); }

        /* Sembunyikan icon mata bawaan browser */
        input[type="password"]::-ms-reveal { display: none !important; }
        input[type="password"]::-ms-clear { display: none !important; }
        input::-webkit-credentials-auto-fill-button { display: none !important; }
        input[type="password"]::-webkit-textfield-decoration-container { display: none !important; }

        .login-btn {
          width: 100%;
          padding: 13px;
          border: none;
          border-radius: 9px;
          background: linear-gradient(135deg, #E8A020, #C07010);
          color: #fff;
          font-size: 14px;
          font-weight: 700;
          font-family: 'Outfit', sans-serif;
          cursor: pointer;
          transition: opacity .18s;
          letter-spacing: .02em;
        }
        .login-btn:hover:not(:disabled) { opacity: .88; }
        .login-btn:disabled { opacity: .55; cursor: not-allowed; }
      `}</style>

      <div className="login-card" style={{
        background: '#1E2436',
        border: '1px solid rgba(255,255,255,0.1)',
        borderRadius: 18,
        padding: '44px 40px',
        width: 'min(420px, calc(100vw - 32px))',
        boxShadow: '0 24px 64px rgba(0,0,0,0.5)',
      }}>

        {/* Logo */}
        <div style={{textAlign:'center', marginBottom:28}}>
          <img
            src="/images/logo-akm.png"
            alt="PT. Andalas Karya Mulia"
            style={{
              height: 90,
              width: 'auto',
              objectFit: 'contain',
              marginBottom: 18,
              filter: 'brightness(1.3) contrast(1.1)',
            }}
          />
          <div style={{
            fontFamily: 'Syne, sans-serif',
            fontSize: 25,
            fontWeight: 700,
            color: '#EAEEF8',
            marginBottom: 4,
          }}>HRIS</div>
          <div style={{
            fontSize: 15.5,
            color: 'rgba(255,255,255,0.45)',
          }}>
            PT. Andalas Karya Mulia
          </div>
        </div>

        {/* Divider */}
        <div style={{height:1, background:'rgba(255,255,255,0.1)', marginBottom:28}}/>

        {/* Form */}
        <form onSubmit={submit} style={{display:'flex', flexDirection:'column', gap:18}}>

          {/* Email */}
          <div>
            <label style={{
              fontSize: 11, color: 'rgba(255,255,255,0.5)',
              marginBottom: 7, display: 'block',
              fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
            }}>Email</label>
            <input
              type="email"
              className="login-input"
              value={data.email}
              onChange={e => setData('email', e.target.value)}
              placeholder="email@akm.com"
              autoComplete="email"
              autoFocus
            />
            {errors.email && (
              <div style={{fontSize:11.5, color:'#E88080', marginTop:5}}>{errors.email}</div>
            )}
          </div>

          {/* Password */}
          <div>
            <label style={{
              fontSize: 11, color: 'rgba(255,255,255,0.5)',
              marginBottom: 7, display: 'block',
              fontWeight: 600, letterSpacing: '.08em', textTransform: 'uppercase',
            }}>Password</label>
            <div style={{position:'relative'}}>
              <input
                type={showPass ? 'text' : 'password'}
                className="login-input"
                value={data.password}
                onChange={e => setData('password', e.target.value)}
                placeholder="••••••••"
                autoComplete="current-password"
                style={{paddingRight: 46}}
              />
              {/* Toggle — satu-satunya icon mata */}
              <div
                onClick={() => setShowPass(!showPass)}
                style={{
                  position:'absolute', right:14, top:'50%',
                  transform:'translateY(-50%)',
                  cursor:'pointer',
                  color: showPass ? 'rgba(232,160,32,0.9)' : 'rgba(255,255,255,0.35)',
                  display:'flex', alignItems:'center',
                  transition:'color .15s',
                  userSelect:'none',
                }}
                onMouseEnter={e=>e.currentTarget.style.color='rgba(232,160,32,0.8)'}
                onMouseLeave={e=>e.currentTarget.style.color=showPass?'rgba(232,160,32,0.9)':'rgba(255,255,255,0.35)'}
              >
                {showPass ? (
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                ) : (
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                )}
              </div>
            </div>
            {errors.password && (
              <div style={{fontSize:11.5, color:'#E88080', marginTop:5}}>{errors.password}</div>
            )}
          </div>

          <button type="submit" className="login-btn" disabled={processing} style={{marginTop:4}}>
            {processing ? 'Loading...' : 'Login'}
          </button>

        </form>

        <div style={{
          marginTop:28, textAlign:'center',
          fontSize: 10.5, color: 'rgba(255,255,255,0.2)',
          letterSpacing: '.02em',
        }}>
          HRIS AKM v1.0 · PT. Andalas Karya Mulia
        </div>
      </div>
    </div>
  );
}