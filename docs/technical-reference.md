# Haanpaa Martial Arts -- Technical Reference

> Machine-readable technical reference for AI agents and developers working in the gym-core and hma-ai-chat codebases. Generated from source analysis on 2026-03-30.

---

## 1. Architecture Overview

### Plugin Structure

Two WordPress plugins in a monorepo that maps to `wp-content/` via Pressable GitHub Deploy:

| Plugin | Namespace | Path | Purpose |
|--------|-----------|------|---------|
| `gym-core` | `Gym_Core\` | `wp-content/plugins/gym-core/` | Core gym management: locations, attendance, ranks, gamification, SMS, scheduling, briefings, CRM |
| `hma-ai-chat` | `HMA_AI_Chat\` | `wp-content/plugins/hma-ai-chat/` | Staff-facing AI chat interface ("Gandalf") with tool-calling, pending action approval queue |

### PSR-4 Autoloading

Both plugins use Composer PSR-4 autoloading:

- `Gym_Core\` maps to `wp-content/plugins/gym-core/src/`
- `HMA_AI_Chat\` maps to `wp-content/plugins/hma-ai-chat/src/`

### Dependency Injection Pattern

`Gym_Core\Plugin` is a singleton that acts as a poor-man's DI container. Shared instances are created once and injected into consumers:

```
Plugin::instance()->init()
  -> register_attendance_modules()     // Creates AttendanceStore, RankStore, CheckInValidator, PromotionEligibility, FoundationsClearance
  -> register_api_modules()            // Injects stores into REST controllers
  -> register_gamification_modules()   // Creates StreakTracker, BadgeEngine with AttendanceStore
  -> register_member_modules()         // Creates MemberDashboard with all stores + gamification
```

### Module Registration Flow

```
plugins_loaded (priority 10)
  -> Verify WooCommerce active
  -> Plugin::instance()->init()
    -> load_textdomain()
    -> register_capabilities()         // Capabilities class, syncs on version change
    -> register_admin_modules()        // Settings, AttendanceDashboard, PromotionDashboard, UserProfileRank
    -> register_location_modules()     // Taxonomy, Manager, ProductFilter, OrderLocation, LocationSelector, BlockIntegration
    -> register_api_modules()          // All REST controllers (some deferred to gym_core_loaded)
    -> register_schedule_modules()     // ClassPostType, ICalFeed
    -> register_attendance_modules()   // Data stores + MilestoneTracker
    -> register_briefing_modules()     // AnnouncementPostType
    -> register_notification_modules() // PromotionNotifier
    -> register_social_modules()       // PromotionPost
    -> register_kiosk_modules()        // KioskEndpoint
    -> register_gamification_modules() // BadgeEngine, StreakTracker
    -> register_integration_modules()  // FormToCrm
    -> register_member_modules()       // ContentGating, MemberDashboard (deferred to gym_core_loaded)
    -> do_action('gym_core_loaded')    // Signals all modules are ready
```

### Database Tables

Four custom tables managed by `Gym_Core\Data\TableManager`. Schema version tracked in `gym_core_db_version` option.

#### `{prefix}gym_ranks` -- Current belt rank per member per program

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint(20) unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint(20) unsigned | NOT NULL |
| `program` | varchar(64) | NOT NULL. Values: `adult-bjj`, `kids-bjj`, `kickboxing` |
| `belt` | varchar(64) | NOT NULL. e.g. `white`, `blue`, `purple`, `brown`, `black`, `grey-white`, `level-1` |
| `stripes` | tinyint(1) unsigned | DEFAULT 0. 0-4 for belts, 0-10 for Black Belt degrees |
| `promoted_at` | datetime | NOT NULL |
| `promoted_by` | bigint(20) unsigned | Nullable. Coach user ID |

Indexes: `PRIMARY (id)`, `UNIQUE user_program (user_id, program)`, `belt (belt)`, `promoted_at (promoted_at)`

#### `{prefix}gym_rank_history` -- Full promotion audit trail

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint(20) unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint(20) unsigned | NOT NULL |
| `program` | varchar(64) | NOT NULL |
| `from_belt` | varchar(64) | Nullable (null on first rank assignment) |
| `from_stripes` | tinyint(1) unsigned | Nullable |
| `to_belt` | varchar(64) | NOT NULL |
| `to_stripes` | tinyint(1) unsigned | DEFAULT 0 |
| `promoted_at` | datetime | NOT NULL |
| `promoted_by` | bigint(20) unsigned | Nullable |
| `notes` | text | Nullable |

Indexes: `PRIMARY (id)`, `user_id`, `program`, `promoted_at`

#### `{prefix}gym_attendance` -- Check-in records

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint(20) unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint(20) unsigned | NOT NULL |
| `class_id` | bigint(20) unsigned | Nullable. 0 = open mat / no specific class |
| `location` | varchar(64) | NOT NULL. `rockford` or `beloit` |
| `checked_in_at` | datetime | NOT NULL |
| `method` | varchar(32) | DEFAULT `manual`. Values: `qr_scan`, `member_id`, `name_search`, `manual`, `imported` |

Indexes: `PRIMARY (id)`, `user_id`, `class_id`, `location`, `checked_in_at`, `user_date (user_id, checked_in_at)`

#### `{prefix}gym_achievements` -- Earned badges

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint(20) unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint(20) unsigned | NOT NULL |
| `badge_slug` | varchar(64) | NOT NULL |
| `earned_at` | datetime | NOT NULL |
| `metadata` | longtext | Nullable. JSON string with context (e.g. promotion details) |

Indexes: `PRIMARY (id)`, `UNIQUE user_badge (user_id, badge_slug)`, `badge_slug`, `earned_at`

#### hma-ai-chat tables

- `{prefix}hma_ai_conversations` -- Chat conversation persistence
- `{prefix}hma_ai_messages` -- Individual messages within conversations
- `{prefix}hma_ai_pending_actions` -- Approval queue for write tools

---

## 2. REST API Reference

All gym-core endpoints use the `gym/v1` namespace. Standard response envelope: `{ "success": bool, "data": mixed, "meta"?: object }`.

### LocationController

#### `GET /gym/v1/locations`
- **Auth**: Public
- **Params**: None
- **Response**: `{ success: true, data: [{ slug, name, description, count, link }] }`

#### `GET /gym/v1/locations/{slug}`
- **Auth**: Public
- **Params**: `slug` (string, required) -- Location slug (`rockford`, `beloit`)
- **Response**: `{ success: true, data: { slug, name, description, count, link } }`
- **Errors**: `404 gym_location_not_found`

#### `GET /gym/v1/locations/{slug}/products`
- **Auth**: Public
- **Params**: `slug` (string, required), `page` (int, default 1), `per_page` (int, default 10, max 100)
- **Response**: `{ success: true, data: [{ id, name, slug, price, regular_price, status, permalink, image }], meta: { pagination: { total, total_pages, page, per_page } } }`

#### `GET /gym/v1/user/location`
- **Auth**: Authenticated user
- **Response**: `{ success: true, data: { location: "rockford", label: "Rockford" } }` or `{ success: true, data: null }`

#### `PUT /gym/v1/user/location`
- **Auth**: Authenticated user
- **Body**: `{ "location": "rockford" }`
- **Response**: `{ success: true, data: { location, label } }`
- **Errors**: `422 gym_invalid_location`

### AttendanceController

#### `POST /gym/v1/check-in`
- **Auth**: `gym_check_in_member` or `manage_options`
- **Body**: `{ user_id: int (required), class_id: int (required), method: string (required, enum: qr_scan|member_id|name_search|manual), location?: string }`
- **Response (201)**: `{ success: true, data: { attendance_id, user: {id, name}, class: {id, name}, location, checked_in_at, method } }`
- **Errors**: `409 duplicate_checkin`, `403 no_active_membership`, `500 checkin_failed`

#### `GET /gym/v1/attendance/{user_id}`
- **Auth**: Own data or `gym_view_attendance`
- **Params**: `user_id` (int, required), `page`, `per_page`, `from` (Y-m-d), `to` (Y-m-d)
- **Response**: `{ success: true, data: [{ id, class: {id, name}|null, location, checked_in_at, method }], meta: { pagination } }`

#### `GET /gym/v1/attendance/today`
- **Auth**: `gym_view_attendance` or `manage_woocommerce`
- **Params**: `location?` (string), `class_id?` (int)
- **Response**: `{ success: true, data: [{ id, user: {id, name}, class_id, location, checked_in_at, method }], meta: { total } }`

### RankController

#### `GET /gym/v1/members/{id}/rank`
- **Auth**: Own data or `gym_view_ranks`
- **Params**: `id` (int, required), `program?` (string)
- **Response**: `{ success: true, data: [{ program, belt, stripes, promoted_at, promoted_by: {id, name}|null, attendance_since_promotion, next_belt }] }`

#### `GET /gym/v1/members/{id}/rank-history`
- **Auth**: Own data or `gym_view_ranks`
- **Params**: `id` (int, required), `program?` (string), `page`, `per_page`
- **Response**: `{ success: true, data: [{ program, from_belt, from_stripes, to_belt, to_stripes, promoted_at, promoted_by: {id, name}|null, notes }] }`

#### `POST /gym/v1/ranks/promote`
- **Auth**: `gym_promote_student` or `manage_woocommerce`
- **Body**: `{ user_id: int (required), program: string (required), belt?: string, stripes?: int, notes?: string }`
- **Notes**: If `belt` is empty, adds a stripe to the current belt instead
- **Response**: `{ success: true, data: { program, belt, stripes, promoted_at, promoted_by, attendance_since_promotion, next_belt } }`
- **Errors**: `404 invalid_user`, `400 invalid_program`, `400 stripe_failed`

### PromotionController

#### `GET /gym/v1/promotions/eligible`
- **Auth**: `manage_woocommerce`
- **Params**: `program` (string, required)
- **Response**: `{ success: true, data: [{ user_id, display_name, belt, stripes, eligible, attendance_count, attendance_required, days_at_rank, days_required, has_recommendation, next_belt }], meta: { total } }`

#### `POST /gym/v1/promotions/recommend`
- **Auth**: `gym_promote_student` or `manage_woocommerce`
- **Body**: `{ user_id: int (required), program: string (required) }`
- **Response (201)**: `{ success: true, data: { user_id, program, recommended_by, recommended_at } }`

### FoundationsController

#### `GET /gym/v1/foundations/{user_id}`
- **Auth**: Own data or `gym_view_ranks`
- **Response**: `{ success: true, data: { user_id, display_name, in_foundations, cleared, phase, classes_completed, classes_phase1_required, classes_total_required, coach_rolls_completed, coach_rolls_required, cleared_at, live_training_allowed } }`

#### `POST /gym/v1/foundations/enroll`
- **Auth**: `gym_promote_student` or `manage_woocommerce`
- **Body**: `{ user_id: int (required) }`
- **Response (201)**: Foundations status object
- **Errors**: `409 already_enrolled`, `400 disabled`

#### `POST /gym/v1/foundations/coach-roll`
- **Auth**: `gym_promote_student` or `manage_woocommerce`
- **Body**: `{ user_id: int (required), notes?: string }`
- **Errors**: `400 not_in_foundations`

#### `POST /gym/v1/foundations/clear`
- **Auth**: `gym_promote_student` or `manage_woocommerce`
- **Body**: `{ user_id: int (required) }`
- **Errors**: `400 not_in_foundations`

#### `GET /gym/v1/foundations/active`
- **Auth**: `gym_view_ranks` or `manage_woocommerce`
- **Response**: `{ success: true, data: [{ user_id, display_name, status: {...} }] }`

### BriefingController

#### `GET /gym/v1/briefings/class/{class_id}`
- **Auth**: `gym_view_briefing` or `manage_woocommerce`
- **Params**: `class_id` (int, required)
- **Response**: `{ success: true, data: { class: {id, name, program, location, day_of_week, start_time, end_time, instructor}, roster: [...], alerts: [...], announcements: [...], generated_at } }`

#### `GET /gym/v1/briefings/today`
- **Auth**: `gym_view_briefing` or `manage_woocommerce`
- **Params**: `location?` (string)
- **Response**: Array of briefing objects sorted by class start time

#### `GET /gym/v1/announcements`
- **Auth**: `gym_view_briefing` or `manage_woocommerce`
- **Params**: `location?` (string), `program?` (string)
- **Response**: `{ success: true, data: [{ id, title, content, type, target_location, target_program, start_date, end_date, pinned, author }] }`

#### `POST /gym/v1/announcements`
- **Auth**: `gym_manage_announcements` or `manage_woocommerce`
- **Body**: `{ title: string (required), content?: string, type?: enum(global|location|program), target_location?: string, target_program?: string, start_date?: string(Y-m-d), end_date?: string(Y-m-d), pinned?: bool }`
- **Response (201)**: Created announcement object

### SMSController

#### `POST /gym/v1/sms/send`
- **Auth**: `manage_options` or `gym_send_sms`
- **Body**: `{ phone: string (required, E.164), message?: string, template_slug?: string, variables?: object, contact_id?: int }`
- **Response (201)**: `{ success: true, data: { sid, to, body, sent_at } }`
- **Errors**: `400 invalid_phone`, `400 missing_message`, `400 invalid_template`, `429 rate_limited`, `502 send_failed`

#### `GET /gym/v1/sms/templates`
- **Auth**: `manage_options` or `gym_send_sms`
- **Response**: `{ success: true, data: [{ slug, name, body, description }] }`

#### `POST /gym/v1/sms/webhook` (Twilio inbound)
- **Auth**: Twilio signature validation (not WP auth)
- **Body**: Twilio POST params (`From`, `To`, `Body`, `MessageSid`)
- **Response**: TwiML XML

### ClassScheduleController

#### `GET /gym/v1/classes`
- **Auth**: Public
- **Params**: `page`, `per_page`, `location?` (string), `program?` (string), `instructor?` (int)
- **Response**: `{ success: true, data: [{ id, name, description, program, instructor: {id, name}, day_of_week, start_time, end_time, capacity, recurrence, status, location }], meta: { pagination } }`

#### `GET /gym/v1/classes/{id}`
- **Auth**: Public
- **Response**: Single class object

#### `GET /gym/v1/schedule`
- **Auth**: Public
- **Params**: `location` (string, required), `week_of?` (string, Y-m-d), `program?` (string)
- **Response**: `{ success: true, data: [{ date, day_name, classes: [{ id, name, program, instructor, start_time, end_time, location, capacity }] }] }` -- 7 days Monday-Sunday

### GamificationController

#### `GET /gym/v1/badges`
- **Auth**: Public
- **Params**: `category?` (string: `attendance`, `rank`, `special`)
- **Response**: Badge definitions with optional `earned`/`earned_at` if user is logged in

#### `GET /gym/v1/members/{id}/badges`
- **Auth**: Own data or `gym_view_achievements`
- **Response**: `{ success: true, data: [{ badge: {slug, name, description, icon}, earned_at, metadata }], meta: { total_badges_earned, total_badges_available } }`

#### `GET /gym/v1/members/{id}/streak`
- **Auth**: Own data or `gym_view_achievements`
- **Response**: `{ success: true, data: { current_streak, longest_streak, streak_started_at, freezes_remaining, freezes_used, last_check_in_date, streak_status } }`

### MemberController

#### `GET /gym/v1/members/me/dashboard`
- **Auth**: Authenticated user
- **Response**: `{ success: true, data: { member: {id, display_name, email, location}, memberships: [...], billing: {next_payment_date, next_payment_amount, payment_method_summary}|null, upcoming_classes: [...], rank: [...], foundations: {...}|null, gamification: {current_streak_weeks, badges_earned_count, total_classes}|null, quick_links: {update_payment_url, billing_history_url, schedule_url, shop_url} } }`

### SocialPostManager

#### `POST /gym/v1/social/draft`
- **Auth**: `gym_manage_announcements`
- **Body**: `{ title: string (required), content: string (required), category?: string }`
- **Response (201)**: `{ success: true, data: { post_id, status: "pending", message, edit_url } }`

#### `GET /gym/v1/social/pending`
- **Auth**: `gym_manage_announcements`
- **Response**: Array of pending social posts

#### `POST /gym/v1/social/{post_id}/approve`
- **Auth**: `gym_manage_announcements`
- **Response**: `{ success: true, data: { post_id, status: "publish", message } }`

---

## 3. Data Models

### User Meta Keys

| Key | Type | Purpose |
|-----|------|---------|
| `gym_location` | string | User's preferred location slug (`rockford` or `beloit`) |
| `_gym_coach_recommendation_{program}` | array | `{ coach_id: int, recommended_at: string }` -- Coach recommendation for promotion |
| `_gym_foundations_status` | array | `{ enrolled_at, cleared_at, cleared_by, classes_at_clearance, coach_rolls_at_clearance }` |
| `_gym_foundations_coach_rolls` | array | Array of `{ coach_id: int, date: string, notes: string }` |
| `_gym_milestones_reached` | array | Array of milestone integers (e.g. `[10, 25, 50]`) |
| `_gym_streak_freezes_used` | array | `{ quarter: "2026-Q1", count: int }` |
| `_gym_streak_frozen_at` | string | Datetime of last streak freeze |
| `_gym_medical_notes` | string | Free-text medical/injury notes shown in briefings |
| `_gym_dashboard_location` | string | Admin dashboard location preference |
| `billing_phone` | string | WooCommerce billing phone, used for SMS notifications |

### Post Meta Keys (gym_class CPT)

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `_gym_class_instructor` | integer | 0 | Instructor WP user ID |
| `_gym_class_capacity` | integer | 30 | Max students |
| `_gym_class_day_of_week` | string | '' | `monday` through `sunday` |
| `_gym_class_start_time` | string | '' | 24hr format `H:i` |
| `_gym_class_end_time` | string | '' | 24hr format `H:i` |
| `_gym_class_recurrence` | string | `weekly` | `weekly`, `biweekly`, `monthly` |
| `_gym_class_status` | string | `active` | `active`, `cancelled`, `suspended` |

### Post Meta Keys (gym_announcement CPT)

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `_gym_announcement_type` | string | `global` | `global`, `location`, `program` |
| `_gym_announcement_target_location` | string | '' | Location slug when type=location |
| `_gym_announcement_target_program` | string | '' | Program slug when type=program |
| `_gym_announcement_start_date` | string | '' | Y-m-d start date |
| `_gym_announcement_end_date` | string | '' | Y-m-d end date |
| `_gym_announcement_pinned` | string | `no` | `yes` or `no` |

### Post Meta Keys (Social Posts)

| Key | Type | Purpose |
|-----|------|---------|
| `_gym_social_post` | bool | Flags post as AI-suggested social content |
| `_gym_suggested_by` | int/string | User ID or `gandalf` for AI agent |
| `_gym_approved_by` | int | User ID who approved publication |

### Post Meta Keys (Promotion Posts)

| Key | Type | Purpose |
|-----|------|---------|
| `_gym_core_promotion_user_id` | int | Promoted student's user ID |
| `_gym_core_promotion_program` | string | Program slug |
| `_gym_core_promotion_belt` | string | New belt slug |

### Order Meta

| Key | Storage | Purpose |
|-----|---------|---------|
| `_gym_location` | WC HPOS order meta | Location where order was placed |

---

## 4. Hook Reference

### Action Hooks (gym-core fires)

#### `gym_core_loaded`
- **Params**: None
- **When**: After all gym-core modules are registered in `Plugin::init()`
- **Use**: Register extensions, override behavior, or add late-binding modules

#### `gym_core_attendance_recorded`
- **Params**: `int $record_id`, `int $user_id`, `int $class_id`, `string $location`, `string $method`
- **When**: After a check-in is saved to the database
- **Use**: Trigger badge evaluation, streak updates, CRM sync, milestone tracking, AutomateWoo workflows

#### `gym_core_rank_changed`
- **Params**: `int $user_id`, `string $program`, `string $new_belt`, `int $new_stripes`, `string|null $from_belt`, `int $promoted_by`
- **When**: After a promotion (belt or stripe) is recorded
- **Use**: Send notifications, create promotion posts, award badges, sync CRM, AutomateWoo triggers

#### `gym_core_promotion_recommended`
- **Params**: `int $user_id`, `string $program`, `int $coach_id`
- **When**: When a coach recommends a member for promotion

#### `gym_core_foundations_enrolled`
- **Params**: `int $user_id`
- **When**: When a student is enrolled in Foundations

#### `gym_core_foundations_coach_roll`
- **Params**: `int $user_id`, `int $coach_id`, `array $rolls`
- **When**: When a supervised coach roll is recorded

#### `gym_core_foundations_cleared`
- **Params**: `int $user_id`, `int $coach_id`, `array $status`
- **When**: When a student is cleared from Foundations
- **Use**: Send notifications, sync CRM, AutomateWoo workflows

#### `gym_core_attendance_milestone`
- **Params**: `int $user_id`, `int $milestone`, `int $total_count`
- **When**: When a member reaches an attendance milestone (10, 25, 50, 100, etc.)
- **Use**: AutomateWoo workflows for milestone-based email/SMS

#### `gym_core_badge_earned`
- **Params**: `int $user_id`, `string $slug`, `array $badge_def`
- **When**: When a badge is awarded

#### `gym_core_sms_sent`
- **Params**: `string $to`, `string $body`, `string $sid`
- **When**: After a successful outbound SMS via Twilio

#### `gym_core_sms_received`
- **Params**: `string $from`, `string $body`, `string $to`, `string $sms_sid`
- **When**: When an inbound SMS arrives via Twilio webhook

#### `gym_core_sms_opt_out`
- **Params**: `string $phone`
- **When**: When a contact texts STOP/UNSUBSCRIBE/etc.

#### `gym_core_sms_opt_in`
- **Params**: `string $phone`
- **When**: When a contact texts START

#### `gym_core_streak_frozen`
- **Params**: `int $user_id`, `int $remaining`
- **When**: When a member freezes their streak

#### `gym_core_crm_pipeline_created`
- **Params**: `int $contact_id`, `string $stage`
- **When**: When a new CRM pipeline entry is created

#### `gym_core_crm_pipeline_updated`
- **Params**: `int $contact_id`, `string $stage`
- **When**: When a CRM contact's pipeline stage changes

#### `gym_core_crm_rep_assigned`
- **Params**: `int $contact_id`, `int $rep_id`, `string $location`
- **When**: When a sales rep is auto-assigned to a CRM contact

#### `gym_core_promotion_post_created`
- **Params**: `int $post_id`, `int $user_id`
- **When**: After a celebratory blog post is created for a belt promotion

#### `gym_core_social_post_published`
- **Params**: `int $post_id`, `int $approved_by`
- **When**: After a social post is approved and published (triggers Jetpack Publicize)

#### `gym_core_daily_maintenance`
- **When**: WP-Cron daily event scheduled on activation

### Action Hooks (hma-ai-chat fires)

#### `hma_ai_chat_agents_registered`
- **Params**: `AgentRegistry $registry`
- **When**: After default agents are registered

#### `hma_ai_chat_tool_queued`
- **Params**: `int $action_id`, `string $tool_name`, `array $params`, `int $user_id`, `string $agent_slug`
- **When**: When a write tool call is queued for approval

#### `hma_ai_chat_action_approved`
- **Params**: `int $action_id`, `int $user_id`
- **When**: When a pending action is approved

#### `hma_ai_chat_action_approved_with_changes`
- **Params**: `int $action_id`, `int $user_id`, `string $instructions`, `array $action_data`
- **When**: When an action is approved with staff-directed modifications

#### `hma_ai_chat_revised_action_completed`
- **Params**: `int $action_id`, `array $revised_data`
- **When**: After a revised action completes

#### `hma_ai_chat_action_rejected`
- **Params**: `int $action_id`, `int $user_id`, `string $reason`
- **When**: When a pending action is rejected

### Filter Hooks

| Filter | Params | Purpose |
|--------|--------|---------|
| `gym_core_checkin_validation` | `WP_Error $errors, int $user_id, int $class_id, string $location` | Add custom check-in validation rules |
| `gym_core_rank_definitions` | `array $definitions` | Modify rank hierarchies for all programs |
| `gym_core_programs` | `array $programs` | Add or modify program slug-label map |
| `gym_core_badge_definitions` | `array $badges` | Add or modify badge definitions |
| `gym_core_sms_templates` | `array $templates` | Add or modify SMS templates |
| `gym_core_attendance_milestone_thresholds` | `array $milestones` | Modify milestone class counts |
| `gym_core_attendance_settings` | `array $settings` | Add fields to the attendance settings section |
| `gym_core_promotion_post_data` | `array $post_data, int $user_id, string $program, string $new_belt` | Modify promotion blog post before insertion |
| `gym_core_crm_contact_data` | `array $contact_data, array $fields` | Modify CRM contact data before creation |
| `gym_core_crm_pipeline_stages` | `array $stages` | Modify available CRM pipeline stages |
| `gym_core_content_access` | `bool $has_access, int $user_id, string $rule_type` | Gate content access by membership |
| `gym_core_content_program` | `string $program, int $user_id` | Determine which program a content item belongs to |
| `gym_core_sms_template_variables` | `array $variables, Workflow, Customer` | Modify SMS template variables in AutomateWoo |
| `hma_ai_chat_persona_tools` | `array $tools, string $persona` | Filter tool list before sending to AI model |

---

## 5. Class Reference

### Gym_Core\Plugin
- **Namespace**: `Gym_Core`
- **Pattern**: Singleton (`Plugin::instance()`)
- **Constructor deps**: None (private)
- **Public methods**: `instance(): self`, `init(): void`
- **Key private methods**: `register_*_modules()`, `get_location_manager(): Location\Manager`

### Gym_Core\Activator
- **Static**: `activate(): void` -- Creates tables, seeds terms, schedules cron, sets defaults
- **Hooks registered**: via `register_activation_hook()`

### Gym_Core\Capabilities
- **Constants**: `ALL_CAPS`, `COACH_CAPS`, `HEAD_COACH_CAPS` -- string arrays of capability names
- **Roles created**: `gym_head_coach` (all gym caps + edit_users), `gym_coach` (subset)
- **Hooks**: `admin_init` -> `maybe_sync_caps()` (re-syncs on version change)

### Gym_Core\Data\TableManager
- **Static methods**: `maybe_create_tables()`, `create_tables()`, `drop_tables()`, `get_table_names(): array`

### Gym_Core\API\BaseController
- **Extends**: `WP_REST_Controller`
- **Constants**: `REST_NAMESPACE = 'gym/v1'`, `RATE_LIMIT_MAX = 60`, `RATE_LIMIT_WINDOW = 60`
- **Permission methods**: `permissions_public()`, `permissions_authenticated()`, `permissions_manage()`, `permissions_view_own_or_cap()`
- **Response methods**: `success_response()`, `error_response()`, `pagination_meta()`, `pagination_route_args()`
- **Rate limiting**: `check_rate_limit(string $key, int $max, int $window): bool`

### Gym_Core\Attendance\AttendanceStore
- **Constructor deps**: None
- **Methods**: `record_checkin()`, `has_checked_in_today()`, `get_user_history()`, `get_total_count()`, `get_count_since()`, `get_today_by_location()`, `get_today_by_class()`, `get_weekly_trend()`, `get_attended_weeks()`
- **Fires**: `gym_core_attendance_recorded` on check-in

### Gym_Core\Attendance\CheckInValidator
- **Constructor deps**: `AttendanceStore`
- **Method**: `validate(int $user_id, int $class_id, string $location): WP_Error|true`
- **Checks**: User exists, active membership, duplicate prevention, class existence/status, location validity
- **Filter**: `gym_core_checkin_validation`

### Gym_Core\Attendance\PromotionEligibility
- **Constructor deps**: `AttendanceStore`, `RankStore`, `?FoundationsClearance`
- **Methods**: `check(int $user_id, string $program): array`, `get_eligible_members(string $program): array`, `set_recommendation()`, `clear_recommendation()`
- **Fires**: `gym_core_promotion_recommended`

### Gym_Core\Attendance\FoundationsClearance
- **Constructor deps**: `AttendanceStore`
- **Constants**: `OPTION_PHASE1_CLASSES`, `OPTION_COACH_ROLLS`, `OPTION_TOTAL_CLASSES`, `OPTION_ENABLED`
- **Methods**: `is_enabled()`, `get_requirements()`, `get_status()`, `enroll()`, `record_coach_roll()`, `clear()`, `can_live_train()`
- **Fires**: `gym_core_foundations_enrolled`, `gym_core_foundations_coach_roll`, `gym_core_foundations_cleared`

### Gym_Core\Attendance\MilestoneTracker
- **Constructor deps**: `AttendanceStore`
- **Default milestones**: 10, 25, 50, 100, 150, 200, 250, 300, 500, 1000
- **Hooks**: `gym_core_attendance_recorded` (priority 20)
- **Fires**: `gym_core_attendance_milestone`

### Gym_Core\Rank\RankStore
- **Constructor deps**: None
- **Methods**: `get_rank()`, `get_all_ranks()`, `promote()`, `add_stripe()`, `get_history()`, `get_members_at_belt()`, `get_member_counts_by_program()`
- **Fires**: `gym_core_rank_changed` on promote/add_stripe

### Gym_Core\Rank\RankDefinitions
- **Static class** -- no constructor
- **Programs**: `adult-bjj` (5 belts, IBJJF), `kids-bjj` (13 belts), `kickboxing` (2 levels)
- **Methods**: `get_ranks()`, `get_programs()`, `get_belt_position()`, `get_next_belt()`, `get_promotion_threshold()`, `get_default_thresholds()`
- **Filter**: `gym_core_rank_definitions`, `gym_core_programs`

### Gym_Core\Gamification\BadgeEngine
- **Constructor deps**: `AttendanceStore`, `StreakTracker`
- **Hooks**: `gym_core_attendance_recorded` (priority 10), `gym_core_rank_changed`
- **Methods**: `award_badge()`, `has_badge()`, `get_user_badges()`, `evaluate_on_checkin()`, `evaluate_on_promotion()`
- **Fires**: `gym_core_badge_earned`

### Gym_Core\Gamification\StreakTracker
- **Constructor deps**: `AttendanceStore`
- **Methods**: `get_streak(int $user_id): array`, `freeze_streak(int $user_id): bool`
- **Streak logic**: Consecutive Monday-Sunday calendar weeks with at least 1 check-in
- **Fires**: `gym_core_streak_frozen`

### Gym_Core\Gamification\BadgeDefinitions
- **Static class**
- **Categories**: `attendance`, `rank`, `special`
- **Badges**: `first_class`, `classes_10`, `classes_25`, `classes_50`, `classes_100`, `classes_250`, `classes_500`, `streak_4`, `streak_12`, `streak_26`, `belt_promotion`, `early_bird`, `multi_program`

### Gym_Core\SMS\TwilioClient
- **Methods**: `send()`, `validate_webhook_signature()`, `is_rate_limited()`, `record_send()`, `sanitize_phone()` (static)

### Gym_Core\SMS\MessageTemplates
- **Static class**
- **Templates**: `lead_followup`, `class_reminder`, `schedule_change`, `payment_failed`, `belt_promotion`, `birthday`, `badge_earned`, `streak_reminder`, `streak_broken`, `reengage_30`, `reengage_60`, `reengage_90`
- **Methods**: `get_all()`, `get()`, `render()`, `get_slugs()`
- **Placeholders**: `{first_name}`, `{location}`, `{class_name}`, `{time}`, `{belt}`, `{program}`, `{badge_name}`, `{streak_count}`, `{site_url}`, `{change_type}`

### Gym_Core\Schedule\ClassPostType
- **CPT slug**: `gym_class`
- **Taxonomy**: `gym_program`
- **Supports**: title, editor, thumbnail, custom-fields
- **Taxonomies**: `gym_location`, `gym_program`

### Gym_Core\Schedule\ICalFeed
- **Endpoints**: `/gym-calendar.ics` (all), `/gym-calendar/{location}.ics` (per-location)
- **Timezone**: America/Chicago
- **Cache**: 1-hour transient, busted on class save/delete

### Gym_Core\Briefing\BriefingGenerator
- **Constructor deps**: `AttendanceStore`, `RankStore`, `FoundationsClearance`, `PromotionEligibility`
- **Alert priorities** (1=highest): Foundations coach rolls, First-timers, Long absence, Medical flags, Promotion candidates

### Gym_Core\Social\PromotionPost
- **Hooks**: `gym_core_rank_changed` (priority 20)
- **Behavior**: Creates published blog post in "Promotions" category on belt changes (not stripes). Jetpack Publicize auto-shares.

### Gym_Core\Social\SocialPostManager
- **Extends**: `BaseController`
- **Pattern**: AI drafts pending posts -> coach reviews -> approves -> Jetpack Publicize shares

### Gym_Core\Member\ContentGating
- **Plans**: `adult-bjj-member`, `kids-bjj-member`, `kickboxing-member`, `all-access-member`
- **Products**: `adult-bjj-membership`, `kids-bjj-membership`, `kickboxing-membership`, `all-access-membership`
- **Methods**: `has_active_membership(int $user_id, string $program): bool` (static)

### Gym_Core\Location\Taxonomy
- **Slug**: `gym_location`
- **Valid locations**: `rockford`, `beloit`
- **Methods**: `is_valid()`, `get_location_labels()`, `seed_terms()`

### Gym_Core\Location\Manager
- **Cookie**: `gym_location` (1 year, HttpOnly, SameSite=Lax)
- **User meta**: `gym_location`
- **Resolution priority**: User meta -> Cookie -> empty string
- **AJAX action**: `gym_set_location`

### Gym_Core\Integrations\FormToCrm
- **Hooks into**: Jetpack Forms (`jetpack_contact_form_process_data`), WooCommerce new customer, order completed
- **CRM actions**: Create contact, create pipeline entry, assign sales rep by location, swap tags on first purchase

### Gym_Core\Integrations\CrmContactSync
- **Hooks into**: `gym_core_rank_changed`, `gym_core_foundations_cleared`, `gym_core_attendance_recorded`
- **CRM fields synced**: `belt_rank`, `foundations_status`, `last_checkin`, `total_classes`

### Gym_Core\Integrations\AutomateWooTriggers
- **Triggers**: `GymBeltPromotion`, `GymFoundationsCleared`, `GymAttendanceRecorded`, `GymAttendanceMilestone`

### Gym_Core\Integrations\AutomateWooSmsAction
- **Action**: `GymSendSms` -- Sends SMS via TwilioClient from AutomateWoo workflows

### Gym_Core\Integrations\CrmSmsBridge
- **Hooks into**: `gym_core_sms_sent`, `gym_core_sms_received`
- **Behavior**: Logs SMS activity on matched CRM contacts, provides `send_to_contact()` method

---

## 6. Tool Registry (Gandalf AI)

All tools are defined in `HMA_AI_Chat\Tools\ToolRegistry`. Write tools are queued as PendingAction records; read tools execute immediately via internal `rest_do_request()`.

### Sales Persona Tools

| Tool | Endpoint | Method | Write | Auth Cap |
|------|----------|--------|-------|----------|
| `get_pricing` | `/locations/{location}/products` | GET | No | `edit_posts` |
| `calculate_pricing` | `/sales/calculate` | POST | No | `gym_process_sale` |
| `lookup_customer` | `/sales/customer` | GET | No | `gym_process_sale` |
| `create_lead` | `/sales/lead` | POST | Yes | `gym_process_sale` |
| `get_schedule` | `/schedule` | GET | No | `edit_posts` |
| `get_locations` | `/locations` | GET | No | `edit_posts` |
| `draft_sms` | `/sms/send` | POST | Yes | `gym_send_sms` |
| `get_trial_info` | `/sms/templates` | GET | No | `edit_posts` |

### Coaching Persona Tools

| Tool | Endpoint | Method | Write | Auth Cap |
|------|----------|--------|-------|----------|
| `get_member_rank` | `/members/{user_id}/rank` | GET | No | `gym_view_ranks` |
| `get_rank_history` | `/members/{user_id}/rank-history` | GET | No | `gym_view_ranks` |
| `get_attendance` | `/attendance/{user_id}` | GET | No | `gym_view_attendance` |
| `get_badges` | `/members/{user_id}/badges` | GET | No | `gym_view_achievements` |
| `get_streak` | `/members/{user_id}/streak` | GET | No | `gym_view_achievements` |
| `get_schedule` | `/schedule` | GET | No | `edit_posts` |
| `recommend_promotion` | `/promotions/recommend` | POST | Yes | `gym_promote_student` |
| `promote_member` | `/ranks/promote` | POST | Yes | `gym_promote_student` |
| `get_briefing` | `/briefings/class/{class_id}` | GET | No | `gym_view_briefing` |
| `get_foundations_status` | `/foundations/{user_id}` | GET | No | `gym_view_ranks` |
| `record_coach_roll` | `/foundations/coach-roll` | POST | Yes | `gym_promote_student` |

### Finance Persona Tools

| Tool | Endpoint | Method | Write | Auth Cap |
|------|----------|--------|-------|----------|
| `get_revenue_summary` | `/wc/v3/reports/revenue/stats` | GET | No | `manage_woocommerce` |
| `get_subscriptions` | `/wc/v3/subscriptions` | GET | No | `manage_woocommerce` |
| `get_failed_payments` | `/wc/v3/orders` | GET | No | `manage_woocommerce` |
| `get_reports` | `/wc/v3/reports` | GET | No | `manage_woocommerce` |

### Admin Persona Tools

| Tool | Endpoint | Method | Write | Auth Cap |
|------|----------|--------|-------|----------|
| `get_today_attendance` | `/attendance/today` | GET | No | `gym_view_attendance` |
| `get_schedule` | `/schedule` | GET | No | `edit_posts` |
| `get_classes` | `/classes` | GET | No | `edit_posts` |
| `get_promotion_eligible` | `/promotions/eligible` | GET | No | `manage_woocommerce` |
| `promote_member` | `/ranks/promote` | POST | Yes | `gym_promote_student` |
| `calculate_pricing` | `/sales/calculate` | POST | No | `gym_process_sale` |
| `lookup_customer` | `/sales/customer` | GET | No | `gym_process_sale` |
| `create_lead` | `/sales/lead` | POST | Yes | `gym_process_sale` |
| `draft_announcement` | `/announcements` | POST | Yes | `gym_manage_announcements` |
| `draft_social_post` | `/social/draft` | POST | Yes | `gym_manage_announcements` |
| `get_briefing_today` | `/briefings/today` | GET | No | `gym_view_briefing` |

### Tool Execution Flow

1. AI model returns tool call with `name` + `parameters`
2. `ToolExecutor::execute()` validates tool exists, persona access, user capability
3. **Read tools**: `rest_do_request()` dispatches internally (zero HTTP overhead), returns data immediately
4. **Write tools**: `PendingActionStore::store_pending_action()` queues for staff approval, returns `{ pending: true, action_id }`
5. Staff approves/rejects via `ActionEndpoint` REST routes
6. On approval, `ToolExecutor::execute_read()` dispatches the actual REST request

---

## 7. Settings Map

All settings are under WooCommerce > Settings > Gym Core, organized by section.

### General Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_gamification_enabled` | checkbox | `yes` | Enable/disable badges, streaks, achievements |
| `gym_core_sms_enabled` | checkbox | `no` | Enable/disable Twilio SMS integration |
| `gym_core_api_enabled` | checkbox | `no` | Enable/disable gym/v1 REST API |

### Locations Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_require_location` | checkbox | `yes` | Show location selector banner |
| `gym_core_filter_products_by_location` | checkbox | `yes` | Filter products by selected location |

### Schedule Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_default_class_capacity` | number | `30` | Default max students per class |
| `gym_core_waitlist_enabled` | checkbox | `yes` | Allow waitlist when class is full |
| `gym_core_ical_enabled` | checkbox | `yes` | Enable iCal calendar feed |

### Ranks Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_require_coach_recommendation` | checkbox | `yes` | Require coach recommendation before promotion |
| `gym_core_notify_on_promotion` | checkbox | `yes` | Send email/SMS on promotion |
| `gym_core_auto_promotion_posts` | checkbox | `yes` | Auto-create blog posts for belt promotions |
| `gym_core_foundations_enabled` | checkbox | `yes` | Enable Foundations safety gate |
| `gym_core_foundations_phase1_classes` | number | `10` | Classes before coach rolls |
| `gym_core_foundations_coach_rolls_required` | number | `2` | Required supervised rolls |
| `gym_core_foundations_total_classes` | number | `25` | Total classes to clear Foundations |
| `gym_core_rank_thresholds` | array (stored) | See RankDefinitions | Per-rank promotion thresholds |
| `gym_core_threshold_adult_bjj_*` | number | Various | Per-belt threshold overrides |

### Attendance Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_checkin_methods` | multiselect | `[qr, search, manual]` | Allowed kiosk check-in methods |
| `gym_core_kiosk_timeout` | number | `10` | Kiosk auto-reset seconds (5-60) |
| `gym_core_prevent_duplicate_checkin` | checkbox | `yes` | Block same-class double check-in |
| `gym_core_attendance_milestones` | text | '' (uses defaults) | Comma-separated milestone counts |

### Gamification Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_streak_freezes_per_quarter` | number | `1` | Max streak freezes per quarter (0-4) |
| `gym_core_notify_on_badge` | checkbox | `yes` | Send SMS/email on badge earned |
| `gym_core_targeted_content_enabled` | checkbox | `yes` | Enable personalized content shortcodes |

### SMS Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_twilio_account_sid` | text | '' | Twilio Account SID |
| `gym_core_twilio_auth_token` | password | '' | Twilio Auth Token |
| `gym_core_twilio_phone_number` | text | '' | Twilio From number (E.164) |
| `gym_core_sms_rate_limit` | number | `1` | Max SMS per contact per hour |

### CRM Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_crm_enabled` | checkbox | `no` | Enable form-to-CRM integration |
| `gym_core_crm_rockford_rep` | select | '' | Auto-assign Rockford leads to this user |
| `gym_core_crm_beloit_rep` | select | '' | Auto-assign Beloit leads to this user |
| `gym_core_crm_default_pipeline_stage` | select | `New Lead` | Default pipeline stage for new leads |

### API Section

| Option Key | Type | Default | Controls |
|------------|------|---------|----------|
| `gym_core_api_require_auth` | checkbox | `yes` | Require auth for all API requests |

### System Options (not in settings UI)

| Option Key | Type | Purpose |
|------------|------|---------|
| `gym_core_version` | string | Installed plugin version |
| `gym_core_activated` | string | Activation timestamp |
| `gym_core_db_version` | int | Schema migration version |
| `gym_core_caps_version` | string | Capability sync version |
| `gym_core_settings` | array | Legacy settings blob from first activation |
| `gym_core_briefing_enabled` | checkbox (yes) | Enable briefing system |
| `gym_core_briefing_forecast_weeks` | int (4) | Weeks to look back for roster forecasting |
| `gym_core_briefing_absence_threshold` | int (14) | Days absent to trigger alert |
| `gym_core_membership_plans_version` | string | Content gating plan creation version |

---

## 8. Integration Points

### Jetpack CRM

**Integration classes**: `FormToCrm`, `CrmContactSync`, `CrmSmsBridge`

- **Contact creation**: Jetpack Forms submissions and WooCommerce new customers create CRM contacts via `zeroBS_integrations_addOrUpdateContact()`
- **Pipeline management**: New leads get a pipeline entry (quote object). First completed order transitions to "Closed Won" and swaps `lead` tag for `member`
- **Sales rep assignment**: Auto-assigns Rockford/Beloit leads to configured rep user IDs via `zeroBS_setOwner()`
- **Field sync**: On every attendance record, rank change, and Foundations clearance, CRM custom fields are updated: `belt_rank`, `foundations_status`, `last_checkin`, `total_classes`
- **SMS logging**: All outbound/inbound SMS are logged as CRM activities on matched contacts (phone number lookup via `zbsc_hometel`/`zbsc_worktel`/`zbsc_mobtel`)
- **Detection**: `class_exists('ZeroBSCRM')` or `function_exists('zeroBS_getContactByEmail')`

### AutomateWoo

**Integration classes**: `AutomateWooTriggers`, `AutomateWooSmsAction`

- **Custom triggers registered** (all in the "Gym" group):
  - `Gym -- Belt Promotion` (fires on `gym_core_rank_changed`)
  - `Gym -- Foundations Cleared` (fires on `gym_core_foundations_cleared`)
  - `Gym -- Class Check-In` (fires on `gym_core_attendance_recorded`)
  - `Gym -- Attendance Milestone` (fires on `gym_core_attendance_milestone`)
- **Custom action**: `Send SMS (Twilio)` -- sends via TwilioClient using templates or custom message with AutomateWoo variable processing
- **Detection**: `class_exists('\AutomateWoo\Trigger')` and `class_exists('\AutomateWoo\Action')`

### WooCommerce

- **HPOS compatibility**: Declared via `FeaturesUtil::declare_compatibility('custom_order_tables')`
- **Blocks compatibility**: Declared via `FeaturesUtil::declare_compatibility('cart_checkout_blocks')`
- **Order location**: Saved on `woocommerce_checkout_order_created` and `woocommerce_store_api_checkout_order_processed` using HPOS CRUD (`$order->update_meta_data()`)
- **Product filtering**: `woocommerce_product_query` and `woocommerce_shortcode_products_query` filtered by active location
- **Settings tab**: Custom "Gym Core" tab registered via `woocommerce_settings_tabs_array`
- **Subscriptions**: `wcs_get_users_subscriptions()` for membership status, billing info, and member dashboard
- **Memberships**: `wc_memberships_is_user_active_member()` for content gating and check-in validation
- **Store API**: Location data injected into Cart and Checkout endpoints via `woocommerce_store_api_register_endpoint_data`
- **Block checkout**: `IntegrationInterface` implementation provides location data to block scripts

### Twilio

- **Outbound**: Direct REST API via `wp_remote_post()` to `https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json`. No SDK dependency.
- **Inbound**: Webhook at `POST /gym/v1/sms/webhook`. Validates via HMAC-SHA1 signature (`X-Twilio-Signature` header). Returns TwiML XML.
- **TCPA compliance**: Handles STOP/UNSUBSCRIBE/CANCEL/QUIT/END keywords for opt-out, START for opt-in. Fires `gym_core_sms_opt_out` and `gym_core_sms_opt_in` actions.
- **Rate limiting**: Per-contact transient-based rate limiting. Configurable max per hour via `gym_core_sms_rate_limit` option.

### Jetpack Publicize (Social Sharing)

- **Automatic**: `PromotionPost` creates published blog posts on belt promotions. Jetpack Publicize auto-shares published posts to connected accounts.
- **Manual via AI**: `SocialPostManager` creates `pending` posts. Coach approves via REST API, status transitions to `publish`, Publicize shares.

### MailPoet

- No direct integration. MailPoet form integration is planned but not yet implemented in source code.

### WP-CLI

- **Command group**: `wp gym`
- **Subcommands**: `wp gym import belt-ranks --file=`, `wp gym import attendance --file=`, `wp gym import achievements --file=`, `wp gym import users --file=`
- **Flags**: `--dry-run`, `--batch-size`, `--skip-existing`
- **Registration**: `Gym_Core\CLI\ImportCommand::register()` called from `plugins_loaded` when in CLI mode

### Custom Post Types

| CPT | Slug | Public | REST | Menu Icon |
|-----|------|--------|------|-----------|
| Classes | `gym_class` | Yes | Yes (`/classes`) | `dashicons-calendar-alt` |
| Announcements | `gym_announcement` | No | Yes (`/announcements`) | `dashicons-megaphone` |

### Custom Taxonomies

| Taxonomy | Slug | Attached to | Purpose |
|----------|------|-------------|---------|
| Locations | `gym_location` | `product` | Multi-location product filtering |
| Programs | `gym_program` | `gym_class` | Class categorization by martial arts program |

### Custom Roles

| Role | Slug | Capabilities |
|------|------|-------------|
| Head Coach | `gym_head_coach` | All gym caps + `edit_users` + `read` |
| Coach | `gym_coach` | `gym_promote_student`, `gym_view_ranks`, `gym_check_in_member`, `gym_view_attendance`, `gym_view_briefing`, `read` |

### Kiosk System

- **URL**: `/check-in/` (rewrite rule -> `gym_kiosk=1` query var)
- **Auth**: Requires logged-in staff member (redirects to wp-login otherwise)
- **Template**: Standalone full-screen HTML (no theme header/footer), optimized for tablets
- **Assets**: `assets/css/kiosk.css`, `assets/js/kiosk.js`
- **JS config**: `gymKiosk` object with `restUrl`, `nonce`, `location`, `timeout`, `strings`
- **Flow**: Name search -> Select class -> POST to `/gym/v1/check-in` -> Success/error screen -> Auto-reset after timeout

### iCal Feed

- **All locations**: `/gym-calendar.ics`
- **Per-location**: `/gym-calendar/rockford.ics`, `/gym-calendar/beloit.ics`
- **Timezone**: America/Chicago
- **Addresses**: Rockford: `4911 26th Avenue, Rockford, IL 61109`, Beloit: `610 4th St, Beloit, WI 53511`
- **Cache**: 1-hour transient, auto-flushed on class save/delete

### WP-Cron Events

| Hook | Schedule | Purpose |
|------|----------|---------|
| `gym_core_daily_maintenance` | Daily | General maintenance (scheduled on activation) |
| `hma_ai_chat_purge_conversations` | Daily | Purge conversations older than 30 days |
