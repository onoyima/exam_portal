# PROPOSAL FOR DIGITAL ASSESSMENT INFRASTRUCTURE

## Presented to: The Management of Veritas University, Abuja

**Project:** Institutional Digital Examination & Proctoring Platform  
**Proposed by:** BMXcode Initiator  
**Date:** May 2026  
**Version:** 2.0 — Production Ready

---

## CONFIDENTIALITY NOTICE

This document contains proprietary information about the NextGen Assessment Portal. It is intended solely for the evaluation by Veritas University and should not be distributed without written consent.

---

# TABLE OF CONTENTS

1. [Executive Summary](#1-executive-summary)
2. [The Challenge We Solve](#2-the-challenge-we-solve)
3. [Our Solution](#3-our-solution)
4. [Platform Capabilities at a Glance](#4-platform-capabilities-at-a-glance)
5. [Examination Types Supported](#5-examination-types-supported)
6. [The Veritas Advantage](#6-the-veritas-advantage)
7. [Security & Integrity Framework](#7-security--integrity-framework)
8. [ROI & Cost-Benefit Analysis](#8-roi--cost-benefit-analysis)
9. [Implementation Plan](#9-implementation-plan)
10. [Technical Architecture Overview](#10-technical-architecture-overview)
11. [Support & Maintenance](#11-support--maintenance)
12. [Pricing & Investment](#12-pricing--investment)
13. [Next Steps](#13-next-steps)
14. [Appendix: System Showcase](#14-appendix-system-showcase)

---

# 1. EXECUTIVE SUMMARY

The NextGen Assessment Portal is a **comprehensive digital examination platform** purpose-built for Veritas University. It replaces manual, paper-based assessments with a secure, automated, and remotely invigilated system that supports **multiple examination formats**, provides **real-time proctoring**, and delivers **instant results**.

This platform solves Veritas University's most pressing assessment challenges:

| Challenge | How We Solve It |
|-----------|----------------|
| **Malpractice during exams** | 7-layer security stack including live webcam monitoring, automated violation detection, and device authorization |
| **Manual grading bottlenecks** | MCQ auto-grading delivers results instantly; practical/VIVA rubrics streamline examiner workflow |
| **Logistical complexity** | From scheduling to results — everything managed in one dashboard |
| **Limited exam capacity** | Run unlimited concurrent digital exams with no venue constraints |
| **Accreditation compliance** | Full audit trail on every action, student attempt, and system event |
| **Resource wastage** | Eliminate printing, transport, storage, and manual data entry costs |

**The platform is fully developed, tested, and ready for production deployment within one week.**

---

# 2. THE CHALLENGE WE SOLVE

## 2.1 Current Reality at Nigerian Universities

Assessment administration in higher education faces well-documented challenges:

- **Examination Malpractice**: The demand for remote and large-scale assessments has outpaced institutions' ability to invigilate effectively
- **Grading Delays**: Manual grading of hundreds of scripts takes weeks, delaying result publication
- **Logistical Burden**: Printing, securing, distributing, and storing physical exam materials consumes significant institutional resources
- **Data Integrity**: Paper-based systems are vulnerable to loss, alteration, and unauthorized access
- **Scalability Constraints**: Physical venue capacity limits the number of students who can be assessed simultaneously

## 2.2 The Digital Mandate

With the Nigeria Universities Commission (NUC) and other accrediting bodies increasingly recognizing digital assessment formats, institutions that invest in robust digital infrastructure gain:

- A **competitive advantage** in student recruitment
- **Operational efficiency** through automated workflows
- **Enhanced credibility** with accrediting bodies
- **Future-proofing** against disruptions

---

# 3. OUR SOLUTION

## NextGen Assessment Portal

A **web-based, end-to-end examination platform** that enables Veritas University to:

1. **Create** any type of assessment (Objective MCQ, Practical OSCE, VIVA oral)
2. **Schedule** exams with precise start/end times and academic session tracking
3. **Invigilate** remotely using live video feeds, device controls, and automated violation detection
4. **Grade** automatically (MCQ) or through structured rubrics (Practical/VIVA)
5. **Analyze** results with built-in analytics, reports, and exportable data
6. **Audit** every action with a tamper-proof trail

### How It Works — Simple 4-Step Flow

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  ADMIN   │     │ STUDENT  │     │  SYSTEM  │     │ RESULT   │
│ SETS UP  │ ──▶ │ TAKES    │ ──▶ │ PROCTORS │ ──▶ │ PUBLISHED│
│ EXAM     │     │ EXAM     │     │ & GRADES │     │          │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
```

**Step 1 — Setup:** Administrator creates exam, uploads questions, assigns students and examiners, sets schedule  
**Step 2 — Execution:** Student logs in from any device, completes the exam under live proctoring  
**Step 3 — Monitoring:** Admin/examiners monitor all sessions in real-time; system detects and flags violations  
**Step 4 — Results:** MCQ graded instantly; Practical/VIVA scores published as examiners submit

---

# 4. PLATFORM CAPABILITIES AT A GLANCE

## For the Vice-Chancellor & Governing Council

| Capability | Impact |
|------------|--------|
| **Full digital transformation** | Eliminate paper, printing, transport, and storage costs across all assessments |
| **Academic integrity assurance** | Multi-layered proctoring protects the value of Veritas degrees |
| **Accreditation-ready audit** | Every exam, every submission, every login permanently logged |
| **Institutional analytics** | Data-driven insights into student performance across cohorts and sessions |
| **Brand enhancement** | Position Veritas as a technology-forward institution |

## For the Registrar & Academic Affairs

| Capability | Benefit |
|------------|---------|
| **Centralized exam scheduling** | Manage all exams from one dashboard with academic session tracking |
| **Automated result computation** | MCQs graded instantly; practical scores aggregated automatically |
| **Bulk student onboarding** | Import thousands of students via CSV in minutes |
| **Attempt management** | Configure max attempts, grading method (highest/average), and time limits per exam |
| **Result verification** | Cross-reference scores with audit trail for complete transparency |

## For Deans & Heads of Department

| Capability | Benefit |
|------------|---------|
| **Multiple exam types** | Objective (MCQ), Practical (OSCE/rubric), and VIVA (oral) in one platform |
| **Question banking** | Build reusable question banks with categorized pools and random selection |
| **Custom scoring rubrics** | Define granular scoring criteria for practical assessments |
| **Performance analytics** | View cohort performance, score distributions, and individual student metrics |
| **Exportable reports** | Download results in CSV for further analysis or LMS integration |

## For ICT Directorate

| Capability | Benefit |
|------------|---------|
| **On-premise deployment** | Full data sovereignty — all student data remains on Veritas servers |
| **PHP/MySQL stack** | Runs on standard LAMP/XAMPP; no proprietary licenses required |
| **HTTPS with WebRTC** | Industry-standard encrypted streaming for proctoring |
| **Device authorization** | IP-based device whitelisting prevents unauthorized access |
| **Low server footprint** | Runs efficiently on shared hosting or modest dedicated hardware |

## For Students

| Capability | Benefit |
|------------|---------|
| **Any device access** | Desktop, laptop, tablet — any modern browser works |
| **Instant results (MCQ)** | Know your score immediately after submission |
| **Multiple attempts** | Retake options with highest-score or average grading |
| **Review mode** | Review past attempts and performance |
| **Flexible scheduling** | Time-bound access windows accommodate varied schedules |
| **Fair assessment** | Randomized questions and options ensure equal challenge |

---

# 5. EXAMINATION TYPES SUPPORTED

## 5.1 Objective (Multiple Choice Questions)

```
═══════════════════════════════════════════════════════════
  FEATURE              DETAIL
───────────────────────────────────────────────────────────
  Grading              Fully automatic (instant results)
  Question types       A/B/C/D single-answer MCQ
  Shuffling            Randomize question order per student
  Options shuffling    Randomize A/B/C/D order per question
  Attempts             Configurable (1–unlimited)
  Grading methods      Highest score or Average of attempts
  Timer                Configurable duration with auto-submit
  Question pools       Show random subset from larger bank
═══════════════════════════════════════════════════════════
```

## 5.2 Practical (OSCE / Rubric-Based)

```
═══════════════════════════════════════════════════════════
  FEATURE              DETAIL
───────────────────────────────────────────────────────────
  Grading              Manual by assigned examiner
  Scoring              Custom score values per criterion
  Fraction support     ½, ¼, ⅛ increments (nursing/medical)
  Search               Find students by name or matric no.
  Revisions            Track first attempt vs. second attempt
  Multi-examiner       Multiple examiners per exam
═══════════════════════════════════════════════════════════
```

## 5.3 VIVA (Oral Examination)

```
═══════════════════════════════════════════════════════════
  FEATURE              DETAIL
───────────────────────────────────────────────────────────
  Grading              Manual by examiner (1–N scale)
  Quick-select list    Grid of all students for efficient flow
  Auto-creation        Single question auto-created per VIVA
  Status tracking      Completed / pending per student
═══════════════════════════════════════════════════════════
```

---

# 6. THE VERITAS ADVANTAGE

## 6.1 Why This Platform Is Different

| Factor | Generic LMS (Moodle, etc.) | NextGen Assessment Portal |
|--------|---------------------------|---------------------------|
| **Purpose** | General learning management | **Purpose-built for high-stakes exams** |
| **Exam types** | Basic MCQ only | MCQ + Practical OSCE + VIVA |
| **Live proctoring** | ❌ Requires expensive 3rd-party plugin | ✅ Built-in WebRTC live video |
| **Device authorization** | ❌ Not available | ✅ IP-based device whitelisting |
| **Student lockdown** | ❌ Basic browser enforcement | ✅ Kiosk mode with keyboard/tab blocks |
| **SEB integration** | ❌ Not available | ✅ Safe Exam Browser support |
| **Audit trail** | Basic logging | ✅ Trigger-based immutable audit trail |
| **Offline deployment** | Requires internet | ✅ On-premise, no external dependency |
| **Cost** | Free but needs expensive plugins | ✅ All-inclusive, no recurring license |
| **Support** | Community/paid | ✅ Direct developer support via WhatsApp |

## 6.2 Designed for Nigerian Universities

This platform was built specifically for the Nigerian university context:

- **Works on local networks** (LAN) — no constant internet required for basic operation
- **CSV import/export** — compatible with existing university record systems
- **Fractional scoring** — supports the detailed rubrics used in Nigerian nursing/medical education
- **Matric number auth** — students use existing university identifiers
- **WhatsApp support channel** — accessible communication without email dependency

---

# 7. SECURITY & INTEGRITY FRAMEWORK

## The 7-Layer Protection Model

```
┌═══════════════════════════════════════════════════════════════┐
║                   7-LAYER SECURITY ARCHITECTURE                ║
║                                                               ║
║  LAYER 7: OS-Level Kiosk (Safe Exam Browser)                   ║
║  ──────────────────────────────────────────────────────────    ║
║  Locks the entire device into exam mode at operating system    ║
║  level. Student cannot access any other application.           ║
║                                                               ║
║  LAYER 6: Browser Lockdown                                     ║
║  ──────────────────────────────────────────────────────────    ║
║  Forces fullscreen. Blocks F12, right-click, Ctrl+U, Alt+F4.  ║
║  Prevents code inspection, printing, or page source access.    ║
║                                                               ║
║  LAYER 5: Tab/Blur Detection + Violation Counting              ║
║  ──────────────────────────────────────────────────────────    ║
║  Detects when student leaves the exam tab. Counts violations.  ║
║  Auto-submits at 5 violations. No warnings — instant action.   ║
║                                                               ║
║  LAYER 4: Live Webcam Monitoring                               ║
║  ──────────────────────────────────────────────────────────    ║
║  Periodic snapshot captures saved for audit. Admin can view    ║
║  any student's camera feed in real-time via WebRTC streaming.  ║
║                                                               ║
║  LAYER 3: Device Authorization                                 ║
║  ──────────────────────────────────────────────────────────    ║
║  Every device must be pre-approved by administrator. Unknown   ║
║  IPs are blocked or queued for manual approval.                ║
║                                                               ║
║  LAYER 2: Session Security                                     ║
║  ──────────────────────────────────────────────────────────    ║
║  Role-based authentication (admin/examiner/student). Session   ║
║  regeneration on login. Multi-device detection prevents        ║
║  shared account sharing.                                       ║
║                                                               ║
║  LAYER 1: Transport & Data Security                            ║
║  ──────────────────────────────────────────────────────────    ║
║  HTTPS encryption. PDO prepared statements prevent SQL         ║
║  injection. MySQL triggers log every data change for audit.    ║
╚═══════════════════════════════════════════════════════════════╝
```

## Administrator Remote Controls

From the Live Proctoring Center, administrators can:

- **View** every active student's status and webcam feed
- **Warn** individual students with pop-up messages
- **Tag violations** manually for suspicious behavior
- **Force submit** any individual student's exam
- **Bulk force submit** all active attempts for an exam
- **Remote bypass** — grant security overrides when needed

---

# 7b. ARTIFICIAL INTELLIGENCE — CAPABILITIES & UPGRADE PATH

## Current State

The platform currently employs **rule-based automation** (event counters, keyword matching, threshold triggers) for violation detection. These are **reliable, predictable, and proven** — but are not AI/ML.

## What's Possible: AI Upgrade Packages

The system architecture is designed to accept AI input. All violations detected by AI models feed directly into the existing proctoring pipeline (violation table → admin alerts → auto-lock). No infrastructure changes are needed.

### Package A: Basic AI Proctoring (₦400,000–₦800,000 — 1-2 weeks)

| Feature | Technology | What It Detects |
|---------|-----------|-----------------|
| **Face Presence Detection** | TensorFlow.js (browser) | Whether a student is at the camera |
| **Multiple Face Alert** | TensorFlow.js (browser) | When another person enters the frame |
| **Object Detection** | TensorFlow.js (browser) | Phones, books, earbuds visible in webcam |

All processing runs in the student's browser — **no server GPU required**. Results are posted to the existing violation system.

### Package B: Advanced AI Proctoring (₦900,000–₦1,800,000 — 3-5 weeks)

Everything in Package A, plus:

| Feature | Technology | What It Detects |
|---------|-----------|-----------------|
| **Gaze/Attention Tracking** | MediaPipe Face Mesh | Student looking away from screen |
| **Facial Identity Verification** | Face descriptor matching | Confirms test-taker matches enrollment photo |
| **Liveness Detection** | Blink & head movement challenge | Prevents photo/video spoofing |
| **Answer Pattern Analysis** | Statistical outlier detection | Identifies coordinated cheating across students |

### Package C: Full AI Suite (₦2,000,000+ — 5-8 weeks)

Packages A + B, plus:
- **LLM-Powered Chatbot** — replaces keyword system with natural language support
- **Behavioral Biometrics** — keystroke dynamics and mouse movement analysis
- **Real-Time Voice Detection** — alerts when conversation is detected during exam
- **Post-Exam Forensic Reports** — per-student AI analysis report with flagged incidents

## Recommendation

For most institutions, **Package A (Basic AI Proctoring)** provides the highest value-to-cost ratio. The face detection and multiple-person alerts address 80% of exam malpractice concerns. The upgrade can be implemented at any time after initial deployment.

---

# 8. ROI & COST-BENEFIT ANALYSIS

## 8.1 Annual Cost Comparison

| Cost Category | Paper-Based System | NextGen Portal | Annual Savings |
|---------------|-------------------|----------------|----------------|
| **Printing** (question papers, answer booklets) | ₦500,000–₦1,500,000 | ₦0 | ₦500,000–₦1,500,000 |
| **Transport & logistics** (exam materials distribution) | ₦300,000–₦800,000 | ₦0 | ₦300,000–₦800,000 |
| **Storage** (secure archive of scripts) | ₦200,000–₦500,000 | ₦0 | ₦200,000–₦500,000 |
| **Manual grading labor** (academic staff overtime) | ₦1,000,000–₦3,000,000 | ₦100,000 (verification only) | ₦900,000–₦2,900,000 |
| **Data entry** (transcribing scores) | ₦300,000–₦700,000 | ₦0 (automatic) | ₦300,000–₦700,000 |
| **Result verification & reconciliation** | ₦200,000–₦500,000 | ₦50,000 | ₦150,000–₦450,000 |
| **TOTAL ESTIMATED ANNUAL SAVINGS** | | | **₦2,350,000–₦6,850,000** |

## 8.2 Intangible Benefits

| Benefit | Value |
|---------|-------|
| **Faster result publication** | From 4–8 weeks to 1–7 days |
| **Enhanced institutional reputation** | Priceless |
| **Accreditation compliance** | Audit trail satisfies NUC requirements |
| **Student satisfaction** | Instant feedback, flexible scheduling |
| **Faculty productivity** | Hundreds of hours saved per semester |
| **Exam capacity** | Unlimited concurrent examinees |

## 8.3 Break-Even Analysis

Based on conservative savings estimates, the platform **pays for itself within the first examination semester** of full deployment.

---

# 9. IMPLEMENTATION PLAN

## 9.1 Deployment Timeline

```
WEEK 1                WEEK 2                WEEK 3                WEEK 4
══════════════════════════════════════════════════════════════════════════════
  ████████████████    ████████████████    ████████████████    ████████████████
  SERVER SETUP        DATA MIGRATION      PILOT EXAM           FULL GO-LIVE
  ─────────────       ─────────────       ─────────────        ─────────────
  • Install LAMP      • Import student    • Configure test     • Deploy all
  • Configure SSL       records             exam with           remaining
  • Deploy portal     • Create question     sample cohort       exams
  • Database setup      banks             • Faculty training   • Go live with
  • Security config   • Configure exams   • Dry-run              full semester
                      • Set up admins/    • Proctoring                       end exams
                        examiners           calibration
```

## 9.2 Training Plan

| Group | Training Content | Duration | Format |
|-------|-----------------|----------|--------|
| **System Administrators** (ICT) | Server management, user administration, exam configuration, security settings, backup/restore | 1 day | Hands-on workshop |
| **Examiners** (Academic staff) | Assessing students, using rubrics, navigating the examiner portal, search and grading workflow | Half day | Practical session |
| **Students** (during onboarding) | Login procedure, exam interface, proctoring requirements, troubleshooting common issues | 30 min | Video tutorial + handout |
| **Proctors/Invigilators** | Live monitoring center, violation management, remote controls, escalation procedures | Half day | Simulated session |

---

# 10. TECHNICAL ARCHITECTURE OVERVIEW

## 10.1 Technology Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    TECHNOLOGY STACK                              │
├─────────────────────────────────────────────────────────────────┤
│  FRONTEND                                                        │
│  ├── Bootstrap 5.3 (responsive UI framework)                     │
│  ├── Vanilla JavaScript (exam lockdown, proctoring)              │
│  ├── WebRTC (real-time video streaming)                          │
│  ├── Chart.js (analytics visualizations)                         │
│  └── SweetAlert2 (user confirmation dialogs)                     │
├─────────────────────────────────────────────────────────────────┤
│  BACKEND                                                         │
│  ├── PHP 8.x (core application logic)                            │
│  ├── PDO (database abstraction with prepared statements)         │
│  └── Apache mod_rewrite (clean URL routing)                      │
├─────────────────────────────────────────────────────────────────┤
│  DATABASE                                                        │
│  ├── MySQL/MariaDB 8.0+                                          │
│  ├── 15+ tables with foreign key constraints                     │
│  ├── MySQL triggers for immutable audit trail                    │
│  └── Composite indexes for query performance                     │
├─────────────────────────────────────────────────────────────────┤
│  INFRASTRUCTURE                                                  │
│  ├── On-premise deployment (full data sovereignty)               │
│  ├── HTTPS (mandatory for WebRTC camera access)                  │
│  └── No external API dependencies (self-contained)               │
└─────────────────────────────────────────────────────────────────┘
```

## 10.2 Minimum Server Requirements

| Component | Requirement |
|-----------|-------------|
| **Web Server** | Apache 2.4+ with mod_rewrite |
| **PHP** | 7.4 or higher (8.0+ recommended) |
| **MySQL** | 5.7 or higher (8.0 recommended) |
| **RAM** | 256 MB minimum (512 MB recommended) |
| **Storage** | 100 MB for application + database + snapshots |
| **SSL Certificate** | Required for webcam/proctoring features |
| **Network** | LAN or internet connectivity to all student devices |

---

# 11. SUPPORT & MAINTENANCE

## 11.1 Included Support

| Service | Coverage |
|---------|----------|
| **Initial deployment** | Full setup, configuration, and go-live support |
| **Faculty training** | Hands-on training for administrators and examiners |
| **Documentation** | User guides for all roles (admin, examiner, student) |
| **Technical support** | WhatsApp direct line to developer during business hours |
| **Bug fixes** | Critical issues addressed within 24 hours |
| **Updates** | Security patches and minor updates included |

## 11.2 Optional Extended Support

| Service | Description |
|---------|-------------|
| **On-site support** | Physical presence during first major examination |
| **Custom development** | Additional features specific to Veritas needs |
| **Server monitoring** | 24/7 uptime monitoring and alerting |
| **Data analytics** | Custom reports and dashboards |
| **Integration** | Connect with existing portals or student information systems |

---

# 12. PRICING & INVESTMENT

## Investment Summary

The NextGen Assessment Portal is offered as a **complete solution** with no recurring licensing fees, no per-student charges, and no third-party plugin costs.

| Item | Investment |
|------|------------|
| **Platform License** (perpetual, institution-wide) | Contact for pricing |
| **Deployment & Configuration** | Contact for pricing |
| **Faculty & Staff Training** | Contact for pricing |
| **First Year Support & Updates** | Contact for pricing |
| **Optional Extended Support (Annual)** | Contact for pricing |

### What's Included

- ✅ Full platform with all features (MCQ, Practical, VIVA, Proctoring, Analytics)
- ✅ On-premise deployment on Veritas infrastructure
- ✅ Source code access (full ownership of custom instance)
- ✅ No per-student or per-exam fees
- ✅ No recurring license requirement
- ✅ All future updates during support period

### Payment Terms

- 50% upon contract signing
- 50% upon successful deployment and acceptance testing

---

# 13. NEXT STEPS

## How to Proceed

```
STEP 1                          STEP 2                          STEP 3
──────────                      ──────────                      ──────────
SCHEDULE DEMO                   PILOT DEPLOYMENT                FULL DEPLOYMENT
We present a live               We deploy on Veritas            Full rollout across
walkthrough of the              infrastructure with a           all departments and
platform to your                sample cohort for a             examination periods
decision-making team            test examination cycle          with ongoing support
                                (2-week evaluation)
                                
▸ Duration: 1 hour             ▸ Duration: 2 weeks             ▸ Duration: Ongoing
▸ Format: Online or in-person  ▸ Zero cost obligation           ▸ Phased by faculty
▸ Outcome: Decision to pilot   ▸ Outcome: Adoption decision     ▸ Outcome: Full adoption
```

## Immediate Action Items

1. **Schedule a live demonstration** with your ICT and Academic Affairs teams
2. **Identify a pilot department** (e.g., Nursing Sciences with existing rubrics)
3. **Allocate server resources** — a standard departmental server or high-spec desktop is sufficient
4. **Provide sample data** — student roster and one exam question bank for pilot configuration

---

# 14. APPENDIX: SYSTEM SHOWCASE

## A. Admin Dashboard

Central command center showing real-time statistics — student counts, active exams, question banks — with quick-action buttons for all management functions.

## B. Live Proctoring Center

Grid/gallery view of all active exam sessions with real-time status indicators. Administrators can view webcam feeds, send warnings, and force-submit exams from this single screen.

## C. Examiner Assessment Portal

Rubric-based scoring interface with radio-group selectors, student search with autocomplete, and revision tracking. Supports fractional scoring (½, ¼, ⅛) for medical/nursing rubrics.

## D. Student Exam Interface

Kiosk-mode examination environment with timer countdown, question navigation, and proctoring status indicator. Fullscreen enforcement blocks all exit routes except the Power Off button.

## E. Analytics Dashboard

Performance metrics including top students, exam averages, score distributions, and exportable reports. Data from both practical and objective exams aggregated in one view.

## F. Audit Trail

Immutable record of every action — user logins, data changes, exam submissions — with IP address, user agent, and timestamp. Tamper-proof through MySQL triggers.

---

## Contact Information

**Developer / Solution Architect:**  
BMXcode Initiator  
📱 WhatsApp: [+2348168438930](https://wa.me/+2348168438930)  
📧 veritas@bmxcode.dev (via WhatsApp contact)

**Demo Access:**  
🌐 `https://[institution-server]/exam_portal/`  
👤 Admin: `admin` / `admin@1`  
👤 Examiner: `examiner1` / `examiner123`

---

## References

The system has been successfully deployed and tested in academic environments with:

- Multi-cohort practical examinations (Nursing Sciences)
- Objective MCQ assessments with randomized question pools  
- VIVA/oral examination scoring with multi-examiner workflows
- Concurrent proctoring sessions via WebRTC live streaming

---

*"Empowering education through secure technological landscapes."*  
**— NextGen Assessment Portal**

---

*This proposal is valid for 90 days from the date of presentation. Pricing and terms subject to revision after expiration.*
