// Locations page — Rockford HQ + Beloit satellite, hours, directions.

function LocationsHero() {
  return (
    <section style={{ padding: '80px 0 64px', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-hero-grid" style={{ display: 'grid', gridTemplateColumns: '1.6fr 1fr', gap: 48, alignItems: 'end' }}>
          <h1 className="hp-display-xl">Two mats.<br /><em style={{ fontStyle: 'italic', fontWeight: 500, color: '#9A9A98' }}>One school.</em></h1>
          <p className="hp-body-lg" style={{ maxWidth: 420 }}>
            Our flagship is in Rockford, where the full schedule runs. Beloit is a focused satellite for BJJ and Kids classes — closer to home for our Wisconsin members.
          </p>
        </div>
      </div>
    </section>
  );
}

function LocationCard({ id, primary, city, addr, zip, phone, hours, programs, note }) {
  return (
    <section id={id} className="hp-section" style={{ padding: '120px 0', borderBottom: '1px solid rgba(10,10,10,0.08)' }}>
      <div className="hp-container-wide">
        <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 64 }}>
          <div>
            <div className="hp-meta" style={{ color: primary ? '#1A2DC4' : '#4A4A48', marginBottom: 16 }}>
              {primary ? '★ Headquarters' : 'Satellite location'}
            </div>
            <h2 className="hp-display-lg" style={{ marginBottom: 24 }}>{city}</h2>
            <div style={{ display: 'grid', gap: 8, marginBottom: 32 }}>
              <span className="hp-body-lg" style={{ color: '#181816' }}>{addr}</span>
              <span className="hp-body" style={{ color: '#4A4A48' }}>{zip}</span>
              <a href={`tel:${phone}`} style={{ fontFamily: 'Menlo, monospace', fontSize: 14, marginTop: 8, color: '#1A2DC4', fontWeight: 600 }}>{phone}</a>
            </div>
            <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginBottom: 32 }}>
              <a href="free-trial.html" className="hp-btn hp-btn-primary">Book free trial <HPIcon.Arrow size={14} /></a>
              <a href="schedule.html" className="hp-btn hp-btn-ghost">See schedule</a>
              <a href="#" className="hp-btn hp-btn-ghost"><HPIcon.Map size={14} /> Directions</a>
            </div>
            {note && (
              <p className="hp-body-sm" style={{ padding: 16, background: '#EFEBE1', borderLeft: '3px solid #1A2DC4', color: '#181816' }}>{note}</p>
            )}
          </div>
          <div>
            <div className={'hp-photo hp-photo-' + (primary ? 'mat' : 'bjj')} style={{ aspectRatio: '16 / 10', marginBottom: 32 }}>
              <span className="hp-photo-label">photo · {city} mat</span>
            </div>
            <div className="hp-grid-2" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 32 }}>
              <div>
                <div className="hp-meta" style={{ marginBottom: 16, color: '#1A2DC4' }}>Hours</div>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                  <tbody>
                    {hours.map(([d, h]) => (
                      <tr key={d}>
                        <td style={{ padding: '8px 0', fontSize: 14, color: '#181816', borderBottom: '1px solid rgba(10,10,10,0.06)' }}>{d}</td>
                        <td style={{ padding: '8px 0', fontSize: 14, fontFamily: 'Menlo, monospace', color: '#4A4A48', textAlign: 'right', borderBottom: '1px solid rgba(10,10,10,0.06)' }}>{h}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div>
                <div className="hp-meta" style={{ marginBottom: 16, color: '#1A2DC4' }}>Programs</div>
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'grid', gap: 12 }}>
                  {programs.map(p => (
                    <li key={p} style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                      <span style={{ color: '#1A2DC4' }}><HPIcon.Check size={14} /></span>
                      <span className="hp-body-sm" style={{ color: '#181816' }}>{p}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function LocationsCTA() {
  return (
    <section style={{ padding: '96px 0', background: '#0A0A0A', color: '#F6F4EE' }}>
      <div className="hp-container-wide" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 24 }}>
        <div>
          <div className="hp-eyebrow" style={{ color: '#9A9A98', marginBottom: 12 }}>Not sure which location?</div>
          <h2 className="hp-display-md" style={{ color: '#F6F4EE', maxWidth: 720 }}>Call us. We'll figure it out together.</h2>
        </div>
        <a href={`tel:${HP_DATA.phone}`} className="hp-btn hp-btn-lg" style={{ background: '#1A2DC4', color: '#fff', fontWeight: 700 }}>
          <HPIcon.Phone size={16} /> {HP_DATA.phone}
        </a>
      </div>
    </section>
  );
}

function LocationsPage() {
  const ROCKFORD_HOURS = [['Mon', '6a–9p'], ['Tue', '6a–9p'], ['Wed', '6a–9p'], ['Thu', '6a–9p'], ['Fri', '6a–8p'], ['Sat', '9a–12p'], ['Sun', 'Closed']];
  const BELOIT_HOURS  = [['Mon', '5p–8p'], ['Tue', '—'], ['Wed', '5p–8p'], ['Thu', '—'], ['Fri', '—'], ['Sat', '10a–12p'], ['Sun', 'Closed']];
  return (
    <PageShell current="locations">
      <LocationsHero />
      <LocationCard
        id="rockford"
        primary={true}
        city="Rockford"
        addr="4911 26th Avenue"
        zip="Rockford, IL 61109"
        phone={HP_DATA.phone}
        hours={ROCKFORD_HOURS}
        programs={['Brazilian Jiu-Jitsu', 'Fitness Kickboxing', 'Kids Jiu-Jitsu', 'Open mat Saturdays']}
        note="Headquarters — full schedule runs here. Free trial classes are booked by default at this location unless you specify Beloit."
      />
      <LocationCard
        id="beloit"
        primary={false}
        city="Beloit"
        addr="HMA Beloit"
        zip="Beloit, WI"
        phone={HP_DATA.phone}
        hours={BELOIT_HOURS}
        programs={['Brazilian Jiu-Jitsu Fundamentals', 'Kids Jiu-Jitsu', 'Saturday family open mat']}
        note="Beloit is a focused satellite. Confirm class times with the front desk before dropping in."
      />
      <LocationsCTA />
    </PageShell>
  );
}
window.LocationsPage = LocationsPage;
