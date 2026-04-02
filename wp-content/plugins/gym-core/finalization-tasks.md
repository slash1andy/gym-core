# Finalization Tasks for Gym Core

## Critical Priority

### TASK-TRC-001: Fix Open Mat check-in — location empty when class_id=0
- **File:** `src/API/AttendanceController.php` lines 166-170, `assets/js/kiosk.js` line 240
- **Issue:** When kiosk has no classes today and calls check-in with `class_id=0`, `get_the_terms(0, 'gym_location')` returns false, producing `$location = ''`. Validator rejects with `missing_location`.
- **Fix:**
  1. Add `location` as an optional route arg in `register_routes()`:
     ```php
     'location' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
     ```
  2. In `check_in()`, fall back to request-provided location when terms are empty:
     ```php
     $location = ( $location_terms && ! is_wp_error( $location_terms ) )
         ? $location_terms[0]->slug
         : sanitize_text_field( $request->get_param( 'location' ) ?? '' );
     ```
  3. In `kiosk.js` `performCheckIn()`, include location in the POST body:
     ```js
     body: JSON.stringify( {
         user_id:  selectedMember.id,
         class_id: classId,
         method:   'name_search',
         location: config.location,   // <-- add this
     } ),
     ```
- **Status:** [ ] Not started

### TASK-TRC-002: Fix TypeError on first-ever promotion
- **File:** `src/Gamification/BadgeEngine.php` line 99
- **Issue:** `evaluate_on_promotion( ..., string $from_belt, ... )` receives `null` when a member has no previous rank (first-ever promotion). PHP 8.x strict types throws TypeError.
- **Fix:** Change signature to `?string $from_belt` and add null check:
  ```php
  public function evaluate_on_promotion( int $user_id, string $program, string $new_belt, int $new_stripes, ?string $from_belt, int $promoted_by ): void {
      if ( null === $from_belt || $new_belt === $from_belt ) {
          return;
      }
  ```
  Note: First-ever promotions should still get the `belt_promotion` badge. Adjust logic to only skip when belt is unchanged (stripe addition), not when from_belt is null:
  ```php
  if ( $new_belt === $from_belt ) {
      return; // Stripe addition, not a belt change
  }
  // $from_belt being null means first rank — still award badge
  ```
- **Status:** [ ] Not started

## High Priority

### TASK-OPT-001: Fix N+1 queries in PromotionEligibility::get_eligible_members()
- **File:** `src/Attendance/PromotionEligibility.php` lines 153-208
- **Issue:** For each ranked member, `check()` runs 2-3 DB queries. 200 members = 600-800 queries.
- **Fix:**
  1. Batch-fetch attendance counts with `SELECT user_id, COUNT(*) FROM gym_attendance WHERE user_id IN (...) AND checked_in_at >= promoted_at GROUP BY user_id`
  2. Prime WP user cache: `cache_users( $user_ids )`
  3. Batch-fetch user meta for coach recommendations
- **Status:** [ ] Not started

### TASK-OPT-002: Extract location labels to Taxonomy::get_location_labels()
- **File:** `src/Location/Taxonomy.php` + 4 consumer files
- **Issue:** Location label maps duplicated in OrderLocation, BlockIntegration, StoreApiExtension, LocationSelector, LocationController.
- **Fix:** Add to Taxonomy.php:
  ```php
  public static function get_location_labels(): array {
      return array(
          self::ROCKFORD => __( 'Rockford', 'gym-core' ),
          self::BELOIT   => __( 'Beloit', 'gym-core' ),
      );
  }
  ```
  Replace all hardcoded maps with `Taxonomy::get_location_labels()`.
- **Status:** [ ] Not started

### TASK-OPT-003: Fix streak freeze quarterly reset
- **File:** `src/Gamification/StreakTracker.php` lines 218-219
- **Issue:** `_gym_streak_freezes_used` user meta never resets. Members permanently lose freezes.
- **Fix:** Store quarter alongside count:
  ```php
  private function get_freezes_used( int $user_id ): int {
      $data = get_user_meta( $user_id, '_gym_streak_freezes_used', true );
      if ( ! is_array( $data ) || ( $data['quarter'] ?? '' ) !== $this->current_quarter() ) {
          return 0; // New quarter — reset
      }
      return (int) ( $data['count'] ?? 0 );
  }

  private function current_quarter(): string {
      return gmdate( 'Y' ) . '-Q' . ceil( (int) gmdate( 'n' ) / 3 );
  }
  ```
  Update `freeze_streak()` to write `['quarter' => $this->current_quarter(), 'count' => $used + 1]`.
- **Status:** [ ] Not started

## Medium Priority

### TASK-SEC-001: Fix Twilio auth token plaintext vs "encrypted" claim
- **File:** `src/Admin/Settings.php` line 420
- **Issue:** Description says "Credentials are stored encrypted" but they are stored as plaintext wp_options.
- **Fix:** Either implement encryption or change the description to: `'Configure Twilio credentials for SMS notifications.'` (remove the false claim).
- **Status:** [ ] Not started

### TASK-TRC-003: Fix TwiML response JSON-wrapping
- **File:** `src/SMS/InboundHandler.php` lines 191-205
- **Issue:** WP_REST_Response JSON-encodes the XML string body. Twilio expects raw XML.
- **Fix:** Use `rest_pre_serve_request` filter or replace with direct output:
  ```php
  private function twiml_response( string $message = '' ): void {
      header( 'Content-Type: application/xml' );
      echo '<?xml version="1.0" encoding="UTF-8"?><Response>';
      if ( '' !== $message ) {
          echo '<Message>' . esc_xml( $message ) . '</Message>';
      }
      echo '</Response>';
      exit;
  }
  ```
  Update callers to `return` after calling `twiml_response()`.
- **Status:** [ ] Not started

### TASK-OPT-004: Fix N+1 in GamificationController badge definitions
- **File:** `src/API/GamificationController.php` lines 133-174
- **Issue:** `has_badge()` queries per badge (14 queries). `get_user_badges()` called repeatedly.
- **Fix:** Fetch user badges once, index by slug:
  ```php
  $user_badges_raw = $this->badges->get_user_badges( $user_id );
  $earned_map = array();
  foreach ( $user_badges_raw as $ub ) {
      $earned_map[ $ub->badge_slug ] = $ub->earned_at;
  }
  // Then: $item['earned'] = isset( $earned_map[ $slug ] );
  ```
- **Status:** [ ] Not started

### TASK-OPT-005: Deduplicate StreakTracker/BadgeEngine instances
- **File:** `src/Plugin.php` lines 191-193 and 255-257
- **Issue:** Two separate instances created — one for API, one for hooks.
- **Fix:** Store as class properties, create once in `register_attendance_modules()`, reuse in both `register_api_modules()` and `register_gamification_modules()`.
- **Status:** [ ] Not started

### TASK-OPT-006: Debounce badge evaluation via Action Scheduler
- **File:** `src/Gamification/BadgeEngine.php` line 59
- **Issue:** 5+ queries per check-in for badge evaluation.
- **Fix:** Replace synchronous hook with `as_schedule_single_action( time(), 'gym_core_evaluate_badges', array( $user_id ) )` and evaluate in the background.
- **Status:** [ ] Not started

### TASK-UX-001: Fix 26 title-case violations to sentence case
- **File:** `src/Admin/Settings.php`, `src/Schedule/ClassPostType.php`, `src/Attendance/KioskEndpoint.php`
- **Issue:** WooCommerce UX guidelines require sentence case. 26 strings use title case.
- **Fix:** Change `'General Settings'` → `'General settings'`, `'Belt Rank Settings'` → `'Belt rank settings'`, etc. Full list in UX audit.
- **Status:** [ ] Not started

### TASK-SEC-002: Use actual request URL for Twilio signature validation
- **File:** `src/SMS/InboundHandler.php` line 90
- **Issue:** `rest_url()` may not match actual request URL behind proxies.
- **Fix:** Build URL from `$_SERVER['HTTP_HOST']` + `$_SERVER['REQUEST_URI']` with scheme detection.
- **Status:** [ ] Not started

### TASK-OPT-007: Extract permission callback to BaseController
- **File:** `src/API/BaseController.php` + 4 controllers
- **Issue:** "View own or have capability" pattern repeated 3 times.
- **Fix:** Add `protected function permissions_view_own_or_cap( $request, $id_param, $capability )` to BaseController.
- **Status:** [ ] Not started

### TASK-OPT-008: Conditional frontend asset loading
- **File:** `src/Frontend/LocationSelector.php` line 85
- **Issue:** Location selector CSS/JS loaded on every frontend page.
- **Fix:** Check `'yes' === get_option( 'gym_core_require_location', 'yes' )` and skip on REST/AJAX requests.
- **Status:** [ ] Not started

## Low Priority

### TASK-SEC-003: Wrap unprepared GROUP BY in prepare()
- **File:** `src/Rank/RankStore.php` line 259
- **Fix:** Add `$wpdb->prepare()` for consistency (no user input but coding standard).
- **Status:** [ ] Not started

### TASK-SEC-004: Escape post_content in API response
- **File:** `src/API/ClassScheduleController.php` line 289
- **Fix:** `'description' => wp_kses_post( $post->post_content )`
- **Status:** [ ] Not started

### TASK-UX-002: Remove viewport zoom lock on kiosk
- **File:** `src/Attendance/KioskEndpoint.php` line 185
- **Fix:** Remove `maximum-scale=1.0, user-scalable=no` from viewport meta.
- **Status:** [ ] Not started

### TASK-TRC-004: Add role filter to kiosk member search
- **File:** `assets/js/kiosk.js` line 126
- **Fix:** Add `&roles=customer,subscriber` to the search URL.
- **Status:** [ ] Not started

### TASK-TRC-005: Add per-user SMS rate limit fallback
- **File:** `src/API/SMSController.php` line 151
- **Fix:** When `contact_id` is missing, rate limit by `get_current_user_id()` using `BaseController::check_rate_limit()`.
- **Status:** [ ] Not started

### TASK-INFRA-001: Add WC + WP-CLI stubs to PHPStan config
- **File:** `phpstan.neon`
- **Fix:** Add `php-stubs/woocommerce-stubs` and `php-stubs/wp-cli-stubs` to require-dev and PHPStan config.
- **Status:** [ ] Not started
