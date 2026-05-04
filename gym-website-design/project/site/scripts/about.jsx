// About / Coaches page — Darby & Amanda Haanpaa, lineage, philosophy.

function AboutHero() {
  return (
    <section style={{ padding: '80px 0 64px', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr', gap: 64, alignItems: 'end' }}>
          <h1 className="hp-display-xl">A school built<br />by a family,<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#1A2DC4' }}>for families.</em></h1>
          <p className="hp-body-lg" style={{ maxWidth: 460, fontSize: 22, lineHeight: 1.5 }}>
            Darby and Amanda Haanpaa opened the doors with a simple idea — make real martial arts available to people who never thought they belonged in a gym. Twenty years later, that's still the room you walk into.
          </p>
        </div>
      </div>
    </section>
  );
}

function AboutStory() {
  return (
    <section className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1.6fr', gap: 96 }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 24 }}>The story</div>
            <h2 className="hp-display-md">From Team Curran<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#9A9A98' }}>to your neighborhood.</em></h2>
          </div>
          <div style={{ display: 'grid', gap: 24 }}>
            <p className="hp-body-lg" style={{ color: '#181816' }}>
              Darby came up under Pat Curran and the Curran family in Crystal Lake — one of the most respected MMA lineages in the Midwest. He brought that pedigree home to Rockford and built a school where the technique is real, the room is welcoming, and the standard is high.
            </p>
            <p className="hp-body-lg">
              Amanda Haanpaa runs the business side and the kids program. Together they\u2019ve raised generations of students from white belts to coaches — many of whom you\u2019ll meet on the mat.
            </p>
            <p className="hp-body-lg">
              We\u2019re still a small, family-run school. We know our students by name. We\u2019ll know yours.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function AboutPrinciples() {
  const items = [
    { n: '01', t: 'Technique over intensity', c: 'Hard work matters, but real progress is built on careful technique. We coach details. We slow things down. You leave class better, not just tired.' },
    { n: '02', t: 'No ego on the mat', c: 'We do not have a fight-gym culture. Beginners are protected. Advanced students help. The room is calm and the people are kind.' },
    { n: '03', t: 'Show up, not show off', c: 'Consistency builds black belts. We celebrate the student who came twice a week for two years more than we celebrate the natural athlete.' },
    { n: '04', t: 'Family first', c: 'Many of our students train with their kids, their spouse, or their best friend. We design schedules and pricing to make that possible.' },
  ];
  return (
    <section className="hp-section" style={{ background: '#0A0A0A', color: '#F6F4EE', padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-eyebrow" style={{ color: '#9A9A98', marginBottom: 24 }}>How we coach</div>
        <h2 className="hp-display-lg" style={{ color: '#F6F4EE', marginBottom: 80, maxWidth: 920 }}>
          Four principles<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#1A2DC4' }}>we never compromise.</em>
        </h2>
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 0, borderTop: '1px solid rgba(255,255,255,0.12)' }}>
          {items.map((it, i) => (
            <div key={it.n} style={{
              padding: '40px 40px 40px 0',
              borderRight: i % 2 === 0 ? '1px solid rgba(255,255,255,0.12)' : 'none',
              borderBottom: i < 2 ? '1px solid rgba(255,255,255,0.12)' : 'none',
              paddingLeft: i % 2 === 1 ? 40 : 0,
            }}>
              <div className="hp-meta" style={{ color: '#1A2DC4', marginBottom: 16 }}>{it.n}</div>
              <h3 className="hp-display-sm" style={{ color: '#F6F4EE', marginBottom: 16 }}>{it.t}</h3>
              <p className="hp-body-lg" style={{ color: '#9A9A98', maxWidth: 460 }}>{it.c}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function AboutCoaches() {
  return (
    <section id="coaches" className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 64, marginBottom: 64 }}>
          <div className="hp-eyebrow">The team</div>
          <h2 className="hp-display-lg">Coaches you'll see every week.</h2>
        </div>
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 32, marginBottom: 64 }}>
          {HP_DATA.instructors.slice(0, 2).map((p, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
              <div className="hp-photo hp-photo-mat" style={{ aspectRatio: '4 / 5' }}>
                <span className="hp-photo-label">{p.name}</span>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', paddingBottom: 8 }}>
                <div className="hp-meta" style={{ color: '#1A2DC4', marginBottom: 12 }}>{p.title}</div>
                <h3 className="hp-display-sm" style={{ marginBottom: 12 }}>{p.name}</h3>
                <p className="hp-body" style={{ marginBottom: 16 }}>{p.belt}</p>
                <p className="hp-body-sm">[Bio paragraph — short, personal, mentions years training, why they coach. Replace with copy from Darby & Amanda before launch.]</p>
              </div>
            </div>
          ))}
        </div>
        <div className="hp-grid-4" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 24 }}>
          {HP_DATA.instructors.slice(2).map((p, i) => (
            <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              <div className="hp-photo hp-photo-bjj" style={{ aspectRatio: '3 / 4' }}>
                <span className="hp-photo-label">{p.name}</span>
              </div>
              <div>
                <h4 className="hp-h-md">{p.name}</h4>
                <div className="hp-body-sm" style={{ marginTop: 4 }}>{p.title}</div>
                <div className="hp-meta" style={{ marginTop: 10, color: '#1A2DC4' }}>{p.belt}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function AboutCTA() {
  return (
    <section style={{ background: '#1A2DC4', color: '#fff', padding: '96px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 48, alignItems: 'center' }}>
          <h2 className="hp-display-lg" style={{ color: '#fff' }}>Come meet the team in person.</h2>
          <div>
            <p className="hp-body-lg" style={{ color: 'rgba(255,255,255,0.85)', marginBottom: 24, fontSize: 20 }}>
              Your free trial includes a sit-down with the head coach. No pressure, no upsell. Just a chance to ask questions and see if we\u2019re the right room for you.
            </p>
            <a href="free-trial.html" className="hp-btn hp-btn-lg" style={{ background: '#fff', color: '#1A2DC4', fontWeight: 700 }}>
              Book your free trial <HPIcon.Arrow size={16} />
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

function AboutPage() {
  return (
    <PageShell current="about">
      <AboutHero />
      <AboutStory />
      <AboutPrinciples />
      <AboutCoaches />
      <AboutCTA />
    </PageShell>
  );
}
window.AboutPage = AboutPage;
