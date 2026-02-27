# Claude Instructions — YogaPro

## Task Tracker Discipline

The Task Tracker in `GAMEPLAN.md` (Section 8) is the live record of this project's progress.
**You must keep it current throughout every session.** It is not optional documentation — it is the source of truth.

### Rules

**When starting a task:**
- Change its status to `🔄 In Progress`
- Fill in the **Started** field with the current date and time: `YYYY-MM-DD HH:MM`

**While working on a task:**
- Check off progress checkboxes as each sub-step is completed: `- [x]`
- Add brief notes under **Notes** when something is worth recording (decisions made, blockers hit, approach taken, anything non-obvious)

**When finishing a task:**
- Change status to `✅ Done`
- Fill in the **Completed** field with the current date and time: `YYYY-MM-DD HH:MM`

**When blocked:**
- Change status to `⛔ Blocked`
- Add a **Notes** entry explaining what is blocking and what is needed to unblock

**At the start of every session:**
- Read the Task Tracker before doing anything else to understand current state
- Identify the next task to work on (lowest-numbered task that is `⬜ Not Started` and not blocked)

**At the end of every session:**
- Ensure all checkboxes and statuses accurately reflect what was actually completed
- Do not leave a task as `🔄 In Progress` if work stopped mid-session without completing it — add a note explaining where things were left off

### Format reference

```
### Task N — Title

| | |
|---|---|
| **Status** | 🔄 In Progress |
| **Started** | 2026-02-21 14:30 |
| **Completed** | — |

**Progress:**
- [x] Completed sub-step
- [ ] Pending sub-step

**Notes:**
> Chose Redis over database queue driver for reliability under load.
```

---

## What This App Is and Why It Matters

### The problem
The user has a favorite yoga YouTuber whose videos they love. (https://www.youtube.com/@leayoga) But with a large library of videos, choosing the right one for a given moment is hard. Some days call for something gentle and restorative. Other days allow for something more challenging. Without knowing what's inside each video — which poses, how demanding the transitions are, how long you're on the floor vs. standing — you're guessing every time.

### What this app does
YogaPro builds a deep, structured understanding of every video in that channel. It knows exactly which yoga poses appear, when, for how long, and how demanding the transitions between them are. Every pose is cross-referenced against a rich knowledge base covering body areas targeted, health benefits, spinal actions, and contraindications. The result is a system that can match the right video to the right moment — by energy level, body focus, difficulty, or physical need.

### What's important to get right

**Back pain is a first-class concern.**
The user deals with lower back pain. This is not a footnote — it is a core design constraint. Every yoga pose in the database must be correctly classified for its effect on the lower back, pelvis, and surrounding areas. Whether a pose helps, is neutral, or should be avoided matters deeply. Transitions that put stress on the spine need to be understood too. Getting this wrong means recommending a video that causes pain instead of relieving it. Get it right.

**Transition difficulty is real difficulty.**
A video full of slow, held poses is a very different experience from one with fast-flowing sequences. The speed and nature of transitions between poses is a key signal for how much energy a workout demands. This data is captured intentionally and must be treated as meaningful — not as a curiosity.

**Personal override always wins.**
No AI classification is perfect. The system is designed so the user's own experience with each pose — their personal difficulty rating, comfort level, and any avoidance flags — always takes precedence over what any API or Gemini model says. Build with that hierarchy in mind.

### Why this matters personally
Yoga is important to this user's physical and mental health. A good yoga session — the right video at the right time — makes them feel genuinely great. This app, if built well, removes the friction between wanting to practice and actually doing it well. It means less time scrolling through videos and more time on the mat, doing something that helps their body and clears their mind. That outcome is the point. Keep it in mind when making technical decisions: the goal is not a clever backend — it is a user who feels better because they practiced yoga today.

---

## Project Overview

### Tech stack
- **Framework:** Laravel (PHP) — queues, scheduler, Eloquent, Artisan
- **DB:** MySQL — host 127.0.0.1, db/user/pw = `yogapro`
- **Queue:** database driver; Supervisor keeps `queue:work` alive
- **AI:** Gemini 2.5 Flash — video analysis (YouTube URL at 0.5 fps) + pose enrichment (text-only)
- **YouTube:** Data API v3 (primary); yt-dlp as fallback
- **Frontend:** Blade + Tailwind v4 + Alpine.js, mobile-first (max 430px), no auth (single user)
- **Full design doc:** `GAMEPLAN.md`

### Data flow

```
YouTube API
    └── channel:scan (daily cron)
            └── videos (analysis_status = 'pending')
                    └── videos:analyze (every 30 min) — only 10–50 min videos
                            └── AnalyzeVideoJob → Gemini 2.5 Flash
                                    └── VideoAnalysisParser → video_segments + segment_moves
                                            └── YogaMoveResolver
                                                    ├── match existing yoga_moves by name
                                                    └── create stub → EnrichYogaMoveJob
                                                            └── Gemini (text-only) → all body/health attrs
```

```
POST /api/recommendations (body zones, energy 1–5, time, goals)
    └── RecommendationEngine
            ├── Pre-filter: only 'analyzed' videos, duration 10–50 min
            ├── Factor A: Safety       — avoided poses, zone risk, conditional avoidance
            ├── Factor B: Body area    — hold-duration-weighted zone relevance
            ├── Factor C: Duration     — match to time available
            ├── Factor D: Energy       — video flow speed vs energy level
            ├── Factor E: Familiarity  — favourites bonus
            ├── Factor F: History      — context similarity (Jaccard zones + energy)
            └── Factor G: Recency      — penalty for recently watched
                    └── Ranked results with explanation chips
```

### Database structure

**`channels`** — YouTube channel metadata (youtube_channel_id, name, handle, last_scanned_at)

**`videos`** — one row per video
```
youtube_id, title, description, url, thumbnail_url
duration_seconds               -- converted from ISO 8601
analysis_status                -- enum: pending | processing | analyzed | failed
analyzed_at, analysis_error, gemini_tokens_used
```

**`video_segments`** — every pose and transition in a video, in chronological order
```
video_id, order_index
segment_type                   -- enum: pose | transition
start_time_seconds, end_time_seconds, duration_seconds
```

**`yoga_moves`** — pose knowledge base (enriched by Gemini, one row per pose)
```
name, sanskrit_name, aliases (JSON), description
category                       -- standing|seated|supine|prone|inversion|balancing|restorative|transition
difficulty_base                -- 1–10
enrichment_status              -- pending | enriched | manual

-- Body areas (boolean): targets_lower_back, targets_upper_back, targets_mid_back,
   targets_pelvis, targets_hips, targets_hamstrings, targets_hip_flexors, targets_glutes,
   targets_core, targets_shoulders, targets_neck, targets_chest, targets_quads,
   targets_calves, targets_ankles, targets_wrists

-- Back pain (enum: helps|neutral|avoid): benefit_back_pain_lower, benefit_back_pain_upper, benefit_back_pain_general
-- Other benefits (enum: helps|neutral|avoid): benefit_pelvic_floor, benefit_hip_mobility
-- Boolean benefits: benefit_flexibility, benefit_strength, benefit_balance,
   benefit_stress_relief, benefit_circulation, benefit_digestion, benefit_posture

-- Spinal risk (boolean): spinal_compression, spinal_flexion, spinal_extension, spinal_rotation
-- Other risk: high_impact, is_inversion, weight_bearing_joints (JSON), contraindications (JSON)
-- Modifications: modifications_available, modifications_description
```

**`segment_moves`** — links a segment to its yoga move(s)
```
video_segment_id, yoga_move_id
role                           -- main | transition_from | transition_to
side                           -- left | right | both | n_a
hold_count, ai_confidence
```

**`user_move_opinions`** — personal overrides (always beats AI classification)
```
yoga_move_id
personal_difficulty            -- 1–10 (user's own experience)
comfort_level                  -- 1–5 (1=painful, 5=love it)
is_avoided, avoid_reason
conditional_avoidance (JSON)   -- [{zones: [...], permanent: false}, ...]
personal_notes
```

**`user_sessions`** — logged practice sessions
```
video_id, watched_at, completed_full_video
overall_rating                 -- 1–5
body_state (JSON), energy_level, time_available, goals (JSON), tags (JSON)
notes
```

**`session_move_flags`** — per-pose feedback from a session
```
user_session_id, yoga_move_id
flag                           -- loved | uncomfortable | avoided | too_hard | too_easy | unclear_instructions
conditional_avoidance (JSON), notes
```

**`body_state_presets`** — saved body check-in configurations
```
name, zones (JSON)
```

**`video_analysis_log`** — raw Gemini responses for debugging
```
video_id, gemini_model, prompt_used, raw_response (JSON)
tokens_prompt, tokens_response, status, error_message
```

### Key Artisan commands

| Command | What it does |
|---|---|
| `channel:scan` | Fetch new videos from YouTube, insert as pending |
| `videos:analyze` | Dispatch Gemini analysis jobs (10–50 min videos only) |
| `yoga:enrich` | Enrich pending yoga_move stubs via Gemini |
| `yoga:import-wiki` | One-time bulk import of base poses from yoga-api |

### App routes

| Route | Page |
|---|---|
| `/` | Moment Finder — body check-in, energy, time, goals → recommendations |
| `/videos/{id}` | Video detail — overview, pose timeline, body map, history |
| `/poses` | Pose Library — browse, filter, rate |
| `/poses/{id}` | Pose detail — attributes, personal opinion, appears-in |
| `/history` | Session history + log-a-session modal |
| `/stats` | Pipeline stats, knowledge base, back-pain safety, API cost |
| `/admin` | Queue monitor, data browsers, manual triggers |

### Current state
All 18 tasks complete. Full app built end-to-end. 309 videos in DB, pipeline running on cron.
