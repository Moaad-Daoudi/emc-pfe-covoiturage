# 📝 Engineering Session Logs & Technical Prompts (Antigravity)

## Session 01: Strategic Scoping, Architecture & Sprint Planning
**Date:** November 16-17, 2026  
**Tool:** Antigravity AI Agent  
**Subject:** Subject 3 — Regional Carpooling Platform  

### 1. Profile Evaluation & Subject Selection
- **User Prompt:**
  > "i have this project : enonce-pfe-challenge, can you tell me what is the best for me as ai engineer you can see my portfolio and my cvs"
- **Agent Analysis:**
  - Evaluated the candidate's CV and existing projects (*PFE Matcher*, *Job Intelligent*, *AtlasLegalAI*, *MediConsult*).
  - Identified core competencies in optimization algorithms, constraint programming, and data engineering.
  - Strongly recommended Subject 3 (Regional Carpooling) due to the algorithmic challenge of partial sub-segment seat management and graph-based OSRM routing.

### 2. Role Architecture & UX Design
- **User Prompt:**
  > "how much roles should be on it and i want to build it like senior engineer"
- **Senior Decision:**
  - Rejected rigid separation of driver vs passenger accounts.
  - Implemented 2 technical security roles (`ROLE_USER`, `ROLE_ADMIN`) combined with contextual **Symfony Voters** (`TripVoter`, `BookingVoter`, `ReviewVoter`) to satisfy the challenge requirement: *"one person can be both"*.
  - Mapped a crisp 11-screen SaaS-grade UX blueprint + 1 unified EasyAdmin back-office.

### 3. Sprint Planning
- Authored the comprehensive roadmap `SPRINTS.md` organizing the 7-day effort into 5 cumulative sprints aligned with the 5 challenge levels.

## Session 02: Sprint 0 Execution — Docker Orchestration & Symfony 7.4 Setup
**Date:** November 17, 2026  
**Tool:** Antigravity AI Agent  
**Accomplishments:**
- Configured multi-container Docker architecture: `php` (PHP 8.3 FPM, Composer 2, pdo_mysql, intl, opcache, zip, apcu), `web` (Nginx), and `database` (MySQL 8.0).
- Successfully bootstrapped Symfony 7.4.23 skeleton and installed full webapp/orm packs.
- Tested and verified database connectivity to MySQL 8.0: database `covoiturage` created and verified.
- Handled Flex automated recipes cleanly (resolved PostgreSQL conflicts injected by Doctrine Flex recipe).


---

## Session 03 — Web Performance & Docker Volume Tuning (Sprint 0 Verification)

- **Challenge:** Initial web request to `http://localhost:8000` was hitting 504 Gateway Timeout or extreme latency during Symfony dev container compilation and asset resolution.
- **Root Cause Analysis:**
  1. Windows host filesystem boundary overhead on bind-mount `var/cache/` caused lock contention on `App_KernelDevDebugContainer.php.lock`.
  2. Nginx default FastCGI read timeout (60s) and buffer size were too constrained for initial container compilation and WebProfiler data collection.
  3. `@hotwired/stimulus` was missing from `importmap.php`, causing AssetMapper remote lookup delays.
- **Resolution:**
  1. Configured dedicated Docker named volumes for `php_cache` (`/var/www/html/var/cache`) and `php_log` (`/var/www/html/var/log`) to leverage native Linux ext4 speeds while keeping source code mounted to host.
  2. Enhanced `docker/nginx/default.conf` with `fastcgi_read_timeout 300` and `fastcgi_buffer_size 128k`.
  3. Installed `@hotwired/stimulus` via `bin/console importmap:require @hotwired/stimulus`.
  4. Successfully verified warm homepage response returning HTTP 200 OK.
