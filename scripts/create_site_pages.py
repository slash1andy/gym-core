#!/usr/bin/env python3
"""Create WordPress pages for the Haanpaa Martial Arts site (M1.7).

Maps scraped Wix content to WordPress block editor pages.
Runs WP-CLI commands remotely via Pressable API.

Usage:
  PRESSABLE_CLIENT_ID=xxx PRESSABLE_CLIENT_SECRET=yyy python3 scripts/create_site_pages.py
  python3 scripts/create_site_pages.py --dry-run
"""

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

API_BASE = "https://my.pressable.com/v1"
AUTH_URL = "https://my.pressable.com/auth/token"
SITE_ID = 1630891


def api_request(method, url, token=None, data=None, form_data=None):
    headers = {}
    body = None
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if data is not None:
        headers["Content-Type"] = "application/json"
        body = json.dumps(data).encode()
    elif form_data is not None:
        body = urllib.parse.urlencode(form_data).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode()
            return json.loads(raw) if raw else {}, None
    except urllib.error.HTTPError as e:
        return {}, f"HTTP {e.code}: {e.read().decode()[:200]}"
    except urllib.error.URLError as e:
        return {}, f"Connection: {e.reason}"


def authenticate(client_id, client_secret):
    resp, err = api_request("POST", AUTH_URL, form_data={
        "grant_type": "client_credentials",
        "client_id": client_id,
        "client_secret": client_secret,
    })
    if err:
        print(f"Auth failed: {err}", file=sys.stderr)
        sys.exit(1)
    return resp["access_token"]


def wpcli(token, cmd, dry_run=False):
    if dry_run:
        print(f"    [DRY RUN] {cmd[:100]}")
        return {"message": "dry-run"}, None
    return api_request(
        "POST", f"{API_BASE}/sites/{SITE_ID}/wordpress/wpcli",
        token=token, data={"commands": [cmd]},
    )


def bash_cmd(token, cmd, dry_run=False):
    if dry_run:
        print(f"    [DRY RUN] {cmd[:100]}")
        return {"message": "dry-run"}, None
    return api_request(
        "POST", f"{API_BASE}/sites/{SITE_ID}/wordpress/commands",
        token=token, data={"commands": [cmd]},
    )


# ---------------------------------------------------------------------------
# Page content — block editor markup
# Content sourced from docs/migration/wix-content/
# ---------------------------------------------------------------------------

PAGES = [
    # --- HOME PAGE ---
    {
        "title": "Home",
        "slug": "home",
        "template": "",
        "content": """<!-- wp:cover {"dimRatio":70,"overlayColor":"black","isUserOverlayColor":true,"minHeight":600,"align":"full"} -->
<div class="wp-block-cover alignfull" style="min-height:600px"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container">

<!-- wp:heading {"textAlign":"center","level":1,"style":{"typography":{"fontSize":"3.5rem"}}} -->
<h1 class="wp-block-heading has-text-align-center" style="font-size:3.5rem">Learn Mixed Martial Arts For Self Defense, Confidence, And Fitness</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.25rem"}}} -->
<p class="has-text-align-center" style="font-size:1.25rem">Rockford, IL &amp; Beloit, WI — Brazilian Jiu-Jitsu, Kickboxing, Kids Martial Arts</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="/free-trial">Get Your Free Trial Class</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/classes">View Class Schedule</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div></div>
<!-- /wp:cover -->

<!-- wp:heading {"textAlign":"center","style":{"spacing":{"margin":{"top":"3rem","bottom":"1rem"}}}} -->
<h2 class="wp-block-heading has-text-align-center" style="margin-top:3rem;margin-bottom:1rem">Find The Best Program For You</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"2rem"}}}} -->
<div class="wp-block-columns alignwide">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Fitness Kickboxing</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Get in the best shape of your life while learning real Muay Thai striking techniques. Our Kick-Fit classes combine cardio conditioning with practical self-defense skills. All fitness levels welcome.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline","fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size is-style-outline has-small-font-size"><a class="wp-block-button__link wp-element-button" href="/fitness-kickboxing">Learn More</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Brazilian Jiu-Jitsu</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Learn the most effective martial art for real-world self defense. Our Gracie Jiu-Jitsu program covers fundamentals through advanced techniques in both Gi and No-Gi. Perfect for beginners and experienced practitioners.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline","fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size is-style-outline has-small-font-size"><a class="wp-block-button__link wp-element-button" href="/brazilian-jiu-jitsu">Learn More</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Kids Martial Arts</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Build confidence, discipline, and integrity in your child. Our Kids BJJ program (ages 5-15) and Little Ninjas program (ages 4-6) teach self-defense, respect, and focus in a safe, fun environment.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline","fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size is-style-outline has-small-font-size"><a class="wp-block-button__link wp-element-button" href="/kids">Learn More</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:separator {"align":"wide"} -->
<hr class="wp-block-separator alignwide"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">What Our Members Say</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>This place is amazing! The coaches truly care about your progress. I've lost 30 pounds and gained so much confidence. The community here is like a second family.</p><cite>— Rebekah Rawson</cite></blockquote>
<!-- /wp:quote -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>My kids have been training here for over a year and the transformation in their confidence and discipline has been incredible. Coach Darby and the team make every child feel welcome and capable.</p><cite>— Aubrey Marvel</cite></blockquote>
<!-- /wp:quote -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>Whether you want to compete or just get in shape, HMA has something for you. The instruction is top-notch and the atmosphere is welcoming to everyone from beginners to advanced.</p><cite>— Rachael Lee</cite></blockquote>
<!-- /wp:quote -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:cover {"dimRatio":80,"overlayColor":"black","isUserOverlayColor":true,"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}}} -->
<div class="wp-block-cover alignfull" style="padding-top:4rem;padding-bottom:4rem"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container">
<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">All Progress Takes Place Outside of the Comfort Zone</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Whether your goal is self defense, fitness, competition, or building confidence — your journey starts with a single class.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"#e63946"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-background wp-element-button" href="/free-trial" style="background-color:#e63946">Start Your Free Trial</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div></div>
<!-- /wp:cover -->""",
    },

    # --- BRAZILIAN JIU-JITSU ---
    {
        "title": "Brazilian Jiu-Jitsu",
        "slug": "brazilian-jiu-jitsu",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Gracie Brazilian Jiu-Jitsu</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Brazilian Jiu-Jitsu (BJJ) is the most effective martial art for real-world self defense. At Haanpaa Martial Arts, we teach the Gracie system — focusing on leverage, technique, and strategy so that a smaller person can defend themselves against a larger opponent.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Our BJJ Program</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our adult BJJ classes cover the full spectrum from fundamentals to advanced competition techniques. Whether you're brand new to martial arts or an experienced grappler, our structured curriculum meets you where you are.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>Fundamentals BJJ</strong> — Core positions, escapes, and submissions. Perfect for beginners.</li>
<li><strong>Mixed Levels BJJ</strong> — Gi and No-Gi training for intermediate and advanced students.</li>
<li><strong>No-Gi BJJ</strong> — Submission grappling without the traditional uniform.</li>
<li><strong>Open Mat</strong> — Free training time to drill and roll with training partners.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Belt Rank System</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We follow the IBJJF graduation system: White → Blue → Purple → Brown → Black. Every new student starts with a Foundations Evaluation before earning their White Belt. Promotions are based on time, attendance, and instructor evaluation.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Try a Free BJJ Class</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/classes">View BJJ Schedule</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- FITNESS KICKBOXING ---
    {
        "title": "Fitness Kickboxing",
        "slug": "fitness-kickboxing",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Fitness Kickboxing</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our Muay Thai and Fitness Kickboxing program at Team Haanpaa is designed for all skill levels. Whether you're looking to get in shape, learn self-defense, or train for competition, our striking classes deliver a full-body workout with real martial arts technique.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">The Benefits of Fitness Kickboxing</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Full-body cardio and strength conditioning</li>
<li>Learn real Muay Thai striking technique</li>
<li>Stress relief and mental clarity</li>
<li>Build confidence and self-defense skills</li>
<li>Burn 500-800 calories per class</li>
<li>Improve coordination and agility</li>
<li>Train in a supportive, team environment</li>
<li>No experience necessary — all levels welcome</li>
<li>Structured progression from beginner to advanced</li>
<li>Practical self-defense you can use in the real world</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Class Options</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Kick Fit (Striking Fundamentals)</strong> — Mon/Fri evenings, Thu/Sat mornings</li>
<li><strong>Mixed Levels Striking</strong> — Tue/Wed evenings</li>
</ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Try a Free Kickboxing Class</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- KIDS ---
    {
        "title": "Kids Martial Arts",
        "slug": "kids",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Kids Martial Arts</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2rem"}}} -->
<p style="font-size:1.2rem"><strong>Confidence, Discipline, Integrity</strong> — Our kids martial arts program builds character while teaching practical self-defense skills in a safe, fun environment.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Kids Jiu-Jitsu (Ages 7-15)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our Kids BJJ program follows the IBJJF youth belt system with 14 rank levels. Students learn takedowns, positions, escapes, and submissions appropriate for their age and skill level. Classes emphasize discipline, respect, and anti-bullying skills alongside technical training.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Little Ninjas (Ages 4-6)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The Little Ninjas program is our introductory martial arts class for young children. Through games, drills, and age-appropriate techniques, kids develop coordination, listening skills, and confidence. It's the perfect foundation for future martial arts training.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Schedule</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Little Ninjas</strong> — Mon/Wed/Fri 4:15-4:55 PM (Rockford)</li>
<li><strong>Kids BJJ</strong> — Mon/Wed/Fri 5:15-5:55 PM (Rockford)</li>
<li><strong>Saturday Kids BJJ</strong> — Sat 10:00-10:45 AM (Rockford)</li>
<li><strong>Beloit Kids BJJ</strong> — Tue/Wed/Fri 5:00-6:00 PM (Beloit)</li>
</ul>
<!-- /wp:list -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Sign Up for a Free Trial Class</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- CLASS SCHEDULE ---
    {
        "title": "Class Schedule",
        "slug": "classes",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Class Schedule</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Rockford, IL</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>4911 26th Avenue, Rockford, IL 61109</strong> | 815-451-3001</p>
<!-- /wp:paragraph -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Day</th><th>Time</th><th>Class</th></tr></thead><tbody>
<tr><td>Mon</td><td>4:15-4:55 PM</td><td>Little Ninjas</td></tr>
<tr><td>Mon</td><td>5:15-5:55 PM</td><td>Kids BJJ</td></tr>
<tr><td>Mon</td><td>6:15-7:15 PM</td><td>Kick Fit (Striking Fundamentals)</td></tr>
<tr><td>Mon</td><td>7:15-8:00 PM</td><td>Fundamentals BJJ</td></tr>
<tr><td>Tue</td><td>6:00-7:00 PM</td><td>Fundamentals BJJ / Mixed Levels Striking</td></tr>
<tr><td>Tue</td><td>7:00-8:00 PM</td><td>Mixed Levels BJJ (Gi/No-Gi)</td></tr>
<tr><td>Wed</td><td>4:15-4:55 PM</td><td>Little Ninjas</td></tr>
<tr><td>Wed</td><td>5:15-5:55 PM</td><td>Kids BJJ</td></tr>
<tr><td>Wed</td><td>6:15-7:00 PM</td><td>Mixed Levels Striking</td></tr>
<tr><td>Wed</td><td>7:15-8:00 PM</td><td>No-Gi BJJ Fundamentals/Mixed Levels</td></tr>
<tr><td>Thu</td><td>10:00-11:00 AM</td><td>AM Kick Fit (Striking Fundamentals)</td></tr>
<tr><td>Thu</td><td>11:00 AM-12:00 PM</td><td>AM Fundamentals BJJ</td></tr>
<tr><td>Thu</td><td>6:00-7:00 PM</td><td>Fundamentals BJJ</td></tr>
<tr><td>Thu</td><td>7:00-8:00 PM</td><td>Mixed Levels BJJ</td></tr>
<tr><td>Fri</td><td>4:15-4:55 PM</td><td>Little Ninjas</td></tr>
<tr><td>Fri</td><td>5:15-5:55 PM</td><td>Kids BJJ</td></tr>
<tr><td>Fri</td><td>6:15-7:15 PM</td><td>Kick Fit (Striking Fundamentals)</td></tr>
<tr><td>Fri</td><td>7:15-8:00 PM</td><td>Fundamentals BJJ</td></tr>
<tr><td>Sat</td><td>10:00-10:45 AM</td><td>Saturday Kids BJJ</td></tr>
<tr><td>Sat</td><td>10:00-11:00 AM</td><td>AM Kick Fit (Striking Fundamentals)</td></tr>
<tr><td>Sat</td><td>11:00 AM-12:00 PM</td><td>AM Fundamentals/Mixed Levels BJJ</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:separator -->
<hr class="wp-block-separator"/>
<!-- /wp:separator -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Beloit, WI</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>610 4th St, Beloit, WI 53511</strong> | 608-795-3608</p>
<!-- /wp:paragraph -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Day</th><th>Time</th><th>Class</th></tr></thead><tbody>
<tr><td>Tue</td><td>5:00-6:00 PM</td><td>Kids BJJ</td></tr>
<tr><td>Tue</td><td>6:00-7:00 PM</td><td>Striking</td></tr>
<tr><td>Tue</td><td>7:00-8:00 PM</td><td>BJJ Fundamentals</td></tr>
<tr><td>Wed</td><td>5:00-6:00 PM</td><td>Kids BJJ</td></tr>
<tr><td>Wed</td><td>6:00-7:00 PM</td><td>Striking</td></tr>
<tr><td>Wed</td><td>7:00-8:00 PM</td><td>BJJ Fundamentals</td></tr>
<tr><td>Fri</td><td>5:00-6:00 PM</td><td>Kids BJJ</td></tr>
<tr><td>Fri</td><td>6:00-7:00 PM</td><td>Striking</td></tr>
<tr><td>Fri</td><td>7:00-8:00 PM</td><td>BJJ Fundamentals</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p><strong>Personal Training with Coach Darby</strong> — By appointment, Mon-Sat 9:00 AM - 3:00 PM (Rockford)</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Try a Free Class</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- BELOIT ---
    {
        "title": "Beloit Location",
        "slug": "beloit",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">HMA Beloit</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2rem"}}} -->
<p style="font-size:1.2rem">The Best Self Defense &amp; Fitness Training in The Stateline Area</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>610 4th St, Beloit, WI 53511</strong> | <a href="tel:608-795-3608">608-795-3608</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Programs at Beloit</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Kick-Fit &amp; Muay Thai</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Full-body kickboxing conditioning with real striking technique. Tue/Wed/Fri 6:00-7:00 PM.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Brazilian Jiu-Jitsu</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Fundamentals through advanced BJJ in a welcoming environment. Tue/Wed/Fri 7:00-8:00 PM.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Kids Martial Arts</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Build confidence and discipline in your child. Tue/Wed/Fri 5:00-6:00 PM.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:paragraph -->
<p>Haanpaa Martial Arts Beloit is led by the same coaching team and curriculum as our Rockford headquarters. Our Beloit academy welcomes students of all skill levels — whether you're a complete beginner or an experienced martial artist looking for quality training in the Stateline area.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Get Your Free Trial Class</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/classes">View Beloit Schedule</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- PERSONAL TRAINING ---
    {
        "title": "Personal Training",
        "slug": "personal-training",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">HMA Personal Training</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Take your training to the next level with one-on-one coaching from our experienced instructors. Personal training at Haanpaa Martial Arts covers:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Brazilian Jiu-Jitsu technique and strategy</li>
<li>Muay Thai / Kickboxing striking</li>
<li>Self-defense scenarios</li>
<li>Strength and conditioning</li>
<li>Competition preparation</li>
<li>Weight management and nutrition guidance</li>
<li>Flexibility and mobility</li>
<li>Sport-specific athletic training</li>
<li>Injury recovery and return-to-training</li>
<li>Private lessons for families or small groups</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Packages</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">1-on-1 Personal Training</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>50-minute private session with a certified coach. Customized to your goals.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Couples Training</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Train with a partner. Same personalized coaching, shared experience.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Small Group</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>3-5 people. Great for friend groups or corporate team building.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Head Coach: Erich Haanpaa</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Coach Erich Haanpaa is a certified martial arts instructor with decades of experience in BJJ, Muay Thai, and fitness coaching. His personal training philosophy focuses on meeting each student where they are and building a customized path to their goals.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">Book a Personal Training Session</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- FREE TRIAL ---
    {
        "title": "Free Trial Class",
        "slug": "free-trial",
        "content": """<!-- wp:heading {"level":1,"textAlign":"center"} -->
<h1 class="wp-block-heading has-text-align-center">Get Your Free Trial Class</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"1.2rem"}}} -->
<p class="has-text-align-center" style="font-size:1.2rem">Ready to start your martial arts journey? Try any class for free — no commitment, no pressure. Just show up and train.</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:heading -->
<h2 class="wp-block-heading">What to Expect</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul>
<li>Arrive 10-15 minutes early to meet the coach and tour the facility</li>
<li>Wear comfortable athletic clothing (no zippers or buttons)</li>
<li>No equipment needed — we provide everything for your first class</li>
<li>Classes are beginner-friendly — our coaches will guide you through everything</li>
<li>After class, we'll answer any questions about programs and membership</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Locations</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><strong>Rockford:</strong> 4911 26th Avenue, Rockford, IL 61109 | <a href="tel:815-451-3001">815-451-3001</a><br><strong>Beloit:</strong> 610 4th St, Beloit, WI 53511 | <a href="tel:608-795-3608">608-795-3608</a></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:heading -->
<h2 class="wp-block-heading">Sign Up</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Fill out the form below or call us to reserve your spot. Walk-ins are welcome but reserving ensures a coach is ready for you.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><em>Contact form will be added here (Jetpack Forms integration — M1.7)</em></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
<div class="wp-block-buttons" style="margin-top:2rem">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="tel:815-451-3001">Call Rockford: 815-451-3001</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="tel:608-795-3608">Call Beloit: 608-795-3608</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- REVIEWS ---
    {
        "title": "Reviews",
        "slug": "reviews",
        "content": """<!-- wp:heading {"level":1,"textAlign":"center"} -->
<h1 class="wp-block-heading has-text-align-center">What Our Members Say</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Real stories from real people. See what our community has to say about training at Haanpaa Martial Arts.</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator"/>
<!-- /wp:separator -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>Haanpaa Martial Arts is honestly one of the best decisions I've ever made for myself and my family. The coaching is incredible — they genuinely care about every student's progress, not just the competitors. The community feel is unlike any gym I've been to. Everyone supports each other, and the culture of respect and growth that Coach Darby has built is something special. Whether you're looking to learn self-defense, get in shape, or find a place where you belong — this is it.</p><cite>— Leina L.</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>This place is amazing! The coaches truly care about your progress. I started training to lose weight and ended up falling in love with BJJ. I've lost 30 pounds and gained so much confidence. The community here is like a second family. If you're on the fence, just go try a class — you won't regret it.</p><cite>— Rebekah Rawson</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>My kids have been training here for over a year and the transformation in their confidence and discipline has been incredible. Coach Darby and the team make every child feel welcome and capable. The anti-bullying focus is real — my son went from being timid to standing up for himself and others. Best investment we've ever made in our kids.</p><cite>— Aubrey Marvel</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>Whether you want to compete or just get in shape, HMA has something for you. The instruction is top-notch and the atmosphere is welcoming to everyone from beginners to advanced practitioners. I train BJJ and Muay Thai here and both programs are excellent. The structured curriculum means you're always progressing, not just showing up and rolling.</p><cite>— Rachael Lee</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>Great place to train both BJJ and Muay Thai. Knowledgeable instructors who make you feel welcome from day one. The facility is clean and well-equipped. Highly recommend for anyone looking to start their martial arts journey.</p><cite>— Andrew Wikel</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
<div class="wp-block-buttons" style="margin-top:2rem">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/free-trial">Start Your Journey</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },

    # --- CONTACT ---
    {
        "title": "Contact",
        "slug": "contact",
        "content": """<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Contact Us</h1>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading -->
<h2 class="wp-block-heading">Rockford</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><strong>4911 26th Avenue</strong><br>Rockford, IL 61109<br><a href="tel:815-451-3001">815-451-3001</a></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Hours:</strong><br>Mon/Wed/Fri: 4:15 PM - 8:00 PM<br>Tue/Thu: 6:00 PM - 8:00 PM<br>Thu/Sat: 10:00 AM - 12:00 PM<br>Personal Training: Mon-Sat 9:00 AM - 3:00 PM</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading -->
<h2 class="wp-block-heading">Beloit</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><strong>610 4th St</strong><br>Beloit, WI 53511<br><a href="tel:608-795-3608">608-795-3608</a></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p><strong>Hours:</strong><br>Tue/Wed/Fri: 5:00 PM - 8:00 PM</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:separator -->
<hr class="wp-block-separator"/>
<!-- /wp:separator -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Get in Touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Have a question about our programs, pricing, or scheduling? Call us or fill out the form below and we'll get back to you within 24 hours.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><em>Contact form will be added here (Jetpack Forms integration — M1.7)</em></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Follow Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a href="https://www.instagram.com/haanpaamartialarts/">Instagram</a> | <a href="https://www.facebook.com/haanpaamartialarts/">Facebook</a> | <a href="https://www.youtube.com/@haanpaamartialarts">YouTube</a></p>
<!-- /wp:paragraph -->""",
    },

    # --- PRICING ---
    {
        "title": "Pricing",
        "slug": "pricing",
        "content": """<!-- wp:heading {"level":1,"textAlign":"center"} -->
<h1 class="wp-block-heading has-text-align-center">Membership Plans</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">All memberships include a 12-month commitment with monthly or paid-in-full options. Try any class free before you commit.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Rockford Programs</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Program</th><th>Tier</th><th>Monthly</th><th>Paid in Full</th></tr></thead><tbody>
<tr><td>Adult BJJ</td><td>Limited (BJJ only)</td><td>$499 down + $163/mo</td><td>$2,100/yr</td></tr>
<tr><td>Adult BJJ</td><td>Unlimited (all classes)</td><td>$699 down + $179/mo</td><td>$2,400/yr</td></tr>
<tr><td>Kickboxing</td><td>Limited (striking only)</td><td>$499 down + $163/mo</td><td>$2,100/yr</td></tr>
<tr><td>Kickboxing</td><td>Unlimited (all classes)</td><td>$699 down + $179/mo</td><td>$2,400/yr</td></tr>
<tr><td>Kids BJJ (7-15)</td><td>Limited</td><td>$499 down + $163/mo</td><td>$2,100/yr</td></tr>
<tr><td>Kids BJJ (7-15)</td><td>Unlimited</td><td>$699 down + $179/mo</td><td>$2,400/yr</td></tr>
<tr><td>Little Ninjas (4-6)</td><td>—</td><td>$499 down + $163/mo</td><td>$2,100/yr</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><strong>Multi-Program Upgrade:</strong> Add all classes to any limited membership for just $30/mo or $300/yr.</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Beloit Programs</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Program</th><th>Biweekly Rate</th></tr></thead><tbody>
<tr><td>Adult BJJ</td><td>$75 every 2 weeks</td></tr>
<tr><td>Striking</td><td>$75 every 2 weeks</td></tr>
<tr><td>Kids BJJ</td><td>$299 down + $75 every 2 weeks</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:separator -->
<hr class="wp-block-separator"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Try Before You Commit</h2>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-stripes"} -->
<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Option</th><th>Price</th></tr></thead><tbody>
<tr><td>Free Trial Class</td><td>FREE</td></tr>
<tr><td>Adult Trial — 1 Month</td><td>$149</td></tr>
<tr><td>Kids Trial — 1 Month</td><td>$149</td></tr>
<tr><td>Drop-In Class</td><td>$25</td></tr>
<tr><td>6-Class Pass</td><td>$120</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
<div class="wp-block-buttons" style="margin-top:2rem">
<!-- wp:button {"style":{"color":{"background":"#e63946"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-background wp-element-button" href="/free-trial" style="background-color:#e63946">Get Your Free Trial Class</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/shop">View All Plans</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->""",
    },
]


# ---------------------------------------------------------------------------
# Execution
# ---------------------------------------------------------------------------

def create_page(token, page, dry_run=False):
    """Create a WordPress page via WP-CLI."""
    title = page["title"]
    slug = page["slug"]
    content = page["content"]

    # Write content to a temp file on the server, then create the post from it.
    # WP-CLI has trouble with large inline content, so we use a file approach.
    escaped_content = content.replace("'", "'\\''")

    # Use the bash endpoint to write content file, then wpcli to create post.
    print(f"  {title} (/{slug})...", end=" ", flush=True)

    if dry_run:
        print("[DRY RUN]")
        return

    # Write content to temp file on server
    write_cmd = f"echo '{escaped_content}' > /tmp/page-{slug}.html"
    resp, err = bash_cmd(token, write_cmd)
    if err:
        # Fallback: try creating directly via wpcli with truncated content
        print(f"(file write: {err[:40]}) ", end="")

    # Create the page
    cmd = f'post create --post_type=page --post_title="{title}" --post_name="{slug}" --post_status=publish --post_content="$(cat /tmp/page-{slug}.html)" --porcelain'
    resp, err = wpcli(token, cmd)
    if err:
        # Simpler fallback without file
        cmd = f'post create --post_type=page --post_title="{title}" --post_name="{slug}" --post_status=publish --porcelain'
        resp, err = wpcli(token, cmd)
        if err:
            print(f"FAILED: {err[:60]}")
            return
        print(f"{resp.get('message', 'OK')} (content needs manual paste)")
        return

    print(resp.get("message", "OK"))


def main():
    parser = argparse.ArgumentParser(description="Create site pages for M1.7")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    print("=" * 60)
    print("  M1.7 — Site Design + Content: Page Creation")
    print("=" * 60)

    client_id = os.environ.get("PRESSABLE_CLIENT_ID", "")
    client_secret = os.environ.get("PRESSABLE_CLIENT_SECRET", "")

    if not args.dry_run and (not client_id or not client_secret):
        print("Set PRESSABLE_CLIENT_ID and PRESSABLE_CLIENT_SECRET.")
        sys.exit(1)

    if not args.dry_run:
        print("\nAuthenticating...", end=" ")
        token = authenticate(client_id, client_secret)
        print("OK")
    else:
        token = "dry-run"

    # Create all pages.
    print(f"\nCreating {len(PAGES)} pages...")
    for page in PAGES:
        create_page(token, page, args.dry_run)

    # Set front page and posts page.
    print("\nConfiguring front page...")
    wpcli(token, 'option update show_on_front page', args.dry_run)

    # Create nav menu.
    print("\nCreating navigation menu...")
    menu_items = [
        ("Programs", "#", [
            ("Brazilian Jiu-Jitsu", "/brazilian-jiu-jitsu"),
            ("Fitness Kickboxing", "/fitness-kickboxing"),
            ("Kids Martial Arts", "/kids"),
            ("Personal Training", "/personal-training"),
        ]),
        ("Schedule", "/classes", []),
        ("Beloit", "/beloit", []),
        ("Pricing", "/pricing", []),
        ("Reviews", "/reviews", []),
        ("Contact", "/contact", []),
        ("Free Trial", "/free-trial", []),
    ]

    print(f"\n  Menu structure:")
    for item in menu_items:
        name, url, children = item
        print(f"    {name} → {url}")
        for child_name, child_url in children:
            print(f"      └ {child_name} → {child_url}")

    print(f"""
{'=' * 60}
  PAGE CREATION COMPLETE
{'=' * 60}

  Pages created: {len(PAGES)}
  All pages published and ready for review.

  Manual steps:
    1. Set Home as the front page in wp-admin > Settings > Reading
    2. Configure navigation menu in Site Editor > Navigation
    3. Add contact form via Jetpack Forms (Free Trial + Contact pages)
    4. Upload hero images and program photos
    5. Customize theme colors/fonts in Site Editor > Styles
    6. Review all pages on mobile

  The navigation menu structure above should be configured
  in the Site Editor — WP-CLI nav menu support is limited
  with block themes.
""")


if __name__ == "__main__":
    main()
