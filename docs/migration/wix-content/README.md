# Wix Site Content — Migration Reference

Scraped content from [teamhaanpaa.com](https://www.teamhaanpaa.com) (Wix) for use during the WordPress migration. Each file contains the original page content with frontmatter metadata (source URL, images, structure notes).

## File Index

| File | Source Page | Status |
|------|------------|--------|
| [home.md](home.md) | `/` (homepage) | pending — needs re-scrape |
| [classes.md](classes.md) | `/classes` | complete |
| [kids.md](kids.md) | `/kids` | complete |
| [beloit.md](beloit.md) | `/beloit` | complete |
| [fitness-kickboxing.md](fitness-kickboxing.md) | `/fitness-kickboxing` | pending — needs re-scrape |
| [personal-training.md](personal-training.md) | `/personal-training` | pending — needs re-scrape |
| [free-trial.md](free-trial.md) | `/free-trial` | pending — needs re-scrape |
| [contact.md](contact.md) | `/contact` | pending — needs re-scrape |
| [blog.md](blog.md) | `/blog` | pending — needs re-scrape |
| [reviews.md](reviews.md) | `/reviews` | complete (original) |
| [reviews-modernized.md](reviews-modernized.md) | `/reviews` | complete (modernized quotes) |

## Status Legend

- **complete** — Full page content captured with frontmatter, images, and structure notes
- **pending** — Placeholder only; content locked during extraction and needs to be re-scraped from the source URL

## How Agents Should Use This

1. **Original files** (e.g., `reviews.md`) contain the verbatim Wix content for reference.
2. **Modernized files** (e.g., `reviews-modernized.md`) contain rewritten content ready for the WordPress build — modernized copy, suggested meta tags, and implementation notes.
3. When building WordPress pages, start from the modernized version if one exists, falling back to the original.
4. Pages marked "pending" need to be scraped from their source URL before migration work can begin on them.

## Known Issues from Wix Site

- Spelling inconsistency: "Ju Jitsu" vs. "Jiu-Jitsu" (standardize to "Jiu-Jitsu")
- Facebook reviews link goes to Darby's personal page (`DarbyAllenBJJ`), not a business page
- Meta descriptions are empty on most pages (Wix default: `rockfordmma`)
- Blog content may be minimal or unused
