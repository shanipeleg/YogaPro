# YogaPro — Frontend Product Requirements Document

---

## Document Purpose

This PRD defines the full frontend experience for YogaPro. The backend (Tasks 1–7) has
produced a rich database: 309 videos, 48+ enriched yoga moves, per-pose health attributes,
per-video segment timelines, and transition data. Now it is time to turn that data into
something the user actually touches.

The guiding principle throughout: **the goal is not a clever interface — it is a user who
feels better because they practiced yoga today.** Every design decision should reduce
friction between "I want to do yoga" and "I am doing the right yoga for right now."

---

## 1. Stats Dashboard (The Technical View)

A read-only overview screen showing the health and completeness of the system.
Useful for monitoring, not for daily use.

### 1.1 Pipeline Status Panel

| Metric | Source |
|---|---|
| Total videos in library | `videos` count |
| Videos analyzed | `analysis_status = 'analyzed'` |
| Videos pending analysis | `analysis_status = 'pending'` |
| Videos failed | `analysis_status = 'failed'` |
| Analysis completion % | (analyzed / total) × 100 |
| Last video analyzed | MAX(analyzed_at) |
| Queue depth | `jobs` table count |

Display as a progress bar with counts. Show a warning banner if >5 failed videos exist.

### 1.2 Yoga Move Knowledge Base Panel

| Metric | Source |
|---|---|
| Total poses in database | `yoga_moves` count |
| Fully enriched poses | `enrichment_status = 'enriched'` |
| Pending enrichment (stubs) | `enrichment_status = 'pending'` |
| Poses I've marked as favorite | `user_move_opinions.comfort_level >= 4` |
| Poses I avoid | `user_move_opinions.is_avoided = true` |
| Poses with no personal opinion | No row in `user_move_opinions` |

### 1.3 Video Content Breakdown

Aggregate stats across analyzed videos:

- **Avg segments per video** — total segments / analyzed videos
- **Avg pose hold time** — AVG duration_seconds where segment_type = 'pose'
- **Avg transition time** — AVG duration_seconds where segment_type = 'transition' (key difficulty signal)
- **Shortest / longest video** — min/max duration_seconds in videos table
- **Most common poses** — top 10 poses by appearance count across all segment_moves
- **Rarest poses** — poses appearing in only 1–2 videos (discovery opportunities)

### 1.4 Back Pain Safety Overview

A table of all poses with `benefit_back_pain_lower = 'avoid'` or `spinal_compression = true`,
showing how many analyzed videos contain each one, and whether the user has personally
flagged it. This gives a quick audit of risk exposure in the library.

### 1.5 Cost & Usage Tracking

- Gemini tokens consumed (sum from `video_analysis_log`)
- Estimated API cost (tokens × rate)
- YouTube API quota used today (if tracked)

---

## 2. The Moment Finder — Core Use Case

This is the main screen. It answers: **"What should I do right now?"**

The entire interaction from opening the app to pressing Play should take under 60 seconds.
Everything is optional — the user can add as much or as little context as they want, and
the system will do its best with whatever it has.

### 2.1 Step 1: How Are You Feeling? (Body Check-In)

A silhouette of a body (front/back, or a simple anatomical zones grid) with tappable zones.
The user taps any area that feels tight, sore, or needs attention. Tap again to toggle off.

**Zones:**
- Lower back
- Upper back / mid back
- Shoulders (left / right / both)
- Neck
- Hips
- Hamstrings
- Hip flexors
- Core / abdomen
- Chest
- Wrists / hands
- Knees
- Calves / ankles

Each zone has a **mode** toggle with two meanings:
- **Sore / avoid** — this area hurts or is in pain. Filter OUT videos that compress or stress it.
- **Wants work** — this area is tight but ready to be stretched. Prioritize videos that target it.

Default: no zones selected (neutral body state).

**Saved body presets** — user can save named presets like "bad back day", "shoulder day",
"morning stiffness" and tap them to fill in zones instantly.

### 2.2 Step 2: Energy Level

A single horizontal slider: **1 (very low — need gentle/restorative) → 5 (high — ready to flow and be challenged)**

Map to transition difficulty thresholds:
- 1–2: prefer videos where avg transition time > 8s (slow flows, long holds)
- 3: moderate; avg transition time 4–8s
- 4–5: fast flows welcome; avg transition time < 4s fine

### 2.3 Step 3: Time Available

Quick-tap buttons: **5 min / 10 min / 15 min / 20 min / 30 min / 45 min / 60 min / Any**

This filters the video list to within ±20% of the chosen duration.
(15 min selected = show videos between 12 and 18 minutes.)

### 2.4 Step 4: Session Goal (optional)

Multi-select chips — the user picks one or more:

- **Stretch** — prioritize flexibility-benefiting poses
- **Strengthen** — prioritize strength poses, core work
- **Relax / de-stress** — prioritize restorative poses, stress_relief benefit, slow flow
- **Back pain relief** — maximize `benefit_back_pain_lower = 'helps'` poses
- **Try something new** — prioritize videos with poses I have never done
- **Challenge me** — prioritize higher difficulty, faster transitions
- **My favorites** — maximize presence of my highly-rated poses

These goals directly modify algorithm weights (see Section 4).

### 2.5 Results: Ranked Video List

Each video card shows:

```
┌─────────────────────────────────────────────────────┐
│  [thumbnail]  Shoulders & Upper Back Release        │
│               18 min · 94% match                   │
│                                                     │
│  ✅ No avoided poses   🎯 Targets: Shoulders, Neck  │
│  🌊 Gentle flow        ⭐ 3 of your favorites       │
│  🆕 1 new pose for you                              │
│                                                     │
│  [▶ Play]  [Details]  [Save for Later]              │
└─────────────────────────────────────────────────────┘
```

**Match score chips** (shown beneath the %)
The UI explains WHY this video was ranked here, not just showing a number. Each chip
corresponds to a factor in the algorithm:

| Chip | What it means |
|---|---|
| ✅ No avoided poses | Zero poses flagged as personally avoided |
| ⚠ 1 avoided pose | Contains one — user decides if it's acceptable |
| 🎯 Targets: [zones] | Video has significant coverage of selected body areas |
| 🌊 Gentle / 💨 Fast flow | Based on average transition speed |
| ⭐ N of your favorites | Poses with comfort_level ≥ 4 in this video |
| 🆕 N new poses | Poses with no personal opinion yet |
| 🔁 You've done this | Previously watched & rated |
| 💚 Great for this moment | Previously rated highly in similar context |

### 2.6 Video Detail Modal

When the user taps "Details" on a video card:

**Overview tab:**
- Full title, thumbnail, duration, published date, view count
- Direct YouTube link

**Pose Timeline tab:**
- Scrollable chronological list of all segments
- Each pose row: timestamp, pose name, hold duration, body areas (small colored dot chips)
- Each transition row: greyed out, duration shown — shorter = harder
- Poses flagged as personally avoided are highlighted in red
- Poses that are user favorites highlighted in green
- Poses with no personal opinion shown with a "?" badge

**Body Map tab:**
- Aggregate of all body zones targeted across the entire video
- Heat map style — zones that appear more often shown as warmer colors
- "This video spends 40% of time on lower back / hip flexors" style breakdown

**Your History tab:**
- Previous times this video was watched (if any session logs exist)
- Average personal rating given
- Notes left after past sessions

---

## 3. Pose Library & Favorites

A dedicated section for building out your personal pose preferences. This is the data
that supercharges the recommendation algorithm.

### 3.1 Browse All Poses

List of all poses in `yoga_moves`, sortable by:
- Name (A–Z)
- My comfort level (highest first)
- Difficulty (easiest / hardest first)
- Body area filter (shoulder poses, back poses, etc.)
- Appears in N videos

**Each pose row shows:**
- Name + Sanskrit name
- Category badge (standing, seated, supine…)
- Difficulty (AI base difficulty, overlaid with personal if set)
- My comfort level (stars, or "unrated")
- Is avoided (red badge if true)
- Targets (small body area dot chips)

### 3.2 Pose Detail View

Tapping a pose opens:

**Info section** (from `yoga_moves`):
- Full description
- All body areas targeted (formatted, not raw booleans)
- Back pain classification: "Helps lower back / Neutral / Avoid"
- Spinal actions: compression / flexion / extension / rotation (with brief explanations)
- Contraindications list
- Modifications available (yes/no, and description if yes)

**Personal section** (from `user_move_opinions`):
- **Comfort level** — star rating 1–5:
  - 1: Painful / impossible
  - 2: Very difficult / struggle
  - 3: Manageable / neutral
  - 4: Enjoy this pose
  - 5: This is a favorite
- **Personal difficulty** — 1–10 slider (your experience, overrides AI classification)
- **Avoid this pose** — toggle with reason text field ("Triggers lower back", "Wrist pain")
- **Personal notes** — free text ("Works best with block support", "Love it but need to warm up first")

**Appears in** section:
- List of all analyzed videos containing this pose
- Each video shown as a small card with duration and flow speed
- Tap to view that video detail

### 3.3 Quick Opinion Mode

A swipe-card stack for rapid-rating all unrated poses. Show one pose at a time:
- Swipe right = I like this (comfort 4)
- Swipe left = Not for me (comfort 2)
- Tap star to give exact rating
- Skip if unsure

A progress indicator: "23 of 84 poses rated"

---

## 4. The Recommendation Algorithm

This is the scoring engine behind the Moment Finder. Every video gets a score 0–100;
the ranked list is sorted descending. The algorithm is transparent — the UI shows which
factors drove the ranking.

### 4.1 Data Inputs Per Video

Before scoring, for each analyzed video collect:

```
- All poses appearing in the video (via segment_moves → yoga_moves JOIN)
- All transitions (segment_moves for transition segments → avg duration)
- For each pose: user_move_opinions data if it exists
- Video duration_seconds
- Past session ratings for this video (from user_sessions table)
```

### 4.2 Factor Definitions

---

**Factor A — Safety Score (0–100)**

Purpose: Prevent the algorithm from recommending a video that will cause pain.

```
base_score = 100

For each pose in video:
  If pose.is_avoided = true (personal flag):
    base_score -= 30   (hard penalty; one avoided pose drops score significantly)

  If any sore_zone selected by user maps to a high-risk attribute:
    - sore_zone = "lower back" AND (pose.spinal_compression OR benefit_back_pain_lower = 'avoid'):
        base_score -= 20
    - sore_zone = "lower back" AND benefit_back_pain_lower = 'neutral':
        base_score -= 5
    - sore_zone = "shoulders" AND pose.targets_shoulders AND pose.weight_bearing_joints contains "wrists":
        if user's shoulder is in "avoid" mode: base_score -= 15
    (similar logic for other zone/attribute pairs)

safety_score = max(0, base_score)
```

A video with any personally-avoided pose is shown but flagged clearly. The user can
still choose it — but they'll know.

---

**Factor B — Body Area Relevance Score (0–100)**

Purpose: Surface videos that work the areas the user wants to address.

Only applies to zones the user has marked as "wants work" (not "sore/avoid").

```
target_zones = user-selected "wants work" zones (mapped to yoga_moves boolean columns)

For each pose in video:
  hit = number of target_zones where yoga_move.targets_{zone} = true
  weight = segment.duration_seconds  (longer hold = more relevance)

relevance_score = SUM(hit × weight) / SUM(all segment durations) × 100
```

A video where 60% of screen time is in poses targeting your selected zones scores ~60 here.

---

**Factor C — Duration Match Score (0–100)**

Purpose: Return videos of approximately the right length.

```
target_minutes = user selection (convert to seconds)
video_duration = videos.duration_seconds

delta = abs(video_duration - target_minutes_in_seconds)
tolerance = target_minutes_in_seconds × 0.20   (±20%)

if delta <= tolerance:
  duration_score = 100 - (delta / tolerance × 20)   (100 at perfect match, 80 at edge of tolerance)
else:
  duration_score = max(0, 80 - ((delta - tolerance) / 60 × 10))  (drops 10 points per extra minute outside window)
```

---

**Factor D — Energy Match Score (0–100)**

Purpose: Match the pace of the video to how much energy the user has right now.

```
video_avg_transition = AVG(duration_seconds) for segment_type = 'transition' in this video

Map user energy level to expected avg transition time:
  energy 1 → expect > 10s transitions (very slow)
  energy 2 → expect 7–10s
  energy 3 → expect 4–7s
  energy 4 → expect 2–4s
  energy 5 → expect < 2s (fast flow)

delta = abs(video_avg_transition - energy_midpoint)
energy_score = max(0, 100 - (delta × 8))
```

---

**Factor E — Personal Familiarity Score (0–100)**

Purpose: Balance comfort vs. discovery based on session goal.

```
For each pose in video:
  If user has opinion: categorize as "known"
  If no opinion:       categorize as "new"

known_pct = (known_poses / total_poses) × 100
new_pct   = 100 - known_pct

Base score:
  - "Try something new" goal selected:  score = new_pct (reward novelty)
  - "My favorites" goal selected:       score = known_pct × (avg_comfort_of_known / 5)
  - "Relax" or "Back pain" goal:        score = known_pct (comfort with familiar poses matters)
  - No goal / default:                  score = 50 + (known_pct × 0.3) - (new_pct × 0.1)
    (slight preference for mostly-known with some new)
```

Separately: **Favorites Bonus**
```
fav_count = count of poses where user comfort_level >= 4
fav_bonus = min(20, fav_count × 4)   (up to +20 extra points for video loaded with favorites)
```

---

**Factor F — Historical Feedback Score (0–100)**

Purpose: Learn from past sessions and surface videos that worked in similar contexts.

```
Past sessions for this video:
  - average_overall_rating (1–5) → normalized to 0–100
  - context_match = how similar was that session's body state / energy to now?
    (Jaccard similarity on selected body zones + energy level difference)

If no past sessions: score = 50 (neutral, unknown)

If past sessions exist:
  weighted_avg = SUM(session.rating × context_match_weight) / SUM(context_match_weight)
  history_score = weighted_avg × 20   (converts 0–5 rating to 0–100)
```

Context matching means a video you rated 5 stars when your lower back hurt scores higher
when your lower back hurts again today — but its high rating for a "challenge me" day
doesn't unfairly boost it on a bad-back day.

---

**Factor G — Recency Penalty**

Purpose: Avoid recommending the same video every day.

```
days_since_last_watch = (today - MAX(session.watched_at)) for this video
  - Watched in last 3 days:  penalty = -20
  - Watched 4–7 days ago:    penalty = -10
  - Watched 8–14 days ago:   penalty = -3
  - Watched 15+ days ago:    penalty = 0
  - Never watched:           penalty = 0
```

---

### 4.3 Final Score Composition

Default weights (adjusted by session goal selections):

| Factor | Default | "Back pain" goal | "Challenge me" | "Try new" | "Relax" |
|---|---|---|---|---|---|
| A — Safety | 35% | **55%** | 20% | 25% | 40% |
| B — Relevance | 25% | 20% | 20% | 15% | 20% |
| C — Duration | 15% | 15% | 15% | 15% | 15% |
| D — Energy | 10% | 5% | **25%** | 10% | 10% |
| E — Familiarity | 10% | 5% | **15%** | **30%** | 10% |
| F — History | 5% | 0% | 5% | 5% | 5% |

```
raw_score = (A × wA) + (B × wB) + (C × wC) + (D × wD) + (E × wE) + (F × wF)
final_score = raw_score + fav_bonus - recency_penalty
final_score = clamp(final_score, 0, 100)
```

### 4.4 Hard Filters (Pre-Scoring)

These eliminate videos from consideration entirely before scoring:

1. `analysis_status != 'analyzed'` — not yet analyzed, skip
2. Duration < (target - 40%) or > (target + 50%) — wildly wrong length, skip
3. User's energy level = 1 AND video avg_transition < 1.5s — too intense, always skip

### 4.5 Explanation Generation

After scoring, generate the UI chips automatically:

```php
if ($safety_score < 70) {
    $chips[] = "⚠ Contains {N} flagged poses";
} else {
    $chips[] = "✅ No avoided poses";
}

if ($relevance_score > 60) {
    $targets = implode(', ', $top_2_zones);
    $chips[] = "🎯 Targets: {$targets}";
}

if ($avg_transition < 3) {
    $chips[] = "💨 Fast flow";
} elseif ($avg_transition > 8) {
    $chips[] = "🌊 Gentle, slow pace";
}

if ($fav_count >= 3) {
    $chips[] = "⭐ {$fav_count} of your favorites";
}

if ($new_pose_pct > 20) {
    $chips[] = "🆕 {$new_pose_count} new poses";
}

if ($history_score > 75 && $context_match > 0.7) {
    $chips[] = "💚 Worked well in a similar moment";
}
```

---

## 5. Post-Session Review

After finishing a yoga session, the user logs what happened. This is the feedback loop
that makes recommendations smarter over time.

### 5.1 Session Log Entry

Triggered manually ("I just finished this video") via a button on the video detail page,
or from a "session history" list.

**Step 1 — The basics:**
- Which video (pre-filled from "now playing" context)
- When did you watch it (default: now, can adjust)
- Did you complete the full video, or stop early?

**Step 2 — Overall rating:**

Five tappable states:
```
😫 This was wrong for today
😕 Not great — wouldn't repeat in this context
😐 Fine, neutral
😊 Good session, felt better after
🤩 Perfect — exactly what I needed
```

Stored as 1–5 in `user_sessions.overall_rating`.

**Step 3 — Specific move feedback (optional but valuable):**

Show a scrollable list of all poses from the video. For each, the user can tap to flag:

| Flag | Meaning |
|---|---|
| 💚 Loved it | This felt great today |
| ⚠ Uncomfortable | Didn't feel right today (but not permanently avoided) |
| 🚫 Skip in future | Add to avoided, ask for reason |
| ❓ Unclear instructions | Instructor wasn't clear — note this for that pose |
| Too hard | Above my level right now |
| Too easy | No challenge |

Unflagged poses = no update.

Any pose flagged "🚫 Skip in future" triggers a prompt:
> "Always avoid this pose, or just when your [lower back / shoulder] is bothering you?"
> Options: **Always avoid** | **Avoid on bad days** | **Just today — forget it**

**Step 4 — Text note (optional):**

Free text field. E.g. "Good but too fast for how tired I was. Try this on a better day."
Stored in `user_sessions.notes`.

**Step 5 — Tag this session (optional):**

Multi-select tags for future filtering:
- "Morning practice"
- "Post-work decompress"
- "Bad back day"
- "High energy"
- "Weekend flow"
- "Quick fix"

These tags allow pattern analysis over time: "When you tag a session 'bad back day',
videos rated 4+ consistently have avg_transition > 7s and < 2 poses with spinal_compression."

### 5.2 Feedback-Driven Learning

After each rated session, the algorithm updates:

**Video-level signals:**
- If overall_rating >= 4 AND context similar to now → boost history_score in similar future contexts
- If overall_rating <= 2 → add recency penalty AND note context mismatch

**Pose-level signals:**
- If pose flagged "💚 Loved it" AND no opinion exists → create `user_move_opinions` with comfort_level = 4
- If pose flagged "💚 Loved it" AND opinion exists → increment comfort_level by 1 (max 5)
- If pose flagged "⚠ Uncomfortable" → add note, don't permanently flag, but lower comfort_level by 1
- If pose flagged "🚫 Skip in future" (always) → set `is_avoided = true`
- If pose flagged "🚫 Skip in future" (on bad days) → store conditional avoidance, weight in safety score only when relevant zone is marked sore

---

## 6. New Data Requirements

The following additions to the database are needed to support the frontend.

### New Table: `user_sessions`

Records every yoga session completed.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| video_id | BIGINT FK → videos | |
| watched_at | TIMESTAMP | When session occurred |
| completed_full_video | BOOLEAN | Did they finish? |
| overall_rating | TINYINT NULL | 1–5 |
| notes | TEXT NULL | Free text post-session note |
| body_state | JSON NULL | Snapshot of zones selected (sore/target) |
| energy_level | TINYINT NULL | 1–5, what they reported going in |
| time_available | TINYINT NULL | Minutes they had |
| goals | JSON NULL | Array of goal strings selected |
| tags | JSON NULL | Array of session tags |
| created_at / updated_at | TIMESTAMP | |

### New Table: `session_move_flags`

Per-pose feedback from a specific session.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| user_session_id | BIGINT FK → user_sessions | |
| yoga_move_id | BIGINT FK → yoga_moves | |
| flag | ENUM('loved','uncomfortable','avoided','unclear_instructions','too_hard','too_easy') | |
| conditional_avoidance | JSON NULL | {"when": ["lower_back"], "permanent": false} |
| notes | TEXT NULL | |
| created_at | TIMESTAMP | |

### Extended: `user_move_opinions`

Add column:

| Column | Type | Notes |
|---|---|---|
| conditional_avoidance | JSON NULL | Zones that trigger avoidance e.g. ["lower_back", "shoulders"] |

This allows: "Avoid Wheel Pose when lower back is sore, but it's fine otherwise."

### Optional: `recommendation_cache`

Cache computed scores per video per context hash to avoid recomputing on every page load.
TTL: invalidate when any `user_move_opinions` or `user_sessions` row changes.

---

## 7. Route / API Design (Laravel Backend)

All routes return JSON. Frontend is a separate SPA or Blade+Alpine setup (TBD).

### Dashboard
```
GET /api/stats                     → pipeline status, pose counts, cost totals
GET /api/stats/videos              → video breakdown by status
GET /api/stats/poses/at-risk       → poses with 'avoid' back-pain + appears-in-N-videos
```

### Moment Finder
```
POST /api/recommendations          → body: { zones, energy, time, goals }
                                     returns: ranked video list with scores + chips

GET  /api/videos/{id}              → full video detail (segments, move data, history)
GET  /api/videos/{id}/segments     → timeline of poses + transitions for detail modal
```

### Pose Library
```
GET  /api/moves                    → all poses, filterable by zone/category/comfort
GET  /api/moves/{id}               → single pose detail + appears-in list
PUT  /api/moves/{id}/opinion       → save/update user_move_opinions for a pose
GET  /api/moves/unrated            → poses with no user_move_opinions row (for swipe mode)
```

### Session Logging
```
POST /api/sessions                 → log a new session
PUT  /api/sessions/{id}            → update session (rating, notes, tags)
POST /api/sessions/{id}/flags      → submit per-pose flags
GET  /api/sessions                 → session history list
GET  /api/sessions/{id}            → single session detail
```

### Presets
```
GET  /api/presets                  → saved body-state presets
POST /api/presets                  → save new preset (name + zones)
DELETE /api/presets/{id}           → remove preset
```

---

## 8. Implementation Phases

### Phase 1 — Data Layer & Algorithm (Backend only, no UI yet)

1. Add `user_sessions` and `session_move_flags` migrations
2. Add `conditional_avoidance` column to `user_move_opinions`
3. Build `RecommendationEngine` service class with full scoring logic
4. Build `POST /api/recommendations` endpoint, return scored list
5. Seed test opinions and a few mock sessions to validate scoring behavior
6. Unit test the algorithm: verify safety factor hard-penalizes avoided poses, energy match works, etc.

### Phase 2 — Stats Dashboard

7. Build all `/api/stats` endpoints
8. Simple HTML/Blade dashboard view — tables, progress bars, no framework needed

### Phase 3 — Moment Finder UI

9. Body zone selector component
10. Energy slider, time picker, goal chips
11. Ranked video card list
12. Video detail modal with tabs (Overview, Timeline, Body Map, History)

### Phase 4 — Pose Library

13. Browse / filter / sort poses
14. Pose detail view with personal opinion form
15. Swipe-to-rate quick mode

### Phase 5 — Session Logging & Feedback

16. "I just finished this" flow
17. Per-move flag interface (scrollable pose list post-session)
18. Session history view

### Phase 6 — Learning Loop

19. Wire session flags back into `user_move_opinions` (auto-update comfort levels)
20. Wire session ratings into `history_score` calculation
21. Conditional avoidance logic in safety factor
22. Pattern insights: "On bad-back days, you tend to enjoy videos under 20 min with slow transitions"

---

## 9. Design Principles

**Speed over completeness.** Every interaction on the Moment Finder must have a fast path.
Zones, energy, time — that's enough to get a great recommendation. Everything else is optional.

**Explain the ranking.** Never show a number without saying why. The chips on each video card
ARE the explanation. A user who understands why a video was recommended trusts the system
and uses it more.

**Personal data beats AI data.** Every place where AI-classified data (difficulty, back-pain
benefit, body area) can be overridden by user opinion — it must be. The algorithm explicitly
loads personal opinion first and falls back to AI data only when no opinion exists.

**Back pain safety is non-negotiable.** The Safety factor has the highest default weight.
On a "bad lower back" day, it is weighted even higher. No recommendation should feel like
it could hurt the user — and if the algorithm is uncertain, it should err toward caution.

**Feedback should feel lightweight.** Post-session rating is two taps: the video, and the
emoji. Everything else — pose flags, notes, tags — is optional depth for users who want it.
Never gate the feedback behind a long form.

**Show what you know.** The "new pose" badge on a video card is not a curiosity — it is a
feature. Users who want to expand their practice need to see where new poses are hiding.
Users who want comfort need to know how many familiar poses are waiting for them.

---

## 10. Open Questions (To Resolve Before Phase 3)

1. **Frontend technology** — Blade + Alpine.js (stays in Laravel, lighter) vs. separate React/Vue SPA?
   Recommendation: Blade + Alpine for Phase 1–2 (stats, internal tool), re-evaluate before Phase 3.

2. **User accounts** — Is this single-user forever, or could it ever be multi-user?
   If single-user: no auth needed, simplify all routes. If multi-user later: add now.
   ANSWER: Single user

3. **Mobile vs. desktop** — The Moment Finder body zone selector and swipe-to-rate are
   clearly mobile-first interactions. Is this primarily used on a phone?
   ANSWER: Mobile phone web browser

4. **Notification / reminder** — Should the system prompt "You haven't practiced in 3 days.
   Want a recommendation?" via email or push? Out of scope for now but worth flagging.
   ANSWER: Absolutely not

5. **Video playback in-app** — Embed YouTube iframe in the video detail modal, or always
   open in YouTube? Embedded gives more control (could log watch time); external is simpler.
   ANSWER: Open in youtube always, allows me to easily cast to TV and another options,

6. **Incomplete analysis** — 309 videos exist, but analysis is running progressively.
   Should unanalyzed videos appear in recommendations at all? Recommendation: no. Only
   analyzed videos are eligible. The dashboard shows how many are pending.
   ANSWER: No- only analyzed videos are eligible.


