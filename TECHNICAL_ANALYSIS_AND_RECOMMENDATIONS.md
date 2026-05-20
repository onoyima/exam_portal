# TECHNICAL APPENDIX & DUE DILIGENCE REPORT

## NextGen Assessment Portal — Platform Analysis for ICT Directorate

**Prepared for:** Veritas University ICT Directorate  
**Purpose:** Technical evaluation companion to the sales proposal  
**Date:** May 2026

---

## About This Document

This document provides the technical depth that university ICT teams need to evaluate the NextGen Assessment Portal. It covers architecture, security analysis, database design, deployment requirements, and recommended enhancements for production readiness.

---

# 1. PLATFORM ARCHITECTURE

## 1.1 High-Level Architecture

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                              STUDENT DEVICES                                 │
│                    (Desktop / Laptop / Tablet / iPad)                         │
└──────────────────────────┬───────────────────────────────────────────────┘
                           │
                           │ HTTPS (with SSL)
                           │
┌──────────────────────────▼───────────────────────────────────────────────┐
│                         APACHE / NGINX                                    │
│                    ┌─────────────────┐                                   │
│                    │  .htaccess URL   │                                   │
│                    │  Rewrite Engine  │                                   │
│                    │  /exam → .php    │                                   │
│                    └─────────────────┘                                   │
└──────────────────────────┬───────────────────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────────────────┐
│                         APPLICATION LAYER (PHP 8.x)                       │
│                                                                           │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│   │ AUTH SYSTEM   │  │ EXAM ENGINE  │  │ PROCTORING  │  │ CHAT & AI   │ │
│   │ ─────────    │  │ ──────────   │  │ ──────────  │  │ ─────────   │ │
│   │ • Session    │  │ • Objective  │  │ • WebRTC    │  │ • Staff Chat│ │
│   │ • Roles      │  │ • Practical  │  │ • Snapshots  │  │ • Chatbot   │ │
│   │ • Device Auth│  │ • VIVA       │  │ • Violations │  │ • WhatsApp  │ │
│   │ • Login Hist │  │ • Timer      │  │ • Remote Cmd │  │             │ │
│   └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘ │
│                                                                           │
│   ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                   │
│   │ ADMIN CTRL   │  │ ANALYTICS   │  │ AUDIT       │                   │
│   │ ─────────   │  │ ──────────  │  │ ─────────   │                   │
│   │ • CRUD ops  │  │ • Charts    │  │ • Triggers  │                   │
│   │ • Settings  │  │ • Reports   │  │ • Logs      │                   │
│   │ • Uploads   │  │ • Exports   │  │ • History   │                   │
│   └──────────────┘  └──────────────┘  └──────────────┘                   │
└──────────────────────────┬───────────────────────────────────────────────┘
                           │
                           │ PDO (Prepared Statements)
                           │
┌──────────────────────────▼───────────────────────────────────────────────┐
│                         DATABASE LAYER (MySQL 8.x)                        │
│                                                                           │
│   STUDENTS  EXAMINERS  EXAMS  QUESTIONS  ANSWERS  ATTEMPTS  VIOLATIONS   │
│   SETTINGS  SESSIONS   AUDIT  LOGIN_HIST  CHAT   WEBRTC_SIG  DEVICES     │
│                                                                           │
│   • Foreign Keys  • Composite Indexes  • Triggers  • Stored Procedures   │
└──────────────────────────────────────────────────────────────────────────┘
```

## 1.2 Design Pattern

- **Pattern**: Monolithic PHP with inline HTML templates
- **Database Access**: Direct PDO (no ORM)
- **Session Management**: PHP native sessions
- **Routing**: Apache mod_rewrite (clean URLs)
- **State Management**: Session-based with GET/POST for page state

---

# 2. DATABASE SCHEMA ANALYSIS

## 2.1 Complete Entity Relationship Overview

### Core Academic Entities

```
students ──┬── exam_students ── exams
           │                      │
           ├── answers            ├── exam_questions ── questions
           │                      │
           ├── objective_answers  ├── exam_examiners ── examiners
           │                      │
           └── objective_attempts └── academic_sessions
                │
                └── exam_violations
                └── webrtc_signals
```

### Audit & Security Entities

```
examiners ──── chat_messages
examiners ──── login_history
examiners ──── export_history
system    ──── system_events
system    ──── audit_trail
students ──── approved_devices
```

### Configuration Entities

```
system_settings (key-value store)
seb_settings (Safe Exam Browser config)
academic_sessions (semester/term tracking)
```

## 2.2 Table Inventory

| # | Table | Rows (Sample) | Purpose | Key Indexes |
|---|-------|--------------|---------|-------------|
| 1 | `students` | 6 | Student accounts | matric_no (UQ), username (UQ) |
| 2 | `examiners` | 3 | Staff accounts (admin + examiner) | username (UQ) |
| 3 | `exams` | 2 | Exam definitions & config | id (PK) |
| 4 | `questions` | 35 | Practical question bank | id (PK) |
| 5 | `objective_questions` | ~ | MCQ bank per exam | exam_id (FK) |
| 6 | `answers` | 173+ | Practical exam scores | exam_id, student_id, question_id |
| 7 | `objective_answers` | ~ | MCQ student selections | exam_id, student_id, attempt_no, question_id (UQ) |
| 8 | `objective_attempts` | ~ | MCQ attempt tracking | exam_id, student_id |
| 9 | `exam_students` | 12 | Exam-student assignments | exam_id, student_id |
| 10 | `exam_examiners` | 4 | Exam-examiner assignments | exam_id, examiner_id |
| 11 | `exam_questions` | 35 | Exam-question linking | exam_id, question_id |
| 12 | `audit_trail` | 233+ | Audit log | user_id + action, created_at |
| 13 | `login_history` | 40+ | Login records | user_id + login_time |
| 14 | `chat_messages` | 4 | Staff messaging | sender_id, receiver_id |
| 15 | `system_chat_log` | 8 | AI chatbot history | id (PK) |
| 16 | `system_events` | 1 | System event log | event_type, severity |
| 17 | `export_history` | 0 | Data export records | user_id, export_type |
| 18 | `exam_violations` | ~ | Proctoring violations | attempt_id |
| 19 | `webrtc_signals` | ~ | WebRTC signaling | attempt_id |
| 20 | `approved_devices` | ~ | Device authorization | ip_address (UQ) |
| 21 | `academic_sessions` | ~ | Semester tracking | id (PK) |
| 22 | `system_settings` | ~ | Key-value config | setting_key (UQ) |
| 23 | `seb_settings` | ~ | SEB configuration | id (PK) |

## 2.3 Schema Strengths

- **Proper normalization**: Tables follow 3NF with clear separation of concerns
- **Foreign key constraints**: Referential integrity enforced at DB level with cascading deletes
- **Composite indexes**: Multi-column indexes for common query patterns (e.g., `idx_exam_student_question`)
- **Unique constraints**: Matric number and username uniqueness enforced
- **Triggers**: Automated audit logging on INSERT/UPDATE/DELETE for critical tables
- **JSON columns**: Structured audit data in `old_values`/`new_values` columns

---

# 3. SECURITY ANALYSIS

## 3.1 Authentication Mechanism

```
STUDENT LOGIN FLOW:
  1. Enter Matric Number
  2. AJAX lookup verifies student exists, returns name
  3. Identity confirmation modal shown
  4. On confirmation: session_regenerate_id() → session created
  5. Session tracked via current_session_id in students table
  6. Login recorded in login_history

STAFF LOGIN FLOW:
  1. Enter username + password
  2. Direct comparison: WHERE username = ? AND password = ?
  3. On success: session created, login history recorded
  4. Role checked for redirect (admin → dashboard, examiner → assess)
```

## 3.2 Authorization Model

```
Three roles with strict page-level gating:

  ADMIN ──── Full access to all admin_*.php pages
              Can manage students, staff, exams, questions, settings
              Can access live proctoring, audit trail, analytics
  
  EXAMINER ─ Access to assess.php (their assigned exams)
              Staff chat, results viewing
              Cannot access any admin_*.php pages
  
  STUDENT ─── Access to student_dashboard.php
              student_objective_exam.php (assigned exams only)
              Device authorization gate enabled
```

**Enforcement**: `check_auth()` function in `auth.php` called at the top of every protected page. Unauthorized access redirects to appropriate login page.

## 3.3 Security Audit Findings

| ID | Severity | Finding | Location | Impact | Recommendation |
|----|----------|---------|----------|--------|----------------|
| S-01 | **CRITICAL** | Passwords stored and compared in plaintext | `database.sql:301`, `login.php:25-26`, `update_database.sql:12` | Any user with DB access sees all passwords. SQL injection could dump credentials. | Implement `password_hash()`/`password_verify()`. Hash existing passwords via migration script. |
| S-02 | **CRITICAL** | Hardcoded DB credentials in secondary connection | `auth.php:18` | Credentials duplicated and hardcoded in source code. Any code disclosure exposes DB. | Use global `$db` connection or centralized config. Remove hardcoded credentials. |
| S-03 | **CRITICAL** | Database credentials tracked in git history | `db.php`, git history | Full DB access credentials exposed in repository. | Use `.env` file. Remove credentials from git history. |
| S-04 | **HIGH** | No CSRF tokens on any form | All POST handlers | Cross-Site Request Forgery possible. Attacker could trigger admin actions. | Generate and validate CSRF tokens on all state-changing POST requests. |
| S-05 | **HIGH** | Weak default student password | `update_database.sql:12` | Default password `12345` applied to all students. No password change requirement. | Enforce password policy. Implement first-login password change. |
| S-06 | **MEDIUM** | No rate limiting on login | `login.php:21-38`, `student_login.php` | Brute force attacks possible against both staff and student accounts. | Implement exponential backoff or CAPTCHA after 3 failed attempts. |
| S-07 | **MEDIUM** | No session timeout enforcement | `auth.php` | Sessions persist indefinitely (until browser close for students, no lifetime for staff). | Implement session lifetime (e.g., 30 min inactivity timeout). |
| S-08 | **LOW** | Direct `query()` calls bypass prepared statements | `admin_dashboard.php` (several COUNT queries) | While these are safe (no parameters), inconsistent pattern sets bad precedent. | Use prepared statements consistently even for static queries. |

## 3.4 Proctoring Security

| Component | Risk | Mitigation |
|-----------|------|------------|
| WebRTC streams | Interception | HTTPS-only transport. Signaling relayed via DB (not peer-exposed) |
| Snapshot capture | Storage exhaustion | 1 snapshot per ping cycle. Garbage collection recommended |
| Violation counting | False positives | 5-strike threshold prevents single accidental tab-switch from failing student |
| Device auth | IP spoofing | Combine with session cookie verification |
| Kiosk lockdown | JS disabled | Fullscreen enforcement also checked on server-side ping |

---

# 4. PERFORMANCE ANALYSIS

## 4.1 Current Performance Profile

| Operation | Current Performance | Bottleneck? |
|-----------|-------------------|-------------|
| Student login | < 100ms | No — simple lookup + session |
| Exam start (MCQ) | < 200ms | No |
| Answer save (MCQ) | < 50ms | No — uses ON DUPLICATE KEY UPDATE |
| Proctor ping | < 100ms | No |
| Live dashboard load | < 500ms (at small scale) | Yes — UNIONS on answers + attempts at scale |
| Analytics queries | < 1s (at small scale) | Yes — full table scans on answers |
| Snapshot save | < 50ms (filesystem) | Yes — FS metadata at 10K+ files |

## 4.2 Scalability Projections

| Metric | 100 Students | 500 Students | 2,000 Students |
|--------|-------------|-------------|----------------|
| **Concurrent exam sessions** | 100 | 500 | 2,000 |
| **Proctor pings/sec** | 5 | 25 | 100 |
| **Answer saves/sec** | 50 | 250 | 1,000 |
| **DB writes/sec (proctoring)** | 10 | 50 | 200 |
| **Snapshot storage/hour** | 360 files | 1,800 files | 7,200 files |

## 4.3 Recommended Optimizations for Scale

1. **Add database indexes** for proctor ping queries (exam_id, student_id, finished_at)
2. **Move WebRTC signaling** from database polling to Redis pub/sub
3. **Implement snapshot retention** — auto-delete snapshots 30 days post-exam
4. **Cache dashboard stats** — store counts in Redis/Memcached with periodic refresh
5. **Partition `answers` table** by exam_id for large-scale practical exams

---

# 5. DEPLOYMENT REQUIREMENTS

## 5.1 Server Specification

### Minimum

| Component | Spec |
|-----------|------|
| CPU | 2 cores @ 2.0 GHz |
| RAM | 256 MB |
| Storage | 10 GB |
| OS | Ubuntu 20.04+ / Windows Server 2019+ |
| Web Server | Apache 2.4 with mod_rewrite |
| PHP | 7.4 |
| MySQL | 5.7 |
| PHP Extensions | PDO, MySQLi, GD, mbstring, json |
| SSL | Let's Encrypt or any valid certificate |

### Recommended (500+ concurrent users)

| Component | Spec |
|-----------|------|
| CPU | 4 cores @ 2.5 GHz |
| RAM | 2 GB |
| Storage | 50 GB SSD |
| OS | Ubuntu 22.04 LTS |
| Web Server | Nginx + PHP-FPM |
| PHP | 8.1+ |
| MySQL | 8.0+ |
| PHP Extensions | PDO, MySQLi, GD, mbstring, json, redis |
| SSL | Paid wildcard certificate |
| CDN | Cloudflare (optional, for static assets) |

## 5.2 PHP Configuration

```ini
; php.ini recommendations
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 50M
upload_max_filesize = 50M
session.gc_maxlifetime = 14400   ; 4 hours
session.cookie_httponly = 1
session.cookie_secure = 1        ; when using HTTPS
session.use_strict_mode = 1
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

## 5.3 MySQL Configuration

```ini
; my.cnf recommendations
innodb_buffer_pool_size = 1G     ; set to 70% of available RAM
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 200
query_cache_type = 0             ; disable in MySQL 8.0
join_buffer_size = 4M
tmp_table_size = 64M
max_heap_table_size = 64M
```

## 5.4 Network Requirements

| Port | Protocol | Purpose | Source |
|------|----------|---------|--------|
| 80 | TCP | HTTP (redirect to HTTPS) | All users |
| 443 | TCP | HTTPS (application access) | All users |
| 3306 | TCP | MySQL (if remote) | Application server only |

---

# 6. ICT INTEGRATION CHECKLIST

## 6.1 Pre-Deployment Checklist

- [ ] **Server provisioned** with LAMP/LEMP stack
- [ ] **SSL certificate** installed and validated
- [ ] **Domain/subdomain** configured (e.g., `exam.veritas.edu.ng`)
- [ ] **MySQL database** created with user and permissions
- [ ] **PHP extensions** verified (PDO, GD, mbstring, json)
- [ ] **Upload directories** created and writable (`uploads/snapshots/`)
- [ ] **Firewall rules** configured (ports 80, 443)
- [ ] **Backup strategy** defined (daily mysqldump + file backup)
- [ ] **Monitoring** configured (uptime check, disk space alert)

## 6.2 Security Hardening Checklist

- [ ] **Disable directory listing** in Apache/Nginx
- [ ] **Restrict MySQL user** to localhost only
- [ ] **Move SQL dump files** out of webroot
- [ ] **Remove test CSV/TXT files** from public directory
- [ ] **Set proper file permissions** (644 for files, 755 for directories)
- [ ] **Enable fail2ban** for SSH protection
- [ ] **Configure PHP open_basedir** restriction
- [ ] **Set MySQL root password** (if not already done)
- [ ] **Enable AppArmor/SELinux** if on Ubuntu/CentOS

---

# 7. CODE QUALITY REPORT

## 7.1 Strengths

| Aspect | Assessment |
|--------|------------|
| **SQL Injection Prevention** | ✅ Prepared statements used in all data-modifying queries |
| **XSS Prevention** | ✅ `htmlspecialchars()` used on all dynamic output |
| **Database Design** | ✅ Well-normalized with foreign keys, indexes, triggers |
| **Responsive UI** | ✅ Bootstrap 5.3 with mobile-first approach |
| **Session Security** | ✅ Session regeneration on student login |
| **Error Handling** | ✅ Try-catch blocks in critical sections |
| **HTTPS Enforcement** | ✅ WebRTC requires secure context; .htaccess can enforce |

## 7.2 Areas for Improvement

| Aspect | Issue | Effort |
|--------|-------|--------|
| **Code Duplication** | SweetAlert injection block (~75 lines) duplicated in 20+ files | Low — extract to single JS file |
| **Password Storage** | Plaintext hashing missing entirely | Low-medium — hash + verify migration |
| **No Autoloading** | Every file manually requires dependencies | Medium — implement PSR-4 autoloader |
| **Inline CSS** | Mixed embedded styles and external stylesheets | Medium — consolidate to CSS file |
| **No Namespacing** | All classes/functions in global scope | Medium — add PHP namespaces |
| **Mixed Dependencies** | Bootstrap loaded both locally and from CDN | Low — standardize to local |
| **SQL Dumps in Root** | 30+ MB of SQL files in public webroot | Low — move outside webroot |
| **Test Data in Root** | CSV/TXT files with real student data | Low — remove from webroot |
| **No Unit Tests** | Zero test coverage | High — PHPUnit test suite needed |
| **No CI/CD** | No automated testing or deployment pipeline | Medium — GitHub Actions setup |

---

# 8. ENHANCEMENT ROADMAP

## Phase 1 — Immediate Security Hardening (Week 1)

| Task | Priority | Effort |
|------|----------|--------|
| Implement password hashing (bcrypt) | 🔴 Critical | 4 hours |
| Add CSRF protection to all forms | 🔴 Critical | 6 hours |
| Remove hardcoded DB credentials from auth.php | 🔴 Critical | 1 hour |
| Remove SQL/CSV/TXT files from webroot | 🟠 High | 1 hour |
| Move DB credentials to .env file | 🟠 High | 2 hours |
| Add session timeout enforcement | 🟠 High | 2 hours |

## Phase 2 — Code Quality (Week 2)

| Task | Priority | Effort |
|------|----------|--------|
| Extract SweetAlert to single JS include | 🟠 High | 2 hours |
| Standardize frontend dependency loading | 🟡 Medium | 3 hours |
| Consolidate all SQL files into migration script | 🟡 Medium | 2 hours |
| Add proper error logging (file-based, not display) | 🟡 Medium | 2 hours |
| Implement login rate limiting | 🟠 High | 3 hours |

## Phase 3 — Performance & Scale (Month 2-3)

| Task | Priority | Effort |
|------|----------|--------|
| Add database indexes for proctoring queries | 🟠 High | 1 hour |
| Implement snapshot garbage collection | 🟡 Medium | 4 hours |
| Cache dashboard statistics | 🟡 Medium | 4 hours |
| WebSocket-based WebRTC signaling | 🟢 Low | 2 weeks |
| Database query optimization | 🟡 Medium | 1 week |

## Phase 4 — Feature Additions (Quarter 2-3)

| Task | Priority | Effort |
|------|----------|--------|
| Question bank categories/tags | 🟡 Medium | 1 week |
| REST API for LMS integration | 🟢 Low | 2 weeks |
| Result email notifications | 🟡 Medium | 3 days |
| Password reset workflow | 🟠 High | 2 days |
| Student self-registration portal | 🟡 Medium | 1 week |

---

# 9. BACKUP & DISASTER RECOVERY

## 9.1 Recommended Backup Strategy

| Backup Type | Frequency | Retention | Contents | Size Estimate |
|-------------|-----------|-----------|----------|---------------|
| Database dump | Daily (off-peak) | 30 days | Full `mysqldump` | ~5-50 MB |
| Snapshot files | During exam only | 30 days | Webcam captures | Variable |
| Application files | Weekly | 90 days | All PHP, CSS, JS | ~20 MB |
| Full server | Monthly | 12 months | Complete system image | ~10-20 GB |

## 9.2 Backup Commands

```bash
# Database backup
mysqldump -u root -p assessment_portal > /backups/db/portal_$(date +%Y%m%d).sql

# Application backup
tar -czf /backups/app/portal_$(date +%Y%m%d).tar.gz /var/www/exam_portal/

# Automated cron (daily at 2 AM)
0 2 * * * /usr/bin/mysqldump -u root -p'password' assessment_portal > /backups/db/portal_$(date +\%Y\%m\%d).sql
```

## 9.3 Recovery Procedure

1. **Application failure**: Restore from file backup, ensure permissions correct
2. **Database corruption**: Import latest SQL dump, replay any incremental changes
3. **Full server failure**: Provision new server, restore application + database from latest backups
4. **Estimated RTO**: 2-4 hours for full recovery
5. **Estimated RPO**: 24 hours (daily backups)

---

# 10. ICT SUPPORT REFERENCE

## 10.1 Common Support Scenarios

| Scenario | Resolution |
|----------|------------|
| **Camera not working** | Ensure HTTPS. Check browser permissions. Try Chrome/Edge. |
| **Student can't log in** | Verify matric number format. Check student exists in database. |
| **Exam not visible** | Check exam is active, student is assigned, time window is open. |
| **Proctoring not loading** | Check WebRTC support in browser. Check HTTPS. |
| **Slow page loads** | Check server resources. Monitor MySQL query log. |
| **Snapshot storage full** | Implement retention policy. Increase disk or offload. |

## 10.2 Log Locations

| Log | Path | Purpose |
|-----|------|---------|
| PHP error log | `php_errors.log` | All PHP errors and warnings |
| MySQL slow log | `mysql-slow.log` | Queries taking > 2 seconds |
| MySQL general log | `mysql.log` | All queries (debug only) |
| Apache access log | `access.log` | All HTTP requests |
| Apache error log | `error.log` | Apache-level errors |
| Application snapshots | `uploads/snapshots/` | Webcam captures |

---

# 11. ARTIFICIAL INTELLIGENCE — CURRENT STATE & ROADMAP

## 11.1 Important Clarification: Current vs. Future AI

```
══════════════════════════════════════════════════════════════════════════════
  WHAT THE MARKETING SAYS            WHAT THE CODE ACTUALLY DOES
──────────────────────────────────────────────────────────────────────────────
  "AI Anti-Cheating Lockdown"        Client-side JS event listeners counting
                                     tab switches. Zero AI/ML inference.
  
  "AI-Driven Proctoring"             WebRTC video relay + periodic JPEG 
                                     snapshots. No computer vision analysis.
  
  "System AI Chatbot"                If/else keyword matching. No NLP/LLM.
  
  "Behavioral Analysis"              Integer violation counter. No pattern 
                                     recognition or anomaly detection.
══════════════════════════════════════════════════════════════════════════════
```

**There is currently zero artificial intelligence, machine learning, computer vision, natural language processing, or neural network inference in this codebase.** The "AI" branding refers to automated rule-based systems, not intelligent algorithms.

## 11.2 Real AI Features That Can Be Added

The following are **production-ready, well-documented AI capabilities** that can integrate with the existing proctoring pipeline. All run **client-side via TensorFlow.js or MediaPipe** — no GPU servers required.

### Level 1: Computer Vision (Browser-Based, Real-Time)

| AI Feature | Library | What It Detects | Value to School |
|------------|---------|-----------------|-----------------|
| **Face Presence Detection** | TensorFlow.js (BlazeFace) | Whether a face is visible in the webcam frame | Prevents student from leaving camera |
| **Multiple Face Detection** | TensorFlow.js (COCO-SSD) | >1 person in frame | Detects helper/accomplice in room |
| **Face Landmark Tracking** | MediaPipe Face Mesh | 468 facial landmarks | Gaze direction, head pose, blink rate |
| **Gaze/Attention Tracking** | MediaPipe + custom calc | Direction student is looking | Detects looking away from screen (reading notes) |
| **Object Detection** | TensorFlow.js (COCO-SSD) | Phones, books, papers, earbuds in frame | Detects prohibited items in real-time |
| **Mouth Open Detection** | MediaPipe Face Mesh | Mouth aspect ratio > threshold | Detects reading aloud / receiving dictation |

**Implementation Pattern (all Level 1 features):**

```
┌───────────────────────────────────────────────────────────┐
│             BROWSER (Student Device)                        │
│                                                           │
│  Webcam → TensorFlow.js Inference → Analyze Result        │
│                                       │                   │
│                              ┌────────▼────────┐          │
│                              │ Violation        │          │
│                              │ Detected?        │          │
│                              └───┬────────┬────┘          │
│                              YES │        │ NO            │
│                                  ▼        ▼               │
│  ┌─────────────────────┐    ┌────────┐  ┌──────┐         │
│  │ Send to proctor_ping │    │ POST   │  │ Skip │         │
│  │ with violation_type  │    │ violation│ │ send │         │
│  └─────────────────────┘    └────────┘  └──────┘         │
│                                                           │
│  POST → proctor_ping.php → exam_violations table          │
│       → Admin Live Proctoring UI updates                  │
└───────────────────────────────────────────────────────────┘
```

**Estimated effort per feature**: 1-3 days using prebuilt models  
**Performance impact**: 30-100ms inference time per frame on modern hardware  
**False positive risk**: Low — configurable confidence thresholds

### Level 2: Behavior Analysis (Server-Side)

| AI Feature | Approach | What It Detects | Implementation |
|------------|----------|-----------------|----------------|
| **Anomalous Answer Patterns** | Statistical outlier detection (Z-score) | Identical answers across different students in same session | PHP script analyzing `objective_answers` table post-exam |
| **Speed Cheating Detection** | Per-question time analysis | Student answers suspiciously fast (e.g., 2 seconds per question) | JS sends `time_per_question` to `objective_answers` |
| **Tab-Switch Pattern Analysis** | Time-series analysis | Repeated tab-switch bursts indicative of systematic cheating | Enhanced `proctor_ping.php` violation categorization |
| **Co-location Detection** | IP subnet + timing correlation | Two+ students with same answers from same IP range | Post-exam SQL analysis |

**Estimated effort**: 3-5 days per feature  
**Implementation**: PHP + MySQL queries (no external ML dependency)

### Level 3: Identity Verification (Pre-Exam)

| AI Feature | Approach | What It Does | Implementation |
|------------|----------|--------------|----------------|
| **Face Enrollment Verification** | Compare webcam snapshot to enrollment photo | Confirms test-taker identity at exam start | face-api.js (Euclidean distance on face descriptors) |
| **Liveness Detection** | Blink detection or head movement challenge | Prevents photo/video spoofing | MediaPipe blink detection or random head-turn prompt |

**Estimated effort**: 5-7 days  
**Dependencies**: Enrollment photo storage, face descriptor database column

### Level 4: NLP Chatbot Upgrade (Future)

| Feature | Approach | Benefit |
|---------|----------|---------|
| **LLM-Powered Assistant** | OpenAI API / local Llama model | Understands natural language questions, not just keywords |
| **Context-Aware Help** | RAG over system documentation | Answers based on actual portal content and FAQs |

**Estimated effort**: 1-2 weeks  
**Cost**: API usage fees (if using cloud LLM)

## 11.3 AI Architecture — How It Integrates

The existing proctoring infrastructure is already designed to handle AI input. Each AI feature feeds into the existing pipeline:

```
EXISTING INFRASTRUCTURE                    NEW AI LAYER
═══════════════════════                    ═══════════════

proctor_ping.php ◄───────────── POST ──── TensorFlow.js inference
  • exam_id, student_id                       • Face count
  • status, violation_type                    • Object detected
  • webcam_snapshot                           • Gaze direction
       │                                      • Blink rate
       ▼
exam_violations table ──── Admin Live UI
  • attempt_id                                • Real-time violation card
  • violation_type                            • Snapshot thumbnail
  • details (JSON)                            • Student status badge
  • created_at                                • Warning button

objective_attempts table
  • violation_count ──── Auto-lock at threshold (configurable)
  • is_locked ────────── Admin remote unlock
  • last_warning_at ──── Warning message to student
```

**No architectural changes needed.** The `proctor_ping.php` endpoint already accepts a `violation_type` field. AI violation types like `MULTIPLE_FACES`, `FACE_NOT_VISIBLE`, `PHONE_DETECTED`, `GAZE_AWAY` can be added alongside the existing types (`tab_switch`, `blur`, `disconnected`).

## 11.4 Sample Implementation: Face Detection (2 Days)

```javascript
// Added to student_lockdown.js or a new ai_proctor.js

import * as face from '@tensorflow-models/face-detection';

const detector = await face.createDetector(
  face.SupportedModels.MediaPipeFaceDetector,
  { runtime: 'tfjs' }
);

setInterval(async () => {
  const video = document.getElementById('webcam');
  const faces = await detector.estimateFaces(video);
  
  const aiViolations = [];
  if (faces.length === 0) aiViolations.push({ type: 'FACE_NOT_VISIBLE', details: 'No face detected' });
  if (faces.length > 1)  aiViolations.push({ type: 'MULTIPLE_FACES', details: `${faces.length} faces detected` });
  
  for (const v of aiViolations) {
    await fetch('proctor_ping', { 
      method: 'POST',
      body: new URLSearchParams({ ...v, exam_id, attempt_no })
    });
  }
}, 5000); // every 5 seconds
```

## 11.5 Hardware Requirements for AI Features

| Feature | Client GPU Required | Client RAM Impact | Server Requirement |
|---------|-------------------|-------------------|--------------------|
| Face Detection (BlazeFace) | No (CPU fallback) | +50-100 MB | None (client-side) |
| Multiple Face Detection | Recommended (GPU) | +100-200 MB | None (client-side) |
| Face Mesh / Gaze | Recommended (GPU) | +100-150 MB | None (client-side) |
| Object Detection (COCO-SSD) | Recommended (GPU) | +150-250 MB | None (client-side) |
| Answer Pattern Analysis | None | None | MySQL only |
| Face Verification | No | +50 MB | +1 GB for face descriptor storage |

**Note**: All client-side models work on Chrome/Edge on modern laptops (2018+). Some mobile devices may struggle. A "light mode" (face detection only, no object detection) should be available as fallback.

## 11.6 Cost Estimate for AI Implementation

| Level | Features | Estimated Cost (Development) | Timeline |
|-------|----------|------------------------------|----------|
| **Level 1** | Face detection, multiple-face alert, gaze tracking, object detection | ₦400,000–₦800,000 | 1-2 weeks |
| **Level 2** | Answer pattern analysis, speed cheating detection, tab-switch patterns | ₦200,000–₦400,000 | 1 week |
| **Level 3** | Face enrollment verification, liveness detection | ₦300,000–₦600,000 | 1-2 weeks |
| **Level 4** | LLM chatbot upgrade | ₦500,000+ | 2-4 weeks |
| **COMPLETE PACKAGE** | All Levels 1-3 (recommended) | **₦900,000–₦1,800,000** | **3-5 weeks** |

## 11.7 Recommendation

To legitimately market this as an "AI-Proctored" platform, implement **Level 1 face detection + multiple face alert** as a minimum. These are:

- ✅ Free and open-source (TensorFlow.js, MediaPipe)
- ✅ Well-documented with thousands of production deployments
- ✅ Low latency (30-50ms per frame on modern hardware)
- ✅ No server GPU required (runs entirely in student's browser)
- ✅ Integrates directly with existing violation/proctoring pipeline

Without these additions, the "AI" claims in the current marketing material are **misleading**. The system is a well-built **rule-based proctoring platform** — it should be sold as such, or the AI features should be developed.

---

*This technical appendix is intended as a companion to the NextGen Assessment Portal sales proposal. It provides ICT teams with the detailed information needed to evaluate, deploy, and maintain the platform.*

*For implementation support, contact the solution architect via WhatsApp [+2348168438930](https://wa.me/+2348168438930).*
