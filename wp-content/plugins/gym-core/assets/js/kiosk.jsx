// Haanpaa kiosk check-in — tablet/mobile.
// Adapted from the gym-website-design prototype.
// Data injected by KioskEndpoint::build_kiosk_data() via window.gymKiosk.

// ---------------------------------------------------------------------------
// Data + API shape
// ---------------------------------------------------------------------------
// window.gymKiosk (injected by PHP):
//   restUrl      — REST base URL
//   nonce        — wp_rest nonce
//   location     — location slug
//   members      — array of { id, first, last, kind, program, belt }
//   nextClass    — format_class() payload for the next/current class today (nullable)
//   todayClasses — array of format_class() payloads for all classes today
//   todayCount   — integer count of check-ins today
//
// format_class() shape (ClassScheduleController::format_class):
//   { id, name, description, program, instructor: {id, name}|null,
//     day_of_week, start_time, end_time, capacity, recurrence, status, location }
// ---------------------------------------------------------------------------

const {
  members: KIOSK_MEMBERS = [],
  nextClass: rawNextClass = null,
  todayClasses: rawTodayClasses = [],
  todayCount: initialTodayCount = 0,
} = window.gymKiosk || {};

function dayOfWeekFromDate(d) {
  return ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'][d.getDay()];
}

// Resolve a "HH:MM" + day_of_week into the next concrete Date for that slot.
function resolveClassDate(cls, now = new Date()) {
  const [h, m] = String(cls.start_time || '00:00').split(':').map(n => parseInt(n, 10));
  const target = new Date(now);
  target.setHours(h, m, 0, 0);
  const [eh, em] = String(cls.end_time || cls.start_time || '00:00').split(':').map(n => parseInt(n, 10));
  const end = new Date(now); end.setHours(eh, em, 0, 0);
  if (now > end && cls.recurrence === 'weekly') target.setDate(target.getDate() + 7);
  return target;
}

// Adapter: format_class() REST payload → kiosk view-model.
function adaptCptClass(cls) {
  const start = resolveClassDate(cls);
  const durationMin = (() => {
    if (!cls.end_time) return 60;
    const [sh, sm] = cls.start_time.split(':').map(Number);
    const [eh, em] = cls.end_time.split(':').map(Number);
    return Math.max(15, (eh * 60 + em) - (sh * 60 + sm));
  })();
  // instructor.name (real API) vs instructor.display_name (old mock) — handle both.
  const instructorFullName = cls.instructor?.name || cls.instructor?.display_name || '';
  return {
    id: cls.id,
    start,
    durationMin,
    capacity: cls.capacity,
    status: cls.status || 'active',
    date: start.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }),
    time: start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }),
    name: cls.name,                           // format_class() uses 'name', not 'title'
    who:  (cls.description || cls.program || '').replace(/<[^>]+>/g, '').trim(), // strip wp_kses_post HTML
    coach: instructorFullName,
  };
}

const TONIGHT     = rawNextClass    ? adaptCptClass(rawNextClass)     : null;
const TODAY_CLASSES = rawTodayClasses.map(adaptCptClass);

// ---------------------------------------------------------------------------
// Taglines
// ---------------------------------------------------------------------------
const ADULT_TAGLINES = [
  { weight: 50, text: "You showed up. That's the whole thing." },
  { weight: 17, text: 'One more rep on the streak.' },
  { weight: 17, text: 'Train like it matters.' },
  { weight: 16, text: 'See you on the mat.' },
];

const KID_TAGLINES = [
  { weight: 25, text: 'Good respects. Listen close. Have fun on the mats.' },
  { weight: 25, text: 'Big effort. Big smiles. Go get it.' },
  { weight: 25, text: "Tie your belt. You've got this." },
  { weight: 25, text: 'Coach is ready for you. Let’s train.' },
];

function pickWeighted(options) {
  const total = options.reduce((sum, o) => sum + o.weight, 0);
  let r = Math.random() * total;
  for (const o of options) {
    r -= o.weight;
    if (r <= 0) return o.text;
  }
  return options[options.length - 1].text;
}

function taglineFor(kind) {
  return pickWeighted(kind === 'kid' ? KID_TAGLINES : ADULT_TAGLINES);
}

// ---------------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------------
function classStatus(now, start, durationMin) {
  const startMs = start.getTime();
  const endMs = startMs + durationMin * 60 * 1000;
  const diffMin = (startMs - now.getTime()) / 60000;
  if (now.getTime() >= startMs && now.getTime() < endMs) return 'live';
  if (now.getTime() >= endMs && now.getTime() < endMs + 30 * 60000) return 'ended';
  if (diffMin > 0 && diffMin <= 10) return 'imminent';
  return 'next';
}

function statusLabel(status) {
  switch (status) {
    case 'cancelled': return { text: 'Class cancelled', dot: '×', color: 'var(--k-rust)',     live: false };
    case 'live':      return { text: 'On the mat now',  dot: '●', color: 'var(--k-moss)',     live: true  };
    case 'ended':     return { text: 'Just ended',      dot: '○', color: 'var(--k-ink-mute)', live: false };
    case 'imminent':  return { text: 'Starting soon',   dot: '●', color: 'var(--k-rust)',     live: true  };
    default:          return { text: 'Next class',      dot: '▸', color: 'var(--k-blue)',     live: false };
  }
}

function useClock(intervalMs = 30000) {
  const [now, setNow] = React.useState(new Date());
  React.useEffect(() => {
    const t = setInterval(() => setNow(new Date()), intervalMs);
    return () => clearInterval(t);
  }, [intervalMs]);
  return now;
}

const fmtTime = d => d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
const fmtDate = d => d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });

function highlight(name, q) {
  if (!q) return name;
  const i = name.toLowerCase().indexOf(q.toLowerCase());
  if (i < 0) return name;
  return (
    <React.Fragment>
      {name.slice(0, i)}<mark>{name.slice(i, i + q.length)}</mark>{name.slice(i + q.length)}
    </React.Fragment>
  );
}

const initials = (f, l) => ((f || '')[0] || '') + ((l || '')[0] || '');

// ---------------------------------------------------------------------------
// Components
// ---------------------------------------------------------------------------

function TopBar() {
  const now = useClock();
  return (
    <header className="k-topbar">
      <a href="/" className="k-mark">
        <span className="k-mark-dot"><HPGlyph size={20} color="#fff" /></span>
        <span className="k-mark-text">Haanpaa<small>Rockford · Check-in</small></span>
      </a>
      <div style={{ display: 'flex', alignItems: 'center', gap: 20 }}>
        <span className="k-clock">{fmtDate(now)}</span>
        <span className="k-clock" style={{ color: 'var(--k-ink)' }}>{fmtTime(now)}</span>
      </div>
    </header>
  );
}

function TonightCard({ cls }) {
  const now = useClock(15000);
  if (!cls) {
    return (
      <div className="k-tonight" style={{ borderLeftColor: 'var(--k-ink-mute)' }}>
        <div>
          <div className="k-tonight-eyebrow" style={{ color: 'var(--k-ink-mute)' }}>
            <span style={{ marginRight: 6 }}>—</span>No class scheduled today
          </div>
          <div className="k-tonight-name">No classes today</div>
          <div className="k-tonight-meta">Check the schedule for upcoming classes</div>
        </div>
        <div className="k-tonight-time">
          <small>Today</small>—
        </div>
      </div>
    );
  }
  const timeStatus = classStatus(now, cls.start, cls.durationMin);
  const status = cls.status === 'cancelled' ? 'cancelled' : timeStatus;
  const lbl = statusLabel(status);
  return (
    <div className="k-tonight" style={{ borderLeftColor: lbl.color }}>
      <div>
        <div className="k-tonight-eyebrow" style={{ color: lbl.color }}>
          <span className={lbl.live ? 'k-live-dot' : ''} style={{ marginRight: 6 }}>{lbl.dot}</span>{lbl.text}
        </div>
        <div className="k-tonight-name">{cls.name}</div>
        <div className="k-tonight-meta">{cls.who} · {cls.coach}</div>
      </div>
      <div className="k-tonight-time">
        <small>{cls.date}</small>
        {cls.time}
      </div>
    </div>
  );
}

function ResultRow({ m, q, onPick }) {
  const tagClass = 'k-tag ' + (m.kind === 'kid' ? 'k-tag-kid' : 'k-tag-adult');
  const tagText = m.kind === 'kid' ? 'Kids' : 'Adult';
  return (
    <button className="k-row" data-kind={m.kind} onClick={() => onPick(m)}>
      <span className="k-avatar">{initials(m.first, m.last)}</span>
      <span>
        <span className="k-name">
          {highlight(m.first, q)} {highlight(m.last, q)}
        </span>
        <span className="k-meta-line">
          <span className={tagClass}>{tagText}</span>
          <span>·</span>
          <span>{m.program}</span>
          {m.belt && m.belt !== '—' && (
            <React.Fragment><span>·</span><span style={{ color: '#9A9A98' }}>{m.belt}</span></React.Fragment>
          )}
        </span>
      </span>
      <span className="k-row-arrow"><HPIcon.Arrow size={14} /></span>
    </button>
  );
}

function ClassPicker({ member, todayClasses, onPick, onCancel }) {
  // Auto-advance if there's exactly one class.
  React.useEffect(() => {
    if (todayClasses.length === 1) {
      onPick(todayClasses[0]);
    }
  }, []);

  if (todayClasses.length === 1) return null; // handled by effect above

  const noClasses = todayClasses.length === 0;

  return (
    <div className="k-class-picker" role="dialog" aria-label="Select your class">
      <button className="k-cp-back" onClick={onCancel}>← Back</button>
      <div className="k-cp-header">
        <div className="k-cp-eyebrow">Select your class</div>
        <h2 className="k-cp-title">
          {member.first}, which class<br />are you here for?
        </h2>
      </div>

      {noClasses ? (
        <div className="k-cp-list">
          <p style={{ color: 'rgba(255,255,255,0.6)', fontSize: 15, textAlign: 'center', margin: 0 }}>
            No classes scheduled for today. See front desk to record your visit.
          </p>
        </div>
      ) : (
        <div className="k-cp-list">
          {todayClasses.map(cls => (
            <button key={cls.id} className="k-cp-row" onClick={() => onPick(cls)}>
              <div className="k-cp-time">
                {cls.time}
              </div>
              <div>
                <div className="k-cp-name">{cls.name}</div>
                {cls.who && <div className="k-cp-who">{cls.who}{cls.coach ? ` · ${cls.coach}` : ''}</div>}
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function Confirmation({ confirmed, onUndo, secondsLeft }) {
  const { member, tagline, cls } = confirmed;
  return (
    <div className="k-confirm" role="dialog" aria-live="polite">
      <div className="k-confirm-glyph"><HPGlyph size={680} color="#fff" /></div>
      <div className="k-confirm-top">
        <span className="k-mark">
          <span className="k-mark-dot" style={{ background: '#fff' }}><HPGlyph size={20} color="#1A2DC4" /></span>
          <span className="k-mark-text" style={{ color: '#fff' }}>
            Haanpaa<small style={{ color: 'rgba(255,255,255,0.7)' }}>Checked in</small>
          </span>
        </span>
        <span style={{ fontFamily: 'var(--k-mono)', fontSize: 12, letterSpacing: '0.1em', textTransform: 'uppercase', color: 'rgba(255,255,255,0.7)' }}>
          {fmtTime(new Date())}
        </span>
      </div>

      <div className="k-confirm-body">
        <div className="k-confirm-eyebrow">You're in</div>
        <h1 className="k-confirm-name">
          Welcome,<br />
          <em>{member.first} {member.last}.</em>
        </h1>
        <p className="k-confirm-tag">{tagline}</p>
        {cls && (
          <div className="k-confirm-class">
            <span className="k-label">Class</span><span className="k-val">{cls.name}</span>
            <span className="k-label">When</span><span className="k-val">{cls.date} · {cls.time}</span>
            {cls.coach && <React.Fragment><span className="k-label">Coach</span><span className="k-val">{cls.coach}</span></React.Fragment>}
          </div>
        )}
      </div>

      <div className="k-confirm-bottom">
        <button className="k-undo" onClick={(e) => { e.stopPropagation(); onUndo(); }}>Not me — undo</button>
        <span className="k-confirm-counter">Returning in {secondsLeft}s · or tap anywhere</span>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main app
// ---------------------------------------------------------------------------

function KioskApp() {
  const [q, setQ]               = React.useState('');
  const [filter, setFilter]     = React.useState('all');
  const [pickerMember, setPickerMember] = React.useState(null);
  const [confirmed, setConfirmed]       = React.useState(null);
  const [secondsLeft, setSecondsLeft]   = React.useState(6);
  const [todayCount, setTodayCount]     = React.useState(initialTodayCount);
  const [attendanceId, setAttendanceId] = React.useState(null);
  const inputRef = React.useRef(null);

  // Re-focus search after confirmation dismisses.
  React.useEffect(() => {
    if (!confirmed && !pickerMember && inputRef.current) inputRef.current.focus();
  }, [confirmed, pickerMember]);

  // Auto-dismiss confirmed overlay after 6 seconds; tap anywhere also dismisses.
  React.useEffect(() => {
    if (!confirmed) return;
    setSecondsLeft(6);
    const t = setInterval(() => setSecondsLeft(s => s - 1), 1000);
    const done = setTimeout(() => { setConfirmed(null); setQ(''); }, 6000);
    const tapAnywhere = () => { clearInterval(t); clearTimeout(done); setConfirmed(null); setQ(''); };
    const id = setTimeout(() => document.addEventListener('click', tapAnywhere), 100);
    return () => {
      clearInterval(t); clearTimeout(done); clearTimeout(id);
      document.removeEventListener('click', tapAnywhere);
    };
  }, [confirmed]);

  // Fire the check-in POST when confirmation is shown.
  React.useEffect(() => {
    if (!confirmed) return;
    const { restUrl, nonce } = window.gymKiosk || {};
    if (!restUrl || !nonce || !confirmed.cls) return;

    fetch(restUrl + 'check-in', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
      body: JSON.stringify({
        user_id:  confirmed.member.id,
        class_id: confirmed.cls.id,
        method:   'name_search',
        location: (window.gymKiosk || {}).location || '',
      }),
    })
      .then(r => r.ok ? r.json() : Promise.reject(r))
      .then(data => {
        if (data?.data?.attendance_id) setAttendanceId(data.data.attendance_id);
      })
      .catch(() => { /* silent — check-in failure doesn't disrupt the kiosk UX */ });
  }, [confirmed]);

  function pick(member) {
    setPickerMember(member);
  }

  function pickClass(cls) {
    const member = pickerMember;
    setPickerMember(null);
    setConfirmed({ member, tagline: taglineFor(member.kind), cls });
    setTodayCount(c => c + 1);
    setAttendanceId(null);
  }

  function undo() {
    setConfirmed(null);
    setTodayCount(c => Math.max(0, c - 1));

    // TODO: implement DELETE /gym/v1/attendance/{id} in AttendanceController
    // to remove the record server-side on undo. For now this is client-only.
    setAttendanceId(null);
  }

  const results = React.useMemo(() => {
    const needle = q.trim().toLowerCase();
    return KIOSK_MEMBERS
      .filter(m => filter === 'all' || m.kind === filter)
      .filter(m => {
        if (!needle) return true;
        return (
          (m.first || '').toLowerCase().includes(needle) ||
          (m.last  || '').toLowerCase().includes(needle) ||
          ((m.first + ' ' + m.last).toLowerCase()).includes(needle)
        );
      })
      .sort((a, b) => (a.last || '').localeCompare(b.last || '') || (a.first || '').localeCompare(b.first || ''));
  }, [q, filter]);

  const footerClass = TONIGHT ? `${TONIGHT.name} · ${TONIGHT.date} · ${TONIGHT.time}` : 'No classes today';

  return (
    <div className="k-shell">
      <TopBar />

      <div className="k-stage">
        <div className="k-eyebrow">Member check-in</div>
        <h1 className="k-headline">
          Type your name<br />
          <em>to check in.</em>
        </h1>
        <p className="k-sub">
          Start with your first name — the list narrows as you go. Tap your name to mark
          yourself on the mat for tonight's class.
        </p>

        <TonightCard cls={TONIGHT} />

        <div className="k-search">
          <span style={{ color: '#9A9A98', display: 'inline-flex' }} aria-hidden>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="11" cy="11" r="7" /><line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
          </span>
          <input
            ref={inputRef}
            value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Your name…"
            autoCapitalize="words"
            autoComplete="off"
            spellCheck={false}
            inputMode="text"
            aria-label="Search for your name"
          />
          {q && (
            <button className="k-clear" onClick={() => { setQ(''); inputRef.current?.focus(); }} aria-label="Clear">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                <line x1="6" y1="6" x2="18" y2="18" /><line x1="18" y1="6" x2="6" y2="18" />
              </svg>
            </button>
          )}
        </div>

        <div className="k-quick-row">
          {[['all', 'Everyone'], ['adult', 'Adults · Teens'], ['kid', 'Kids']].map(([id, label]) => (
            <button
              key={id}
              className={'k-chip' + (filter === id ? ' is-on' : '')}
              onClick={() => setFilter(id)}
            >{label}</button>
          ))}
        </div>

        <div className="k-results">
          <div className="k-result-meta">
            <span>{results.length} {results.length === 1 ? 'match' : 'matches'}</span>
            <span>Tap a row to check in</span>
          </div>

          {results.length === 0 ? (
            <div className="k-empty">
              <h3>No match for &ldquo;{q}&rdquo;</h3>
              <p>First-time visitor or trial guest? Front desk will get you set up.</p>
              <a href="/free-trial/" className="k-btn k-btn-primary">
                I'm new — start a free trial <HPIcon.Arrow size={14} />
              </a>
            </div>
          ) : (
            results.map(m => <ResultRow key={m.id} m={m} q={q} onPick={pick} />)
          )}
        </div>
      </div>

      <footer className="k-footer">
        <span><span className="k-dot" />Kiosk online · syncing to front desk</span>
        <span>{todayCount} checked in today · {footerClass}</span>
      </footer>

      {pickerMember && (
        <ClassPicker
          member={pickerMember}
          todayClasses={TODAY_CLASSES}
          onPick={pickClass}
          onCancel={() => setPickerMember(null)}
        />
      )}

      {confirmed && (
        <Confirmation
          confirmed={confirmed}
          secondsLeft={Math.max(0, secondsLeft)}
          onUndo={undo}
        />
      )}
    </div>
  );
}

window.KioskApp = KioskApp;
