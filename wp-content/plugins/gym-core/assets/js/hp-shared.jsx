// Haanpaa — shared atoms used across all three directions.
// Icons are inline SVGs (currentColor); logo wraps the SVG mark.

const HPLogoMark = ({ size = 36, color = '#0A0A0A', bg = 'transparent', stroke = false }) => (
  <span
    className="hp-logo-mark"
    style={{
      width: size, height: size,
      background: bg,
      border: stroke ? `1px solid ${color}` : 'none',
      borderRadius: '50%',
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      flexShrink: 0,
    }}
  >
    {/* Stylized H mark — abstracted from the brand asset (helmet/shield H) */}
    <svg viewBox="0 0 100 100" width={size * 0.62} height={size * 0.62} fill={color}>
      {/* outer ring */}
      <circle cx="50" cy="50" r="48" fill="none" stroke={color} strokeWidth="1.4" />
      {/* helmet H glyph */}
      <g>
        {/* top arrow */}
        <polygon points="50,12 45,21 55,21" />
        {/* bottom arrow */}
        <polygon points="50,88 45,79 55,79" />
        {/* helmet body */}
        <path d="M30 26 L40 26 L40 38 L60 38 L60 26 L70 26 L70 74 L60 74 L60 62 L40 62 L40 74 L30 74 Z" />
        {/* horizontal slot (eye) */}
        <rect x="40" y="48" width="20" height="4" fill={bg === 'transparent' ? '#fff' : bg} />
      </g>
    </svg>
  </span>
);

// Tighter, cleaner geometric H reused across the site
const HPGlyph = ({ size = 28, color = '#fff' }) => (
  <svg viewBox="0 0 100 100" width={size} height={size} fill={color} aria-hidden="true">
    <polygon points="50,8 43,20 57,20" />
    <polygon points="50,92 43,80 57,80" />
    <path d="M28 24 H40 V40 H60 V24 H72 V76 H60 V60 H40 V76 H28 Z" />
    <rect x="40" y="48" width="20" height="4" fill="rgba(0,0,0,0.001)" />
    <rect x="40" y="48" width="20" height="4" />
  </svg>
);

// Generic line icons
const HPIcon = {
  Arrow: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="5" y1="12" x2="19" y2="12" />
      <polyline points="13 6 19 12 13 18" />
    </svg>
  ),
  ArrowDown: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <line x1="12" y1="5" x2="12" y2="19" />
      <polyline points="6 13 12 19 18 13" />
    </svg>
  ),
  Plus: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
      <line x1="12" y1="5" x2="12" y2="19" />
      <line x1="5" y1="12" x2="19" y2="12" />
    </svg>
  ),
  Minus: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
      <line x1="5" y1="12" x2="19" y2="12" />
    </svg>
  ),
  Phone: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
    </svg>
  ),
  Map: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z" />
      <circle cx="12" cy="10" r="3" />
    </svg>
  ),
  Clock: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </svg>
  ),
  Star: ({ size = 14, filled = true }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round">
      <polygon points="12 2 15.1 8.6 22 9.5 17 14.4 18.2 21.5 12 18 5.8 21.5 7 14.4 2 9.5 8.9 8.6 12 2" />
    </svg>
  ),
  Menu: ({ size = 18 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
      <line x1="3" y1="7" x2="21" y2="7" />
      <line x1="3" y1="17" x2="21" y2="17" />
    </svg>
  ),
  Instagram: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
      <rect x="2" y="2" width="20" height="20" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" />
    </svg>
  ),
  Facebook: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor">
      <path d="M22 12.07C22 6.51 17.52 2 12 2S2 6.51 2 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.02H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.78-3.91 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.77l-.44 2.91h-2.33V22c4.78-.75 8.43-4.91 8.43-9.93z" />
    </svg>
  ),
  Youtube: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor">
      <path d="M23 7.2a3 3 0 0 0-2.1-2.1C19.1 4.6 12 4.6 12 4.6s-7.1 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12 31 31 0 0 0 1 16.8a3 3 0 0 0 2.1 2.1c1.8.5 8.9.5 8.9.5s7.1 0 8.9-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 23.5 12 31 31 0 0 0 23 7.2zM10 15.5v-7l6 3.5-6 3.5z" />
    </svg>
  ),
  Check: ({ size = 16 }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <polyline points="5 12 10 17 19 7" />
    </svg>
  ),
};

// Site-wide content
const HP_DATA = {
  brand: 'Haanpaa Martial Arts',
  tagline: 'Brazilian Jiu-Jitsu (Adults & Kids) · Fitness Kickboxing',
  phone: '815-451-3001',
  address: '4911 26th Avenue, Rockford, IL 61109',
  affiliated: 'In affiliation with Team Curran',
  hours: [
    ['Mon', '8a–9p'], ['Tue', '8a–6p'], ['Wed', '8a–9p'],
    ['Thu', '8a–6p'], ['Fri', '8a–6p'], ['Sat', 'Open mat'],
  ],
  programs: [
    {
      id: 'bjj',
      name: 'Brazilian Jiu-Jitsu',
      short: 'BJJ',
      kicker: 'Gracie lineage',
      copy: 'In the tradition of Helio Gracie. Gentle when you start, technical for life. Train to defend yourself, get strong, and join a deep community on the mats.',
      ages: '13+',
      sessions: '5 classes / week',
      tag: 'Adults & Teens',
      bullets: ['No-gi & gi formats', 'Beginner fundamentals nightly', 'Open mat Saturdays', 'IBJJF-style ranking'],
    },
    {
      id: 'kick',
      name: 'Fitness Kickboxing',
      short: 'Kickboxing',
      kicker: 'Muay Thai roots',
      copy: 'Pads, bags, and a heart-rate that drops weight without dieting. Real Thai-boxing technique, scaled to where you are. Most powerful striking on earth, taught with patience.',
      ages: '16+',
      sessions: '6 classes / week',
      tag: 'All levels',
      bullets: ['Cardio + technique blend', 'Heavy bag & pad work', 'Burns 600–900 cal / hr', 'No fight required'],
    },
    {
      id: 'kids',
      name: 'Kids Jiu-Jitsu',
      short: 'Kids',
      kicker: 'Ages 5–12',
      copy: 'Our Kids program is part of the same Brazilian Jiu-Jitsu curriculum, taught at a kid-appropriate pace. They learn focus, courtesy, and confidence on the same mats as our adults — just in their own classes.',
      ages: '5–12',
      sessions: '[Sessions / week]',
      tag: 'Family',
      bullets: ['Real BJJ curriculum, kid-appropriate pacing', 'Parents may watch every class', '[Belt promotion cadence]', '[Other program detail]'],
    },
  ],
  schedule: {
    Mon: [
      { time: '6:00a', name: 'Open Mat', kind: 'bjj', who: 'All levels' },
      { time: '12:00p', name: 'Fitness Kickboxing', kind: 'kick', who: 'All levels' },
      { time: '4:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 5–8' },
      { time: '5:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 9–12' },
      { time: '6:30p', name: 'BJJ Fundamentals', kind: 'bjj', who: 'Beginners' },
      { time: '7:30p', name: 'BJJ Advanced', kind: 'bjj', who: 'Blue belt+' },
    ],
    Tue: [
      { time: '6:00a', name: 'Fitness Kickboxing', kind: 'kick', who: 'All levels' },
      { time: '12:00p', name: 'BJJ All Levels', kind: 'bjj', who: 'All levels' },
      { time: '4:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 5–8' },
    ],
    Wed: [
      { time: '6:00a', name: 'Open Mat', kind: 'bjj', who: 'All levels' },
      { time: '12:00p', name: 'Fitness Kickboxing', kind: 'kick', who: 'All levels' },
      { time: '4:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 5–8' },
      { time: '5:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 9–12' },
      { time: '6:30p', name: 'BJJ Fundamentals', kind: 'bjj', who: 'Beginners' },
      { time: '7:30p', name: 'No-Gi BJJ', kind: 'bjj', who: 'All levels' },
    ],
    Thu: [
      { time: '6:00a', name: 'Fitness Kickboxing', kind: 'kick', who: 'All levels' },
      { time: '12:00p', name: 'BJJ All Levels', kind: 'bjj', who: 'All levels' },
      { time: '4:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 9–12' },
    ],
    Fri: [
      { time: '6:00a', name: 'Fitness Kickboxing', kind: 'kick', who: 'All levels' },
      { time: '12:00p', name: 'Open Mat', kind: 'bjj', who: 'All levels' },
      { time: '4:30p', name: 'Kids Jiu-Jitsu', kind: 'kids', who: 'Ages 5–12' },
      { time: '6:00p', name: 'BJJ Sparring', kind: 'bjj', who: 'Blue belt+' },
    ],
    Sat: [
      { time: '9:00a', name: 'Family Open Mat', kind: 'kids', who: 'All ages' },
      { time: '10:30a', name: 'BJJ Fundamentals', kind: 'bjj', who: 'Beginners' },
    ],
    Sun: [],
  },
  // Darby & Amanda Haanpaa are the real owners. Other coaches are placeholders to populate before launch.
  instructors: [
    { name: 'Darby Haanpaa', title: 'Owner & Head Coach', belt: 'Brazilian Jiu-Jitsu · Team Curran', years: null },
    { name: 'Amanda Haanpaa', title: 'Co-owner', belt: '[Role / specialty]', years: null },
    { name: '[Coach name]', title: '[Title]', belt: '[Rank · lineage]', years: null },
    { name: '[Coach name]', title: '[Title]', belt: '[Rank · lineage]', years: null },
  ],
  reviews: [
    { quote: '[Real student testimonial — pull from existing Google reviews or collect new ones.]', who: '[Student]', context: '[Program · tenure]' },
    { quote: '[Real student testimonial — pull from existing Google reviews or collect new ones.]', who: '[Student]', context: '[Program · tenure]' },
    { quote: '[Real student testimonial — pull from existing Google reviews or collect new ones.]', who: '[Student]', context: '[Program · tenure]' },
    { quote: '[Real student testimonial — pull from existing Google reviews or collect new ones.]', who: '[Student]', context: '[Program · tenure]' },
  ],
  locations: [
    { id: 'rockford', city: 'Rockford', addr: '4911 26th Avenue', zip: 'Rockford, IL 61109', phone: '815-451-3001', primary: true },
    { id: 'beloit', city: 'Beloit', addr: 'HMA Beloit', zip: 'Beloit, WI', phone: '815-451-3001', primary: false },
  ],
};

// Export for other Babel scripts
Object.assign(window, { HPLogoMark, HPGlyph, HPIcon, HP_DATA });
