# Member Transition SMS Messages

> 3 SMS messages matching the email series cadence. All messages are under 160
> characters. Uses gym-core `MessageTemplates` class with the slugs listed below.

---

## SMS 1 — 2-week notice

**Template slug:** `transition_announce`

**Message (149 chars):**

```
Hey {first_name}, {gym_name} is upgrading our member system! Your plan & pricing stay the same. More details coming soon. Questions? Reply here.
```

**Trigger:** Send same day as Email 1 (T-14 days), via Twilio integration.

---

## SMS 2 — 1-week notice (portal ready)

**Template slug:** `transition_portal_ready`

**Message (153 chars):**

```
{first_name}, your new {gym_name} member portal is ready! Log in at {portal_url} to set your password and update your payment method.
```

**Trigger:** Send same day as Email 2 (T-7 days), or on `gym_core_member_migrated` hook.

---

## SMS 3 — Day-of confirmation

**Template slug:** `transition_complete`

**Message (143 chars):**

```
{first_name}, you're all set! Your {gym_name} membership is live on our new system. Next billing: {billing_date}. Questions? Reply anytime.
```

**Trigger:** Send same day as Email 3 (cutover day), or on `gym_core_transition_complete` hook.

---

## Integration Notes

- All templates are registered in `wp-content/plugins/gym-core/src/SMS/MessageTemplates.php`
- Render via `MessageTemplates::render( 'transition_announce', [ 'first_name' => '...', ... ] )`
- Sending is handled by the existing `Gym_Core\SMS` Twilio integration
- Messages respect TCPA opt-in status — only sent to members with SMS consent
