// Full week schedule page with location toggle and class-type filters.

function ScheduleHero() {
  return (
    <section style={{ padding: '80px 0 56px', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 48, alignItems: 'end' }}>
          <h1 className="hp-display-xl">Class schedule.</h1>
          <p className="hp-body-lg" style={{ maxWidth: 420 }}>
            Drop in to any class on your free trial. Below is the full week — filter by program or location to find what fits your day.
          </p>
        </div>
      </div>
    </section>
  );
}

function ScheduleGrid() {
  const [loc, setLoc] = React.useState('rockford');
  const [filter, setFilter] = React.useState('all');
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const colors = { bjj: '#1A2DC4', kick: '#B26200', kids: '#2B8A5F' };
  const labels = { bjj: 'Jiu-Jitsu', kick: 'Kickboxing', kids: 'Kids' };
  const filters = [
    { id: 'all', label: 'All programs' },
    { id: 'bjj', label: 'Jiu-Jitsu' },
    { id: 'kick', label: 'Kickboxing' },
    { id: 'kids', label: 'Kids' },
  ];

  return (
    <section className="hp-section" style={{ padding: '64px 0 96px' }}>
      <div className="hp-container-wide">
        <div className="hp-row-flex" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 32, gap: 24 }}>
          <div style={{ display: 'flex', gap: 0, border: '1px solid rgba(10,10,10,0.18)', borderRadius: 2 }}>
            {[
              { id: 'rockford', label: 'Rockford' },
              { id: 'beloit', label: 'Beloit' },
            ].map(l => (
              <button key={l.id} onClick={() => setLoc(l.id)} style={{
                padding: '12px 24px', border: 'none', cursor: 'pointer',
                background: loc === l.id ? '#0A0A0A' : 'transparent',
                color: loc === l.id ? '#F6F4EE' : '#181816',
                fontSize: 14, fontWeight: 600, fontFamily: 'inherit',
              }}>{l.label}</button>
            ))}
          </div>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {filters.map(f => (
              <button key={f.id} onClick={() => setFilter(f.id)} style={{
                padding: '8px 14px', borderRadius: 999,
                border: '1px solid ' + (filter === f.id ? '#1A2DC4' : 'rgba(10,10,10,0.18)'),
                background: filter === f.id ? '#1A2DC4' : 'transparent',
                color: filter === f.id ? '#fff' : '#181816',
                cursor: 'pointer', fontSize: 13, fontWeight: 500, fontFamily: 'inherit',
              }}>{f.label}</button>
            ))}
          </div>
        </div>

        {loc === 'beloit' && (
          <div style={{ padding: 24, background: '#EFEBE1', marginBottom: 24, border: '1px solid rgba(10,10,10,0.08)' }}>
            <div className="hp-meta" style={{ color: '#1A2DC4', marginBottom: 8 }}>Beloit satellite</div>
            <p className="hp-body">
              The Beloit location runs a focused subset of our schedule. Below shows BJJ Fundamentals and Kids classes — call <a className="hp-link-blue" href="tel:8154513001">{HP_DATA.phone}</a> to confirm before dropping in.
            </p>
          </div>
        )}

        <div className="hp-schedule-week" style={{
          display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 16,
          borderTop: '1px solid rgba(10,10,10,0.15)', paddingTop: 24,
        }}>
          {days.map(d => {
            const all = HP_DATA.schedule[d] || [];
            const filtered = all
              .filter(c => filter === 'all' || c.kind === filter)
              .filter(c => loc === 'rockford' || (loc === 'beloit' && (c.kind === 'bjj' || c.kind === 'kids')));
            return (
              <div key={d} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div style={{ paddingBottom: 12, borderBottom: '1px solid rgba(10,10,10,0.1)' }}>
                  <div style={{ fontFamily: 'Fraunces, serif', fontSize: 24, fontWeight: 600 }}>{d}</div>
                  <div className="hp-meta" style={{ marginTop: 4 }}>{filtered.length} {filtered.length === 1 ? 'class' : 'classes'}</div>
                </div>
                {filtered.length === 0 && (
                  <div style={{ padding: '12px 0', color: '#9A9A98', fontSize: 13, fontStyle: 'italic' }}>—</div>
                )}
                {filtered.map((c, i) => (
                  <div key={i} style={{
                    padding: 14, background: '#fff', border: '1px solid rgba(10,10,10,0.08)',
                    borderLeft: '3px solid ' + colors[c.kind],
                    borderRadius: 2,
                  }}>
                    <div style={{ fontFamily: 'Menlo, monospace', fontSize: 13, color: '#0A0A0A', marginBottom: 6 }}>{c.time}</div>
                    <div style={{ fontFamily: 'Fraunces, serif', fontSize: 16, fontWeight: 600, lineHeight: 1.2, marginBottom: 8 }}>{c.name}</div>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', marginBottom: 4 }}>
                      <span style={{ width: 6, height: 6, borderRadius: '50%', background: colors[c.kind] }} />
                      <span style={{ fontSize: 11, color: '#4A4A48', fontWeight: 500 }}>{labels[c.kind]}</span>
                    </div>
                    <div style={{ fontSize: 11, color: '#9A9A98' }}>{c.who}</div>
                  </div>
                ))}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function ScheduleLegend() {
  const items = [
    { color: '#1A2DC4', label: 'Brazilian Jiu-Jitsu', desc: 'Gi & no-gi · adults & teens 13+' },
    { color: '#B26200', label: 'Fitness Kickboxing', desc: 'Pad & bag work · all levels' },
    { color: '#2B8A5F', label: 'Kids Jiu-Jitsu', desc: 'Ages 5–12 · split into Tigers & Juniors' },
  ];
  return (
    <section style={{ padding: '64px 0', borderTop: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-eyebrow" style={{ marginBottom: 24 }}>Class types</div>
        <div className="hp-grid-4" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 24 }}>
          {items.map(it => (
            <div key={it.label} style={{ display: 'flex', gap: 12 }}>
              <span style={{ width: 4, background: it.color, flexShrink: 0 }} />
              <div>
                <div className="hp-h-sm" style={{ marginBottom: 4 }}>{it.label}</div>
                <div className="hp-body-sm">{it.desc}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function ScheduleCTA() {
  return (
    <section style={{ background: '#1A2DC4', color: '#fff', padding: '80px 0' }}>
      <div className="hp-container-wide" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 24 }}>
        <div>
          <div className="hp-eyebrow" style={{ color: 'rgba(255,255,255,0.7)', marginBottom: 12 }}>Found a class?</div>
          <h2 className="hp-display-md" style={{ color: '#fff', maxWidth: 720 }}>Drop in for free this week.</h2>
        </div>
        <a href="free-trial.html" className="hp-btn hp-btn-lg" style={{ background: '#fff', color: '#1A2DC4', fontWeight: 700 }}>
          Reserve a class <HPIcon.Arrow size={16} />
        </a>
      </div>
    </section>
  );
}

function SchedulePage() {
  return (
    <PageShell current="schedule">
      <ScheduleHero />
      <ScheduleGrid />
      <ScheduleLegend />
      <ScheduleCTA />
    </PageShell>
  );
}
window.SchedulePage = SchedulePage;
