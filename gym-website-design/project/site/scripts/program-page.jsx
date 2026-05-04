// Shared template for program detail pages.
// Reads program data from HP_DATA.programs by id and renders a consistent layout.

const PROG_PREFIX_KEY = '__prog_prefix__';

function ProgramHero({ program, accentColor }) {
  const prefix = pathPrefix();
  return (
    <section style={{ padding: '64px 0 96px', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 64, alignItems: 'end' }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 24, color: accentColor }}>{program.kicker} · {program.tag}</div>
            <h1 className="hp-display-xl" style={{ marginBottom: 32 }}>{program.name}</h1>
            <p className="hp-body-lg" style={{ maxWidth: 560, fontSize: 22, lineHeight: 1.5 }}>{program.copy}</p>
            <div className="hp-row-flex" style={{ display: 'flex', gap: 12, marginTop: 40 }}>
              <a href={prefix + 'free-trial.html'} className="hp-btn hp-btn-primary hp-btn-lg">Try a free class <HPIcon.Arrow size={16} /></a>
              <a href={prefix + 'schedule.html'} className="hp-btn hp-btn-ghost hp-btn-lg">See class times</a>
            </div>
          </div>
          <div className={'hp-photo hp-photo-' + (program.id === 'kick' ? 'kick' : program.id === 'kids' ? 'kid' : 'bjj')}
            style={{ aspectRatio: '4 / 5' }}>
            <span className="hp-photo-label">photo · {program.name}</span>
          </div>
        </div>
      </div>
    </section>
  );
}

function ProgramFacts({ program, accentColor }) {
  const facts = [
    { k: 'Ages', v: program.ages },
    { k: 'Cadence', v: program.sessions },
    { k: 'Format', v: program.tag },
    { k: 'Lineage', v: program.kicker },
  ];
  return (
    <section className="hp-section" style={{ padding: '80px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-4" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 0, borderTop: '1px solid rgba(10,10,10,0.15)' }}>
          {facts.map((f, i) => (
            <div key={f.k} style={{
              padding: '36px 32px 36px 0',
              borderRight: i < 3 ? '1px solid rgba(10,10,10,0.1)' : 'none',
              paddingLeft: i > 0 ? 32 : 0,
            }}>
              <div className="hp-meta" style={{ color: accentColor, marginBottom: 16 }}>{f.k}</div>
              <div className="hp-h-md">{f.v}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function ProgramWhatYouLearn({ program, sections, accentColor }) {
  return (
    <section className="hp-section" style={{ background: '#EFEBE1', padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 64, marginBottom: 64 }}>
          <div className="hp-eyebrow">What you'll learn</div>
          <h2 className="hp-display-md">A clear curriculum, taught patiently.</h2>
        </div>
        <div className="hp-grid-3" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24 }}>
          {sections.map((s, i) => (
            <div key={i} style={{ padding: 32, background: '#fff', border: '1px solid rgba(10,10,10,0.08)' }}>
              <div className="hp-meta" style={{ color: accentColor, marginBottom: 14 }}>{String(i + 1).padStart(2, '0')} · {s.phase}</div>
              <h3 className="hp-h-md" style={{ marginBottom: 12 }}>{s.title}</h3>
              <p className="hp-body" style={{ marginBottom: 18 }}>{s.copy}</p>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 8 }}>
                {s.bullets.map((b, j) => (
                  <li key={j} style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                    <span style={{ color: accentColor, marginTop: 2 }}><HPIcon.Check size={13} /></span>
                    <span className="hp-body-sm" style={{ color: '#181816' }}>{b}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function ProgramFirstClass({ steps, accentColor }) {
  return (
    <section className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 64, marginBottom: 56 }}>
          <div className="hp-eyebrow">Your first class</div>
          <h2 className="hp-display-md">What to expect when you walk in.</h2>
        </div>
        <ol style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 0, borderTop: '1px solid rgba(10,10,10,0.15)' }}>
          {steps.map((s, i) => (
            <li key={i} style={{
              display: 'grid', gridTemplateColumns: '120px 1fr 2fr', gap: 32,
              padding: '32px 0', borderBottom: '1px solid rgba(10,10,10,0.08)', alignItems: 'baseline',
            }}>
              <div className="hp-meta" style={{ color: accentColor }}>Step {String(i + 1).padStart(2, '0')}</div>
              <div className="hp-h-md">{s.title}</div>
              <p className="hp-body-lg" style={{ color: '#4A4A48' }}>{s.copy}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

function ProgramFAQ({ faq, accentColor }) {
  const [open, setOpen] = React.useState(0);
  return (
    <section className="hp-section" style={{ background: '#0A0A0A', color: '#F6F4EE', padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 64, marginBottom: 56 }}>
          <div className="hp-eyebrow" style={{ color: '#9A9A98' }}>FAQ</div>
          <h2 className="hp-display-md" style={{ color: '#F6F4EE' }}>Common questions, answered honestly.</h2>
        </div>
        <div style={{ borderTop: '1px solid rgba(255,255,255,0.12)' }}>
          {faq.map((q, i) => {
            const isOpen = open === i;
            return (
              <div key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.12)' }}>
                <button onClick={() => setOpen(isOpen ? -1 : i)} style={{
                  width: '100%', padding: '28px 0', display: 'flex',
                  justifyContent: 'space-between', alignItems: 'center',
                  background: 'transparent', border: 'none', cursor: 'pointer',
                  color: '#F6F4EE', textAlign: 'left',
                }}>
                  <span className="hp-h-md" style={{ color: '#F6F4EE' }}>{q.q}</span>
                  <span style={{ color: accentColor, marginLeft: 24 }}>
                    {isOpen ? <HPIcon.Minus size={20} /> : <HPIcon.Plus size={20} />}
                  </span>
                </button>
                {isOpen && (
                  <div style={{ paddingBottom: 28, maxWidth: 760 }}>
                    <p className="hp-body-lg" style={{ color: '#9A9A98' }}>{q.a}</p>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function ProgramCTA({ program, accentColor }) {
  const prefix = pathPrefix();
  return (
    <section style={{ background: accentColor, color: '#fff', padding: '120px 0', position: 'relative', overflow: 'hidden' }}>
      <div style={{ position: 'absolute', right: '-8%', top: '-20%', opacity: 0.1 }}>
        <HPGlyph size={640} color="#fff" />
      </div>
      <div className="hp-container-wide" style={{ position: 'relative', zIndex: 1 }}>
        <div className="hp-eyebrow" style={{ color: 'rgba(255,255,255,0.7)', marginBottom: 24 }}>Ready when you are</div>
        <h2 className="hp-display-lg" style={{ color: '#fff', maxWidth: 920, marginBottom: 40 }}>
          Try {program.short} for free.<br />
          <em style={{ fontStyle: 'italic', fontWeight: 500, opacity: 0.8 }}>No commitment, loaner gear, beginner-friendly.</em>
        </h2>
        <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
          <a href={prefix + 'free-trial.html'} className="hp-btn hp-btn-lg" style={{ background: '#fff', color: accentColor, fontWeight: 700 }}>
            Pick a date <HPIcon.Arrow size={16} />
          </a>
          <a href={prefix + 'schedule.html'} className="hp-btn hp-btn-lg" style={{ background: 'transparent', color: '#fff', border: '1px solid rgba(255,255,255,0.4)' }}>
            See class schedule
          </a>
        </div>
      </div>
    </section>
  );
}

function ProgramRelated({ currentId }) {
  const prefix = pathPrefix();
  const others = HP_DATA.programs.filter(p => p.id !== currentId);
  const links = { bjj: 'programs/bjj.html', kick: 'programs/kickboxing.html', kids: 'programs/kids.html' };
  return (
    <section className="hp-section" style={{ padding: '96px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-eyebrow" style={{ marginBottom: 24 }}>Other programs</div>
        <h2 className="hp-display-sm" style={{ marginBottom: 40 }}>Train more than one.</h2>
        <div className="hp-grid-3" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 24 }}>
          {others.map(p => (
            <a key={p.id} href={prefix + links[p.id]} style={{
              padding: 28, border: '1px solid rgba(10,10,10,0.12)', background: '#fff',
              display: 'flex', flexDirection: 'column', gap: 16, transition: 'all 160ms',
            }}>
              <div className="hp-meta" style={{ color: '#1A2DC4' }}>{p.tag}</div>
              <h3 className="hp-display-sm">{p.name}</h3>
              <p className="hp-body" style={{ color: '#4A4A48' }}>{p.kicker}</p>
              <span style={{ marginTop: 'auto', color: '#1A2DC4', display: 'flex', alignItems: 'center', gap: 8, fontWeight: 600, fontSize: 14 }}>
                Read more <HPIcon.Arrow size={14} />
              </span>
            </a>
          ))}
        </div>
      </div>
    </section>
  );
}

function ProgramPage({ programId, accentColor = '#1A2DC4', sections, steps, faq }) {
  const program = HP_DATA.programs.find(p => p.id === programId);
  return (
    <PageShell current="programs">
      <ProgramHero program={program} accentColor={accentColor} />
      <ProgramFacts program={program} accentColor={accentColor} />
      <ProgramWhatYouLearn program={program} sections={sections} accentColor={accentColor} />
      <ProgramFirstClass steps={steps} accentColor={accentColor} />
      <ProgramFAQ faq={faq} accentColor={accentColor} />
      <ProgramCTA program={program} accentColor={accentColor} />
      <ProgramRelated currentId={programId} />
    </PageShell>
  );
}

window.ProgramPage = ProgramPage;
