# YogaPro UI/UX Review

*Reviewed by browsing the full app via Playwright — Feb 2026*

---

## Overall Vibe

The design is genuinely lovely. The cream/warm-beige background, sage green palette, rounded corners, and calming typography feel appropriate for a yoga app. It doesn't look generic. The aesthetic is doing a lot of good work.

The problem is the **interactions don't feel as good as the visuals look**. The app is quiet where it should speak up, cryptic where it should be clear, and passive where it should feel alive. It doesn't feel unfriendly — it just doesn't feel warm *enough*. The design sets a high bar with its look; the UX just needs to match it.

---

## Page-by-Page Issues

---

### 1. Homepage — Moment Finder (`/`)

This is the most important page. It needs to feel like a gentle conversation, not a form.

#### The body area buttons are confusing

**The problem:** The buttons have a hidden cycling mechanic. Clicking once sets "Sore / avoid" (pink). Clicking again sets "Wants work" (green). Clicking a third time sets some intermediate active state. Then it cycles back. There is zero indication this mechanic exists.

A new user will:
- Click a body area expecting a simple toggle (selected / not selected)
- Be surprised the button changed colour AND added new text
- Not know what the text means
- Not know they can click again for a different state
- Not know what the third mystery state does

**The sub-labels are too small and low-contrast.** "Sore / avoid" and "Wants work" appear as tiny grey/muted text beneath the body area name. They read as metadata, not as an active signal that the button has two distinct modes.

**The icons (🔵, 💪, 🦢, 🦵 etc.) are rendered as tiny square outlines** — they're not loading or displaying properly. Every button shows a small square box icon instead of the intended emoji. This makes all buttons look visually identical and reinforces the confusion.

**Suggestions:**
- Make the two states explicit from the start. Instead of cycling, show **two buttons per body area**: one labelled "Sore" and one labelled "Work it". Or use a clear segmented toggle.
- Alternatively: on first click, show a small popover/tooltip that says *"Tap again to switch to 'Wants work' instead."*
- Make the selected state labels much more prominent — bolder, larger, or displayed as a badge rather than sub-text.
- Fix the emoji/icon rendering.

---

#### The energy slider is unclear

**The problem:** There are three icons above the slider (a moon 🌙, something, and a sun-like starburst ✳). These icons are not labelling the positions clearly. The moon renders oddly and the middle icon appears blank.

Below the slider are 5 labels: Need rest / Low / Moderate / Good / Let's go! — but the slider thumb is a blue dot sitting at position 3 ("Moderate"), and there's no visual connection between the thumb and the label it corresponds to. If you drag it, it's not obvious which label is active.

**Suggestions:**
- Highlight the current label (bold + colour) as the slider moves.
- Consider replacing the slider with 5 big tap-able mood buttons. For a yoga app, "How energetic do you feel?" maps naturally to something tactile and visual — like 5 faces or 5 descriptive cards.
- Or at minimum, show the current value as a label above the thumb itself.

---

#### "Time available" — "Any" is pre-selected but it doesn't look different enough from the others

The "Any" button has a filled green background (selected) while the others are white outlines. This is good. But the first-time user doesn't know they've already made a choice. There's no framing like *"Already set to Any — tap to narrow it down."*

**Suggestions:**
- The current state is fine visually. Just add a short line of context: *"Showing all durations"* that updates when a specific time is selected.

---

#### "What's your goal?" — the icons don't render

Same issue as body buttons: the icon before each goal button (🧘, 💪, 😌 etc.) renders as a small square outline. The buttons look like plain text with a broken icon prefix.

---

#### "Find My Practice" button is too quiet

The primary action button is plain bold text centered on the page with no obvious button affordance in the screenshot — it blends into the surrounding content. It needs to be much more prominent: full-width, filled background, maybe with a subtle pulse or glow on page load to invite the user in.

**Suggestion:** Full-width pill button, sage green fill, white text, slightly larger font. This is *the* action. Treat it like one.

---

#### Results appear below the form with no signal

When you press "Find My Practice", the page scrolls down and shows results — but there's no transition, no loading state, no "here's what I found for you" header. The results just appear. Because the form stays at the top, the user might not even notice results have appeared below.

**Suggestions:**
- After submission, smoothly scroll to the results section.
- Show a friendly header above results: *"Here's what I found for you"* or *"3 practices that match your mood"*.
- Add a loading state (even a brief spinner or animated dots) so the submission feels responsive.

---

### 2. Videos page (`/videos`)

Clean and functional. The filter chips (All lengths / 5–15 min / etc.) are clear. The list items with thumbnails work well.

**Issues:**
- The status badge ("Analyzed", "Failed") is shown as a small tag on every row. "Failed" appears in a plain style — it doesn't feel alarming enough for a failed video, but also doesn't need to be. For a single user, failed videos could just be hidden rather than surfaced in this list.
- No thumbnail-first layout option. On mobile, the list is small thumbnails + right-aligned text. The titles are long (and in Hebrew) so they wrap. A card-style grid might feel more visual and easier to scan.
- The "Search" button below the filter chips is generic. It could say "Show videos" or just be removed in favour of live filtering.

---

### 3. Video detail page (`/videos/{id}`)

This is quite good. The hero thumbnail, stats (26 poses / 27 transitions / 1s avg), and the "Fast flow" badge are useful at a glance.

**Issues:**

- **The back button is a `<` chevron with "YogaPro" next to it.** That's not a back button — that's a home button. But it's positioned and styled like a back button. Users expect a back arrow to go back to the video list, not to the homepage. Label it clearly: `← Videos` or use a proper back navigation.

- **Tab labels (Overview / Timeline / Body Map / History) are functional but small.** Especially on mobile, these 4 tabs are quite close together with small text. Consider making them slightly larger or using icons + labels.

- **The Timeline tab** shows every single segment including transitions as a dense list. The "S" badge (for Seated?) and "B" badge (for Breathing?) are not explained anywhere. A tiny legend would help enormously. Also, all pose rows show "?" as the difficulty — this is a stub placeholder that leaks through to the user.

- **"Open in YouTube"** is the right primary CTA on this page. It's well-styled (terracotta pill button).

---

### 4. Pose Library (`/poses`)

**Issues:**

- **1,226 poses is overwhelming.** The list is alphabetical and starts with "Abdominal Crunch" variations. For a yoga app meant to help someone feel good, starting with clinical-sounding variations of abdominal crunches isn't welcoming.

- **The "1226 unrated" badge** in the top-right is a salmon/red alert chip. It reads as a warning or an error, not as an invitation. The label "unrated" isn't actionable without context. Consider: *"Rate your poses →"* in a friendlier style.

- **Each pose row shows**: an "A" circle avatar (just the first letter, always "A" on this page since everything starts with A), pose name, back-pain badge ("✓ Helps back" / "✗ Avoid"), category (Supine/Standing), and "unrated ›". This is a dense information row and it works — but the long pose names (e.g. "Abdominal Crunch with Left Leg Extension, Left Twist, and Right Arm Extension") wrap to 3 lines and make the list feel very long.

- **No quick way to jump to poses you've actually done.** The most useful entry point for this user would be "poses from my videos" — not an alphabetical list of all 1,226.

---

### 5. Practice History (`/history`)

This page is mostly good. The "Log a session" button is prominent and well-placed.

**The log-a-session form:**

- **"Video (YouTube ID or title)"** — the label says "YouTube ID or title" which is developer-speak. Just say *"Which video did you practice?"*

- **The emoji rating buttons (😫 😕 😐 😊 🤩)** — on the screenshot, the last emoji (🤩) renders as a red square outline, not the intended face. Same emoji rendering issue as elsewhere.

- **The "Save session" button is disabled** until a video is selected. The disabled state is very subtle (grey text, no border change). The user might try to click it and nothing happens — with no explanation of why. Add a small note: *"Pick a video to save your session."*

- **The tag buttons** (Morning practice / Post-work / Bad back day etc.) are well-chosen and human-feeling. This section is good.

---

### 6. Stats page (`/stats`)

This is an internal/informational page and it's well-executed. The "Analysis Pipeline" section with the warning and progress bar is clear. The "Back Pain Safety" list is useful.

Minor: the "Pose Knowledge Base" section shows stat tiles with a small square-outline icon in every tile (the same broken emoji/icon rendering issue). The emojis (🧘, ✅, ⭐, 🚫, ❓, ⏳) are all showing as squares.

---

## Global Issues

### 1. Emoji rendering is broken everywhere

Almost every emoji in the app renders as a small square outline on this system. This is likely a system font issue (WSL2 + Chromium not having an emoji font installed), but it's worth confirming it works in production/on the target device. The affected elements are:
- Body area button icons (🔵, 💪, 🦵, etc.)
- Energy level icons (🌙, ☀)
- Goal button icons (🧘, 💪, 😌, etc.)
- Rating emojis (😫 😕 😐 😊 🤩)
- Stats icons (🧘 ✅ ⭐ 🚫 ❓ ⏳)

If emojis don't render, they leave broken visual gaps and the design falls apart in those areas.

---

### 2. The top header bar

Every page shows "YogaPro" in the top bar — but on the video detail page it becomes a back-navigation element. The header is inconsistent: sometimes it's a title, sometimes it's a nav link, sometimes it has a `<` chevron. Decide what the header is and be consistent.

---

### 3. The bottom nav

Five tabs: Find / Videos / Poses / History / Stats.

- "Find" is a good label for the homepage (Moment Finder).
- The icons are small and consistent but a bit generic.
- The active tab gets a coloured icon — but the colour difference is subtle. A more obvious active state would help (bolder label, filled icon vs outline, or an underline indicator).
- "Stats" at the end feels out of place for a user-facing app. Consider renaming it "Health" or "Insights" or moving it to a less prominent spot.

---

### 4. No onboarding or empty state warmth

First time you open the app (or on a fresh state), the homepage just presents the full form with no greeting, no context, no "here's what to do". For an app that's meant to feel personal and caring, a small welcome line — even just *"Good morning. How are you feeling today?"* based on time of day — would go a long way.

---

## Priority Summary

| Priority | Issue |
|---|---|
| 🔴 High | Body area button cycling mechanic — invisible and confusing |
| 🔴 High | Emoji rendering broken (affects most of the app visually) |
| 🔴 High | "Find My Practice" results appear with no scroll/transition/header |
| 🟡 Medium | Energy slider — no clear indication of current label |
| 🟡 Medium | "?" difficulty placeholders visible in Timeline |
| 🟡 Medium | Timeline badge legend ("S", "B") unexplained |
| 🟡 Medium | "Save session" disabled with no explanation |
| 🟡 Medium | Video detail back button goes to home, not back to videos |
| 🟢 Low | Pose library starting point — not warm or relevant |
| 🟢 Low | "Video (YouTube ID or title)" developer-speak label |
| 🟢 Low | Stats tab name feels out of place |
| 🟢 Low | Empty state / onboarding for new users |
