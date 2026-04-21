# M1.8 — Manual Checkout Test Plan

WooCommerce + WooPayments end-to-end validation for Haanpaa Martial Arts staging.

Run this after `python3 scripts/checkout_smoke_test.py` is green. The smoke test validates infrastructure (products, pages, endpoints); this checklist validates real purchase flows that only a human in a browser can drive.

## Prerequisites

- Staging URL: `https://haanpaa-staging.mystagingwebsite.com`
- WooPayments in **test mode** (not live). Confirm at WP Admin → Payments → Settings → Test mode.
- Stripe test cards reference: <https://docs.stripe.com/testing#cards>
- WP admin access on staging
- A test email inbox (not a real customer's)
- Optional: WP-CLI via Pressable for renewal triggers (M1.8-02)

## How to run

1. Work top-to-bottom — later tests depend on earlier ones (M1.8-08 checks emails from 01–04).
2. For each test, tick `[x]` next to the expected outcomes that pass, record notes for failures, mark overall **PASS** or **FAIL**.
3. Blockers: flag any **FAIL** back to Andrew before proceeding to M1.9 (DNS cutover). All nine must pass.

---

## M1.8-01 — New customer subscription purchase

**Prereq:** WooPayments in test mode. Use Stripe test card `4242 4242 4242 4242`.

**Steps:**
1. Open the site as a logged-out visitor
2. Browse to Programs or Pricing page
3. Select a membership product (e.g., Adult BJJ - Rockford)
4. Add to cart, proceed to checkout
5. Fill in billing details as a new customer
6. Enter test card `4242 4242 4242 4242`, any future expiry, any CVC
7. Complete purchase

**Expected:**
- [ ] Order created with status `active` or `processing`
- [ ] WooCommerce Subscription created with correct billing schedule
- [ ] Customer account created (or existing one linked)
- [ ] Order confirmation email received
- [ ] My Account → Subscriptions shows the new subscription

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-02 — Subscription renewal payment (automatic)

**Prereq:** Active subscription from M1.8-01. WP-CLI access to trigger renewal.

**Steps:**
1. Via WP-CLI or WP admin, trigger an early renewal for the test subscription:
   - `wp wc subscription update <ID> --status=pending-cancel` (then reactivate), or
   - Use Action Scheduler to run the renewal action immediately
2. Alternatively: wait for the next scheduled renewal in test mode

**Expected:**
- [ ] Renewal order created automatically
- [ ] Payment charged via WooPayments (test mode)
- [ ] Subscription remains active
- [ ] Renewal email notification sent

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-03 — Failed payment retry

**Prereq:** Use Stripe test card for declines: `4000 0000 0000 0002`.

**Steps:**
1. Create a subscription using the decline test card, **or** update an existing subscription's payment method to the decline card
2. Trigger a renewal (or wait for scheduled renewal)

**Expected:**
- [ ] Renewal payment fails gracefully
- [ ] Subscription status changes to `on-hold` (not immediately cancelled)
- [ ] Failed payment email notification sent to customer
- [ ] WooCommerce retry schedule activates (check Action Scheduler)
- [ ] Admin can see the failed renewal in WooCommerce → Orders

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-04 — Subscription cancellation from My Account

**Prereq:** Active subscription from M1.8-01.

**Steps:**
1. Log in as the test customer
2. Go to My Account → Subscriptions
3. Click **Cancel** on the active subscription
4. Confirm the cancellation

**Expected:**
- [ ] Subscription status changes to `pending-cancel` (active until period end)
- [ ] Customer sees updated status in My Account
- [ ] Cancellation email sent
- [ ] No immediate charge or refund

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-05 — Subscription tier upgrade/downgrade

**Prereq:** Active subscription. Subscription switching enabled in WC settings.

**Steps:**
1. Log in as the test customer
2. Go to My Account → Subscriptions
3. Click **Switch** or **Upgrade** on the active subscription
4. Select a different membership tier (e.g., Adult BJJ → All-Access)
5. Complete the switch checkout (prorated amount)

**Expected:**
- [ ] Old subscription updated or replaced with new tier
- [ ] Prorated charge/credit applied correctly
- [ ] New billing schedule reflects the new tier's pricing
- [ ] Confirmation email sent with new subscription details

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-06 — Drop-in / trial class one-time purchase

**Prereq:** A simple product (non-subscription) for trial class exists.

**Steps:**
1. Browse to the Free Trial or drop-in product
2. Add to cart
3. Complete checkout with test card `4242 4242 4242 4242`

**Expected:**
- [ ] Order created with status `processing` or `completed`
- [ ] **No** subscription created (one-time payment only)
- [ ] Order confirmation email received
- [ ] Product visible in My Account → Orders

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-07 — Mobile checkout

**Prereq:** Real mobile device or browser dev-tools mobile emulation.

**Steps:**
1. Open the site on a mobile device (or emulated)
2. Browse to a membership product
3. Add to cart and proceed to checkout
4. Verify the checkout form is usable on mobile:
   - Fields are large enough to tap
   - Card input is accessible
   - Keyboard doesn't obscure the form
5. Complete purchase with test card

**Expected:**
- [ ] Checkout completes successfully on mobile
- [ ] No layout issues or overlapping elements
- [ ] Success page displays correctly
- [ ] Touch targets are at least 44×44px

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-08 — Email notifications

**Prereq:** Complete M1.8-01 through M1.8-04.

**Steps:** Check the test customer's email inbox for all expected notifications.

**Expected:**
- [ ] New order / subscription confirmation (from M1.8-01)
- [ ] Subscription renewal receipt (from M1.8-02)
- [ ] Failed payment notice (from M1.8-03)
- [ ] Subscription cancellation notice (from M1.8-04)
- [ ] All emails have correct branding (Haanpaa Martial Arts)
- [ ] All emails are mobile-responsive
- [ ] Reply-to address is correct

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## M1.8-09 — Tax calculation (if applicable)

**Prereq:** If tax is configured for Rockford, IL.

**Steps:**
1. Add a membership product to cart
2. Enter a Rockford, IL billing address at checkout
3. Check the order summary for tax line items

**Expected:**
- [ ] Tax calculated correctly (or no tax if not configured)
- [ ] Tax shown in cart and order confirmation
- [ ] Tax rate matches Rockford/Beloit jurisdiction (verify with Darby)

**Result:** [ ] PASS [ ] FAIL — Notes: ___________________________

---

## Sign-off

- Tester: ________________________________
- Date: ________________________________
- Overall: [ ] All 9 PASS — M1.8 complete, clear to proceed to M1.9
- Overall: [ ] Has failures — blockers captured above, returned to Andrew

> This markdown is generated to mirror `python3 scripts/checkout_smoke_test.py --manual`. If scenarios change, update the script's `MANUAL_TESTS` list and regenerate this doc.
