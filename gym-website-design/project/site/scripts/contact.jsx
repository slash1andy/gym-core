// Contact page — mirrors the block-pattern layout exactly (form full-width, location cards below).

function ContactPage() {
  const [submitted, setSubmitted] = React.useState(false);
  const [location, setLocation] = React.useState('rockford');
  const [info, setInfo] = React.useState({ name: '', email: '', phone: '', notes: '' });

  const onSubmit = e => {
    e.preventDefault();
    setSubmitted(true);
  };

  if (submitted) {
    return (
      <PageShell current="contact">
        <section style={{ padding: 'clamp(64px,8vw,100px) clamp(24px,4vw,80px) clamp(80px,10vw,140px)', background: '#ffffff' }}>
          <div style={{ maxWidth: 880, margin: '0 auto', textAlign: 'center', paddingTop: 40 }}>
            <div style={{ width: 80, height: 80, borderRadius: '50%', background: '#1A2DC4', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: 32 }}>
              <span style={{ color: '#fff' }}><HPIcon.Check size={36} /></span>
            </div>
            <h2 style={{ fontSize: 'clamp(28px,4vw,48px)', fontWeight: 600, letterSpacing: '-0.02em', margin: '0 0 24px' }}>We got it.</h2>
            <p style={{ fontSize: 20, maxWidth: 540, margin: '0 auto 32px', color: '#181816' }}>
              Expect a reply to <strong>{info.email || 'your inbox'}</strong> within one business day. A coach will reach out — not a sales team.
            </p>
            <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
              <a href="index.html" className="hp-btn hp-btn-ghost hp-btn-lg">Back to home</a>
              <a href="free-trial.html" className="hp-btn hp-btn-primary hp-btn-lg">Book a free trial <HPIcon.Arrow size={16} /></a>
            </div>
          </div>
        </section>
      </PageShell>
    );
  }

  return (
    <PageShell current="contact">

      {/* Hero — cream background, matches block-pattern section 1 */}
      <section style={{ padding: 'clamp(64px,8vw,120px) clamp(24px,4vw,80px) 0', background: '#EFEBE1' }}>
        <div style={{ maxWidth: 1200, margin: '0 auto' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 48, alignItems: 'end', paddingBottom: 'clamp(40px,5vw,72px)' }}>
            <h1 style={{ fontSize: 'clamp(40px,6vw,80px)', fontWeight: 600, letterSpacing: '-0.03em', lineHeight: 1.02, margin: 0 }}>
              Get in touch.<br />
              <em style={{ fontStyle: 'italic', fontWeight: 500, color: '#1A2DC4' }}>We respond fast.</em>
            </h1>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 12, color: '#181816' }}>
              {['Same-day response', 'Two locations to choose from', 'No sales pitch — just answers', 'A coach makes first contact'].map(b => (
                <li key={b} style={{ display: 'flex', gap: 12, alignItems: 'center', fontSize: 16 }}>
                  <span style={{ color: '#1A2DC4', fontSize: 16 }}>✓</span> {b}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* Form section — white background, matches block-pattern section 2 */}
      <section style={{ padding: 'clamp(64px,8vw,100px) clamp(24px,4vw,80px) clamp(80px,10vw,140px)', background: '#ffffff' }}>
        <div style={{ maxWidth: 880, margin: '0 auto' }}>

          <h2 style={{ fontSize: 'clamp(28px,4vw,48px)', fontWeight: 600, letterSpacing: '-0.02em', margin: '0 0 40px' }}>Send us a message.</h2>

          <form onSubmit={onSubmit} style={{ display: 'grid', gap: 20 }}>
            {/* Honeypot */}
            <input type="text" name="company" tabIndex={-1} autoComplete="off" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }} aria-hidden="true" />

            {/* Location toggle */}
            <div>
              <p style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48', margin: '0 0 12px' }}>Location</p>
              <div style={{ display: 'flex', gap: 0, border: '1px solid rgba(10,10,10,0.18)', borderRadius: 2, width: 'fit-content' }}>
                {[['rockford', 'Rockford'], ['beloit', 'Beloit']].map(([id, label]) => (
                  <button key={id} type="button" onClick={() => setLocation(id)} style={{
                    padding: '12px 24px', border: 'none', cursor: 'pointer',
                    background: location === id ? '#0A0A0A' : 'transparent',
                    color: location === id ? '#F6F4EE' : '#181816',
                    fontFamily: 'inherit', fontSize: 14, fontWeight: 600,
                  }}>{label}</button>
                ))}
              </div>
            </div>

            {/* Name */}
            <label style={{ display: 'grid', gap: 8 }}>
              <span style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48' }}>Your name</span>
              <input required name="name" placeholder="Alex Garcia" value={info.name} onChange={e => setInfo({ ...info, name: e.target.value })}
                style={{ padding: 16, border: '1px solid rgba(10,10,10,0.18)', fontSize: 16, borderRadius: 2, background: '#fff', width: '100%', boxSizing: 'border-box' }} />
            </label>

            {/* Email + Phone row */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
              <label style={{ display: 'grid', gap: 8 }}>
                <span style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48' }}>Email</span>
                <input required name="email" type="email" placeholder="alex@example.com" value={info.email} onChange={e => setInfo({ ...info, email: e.target.value })}
                  style={{ padding: 16, border: '1px solid rgba(10,10,10,0.18)', fontSize: 16, borderRadius: 2, background: '#fff' }} />
              </label>
              <label style={{ display: 'grid', gap: 8 }}>
                <span style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48' }}>
                  Phone <span style={{ textTransform: 'none', fontSize: 12 }}>(optional)</span>
                </span>
                <input name="phone" type="tel" placeholder="(815) 000-0000" value={info.phone} onChange={e => setInfo({ ...info, phone: e.target.value })}
                  style={{ padding: 16, border: '1px solid rgba(10,10,10,0.18)', fontSize: 16, borderRadius: 2, background: '#fff' }} />
              </label>
            </div>

            {/* Message */}
            <label style={{ display: 'grid', gap: 8 }}>
              <span style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48' }}>Message</span>
              <textarea required name="notes" rows={5} placeholder="What are you curious about?" value={info.notes} onChange={e => setInfo({ ...info, notes: e.target.value })}
                style={{ padding: 16, border: '1px solid rgba(10,10,10,0.18)', fontSize: 16, borderRadius: 2, background: '#fff', fontFamily: 'inherit', resize: 'vertical' }} />
            </label>

            {/* Submit */}
            <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
              <button type="submit" style={{ background: '#1A2DC4', color: '#fff', border: 0, padding: '18px 40px', fontSize: 15, fontWeight: 600, cursor: 'pointer', borderRadius: 2 }}>
                Send message →
              </button>
            </div>
          </form>

          {/* Location cards — matches "Or reach us directly" section in block pattern */}
          <div style={{ marginTop: 48, paddingTop: 40, borderTop: '1px solid rgba(10,10,10,0.1)' }}>
            <p style={{ fontSize: 13, letterSpacing: '0.06em', textTransform: 'uppercase', color: '#4A4A48', margin: '0 0 16px' }}>Or reach us directly</p>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
              <div style={{ padding: 24, background: '#EFEBE1', borderLeft: '3px solid #1A2DC4' }}>
                <p style={{ fontSize: 11, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#4A4A48', margin: '0 0 8px' }}>★ Rockford HQ</p>
                <p style={{ margin: '0 0 4px', fontSize: 15, color: '#181816' }}>4911 26th Avenue, Rockford, IL 61109</p>
                <a href="tel:815-451-3001" style={{ fontFamily: 'Menlo, monospace', fontSize: 14, color: '#1A2DC4', fontWeight: 600 }}>815-451-3001</a>
              </div>
              <div style={{ padding: 24, background: '#F6F4EE', borderLeft: '3px solid rgba(10,10,10,0.18)' }}>
                <p style={{ fontSize: 11, letterSpacing: '0.08em', textTransform: 'uppercase', color: '#4A4A48', margin: '0 0 8px' }}>Beloit satellite</p>
                <p style={{ margin: '0 0 4px', fontSize: 15, color: '#181816' }}>610 4th St, Beloit, WI 53511</p>
                <a href="tel:608-795-3608" style={{ fontFamily: 'Menlo, monospace', fontSize: 14, color: '#1A2DC4', fontWeight: 600 }}>608-795-3608</a>
              </div>
            </div>
          </div>

        </div>
      </section>

    </PageShell>
  );
}
window.ContactPage = ContactPage;
