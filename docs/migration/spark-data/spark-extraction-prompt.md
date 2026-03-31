# Spark Data Extraction — Claude in Chrome Prompt

Paste this into the Claude in Chrome extension while logged into app.sparkmembership.com

---

## PROMPT — PASTE BELOW THIS LINE

You are extracting data from Spark Membership for a martial arts gym migration (Haanpaa Martial Arts, Account ID 3208, 2 locations: Rockford IL and Beloit WI). You are currently on the Spark admin dashboard.

You have 4 extraction tasks. Complete them in order. After ALL tasks are done, output ALL results together in a single response.

---

### TASK 1: Membership Plans (HIGHEST PRIORITY)

Navigate to the membership plans settings. Try these paths:
- Left sidebar hamburger menu (3 lines icon) near "Haanpaa Martial Arts" account name
- General Settings > Memberships
- Settings > Memberships
- Look for a gear/settings icon in the top nav bar

FOR EACH MEMBERSHIP PLAN, capture:
- Plan name (exact text)
- Monthly price
- Billing frequency (monthly, quarterly, annual, etc.)
- Signup fee (one-time, if any)
- Trial period (days, if any)
- Contract length (months, if any)
- Which location(s) the plan applies to (Rockford, Beloit, or both) — this may be under a "Location Access" tab
- What programs/classes are included
- Any restrictions or notes
- Whether the plan is currently active or archived
- Whether it auto-renews
- Any cancellation fee
- Any family discount percentage

IMPORTANT: Check if there's a "Show Archived" or "Include Inactive" toggle — we need ALL plans, not just active ones. If the list paginates, go through every page.

Also look for:
- Drop-in / single class pricing
- Family plan pricing or discounts
- Student/military discounts
- Trial membership plans

Output this as a CSV table with columns:
plan_name, price, billing_frequency, signup_fee, trial_days, contract_months, locations, programs_included, status, auto_renew, cancellation_fee, family_discount_pct, notes

---

### TASK 2: POS Product Catalog

Navigate to: Left sidebar > Point of Sale Settings > Products
(or POS > Product Management)

FOR EACH PRODUCT, capture:
- Product name
- SKU (if visible)
- Price
- Category (gear, apparel, supplements, etc.)
- Current stock quantity (if inventory tracking is on)
- Description (if any)
- Whether it's active or discontinued
- Any variants (sizes, colors)

Output as a CSV table with columns:
product_name, sku, price, category, stock_qty, description, status, variants

---

### TASK 3: Class Schedule

Navigate to: Left sidebar > Calendar, or look for a Schedule section.

FOR EACH CLASS at BOTH locations (switch location filter if needed), capture:
- Class name (e.g., "Adult BJJ", "Kids Kickboxing")
- Program / martial arts style
- Day(s) of the week
- Start time
- End time / duration
- Location (Rockford or Beloit)
- Instructor name
- Capacity limit (if shown)
- Whether it's recurring
- Any notes (no-gi, sparring, beginners only, etc.)

Also check for: private lesson slots, open mat times, special events.

Output as a CSV table with columns:
class_name, program, day_of_week, start_time, end_time, location, instructor, capacity, recurrence, notes, status

---

### TASK 4: Belt Rank Definitions

Navigate to: Left sidebar > Ranks (it has a dropdown arrow, expand it)

FOR EACH PROGRAM (Adult BJJ, Kids BJJ, Kickboxing, and any others):
List every rank level IN ORDER from lowest to highest:
- Rank/belt name (exact text and color)
- Number of stripes at each belt level
- Position/order number
- Minimum attendance for promotion (if configured)
- Minimum time at rank (if configured)
- Whether coach recommendation is required

CRITICAL: Kids BJJ reportedly has 13 belt levels. Get the EXACT names and order.

Output as a structured list grouped by program.

---

### OUTPUT FORMAT

After completing all 4 tasks, output everything in this format:

```
=== TASK 1: MEMBERSHIP PLANS ===
[CSV table here]

Navigation path used: [exact clicks you made to get there]
Total plans found: [count] ([X] active, [Y] archived)
Fields not found in UI: [list any fields from above that don't exist]
Additional fields discovered: [any fields you saw that weren't listed above]
Gotchas: [pagination, hidden filters, UI quirks]

=== TASK 2: POS PRODUCTS ===
[CSV table here]

Navigation path used: [exact clicks]
Total products found: [count]
Fields not found in UI: [list]
Additional fields discovered: [list]
Gotchas: [notes]

=== TASK 3: CLASS SCHEDULE ===
[CSV table here]

Navigation path used: [exact clicks]
Total classes found: [count] (Rockford: [X], Beloit: [Y])
Fields not found in UI: [list]
Additional fields discovered: [list]
Gotchas: [notes]

=== TASK 4: BELT RANKS ===
[Structured list here]

Navigation path used: [exact clicks]
Programs found: [list]
Total rank levels per program: [counts]
Fields not found in UI: [list]
Additional fields discovered: [list]
Gotchas: [notes]
```

---
---

# Wix Content Extraction — Claude in Chrome Prompt

Paste this into the Claude in Chrome extension while on www.teamhaanpaa.com

---

## PROMPT — PASTE BELOW THIS LINE

You are extracting all content from a Wix website (www.teamhaanpaa.com) for a martial arts gym migration. This gym has 2 locations: Rockford, IL and Beloit, WI. The site is being replaced with a new WordPress site and we need every piece of content captured.

Visit EVERY page listed below. For each page, extract everything described. Do NOT summarize — capture the full raw content.

### PAGES TO VISIT (in order):

1. https://www.teamhaanpaa.com/ (Home)
2. https://www.teamhaanpaa.com/gracie-brazilian-jiu-jitsu (Gracie BJJ)
3. https://www.teamhaanpaa.com/fitness-kickboxing (Fitness Kickboxing)
4. https://www.teamhaanpaa.com/kids (Kids)
5. https://www.teamhaanpaa.com/personal-training (Personal Training)
6. https://www.teamhaanpaa.com/classes (Class Schedule)
7. https://www.teamhaanpaa.com/instructors (Instructors)
8. https://www.teamhaanpaa.com/beloit (Beloit Location)
9. https://www.teamhaanpaa.com/free-trial (Free Trial)
10. https://www.teamhaanpaa.com/reviews (Reviews)
11. https://www.teamhaanpaa.com/contact (Contact)

Also check the navigation menu for any pages NOT in this list. If you find additional pages, visit and extract those too.

### FOR EACH PAGE, capture:

**Text content:**
- Page URL
- Page title (the H1 or main heading)
- Meta title (from browser tab or page source)
- Every heading (H1, H2, H3, etc.) in order
- Every paragraph of body text — full text, not summaries
- All button/CTA text and where they link to
- Any lists or bullet points
- Footer content (on the first page only — hours, address, phone, social links)

**Images:**
- Description of what each image shows (e.g., "gym interior wide shot", "kids BJJ class", "instructor headshot")
- The image URL (right-click > Copy Image Address — these will be on static.wixstatic.com)
- Where the image appears on the page (hero section, sidebar, gallery, etc.)
- Alt text if visible

**Structure:**
- What sections exist on the page and in what order
- Any embedded widgets (Google Maps, calendars, social feeds, video embeds, forms)
- Form fields (if there's a contact or signup form — list every field, its type, and whether it's required)

**SEO:**
- Meta description (if visible in page source)
- Any structured data or schema markup you can find

### SPECIAL INSTRUCTIONS PER PAGE:

**Home page:** Capture the full hero section text, all program descriptions, any testimonial quotes, and all CTAs. Note the overall layout structure.

**Class Schedule (/classes):** This likely has an embedded Spark schedule widget or an iframe. Capture whatever schedule data is visible — class names, times, days, instructors. Note if it's an embed from sparkmembership.com. Check if there are tabs or toggles for different locations (Rockford vs Beloit).

**Instructors:** Get every instructor's name, title/role, bio text, and photo description.

**Beloit page:** This is the second location. Capture all location-specific content — address, hours, programs offered, any differences from Rockford.

**Contact:** Get the full address for both locations, phone numbers, email addresses, hours of operation, and all form fields.

**Free Trial:** Capture the full signup form — every field, field type, required/optional, and any fine print.

**Reviews:** Get the text of every testimonial/review visible on the page, including the reviewer's name if shown.

### OUTPUT FORMAT

Output results page by page in this format:

```
=== PAGE: [Page Name] ===
URL: [full URL]
Meta Title: [title]
Meta Description: [if found]

--- CONTENT ---
[Full text content with headings preserved using markdown formatting]

--- IMAGES ---
1. [description] | URL: [image URL] | Position: [where on page] | Alt: [alt text]
2. ...

--- STRUCTURE ---
Sections in order: [list]
Embedded widgets: [list any iframes, maps, calendar embeds, forms]
Forms: [field name | field type | required?] for each field

--- CTAs ---
1. [button text] → [link destination]
2. ...

--- NOTES ---
[Anything unusual, broken, or noteworthy about this page]
```

After ALL pages are done, add a summary section:

```
=== SITE SUMMARY ===
Total pages extracted: [count]
Pages found not in original list: [any additional pages discovered]
Total images found: [count]
Embedded widgets found: [list]
Forms found: [list with page locations]
Navigation menu items: [exact menu structure as seen on site]
Social media links: [list all social URLs from footer/header]
Business hours: [as listed on site]
Phone: [number]
Email: [address]
Rockford address: [full address]
Beloit address: [full address]
```
