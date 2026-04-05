# Haanpaa Martial Arts — Brand Guide

> The single source of truth for all visual design, communications, and digital assets.
> Last updated: 2026-04-05

---

## 1. Brand Overview

**Haanpaa Martial Arts** is a family-friendly martial arts academy offering Brazilian Jiu-Jitsu (BJJ) and kickboxing training across two locations in the Illinois/Wisconsin region.

### Locations

- **Rockford, IL** (primary)
- **Beloit, WI** (secondary)

### Mission

To build character, discipline, and community through martial arts training — welcoming all ages and experience levels.

### Core Values

- **Family** — A close-knit community where every member belongs
- **Discipline** — Consistent training builds strength of body and mind
- **Growth** — Every practitioner is on a journey of continuous improvement
- **Respect** — Honor the art, your training partners, and yourself

---

## 2. Logo

### Primary Mark

The Haanpaa Martial Arts logo consists of a stylized **"H" icon** centered within a **blue circle**, surrounded by a **gray circular badge** with "HAANPAA MARTIAL ARTS" in uppercase white text.

### Logo Variations

| Variation | Description | Use When |
|-----------|-------------|----------|
| **Full badge** | Circle badge with icon + text ring | Primary use: headers, signage, merchandise |
| **Icon only** | Blue circle with H icon | Small sizes, favicons, app icons, social avatars |
| **Wordmark** | "HAANPAA MARTIAL ARTS" text only | Inline text contexts, email signatures |

### Clear Space

Maintain a minimum clear space around the logo equal to the height of the "H" icon on all sides.

### Minimum Sizes

- **Full badge:** 48px diameter (digital), 0.5in (print)
- **Icon only:** 24px diameter (digital), 0.25in (print)

### Logo Don'ts

- Do not rotate, skew, or distort the logo
- Do not change the logo colors outside the approved palette
- Do not place the logo on busy backgrounds without sufficient contrast
- Do not add drop shadows, outlines, or effects to the logo
- Do not rearrange or separate the icon from the badge text

---

## 3. Color System

### Primary Palette

| Name | Pantone | Hex | RGB | CSS Custom Property | Usage |
|------|---------|-----|-----|---------------------|-------|
| **Royal Blue** | 2728 C | `#0032A0` | 0, 50, 160 | `--wp--preset--color--royal-blue` | Primary accent, CTAs, links, active states |
| **Cool Gray** | Cool Gray 9 C | `#75787B` | 117, 120, 123 | `--wp--preset--color--cool-gray` | Secondary text, labels, borders, metadata |
| **Black** | Black C | `#000000` | 0, 0, 0 | `--wp--preset--color--black` | Primary text, dark backgrounds, headings |
| **White** | — | `#FFFFFF` | 255, 255, 255 | `--wp--preset--color--white` | Text on dark backgrounds, light backgrounds |

### Extended Palette (Derived)

These colors are derived from the primary palette for UI functionality.

| Name | Hex | CSS Custom Property | Usage |
|------|-----|---------------------|-------|
| **Blue Hover** | `#0041CC` | `--wp--preset--color--blue-hover` | Hover/focus state for blue elements |
| **Blue Pressed** | `#00207A` | — | Active/pressed state (CSS only) |
| **Light Gray** | `#F5F5F7` | `--wp--preset--color--gray-light` | Alternate section backgrounds |
| **Gray 100** | `#E5E5E7` | `--wp--preset--color--gray-100` | Borders, dividers, table lines |
| **Gray 800** | `#2A2A2A` | `--wp--preset--color--gray-800` | Card backgrounds on dark sections |
| **Gray 900** | `#1A1A1A` | `--wp--preset--color--gray-900` | Dark section backgrounds (header, hero) |
| **Blue Tint** | `#E6EBF5` | — | Subtle blue highlight backgrounds |
| **Success** | `#2E7D32` | — | Form success states, positive indicators |
| **Error** | `#C62828` | — | Form errors, destructive actions |
| **Warning** | `#F9A825` | — | Warnings, caution states |

### Dark vs Light Sections

The site uses a **dark-dominant** design pattern:

| Section | Background | Text | Accents |
|---------|------------|------|---------|
| **Header** | Gray 900 (`#1A1A1A`) | White | Royal Blue CTA |
| **Hero** | Black with photo overlay | White | Royal Blue CTA |
| **Content** | White (`#FFFFFF`) | Black | Royal Blue links/accents |
| **Alternate content** | Light Gray (`#F5F5F7`) | Black | Royal Blue links/accents |
| **Footer** | Black (`#000000`) | Cool Gray / White | Royal Blue section labels |

### Accessibility — Contrast Ratios (WCAG 2.1 AA)

| Combination | Ratio | Passes AA | Notes |
|-------------|-------|-----------|-------|
| Black on White | 21:1 | Yes (all sizes) | Primary text pairing |
| White on Black | 21:1 | Yes (all sizes) | Dark section text |
| Royal Blue on White | ~9.5:1 | Yes (all sizes) | Links, accents |
| White on Royal Blue | ~9.5:1 | Yes (all sizes) | Button text |
| Cool Gray on White | ~4.6:1 | Large text only (18px+) | Secondary text, labels |
| Cool Gray on Black | ~4.6:1 | Large text only (18px+) | Footer secondary text |
| White on Gray 900 | ~18:1 | Yes (all sizes) | Header/dark section text |

**Rule:** Cool Gray (`#75787B`) must only be used at 18px or larger, or on contrasting dark/light backgrounds where the ratio exceeds 4.5:1.

---

## 4. Typography

### Font Families

| Role | Family | Weights | Fallback Stack |
|------|--------|---------|----------------|
| **Headings** | Barlow Condensed | 600 (Semi-bold), 700 (Bold), 800 (Extra-bold) | `'Arial Narrow', sans-serif` |
| **Body** | Inter | 400 (Regular), 500 (Medium), 600 (Semi-bold), 700 (Bold) | `'Helvetica Neue', Arial, sans-serif` |

### Type Scale

| Token | Size | Usage |
|-------|------|-------|
| `small` | 0.875rem (14px) | Captions, metadata, fine print |
| `medium` | 1rem (16px) | Body text (base) |
| `large` | 1.25rem (20px) | Lead paragraphs, large body |
| `x-large` | 1.75rem (28px) | Section subheadings (H3/H4) |
| `xx-large` | 2.25rem (36px) | Page headings (H2) |
| `huge` | clamp(2.75rem, 5vw, 4rem) | Hero headlines (H1) |

### Heading Treatment

- **Font:** Barlow Condensed
- **Weight:** 700 (default), 800 for hero headlines
- **Transform:** `uppercase`
- **Line height:** 1.1
- **Letter spacing:** `0.02em`

### Body Treatment

- **Font:** Inter
- **Weight:** 400 (default), 500 for emphasis, 700 for bold
- **Line height:** 1.6
- **Letter spacing:** normal

### UI Label Treatment

- **Font:** Barlow Condensed or Inter (context-dependent)
- **Weight:** 600
- **Transform:** `uppercase`
- **Size:** `small` (0.875rem)
- **Letter spacing:** `0.1em`

---

## 5. Spacing & Layout

### Grid

All spacing uses an **8px base unit** system.

### Spacing Scale

| Token | Value | Usage |
|-------|-------|-------|
| `10` | 0.5rem (8px) | Tight gaps, icon margins |
| `20` | 1rem (16px) | Default paragraph spacing, small gaps |
| `30` | 1.5rem (24px) | Card padding, medium gaps |
| `40` | 2rem (32px) | Section internal padding |
| `50` | 3rem (48px) | Section separators |
| `60` | 4rem (64px) | Large section padding |
| `70` | 5rem (80px) | Hero padding |
| `80` | 7rem (112px) | Maximum section padding |

### Layout Widths

| Token | Value | Usage |
|-------|-------|-------|
| Content | 860px | Default content column |
| Wide | 1280px | Full-width sections, hero, footer |

### Breakpoints

| Name | Value | Usage |
|------|-------|-------|
| Mobile | < 600px | Single column, stacked layout |
| Tablet | 600px–1024px | Two-column grids |
| Desktop | > 1024px | Full multi-column layouts |

---

## 6. UI Components

### Buttons

| Type | Background | Text | Border | Usage |
|------|------------|------|--------|-------|
| **Primary** | Royal Blue (`#0032A0`) | White | None | Main CTAs: "Join Now", "Book a Class" |
| **Primary hover** | Blue Hover (`#0041CC`) | White | None | Hover state |
| **Secondary** | Transparent | Royal Blue | 1px Royal Blue | Secondary actions: "Learn More" |
| **Secondary hover** | Royal Blue 10% | Royal Blue | 1px Royal Blue | Hover state |
| **Ghost (dark bg)** | Transparent | White | 1px White 30% | CTAs on dark backgrounds |
| **Ghost hover** | White 10% | White | 1px White 50% | Hover on dark backgrounds |

**Button typography:** Barlow Condensed, 600 weight, uppercase, `0.1em` letter-spacing, `0.875rem` size.

**Button shape:** `border-radius: 4px` (default), `border-radius: 999px` (pill variant for hero CTAs).

**Button interaction:** `translateY(-2px)` lift on hover with subtle `box-shadow`.

### Cards

- Background: White (light sections) or Gray 800 (dark sections)
- Border radius: 8px
- Shadow: `0 2px 8px rgba(0, 0, 0, 0.06)` (resting), `0 12px 32px rgba(0, 0, 0, 0.1)` (hover)
- Hover: `translateY(-4px)` lift
- Padding: spacing token `30` (1.5rem)

### Navigation

- Style: Frosted glassmorphism (`backdrop-filter: blur(12px)`)
- Background: `rgba(26, 26, 26, 0.85)` (Gray 900 with alpha)
- Text: White, uppercase, `0.8125rem`, `0.15em` letter-spacing
- Active indicator: Royal Blue underline animation (scaleX transform)
- Position: Sticky top

### Forms

- Input border: Gray 100 (`#E5E5E7`)
- Input focus: Royal Blue border, `0 0 0 3px rgba(0, 50, 160, 0.15)` ring
- Input radius: 4px
- Label: Inter 500, `small` size, Black
- Error text: Error (`#C62828`), `small` size

### Tables (Schedule)

- Header: Royal Blue text, uppercase, `0.8rem`, `0.1em` letter-spacing
- Row border: Gray 100 (`#E5E5E7`)
- Row padding: `1rem 1.25rem`
- Last row: no bottom border

---

## 7. Photography & Imagery

### Style Direction

- **Authentic action photography** — real students training, not staged stock photos
- **Warm but not golden** — natural lighting, not filtered or color-graded warm
- **Diverse ages and skill levels** — kids, adults, beginners, advanced
- **Both locations represented** — include Rockford and Beloit facilities
- **Community moments** — fist bumps, belt promotions, group photos alongside training action

### Overlay Treatment

On dark sections (hero, feature banners):

```css
background: linear-gradient(
  180deg,
  rgba(0, 0, 0, 0.4) 0%,
  rgba(0, 50, 160, 0.2) 50%,
  rgba(0, 0, 0, 0.7) 100%
);
```

### Image Aspect Ratios

| Context | Ratio | Usage |
|---------|-------|-------|
| Hero | 16:9 or full-viewport | Homepage hero, page banners |
| Program cards | 4:3 | Program listing thumbnails |
| Instructor portraits | 3:4 | Headshots, about page |
| Gallery | 1:1 or 4:3 | Social feed, gallery grids |

### Image Treatment

- Border radius: 8px (cards, thumbnails)
- No border radius on full-width hero images
- `object-fit: cover` for all constrained images

---

## 8. Voice & Tone

### Brand Personality

**Encouraging, direct, community-focused.** Haanpaa Martial Arts speaks like a trusted coach — motivating without being aggressive, knowledgeable without being pretentious.

### Language Guidelines

| Use | Avoid |
|-----|-------|
| "Train" / "Training" | "Fight" / "Fighting" |
| "Academy" / "School" | "Gym" (in formal contexts) |
| "Journey" / "Path" | "Grind" / "Hustle" |
| "Practitioners" / "Students" | "Fighters" / "Warriors" |
| "Welcome" / "Join our community" | "Dominate" / "Crush it" |
| "All ages and levels" | "No experience needed" (implies low bar) |
| "Build discipline" | "Get tough" |

### Tone by Context

| Context | Tone |
|---------|------|
| Homepage / Marketing | Warm, inviting, confident |
| Class descriptions | Informative, encouraging |
| Belt promotions / Achievements | Celebratory, proud |
| Schedule / Logistics | Clear, concise, practical |
| Social media | Energetic, community-focused |
| Email communications | Personal, respectful |

### Writing Style

- Use active voice
- Keep sentences short and direct
- Lead with benefits, not features
- Address the reader directly ("you" / "your")
- Capitalize "Brazilian Jiu-Jitsu" and "BJJ" consistently
- Spell out "Haanpaa Martial Arts" on first reference; "Haanpaa" is acceptable in subsequent mentions

---

## 9. Accessibility

### Requirements

All digital properties must meet **WCAG 2.1 Level AA** standards.

### Contrast

- Body text: minimum 4.5:1 contrast ratio
- Large text (18px+ or 14px+ bold): minimum 3:1 contrast ratio
- UI components and graphical objects: minimum 3:1 contrast ratio
- See Section 3 contrast table for specific color pairings

### Focus Indicators

- All interactive elements must have a visible focus indicator
- Style: `outline: 2px solid #0032A0; outline-offset: 2px`
- Never use `outline: none` without providing an alternative focus style

### Motion

- Respect `prefers-reduced-motion: reduce` — disable all non-essential animations
- Essential animations (page transitions) should be simplified, not removed
- No auto-playing video without user consent

### Touch Targets

- Minimum touch target: 44x44px
- Adequate spacing between interactive elements on mobile

---

## 10. WordPress Implementation

### theme.json Token Slugs

These slugs are used in WordPress block markup as `has-{slug}-color` and `has-{slug}-background-color` classes.

#### Colors

| Slug | Hex | CSS Custom Property |
|------|-----|---------------------|
| `black` | `#000000` | `var(--wp--preset--color--black)` |
| `royal-blue` | `#0032A0` | `var(--wp--preset--color--royal-blue)` |
| `cool-gray` | `#75787B` | `var(--wp--preset--color--cool-gray)` |
| `white` | `#FFFFFF` | `var(--wp--preset--color--white)` |
| `blue-hover` | `#0041CC` | `var(--wp--preset--color--blue-hover)` |
| `gray-light` | `#F5F5F7` | `var(--wp--preset--color--gray-light)` |
| `gray-100` | `#E5E5E7` | `var(--wp--preset--color--gray-100)` |
| `gray-800` | `#2A2A2A` | `var(--wp--preset--color--gray-800)` |
| `gray-900` | `#1A1A1A` | `var(--wp--preset--color--gray-900)` |

#### Typography

| Slug | Family |
|------|--------|
| `barlow-condensed` | `'Barlow Condensed', 'Arial Narrow', sans-serif` |
| `inter` | `'Inter', 'Helvetica Neue', Arial, sans-serif` |

### WooCommerce Compatibility

- Button styles inherit from theme.json element definitions
- Cart/Checkout Blocks automatically use CSS custom properties from the theme palette
- Form inputs should use Gray 100 borders with Royal Blue focus states
- Product cards follow the standard card component styles from Section 6
- Price display: Inter 700, Black text
