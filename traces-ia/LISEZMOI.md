# 🤖 Artificial Intelligence Usage Report — PFE Challenge EMC

**Candidate:** Moaad Daoudi  
**Role:** AI & State Digital Transformation Engineer Student (ENSA Al Hoceima)  
**Selected Subject:** Subject 3 — Regional Carpooling Platform  
**Date:** November 2026  

---

## 1. AI Tools Utilized & Scope of Work

| AI Tool | Model / Version | Scope & Responsibility |
| :--- | :--- | :--- |
| **Google AI Studio** | Gemini Hydrogen (Advanced / Reasoning) | Strategic domain scoping, relational entity architecture, and multi-leg segment decomposition logic. |
| **Stitch AI** | UI & Front-end Generation Engine | Rapid prototyping and generation of modern, responsive Tailwind CSS UI layouts, form mockups, and dashboard views. |
| **Antigravity (AI Agent)** | Autonomous Development Agent | Multi-container Docker configuration, Symfony 7.4 scaffolding, pessimistic locking concurrency implementation, PHPUnit test generation, and documentation. |

---

## 2. Archive Directory Structure

```text
traces-ia/
├── LISEZMOI.md                    # Synthesis report, toolchain, and critical lessons learned
├── ai-studio/
│   ├── architecture-chat.md       # Exported chats & reasoning transcripts from Google AI Studio
│   └── raw-drive-export.json      # Raw JSON session export downloaded from Google Drive
└── antigravity/
    ├── GEMINI.md                  # Steering instructions and agent rules
    ├── implementation_plan.md     # Detailed sprint-by-sprint implementation checklist
    ├── walkthrough.md             # Verification walkthroughs and test guides
    └── session-transcript.md      # Engineering logs, architectural decisions, and prompts
```

---

## 3. The 3 Key Learnings & Critical Engineering Mindset

### A. Where AI Saved Significant Time (Productivity Gains)
- **UI/UX Design with Stitch AI:** Dramatically sped up front-end development by generating cohesive, accessible Tailwind CSS components and page layouts for the 11 user-facing views (trip search, dynamic booking timeline, driver passenger manifest).
- **Scaffolding & Architecture Blueprinting:** Rapidly structured the 5-sprint roadmap, defined the 11 user-facing UI screens without bloat, and configured multi-container Dockerfiles (PHP 8.3-FPM + Nginx + MySQL 8.0) avoiding host-level Windows configuration issues.
- **Moroccan Demo Data Generation:** Accelerated the creation of realistic `AppFixtures` with accurate GPS coordinates for 15 Moroccan cities, realistic driver profiles, and multi-stop journeys.

### B. Where AI Made Mistakes or Hallucinated (Errors Identified)
- **Git Tracking of `.env`:** Standard Symfony recipes default to committing `.env` as a baseline template, which risked exposing local credentials. We caught that `.env` should be strictly untracked and replaced by `.env.example`.
- **Naive Concurrency in Booking:** During initial checks, standard AI prompts suggested basic PHP `if ($availableSeats >= $requested)` checks. This creates severe race conditions under simultaneous requests.
- **Deprecated Framework Practices:** Suggested older Symfony 6 bundle patterns and annotations instead of modern Symfony 7.4 PHP 8.3 Attributes and native AssetMapper.

### C. How Errors Were Detected and Corrected (Engineering Rigor)
- **Strict `.gitignore` & Git Index Scrubbing:** Configured `.gitignore` with strict ignore rules (`/.env`, `/.env.local*`), retained only `!/.env.example` and `!/.env.test`, and removed `.env` from the repository index via `git rm --cached .env` without deleting the local file.
- **Enforcing ACID Transactions & Pessimistic Locks:** Replaced application-level checks with `$entityManager->wrapInTransaction()` and applied `LockMode::PESSIMISTIC_WRITE` (`SELECT ... FOR UPDATE`) directly on `TripSegment` entities.
- **Automated Concurrency Test Verification:** Designed integration tests with PHPUnit simulating rapid concurrent booking attempts to verify that overbooking is structurally impossible at the database level.
