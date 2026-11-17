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
