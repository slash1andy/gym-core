# CoWork Playbooks: M1.1–1.4 Infrastructure Setup

> Playbooks for Claude Desktop / CoWork agents to set up the WordPress + WooCommerce infrastructure for Milestone 1. These are admin UI configuration tasks — the agent navigates wp-admin screens and configures settings.

**Last updated:** 2026-03-30

**Prerequisites:**
- Pressable staging site provisioned (or Local WP / wp-env dev environment)
- Admin credentials available
- WooPayments requires a Stripe account (Haanpaa's)

---

## General Instructions (prepend to every playbook)

```
You are setting up the WordPress + WooCommerce infrastructure for Haanpaa
Martial Arts — a martial arts gym with 2 locations (Rockford IL, Beloit WI).

You have admin access to the WordPress staging site. Navigate the wp-admin
UI to configure settings as specified.

After completing each configuration step:
1. Take a screenshot showing the setting is correctly configured
2. Save screenshots to ~/hma-migration/screenshots/m1-setup/

When you finish, update this playbook file at:
~/Local Sites/hma-core/docs/planning/cowork-m1-infrastructure-playbooks.md

Add a "### Completed Run" section with:
- Date completed
- Exact navigation paths used
- Any settings not found or different from expected
- Any decisions made (and why)
- Screenshots taken
- The WordPress/WooCommerce versions actually installed

If you encounter errors, permission issues, or settings that don't exist
in this version, document them and stop — do not guess.
```

---

## Playbook M1.1: Environment Setup

**When:** First — everything depends on this
**Estimated time:** 15-30 minutes
**Requires:** Pressable account access OR local dev environment

```
TASK: Set up and configure the WordPress 7.0 + WooCommerce 10.3 development environment.

STEP 1 — VERIFY WORDPRESS INSTALLATION
Navigate to: wp-admin > Dashboard
Confirm:
  - WordPress version is 7.0+ (check footer or Dashboard > Updates)
  - PHP version is 8.0+ (Tools > Site Health > Info > Server > PHP Version)
  - SSL certificate is active (URL shows https://)

If WordPress is not yet installed, this requires Pressable dashboard access
or wp-cli — ask the human to provision the site first.

STEP 2 — INSTALL WOOCOMMERCE
Navigate to: Plugins > Add New
Search for "WooCommerce" and install version 10.3+
Activate the plugin.

If the WooCommerce Setup Wizard appears:
  - Country/Region: United States > Illinois
  - Address: Use Haanpaa's Rockford address
  - Industry: Other
  - Product Types: Physical, Subscriptions (if shown)
  - Skip theme selection (keep current theme for now)
  - Skip any marketing opt-ins

STEP 3 — ENABLE HPOS
Navigate to: WooCommerce > Settings > Advanced > Features
Enable: "High-Performance Order Storage (HPOS)"
If there's a compatibility mode option, set it to "Use HPOS tables"
Save changes.

STEP 4 — ENABLE BLOCK-BASED CART AND CHECKOUT
Navigate to: WooCommerce > Settings > Advanced > Features
Enable: "Cart & Checkout Blocks" (or confirm already enabled — it's the default in WC 10.x)
Save changes.

Navigate to: Pages
Confirm that Cart and Checkout pages use the block-based versions:
  - Edit Cart page → should contain [woocommerce_cart] block or Cart block
  - Edit Checkout page → should contain Checkout block

If they use the legacy shortcode ([woocommerce_cart] / [woocommerce_checkout]),
replace with the block versions:
  - Delete shortcode content
  - Add block: search for "Cart" / "Checkout" (WooCommerce blocks)

STEP 5 — SET PERMALINK STRUCTURE
Navigate to: Settings > Permalinks
Select: "Post name" (/%postname%/)
Save Changes (this also flushes rewrite rules)

STEP 6 — ENABLE DEBUG LOGGING (staging only)
This requires wp-config.php access. Check if the following constants are set:

Option A — If you have file access (Local WP, SSH, Pressable file manager):
Add to wp-config.php (before "That's all, stop editing!"):
  define( 'WP_DEBUG', true );
  define( 'WP_DEBUG_LOG', true );
  define( 'WP_DEBUG_DISPLAY', false );
  define( 'SCRIPT_DEBUG', true );

Option B — If no file access:
Navigate to: Tools > Site Health > Info > WordPress Constants
Document which debug constants are currently set.
Note: Pressable may have its own debug toggle in the Pressable dashboard.

STEP 7 — VERIFY
Navigate to: WooCommerce > Status > System Status
Confirm:
  - WP Version: 7.0+
  - WC Version: 10.3+
  - PHP Version: 8.0+
  - HPOS: Enabled
  - Cart/Checkout Blocks: Enabled (or "Block-based" in the relevant field)
  - SSL: Yes

Take a screenshot of the System Status page.

OUTPUT: Save system status summary to:
~/hma-migration/exports/m1-environment-status.md
```

---

## Playbook M1.2: WooPayments Configuration

**When:** After M1.1 is complete
**Estimated time:** 20-40 minutes
**Requires:** Haanpaa's Stripe account credentials (Darby must provide or do the Stripe connect step)

```
TASK: Install and configure WooPayments as the payment gateway.

STEP 1 — INSTALL WOOPAYMENTS
Navigate to: Plugins > Add New
Search for "WooPayments" (by Automattic / WooCommerce)
Install and activate.

STEP 2 — WOOPAYMENTS ONBOARDING
After activation, WooPayments will prompt you to connect a Stripe account.

⚠️ HUMAN REQUIRED: The Stripe account connection step requires the business
owner (Darby) to authorize. Options:
  A) Darby logs into the site and completes the Stripe Connect flow
  B) Andrew does it with Darby's Stripe credentials
  C) The agent walks through the UI and stops at the Stripe Connect screen,
     saving a screenshot for the human to complete

If you can complete the connection:
  - Business type: Individual or Company (confirm with Darby)
  - Business address: Haanpaa's Rockford address
  - Industry: Fitness/Recreation
  - Complete KYC (Know Your Customer) verification

STEP 3 — ENABLE TEST MODE
Navigate to: WooCommerce > Settings > Payments > WooPayments
Enable "Test mode" toggle (CRITICAL for staging — do not process real payments)
Save changes.

STEP 4 — CONFIGURE PAYMENT METHODS
Navigate to: WooCommerce > Settings > Payments > WooPayments
Enable:
  - Credit/Debit cards: ✅
  - Apple Pay / Google Pay: ✅ (requires domain verification — may only work on production domain)
  - Link by Stripe: optional (leave off for now)

STEP 5 — ENABLE SAVED PAYMENT METHODS
Navigate to: WooCommerce > Settings > Payments > WooPayments
Find "Saved cards" or "Tokenization" setting
Enable saved payment methods (REQUIRED for WooCommerce Subscriptions)
Save changes.

STEP 6 — CONFIGURE SETTINGS
Navigate to: WooCommerce > Settings > Payments > WooPayments
Set:
  - Currency: USD
  - Fraud protection: Enable basic rules (or Stripe Radar defaults)
  - Statement descriptor: "HAANPAA MA" or similar (13 char max)

STEP 7 — VERIFY WEBHOOK
Navigate to: WooCommerce > Settings > Payments > WooPayments
Check the webhook status — it should show as "Active" or "Verified"
If not, click "Refresh" or troubleshoot per the WooPayments docs.

STEP 8 — DOCUMENT RATES
Navigate to: WooPayments dashboard or Stripe dashboard
Document:
  - Per-transaction rate (e.g., 2.9% + $0.30)
  - Any volume discounts or special rates
  - Compare to current USAePay/Pitbull rate (~3.23% blended)

OUTPUT: Save configuration summary to:
~/hma-migration/exports/m1-woopayments-config.md
Include: connection status, test mode confirmed, payment methods enabled,
saved cards enabled, rates documented, webhook status.

Screenshot the WooPayments settings page:
~/hma-migration/screenshots/m1-setup/woopayments-settings.png
```

---

## Playbook M1.3: WooCommerce Subscriptions Setup

**When:** After M1.2 (WooPayments must be active)
**Estimated time:** 10-15 minutes
**Requires:** WooCommerce Subscriptions plugin (commercial — must be purchased/downloaded from woocommerce.com)

```
TASK: Install and configure WooCommerce Subscriptions for recurring membership billing.

STEP 1 — INSTALL WOOCOMMERCE SUBSCRIPTIONS
⚠️ HUMAN REQUIRED: WooCommerce Subscriptions is a paid extension.
  - Download from: woocommerce.com (requires a woocommerce.com account + license)
  - If the plugin ZIP is at a known path, upload via Plugins > Add New > Upload
  - If installed via WooCommerce.com account connection: Plugins > My Subscriptions

Activate the plugin after installation.

STEP 2 — CONFIGURE SUBSCRIPTION SETTINGS
Navigate to: WooCommerce > Settings > Subscriptions
(or WooCommerce > Settings > Payments — Subscriptions may add its own tab)

Configure:
  - Renewal payment method: Automatic (via WooPayments) ✅
  - Accept Manual Renewals: Yes (needed for migrated subscriptions initially)

STEP 3 — CONFIGURE RETRY RULES
Navigate to: WooCommerce > Settings > Subscriptions > Payment Retry
Configure failed payment retry schedule:
  - Retry 1: 1 day after failure
  - Retry 2: 3 days after failure
  - Retry 3: 5 days after failure
  - Retry 4: 7 days after failure (final)
  - After final failure: Subscription status → On Hold (not Cancelled)

STEP 4 — CONFIGURE SWITCHING
Navigate to: WooCommerce > Settings > Subscriptions
Enable:
  - Subscription Switching: ✅ (allows upgrade/downgrade between tiers)
  - Switching type: "Upgrade or Downgrade"
  - Prorate: "Prorate recurring payment amount" or equivalent

STEP 5 — CONFIGURE SYNCHRONIZATION
Navigate to: WooCommerce > Settings > Subscriptions
Enable:
  - Synchronize renewals: ✅
  - Renewal day: 1 (anchor to 1st of month)
  - This means all subscriptions bill on the 1st regardless of signup date

STEP 6 — DISABLE DRIP CONTENT
Navigate to: WooCommerce > Settings > Subscriptions
If there's a "Drip Content" option: Disable it
(WooCommerce Memberships handles content gating in M3)

STEP 7 — DISABLE EARLY RENEWAL
Navigate to: WooCommerce > Settings > Subscriptions
If there's an "Early Renewal" option: Disable it
(Prevents billing confusion for gym members)

STEP 8 — VERIFY
Navigate to: Products > Add New
Confirm that "Subscription" product types are available in the product type dropdown:
  - Simple subscription
  - Variable subscription

Take screenshot of the Subscriptions settings page.

OUTPUT: Save configuration summary to:
~/hma-migration/exports/m1-subscriptions-config.md

Screenshot: ~/hma-migration/screenshots/m1-setup/subscriptions-settings.png
```

---

## Playbook M1.4: Membership Product Configuration

**When:** After M1.3 AND after CoWork Playbook 1 (Spark membership plan extraction) is complete
**Estimated time:** 30-60 minutes
**Requires:**
  - Spark membership plan data (from `~/hma-migration/exports/spark-membership-plans.csv`)
  - Pricing confirmed by Darby
  - gym-core plugin activated (for `gym_location` taxonomy on products)

```
TASK: Create subscription products for each membership tier at both locations.

⚠️ PREREQUISITE CHECK:
Before starting, verify that:
1. ~/hma-migration/exports/spark-membership-plans.csv exists (from CoWork Playbook 1)
2. The gym-core plugin is installed and activated (for gym_location taxonomy)
3. WooCommerce Subscriptions is active (from M1.3)

If spark-membership-plans.csv is missing, STOP and ask the human to run
CoWork Playbook 1 first. Do NOT guess at pricing or plan names.

STEP 1 — CREATE PRODUCT CATEGORY
Navigate to: Products > Categories
Create category: "Memberships"
  - Name: Memberships
  - Slug: memberships
  - Description: "Martial arts membership plans"

Also create: "Drop-In"
  - Name: Drop-In
  - Slug: drop-in
  - Description: "Single class and trial passes"

STEP 2 — VERIFY GYM_LOCATION TAXONOMY
Navigate to: Products > Gym Locations (or wherever the taxonomy appears)
Confirm "Rockford" and "Beloit" terms exist.
If not, activate the gym-core plugin first.

STEP 3 — CREATE SUBSCRIPTION PRODUCTS
For EACH row in spark-membership-plans.csv where status = "active":

Navigate to: Products > Add New

Product setup:
  - Product name: {plan_name} — e.g., "Adult BJJ Monthly (Rockford)"
  - Product type: "Simple subscription" (or "Variable subscription" if multiple pricing options)
  - Regular price: {price} from CSV
  - Subscription period: Map billing_frequency to WC Subscriptions format:
    - "monthly" → Every 1 month
    - "quarterly" → Every 3 months
    - "annual" → Every 1 year
  - Sign-up fee: {signup_fee} from CSV (leave blank if $0)
  - Free trial: {trial_days} from CSV (leave blank if 0)
  - Category: Memberships
  - Gym Location: Assign the correct location(s) from the CSV
  - Product description: Write a brief description based on programs_included
  - Product image: Skip for now (will add later with gym photos)
  - Published status: Draft (do NOT publish until Darby reviews)

NAMING CONVENTION for products:
  "{Program} {Frequency} ({Location})"
  Examples:
    - "Adult BJJ Monthly (Rockford)"
    - "Kids BJJ Monthly (Beloit)"
    - "Kickboxing Monthly (Rockford)"
    - "All-Access Monthly (Both Locations)"

STEP 4 — CREATE DROP-IN / TRIAL PRODUCTS
Create NON-subscription products for:
  - "Drop-In Class" — Simple product, one-time purchase, price from Spark data
  - "Trial Class" — Simple product, one-time purchase (may be free or discounted)
  Category: Drop-In
  Assign appropriate gym_location terms.

STEP 5 — VERIFY
Navigate to: Products > All Products
Confirm:
  - All active membership plans from Spark have corresponding WC products
  - Each product is in "Draft" status
  - Each product has correct: price, subscription period, signup fee, location
  - Categories are assigned (Memberships or Drop-In)

Count the products and compare to the row count in spark-membership-plans.csv.

OUTPUT: Save product manifest to:
~/hma-migration/exports/m1-product-manifest.csv with columns:
wc_product_id, product_name, product_type, price, billing_period,
signup_fee, trial_days, category, location, status, spark_plan_name

This manifest is critical — it maps Spark plan names to WooCommerce
product IDs, which is needed for the subscription migration CSV later.

Screenshot: ~/hma-migration/screenshots/m1-setup/product-list.png
```

---

## Execution Order

| Step | Playbook | Depends On | Blocking? | Agent Type |
|------|----------|-----------|-----------|------------|
| 1 | **M1.1: Environment Setup** | Nothing | Yes — everything else needs WP + WC | CoWork / Desktop |
| 2 | **M1.2: WooPayments** | M1.1 | Yes — Subscriptions needs a gateway | CoWork + human (Stripe connect) |
| 3 | **M1.3: Subscriptions** | M1.2 | Yes — products need subscription type | CoWork + human (plugin purchase) |
| 4 | **M1.4: Products** | M1.3 + PB1 | Yes — subscription import needs product IDs | CoWork |

**Human touchpoints:**
- M1.2: Darby must authorize the Stripe Connect flow
- M1.3: Someone must purchase/download WooCommerce Subscriptions from woocommerce.com
- M1.4: Pricing must be confirmed by Darby before publishing products

**Parallel work while waiting on human touchpoints:**
- Run CoWork Playbook 1 (Spark membership plans) — needed before M1.4
- Run CoWork Playbook 9 + 10 (Wix content + media) — needed for M1.7
- Run CoWork Playbook 12 (USAePay recurring profiles) — needed for M1.9
- Begin gym-core plugin development for M1.5 + M1.6 (already complete)

---

## How These Connect to Later Milestones

```
M1.1 Environment ──→ M1.2 WooPayments ──→ M1.3 Subscriptions ──→ M1.4 Products
                                                                        │
                         PB1 (Spark Plans) ─────────────────────────────┘
                                                                        │
                                                          M1.9 (Subscription Import)
                                                                        │
                                                   spark-membership-plans.csv
                                                   + m1-product-manifest.csv
                                                   + usaepay-recurring-profiles.csv
                                                          ↓
                                                   Build subscription import CSV
                                                   with correct product_id mapping
```

The `m1-product-manifest.csv` output from M1.4 is the bridge between the data extraction playbooks (which capture Spark plan names) and the subscription import (which needs WooCommerce product IDs).
