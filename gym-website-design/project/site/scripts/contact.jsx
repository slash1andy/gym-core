// Contact page — single-step form with location preference and confirmation state.

function ContactPage() {
  const [submitted, setSubmitted] = React.useState(false);
  const [location, setLocation] = React.useState('rockford');
  const [info, setInfo] = React.useState({ name: '', email: '', phone: '', notes: '' });

  const onSubmit = e => {
    e.preventDefault();
    setSubmitted(true);
  };

  return (
    <PageShell current="contact">

      {/* Hero — mirrors free-trial hero exactly */}
      <section style={{ padding: '64px 0 32px', background: '#EFEBE1', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
        <div className="hp-container-wide">
          <div className="hp-eyebrow-mono" style={{ color: '#1A2DC4', marginBottom: 24 }}>
            <a href="index.html" style={{ color: 'inherit' }}>HMA</a>
            <span style={{ color: '#9A9A98' }}> / Contact</span>
          </div>
          <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 48, alignItems: 'end' }}>
            <h1 className="hp-display-xl">Get in touch.<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#1A2DC4' }}>We respond fast.</em></h1>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 12, color: '#181816' }}>
              {['Same-day response', 'Two locations to choose from', 'No sales pitch — just answers', 'A coach makes first contact'].map(b => (
                <li key={b} style={{ display: 'flex', gap: 12, alignItems: 'center', fontSize: 16 }}>
                  <span style={{ color: '#1A2DC4' }}><HPIcon.Check size={16} /></span>{b}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* Form + location cards */}
      <section className="hp-section" style={{ padding: '80px 0 120px' }}>
        <div className="hp-container-wide" style={{ maxWidth: 1080 }}>
          {!submitted ? (
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 64, alignItems: 'start' }}>

              {/* Left: form */}
              <form onSubmit={onSubmit}>
                {/* Honeypot — matches free-trial pattern */}
                <input type="text" name="company" tabIndex={-1} autoComplete="off" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }} aria-hidden="true" />

                <h2 className="hp-display-md" style={{ marginBottom: 40 }}>Send us a message.</h2>

                {/* Location toggle — mirrors step-2 location toggle from free-trial */}
                <div style={{ marginBottom: 32 }}>
                  <div className="hp-meta" style={{ marginBottom: 12 }}>Location</div>
                  <div style={{ display: 'flex', gap: 0, border: '1px solid rgba(10,10,10,0.18)', borderRadius: 2, width: 'fit-content' }}>
                    {['rockford', 'beloit'].map(l => (
                      <button key={l} type="button" onClick={() => setLocation(l)} style={{
                        padding: '12px 24px', border: 'none', cursor: 'pointer',
                        background: location === l ? '#0A0A0A' : 'transparent',
                        color: location === l ? '#F6F4EE' : '#181816',
                        fontFamily: 'inherit', fontSize: 14, fontWeight: 600,
                        textTransform: 'capitalize',
                      }}>{l}</button>
                    ))}
                  </div>
                </div>

                <div style={{ display: 'grid', gap: 20 }}>
                  <label>
                    <div className="hp-meta" style={{ marginBottom: 8 }}>Your name</div>
                    <input className="hp-input" required value={info.name} onChange={e => setInfo({ ...info, name: e.target.value })} placeholder="Alex Garcia" />
                  </label>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                    <label>
                      <div className="hp-meta" style={{ marginBottom: 8 }}>Email</div>
                      <input className="hp-input" required type="email" value={info.email} onChange={e => setInfo({ ...info, email: e.target.value })} placeholder="alex@example.com" />
                    </label>
                    <label>
                      <div className="hp-meta" style={{ marginBottom: 8 }}>Phone <span style={{ color: '#9A9A98', fontFamily: 'inherit' }}>(optional)</span></div>
                      <input className="hp-input" type="tel" value={info.phone} onChange={e => setInfo({ ...info, phone: e.target.value })} placeholder="(815) 000-0000" />
                    </label>
                  </div>
                  <label>
                    <div className="hp-meta" style={{ marginBottom: 8 }}>Message</div>
                    <textarea className="hp-input" required rows={5} value={info.notes} onChange={e => setInfo({ ...info, notes: e.target.value })} placeholder="What are you curious about?" style={{ resize: 'vertical' }} />
                  </label>
                </div>

                <div style={{ marginTop: 32, display: 'flex', justifyContent: 'flex-end' }}>
                  <button type="submit" className="hp-btn hp-btn-primary hp-btn-lg">Send message <HPIcon.Arrow size={16} /></button>
                </div>
              </form>

              {/* Right: location detail cards */}
              <div>
                {HP_DATA.locations.map(loc => (
                  <div key={loc.id} style={{
                    padding: 28, marginBottom: 16,
                    background: location === loc.id ? 'rgba(26,45,196,0.04)' : '#fff',
                    border: '1px solid ' + (location === loc.id ? '#1A2DC4' : 'rgba(10,10,10,0.12)'),
                    borderRadius: 2, transition: 'border-color 160ms',
                  }}>
                    <div className="hp-meta" style={{ color: loc.primary ? '#1A2DC4' : '#4A4A48', marginBottom: 8 }}>
                      {loc.primary ? '★ Headquarters' : 'Satellite location'}
                    </div>
                    <div className="hp-h-md" style={{ marginBottom: 12 }}>{loc.city}</div>
                    <div style={{ display: 'grid', gap: 4, marginBottom: 20 }}>
                      <span className="hp-body-sm">{loc.addr}</span>
                      <span className="hp-body-sm" style={{ color: '#4A4A48' }}>{loc.zip}</span>
                      <a href={`tel:${loc.phone}`} style={{ fontFamily: 'Menlo, monospace', fontSize: 13, color: '#1A2DC4', fontWeight: 600, marginTop: 4 }}>{loc.phone}</a>
                    </div>
                    <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                      <a href="free-trial.html" className="hp-btn hp-btn-primary" style={{ fontSize: 13, padding: '10px 16px' }}>Book free trial <HPIcon.Arrow size={12} /></a>
                      <a href="schedule.html" className="hp-btn hp-btn-ghost" style={{ fontSize: 13, padding: '10px 16px' }}>See schedule</a>
                    </div>
                  </div>
                ))}
                <div style={{ padding: 20, background: '#EFEBE1', borderLeft: '3px solid #1A2DC4' }}>
                  <div className="hp-meta" style={{ marginBottom: 8 }}>Prefer to call?</div>
                  <a href={`tel:${HP_DATA.phone}`} style={{ fontFamily: 'Menlo, monospace', fontSize: 18, color: '#1A2DC4', fontWeight: 700 }}>{HP_DATA.phone}</a>
                </div>
              </div>

            </div>
          ) : (
            /* Confirmation — mirrors free-trial step 4 */
            <div style={{ textAlign: 'center', padding: '40px 0' }}>
              <div style={{ width: 80, height: 80, borderRadius: '50%', background: '#1A2DC4', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: 32 }}>
                <span style={{ color: '#fff' }}><HPIcon.Check size={36} /></span>
              </div>
              <h2 className="hp-display-lg" style={{ marginBottom: 24 }}>We got it.</h2>
              <p className="hp-body-lg" style={{ maxWidth: 540, margin: '0 auto 32px', fontSize: 20 }}>
                Expect a reply to <strong>{info.email || 'your inbox'}</strong> within one business day. A coach will reach out — not a sales team.
              </p>
              <div style={{ display: 'inline-block', padding: 24, background: '#0A0A0A', color: '#F6F4EE', textAlign: 'left', marginBottom: 40 }}>
                <div className="hp-meta" style={{ color: '#9A9A98', marginBottom: 8 }}>Your message was sent to</div>
                <div className="hp-h-md" style={{ color: '#F6F4EE', textTransform: 'capitalize' }}>{location} location</div>
              </div>
              <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
                <a href="index.html" className="hp-btn hp-btn-ghost hp-btn-lg">Back to home</a>
                <a href="free-trial.html" className="hp-btn hp-btn-primary hp-btn-lg">Book a free trial <HPIcon.Arrow size={16} /></a>
              </div>
            </div>
          )}
        </div>
      </section>

    </PageShell>
  );
}
window.ContactPage = ContactPage;
