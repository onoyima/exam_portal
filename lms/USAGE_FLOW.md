# Veritas Integrated LMS — Usage Flow

## How the LMS and Exam Portal Work Together

The LMS lives **inside** the exam portal's database, sharing the same MySQL instance. All LMS tables are prefixed `lms_` (`lms_users`, `lms_courses`, `lms_quizzes`, ...), coexisting with portal tables (`students`, `examiners`, `exams`, `exam_students`, ...). A second database (`exeat2`) holds the school's academic ERP data — read-only from the LMS side.

---

## 1. Authentication Flow

```
User → Login Page → POST /auth/login → PortalAuthBridge → JWT → SPA
                              │
                              ├── Tier 1: Portal DB (students / examiners table)
                              │     - bcrypt verify, auto-upgrades plain-text passwords
                              │
                              └── Tier 2: exeat2 (school ERP, fallback)
                                    - auto-provisions portal account on first login
                                    - creates lms_users row with (portal_user_id, portal_user_type)
```

### What happens on login:

1. User enters username/email + password
2. `PortalAuthBridge` tries the portal's `students` table first, then `examiners` table
3. If not found, falls back to `exeat2.students` / `exeat2.staff` (school ERP)
4. On exeat2 success: a new student/examiner record is **auto-created** in the portal DB
5. An `lms_users` row is created/retrieved linking to the portal user
6. A **JWT** (HS256, 1-hour TTL) and **refresh token** (30-day TTL, stored in `lms_refresh_tokens`) are issued
7. PHP `$_SESSION` vars are set for the exam portal to recognize the user (`student_id`, `examiner_id`, `role`, etc.)
8. Background sync: student's department/faculty/level + course registrations are pulled from exeat2
9. Frontend receives `{ user, token, refresh_token }` and stores them in `localStorage`

### All subsequent API calls:

- The SPA sends `Authorization: Bearer <jwt>` on every request
- The inline auth middleware in `routes.php` verifies the HMAC-SHA256 signature and expiry
- Failed auth (401) triggers an automatic refresh-token rotation in `api.js`

---

## 2. SSO Bridge (Exam Portal → LMS)

The exam portal can obtain a JWT for a user without a password:

```
Exam Portal → POST /auth/portalLogin { portal_user_id, portal_user_type, token }
                                                      │
                                  HMAC-SHA256(portal_user_id|portal_user_type, api_secret)
                                                      │
                                              Returns { user, token, refresh_token }
```

And vice versa — the LMS can redirect a user into the exam portal:

```
LMS → POST /exam-bridge/sso → Short-lived JWT (5 min) → Redirect URL → Exam Portal
```

---

## 3. Course Sync (exeat2 → LMS)

Courses in the LMS come from the school's academic ERP (`exeat2`), not from the LMS itself.

### On student login:

| What syncs | Source | Target |
|---|---|---|
| Academic context (faculty, dept, level) | `exeat2.student_academics` | `lms_users` columns |
| Course registrations | `exeat2.course_regs` where `is_course_reg = 2` | `lms_courses` + `lms_enrollments` |
| Exam enrollments | Portal `exam_students` + `exams` | `lms_enrollments` |

### On staff/instructor login:

| What syncs | Source | Target |
|---|---|---|
| Assigned courses | `exeat2.assigned_courses` where `approval_status = 'approved'` | `lms_courses` + `lms_course_instructors` |

### Manual bulk sync (admin only):

```
POST /sync/enrollments
```
Iterates all active `lms_users` and re-syncs their enrollments from exeat2.

---

## 4. Exam Bridge (LMS Quizzes ↔ Portal Exams)

The `lms_exam_links` table connects an LMS quiz to a portal exam:

```
lms_exam_links
├── lms_quiz_id    → lms_quizzes.id
├── portal_exam_id → exams.id (portal)
├── course_id      → lms_courses.id
└── sync_status    → linked | unlinked | pending_sync
```

| Endpoint | Purpose |
|---|---|
| `POST /exam-bridge/link` | Link an LMS quiz to a portal exam |
| `POST /exam-bridge/unlink/:id` | Remove link |
| `POST /exam-bridge/sync` | Push grades/enrollments or pull results to/from portal |
| `GET /exam-bridge/sync-log` | View sync history |
| `POST /exam-bridge/sso` | Generate SSO redirect to launch exam in portal |

The sync communicates with the exam portal via HTTP. Every attempt is logged in `lms_exam_sync_log`.

---

## 5. SPA Frontend Flows

### Login → Dashboard
```
/login ──POST──→ /auth/login ──→ store token → redirect → /dashboard
                                                           ├── GET /dashboard/student → stats cards
                                                           └── GET /enrollments/my    → course list
```

### Browse Courses
```
/courses ──GET──→ /courses ──→ list with search
  └── click course ──→ GET /courses/:id ──→ detail with modules
       └── click module ──→ GET /modules/:id/lessons
            └── click lesson ──→ GET /lessons/:id
```

### Take a Quiz
```
/quiz/:id ──→ GET /quizzes/:id ──→ quiz detail + past attempts table
  └── "Start Attempt" ──→ POST /quizzes/:id/attempts ──→ render questions
       └── submit ──→ POST /quizzes/:id/attempts/:id/submit ──→ score + redirect
```

### Academics (exeat2 data)
```
/academics ──→ GET /academic/faculties ──→ faculty list
  └── click faculty ──→ GET /academic/departments?faculty_id=X ──→ department list
       └── click dept ──→ GET /academic/course-studies?department_id=X ──→ programs
            └── click program ──→ GET /academic/courses?course_study_id=X ──→ courses
```

### Messages
```
/messages ──→ GET /messages/conversations ──→ conversation list
  └── click conversation ──→ GET /messages/conversations/:id ──→ chat view
       └── send message ──→ POST /messages/send
```

---

## 6. Key Configuration Points

### Database connections (`config/database.php`)
- **`lms`** — shared portal DB (`exam_lms`), contains both portal tables and `lms_*` tables
- **`exeat2`** — school ERP (read-only), academic records, course regs, staff assignments

### User mapping
- Portal user in `students` or `examiners` table ↔ `lms_users` via `portal_user_id` + `portal_user_type`
- The LMS does **not** store passwords (except for self-registered accounts)
- Role mapping: `student → student`, `examiner/lecturer/staff → instructor`, `admin → super_admin`

### Auth
- JWT secret: `lms-secret-key-change-in-production` (override via `LMS_JWT_SECRET` env)
- Access token: 1 hour | Refresh token: 30 days
- Self-registration available at `POST /auth/register`

---

## 7. Error Handling Patterns

| Scenario | Response |
|---|---|
| Invalid credentials | `401 { error: { code: "AUTH_ERROR", message: "..." } }` |
| Expired/invalid JWT | `401 { error: { code: "AUTH_ERROR", message: "..." } }` → auto-refresh |
| Insufficient role | `403 { error: { code: "FORBIDDEN", ... } }` |
| Resource not found | `404 { error: { code: "NOT_FOUND", ... } }` |
| Validation failure | `422 { error: { code: "VALIDATION_ERROR", details: [...] } }` |
| exeat2 unavailable | `503 { error: { code: "EXEAT2_UNAVAILABLE", ... } }` |
| Rate limit exceeded | `429 { error: { code: "RATE_LIMIT", ... } }` |
| Server error | `500 { error: { code: "INTERNAL_ERROR", ... } }` |

All API responses follow the envelope: `{ success: true/false, data?: ..., error?: { code, message, details? }, meta?: ... }`. The SPA unwraps `data.data ?? data` so views access properties directly.

---

## 8. Database Table Layout

| Scope | Tables | Examples |
|---|---|---|
| Portal (shared) | `students`, `examiners`, `exams`, `exam_students` | Created by exam portal |
| LMS (prefixed) | `lms_users`, `lms_courses`, `lms_quizzes`, `lms_enrollments`, `lms_messages`, `lms_notifications`, `lms_exam_links`, `lms_exam_sync_log`, ... | Created by LMS migrations |
| exeat2 (separate DB) | `students`, `staff`, `faculties`, `departments`, `course_studies`, `courses`, `course_regs`, `assigned_courses`, `student_academics`, `vu_sessions`, `vu_semesters`, `levels` | School ERP (read-only) |
