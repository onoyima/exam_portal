# Exam, Analytics & AI Features — Comprehensive Capability Overview

> **Project:** Veritas University Exam Portal  
> **Document Type:** Management Proposal  
> **Prepared:** July 2026

---

## 1. Executive Summary

The Veritas University Exam Portal is a **dual-system architecture** comprising a battle-tested legacy exam delivery platform (PHP/MySQL) and a modern Learning Management System (LMS) with a React/TypeScript frontend. Together, they deliver a **fully integrated, multi-layered assessment ecosystem** supporting objective, practical, and viva examinations with **enterprise-grade proctoring**, **real-time analytics**, **AI-powered fraud detection**, and a **weighted gradebook engine**.

The system currently serves the university's examination needs with zero third-party recurring costs for AI inference — all machine learning runs **entirely client-side** in the student's browser.

---

## 2. Exam Management System

### 2.1 Exam Types Supported

| Type | Description | Grading |
|------|-------------|---------|
| **Objective (MCQ)** | Multiple-choice with A/B/C/D options, optional image support | Fully auto-graded |
| **Practical** | Open-ended questions with manual scoring | Assessor-graded via examiner panel |
| **VIVA** | Oral exam management with single-question workflow | Assessor-graded via examiner panel |

### 2.2 Question Bank Management

- **Add / Edit / Delete Questions** per exam type
- **CSV Bulk Import & Export** — upload hundreds of questions at once
- **Question Images** — attach images to MCQ options
- **Question Ordering** — configurable display order
- **CSV Template Downloads** — pre-formatted templates for offline question preparation
- **8 Question Types in LMS** — MCQ, Multiple Answer, True/False, Short Answer, Essay, Matching, Ordering, Fill-in-the-Blank

### 2.3 Student Assignment & Bulk Import

| Method | Description |
|--------|-------------|
| **Manual Selection** | Pick individual students from a list |
| **CSV Bulk Upload** | Upload a spreadsheet of matric numbers |
| **Matric Prefix Match** | Assign all students whose matric number starts with a given prefix |
| **Email Domain Match** | Assign by email domain |
| **Assign All Students** | One-click assignment of every registered student |
| **School Portal Import** | Direct import from Veritas School Portal (Exeat2) — filter by department, level, course of study |
| **Bulk Enrollment Sync** | Bidirectional sync between LMS classes and exam portal enrollments |

### 2.4 Exam Configuration Options

Per-exam settings available to administrators:

- **Scheduling** — Start time, end time, timezone-aware access control
- **Duration** — Fixed time limit per attempt
- **Max Attempts** — Configurable number of allowed attempts per student
- **Grading Method** — Highest score or Average score across attempts
- **Questions to Show** — Control how many questions appear per attempt
- **Shuffle Questions** — Randomize question order per student
- **Shuffle Options** — Randomize option order within MCQ questions
- **Student Score Visibility** — Allow students to see their results immediately
- **Review Mode** — Allow students to review their answers after submission
- **Passing Score** — Set minimum percentage to pass
- **Availability Window** — Date range when the exam is accessible
- **Assignment Weights** — Configure grade weight in the gradebook

---

## 3. Exam Delivery & Student Experience

### 3.1 Student Dashboard

- Lists all **active assigned exams** with status badges
- Shows **attempts remaining** per exam
- **Locked status indicators** for attempts that exhausted violations
- **Auto-finalization** of expired attempts on dashboard load
- **View Results** link for completed attempts

### 3.2 Objective Exam Interface (1,495-line implementation)

Comprehensive exam-taking experience:

- **Question Navigation** — Sidebar question palette showing answered/unanswered/flagged status
- **Countdown Timer** — Real-time countdown with automatic submission on expiry
- **Auto-Save** — Periodic save of in-progress answers to prevent data loss
- **Final Submission** — Two-step confirmation with immediate score calculation
- **Review Mode** — Post-exam review showing selected answers and correct answers
- **Progress Tracking** — Live answered/question count

### 3.3 Examiner Assessment Panel (~900 lines)

- Dedicated interface for practical/viva exam grading
- **AJAX instant save** — scores saved per question without page reload
- **Auto-advance** — automatically moves to next student after scoring
- **Per-question score entry** with running total
- **View Results** — see all scored attempts for practical/viva exams

---

## 4. Multi-Layered Anti-Cheating & Proctoring System

The platform employs a **defense-in-depth** approach with six independent layers of security:

### 4.1 AI-Powered Face Detection (Layer 1)

**Implementation:** `assets/js/ai_proctor.js` (298 lines)  
**Technology:** TensorFlow.js 4.15 + BlazeFace 0.1.0

- Runs **100% client-side** in the student's browser — zero server GPU cost
- Detects **FACE_NOT_VISIBLE** — no face in webcam frame
- Detects **MULTIPLE_FACES** — potential accomplice in frame
- Configurable: detection interval (4s), violation cooldown (18s), face confidence threshold (0.72)
- **Audible alarm system** via Web Audio API — three distinct tone patterns (no face, multiple faces, strike)
- **On-screen toast alerts** with colour-coded severity (red, purple, amber)
- Integrates with the proctor ping violation pipeline
- Auto-starts on exam load when webcam proctoring is enabled

### 4.2 Proctor Ping Heartbeat System (Layer 2)

**Implementation:** `proctor_ping.php` (156 lines)

- Periodic heartbeat from student client to server
- **Webcam snapshot** capture and server-side storage at configurable intervals
- **Violation logging** with type, details, and timestamp
- **Auto-lock after 5 violations** — permanent exam interface lockdown
- **Session integrity checks** — detects concurrent logins on other devices
- **Admin warning delivery** — real-time popup messages to the student
- **Remote command execution** — admin can force-submit, lock, or warn students remotely

### 4.3 WebRTC Live Proctoring (Layer 3)

**Implementation:** `admin_live_proctor.php` (1,084 lines), `webrtc_api.php`

- **Real-time video streaming** from student webcam to admin dashboard
- **Bidirectional signaling** — admin requests live stream, student auto-accepts
- **Live monitoring dashboard** showing all active attempts with:
  - Webcam thumbnails
  - Violation counts and history
  - Active time tracking
- **Intervention controls**: send warnings, tag violations, force submit, download recordings
- **Recording system** — WebM chunked upload of proctoring sessions

### 4.4 Browser Lockdown & Security (Layer 4)

**Implementation:** `assets/js/student_lockdown.js`, inline in `student_objective_exam.php`

- **Fullscreen enforcement** — exam auto-enters fullscreen; leaving triggers violation
- **Tab switch detection** — alt-tab or window blur triggers violation
- **Keyboard shortcut blocking** — blocks F12, Ctrl+U/P/S, Ctrl+Shift+I/J/C, Escape, Alt+F4
- **Context menu disabled** — blocks right-click
- **Network connectivity monitor** — detects disconnection and warns the student
- **Self-healing anti-hang logic** — periodic health checks

### 4.5 Device Authorization (Layer 5)

**Implementation:** `admin_devices.php`, `auth.php` (check_device_auth)

- **IP-based device registration** — first access auto-registers device as "pending"
- **Admin approval workflow** — approve or reject student devices
- **Status-based access control** — unapproved devices are blocked from starting exams

### 4.6 Safe Exam Browser Integration (Layer 6)

**Implementation:** `modules/secure_gate/`

- **SEB config file generation** (`.seb` files) — turns the exam machine into a dedicated kiosk
- Prevents all unauthorised applications and system access during the exam

---

## 5. Assessment & Grading Engine

### 5.1 Auto-Grading (Objective Exams)

- **Immediate scoring** on submission — compares selected options against correct_answer
- **Partial scoring support** — configurable per-question mark values
- **Multi-attempt grading** — supports highest-score and average-score methods
- **Score persistence** — stored in `objective_attempts` with timestamps

### 5.2 Examiner Assessment (Practical/Viva)

- **Manual scoring** per question via the assessor panel
- **Running total** and automatic aggregation
- **AJAX saves** — no page reload required between student assessments
- **Results viewer** — historical results with per-question breakdown

### 5.3 Weighted Gradebook (LMS)

**Implementation:** `GradebookService.php` (184 lines)

| Feature | Description |
|---------|-------------|
| **Grade Items** | Assignments, quizzes, participation, manual, final exam — with individual weights |
| **Weighted Calculation** | Percentage-based weighted scoring across all grade items |
| **Letter Grades** | A (70+), B (60-69), C (50-59), D (45-49), E (40-44), F (<40) |
| **GPA Computation** | 4.00 scale (A=4.0, B=3.5, C=3.0, D=2.5, E=2.0, F=0.0) |
| **Course-wide Recalculation** | One-click recalculation for all enrolled students |
| **Event Integration** | Fires `grade.calculated` event on completion — extensible for notifications, reports |
| **Progress Tracking** | Course completion percentage per student, lesson-level tracking |

### 5.4 Result & Score Delivery

- **Student result view** — immediate score display after submission (configurable)
- **Review mode** — question-by-question review with correct answers
- **Downloadable CSV reports** — per-exam reports with per-question breakdowns
- **All-students score view** — consolidated view across all exams
- **Per-student score viewer** — drill down into individual student results

---

## 6. Analytics & Reporting

### 6.1 Admin Dashboard Analytics (`admin_dashboard.php`)

| Metric | Description |
|--------|-------------|
| **Total Students** | Active student count |
| **Total Exams** | All created exams |
| **Active Exams** | Currently active exams |
| **Exam Type Breakdown** | Practical / Objective / Viva counts |
| **Total Questions** | Combined question bank size |
| **Staff Accounts** | Registered examiners |
| **Assessments Completed** | Total submissions across all exam types |
| **Month-over-Month Trends** | Student growth, exam creation, assessment completion — with percentage change indicators |
| **Weekly Activity Chart** | Daily assessment counts for the past 7 days (Chart.js bar chart) |
| **Monthly Trends** | 6-month assessment trend (Chart.js line chart) |
| **Recent Activity Feed** | Latest 8 student submissions with exam/student details |

### 6.2 Analytics Dashboard (`admin_analytics.php` — 368 lines)

| Feature | Description |
|---------|-------------|
| **Top 10 Students** | Leaderboard by total aggregate score across all exams |
| **Exam Performance Table** | Per-exam metrics: students assessed, average/max/min score |
| **Score Distribution** | Doughnut chart: Excellent (40+), Good (30-39), Average (20-29), Below Average (10-19), Poor (<10) |
| **7-Day Activity** | Line chart of daily assessment completions |
| **Quick Stats** | Total assessments, average score, students with scores, exams with data |

### 6.3 LMS Analytics Module

**REST API endpoints** in `AnalyticsController.php`:

| Endpoint | Data |
|----------|------|
| **Course Stats** | Enrollments (total/active/completed), completion rate, lesson completions, recent activity per course |
| **Platform Stats** | Total/published courses, enrollments, activity with period filter (7d/30d/90d/1y) |
| **User Progress** | Per-student: course progress %, grade, letter grade, earned/total points across all enrolled courses |
| **Instructor Stats** | Instructor's course list with student counts, completion rates, assignment/quiz counts |
| **Engagement Metrics** | Daily engagement breakdown over 30 days by activity type |

### 6.4 Event Tracking (`AnalyticsService.php` — 220 lines)

Generic analytics event system:

```php
AnalyticsService::trackEvent('quiz.completed', $userId, $courseId, 'quiz', $quizId, [...]);
```

- Stores events in `lms_analytics_events` table with user, course, entity type/ID, and JSON payload
- Used for all domain events: `quiz.started`, `quiz.completed`, `grade.calculated`, `exam.synced`, `exam.sso_redirect`
- Logger integration for debugging and audit trails

### 6.5 Audit & Activity Logging

| Log Type | Description |
|----------|-------------|
| **System Audit Trail** | All admin actions across the portal with pagination and detail modals |
| **Exam Audit** | Per-exam attempt logs: student info, IP addresses, timestamps, login history, violation count, time taken |
| **Session Reports** | Per-academic-session exam reports with student scores |
| **Activity Logs** | LMS activity: lesson views, quiz attempts, submissions, grade changes |
| **Lesson Progress** | Per-lesson status (not_started, in_progress, completed) with auto-grading on completion |
| **Course Views** | Page view tracking for course content |

### 6.6 Data Export

- **CSV Export** — exam reports with per-question breakdowns
- **CSV Export** — session reports with all student scores
- **CSV Export/Import** — question bank templates
- **CSV Import** — bulk student enrollment

---

## 7. AI & Smart Features

### 7.1 AI Proctoring (Production — Active)

**Technology Stack:** TensorFlow.js 4.15 + BlazeFace 0.1.0

**Architecture:** Entirely client-side inference in the student's browser.

**Detection Capabilities:**

| Detection | Description | Action |
|-----------|-------------|--------|
| `FACE_NOT_VISIBLE` | No face detected in webcam frame | Alarm + toast + server violation log |
| `MULTIPLE_FACES` | More than one face in frame | Alarm + toast + server violation log |

**Technical Specifications:**

- Face confidence threshold: **0.72** (configurable)
- Detection interval: **4 seconds**
- Violation cooldown: **18 seconds** (prevents alert spam)
- Startup delay: **5 seconds** after exam load
- Model: BlazeFace (lightweight, optimised for mobile and desktop)
- Audio: Web Audio API — 3 distinct alarm patterns (no face, multiple faces, strike)
- Integration: hooks into existing `proctor_ping` violation pipeline
- No server-side ML infrastructure required — zero recurring cost

### 7.2 AI Feature Flag (Config — Ready)

Configuration in `lms/.env`:

```env
LMS_ENABLE_AI_FEATURES=false
```

A global toggle allowing the institution to enable/disable all AI features from a single point. Ready for activation when the institution is prepared.

### 7.3 Predictive Analytics Flag (Planned — Schema Ready)

Configuration in `lms/config/lms.php`:

```php
'predictive' => false,
```

Flagged for a future ML pipeline that would analyse historical student performance data to:
- Predict at-risk students before exams
- Recommend personalised study materials
- Identify patterns in academic performance

### 7.4 Plagiarism Detection (Schema Ready)

The `lms_submissions` database table includes a dedicated field:

```sql
plagiarism_score DECIMAL(5,2) NULL
```

- Schema-ready for integration with plagiarism detection services
- Can be connected to existing tools (Turnitin, Copyscape, etc.) or a custom solution
- Supports scores from 0.00 to 99.99 (percentage of plagiarised content)

### 7.5 Future AI Pipeline Opportunities

Based on existing data collection and infrastructure:

1. **Adaptive Testing** — Use historical performance data to dynamically adjust question difficulty
2. **Cheating Pattern Detection** — Analyse answer patterns across cohorts to identify potential academic dishonesty
3. **Automated Essay Scoring** — NLP-based evaluation of essay/short answer responses
4. **Performance Prediction** — Predict final grades based on quiz/course activity patterns
5. **Smart Scheduling** — Recommend optimal exam schedules based on student performance trends
6. **Anomaly Detection** — Flag unusual answer patterns, timing anomalies, or proctoring deviations
7. **Personalised Remediation** — Recommend specific course materials based on exam weak points

**Data Already Available for AI/ML:**
- Historical exam scores across multiple attempts
- Per-question answer data with timestamps
- Proctoring violation logs with types and frequencies
- Student engagement metrics (lesson views, time spent)
- Gradebook data with weighted scores
- Activity logs with action types and frequencies

---

## 8. Integration & Architecture

### 8.1 Two-System Architecture

```
┌──────────────────────────────────────────────────┐
│              Legacy Exam Portal                   │
│  (PHP Native / MySQL / Bootstrap 5 / Chart.js)    │
│                                                   │
│  • Exam CRUD & Assignment   • Question Bank       │
│  • Proctoring Pipeline      • Score Calculation   │
│  • Live Proctoring          • Admin Analytics     │
│  • Student Exam Interface   • AI Proctor          │
└────────────────┬─────────────────────────────────┘
                 │
    ┌────────────┴────────────┐
    │   Exam Bridge API       │
    │   JWT SSO + REST Sync   │
    └────────────┬────────────┘
                 │
┌────────────────┴─────────────────────────────────┐
│              LMS Module                           │
│  (PHP MVC / React+TypeScript / Ant Design)        │
│                                                   │
│  • Course Management      • Quiz System           │
│  • Gradebook              • Analytics             │
│  • Assignments            • Class Management      │
│  • Lesson Progress        • Event Tracking        │
└──────────────────────────────────────────────────┘
```

### 8.2 Exam Bridge (LMS ↔ Legacy Portal)

**Implementation:** `ExamBridgeController.php`, `ExamBridgeService.php`, `PortalSyncService.php`

| Operation | Direction | Description |
|-----------|-----------|-------------|
| **Link Exam** | LMS → Portal | Associate an LMS quiz with a legacy portal exam |
| **Unlink** | Bidirectional | Disconnect a linked exam |
| **Push Grades** | LMS → Portal | Sync gradebook scores to legacy exam records |
| **Push Enrollments** | LMS → Portal | Sync class enrollments to exam assignments |
| **Pull Results** | Portal → LMS | Retrieve exam results into the LMS gradebook |
| **SSO Redirect** | LMS → Portal | JWT-based single sign-on: students click once to access exams |

### 8.3 SSO & Authentication

- **JWT-based SSO** between LMS and exam portal (HS256 signed tokens)
- **Device-aware session management** — detects concurrent logins
- **Role-based access** — Admin, Examiner, Student with distinct dashboards
- **CSRF protection** on all forms
- **Session timeout** with auto-logout

### 8.4 Event-Driven Architecture

The LMS uses a domain event system for extensibility:

| Event | Triggered When |
|-------|----------------|
| `quiz.started` | Student begins a quiz |
| `quiz.completed` | Student submits a quiz |
| `grade.calculated` | Final grade computed for a student/course |
| `exam.synced` | Data synced between LMS and exam portal |
| `exam.sso_redirect` | Student redirected via SSO |

### 8.5 Infrastructure

| Component | Implementation | Purpose |
|-----------|---------------|---------|
| **Redis** | Multi-DB (cache, sessions, queues) | Caching, timer management, session storage |
| **RabbitMQ** | Event bus | Async job processing, queue management |
| **Sentry** | Error tracking | Production error monitoring |
| **OpenTelemetry** | Tracing | Distributed system observability |
| **Supervisor** | Process manager | Queue worker management |
| **Docker** | Containerisation | Development & production deployment |
| **Kubernetes** | Orchestration | Production scaling (k8s/ directory) |
| **S3/MinIO** | File storage | Media file storage |
| **SMTP** | Email service | Notifications via Veritas mail server |

---

## 9. Technical Specifications

### 9.1 Database Schema (Exam-Related)

| Table | Purpose |
|-------|---------|
| `exams` | Core exam definitions with type, scheduling, proctoring config |
| `exam_students` | Many-to-many student-exam assignments |
| `exam_examiners` | Many-to-many examiner-exam assignments |
| `questions` | Practical/viva questions with text and score values |
| `exam_questions` | Many-to-many exam-question associations |
| `objective_questions` | MCQ questions with 4 options, images, correct answer |
| `objective_attempts` | Exam attempts with score, proctor status, violation count, snapshots |
| `objective_answers` | Per-question selected options |
| `answers` | Practical/viva exam answers with scores |
| `exam_violations` | Proctoring violation log |
| `students` | Student records with login tracking |
| `examiners` | Staff/examiner accounts |
| `approved_devices` | Device authorisation |
| `audit_trail` | System audit log |
| `login_history` | Login tracking |
| `webrtc_signals` | WebRTC signaling messages |
| `system_settings` | Key-value configuration store |

### 9.2 LMS Database Schema (Exam-Related)

| Table | Purpose |
|-------|---------|
| `lms_quizzes` | Quiz definitions with type, timing, availability |
| `lms_quiz_questions` | 8 question types with JSON options and points |
| `lms_quiz_attempts` | Per-user attempts with scores and metadata |
| `lms_quiz_answers` | Per-question answers with scoring |
| `lms_assignments` | Assignment definitions |
| `lms_submissions` | Submissions with plagiarism_score field |
| `lms_grade_items` | Grade items with weights and categories |
| `lms_gradebook` | Per-student grade entries |
| `lms_activity_logs` | User activity tracking |
| `lms_lesson_progress` | Per-lesson student progress |
| `lms_course_views` | Course view tracking |
| `lms_analytics_events` | Extensible analytics event store |
| `lms_exam_links` | Bridge between LMS quizzes and portal exams |
| `lms_exam_sync_log` | Sync operation history |

### 9.3 Tech Stack Summary

| Layer | Technology |
|-------|-----------|
| Legacy Backend | PHP, MySQL (PDO) |
| LMS Backend | PHP (Custom MVC), MySQL |
| LMS API Auth | JWT (firebase/php-jwt v7) |
| LMS Frontend | React 18, TypeScript, Ant Design 5, Vite |
| Legacy Frontend | HTML, Bootstrap 5, vanilla JS |
| AI/ML | TensorFlow.js 4.15, BlazeFace 0.1.0 |
| Charts | Chart.js |
| Message Queue | RabbitMQ |
| Cache | Redis |
| Error Tracking | Sentry |
| Tracing | OpenTelemetry |
| Containers | Docker, Kubernetes |
| SSO | JWT-based custom |

---

## 10. Feature Summary Matrix

| Feature Area | Status |
|-------------|--------|
| **Exam Management (Practical/Viva/Objective)** | ✅ Production |
| **MCQ Auto-Grading** | ✅ Production |
| **Examiner Assessment Panel** | ✅ Production |
| **Bulk Student Import (CSV, Portal, Filters)** | ✅ Production |
| **Question Bank (CSV Import/Export)** | ✅ Production |
| **Weighted Gradebook (A-F / GPA)** | ✅ Production |
| **AI Face Detection Proctoring** | ✅ Production |
| **WebRTC Live Proctoring** | ✅ Production |
| **Browser Lockdown (Fullscreen, Keyboard)** | ✅ Production |
| **Device Authorization** | ✅ Production |
| **Safe Exam Browser Integration** | ✅ Production |
| **Admin Analytics Dashboard** | ✅ Production |
| **Exam Performance Charts** | ✅ Production |
| **Score Distribution Analytics** | ✅ Production |
| **Activity & Trend Tracking** | ✅ Production |
| **LMS ↔ Exam Portal Bridge** | ✅ Production |
| **Event-Driven Analytics** | ✅ Production |
| **CSV Reporting & Export** | ✅ Production |
| **JWT Single Sign-On** | ✅ Production |
| **Plagiarism Detection (Schema Ready)** | ⏳ Configure |
| **Predictive Analytics (Flagged)** | 🚧 Planned |
| **Adaptive Testing** | 💡 Future |
| **Automated Essay Scoring (NLP)** | 💡 Future |
| **Cheating Pattern Detection (ML)** | 💡 Future |

---

*Prepared for Veritas University Management — July 2026*
