// Free Trial booking — three-step flow with calendar, time picker, contact form, confirmation.

const TRIAL_PROGRAMS = [
  { id: 'bjj', name: 'Brazilian Jiu-Jitsu', desc: 'Adults 13+ · gentle start, technical for life' },
  { id: 'kick', name: 'Fitness Kickboxing', desc: 'All levels · Muay Thai technique at fitness pace' },
  { id: 'kids', name: 'Kids Jiu-Jitsu', desc: 'Ages 5–12 · focus, courtesy, confidence' },
  { id: 'unsure', name: "I'm not sure yet", desc: "We'll help you pick after a quick chat" },
];
const TRIAL_TIMES = {
  bjj: ['Mon 6:30p · BJJ Fundamentals', 'Wed 6:30p · BJJ Fundamentals', 'Fri 12:00p · Open Mat', 'Sat 10:30a · BJJ Fundamentals'],
  kick: ['Mon 12:00p · Fitness Kickboxing', 'Tue 6:00a · Fitness Kickboxing', 'Wed 12:00p · Fitness Kickboxing', 'Thu 6:00a · Fitness Kickboxing'],
  kids: ['Mon 4:30p · Tigers (5–8)', 'Mon 5:30p · Juniors (9–12)', 'Wed 4:30p · Tigers (5–8)', 'Sat 9:00a · Family Open Mat'],
  unsure: ['Mon 6:30p · BJJ Fundamentals', 'Wed 12:00p · Fitness Kickboxing', 'Sat 10:30a · BJJ Fundamentals'],
};

function StepBar({ step }) {
  const steps = [
    { n: 1, label: 'Pick a program' },
    { n: 2, label: 'Pick a class time' },
    { n: 3, label: 'Your details' },
  ];
  return (
    <div style={{ display: 'flex', gap: 0, marginBottom: 64, borderTop: '1px solid rgba(10,10,10,0.15)', paddingTop: 32 }}>
      {steps.map((s, i) => {
        const isActive = step === s.n;
        const isDone = step > s.n;
        return (
          <div key={s.n} style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 16, paddingRight: 24 }}>
            <span style={{
              width: 40, height: 40, borderRadius: '50%',
              background: isActive ? '#1A2DC4' : isDone ? '#0A0A0A' : 'transparent',
              border: isActive || isDone ? 'none' : '1px solid rgba(10,10,10,0.2)',
              color: isActive || isDone ? '#fff' : '#9A9A98',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontFamily: 'Menlo, monospace', fontSize: 14, flexShrink: 0,
            }}>
              {isDone ? <HPIcon.Check size={16} /> : s.n}
            </span>
            <div>
              <div className="hp-meta" style={{ color: isActive ? '#1A2DC4' : '#9A9A98' }}>Step {s.n}</div>
              <div style={{ fontFamily: 'Fraunces, serif', fontSize: 18, fontWeight: 600, color: isActive || isDone ? '#181816' : '#9A9A98' }}>{s.label}</div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function FreeTrialPage() {
  const [step, setStep] = React.useState(1);
  const [program, setProgram] = React.useState(null);
  const [time, setTime] = React.useState(null);
  const [location, setLocation] = React.useState('rockford');
  const [info, setInfo] = React.useState({ name: '', phone: '', email: '', notes: '' });

  const onSubmit = e => {
    e.preventDefault();
    setStep(4);
  };

  return (
    <PageShell current="free-trial">
      <section style={{ padding: '64px 0 32px', background: '#EFEBE1', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
        <div className="hp-container-wide">
          <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 48, alignItems: 'end' }}>
            <h1 className="hp-display-xl">Your first class<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#1A2DC4' }}>is on us.</em></h1>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 12, color: '#181816' }}>
              {['Loaner gi & gloves provided', 'Beginners welcome — most start here', 'Same-day text confirmation', 'Cancel anytime, no upsell'].map(b => (
                <li key={b} style={{ display: 'flex', gap: 12, alignItems: 'center', fontSize: 16 }}>
                  <span style={{ color: '#1A2DC4' }}><HPIcon.Check size={16} /></span>{b}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      <section className="hp-section" style={{ padding: '80px 0 120px' }}>
        <div className="hp-container-wide" style={{ maxWidth: 1080 }}>
          {step <= 3 && <StepBar step={step} />}

          {step === 1 && (
            <div>
              <div className="hp-eyebrow" style={{ marginBottom: 16 }}>Step 01</div>
              <h2 className="hp-display-md" style={{ marginBottom: 40 }}>Which class are you curious about?</h2>
              <div style={{ display: 'grid', gap: 12 }}>
                {TRIAL_PROGRAMS.map(p => {
                  const isSel = program === p.id;
                  return (
                    <button key={p.id} onClick={() => setProgram(p.id)} style={{
                      display: 'flex', alignItems: 'center', gap: 24, padding: '24px 28px',
                      border: '1px solid ' + (isSel ? '#1A2DC4' : 'rgba(10,10,10,0.15)'),
                      background: isSel ? 'rgba(26,45,196,0.04)' : '#fff',
                      borderRadius: 2, cursor: 'pointer', textAlign: 'left', fontFamily: 'inherit',
                    }}>
                      <span style={{
                        width: 22, height: 22, borderRadius: '50%',
                        border: '2px solid ' + (isSel ? '#1A2DC4' : '#9A9A98'),
                        background: isSel ? '#1A2DC4' : 'transparent',
                        flexShrink: 0,
                        boxShadow: isSel ? 'inset 0 0 0 4px #fff' : 'none',
                      }} />
                      <div style={{ flex: 1 }}>
                        <div className="hp-h-md">{p.name}</div>
                        <div className="hp-body-sm" style={{ marginTop: 4 }}>{p.desc}</div>
                      </div>
                    </button>
                  );
                })}
              </div>
              <div style={{ marginTop: 40, display: 'flex', justifyContent: 'flex-end' }}>
                <button onClick={() => program && setStep(2)} disabled={!program} className="hp-btn hp-btn-primary hp-btn-lg" style={{ opacity: program ? 1 : 0.4 }}>
                  Continue <HPIcon.Arrow size={16} />
                </button>
              </div>
            </div>
          )}

          {step === 2 && (
            <div>
              <div className="hp-eyebrow" style={{ marginBottom: 16 }}>Step 02</div>
              <h2 className="hp-display-md" style={{ marginBottom: 40 }}>Pick a class time.</h2>

              <div style={{ marginBottom: 32 }}>
                <div className="hp-meta" style={{ marginBottom: 12 }}>Location</div>
                <div style={{ display: 'flex', gap: 0, border: '1px solid rgba(10,10,10,0.18)', borderRadius: 2, width: 'fit-content' }}>
                  {['rockford', 'beloit'].map(l => (
                    <button key={l} onClick={() => setLocation(l)} style={{
                      padding: '12px 24px', border: 'none', cursor: 'pointer',
                      background: location === l ? '#0A0A0A' : 'transparent',
                      color: location === l ? '#F6F4EE' : '#181816',
                      fontFamily: 'inherit', fontSize: 14, fontWeight: 600,
                      textTransform: 'capitalize',
                    }}>{l}</button>
                  ))}
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12 }}>
                {(TRIAL_TIMES[program] || []).map(t => {
                  const isSel = time === t;
                  return (
                    <button key={t} onClick={() => setTime(t)} style={{
                      padding: '20px 24px', textAlign: 'left',
                      border: '1px solid ' + (isSel ? '#1A2DC4' : 'rgba(10,10,10,0.15)'),
                      background: isSel ? 'rgba(26,45,196,0.04)' : '#fff',
                      cursor: 'pointer', fontFamily: 'inherit', borderRadius: 2,
                      display: 'flex', alignItems: 'center', gap: 16,
                    }}>
                      <span style={{ color: isSel ? '#1A2DC4' : '#9A9A98' }}><HPIcon.Clock size={18} /></span>
                      <span className="hp-h-sm">{t}</span>
                    </button>
                  );
                })}
              </div>
              <p className="hp-body-sm" style={{ marginTop: 20 }}>
                Don\u2019t see a time that works? <a className="hp-link-blue" href={`tel:${HP_DATA.phone}`}>Call us at {HP_DATA.phone}</a> and we'll find something.
              </p>
              <div style={{ marginTop: 40, display: 'flex', justifyContent: 'space-between' }}>
                <button onClick={() => setStep(1)} className="hp-btn hp-btn-ghost hp-btn-lg">← Back</button>
                <button onClick={() => time && setStep(3)} disabled={!time} className="hp-btn hp-btn-primary hp-btn-lg" style={{ opacity: time ? 1 : 0.4 }}>
                  Continue <HPIcon.Arrow size={16} />
                </button>
              </div>
            </div>
          )}

          {step === 3 && (
            <form onSubmit={onSubmit}>
              <div className="hp-eyebrow" style={{ marginBottom: 16 }}>Step 03</div>
              <h2 className="hp-display-md" style={{ marginBottom: 40 }}>Last bit. Where do we text the confirmation?</h2>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                <label>
                  <div className="hp-meta" style={{ marginBottom: 8 }}>Your name</div>
                  <input className="hp-input" required value={info.name} onChange={e => setInfo({ ...info, name: e.target.value })} placeholder="Alex Garcia" />
                </label>
                <label>
                  <div className="hp-meta" style={{ marginBottom: 8 }}>Phone</div>
                  <input className="hp-input" required type="tel" value={info.phone} onChange={e => setInfo({ ...info, phone: e.target.value })} placeholder="(815) 000-0000" />
                </label>
                <label style={{ gridColumn: '1 / -1' }}>
                  <div className="hp-meta" style={{ marginBottom: 8 }}>Email <span style={{ color: '#9A9A98', fontFamily: 'inherit' }}>(optional)</span></div>
                  <input className="hp-input" type="email" value={info.email} onChange={e => setInfo({ ...info, email: e.target.value })} placeholder="alex@example.com" />
                </label>
                <label style={{ gridColumn: '1 / -1' }}>
                  <div className="hp-meta" style={{ marginBottom: 8 }}>Anything we should know? <span style={{ color: '#9A9A98', fontFamily: 'inherit' }}>(injuries, nerves, kid info)</span></div>
                  <textarea className="hp-input" rows={4} value={info.notes} onChange={e => setInfo({ ...info, notes: e.target.value })} placeholder="Optional. We'll keep it private." />
                </label>
              </div>

              <div style={{ padding: 24, background: '#EFEBE1', marginTop: 32, borderLeft: '3px solid #1A2DC4' }}>
                <div className="hp-meta" style={{ marginBottom: 8 }}>You're booking</div>
                <div className="hp-h-md">{TRIAL_PROGRAMS.find(p => p.id === program)?.name}</div>
                <div className="hp-body-sm" style={{ marginTop: 4 }}>{time} · {location === 'rockford' ? 'Rockford HQ' : 'Beloit'}</div>
              </div>

              <div style={{ marginTop: 32, display: 'flex', justifyContent: 'space-between' }}>
                <button type="button" onClick={() => setStep(2)} className="hp-btn hp-btn-ghost hp-btn-lg">← Back</button>
                <button type="submit" className="hp-btn hp-btn-primary hp-btn-lg">Confirm my class <HPIcon.Arrow size={16} /></button>
              </div>
            </form>
          )}

          {step === 4 && (
            <div style={{ textAlign: 'center', padding: '40px 0' }}>
              <div style={{ width: 80, height: 80, borderRadius: '50%', background: '#1A2DC4', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', marginBottom: 32 }}>
                <span style={{ color: '#fff' }}><HPIcon.Check size={36} /></span>
              </div>
              <h2 className="hp-display-lg" style={{ marginBottom: 24 }}>You're in.</h2>
              <p className="hp-body-lg" style={{ maxWidth: 540, margin: '0 auto 32px', fontSize: 20 }}>
                We just texted <strong>{info.phone || 'your phone'}</strong> to confirm. A coach will follow up before class with anything you need to know.
              </p>
              <div style={{ display: 'inline-block', padding: 24, background: '#0A0A0A', color: '#F6F4EE', textAlign: 'left', marginBottom: 32 }}>
                <div className="hp-meta" style={{ color: '#9A9A98', marginBottom: 12 }}>Your booking</div>
                <div className="hp-h-md" style={{ color: '#F6F4EE' }}>{TRIAL_PROGRAMS.find(p => p.id === program)?.name}</div>
                <div className="hp-body-sm" style={{ color: '#9A9A98', marginTop: 4 }}>{time} · {location === 'rockford' ? 'Rockford HQ · 4911 26th Ave' : 'Beloit · HMA Beloit'}</div>
              </div>
              <div style={{ display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
                <a href="index.html" className="hp-btn hp-btn-ghost hp-btn-lg">Back to home</a>
                <a href="schedule.html" className="hp-btn hp-btn-primary hp-btn-lg">See full schedule <HPIcon.Arrow size={16} /></a>
              </div>
            </div>
          )}
        </div>
      </section>
    </PageShell>
  );
}
window.FreeTrialPage = FreeTrialPage;
