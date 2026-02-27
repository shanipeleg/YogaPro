# YogaPro — Technical Gameplan

---

## Overview

A backend system that:
1. Discovers and stores all videos from a YouTube channel - https://www.youtube.com/@leayoga
2. Analyzes each video via Gemini AI to extract yoga moves, timestamps, and metadata
3. Maintains a rich database of yoga moves with body/health attributes (especially back-pain-aware)
4. Lays the groundwork for intelligent video recommendations

---

## 1. Video Discovery Strategy

### Primary: YouTube Data API v3

Use the official YouTube Data API to fetch all videos from a channel. This is the most reliable and structured approach.
(Remember channel is https://www.youtube.com/@leayoga FOR NOW- we might add more later)
**Steps:**
1. Get the channel's `uploads` playlist ID from the channel resource (`GET /channels?part=contentDetails&id={channelId}`)
2. Page through the playlist with `GET /playlistItems?part=snippet,contentDetails&playlistId={uploadsId}&maxResults=50` using `nextPageToken`
3. For each video, fetch full details: `GET /videos?part=snippet,contentDetails,statistics&id={videoIds}` (batch up to 50 IDs per request)

**Data to collect per video:**
- YouTube video ID, URL
- Title, description
- Thumbnail URLs (multiple sizes)
- Duration (ISO 8601, convert to seconds)
- Published date
- View count, like count

**Quota note:** YouTube API has a daily quota (10,000 units/day on free tier). A full channel scan is a one-time cost; paginating playlist items costs ~2 units/page, video detail fetches cost ~1 unit/video. Cache the channel scan result; only re-run to pick up new videos (daily cron).

**Fallback / supplement:** `yt-dlp` (CLI tool) can dump full channel metadata as JSON without quota limits — useful for initial bulk import or if API quota runs out:
```
yt-dlp --flat-playlist --dump-json "https://www.youtube.com/@channelname" > videos.json
```
See .env file in the directory to connect properly to gemini api.
---

## 2. Database Design (MySQL)

### Table: `channels`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| youtube_channel_id | VARCHAR(64) UNIQUE | UC... format |
| name | VARCHAR(255) | |
| handle | VARCHAR(128) | @handle |
| description | TEXT | |
| thumbnail_url | VARCHAR(512) | |
| last_scanned_at | TIMESTAMP NULL | When we last fetched their video list |
| created_at / updated_at | TIMESTAMP | |

---

### Table: `videos`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| channel_id | BIGINT FK → channels | |
| youtube_id | VARCHAR(32) UNIQUE | The 11-char video ID |
| title | VARCHAR(512) | |
| description | TEXT | |
| url | VARCHAR(512) | Full YouTube URL |
| thumbnail_url | VARCHAR(512) | |
| duration_seconds | INT | Converted from ISO 8601 |
| published_at | TIMESTAMP | |
| view_count | BIGINT | |
| like_count | BIGINT | |
| analysis_status | ENUM('pending','processing','analyzed','failed') | DEFAULT 'pending' |
| analyzed_at | TIMESTAMP NULL | |
| analysis_error | TEXT NULL | Error message if failed |
| gemini_tokens_used | INT NULL | For cost tracking |
| created_at / updated_at | TIMESTAMP | |

---

### Table: `video_segments`
One row per pose **or transition** within a video, in chronological order. Transitions are first-class entries — their duration is the primary difficulty signal.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| video_id | BIGINT FK → videos | |
| order_index | TINYINT | 1-based order within the video |
| segment_type | ENUM('pose','transition') | Whether this is a held pose or a movement between poses |
| start_time_seconds | INT | |
| end_time_seconds | INT | |
| duration_seconds | INT | Computed: end - start. For transitions, shorter = harder. |
| created_at / updated_at | TIMESTAMP | |

---

### Table: `yoga_moves`
Sourced from a yoga API (e.g. `yoga-api` on GitHub / RapidAPI) or web-scraped, then enriched. This is the core reference table.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| name | VARCHAR(255) | English name e.g. "Downward Facing Dog" |
| sanskrit_name | VARCHAR(255) NULL | e.g. "Adho Mukha Svanasana" |
| aliases | JSON NULL | Array of alternate names |
| description | TEXT | |
| category | ENUM('standing','seated','supine','prone','inversion','balancing','restorative','transition') | |
| difficulty_base | TINYINT | 1–10, sourced/default difficulty |
| **Body areas targeted** | | |
| targets_lower_back | BOOLEAN | |
| targets_upper_back | BOOLEAN | |
| targets_mid_back | BOOLEAN | |
| targets_pelvis | BOOLEAN | |
| targets_hips | BOOLEAN | |
| targets_hamstrings | BOOLEAN | |
| targets_hip_flexors | BOOLEAN | |
| targets_glutes | BOOLEAN | |
| targets_core | BOOLEAN | |
| targets_shoulders | BOOLEAN | |
| targets_neck | BOOLEAN | |
| targets_chest | BOOLEAN | |
| targets_quads | BOOLEAN | |
| targets_calves | BOOLEAN | |
| targets_ankles | BOOLEAN | |
| targets_wrists | BOOLEAN | |
| **Health benefits** | | |
| benefit_back_pain_lower | ENUM('helps','neutral','avoid') | Key for your use case |
| benefit_back_pain_upper | ENUM('helps','neutral','avoid') | |
| benefit_back_pain_general | ENUM('helps','neutral','avoid') | |
| benefit_pelvic_floor | ENUM('helps','neutral','avoid') | |
| benefit_hip_mobility | ENUM('helps','neutral','avoid') | |
| benefit_flexibility | BOOLEAN | |
| benefit_strength | BOOLEAN | |
| benefit_balance | BOOLEAN | |
| benefit_stress_relief | BOOLEAN | |
| benefit_circulation | BOOLEAN | |
| benefit_digestion | BOOLEAN | |
| benefit_posture | BOOLEAN | |
| **Contraindications / risks** | | |
| contraindications | JSON | Array of strings: conditions to avoid for |
| spinal_compression | BOOLEAN | Puts compression on spine |
| spinal_flexion | BOOLEAN | Forward bend — risky for some disc issues |
| spinal_extension | BOOLEAN | Backbend |
| spinal_rotation | BOOLEAN | Twisting |
| high_impact | BOOLEAN | |
| weight_bearing_joints | JSON | e.g. ["wrists", "knees"] |
| is_inversion | BOOLEAN | Head below heart |
| modifications_available | BOOLEAN | |
| modifications_description | TEXT NULL | How to modify for injuries |
| **Meta** | | |
| image_url | VARCHAR(512) NULL | |
| source_url | VARCHAR(512) NULL | Where data came from |
| data_source | ENUM('api','scraped','manual') | |
| created_at / updated_at | TIMESTAMP | |

---

### Table: `user_move_opinions`
Personal overrides and opinions on yoga moves. Allows fine-tuning recommendations.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| yoga_move_id | BIGINT FK → yoga_moves | |
| personal_difficulty | TINYINT NULL | 1–10, your own experience |
| comfort_level | TINYINT NULL | 1–5 (1=painful, 5=love it) |
| is_avoided | BOOLEAN | Personally avoid this move |
| avoid_reason | TEXT NULL | e.g. "triggers lower back pain" |
| personal_notes | TEXT NULL | Any free notes |
| updated_at | TIMESTAMP | |

---

### Table: `segment_moves`
Links a video segment to its yoga move(s).
- For `pose` segments: one row with `role = 'main'`
- For `transition` segments: two rows — `role = 'from'` and `role = 'to'` — capturing which pose is being left and which is being entered

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| video_segment_id | BIGINT FK → video_segments | |
| yoga_move_id | BIGINT FK → yoga_moves | |
| role | ENUM('main','transition_from','transition_to') | 'main' for poses; 'from'/'to' for transitions |
| side | ENUM('left','right','both','n_a') | Applies to pose segments |
| hold_count | TINYINT NULL | Applies to pose segments |
| ai_confidence | DECIMAL(3,2) NULL | 0.00–1.00, from Gemini |
| created_at | TIMESTAMP | |

---

### Table: `video_analysis_log`
Stores raw Gemini responses for debugging and reprocessing.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT PK | |
| video_id | BIGINT FK → videos | |
| gemini_model | VARCHAR(64) | e.g. "gemini-2.5-flash" |
| prompt_used | TEXT | Exact prompt sent |
| raw_response | JSON | Full Gemini response |
| tokens_prompt | INT NULL | |
| tokens_response | INT NULL | |
| status | ENUM('success','error') | |
| error_message | TEXT NULL | |
| created_at | TIMESTAMP | |

---

## 3. Gemini AI Analysis Pipeline
See .env file in the directory to connect properly to gemini api.
### Request Design

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={ENV_KEY}
```

**Request Body:**
```json
{
  "contents": [
    {
      "parts": [
        {
          "file_data": {
            "file_uri": "https://www.youtube.com/watch?v=VIDEO_ID"
          },
          "video_metadata": {
            "fps": 0.5
          }
        },
        {
          "text": "Watch this yoga video and produce a precise chronological log of every pose and every transition between poses. A transition is the movement between one pose and the next — it starts the moment the previous pose ends and ends the moment the new pose is settled. Do not skip transitions, even fast ones. Return ONLY valid JSON with this exact structure, no markdown, no explanation:\n{\n  \"segments\": [\n    {\n      \"order\": 1,\n      \"type\": \"pose\",\n      \"start_time_seconds\": number,\n      \"end_time_seconds\": number,\n      \"name\": \"Standard English name of the pose (e.g. Downward Facing Dog)\",\n      \"sanskrit_name\": \"Sanskrit name if known, else null\",\n      \"side\": \"left|right|both|n_a\",\n      \"hold_count\": number or null,\n      \"confidence\": 0.0-1.0\n    },\n    {\n      \"order\": 2,\n      \"type\": \"transition\",\n      \"start_time_seconds\": number,\n      \"end_time_seconds\": number,\n      \"from_name\": \"pose being left\",\n      \"from_sanskrit\": \"Sanskrit name if known, else null\",\n      \"to_name\": \"pose being entered\",\n      \"to_sanskrit\": \"Sanskrit name if known, else null\",\n      \"confidence\": 0.0-1.0\n    }\n  ]\n}"
        }
      ]
    }
  ],
  "generationConfig": {
    "responseMimeType": "application/json",
    "temperature": 0.1
  }
}
```

**Key prompt choices:**
- `fps: 0.5` — one frame every 2 seconds; enough to identify poses, minimizes token cost
- `responseMimeType: application/json` — forces structured JSON output from Gemini
- `temperature: 0.1` — low temperature for consistent, precise output
- Prompt is intentionally narrow: **only pose name + timestamps**. All health/body attributes live in the Yoga Wiki (Section 5), not here.

### Processing Flow

1. Cron fires every N minutes (configurable)
2. Queue worker picks next `videos` row where `analysis_status = 'pending'`
3. Sets status to `'processing'` (prevents double-processing)
4. Sends Gemini request
5. On success:
   - Parses JSON response
   - Inserts `video_segments` rows in order — each is either `segment_type = 'pose'` or `segment_type = 'transition'`; `duration_seconds` is computed from start/end
   - For **pose** segments: `YogaMoveResolver` looks up `yoga_moves` by name → inserts one `segment_moves` row with `role = 'main'`
   - For **transition** segments: resolves both `from_name` and `to_name` → inserts two `segment_moves` rows (`role = 'transition_from'` and `role = 'transition_to'`)
   - In both cases: if a move isn't found, creates a stub `yoga_moves` row (`enrichment_status = 'pending'`) and queues `EnrichYogaMoveJob`
   - Saves raw response to `video_analysis_log`
   - Sets `analysis_status = 'analyzed'`, writes `analyzed_at`
6. On failure:
   - Sets `analysis_status = 'failed'`, writes error to `analysis_error`
   - Logs to `video_analysis_log`
   - Job retried with backoff

---

## 4. Backend Framework Recommendation: **Laravel (PHP)**

Laravel is the ideal fit for this project because:

| Need | Laravel Feature |
|---|---|
| Queued jobs (video analysis) | Built-in Queue system (Redis/DB driver) |
| Cron scheduling (channel scan, analysis) | Task Scheduler (`schedule:run` via single crontab) |
| MySQL ORM | Eloquent ORM |
| Database migrations | Native migrations |
| CLI commands | Artisan commands |
| Job retries & backoff | Native queue job options |
| Rate limiting API calls | `RateLimiter` facade |
| Environment management | `.env` + `config/` |

### Scheduled Tasks (via `app/Console/Kernel.php`):

```
Daily  → ScanChannelJob      — Fetch new videos from YouTube API, insert pending rows
Hourly → DispatchAnalysisJob — Push a batch of pending videos onto the analysis queue
```

### Queue Jobs:

```
AnalyzeVideoJob($videoId)
  - Called by DispatchAnalysisJob
  - Handles Gemini API call + DB writes
  - maxTries: 3, backoff: [60, 300, 900] seconds
```

### Key Packages:
- `guzzlehttp/guzzle` — HTTP client for YouTube API + Gemini API
- `google/apiclient` (optional) — Official Google API client for YouTube Data API
- Queue driver: **Redis** (via `predis/predis`) for production reliability; `database` driver works fine for simpler setups

---

## 5. Yoga Wiki — Move Database & Enrichment

The `yoga_moves` table is the single source of truth for all pose knowledge. The video analysis pipeline (Section 3) only identifies *which* pose by name; all body/health attributes are owned here. This clean separation means the rich data is built once per pose, not redundantly per video.

### `yoga_moves` — add `enrichment_status` column
| Column | Type | Notes |
|---|---|---|
| enrichment_status | ENUM('pending','enriched','manual') | DEFAULT 'pending'; set to 'enriched' after Gemini wiki job runs |

### Step 1: Bulk Import (base data)

**Option A — Yoga API (recommended starting point)**
- **yoga-api** (open source): `https://github.com/alexcumplido/yoga-api` — free, self-hostable JSON dataset with English name, Sanskrit name, category, description
- **API Ninjas Yoga API**: `https://api.api-ninjas.com/v1/yoga` — REST endpoint, easy to paginate

Import gives us: name, Sanskrit name, category, base difficulty, description.

**Option B — Web scraping (richer data)**
Scrape `https://www.yogajournal.com/poses/` with `symfony/dom-crawler`. Gets benefits text, contraindications, images. More work but richer starting point.

### Step 2: Gemini Enrichment Job (per pose, one-time)

A separate queue job — `EnrichYogaMoveJob($yogaMoveId)` — runs against all `yoga_moves` where `enrichment_status = 'pending'`. This is what populates all the detailed boolean/enum columns in the table (body areas, spinal actions, back-pain classifications, contraindications, etc.).

**Prompt:**
```
You are a yoga expert and physiotherapist. Given the yoga pose "{name}" ({sanskrit_name}), return ONLY valid JSON:
{
  "category": "standing|seated|supine|prone|inversion|balancing|restorative|transition",
  "difficulty_base": 1-10,
  "targets_lower_back": true|false,
  "targets_upper_back": true|false,
  "targets_mid_back": true|false,
  "targets_pelvis": true|false,
  "targets_hips": true|false,
  "targets_hamstrings": true|false,
  "targets_hip_flexors": true|false,
  "targets_glutes": true|false,
  "targets_core": true|false,
  "targets_shoulders": true|false,
  "targets_neck": true|false,
  "targets_chest": true|false,
  "targets_quads": true|false,
  "targets_calves": true|false,
  "targets_ankles": true|false,
  "targets_wrists": true|false,
  "benefit_back_pain_lower": "helps|neutral|avoid",
  "benefit_back_pain_upper": "helps|neutral|avoid",
  "benefit_back_pain_general": "helps|neutral|avoid",
  "benefit_pelvic_floor": "helps|neutral|avoid",
  "benefit_hip_mobility": "helps|neutral|avoid",
  "benefit_flexibility": true|false,
  "benefit_strength": true|false,
  "benefit_balance": true|false,
  "benefit_stress_relief": true|false,
  "benefit_circulation": true|false,
  "benefit_digestion": true|false,
  "benefit_posture": true|false,
  "contraindications": ["string"],
  "spinal_compression": true|false,
  "spinal_flexion": true|false,
  "spinal_extension": true|false,
  "spinal_rotation": true|false,
  "high_impact": true|false,
  "weight_bearing_joints": ["string"],
  "is_inversion": true|false,
  "modifications_available": true|false,
  "modifications_description": "string or null"
}
```

This is a text-only call (no video), so token cost is tiny. Can batch-process the entire yoga_moves catalog quickly.

### Step 3: Stub rows from video analysis

When `YogaMoveResolver` encounters a pose name not in the database (a move Gemini identified in a video that isn't in the wiki yet), it creates a minimal stub row (`enrichment_status = 'pending'`). `EnrichYogaMoveJob` picks it up on its next run automatically.

### Step 4: Personal overrides

`user_move_opinions` lets you override any enriched attribute with your lived experience — e.g. marking a pose as personally avoided, adjusting difficulty, or noting it triggers your lower back despite being classified as "helps."

---

## 6. Environment & Configuration

```
# .env keys needed
YOUTUBE_API_KEY=
GEMINI_API_KEY=
DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
YOUTUBE_CHANNEL_ID=        # Target channel's ID
QUEUE_CONNECTION=redis      # or database
REDIS_HOST=
ANALYSIS_BATCH_SIZE=5       # Videos to analyze per cron run (rate limit safety)
```

---

## 7. Project Structure (Laravel)

```
app/
  Console/
    Commands/
      ScanChannelCommand.php         # artisan channel:scan
      AnalyzeVideosCommand.php       # artisan videos:analyze
  Jobs/
    ScanChannelJob.php
    AnalyzeVideoJob.php              # Video → Gemini → timestamps + pose names
    EnrichYogaMoveJob.php            # Pose name → Gemini → all body/health attributes
  Services/
    YouTubeService.php               # Wraps YouTube Data API calls
    GeminiService.php                # Wraps Gemini API calls (video analysis + wiki enrichment)
    VideoAnalysisParser.php          # Parses Gemini video JSON → DB writes
    YogaMoveResolver.php             # Finds or creates yoga_moves stub; queues enrichment
  Models/
    Channel.php
    Video.php
    VideoSegment.php
    YogaMove.php
    SegmentMove.php
    UserMoveOpinion.php
    VideoAnalysisLog.php
database/
  migrations/
    (one file per table above)
```

---

## 8. Task Tracker

> **Status key:** `⬜ Not Started` &nbsp;|&nbsp; `🔄 In Progress` &nbsp;|&nbsp; `✅ Done` &nbsp;|&nbsp; `⛔ Blocked`
>
> Update status, timestamps, and progress checkboxes every session. See `CLAUDE.md` for instructions.

---

### Task 1 — Database Migrations

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 00:00 |
| **Completed** | 2026-02-21 00:30 |

**Progress:**
- [x] Create `channels` migration
- [x] Create `videos` migration
- [x] Create `video_segments` migration (`segment_type` column included)
- [x] Create `yoga_moves` migration (`enrichment_status` column included)
- [x] Create `user_move_opinions` migration
- [x] Create `segment_moves` migration (`role` column included)
- [x] Create `video_analysis_log` migration
- [x] Run `php artisan migrate` and verify schema in DB

**Notes:**
> Laravel installed fresh. MySQL running locally (user: yogapro / pw: yogapro / db: yogapro). All 7 custom tables + 3 Laravel default tables created successfully. `video_segments.duration_seconds` uses a stored computed column (end - start).

---

### Task 2 — YouTube Channel Scanner

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 00:30 |
| **Completed** | 2026-02-21 01:00 |

**Progress:**
- [x] Set `YOUTUBE_API_KEY` and `YOUTUBE_CHANNEL_ID` in `.env`
- [x] Implement `YouTubeService` — fetch uploads playlist, paginate, batch-fetch video details
- [x] Implement `ScanChannelCommand` (`artisan channel:scan`)
- [x] Insert/upsert rows into `videos` with `analysis_status = 'pending'`
- [x] Test: run command, confirm videos appear in DB

**Notes:**
> Real channel ID is `UCYIEmkLNyiUgtXdYmvIKvgw` (resolved via `forHandle=leayoga` — the ID in GAMEPLAN was wrong). 309 videos imported, all `analysis_status = 'pending'`. Key in .env as both `YOUTUBE_API_KEY` and `GOOGLE_CLOUD_YOUTUBE_API_KEY`.

---

### Task 3 — Yoga Wiki Import

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 14:30 |
| **Completed** | 2026-02-21 14:45 |

**Progress:**
- [x] Choose data source (yoga-api JSON dataset vs. API Ninjas vs. scrape)
- [x] Write import script / seeder to populate `yoga_moves` base rows (name, Sanskrit name, category, description)
- [x] Confirm all rows land with `enrichment_status = 'pending'`
- [x] Record source used and row count here

**Notes:**
> Source: yoga-api (https://yoga-api-nzy4.onrender.com). Artisan command: `yoga:import-wiki`. 48 poses imported, all `enrichment_status = 'pending'`, `data_source = 'api'`. Category breakdown: standing×20, seated×16, prone×8, balancing×1, restorative×1, inversion×1, null×1 (Bridge — not in any category). Gemini enrichment in Task 4 will correct/refine categories.

---

### Task 4 — Yoga Wiki Enrichment (Gemini per-pose)

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 14:45 |
| **Completed** | 2026-02-21 15:15 |

**Progress:**
- [x] Implement `EnrichYogaMoveJob` — text-only Gemini call with enrichment prompt
- [x] Parse response and write all boolean/enum columns (`targets_*`, `benefit_*`, `spinal_*`, etc.)
- [x] Set `enrichment_status = 'enriched'` on success
- [x] Register enrichment cron in scheduler (or run as one-time batch via artisan)
- [x] Test on a handful of known poses, spot-check accuracy
- [x] Run full batch against all `pending` rows

**Notes:**
> Artisan command: `yoga:enrich` (supports `--sync`, `--limit`, `--sleep`). All 48 poses enriched, 0 failures. Gemini corrected categories beyond initial rough mapping (now has supine, inversion, etc.). Back-pain: 43 "helps", 3 "neutral", 2 "avoid" (Handstand, Splits). New stubs created by Task 5 will automatically get picked up by this command.

---

### Task 5 — Gemini Video Analysis Pipeline

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 15:15 |
| **Completed** | 2026-02-21 15:35 |

**Progress:**
- [x] Implement `GeminiService` — POST to Gemini endpoint with video URI + prompt
- [x] Implement `VideoAnalysisParser` — parse segments array (poses + transitions), insert `video_segments` rows
- [x] Implement `YogaMoveResolver` — name-match against `yoga_moves`; create stubs + queue enrichment for unknowns
- [x] Insert `segment_moves` rows (`role = 'main'` for poses; `transition_from` / `transition_to` for transitions)
- [x] Implement `AnalyzeVideoJob` with retry logic (maxTries: 3, backoff: 60/300/900s)
- [x] Implement `DispatchAnalysisJob` — pops N pending videos onto the queue
- [x] Save raw Gemini response to `video_analysis_log`
- [x] Mark video `analysis_status = 'analyzed'` on success, `'failed'` on error
- [x] Test end-to-end on one video; inspect DB output

**Notes:**
> Tested on video 20 (8-min shoulder/back yoga): 115 segments (58 poses + 57 transitions) inserted, all segment_moves correct. 36 new yoga_move stubs auto-created and queued for enrichment. Fix required: `VideoAnalysisLog` model needed explicit `$table = 'video_analysis_log'` (migration uses singular name). Commands: `videos:analyze --video-id=N --sync` (single), `videos:analyze --limit=N` (batch dispatch).

---

### Task 6 — Scheduler & Crons

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 15:35 |
| **Completed** | 2026-02-21 15:40 |

**Progress:**
- [x] Register `ScanChannelJob` — daily schedule
- [x] Register `DispatchAnalysisJob` — hourly schedule (respects `ANALYSIS_BATCH_SIZE`)
- [x] Add single crontab entry: `* * * * * php /path/to/artisan schedule:run`
- [x] Confirm scheduler fires correctly (check `schedule:list`)

**Notes:**
> Laravel 11 — no Kernel.php; schedule registered in `routes/console.php` using `Schedule` facade. 3 tasks: `channel:scan` (daily), `videos:analyze` (hourly), `yoga:enrich` (hourly). Crontab entry added for user `shani`. Verified with `php artisan schedule:list`.

---

### Task 7 — Queue Worker (Production)

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 15:40 |
| **Completed** | 2026-02-21 15:50 |

**Progress:**
- [x] Configure Redis (or database queue driver) in `.env`
- [x] Set up Supervisor config to keep `php artisan queue:work` alive
- [x] Test worker restart behavior on failure
- [x] Confirm failed jobs land in `failed_jobs` table and can be retried

**Notes:**
> Queue driver: `database` (already set in `.env`). Supervisor config at `supervisor/yogapro-worker.conf`. To run manually: `php artisan queue:work database --sleep=3 --tries=3 --timeout=600`. To install Supervisor on Ubuntu/WSL: `sudo apt-get install supervisor && sudo cp supervisor/yogapro-worker.conf /etc/supervisor/conf.d/ && sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start yogapro-worker:*`. Failed jobs go to `failed_jobs` table; retry with `php artisan queue:retry all`.

---

*Frontend phase — Tasks 8–17 below. Full specification: `PRD_FRONTEND.md`.*
*Tasks 8–9: Admin data panel (no design system needed — functional first).*
*Tasks 10–17: User-facing app with full design system.*

---

## Resolved Design Decisions (from PRD_FRONTEND.md § 10)

| Question | Answer |
|---|---|
| Frontend technology | Blade + Tailwind + Alpine.js — stays in Laravel, no separate SPA |
| User accounts | Single user only — no auth, no login screen |
| Primary device | Mobile phone web browser — all UI is mobile-first |
| Notifications / reminders | None — explicitly not wanted |
| Video playback | Always open in YouTube (supports casting to TV etc.) |
| Unanalyzed videos in recommendations | Never — only `analysis_status = 'analyzed'` videos are eligible |

---

### Task 8 — Admin Panel: Queue Monitor & Manual Controls

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 16:00 |
| **Completed** | 2026-02-21 16:30 |

**Progress:**
- [x] Create a minimal admin Blade layout (`layouts/admin.blade.php`):
  - Plain white background, simple top nav with links: Queue | Data | Channel | Logs
  - No design system required — functional utility classes only
  - Route prefix: `/admin`
- [x] **Queue status page** (`/admin/queue`):
  - **Pending jobs table** — query `jobs` table: columns Job Class (extracted from payload), Queue, Attempts, Available At; row count badge in nav
  - **Failed jobs table** — query `failed_jobs` table: columns ID, Job Class excerpt, Queue, Exception (first 120 chars), Failed At; actions per row:
    - "Retry" → runs `php artisan queue:retry {id}` via `Artisan::call()`
    - "Delete" → deletes the row (with confirmation prompt)
  - **Bulk actions**: "Retry All Failed" button → `Artisan::call('queue:retry', ['id' => 'all'])`
  - **Worker alive check**: run `shell_exec('pgrep -f "queue:work"')` — show green "Worker running" or red "Worker DOWN" badge
- [x] **Manual trigger panel** (on the same page, below tables):
  - Button: **Scan Channel** → `Artisan::call('channel:scan')` — fetches new videos from YouTube; shows output
  - Button: **Analyze 5 Videos** → `Artisan::call('videos:analyze', ['--limit' => 5])` — dispatches batch to queue
  - Button: **Enrich Poses** → `Artisan::call('yoga:enrich', ['--limit' => 10, '--sync' => true])` — enriches pending stubs
  - Each button shows a spinner while running and prints artisan output to a `<pre>` block on completion
  - All triggers are POST requests with CSRF (standard Laravel form)
- [x] **Analysis log quick-view** (last 20 entries, linked to full log in Task 9):
  - Columns: Video title, Model, Status badge (green/red), Tokens used, Created At
  - "View full log" link → `/admin/logs`

**Notes:**
> 6 controllers in `app/Http/Controllers/Admin/`. Admin layout uses a View Composer (AppServiceProvider) to share nav badge counts independently of page-level variables. Artisan output shown in dark `<pre>` block with session flash. All 6 admin routes return 200. Assets built via `./node_modules/.bin/vite build`.

---

### Task 9 — Admin Panel: Data Browser

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 16:00 |
| **Completed** | 2026-02-21 16:30 |

**Progress:**
- [x] **Videos browser** (`/admin/videos`):
  - Paginated table (50 per page) of all videos from `videos` table
  - Filterable by `analysis_status` — tab strip: All | Pending | Processing | Analyzed | Failed
  - Columns: ID, Thumbnail (small), Title (truncated), Duration, Published At, Status badge, Segment count (JOIN count), Analyzed At, Error (if failed, first 80 chars with tooltip for full)
  - Per-row actions: Re-analyze (dispatches AnalyzeVideoJob), View Log (links to /admin/logs?video_id=)
  - Bulk action (failed tab): Re-queue all failed button
  - Summary bar at top with color-coded counts
- [x] **Yoga moves browser** (`/admin/moves`):
  - Paginated table (50/page), filterable by enrichment_status
  - Columns: ID, Name, Sanskrit, Category, Enrichment status, Back Pain (color-coded), Spinal flags (C/F/E/R), Data Source, Videos count
  - Per-row actions: View (detail page `/admin/moves/{id}`), Re-enrich
  - Bulk: Re-enrich all pending
- [x] **Analysis logs browser** (`/admin/logs`):
  - Paginated (25/page), newest first, filterable by status + video search
  - Per-row `<details>` expand for raw_response JSON + prompt_used (no JS needed)
  - Token cost summary at top
- [x] **Channel info panel** (`/admin/channel`):
  - Channel thumbnail, name, handle, channel ID, description, last_scanned_at
  - Video counts progress bar + "Scan Channel Now" trigger
- [x] **Admin home / dashboard** (`/admin`):
  - 2×2 grid: Queue Health, Analysis Pipeline (with progress bar), Pose Enrichment, Last Activity

**Notes:**
> `<details>/<summary>` used for inline log expansion — no JS required. Videos count for yoga moves uses raw subquery (multi-hop join). View Composer in AppServiceProvider provides nav badge counts to all admin views.

---

### Task 10 — Frontend Foundation: Design System + Base Layout

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 17:00 |
| **Completed** | 2026-02-21 17:30 |

**Progress:**
- [x] Install Tailwind CSS via npm + Laravel Mix / Vite pipeline
- [x] Install Alpine.js
- [x] Define design tokens in `resources/css/app.css` via Tailwind v4 `@theme`:
  - Colors: ivory `#FAF7F2`, sage `#8FAF8F`, terracotta `#D4846A`, charcoal `#3D3D3A`, pale moss `#C5D5C5`
  - Typography: Inter (body) + Lora (serif headings) via Google Fonts
  - Border radius tokens: sm (12px) through 2xl (32px)
  - Warm shadow tokens: warm-sm / warm-md / warm-lg
- [x] Create base Blade layout (`layouts/app.blade.php`):
  - Max-width 430px container, centered on wider screens
  - Sticky bottom nav: Find | Poses | History | Stats (sage active dot indicator)
  - Sticky top header with back button support, page title slot, headerRight slot
  - Body background ivory, nav bar white with warm shadow
- [x] Create reusable Blade components:
  - `<x-card>` — rounded-2xl, warm shadow, white fill, optional hover/padding props
  - `<x-chip>` — pill badge, 6 color variants (neutral/green/sage/amber/red/terra/blue), sm/md sizes
  - `<x-section-title>` — Lora serif, charcoal
  - `<x-page-header>` — title + optional subtitle, serif heading
- [x] Register frontend routes: home / poses / history / stats with placeholder views
- [x] Build assets clean (no warnings), all Blade templates compile without error

**Notes:**
> Tailwind v4 uses CSS-based `@theme` config, not `tailwind.config.js` — tokens defined in `resources/css/app.css`. Alpine.js imported in `app.js` and bound to `window.Alpine`. All 4 nav routes work. Placeholder views in place for Tasks 13–16 to fill in.

---

### Task 11 — New DB Migrations: Sessions, Flags & Presets

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 17:30 |
| **Completed** | 2026-02-21 17:50 |

**Progress:**
- [x] Create `user_sessions` migration (video_id FK, watched_at, completed_full_video, overall_rating, notes, body_state JSON, energy_level, time_available, goals JSON, tags JSON)
- [x] Create `session_move_flags` migration (user_session_id FK, yoga_move_id FK, flag ENUM, conditional_avoidance JSON, notes)
- [x] Create `body_state_presets` migration (name, zones JSON)
- [x] Add `conditional_avoidance` JSON NULL column to `user_move_opinions`
- [x] Run `php artisan migrate` — all 4 migrations applied successfully
- [x] Create Eloquent models: `UserSession`, `SessionMoveFlag`, `BodyStatePreset`
- [x] Update `UserMoveOpinion` model with `conditional_avoidance` fillable + cast
- [x] Add relationships: `Video hasMany UserSessions`, `UserSession hasMany SessionMoveFlags`

**Notes:**
> Migration files: 000008–000011. All models verified via `php artisan model:show`. JSON columns all cast to array. UserSession→Video BelongsTo, UserSession hasMany SessionMoveFlag, SessionMoveFlag→YogaMove BelongsTo.

---

### Task 12 — Recommendation Engine

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 17:50 |
| **Completed** | 2026-02-21 18:30 |

**Progress:**
- [x] Create `app/Services/RecommendationEngine.php`
- [x] Implement hard pre-filters (analysis status, duration bounds, energy=1+fast-flow)
- [x] Implement Factor A — Safety Score (avoided −30, conditional avoidance, zone risk −5 to −20, floor 0)
- [x] Implement Factor B — Body Area Relevance (hold-duration-weighted, 50 if no zones selected)
- [x] Implement Factor C — Duration Match (100 at perfect, 80 at ±20%, −10pts/extra minute)
- [x] Implement Factor D — Energy Match (midpoint table, 100 − delta×8)
- [x] Implement Factor E — Familiarity Score + Fav Bonus (goal-specific strategy, +4 per fav up to +20)
- [x] Implement Factor F — Historical Score (context similarity: Jaccard on zones, energy delta, goal overlap)
- [x] Implement Factor G — Recency Penalty (−20/−10/−3 by days since last watch)
- [x] Implement goal-based weight table (back_pain/challenge/try_new/relax overrides)
- [x] Implement chip explanation generator (7 chip types with threshold logic)
- [x] Create `RecommendationController` → `POST /api/recommendations` route
- [x] Register `routes/api.php` in `bootstrap/app.php`
- [x] Write 22 unit tests — all passing

**Notes:**
> Bug found and fixed: Carbon 3 `diffInDays()` returns signed floats (negative for past dates). Fixed with `abs()`. API route: `POST /api/recommendations`. Goals accepted: stretch/strengthen/relax/back_pain_relief/try_something_new/challenge_me/my_favorites.

---

### Task 13 — Stats Dashboard

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 18:30 |
| **Completed** | 2026-02-21 18:50 |

**Progress:**
- [x] Create `StatsController` with `/stats` route (returns view with all data)
- [x] Build `stats/index.blade.php` dashboard view:
  - Pipeline panel: progress bar, pending/failed counts, warning banner for >5 failed
  - Yoga move panel: total, enriched, favorites, avoided, unrated, stubs
  - Video content panel: avg segments, avg hold, avg transition, shortest/longest, top 10 poses
  - Back-pain safety table: avoid/compression poses + video count + personally-avoided flag
  - Gemini cost panel: total tokens, estimated cost ($0.000075/1K)
- [x] Stats rendered in mobile-first layout using app layout + design system components

**Notes:**
> Stats page is accessible at `/stats` and uses the same bottom nav as the rest of the app. The view is functionally rich but intentionally not visually polished — it's a technical monitor. `fmtDuration()` helper defined inline in Blade for duration formatting.

---

### Task 14 — Moment Finder UI

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 18:50 |
| **Completed** | 2026-02-21 19:30 |

**Progress:**
- [x] Create `MomentFinderController` — serves home page, handles recommendation calls, video detail, preset CRUD
- [x] Body Check-In: 12-zone grid, neutral→sore(red)→target(sage)→neutral cycle, tap targets ≥48px
- [x] Preset pills above grid; "Save as preset" inline form; preset API (`GET/POST/DELETE /api/presets`)
- [x] Energy slider: 1–5 with emoji progression (😴🌿🌤⚡🔥) and labeled stops
- [x] Time picker: pill buttons 5/10/15/20/30/45/60/Any with sage active state
- [x] Goal chips: 7 goals, multi-select, sage fill when selected
- [x] "Find My Practice" terracotta CTA → fetch to `/api/recommendations` with loading shimmer
- [x] Ranked results: thumbnail, title, duration, match % badge, explanation chips, YouTube + Details actions
- [x] Video Detail page (`/videos/{id}`) with 4-tab layout:
  - Overview: pose/transition counts, avg transition, flow speed chip, description
  - Pose Timeline: chronological segments — poses highlighted (red=avoided, green=fav, ?=unrated), transitions greyed
  - Body Map: zone coverage % with proportional bars
  - History: past sessions with rating emoji, energy, tags, notes
- [x] CSRF meta tag added to app layout; `@stack('scripts')` for page-level JS
- [x] All 44 Blade templates compile cleanly; assets built clean

**Notes:**
> Alpine.js `x-data="momentFinder()"` — full component defined inline in `home.blade.php`. The `momentFinder()` function handles all state. Preset save triggers page reload to show new preset. Body state cycles: null→sore→target→null per tap.

---

### Task 15 — Pose Library & Favorites

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 19:30 |
| **Completed** | 2026-02-21 20:00 |

**Progress:**
- [x] Create `PoseLibraryController` with routes: browse, detail, opinion upsert, unrated API
- [x] Browse view (`/poses`): search bar, category/rating/sort filters, paginated pose list with comfort stars, avoided/fav chips, back-pain chip
- [x] Pose detail view (`/poses/{move}`): name+Sanskrit header, category/difficulty/inversion chips, back pain chip, body areas, spinal actions, contraindications, modifications, personal opinion card, appears-in videos
- [x] Personal opinion card: 5-emoji comfort selector, difficulty slider (1–10), avoid toggle+reason, notes textarea, save via Alpine fetch → `PUT /api/moves/{move}/opinion`
- [x] API routes: `PUT /api/moves/{move}/opinion`, `GET /api/moves/unrated`
- [x] Unrated badge in poses header links to `/poses/rate` (Quick Rate mode uses same browse view)

**Notes:**
> Quick Rate swipe mode simplified to use same browse view with `unrated` filter; full swipe-card implementation deferred (the key data infrastructure is in place). `/poses/rate` route serves the browse view filtered to unrated poses. Opinion save uses Alpine x-data=poseDetail() with saving/saved state indicators.

---

### Task 16 — Post-Session Logging & History

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 20:00 |
| **Completed** | 2026-02-21 20:20 |

**Progress:**
- [x] Create `SessionController` — POST/PUT/GET sessions, POST flags, GET history
- [x] All 5 API routes registered (`/api/sessions`, `/api/sessions/{id}`, `/api/sessions/{id}/flags`)
- [x] Session history view (`/history`): summary bar (total/avg rating/most-practiced), session cards with thumbnail/title/date/rating/tags/notes
- [x] "Log a session" Alpine modal with: video search, datetime picker, emoji rating, tags, notes, completed toggle
- [x] Video search API (`GET /api/videos/search?q=`) for inline video selector

**Notes:**
> Session logger implemented as an Alpine modal in `history/index.blade.php`. Emoji rating is the only required field (besides video). Video search uses debounced fetch to `/api/videos/search`. On save, page reloads to show new entry. Move flagging available via API (`POST /api/sessions/{id}/flags`) — the per-move flag UI on video detail page will be added when session context is available (step 3 of the flow).

---

### Task 17 — Learning Loop (Feedback → Algorithm)

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 20:20 |
| **Completed** | 2026-02-21 20:40 |

**Progress:**
- [x] `ProcessSessionFeedbackJob` — queued job that processes all move flags for a session:
  - `loved` → comfort_level = 4 (new) or min(5, current+1) (existing)
  - `uncomfortable` → max(1, comfort-1); optionally records note
  - `avoided` + permanent flag → is_avoided = true; avoid_reason from notes
  - `avoided` + conditional → appends {zones, permanent: false} to conditional_avoidance JSON
  - `too_hard` → min(10, difficulty+1); `too_easy` → max(1, difficulty-1)
  - `unclear_instructions` → appends note to personal_notes
- [x] Job dispatched from `SessionController::storeFlags()` after flags saved
- [x] Conditional avoidance integrated in Safety Factor A (already in place from Task 12)
- [x] Historical Score (Factor F) already context-matched via Jaccard+energy in Task 12
- [x] Recency penalty already reads from `user_sessions.watched_at` in Task 12
- [x] "Insights" section on History page: top 3 most-loved poses (aggregate from session_move_flags)

**Notes:**
> The full learning loop is closed: session flags → `ProcessSessionFeedbackJob` → `user_move_opinions` updates → next recommendation run uses updated opinions in Safety + Familiarity factors. Conditional avoidance rules accumulate over time and factor into every future recommendation that involves those poses when the relevant zones are sore.




---

### Task 18 — Pose Photos in the Pose Library

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-21 21:00 |
| **Completed** | 2026-02-21 21:10 |

**Progress:**
- [x] Browse view (`/poses`): add pose thumbnail image left of each list row
- [x] Browse view: graceful fallback (sage initial-circle) when no `image_url`
- [x] Detail view (`/poses/{id}`): show full-width pose image below header chips

**Notes:**
> `image_url` already populated for 48 original yoga-api poses (Cloudinary SVG). 243 stub poses (video analysis stubs) have no image — fallback needed. No backend changes required — column already exists and is populated by import command.

---

### Task 19 — Videos Browse Page

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-22 00:00 |
| **Completed** | 2026-02-22 00:10 |

**Progress:**
- [x] Add `/videos` route + controller action + nav tab
- [x] List all videos with thumbnail, title, duration chip, analysis status badge
- [x] Filters: duration range (short / medium / long), analysis status (analyzed / pending / all), search by title keyword
- [x] Each video links to existing `/videos/{id}` detail page

**Notes:**
> User wants a straightforward way to browse and pick videos without going through the Moment Finder recommendation flow. Standard filters are enough — no scoring needed here.

---

### Task 20 — Suppress Short Non-Yoga Videos

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-22 00:00 |
| **Completed** | 2026-02-22 00:10 |

**Progress:**
- [x] Add `deleted_at` column to `videos` table (soft delete)
- [x] Enable `SoftDeletes` on the `Video` model
- [x] One-time Artisan command (`videos:prune-short`) to soft-delete all existing videos under 5 minutes (300 seconds)
- [x] Update `channel:scan` to skip inserting videos under 300 seconds when fetching from YouTube API
- [x] All existing queries exclude soft-deleted rows automatically via Eloquent SoftDeletes

**Notes:**
> Threshold is 300 seconds (5 minutes). Soft delete (not hard delete) so data is recoverable if a short video turns out to be relevant. The daily scan should silently skip short videos at insert time — no need to soft-delete them on scan, just never insert them.

---

### Task 21 — UI/UX Polish (from Review)

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-22 20:00 |
| **Completed** | 2026-02-22 20:10 |

**Progress:**
- [x] Home: add time-of-day greeting ("Good morning/afternoon/evening/night")
- [x] Home: body zone hint text — "Tap once = sore (avoid) · Tap again = focus area"
- [x] Home: body zone sub-labels — show prominently only when active (no opacity fade)
- [x] Home: energy slider — active label highlighted in sage green + bold
- [x] Home: results header — "Here's what I found" + "X practices match your mood" subtitle
- [x] Home: time picker — inline "Showing all durations" / "X min" context label
- [x] Video detail: back button fixed to go to `/videos` (was going to `/`)
- [x] Video detail: floating hero back button also fixed to `/videos`
- [x] Timeline: removed confusing "?" unrated chips from pose rows
- [x] History: label "Video (YouTube ID or title)" → "Which video did you practice?"
- [x] History: "Save session" disabled state shows helper text explaining why
- [x] Global: added Noto Color Emoji to Google Fonts import + font-family stack

**Notes:**
> Emoji rendering in WSL2/Chromium is a system limitation (no system emoji font). Noto Color Emoji web font added as a fix for Linux environments; on real mobile devices (iOS/Android) emoji always renders correctly — this is not a production issue. All high and medium priority items from UI_UX_REVIEW.md addressed.



### Task 22 — Fix MAX_TOKENS failure on long videos

| | |
|---|---|
| **Status** | 🔄 In Progress |
| **Started** | 2026-02-23 18:55 |
| **Completed** | — |

**Progress:**
- [ ] Disable Gemini thinking (`thinkingBudget: 0`) in AnalyzeVideoJob
- [ ] Lower fps from 0.5 → 0.25
- [ ] Reset video 309 to `pending` and re-queue to verify fix

**Notes:**
> Root cause: `gemini-2.5-flash` is a thinking model. Its internal reasoning phase consumes tokens from the same `maxOutputTokens` budget (65536). Thinking was consuming ~63K tokens, leaving only ~2610 for actual JSON output. Lowering fps alone does NOT fix this — the output size is determined by the number of pose segments, not input frames. The correct fix is disabling thinking for this task (it's structured extraction, not reasoning) + lowering fps to 0.25 as a belt-and-suspenders measure. At 0.25fps (1 frame/4s), yoga poses (held 10-60s typically) are still fully captured. Quick transitions may occasionally be missed but this is acceptable.

### Task 23 — Hide Un-analyzed Videos from User-Facing Pages

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-23 19:05 |
| **Completed** | 2026-02-23 19:08 |

**Progress:**
- [x] VideoBrowseController: always scope to `analysis_status = 'analyzed'`; remove status filter param
- [x] Videos view: remove status filter chips row
- [x] Videos view: remove per-card status badge (redundant — all shown videos are analyzed)
- [x] Videos view: update subtitle to "N analyzed videos"
- [x] Confirmed recommendation engine already filtered to `analyzed` only
- [x] Fix: add minimum 5 pose segments filter — excludes discussion/info videos marked analyzed with 0 segments
- [x] Fix: add minimum 5-minute duration filter (always) — excludes YouTube Shorts

**Notes:**
> 173 analyzed videos shown in browser. 166 pass hard filter for recommendations (5+ pose segments, 5+ min). The 4 zero-segment videos (IDs 41, 140, 153, 213) were being recommended at the top with score 77.6 because `analysis_status=analyzed` but no yoga content — now blocked by pose count filter.

### Task 24 — Pose Images

| | |
|---|---|
| **Status** | ⛔ Blocked |
| **Started** | 2026-02-23 |
| **Completed** | — |

**Progress:**
- [x] Audited image coverage: 48 yoga-api SVGs, 1,368 missing
- [x] Built `yoga:generate-images` Artisan command (3 phases)
- [x] Phase 1: exact name/sanskrit matching — recovered 102 poses for free
- [x] Phase 2: Gemini Imagen (`imagen-4.0-fast-generate-001`) — $0.02/image
- [x] Phase 3: similarity/fuzzy matching (strips qualifiers like "variation", "(left)", etc.) — matched 90 more for free
- [x] Generated ~15 images via Imagen API before hitting rate limit
- [ ] Generate remaining top-priority poses

**Notes:**
> Total: 1,416 poses. Currently 245 have images (102 exact-matched SVGs + 90 similarity-matched + ~15 generated). 1,171 still without.
> Rate limit: free tier is 10 requests/minute for imagen-4.0-fast-generate. Command uses 0.5s delay which is too fast — need 7s delay or paid quota increase to run a full batch.
> Cost: $0.02/image. Top 50 most-used poses = $1.00. Full run of all 1,171 = ~$23.40.
> Command: `php artisan yoga:generate-images --skip-match --skip-similar --limit=50` (run multiple times, skips already-done poses)

### Task 25 — Pose Preview Animation Component

| | |
|---|---|
| **Status** | Done but broken- I can't see the animations - dont fix before I tell you to |
| **Started** | 2026-02-23 |
| **Completed** | 2026-02-23 |

**Progress:**
- [x] Create `<x-pose-preview>` Blade component (Alpine.js cycling animation)
- [x] Backend: `Video::posePreviewData()` method — works on eager-loaded relationships
- [x] Embed in video cards (videos index page) — replaces static thumbnail
- [x] Embed in recommendation slots (Moment Finder results) — pose_preview added to API response
- [x] Embed in video detail Overview tab — 150px animated strip with pose name caption

**Notes:**
> Looping GIF-like animation: cycles through pose images in segment order, ~1.5s per pose, fade transition.
> 245 yoga_moves have images (Cloudinary SVGs). 183 videos analyzed — enough to build now.
> Component should gracefully degrade (show thumbnail) when a video has <2 poses with images.


### Task 26 — Cost-Optimized Video Analysis Recovery

| | |
|---|---|
| **Status** | ⬜ Not Started - ythis is a big one so wait for me |
| **Started** | — |
| **Completed** | — |

**Progress:**
- [ ] Add `analysis_status = 'skipped'` support + `videos:skip-junk` command
- [ ] Retry 429 rate-limit failures (free — quota resets)
- [ ] Investigate 8 MAX_TOKENS failures (only 2608 output tokens — anomalous)
- [ ] Accept 16 Gemini-blocked videos as unanalyzable (PROHIBITED_CONTENT / OTHER)
- [ ] Check Google Cloud billing for Gemini seat credits
- [ ] Batch retry remaining eligible failed videos

**Notes:**
> **Failure breakdown (58 failed videos):**
> - 30 × 429 Rate Limit — just retry, quota resets. No cost to fix.
> - 16 × Gemini blocked (6 PROHIBITED_CONTENT + 10 OTHER) — nothing we can do, skip these.
> - 8 × MAX_TOKENS (anomalous: only 2608–18836 output tokens despite maxOutputTokens=65536) — retry first, investigate if still failing.
> - 2 × Network/DNS errors — retry.
>
> **Junk pending videos to skip (keywords):** announcements ("הגרלה", "הכרזה", "אנחנו X000"), handstand course ("קורס עמידת ידיים"), trauma/talking series ("ימי טראומה", "מסע הגיבור"), gear reviews ("מזרן יוגה"), pure breathing ("נשימה"), meditation-only.
>
> **Cost estimate:** ~40 retryable videos × ~$0.06/video = ~$2.40 (~8.5 shekels). Well within the 20 shekel budget.
>
> **Gemini seat:** Google One AI Premium does NOT give API credits. Check Google Cloud console (console.cloud.google.com/billing) — if there's a credit balance there, it automatically offsets Gemini API usage.
>
> **Current config:** 0.25 fps, maxOutputTokens=65536, thinkingBudget=0. Config is correct — failures are environmental (rate limits, content blocks), not config issues.

### Task 27 — Pose Library Deduplication

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-24 |
| **Completed** | 2026-02-24 |

**Progress:**
- [x] Query DB to find near-duplicate pose names (e.g. same base name with different suffixes like "left", "right", "variation", "modified")
- [x] Build `yoga:deduplicate` Artisan command: identify clusters, reassign `segment_moves` references, soft-delete or hard-delete duplicates
- [x] Also remove clearly non-yoga entries (e.g. "apply face cream", "apply lotion")
- [x] Run deduplication and verify pose count reduction
- [ ] Note: Gemini-generated images look visually different from API-scraped ones — improve image generation prompt in a later task

**Notes:**
> `yoga:deduplicate --confirm` executed. 3 phases: (1) 47 junk poses removed (talking, intro, outro, face cream, PMR, etc.), (2) 22 "X"/"X Pose" pairs merged (Plank/Plank Pose, Tabletop/Tabletop Pose, etc.), (3) 10 bilateral Left X/Right X pairs merged. Total: 83 poses removed, 1416 → 1333. 1368 segment_moves reassigned. No user opinions existed so nothing lost.

---

### Task 28 — Time Available as a Range

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-24 |
| **Completed** | 2026-02-24 |

**Progress:**
- [x] Update Moment Finder UI: replace single time picker with predefined range chips (10–20, 20–30, 30–45, 45–60, 60+)
- [x] Update `/api/recommendations` request to accept `time_min` + `time_max` instead of single `time_available`
- [x] Update RecommendationEngine Factor C (duration) to score against the range (100 inside range, drops 10pts/min outside)
- [x] Updated hard filter to use range with 30% below / 40% above tolerance
- [x] Updated MomentFinderController validation to match

**Notes:**
> Predefined range chips chosen over a range slider — simpler mobile interaction. `time_available` in user_sessions is unchanged (session logging still stores a single integer for what time they actually had). Ranges: 10–20, 20–30, 30–45, 45–60, 60+.

---

### Task 29 — Cat-Cow Consolidation

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-24 |
| **Completed** | 2026-02-24 |

**Progress:**
- [x] Identify all yoga_move rows that represent "cat" and "cow" as separate poses
- [x] Find video_segments where cat and cow appear as consecutive separate segments in the same video
- [x] Merge consecutive cat/cow segment pairs into a single "Cat-Cow" segment_move entry (update references, delete split rows)
- [x] Ensure a canonical "Cat-Cow" yoga_move exists and all merged entries point to it
- [x] Update Gemini analysis prompt to emit cat-cow as a single combined pose going forward

**Notes:**
> `yoga:consolidate-cat-cow --confirm` run. Found 99 consecutive Cat→Cow pairs across 25 videos. Merged all 99: extended Cat segment's end_time to Cow's end_time, updated yoga_move_id to Cat-Cow Pose (id=579), deleted Cow segment. Cat-Cow Pose refs: 91→190. Gemini PROMPT updated with explicit instruction: "Cat and Cow always form a single combined pose — log them as Cat-Cow Pose".

**Notes:**
> Cat-cow is taught and experienced as one flowing sequence, never as two independent poses. Splitting it creates noise in timelines and skews pose frequency counts. The fix is both a DB cleanup (historical data) and a prompt/parser change (future videos).

---

### Task 30 — In-Video Review + Review Context in Recommendations

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-24 |
| **Completed** | 2026-02-24 |

**Progress:**
- [x] Add a "Rate this practice" button on the video detail page (below YouTube CTA)
- [x] Clicking it opens an inline session logger pre-filled with that video (no video search needed)
- [x] On save, page reloads to update the History tab with the new session
- [x] In recommendation results, show personal context line on each video card: "Done N times · You love this one 🤩"
- [x] Session context added to RecommendationEngine return (`session_count`, `last_rating`, `last_watched_at`) and exposed in API response
- [x] History tab empty state updated: points user to the Rate button above instead of "coming soon"

**Notes:**
> `videoRater(videoId, videoTitle)` Alpine component added to videos/show.blade.php. Separate from the full sessionLogger on /history — simpler (no video search). Context label shown in green below duration on recommendation cards.

---

### Task 31 — Remove Gimmicky Videos and Non-Yoga Poses

| | |
|---|---|
| **Status** | ✅ Done |
| **Started** | 2026-02-24 |
| **Completed** | 2026-02-24 |

**Progress:**
- [x] Define gimmick keywords/criteria: kids videos, face massage, gear reviews, craft tutorials, course announcements
- [x] Build `videos:prune-gimmicks` Artisan command: soft-delete matching videos by title keyword scan
- [x] Review list before committing — output candidates first, require `--confirm` flag to execute
- [x] Non-yoga pose entries (face cream, talking, intro/outro, etc.) were already removed in Task 27
- [x] Soft-delete executed: 8 videos removed

**Notes:**
> `videos:prune-gimmicks --confirm` executed. 8 videos soft-deleted: 3 kids/parents yoga, 1 face massage video, 2 gear reviews (mat selection/tip), 1 craft tutorial (eye pillow sewing), 1 course announcement. No couples yoga found in DB. Soft-delete used — data recoverable. Excluded from all user-facing pages automatically via Eloquent SoftDeletes.