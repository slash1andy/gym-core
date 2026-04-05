# CoWork Playbook: M1.8 Checkout Flow Testing

> Instructions for Claude Desktop / Claude CoWork to execute the M1.8 manual checkout test plan on the Haanpaa Martial Arts staging site. The agent navigates the storefront and wp-admin, performs purchases with Stripe test cards, and records pass/fail results.

**Last updated:** 2026-04-05
**Depends on:** M1.2 (WooPayments), M1.3 (Subscriptions), M1.4 (Products), M1.7 (Site)
**Staging site:** https://haanpaa-staging.mystagingwebsite.com
**WP Admin:** https://haanpaa-staging.mystagingwebsite.com/wp-admin

---

## Prerequisites

Before starting, verify the following in wp-admin:

```
TASK: Verify 5 WooCommerce settings before running checkout tests.

NAVIGATE TO: WP Admin > WooCommerce > Settings

CHECK 1 — WooPayments test mode:
  Go to: WooCommerce > Settings > Payments > WooPayments > Settings
  Verify: "Test mode" or "Enable test mode" is ON.
  If OFF: Enable it. Do NOT run checkout tests in live mode.
  Take screenshot: ~/hma-migration/screenshots/m18-woopayments-test-mode.png

CHECK 2 — HPOS enabled:
  Go to: WooCommerce > Settings > Advanced > Features
  Verify: "High-Performance Order Storage" is checked / enabled.
  Take screenshot: ~/hma-migration/screenshots/m18-hpos-enabled.png

CHECK 3 — Subscription switching:
  Go to: WooCommerce > Settings > Subscriptions
  Verify: "Allow Switching" is enabled (Between Subscription Variations and/or
  Between Grouped Subscriptions).
  Take screenshot: ~/hma-migration/screenshots/m18-subscription-switching.png

CHECK 4 — Sync renewals:
  Same page (WooCommerce > Settings > Subscriptions)
  Verify: "Synchronise Renewals" is enabled.
  Take screenshot: ~/hma-migration/screenshots/m18-sync-renewals.png

CHECK 5 — Failed payment retry:
  Same page (WooCommerce > Settings > Subscriptions)
  Verify: "Retry Failed Payments" is enabled.
  Take screenshot: ~/hma-migration/screenshots/m18-retry-enabled.png

OUTPUT: Record results in ~/hma-migration/exports/m18-prereq-results.md as:
| Setting | Expected | Actual | Status |
```

---

## Stripe Test Cards

Use these throughout the test scenarios:

| Card Number | Result |
|-------------|--------|
| `4242 4242 4242 4242` | Successful payment |
| `4000 0000 0000 0002` | Card declined |
| `4000 0000 0000 3220` | 3D Secure authentication required |

For all cards: use any future expiry date (e.g., 12/28), any 3-digit CVC (e.g., 123), any ZIP code.

---

## Test Scenario 1: New Customer Subscription Purchase

```
TASK: Complete a membership purchase as a new customer using a Stripe test card.

STEP 1 — Open the site as a guest:
  Open a private/incognito browser window.
  Navigate to: https://haanpaa-staging.mystagingwebsite.com
  Verify: Site loads, Team Haanpaa theme is active (not Twenty Twenty-Five).
  Take screenshot: ~/hma-migration/screenshots/m18-01-homepage.png

STEP 2 — Browse to a membership product:
  Navigate to the Shop page, Programs page, or Pricing page.
  Find a subscription membership product (e.g., "Adult BJJ - Rockford").
  Click to view the product detail page.
  Verify: Product shows a recurring price (e.g., "$XX.XX / month").
  Take screenshot: ~/hma-migration/screenshots/m18-01-product.png

STEP 3 — Add to cart and proceed to checkout:
  Click "Sign up now" or "Add to cart".
  Go to the Cart page (or proceed directly to Checkout if redirected).
  Verify: Cart shows the correct product, price, and billing period.
  Proceed to Checkout.

STEP 4 — Fill in billing details:
  Use test customer details:
    First name: Test
    Last name: Member
    Email: Use a real email you can check (or a test inbox)
    Address: 123 Test St, Rockford, IL 61101
    Phone: 555-555-0100
  Take screenshot: ~/hma-migration/screenshots/m18-01-checkout-form.png

STEP 5 — Enter payment and complete purchase:
  In the payment section, enter:
    Card: 4242 4242 4242 4242
    Expiry: 12/28
    CVC: 123
  Click "Place order" / "Sign up" / "Subscribe".
  Wait for the order confirmation page.
  Take screenshot: ~/hma-migration/screenshots/m18-01-confirmation.png

VERIFY (all must pass):
  [ ] Order confirmation page displayed with order number
  [ ] Order status is "Active" or "Processing"
  [ ] Subscription is mentioned on the confirmation page
  [ ] Customer account was created (or login was prompted)

STEP 6 — Verify in My Account:
  Navigate to: My Account > Subscriptions
  Verify: The new subscription appears with status "Active".
  Verify: Next payment date and amount are correct.
  Take screenshot: ~/hma-migration/screenshots/m18-01-my-account-sub.png

STEP 7 — Verify in wp-admin:
  Open a new tab, go to WP Admin > WooCommerce > Subscriptions.
  Find the subscription just created.
  Verify: Status is Active, correct product, correct billing schedule.
  Note the Subscription ID — you will need it for scenarios 2-5.
  Take screenshot: ~/hma-migration/screenshots/m18-01-admin-sub.png

STEP 8 — Check email:
  Check the email inbox used in Step 4.
  Verify: Order confirmation / subscription welcome email received.
  Verify: Email has correct branding (Haanpaa Martial Arts, not default WooCommerce).
  Take screenshot: ~/hma-migration/screenshots/m18-01-email.png

RESULT: [ ] PASS  [ ] FAIL
NOTES: Record the Subscription ID and Order ID for use in later scenarios.

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 2: Subscription Renewal Payment

```
TASK: Trigger and verify an automatic subscription renewal payment.

PREREQ: Active subscription from Scenario 1. Note the Subscription ID.

STEP 1 — Trigger early renewal from wp-admin:
  Go to: WP Admin > WooCommerce > Subscriptions
  Click on the subscription from Scenario 1.
  In the "Billing Schedule" section, look for a "Process Renewal" button or link.
  If available, click it to trigger an immediate renewal.

  ALTERNATIVE: If no "Process Renewal" button exists:
    Go to WP Admin > Tools > Action Scheduler (or WooCommerce > Status > Scheduled Actions).
    Search for the subscription ID in pending actions.
    Find the renewal action and click "Run" to trigger it immediately.

  Take screenshot: ~/hma-migration/screenshots/m18-02-trigger-renewal.png

STEP 2 — Verify renewal order created:
  Go to: WP Admin > WooCommerce > Orders
  Look for a new renewal order linked to the subscription.
  Verify: Order status is "Processing" or "Completed".
  Verify: Payment was charged via WooPayments (test mode).
  Take screenshot: ~/hma-migration/screenshots/m18-02-renewal-order.png

STEP 3 — Verify subscription still active:
  Go back to the subscription.
  Verify: Status is still "Active".
  Verify: Next payment date has advanced to the next billing cycle.
  Take screenshot: ~/hma-migration/screenshots/m18-02-sub-still-active.png

STEP 4 — Check email:
  Check the test customer's inbox.
  Verify: Renewal receipt / payment confirmation email received.
  Take screenshot: ~/hma-migration/screenshots/m18-02-email.png

RESULT: [ ] PASS  [ ] FAIL
NOTES:

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 3: Failed Payment and Retry

```
TASK: Simulate a failed payment and verify the retry mechanism activates.

STEP 1 — Update payment method to a decline card:
  Log in as the test customer from Scenario 1.
  Go to: My Account > Payment Methods
  Add a new payment method using the decline test card:
    Card: 4000 0000 0000 0002
    Expiry: 12/28
    CVC: 123
  Set it as the default payment method.
  Take screenshot: ~/hma-migration/screenshots/m18-03-decline-card-added.png

STEP 2 — Trigger a renewal:
  Go to wp-admin and trigger a renewal for this subscription
  (same method as Scenario 2 — Process Renewal or Action Scheduler).
  Take screenshot: ~/hma-migration/screenshots/m18-03-trigger-renewal.png

STEP 3 — Verify failure handled gracefully:
  Go to: WP Admin > WooCommerce > Orders
  Find the renewal order.
  Verify: Order status is "Failed".
  Take screenshot: ~/hma-migration/screenshots/m18-03-failed-order.png

  Go to: WP Admin > WooCommerce > Subscriptions
  Find the subscription.
  Verify: Subscription status changed to "On Hold" (NOT immediately Cancelled).
  Take screenshot: ~/hma-migration/screenshots/m18-03-sub-on-hold.png

STEP 4 — Verify retry scheduled:
  Go to: WP Admin > Tools > Action Scheduler (or WooCommerce > Status > Scheduled Actions)
  Search for the subscription ID.
  Verify: A retry action is scheduled for a future date.
  Take screenshot: ~/hma-migration/screenshots/m18-03-retry-scheduled.png

STEP 5 — Check email:
  Check the test customer's inbox.
  Verify: Failed payment notification email received.
  Take screenshot: ~/hma-migration/screenshots/m18-03-email.png

STEP 6 — Restore the subscription (cleanup for later tests):
  Update the payment method back to the success card (4242 4242 4242 4242).
  In wp-admin, change the subscription status back to "Active".
  Take screenshot: ~/hma-migration/screenshots/m18-03-restored.png

RESULT: [ ] PASS  [ ] FAIL
NOTES:

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 4: Subscription Cancellation from My Account

```
TASK: Cancel a subscription from the customer-facing My Account page.

PREREQ: Active subscription (restored from Scenario 3).

STEP 1 — Navigate to My Account:
  Log in as the test customer.
  Go to: My Account > Subscriptions
  Find the active subscription.
  Take screenshot: ~/hma-migration/screenshots/m18-04-before-cancel.png

STEP 2 — Cancel the subscription:
  Click "Cancel" on the subscription.
  Confirm the cancellation if a confirmation prompt appears.
  Take screenshot: ~/hma-migration/screenshots/m18-04-cancel-confirm.png

STEP 3 — Verify cancellation:
  Verify: Subscription status changes to "Pending Cancellation"
  (active until the end of the current billing period, then cancelled).
  Verify: The page shows when access will end.
  Take screenshot: ~/hma-migration/screenshots/m18-04-pending-cancel.png

STEP 4 — Verify in wp-admin:
  Go to: WP Admin > WooCommerce > Subscriptions
  Find the subscription.
  Verify: Status is "Pending Cancellation" (not immediately "Cancelled").
  Verify: End date matches the current billing period end.
  Take screenshot: ~/hma-migration/screenshots/m18-04-admin-pending.png

STEP 5 — Check email:
  Check the test customer's inbox.
  Verify: Cancellation confirmation email received.
  Take screenshot: ~/hma-migration/screenshots/m18-04-email.png

RESULT: [ ] PASS  [ ] FAIL
NOTES: After this test, you may need to create a new subscription for Scenario 5.

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 5: Subscription Tier Upgrade/Downgrade

```
TASK: Switch a subscription from one membership tier to another.

PREREQ: An active subscription. If the previous one was cancelled, create a new one
by repeating Scenario 1 with a different product (e.g., "Kids BJJ - Rockford").

STEP 1 — Navigate to subscription management:
  Log in as the test customer.
  Go to: My Account > Subscriptions
  Find the active subscription.
  Look for a "Switch" or "Upgrade" button/link.
  Take screenshot: ~/hma-migration/screenshots/m18-05-before-switch.png

  IF NO SWITCH BUTTON:
    This means subscription switching is not enabled or not configured for these
    products. Record this as a FAIL and note: "Subscription switching UI not
    available — check WooCommerce > Settings > Subscriptions > Allow Switching."
    STOP this scenario.

STEP 2 — Select a new tier:
  Click "Switch" / "Upgrade".
  Select a different membership tier (e.g., upgrade from Adult BJJ to All-Access,
  or downgrade from All-Access to Adult BJJ).
  Take screenshot: ~/hma-migration/screenshots/m18-05-select-tier.png

STEP 3 — Complete the switch:
  Review the prorated charge or credit shown.
  Verify: The amount makes sense (partial month charge for upgrade, credit for downgrade).
  Complete the checkout for the switch (use test card 4242 4242 4242 4242 if needed).
  Take screenshot: ~/hma-migration/screenshots/m18-05-switch-checkout.png

STEP 4 — Verify the switch:
  Go to: My Account > Subscriptions
  Verify: Subscription now shows the new tier/product.
  Verify: Billing amount reflects the new tier's pricing.
  Verify: Next payment date is correct.
  Take screenshot: ~/hma-migration/screenshots/m18-05-switched.png

STEP 5 — Verify in wp-admin:
  Go to: WP Admin > WooCommerce > Subscriptions
  Find the subscription.
  Verify: Product has changed to the new tier.
  Verify: Billing schedule and amount are correct.
  Take screenshot: ~/hma-migration/screenshots/m18-05-admin-switched.png

RESULT: [ ] PASS  [ ] FAIL
NOTES:

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 6: Drop-In / Trial Class One-Time Purchase

```
TASK: Purchase a simple (non-subscription) product to verify one-time payments work.

STEP 1 — Find a trial or drop-in product:
  Open a private/incognito browser window (or use the existing test customer).
  Navigate to the Shop page.
  Find a simple product (non-subscription) — e.g., "Free Trial Class" or "Drop-In".
  Click to view the product.
  Verify: Product shows a one-time price (no "/ month" or recurring language).
  Take screenshot: ~/hma-migration/screenshots/m18-06-product.png

STEP 2 — Complete purchase:
  Add to cart.
  Proceed to checkout.
  Enter billing details (or use saved details if logged in).
  Pay with: 4242 4242 4242 4242
  Complete the order.
  Take screenshot: ~/hma-migration/screenshots/m18-06-confirmation.png

STEP 3 — Verify:
  [ ] Order confirmation page displayed
  [ ] Order status is "Processing" or "Completed"
  [ ] NO subscription was created (check My Account > Subscriptions — should
      not show a new entry for this purchase)
  [ ] My Account > Orders shows the one-time order

STEP 4 — Check email:
  Verify: Order confirmation email received.
  Take screenshot: ~/hma-migration/screenshots/m18-06-email.png

RESULT: [ ] PASS  [ ] FAIL
NOTES:

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 7: Mobile Checkout

```
TASK: Complete a purchase on a mobile device to verify responsive checkout.

STEP 1 — Open the site on mobile:
  Use a real mobile device OR use browser DevTools responsive mode
  (Chrome: F12 > toggle device toolbar > select iPhone 14 or similar).
  Navigate to: https://haanpaa-staging.mystagingwebsite.com
  Take screenshot: ~/hma-migration/screenshots/m18-07-mobile-home.png

STEP 2 — Browse and add a product:
  Navigate to a membership product.
  Add to cart.
  Proceed to checkout.
  Take screenshot: ~/hma-migration/screenshots/m18-07-mobile-checkout.png

STEP 3 — Verify mobile usability:
  [ ] All form fields are visible and tappable without zooming
  [ ] Card input fields (number, expiry, CVC) are accessible
  [ ] The on-screen keyboard does not permanently obscure the form
  [ ] Buttons ("Place order") are large enough to tap (minimum 44x44px)
  [ ] No horizontal scrolling required
  [ ] No elements overlapping or cut off

STEP 4 — Complete purchase:
  Enter test card: 4242 4242 4242 4242
  Complete the order.
  Verify: Order confirmation page displays correctly on mobile.
  Take screenshot: ~/hma-migration/screenshots/m18-07-mobile-confirmation.png

RESULT: [ ] PASS  [ ] FAIL
NOTES: Note any layout issues, overlapping elements, or usability problems.

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 8: Email Notification Audit

```
TASK: Verify all email notifications from Scenarios 1-4 were received with correct
branding and content.

PREREQ: Complete Scenarios 1 through 4 first. Check the test customer's email inbox.

CHECK EACH EMAIL:

  EMAIL 1 — New order / subscription confirmation (from Scenario 1):
    [ ] Received
    [ ] Subject line mentions the order or subscription
    [ ] Body contains: order number, product name, price, billing schedule
    [ ] Branding: "Haanpaa Martial Arts" (not "WordPress" or "WooCommerce" generic)
    [ ] Reply-to address is correct (not noreply@wordpress.com)
    [ ] Mobile-responsive (check on phone or resize browser window)
    Take screenshot: ~/hma-migration/screenshots/m18-08-email-new-order.png

  EMAIL 2 — Subscription renewal receipt (from Scenario 2):
    [ ] Received
    [ ] Contains renewal amount and next payment date
    [ ] Correct branding
    Take screenshot: ~/hma-migration/screenshots/m18-08-email-renewal.png

  EMAIL 3 — Failed payment notice (from Scenario 3):
    [ ] Received
    [ ] Clearly explains the payment failed
    [ ] Includes instructions to update payment method
    [ ] Contains a link to My Account > Payment Methods
    [ ] Correct branding
    Take screenshot: ~/hma-migration/screenshots/m18-08-email-failed.png

  EMAIL 4 — Cancellation confirmation (from Scenario 4):
    [ ] Received
    [ ] Confirms the cancellation
    [ ] States when access will end
    [ ] Correct branding
    Take screenshot: ~/hma-migration/screenshots/m18-08-email-cancel.png

IF ANY EMAIL IS MISSING:
  Go to WP Admin > WooCommerce > Settings > Emails
  Verify the relevant email template is enabled.
  Check the "From" name and address.
  Take screenshot: ~/hma-migration/screenshots/m18-08-email-settings.png

RESULT: [ ] PASS  [ ] FAIL
NOTES: List any missing emails, branding issues, or broken links.

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Test Scenario 9: Tax Calculation

```
TASK: Verify tax calculation at checkout (if tax is configured).

STEP 1 — Check if tax is configured:
  Go to: WP Admin > WooCommerce > Settings > Tax
  If the Tax tab does not exist or tax is disabled:
    Record: "Tax not configured — skipping this scenario."
    RESULT: [ ] N/A
    STOP this scenario.

  If tax is enabled:
    Note the tax rate(s) configured.
    Take screenshot: ~/hma-migration/screenshots/m18-09-tax-settings.png

STEP 2 — Test tax at checkout:
  Open the storefront.
  Add a membership product to cart.
  Proceed to checkout.
  Enter a Rockford, IL billing address (123 Test St, Rockford, IL 61101).
  Before placing the order, check the order summary.

VERIFY:
  [ ] Tax line item appears in the order summary (or "Tax: $0.00" if exempt)
  [ ] Tax amount is correct for the Rockford/Winnebago County rate
  [ ] Tax is itemized separately from the product price
  Take screenshot: ~/hma-migration/screenshots/m18-09-tax-checkout.png

STEP 3 — Test Beloit address:
  Change the billing address to a Beloit, WI address
  (e.g., 456 Test Ave, Beloit, WI 53511).
  Verify: Tax updates for Wisconsin jurisdiction.
  Take screenshot: ~/hma-migration/screenshots/m18-09-tax-beloit.png

RESULT: [ ] PASS  [ ] FAIL  [ ] N/A (tax not configured)
NOTES: Record the tax rates observed. Confirm with Darby that they are correct.

OUTPUT: Append results to ~/hma-migration/exports/m18-test-results.md
```

---

## Final Summary

```
TASK: Compile the final M1.8 test results summary.

After completing all 9 scenarios, create a summary file:

OUTPUT FILE: ~/hma-migration/exports/m18-final-summary.md

CONTENTS:
  # M1.8 Checkout Flow Test Results
  **Date:** [today's date]
  **Site:** haanpaa-staging.mystagingwebsite.com
  **Tester:** [your name or "Claude CoWork"]

  ## Prerequisites
  | Setting | Status |
  (from prereq check)

  ## Test Results
  | # | Scenario | Result | Notes |
  |---|----------|--------|-------|
  | 1 | New customer subscription purchase | PASS/FAIL | ... |
  | 2 | Subscription renewal payment | PASS/FAIL | ... |
  | 3 | Failed payment retry | PASS/FAIL | ... |
  | 4 | Subscription cancellation | PASS/FAIL | ... |
  | 5 | Tier upgrade/downgrade | PASS/FAIL | ... |
  | 6 | Drop-in / trial one-time purchase | PASS/FAIL | ... |
  | 7 | Mobile checkout | PASS/FAIL | ... |
  | 8 | Email notifications | PASS/FAIL | ... |
  | 9 | Tax calculation | PASS/FAIL/N/A | ... |

  ## Issues Found
  (List any failures, bugs, or UX issues with screenshots)

  ## Recommendation
  [ ] ALL PASS — M1.8 is complete. Proceed to M1.9 (DNS + Go-Live).
  [ ] FAILURES FOUND — Fix issues and re-test before proceeding.

IMPORTANT — UPDATE THE PLAYBOOK:
After completing all tests, update this playbook file in the monorepo at:
~/Local Sites/gym-core/docs/planning/cowork-m18-checkout-testing.md

Add a "## Completed Run" section at the bottom with:
1. Date you ran it
2. Exact navigation paths used (correct any that were wrong)
3. Any UI differences from what was described
4. Issues, surprises, or blockers encountered
5. Overall pass/fail count
```
