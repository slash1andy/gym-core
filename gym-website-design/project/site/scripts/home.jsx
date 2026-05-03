// Homepage sections — extracted from Direction A.
// Nav/footer come from PageShell; this file only owns the hero through CTA.

const HOME_PROGRAMS = HP_DATA.programs;
const HOME_REVIEWS = HP_DATA.reviews;
const HOME_INSTRUCTORS = HP_DATA.instructors;
const HOME_SCHEDULE = HP_DATA.schedule;
const HOME_PREFIX = pathPrefix();

function HomeHero() {
  return (
    <section style={{ position: 'relative', padding: '80px 0 0' }}>
      <div className="hp-container-wide">
        <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.15fr 1fr', gap: 64, alignItems: 'end' }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 28 }}>Rockford · Beloit</div>
            <h1 className="hp-hero-h1" style={{ fontFamily: 'Fraunces, serif', fontSize: 156, lineHeight: 0.86, letterSpacing: '-0.04em', fontWeight: 700, marginBottom: 56 }}>
              Train<br />like it<br /><span style={{ color: '#1A2DC4' }}>matters.</span>
            </h1>
            <p className="hp-body-lg" style={{ maxWidth: 480, marginBottom: 36 }}>
              A family-run martial arts school teaching Brazilian Jiu-Jitsu — for adults and kids — and Fitness Kickboxing.
              Beginners welcome; most of our members started exactly where you are now.
            </p>
            <div className="hp-row-flex" style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
              <a href={HOME_PREFIX + 'free-trial.html'} className="hp-btn hp-btn-primary hp-btn-lg">Book your free trial <HPIcon.Arrow size={16} /></a>
              <a href={HOME_PREFIX + 'schedule.html'} className="hp-btn hp-btn-ghost hp-btn-lg">See class schedule</a>
            </div>
            <div className="hp-row-flex" style={{ marginTop: 56, display: 'flex', gap: 40, alignItems: 'flex-start' }}>
              <div>
                <div className="hp-meta">Locations</div>
                <div className="hp-h-md" style={{ marginTop: 4 }}>Rockford <span style={{ color: '#9A9A98', fontWeight: 400, fontSize: 16 }}>& Beloit</span></div>
              </div>
              <div>
                <div className="hp-meta">Affiliation</div>
                <div className="hp-h-md" style={{ marginTop: 4 }}>Team Curran</div>
              </div>
            </div>
          </div>
          <div style={{ position: 'relative', aspectRatio: '4 / 5', borderRadius: 4, overflow: 'hidden' }}>
            <div className="hp-photo hp-photo-bjj" style={{ position: 'absolute', inset: 0 }}>
              <span className="hp-photo-label">photo · BJJ class on the mat</span>
            </div>
            <div style={{
              position: 'absolute', left: 24, bottom: 24, right: 24,
              padding: 20, background: 'rgba(246,244,238,0.96)', borderRadius: 2, backdropFilter: 'blur(8px)',
            }}>
              <div className="hp-eyebrow-mono" style={{ color: '#1A2DC4' }}>● Live · Tonight</div>
              <div className="hp-h-sm" style={{ marginTop: 8 }}>BJJ Fundamentals · 6:30p</div>
              <div className="hp-body-sm" style={{ marginTop: 4 }}>Beginners welcome. Loaner gi available at the front desk.</div>
            </div>
          </div>
        </div>
      </div>
      <div style={{ marginTop: 96, padding: '28px 0', borderTop: '1px solid rgba(10,10,10,0.1)', borderBottom: '1px solid rgba(10,10,10,0.1)' }}>
        <div className="hp-container-wide hp-trust-bar" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 32 }}>
          <span className="hp-eyebrow">As featured in</span>
          {['The Rockford Register Star', 'WTVO Good Day Stateline', 'Rockford Buzz'].map((t, i, arr) => (
            <React.Fragment key={t + i}>
              <span style={{ fontFamily: 'Fraunces, serif', fontStyle: 'italic', fontSize: 20, fontWeight: 500, color: '#181816' }}>{t}</span>
              {i < arr.length - 1 && <span style={{ color: '#1A2DC4', fontSize: 12 }}>★</span>}
            </React.Fragment>
          ))}
        </div>
      </div>
    </section>
  );
}

function HomeValues() {
  const values = [
    { n: '01', t: 'Everyone starts as a beginner', c: 'No experience, no shame. Our beginner classes run nightly and the room is full of people who walked in nervous a week ago.' },
    { n: '02', t: 'Family-built, family-run', c: 'Darby and Amanda Haanpaa run the school as a family. The room takes care of you.' },
    { n: '03', t: 'Real lineage, taught patiently', c: 'Gracie Brazilian Jiu-Jitsu and traditional Muay Thai. Real curriculum, no flash. You leave each class better than you came in.' },
  ];
  return (
    <section className="hp-section" style={{ paddingTop: 64 }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2.2fr', gap: 64, marginBottom: 80 }}>
          <div className="hp-eyebrow">What we believe</div>
          <h2 className="hp-display-md">
            We are not a fight gym. We are a martial arts school
            <span style={{ color: '#9A9A98' }}> — for everyone in your family, regardless of where you are starting.</span>
          </h2>
        </div>
        <div className="hp-grid-3" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 0, borderTop: '1px solid rgba(10,10,10,0.15)' }}>
          {values.map((v, i) => (
            <div key={v.n} style={{
              padding: '36px 32px 32px 0',
              borderRight: i < 2 ? '1px solid rgba(10,10,10,0.1)' : 'none',
              paddingLeft: i > 0 ? 32 : 0,
            }}>
              <div className="hp-meta" style={{ color: '#1A2DC4', marginBottom: 24 }}>{v.n}</div>
              <h3 className="hp-h-md" style={{ marginBottom: 14 }}>{v.t}</h3>
              <p className="hp-body">{v.c}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function HomeProgramsAccordion() {
  const [open, setOpen] = React.useState('bjj');
  const programLinks = { bjj: 'programs/bjj.html', kick: 'programs/kickboxing.html', kids: 'programs/kids.html' };
  return (
    <section id="programs" className="hp-section" style={{ background: '#EFEBE1', padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-row-flex" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 64 }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 16 }}>Programs · 04</div>
            <h2 className="hp-display-lg" style={{ maxWidth: 720 }}>One school, three ways in.</h2>
          </div>
          <a href={HOME_PREFIX + 'free-trial.html'} className="hp-btn hp-btn-ghost">Try any program free <HPIcon.Arrow size={14} /></a>
        </div>
        <div style={{ borderTop: '1px solid rgba(10,10,10,0.18)' }}>
          {HOME_PROGRAMS.map(p => {
            const isOpen = open === p.id;
            return (
              <div key={p.id} style={{ borderBottom: '1px solid rgba(10,10,10,0.18)' }}>
                <button onClick={() => setOpen(isOpen ? null : p.id)} style={{
                  width: '100%', padding: '36px 0', display: 'grid',
                  gridTemplateColumns: '80px 2fr 1.4fr 0.6fr 60px',
                  gap: 24, alignItems: 'center', background: 'transparent',
                  border: 'none', cursor: 'pointer', textAlign: 'left', color: '#181816',
                }}>
                  <span className="hp-meta">{String(HOME_PROGRAMS.indexOf(p) + 1).padStart(2, '0')}</span>
                  <span className="hp-display-sm">{p.name}</span>
                  <span className="hp-body" style={{ color: '#4A4A48' }}>{p.kicker}</span>
                  <span className="hp-meta" style={{ color: '#1A2DC4' }}>{p.tag}</span>
                  <span style={{ display: 'flex', justifyContent: 'flex-end', color: '#1A2DC4' }}>
                    {isOpen ? <HPIcon.Minus size={20} /> : <HPIcon.Plus size={20} />}
                  </span>
                </button>
                {isOpen && (
                  <div className="hp-grid-2" style={{ padding: '0 0 48px 104px', display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 48 }}>
                    <div>
                      <p className="hp-body-lg" style={{ marginBottom: 24, color: '#181816' }}>{p.copy}</p>
                      <ul className="hp-grid-2" style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        {p.bullets.map(b => (
                          <li key={b} style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                            <span style={{ color: '#1A2DC4' }}><HPIcon.Check size={14} /></span>
                            <span className="hp-body-sm" style={{ color: '#181816' }}>{b}</span>
                          </li>
                        ))}
                      </ul>
                      <div style={{ display: 'flex', gap: 12, marginTop: 28 }}>
                        <a href={HOME_PREFIX + programLinks[p.id]} className="hp-btn hp-btn-dark">Read more <HPIcon.Arrow size={14} /></a>
                        <a href={HOME_PREFIX + 'free-trial.html'} className="hp-btn hp-btn-primary">Try {p.short} free <HPIcon.Arrow size={14} /></a>
                      </div>
                    </div>
                    <div className={`hp-photo hp-photo-${p.id === 'kick' ? 'mat' : p.id === 'kids' ? 'kid' : 'bjj'}`} style={{ aspectRatio: '4 / 3' }}>
                      <span className="hp-photo-label">photo · {p.name}</span>
                    </div>
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

function HomeScheduleTeaser() {
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const [active, setActive] = React.useState('Mon');
  const list = HOME_SCHEDULE[active] || [];
  return (
    <section id="schedule" className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-row-flex" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 56 }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 16 }}>This week on the mat</div>
            <h2 className="hp-display-lg">Class schedule</h2>
          </div>
          <a href={HOME_PREFIX + 'schedule.html'} className="hp-btn hp-btn-ghost">Full week + Beloit <HPIcon.Arrow size={14} /></a>
        </div>
        <div style={{ display: 'flex', gap: 0, borderBottom: '1px solid rgba(10,10,10,0.15)', marginBottom: 8, overflowX: 'auto' }}>
          {days.map(d => {
            const count = (HOME_SCHEDULE[d] || []).length;
            const isActive = active === d;
            return (
              <button key={d} onClick={() => setActive(d)} style={{
                background: 'transparent', border: 'none', cursor: 'pointer', flexShrink: 0,
                padding: '20px 28px', borderBottom: isActive ? '2px solid #1A2DC4' : '2px solid transparent',
                marginBottom: -1, display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 4,
                color: isActive ? '#1A2DC4' : '#181816',
              }}>
                <span style={{ fontFamily: 'Fraunces, serif', fontSize: 22, fontWeight: 600 }}>{d}</span>
                <span style={{ fontFamily: 'Menlo, monospace', fontSize: 11, color: isActive ? '#1A2DC4' : '#9A9A98' }}>{count} classes</span>
              </button>
            );
          })}
        </div>
        <div>
          {list.length === 0 ? (
            <div style={{ padding: '80px 0', textAlign: 'center' }}><p className="hp-body-lg">No classes scheduled.</p></div>
          ) : list.map((c, i) => {
            const colors = { bjj: '#1A2DC4', kick: '#B26200', kids: '#2B8A5F' };
            return (
              <div key={i} className="hp-schedule-row" style={{
                display: 'grid', gridTemplateColumns: '120px 2fr 1fr 1fr 140px',
                gap: 24, padding: '24px 0', alignItems: 'center',
                borderBottom: '1px solid rgba(10,10,10,0.08)',
              }}>
                <span style={{ fontFamily: 'Menlo, monospace', fontSize: 18, color: '#0A0A0A' }}>{c.time}</span>
                <span className="hp-h-md">{c.name}</span>
                <span className="hp-schedule-cell-hide" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ width: 8, height: 8, borderRadius: '50%', background: colors[c.kind] }} />
                  <span className="hp-body-sm" style={{ color: '#181816' }}>{c.kind === 'bjj' ? 'Jiu-Jitsu' : c.kind === 'kick' ? 'Kickboxing' : 'Kids'}</span>
                </span>
                <span className="hp-body-sm hp-schedule-cell-hide">{c.who}</span>
                <a href={HOME_PREFIX + 'free-trial.html'} className="hp-schedule-cell-hide" style={{ justifySelf: 'end', fontSize: 13, fontWeight: 600, color: '#1A2DC4', display: 'flex', alignItems: 'center', gap: 6 }}>
                  Drop in <HPIcon.Arrow size={12} />
                </a>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}

function HomeInstructors() {
  return (
    <section id="about" className="hp-section" style={{ background: '#0A0A0A', color: '#F6F4EE', padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 64, marginBottom: 80 }}>
          <div className="hp-eyebrow" style={{ color: '#9A9A98' }}>The team · 04 coaches</div>
          <h2 className="hp-display-lg" style={{ color: '#F6F4EE' }}>
            Continuously training.<br />
            <em style={{ fontStyle: 'italic', fontWeight: 400, color: '#9A9A98' }}>Continuously coaching.</em>
          </h2>
        </div>
        <div className="hp-grid-4" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 24 }}>
          {HOME_INSTRUCTORS.map((p, i) => (
            <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div className="hp-photo" style={{ aspectRatio: '4 / 5', background: 'linear-gradient(180deg, #2A2825, #0A0A0A)' }}>
                <span className="hp-photo-label">{p.name}</span>
              </div>
              <div>
                <h3 className="hp-h-md" style={{ color: '#F6F4EE' }}>{p.name}</h3>
                <div className="hp-body-sm" style={{ color: '#9A9A98', marginTop: 6 }}>{p.title}</div>
                <div className="hp-meta" style={{ marginTop: 14, color: '#1A2DC4' }}>{p.belt}</div>
              </div>
            </div>
          ))}
        </div>
        <div style={{ marginTop: 56 }}>
          <a href={HOME_PREFIX + 'about.html'} className="hp-btn" style={{ background: 'transparent', color: '#F6F4EE', border: '1px solid rgba(255,255,255,0.3)' }}>Meet the team <HPIcon.Arrow size={14} /></a>
        </div>
      </div>
    </section>
  );
}

function HomeReviews() {
  return (
    <section id="reviews" className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 64, marginBottom: 80, alignItems: 'flex-end' }}>
          <div>
            <div className="hp-eyebrow" style={{ marginBottom: 16 }}>Real stories from real students</div>
            <h2 className="hp-display-lg">Our students<br /><em style={{ fontStyle: 'italic', fontWeight: 400, color: '#1A2DC4' }}>say it best.</em></h2>
          </div>
          <p className="hp-body-lg" style={{ maxWidth: 420 }}>
            From parents who saw their kids transform, to adults who came for fitness and stayed
            for the community — these are unedited Google reviews.
          </p>
        </div>
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 24 }}>
          {HOME_REVIEWS.map((r, i) => (
            <figure key={i} style={{ margin: 0, padding: 40, background: '#fff', border: '1px solid rgba(10,10,10,0.08)', borderRadius: 4 }}>
              <div style={{ display: 'flex', gap: 2, color: '#1A2DC4', marginBottom: 20 }}>
                {[1,2,3,4,5].map(i => <HPIcon.Star key={i} size={14} />)}
              </div>
              <blockquote style={{ margin: 0 }}>
                <p style={{ fontFamily: 'Fraunces, serif', fontSize: 24, lineHeight: 1.4, fontWeight: 500 }}>&ldquo;{r.quote}&rdquo;</p>
              </blockquote>
              <figcaption style={{ marginTop: 24, display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                <span style={{ fontWeight: 600 }}>{r.who}</span>
                <span className="hp-meta">{r.context}</span>
              </figcaption>
            </figure>
          ))}
        </div>
      </div>
    </section>
  );
}

function HomeLeadCapture() {
  return (
    <section id="trial" style={{ padding: '120px 0', background: '#1A2DC4', color: '#fff', position: 'relative', overflow: 'hidden' }}>
      <div style={{ position: 'absolute', left: '-10%', bottom: '-20%', opacity: 0.08 }}>
        <HPGlyph size={720} color="#fff" />
      </div>
      <div className="hp-container-wide" style={{ position: 'relative', zIndex: 1 }}>
        <div className="hp-eyebrow" style={{ color: 'rgba(255,255,255,0.7)', marginBottom: 24 }}>Step 1 · It's free</div>
        <h2 className="hp-hero-h1" style={{ fontFamily: 'Fraunces, serif', fontSize: 156, lineHeight: 0.86, letterSpacing: '-0.04em', fontWeight: 700, marginBottom: 48, color: '#fff' }}>
          Walk in.<br /><span style={{ fontStyle: 'italic', fontWeight: 500 }}>That's the hard part.</span>
        </h2>
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 64, alignItems: 'flex-start' }}>
          <div>
            <p style={{ fontFamily: 'Fraunces, serif', fontSize: 28, lineHeight: 1.4, fontStyle: 'italic', fontWeight: 400, color: 'rgba(255,255,255,0.85)', marginBottom: 32 }}>
              Your first class is free. Bring nothing but yourself.
            </p>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 12, color: 'rgba(255,255,255,0.85)' }}>
              {['Loaner gi & gloves on the house', 'No fitness prerequisite — beginners welcome', 'Same-day text confirmation', 'Cancel anytime, no upsell'].map(b => (
                <li key={b} style={{ display: 'flex', gap: 12, alignItems: 'center', fontSize: 16 }}>
                  <span style={{ fontFamily: 'Menlo, monospace', fontSize: 12 }}>+</span>{b}
                </li>
              ))}
            </ul>
            <a href={HOME_PREFIX + 'free-trial.html'} className="hp-btn hp-btn-lg" style={{ marginTop: 40, background: '#fff', color: '#1A2DC4', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 700 }}>
              Pick a date & time <HPIcon.Arrow size={16} />
            </a>
          </div>
          <div style={{ background: '#0A0A0A', padding: 40, color: '#fff' }}>
            <div className="hp-eyebrow" style={{ color: '#9A9A98', marginBottom: 20 }}>Or just leave your number</div>
            <p className="hp-body" style={{ color: 'rgba(255,255,255,0.7)', marginBottom: 24 }}>We'll text you to set up a class within the hour.</p>
            <form onSubmit={e => { e.preventDefault(); window.location.href = HOME_PREFIX + 'free-trial.html'; }}>
              <label style={{ display: 'block', marginBottom: 16 }}>
                <div className="hp-meta" style={{ color: '#9A9A98', marginBottom: 8 }}>Your name</div>
                <input className="hp-input hp-input-dark" placeholder="Alex Garcia" />
              </label>
              <label style={{ display: 'block', marginBottom: 24 }}>
                <div className="hp-meta" style={{ color: '#9A9A98', marginBottom: 8 }}>Phone</div>
                <input className="hp-input hp-input-dark" placeholder="(815) 000-0000" />
              </label>
              <button type="submit" className="hp-btn hp-btn-lg" style={{ width: '100%', background: '#1A2DC4', color: '#fff', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 700 }}>
                Text me to confirm <HPIcon.Arrow size={16} />
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>
  );
}

function HomeLocations() {
  return (
    <section id="locations" className="hp-section" style={{ padding: '120px 0' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 64, marginBottom: 64 }}>
          <div className="hp-eyebrow">Two locations · One school</div>
          <h2 className="hp-display-md">Train where it works for your family.</h2>
        </div>
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 }}>
          {HP_DATA.locations.map(l => (
            <div key={l.id} style={{
              border: '1px solid rgba(10,10,10,0.12)', padding: 32,
              background: l.primary ? '#0A0A0A' : '#fff', color: l.primary ? '#F6F4EE' : '#181816',
            }}>
              <div className="hp-meta" style={{ color: '#9A9A98' }}>{l.primary ? '★ Headquarters' : 'Satellite'}</div>
              <h3 className="hp-display-sm" style={{ marginTop: 12, color: 'inherit' }}>{l.city}</h3>
              <div style={{ marginTop: 24, display: 'grid', gap: 6 }}>
                <span className="hp-body" style={{ color: l.primary ? '#F6F4EE' : '#181816' }}>{l.addr}</span>
                <span className="hp-body-sm" style={{ color: l.primary ? '#9A9A98' : '#4A4A48' }}>{l.zip}</span>
                <span style={{ fontFamily: 'Menlo, monospace', fontSize: 13, marginTop: 12 }}>{l.phone}</span>
              </div>
              <div style={{ display: 'flex', gap: 12, marginTop: 32, flexWrap: 'wrap' }}>
                <a href={HOME_PREFIX + 'locations.html#' + l.id} className="hp-btn hp-btn-sm" style={{ background: l.primary ? '#1A2DC4' : '#0A0A0A', color: '#fff' }}>Visit page <HPIcon.Arrow size={12} /></a>
                <a href={HOME_PREFIX + 'schedule.html'} className="hp-btn hp-btn-sm" style={{ background: 'transparent', color: l.primary ? '#F6F4EE' : '#181816', border: '1px solid ' + (l.primary ? 'rgba(255,255,255,0.2)' : 'rgba(10,10,10,0.18)') }}>See schedule</a>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function HomePage() {
  return (
    <PageShell current="home">
      <HomeHero />
      <HomeValues />
      <HomeProgramsAccordion />
      <HomeScheduleTeaser />
      <HomeInstructors />
      <HomeReviews />
      <HomeLeadCapture />
      <HomeLocations />
    </PageShell>
  );
}

window.HomePage = HomePage;
