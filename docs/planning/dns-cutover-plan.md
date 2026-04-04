# DNS + Go-Live Cutover Plan (M1.9)

## Overview

Migrate haanpaamartialarts.com from Wix to Pressable. This is a controlled DNS cutover with a documented rollback path. The site must be fully tested on staging before proceeding.

---

## Prerequisites (Go/No-Go Checklist)

All items must be checked before proceeding with DNS cutover.

### Technical readiness
- [ ] M1.8 checkout smoke test passes (`python3 scripts/checkout_smoke_test.py`)
- [ ] M1.8 manual checkout tests completed (all 9 scenarios pass)
- [ ] SSL certificate active on Pressable site
- [ ] WooPayments onboarding completed (Stripe account connected)
- [ ] WooPayments switched from test mode to **live mode**
- [ ] Transactional emails verified (new order, renewal, failed payment, cancellation)
- [ ] Email sender domain verified (DKIM/SPF/DMARC for haanpaamartialarts.com)
- [ ] All 11 content pages published and reviewed
- [ ] Contact form tested and delivering to correct inbox
- [ ] Mobile responsiveness verified on at least 2 devices
- [ ] gym-core plugin activated, no PHP errors in debug log
- [ ] hma-ai-chat plugin activated (or deactivated if deferring M6 to post-launch)

### Content readiness
- [ ] All product pricing confirmed with Darby
- [ ] Location information correct for both Rockford and Beloit
- [ ] Schedule pages populated with current class times
- [ ] Coach/instructor bios and photos uploaded
- [ ] Legal pages present (Privacy Policy, Terms of Service)

### Stakeholder sign-off
- [ ] **Darby** has reviewed the staging site and approved for go-live
- [ ] **Amanda** has reviewed the staging site and approved for go-live
- [ ] Andrew has verified all technical checklist items above

---

## DNS Migration Steps

### 1. Preparation (day before cutover)

1. **Lower DNS TTL** — Log into the domain registrar and set TTL for the A record and CNAME to **300 seconds** (5 minutes). This ensures fast propagation when we switch.
   - Current registrar: _______________ (confirm with Darby)
   - Current TTL: _______________ (note for rollback)

2. **Document current DNS records** — Screenshot or export all DNS records before making changes. Save to this file as reference.

3. **Verify Pressable site IP** — Get the Pressable site's IP address:
   ```
   dig +short haanpaa-staging.mystagingwebsite.com
   ```
   Or check the Pressable dashboard under Site > Domain.

4. **Add the production domain to Pressable** — In Pressable dashboard:
   - Go to Site > Domains
   - Add `haanpaamartialarts.com` as a domain
   - Add `www.haanpaamartialarts.com` as an alias
   - Note the DNS records Pressable provides (A record IP and/or CNAME)

### 2. Cutover (go-live window)

**Target window:** Low-traffic period (early morning or late evening)

1. **Put Wix site in maintenance mode** (if possible) or simply proceed — both sites will be live briefly during propagation.

2. **Update DNS records** at the registrar:

   | Record | Type | Value | TTL |
   |--------|------|-------|-----|
   | @ | A | `<Pressable IP>` | 300 |
   | www | CNAME | `haanpaamartialarts.com` | 300 |

   Or if Pressable provides a CNAME target:

   | Record | Type | Value | TTL |
   |--------|------|-------|-----|
   | @ | A | `<Pressable IP>` | 300 |
   | www | CNAME | `<pressable-cname-target>` | 300 |

3. **Verify SSL on production domain** — Pressable auto-provisions Let's Encrypt. After DNS propagates (5-15 minutes):
   ```
   curl -I https://haanpaamartialarts.com
   ```
   Confirm HTTPS works without certificate errors.

4. **Update WordPress site URL** — Via WP-CLI through Pressable:
   ```
   wp option update siteurl https://haanpaamartialarts.com
   wp option update home https://haanpaamartialarts.com
   ```

5. **Search-replace old URLs** — If the staging domain appears in content:
   ```
   wp search-replace 'haanpaa-staging.mystagingwebsite.com' 'haanpaamartialarts.com' --skip-columns=guid
   ```

6. **Flush caches and permalinks:**
   ```
   wp cache flush
   wp rewrite flush
   ```

### 3. Post-cutover verification (within 1 hour)

- [ ] `https://haanpaamartialarts.com` loads correctly
- [ ] `https://www.haanpaamartialarts.com` redirects to `https://haanpaamartialarts.com`
- [ ] SSL certificate is valid (check browser padlock)
- [ ] Homepage displays correctly (desktop + mobile)
- [ ] Product pages load with correct pricing
- [ ] Checkout page is accessible
- [ ] My Account page is accessible
- [ ] WooPayments processes a live $1 test charge (use Darby's card, refund immediately)
- [ ] Contact form sends to correct email
- [ ] No mixed content warnings (HTTP resources on HTTPS page)
- [ ] Transactional email from new domain reaches inbox (not spam)

### 4. 301 Redirects for Wix URLs

If any Wix URLs have been indexed by Google or linked externally, add 301 redirects. These go in the WordPress `.htaccess` or via a redirect plugin.

Common Wix URLs to redirect:

| Wix URL path | WordPress path |
|--------------|----------------|
| `/adult-bjj` | `/programs/adult-bjj/` |
| `/kids-bjj` | `/programs/kids-bjj/` |
| `/kickboxing` | `/programs/kickboxing/` |
| `/schedule` | `/schedule/` |
| `/pricing` | `/pricing/` |
| `/about` | `/about/` |
| `/contact` | `/contact/` |
| `/free-trial` | `/free-trial/` |

Verify with Google Search Console which Wix URLs have external links after cutover.

---

## Rollback Plan

If critical issues are discovered after cutover:

### Quick rollback (within 24 hours)

1. **Revert DNS** — Point A record back to Wix IP:
   - Wix IP: _______________ (document before cutover)
   - TTL was set to 300s, so propagation takes ~5 minutes

2. **Revert WordPress URLs** (if changed):
   ```
   wp option update siteurl https://haanpaa-staging.mystagingwebsite.com
   wp option update home https://haanpaa-staging.mystagingwebsite.com
   ```

3. **Notify Darby and Amanda** that the site is temporarily back on Wix

### Rollback triggers (any of these = immediate rollback)

- WooPayments cannot process payments in live mode
- SSL certificate errors on production domain
- Site returns 500 errors or white screen
- Checkout flow is completely broken
- Member data is missing or corrupted

### Rollback is NOT triggered by

- Minor styling issues (fix forward)
- Individual page content errors (fix forward)
- Email delivery delays (monitor, fix forward)
- Non-critical plugin warnings in debug log

---

## Post-Launch Monitoring (first 48 hours)

- [ ] Check WooPayments dashboard for successful transactions (2 hours, 12 hours, 24 hours)
- [ ] Verify email delivery (test purchase to known inbox)
- [ ] Check PHP error log for new errors: `wp-content/debug.log`
- [ ] Monitor site uptime (Pressable dashboard or Jetpack)
- [ ] Check Google Search Console for crawl errors (24-48 hours after cutover)
- [ ] Verify Stripe webhook endpoint is receiving events

---

## Timeline

| Step | Owner | Duration |
|------|-------|----------|
| Go/no-go checklist | Andrew + Darby | 30 min |
| Lower DNS TTL | Andrew | 5 min |
| Wait for TTL propagation | — | 24 hours (do day before) |
| DNS cutover | Andrew | 10 min |
| SSL + URL verification | Andrew | 15 min |
| Post-cutover checks | Andrew | 30 min |
| Live payment test | Andrew + Darby | 10 min |
| **Total cutover window** | | **~1 hour** |

---

## After successful cutover

1. **Cancel Wix subscription** — $74/mo savings
2. **Raise DNS TTL** back to 3600s (1 hour) after 48 hours of stability
3. **Update Google Business Profile** with new site URL (if needed)
4. **Submit sitemap** to Google Search Console: `https://haanpaamartialarts.com/sitemap.xml`
5. **Remove staging domain** from Pressable (keep only production domain)
