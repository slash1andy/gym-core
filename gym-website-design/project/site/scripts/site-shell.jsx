// Shared site shell — nav + footer + page wrapper used by every page.
// Reads color-mode from Tweaks (persisted via __edit_mode_set_keys).

const NAV_ITEMS = [
  { label: 'Programs', href: 'programs/bjj.html', children: [
    { label: 'Brazilian Jiu-Jitsu', href: 'programs/bjj.html' },
    { label: 'Kids Jiu-Jitsu', href: 'programs/kids.html' },
    { label: 'Fitness Kickboxing', href: 'programs/kickboxing.html' },
  ]},
  { label: 'Schedule', href: 'schedule.html' },
  { label: 'Locations', href: 'locations.html' },
  { label: 'About', href: 'about.html' },
  { label: 'Contact', href: 'contact.html' },
];

// Compute href prefix based on current location depth.
// Pages in /programs/* need to walk up one directory.
function pathPrefix() {
  const p = window.location.pathname;
  return /\/programs\//.test(p) ? '../' : '';
}

function SiteNav({ current = '' }) {
  const [openMobile, setOpenMobile] = React.useState(false);
  const [progOpen, setProgOpen] = React.useState(false);
  const prefix = pathPrefix();
  return (
    <header className="hp-nav" style={{
      position: 'sticky', top: 0, zIndex: 30, background: 'rgba(246,244,238,0.92)',
      backdropFilter: 'blur(10px)', borderBottom: '1px solid rgba(10,10,10,0.08)',
    }}>
      <div className="hp-container-wide" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', height: 76 }}>
        <a href={prefix + 'index.html'} className="hp-mark">
          <span className="hp-mark-dot"><HPGlyph size={20} color="#fff" /></span>
          <span className="hp-mark-text">Haanpaa<small>Martial Arts</small></span>
        </a>
        <nav className="hp-nav-links" style={{ display: 'flex', gap: 36, fontSize: 14, fontWeight: 500 }}>
          {NAV_ITEMS.map(item => {
            const isCurrent = current === item.label.toLowerCase() ||
              (item.children && item.children.some(c => c.href.includes(current)));
            if (item.children) {
              return (
                <div key={item.label} style={{ position: 'relative' }}
                  onMouseEnter={() => setProgOpen(true)}
                  onMouseLeave={() => setProgOpen(false)}>
                  <a href={prefix + item.href} style={{
                    color: '#181816', opacity: isCurrent ? 1 : 0.85,
                    borderBottom: isCurrent ? '2px solid #1A2DC4' : '2px solid transparent',
                    paddingBottom: 4,
                  }}>{item.label}</a>
                  {progOpen && (
                    <div style={{
                      position: 'absolute', top: '100%', left: -16, marginTop: 8,
                      background: '#fff', border: '1px solid rgba(10,10,10,0.08)',
                      padding: '12px 0', minWidth: 240, boxShadow: '0 8px 24px rgba(0,0,0,0.08)',
                    }}>
                      {item.children.map(c => (
                        <a key={c.href} href={prefix + c.href} style={{
                          display: 'block', padding: '10px 20px', fontSize: 14, color: '#181816',
                        }}>{c.label}</a>
                      ))}
                    </div>
                  )}
                </div>
              );
            }
            return (
              <a key={item.label} href={prefix + item.href} style={{
                color: '#181816', opacity: isCurrent ? 1 : 0.85,
                borderBottom: isCurrent ? '2px solid #1A2DC4' : '2px solid transparent',
                paddingBottom: 4,
              }}>{item.label}</a>
            );
          })}
        </nav>
        <div className="hp-nav-cta" style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
          <a href={`tel:${HP_DATA.phone}`} style={{ fontFamily: 'Menlo, monospace', fontSize: 13, color: '#4A4A48' }}>{HP_DATA.phone}</a>
          <a href={prefix + 'free-trial.html'} className="hp-btn hp-btn-dark" style={{ padding: '12px 18px', fontSize: 13 }}>
            Book free trial <HPIcon.Arrow size={14} />
          </a>
        </div>
        <button className="hp-nav-burger" aria-label="Menu" onClick={() => setOpenMobile(!openMobile)} style={{
          display: 'none', background: 'transparent', border: 'none', cursor: 'pointer', padding: 8,
        }}>
          <HPIcon.Menu size={22} />
        </button>
      </div>
      {openMobile && (
        <div className="hp-nav-mobile" style={{ background: '#fff', borderTop: '1px solid rgba(10,10,10,0.08)', padding: '20px 0' }}>
          <div className="hp-container-wide" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            {NAV_ITEMS.flatMap(item => item.children ? [item, ...item.children.map(c => ({ ...c, indent: true }))] : [item]).map(item => (
              <a key={item.label} href={prefix + item.href} style={{
                padding: '14px 0', fontSize: item.indent ? 15 : 17, fontWeight: item.indent ? 400 : 600,
                paddingLeft: item.indent ? 20 : 0, color: '#181816',
                borderBottom: '1px solid rgba(10,10,10,0.06)',
              }}>{item.label}</a>
            ))}
            <a href={prefix + 'free-trial.html'} className="hp-btn hp-btn-primary hp-btn-lg" style={{ marginTop: 16, justifyContent: 'center' }}>
              Book free trial <HPIcon.Arrow size={14} />
            </a>
          </div>
        </div>
      )}
    </header>
  );
}

function SiteFooter() {
  const prefix = pathPrefix();
  const cols = [
    { t: 'Programs', l: [
      { label: 'Brazilian Jiu-Jitsu', href: 'programs/bjj.html' },
      { label: 'Kids Jiu-Jitsu', href: 'programs/kids.html' },
      { label: 'Fitness Kickboxing', href: 'programs/kickboxing.html' },
    ]},
    { t: 'Visit', l: [
      { label: 'HMA Rockford', href: 'locations.html#rockford' },
      { label: 'HMA Beloit', href: 'locations.html#beloit' },
      { label: 'Schedule', href: 'schedule.html' },
      { label: 'Free trial', href: 'free-trial.html' },
    ]},
    { t: 'Connect', l: [
      { label: 'About', href: 'about.html' },
      { label: 'Coaches', href: 'about.html#coaches' },
      { label: 'Contact', href: 'contact.html' },
    ]},
  ];
  return (
    <footer style={{ background: '#0A0A0A', color: '#F6F4EE', padding: '80px 0 32px' }}>
      <div className="hp-container-wide">
        <div className="hp-footer-grid" style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr', gap: 48, marginBottom: 64 }}>
          <div>
            <div className="hp-mark">
              <span className="hp-mark-dot" style={{ background: '#fff' }}><HPGlyph size={20} color="#0A0A0A" /></span>
              <span className="hp-mark-text" style={{ color: '#F6F4EE' }}>Haanpaa<small style={{ color: '#9A9A98' }}>Martial Arts</small></span>
            </div>
            <p className="hp-body-sm" style={{ color: '#9A9A98', marginTop: 24, maxWidth: 320 }}>
              {HP_DATA.affiliated}. Teaching Brazilian Jiu-Jitsu (adults & kids) and Fitness Kickboxing in Rockford and Beloit.
            </p>
            <div style={{ display: 'flex', gap: 12, marginTop: 24, color: '#F6F4EE' }}>
              <HPIcon.Instagram /> <HPIcon.Facebook /> <HPIcon.Youtube />
            </div>
          </div>
          {cols.map(c => (
            <div key={c.t}>
              <div className="hp-meta" style={{ color: '#9A9A98', marginBottom: 16 }}>{c.t}</div>
              <ul style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: 0, margin: 0, listStyle: 'none' }}>
                {c.l.map(x => <li key={x.label}><a href={prefix + x.href} style={{ fontSize: 14, color: '#F6F4EE' }}>{x.label}</a></li>)}
              </ul>
            </div>
          ))}
        </div>
        <div style={{ paddingTop: 24, borderTop: '1px solid rgba(255,255,255,0.1)', display: 'flex', justifyContent: 'space-between', fontSize: 12, color: '#9A9A98', flexWrap: 'wrap', gap: 12 }}>
          <span>© 2026 Haanpaa Martial Arts · {HP_DATA.address}</span>
          <span style={{ fontFamily: 'Menlo, monospace' }}>{HP_DATA.affiliated}</span>
        </div>
      </div>
    </footer>
  );
}

function PageShell({ current, children }) {
  return (
    <div className="hp-site">
      <SiteNav current={current} />
      <main>{children}</main>
      <SiteFooter />
    </div>
  );
}

Object.assign(window, { SiteNav, SiteFooter, PageShell, pathPrefix });
