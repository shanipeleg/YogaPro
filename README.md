<div align="center">

# 🧘 YogaPro

**The right yoga video, at the right moment — every time.**

*A personal tool, built by me for me. If it's useful to you too, great — but this one's mine.*

[![PHP](https://img.shields.io/badge/PHP-8.2-8892BF?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://mysql.com)
[![Gemini AI](https://img.shields.io/badge/Gemini_2.5_Flash-AI-4285F4?logo=google&logoColor=white)](https://deepmind.google/technologies/gemini/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_v4-CSS-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)

</div>

---

## The Problem

I do yoga regularly, and I have a favorite instructor — [Lea Hutzler](https://www.youtube.com/@leayoga) — whose library I love. But with hundreds of videos, choosing the right one for a given day is hard. Some days call for something gentle. Other days I want a challenge. And with lower back pain in the mix, picking wrong isn't just frustrating — it can actually hurt.

I was spending 10 minutes scrolling every time I wanted to practice. Trying to remember which video was the gentle one, which one wrecked my back, which one had that flow I loved after a stressful day.

**YogaPro solves this.** It watches every video so I don't have to remember them.

---

## What It Does

YogaPro builds a **deep, structured understanding** of every video in a YouTube yoga channel. For each video, it knows:

- **Every pose** — identified by name, with timestamps
- **How long** each pose is held
- **How demanding** the transitions are between poses
- **Which body areas** each pose targets
- **Back-pain safety** — whether each pose helps, is neutral, or should be avoided
- **Your personal history** with each pose — your ratings, discomfort flags, avoidances

Then, when you want to practice, it asks three simple questions:

> *What's going on with your body today?*
> *How much energy do you have?*
> *How much time do you have?*

...and gives you the right video.

---

## Screenshots

### Moment Finder — Body Check-in

Tell YogaPro what your body needs today. Select the areas you want to work or that feel tight. Set your energy level and how much time you have.

<table>
<tr>
<td><img src="homepage.png" alt="Homepage - body check-in" width="280"/></td>
<td><img src="body_selected.png" alt="Body areas selected" width="280"/></td>
<td><img src="homepage_bottom.png" alt="Energy and time selection" width="280"/></td>
</tr>
</table>

### Recommendations

Results are ranked by a multi-factor engine (safety, body area match, energy level, duration, your history, pose familiarity). Each result shows **why** it was recommended — as readable chips.

<table>
<tr>
<td><img src="results_top.png" alt="Recommendation results - top" width="280"/></td>
<td><img src="results.png" alt="Recommendation results" width="280"/></td>
</tr>
</table>

### Video Detail — Pose Timeline

Every analyzed video has a full **pose timeline** — a chronological breakdown of every move with timestamps, duration, and body area indicators. Tap any pose for details.

<table>
<tr>
<td><img src="video_detail.png" alt="Video detail page" width="280"/></td>
<td><img src="video_timeline.png" alt="Pose timeline" width="280"/></td>
</tr>
</table>

### Pose Library

Browse and filter all 1,300+ poses extracted across the entire channel. See which videos each pose appears in, rate your personal comfort, flag poses to avoid, and add notes.

<img src="poses.png" alt="Pose library" width="280"/>

### Session History & Logging

Log your practice sessions with body state, energy level, goals, and per-pose feedback. The app learns from your sessions over time — adjusting what it recommends based on what worked (and what didn't).

<table>
<tr>
<td><img src="history.png" alt="Session history" width="280"/></td>
<td><img src="log_session.png" alt="Log a session" width="280"/></td>
</tr>
</table>

### Pipeline Stats Dashboard

Real-time view of the analysis pipeline — videos analyzed, poses enriched, Gemini API token usage and cost, back-pain safety coverage, and queue health.

<img src="stats.png" alt="Stats dashboard" width="280"/>

---

## How It Works

### Data Pipeline

```
YouTube API (daily cron)
    └── Discover new videos → store as pending
            └── videos:analyze (every 30 min)
                    └── Gemini 2.5 Flash (watches video at 0.5 fps)
                            └── Extracts: pose names, timestamps, transition types
                                    └── YogaMoveResolver
                                            ├── Match to known poses in DB
                                            └── New pose → EnrichYogaMoveJob
                                                    └── Gemini (text-only)
                                                            └── Body areas, benefits,
                                                                back-pain classification,
                                                                spinal actions, risks
```

### Recommendation Engine

```
POST /api/recommendations
    Input: body zones, energy (1–5), time range, goals
    │
    ├── Factor A: Safety       — avoided poses, zone risk, conditional avoidance
    ├── Factor B: Body area    — hold-duration-weighted zone relevance
    ├── Factor C: Duration     — match to time available
    ├── Factor D: Energy       — video flow speed vs your energy level
    ├── Factor E: Familiarity  — favourites bonus
    ├── Factor F: History      — session similarity (Jaccard zones + energy)
    └── Factor G: Recency      — penalty for recently watched
            └── Ranked results with explanation chips
```

---

## Key Features

| Feature | Detail |
|---|---|
| **AI Video Analysis** | Gemini 2.5 Flash watches each video at 0.5 fps, extracts structured pose+transition data |
| **Pose Knowledge Base** | 1,300+ poses enriched with body areas, benefits, spinal actions, contraindications |
| **Back-Pain Aware** | Every pose classified: helps / neutral / avoid for lower back, upper back, general |
| **Personal Overrides** | Your experience with each pose always wins over AI classification |
| **Learning Loop** | Post-session feedback updates pose avoidances and conditional preferences |
| **Smart Deduplication** | Bilateral poses (left/right), Cat-Cow sequences, and near-duplicates auto-merged |
| **Multi-Factor Scoring** | 7-factor recommendation engine with explainable output chips |
| **Mobile-First UI** | Designed for 430px — use it from your phone, mat-side |

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 11 (PHP 8.2) |
| **Database** | MySQL 8 |
| **AI** | Google Gemini 2.5 Flash (video + text) |
| **Queue** | Laravel Queue (database driver) + Supervisor |
| **Video Discovery** | YouTube Data API v3, yt-dlp fallback |
| **Frontend** | Blade + Tailwind CSS v4 + Alpine.js |
| **Design** | Mobile-first (max 430px), no auth (single-user) |

---

## App Pages

| Route | Page |
|---|---|
| `/` | Moment Finder — body check-in, energy, time → recommendations |
| `/videos` | Video browser — all analyzed videos |
| `/videos/{id}` | Video detail — pose timeline, body map, session history |
| `/poses` | Pose Library — browse, filter, rate 1,300+ poses |
| `/poses/{id}` | Pose detail — attributes, personal opinion, appears-in |
| `/history` | Session history + log a session |
| `/stats` | Pipeline stats, knowledge base, back-pain safety, API cost |
| `/admin` | Queue monitor, data browsers, manual pipeline triggers |

---

## Local Setup

### Prerequisites
- PHP 8.2+, Composer
- MySQL 8
- Node.js (for Tailwind CSS)
- Google Gemini API key
- YouTube Data API v3 key

### Install

```bash
git clone https://github.com/shanipeleg/YogaPro.git
cd YogaPro

composer install
npm install

cp .env.example .env
php artisan key:generate
```

### Configure `.env`

```env
DB_HOST=127.0.0.1
DB_DATABASE=yogapro
DB_USERNAME=yogapro
DB_PASSWORD=your_password

GEMINI_API_KEY=your_gemini_key
YOUTUBE_API_KEY=your_youtube_key
YOUTUBE_CHANNEL_ID=UCYIEmkLNyiUgtXdYmvIKvgw
```

### Migrate & seed

```bash
php artisan migrate
php artisan yoga:import-wiki   # Seed base pose knowledge
php artisan channel:scan       # Discover videos from YouTube
```

### Build assets & run

```bash
npm run build
php artisan serve
```

### Run the analysis pipeline

```bash
# Dispatch analysis jobs for pending videos (10–50 min videos only)
php artisan videos:analyze

# Enrich new pose stubs with Gemini
php artisan yoga:enrich

# Run the queue worker
php artisan queue:work
```

For production, use Supervisor to keep `queue:work` running. A daily cron calling `channel:scan` and `videos:analyze` will keep the pipeline running automatically.

---

## Artisan Commands

| Command | Description |
|---|---|
| `channel:scan` | Fetch new videos from YouTube, insert as pending |
| `videos:analyze` | Dispatch Gemini analysis jobs for unanalyzed videos |
| `yoga:enrich` | Enrich pending pose stubs via Gemini (text-only) |
| `yoga:import-wiki` | One-time bulk import of base poses |
| `yoga:deduplicate` | Merge near-duplicate poses (bilateral, aliases) |
| `yoga:consolidate-cat-cow` | Merge Cat→Cow consecutive sequences |
| `videos:prune-gimmicks` | Soft-delete off-topic videos (kids yoga, gear reviews, etc.) |

---

## Database Schema

Core tables: `channels`, `videos`, `video_segments`, `segment_moves`, `yoga_moves`, `user_move_opinions`, `user_sessions`, `session_move_flags`, `body_state_presets`, `video_analysis_log`.

See [`GAMEPLAN.md`](GAMEPLAN.md) for the full schema with column-level documentation.

---

## Why I Built This

Yoga matters to me — physically and mentally. A good session, the right video at the right time, makes me feel genuinely great. But I was spending 10 minutes scrolling before every practice, unsure what to pick, and occasionally choosing wrong and paying for it with back pain.

I wanted a tool that actually *knew* what was in each video and could match it to how I feel right now. So I built one.

---

## Credit Where It's Due

**[Lea Hutzler](https://www.youtube.com/@leayoga)** — the instructor whose videos this entire system is built around. Her teaching is thoughtful, accessible, and genuinely good for your body. If you practice yoga, go check her out.

Built by me, with **[Claude](https://claude.ai)** as my coding partner — pair programming, architecture decisions, the whole thing.

---

<div align="center">
<sub>Built for the mat · Powered by Gemini AI & Laravel · Inspired by <a href="https://www.youtube.com/@leayoga">Lea Hutzler</a></sub>
</div>
