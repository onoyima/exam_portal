# VERITAS INTEGRATED LEARNING MANAGEMENT SYSTEM (ILMS)
## Enterprise Architecture Document

---

**Version:** 1.0.0  
**Institution:** Veritas University, Abuja  
**Architecture Type:** Modular Monolith with Microservice-Oriented Design  
**Target Scale:** 10,000+ Concurrent Users  
**Last Updated:** May 2026

---

## TABLE OF CONTENTS

1. [System Overview](#1-system-overview)
2. [Architecture Philosophy](#2-architecture-philosophy)
3. [System Architecture Diagram](#3-system-architecture-diagram)
4. [Technology Stack](#4-technology-stack)
5. [Directory Structure](#5-directory-structure)
6. [Database Architecture](#6-database-architecture)
7. [API Architecture](#7-api-architecture)
8. [Authentication & Authorization](#8-authentication--authorization)
9. [Layered Service Architecture](#9-layered-service-architecture)
10. [Exam Portal Integration](#10-exam-portal-integration)
11. [Event-Driven Architecture](#11-event-driven-architecture)
12. [Caching Strategy](#12-caching-strategy)
13. [Queue & Async Processing](#13-queue--async-processing)
14. [Security Architecture](#14-security-architecture)
15. [DevOps & Deployment](#15-devops--deployment)
16. [Monitoring & Observability](#16-monitoring--observability)
17. [Disaster Recovery](#17-disaster-recovery)
18. [Performance Optimization](#18-performance-optimization)
19. [AI Integration Strategy](#19-ai-integration-strategy)
20. [Implementation Roadmap](#20-implementation-roadmap)

---

## 1. SYSTEM OVERVIEW

The Veritas Integrated Learning Management System (ILMS) is a production-grade educational ecosystem that unifies course management, content delivery, formative assessment, and summative examination through deep integration with the existing Veritas Exam Portal.

### Core Capabilities

| Capability | Description |
|---|---|
| **Course Management** | Create, organize, and deliver courses with modular lesson structures |
| **Content Delivery** | Rich media support: video, PDF, interactive H5P, documents, SCORM |
| **Formative Assessment** | Quizzes, assignments, discussions within LMS |
| **Summative Assessment** | Seamless launch into Exam Portal for proctored exams |
| **Gradebook** | Unified grade tracking across LMS and Exam Portal |
| **Analytics** | Student, instructor, and institutional analytics with predictive ML |
| **Communication** | Forums, direct messaging, announcements, notifications |
| **Integration** | LTI 1.3, SSO, REST APIs, webhooks |

### Design Tenets

1. **Security First** — Every layer designed with zero-trust principles
2. **Scale from Day One** — Horizontally scalable architecture
3. **Observability by Default** — All systems emit metrics, traces, logs
4. **Event-Driven** — Async communication between all subsystems
5. **API-First** — Every feature accessible via well-documented API
6. **Integration Ready** — LTI 1.3 compliant, webhook-enabled
7. **AI Extensible** — Architecture supports ML model injection at every level

---

## 2. ARCHITECTURE PHILOSOPHY

### Modular Monolith + Microservice Seeds

The ILMS uses a **Modular Monolith** architecture with clear bounded contexts that can be extracted into microservices as scale demands. This provides:

- **Monolithic benefits:** Simpler deployment, atomic transactions, lower latency, easier debugging
- **Microservice seeds:** Each module has defined API boundaries, independent database schemas (schema-per-module), and can be extracted without refactoring

### Bounded Contexts

```
┌─────────────────────────────────────────────────────────────┐
│                    ILMS SYSTEM CONTEXT                        │
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │   CORE   │  │ CONTENT  │  │ ASSESS-  │  │  ANALYT- │   │
│  │   LMS    │  │ SERVICE  │  │  MENT    │  │  ICS     │   │
│  │          │  │          │  │          │  │          │   │
│  │ Courses  │  │ Media    │  │ Quizzes  │  │ Tracking │   │
│  │ Modules  │  │ Video    │  │ Assign.  │  │ Predict. │   │
│  │ Lessons  │  │ H5P      │  │ Grading  │  │ Reports  │   │
│  │ Enroll.  │  │ SCORM    │  │ Gradebx  │  │ ML Pipe  │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
│       │              │              │              │        │
│  ┌────┴──────────────┴──────────────┴──────────────┴────┐  │
│  │                    API GATEWAY                         │  │
│  │         NGINX + Rate Limit + Auth + Versioning         │  │
│  └─────────────────────────┬──────────────────────────────┘  │
│                            │                                  │
│  ┌─────────────────────────┴──────────────────────────────┐  │
│  │                 EXAM BRIDGE SERVICE                      │  │
│  │    SSO  │  Sync  │  Webhooks  │  Grade Passback         │  │
│  └─────────────────────────┬──────────────────────────────┘  │
│                            │                                  │
│  ┌─────────────────────────┴──────────────────────────────┐  │
│  │              VERITAS EXAM PORTAL                         │  │
│  │    (Existing System - Objective, Practical, VIVA)       │  │
│  └─────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### Data Architecture Per Module

Each module owns its data. Cross-module communication happens through:
1. **Direct service calls** (within same process) — low latency
2. **Events/Queue** (for eventual consistency) — async
3. **Shared read models** (cached/denormalized) — for queries

---

## 3. SYSTEM ARCHITECTURE DIAGRAM

```
┌────────────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER                                            │
│                                                                                      │
│  ┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────────────┐  │
│  │   STUDENT PORTAL    │  │   INSTRUCTOR PORTAL │  │      ADMIN DASHBOARD        │  │
│  │   (React SPA)       │  │   (React SPA)       │  │      (React SPA)             │  │
│  └─────────┬───────────┘  └─────────┬───────────┘  └─────────────┬───────────────┘  │
│            │                        │                            │                    │
│  ┌─────────┴────────────────────────┴────────────────────────────┴────────────────┐ │
│  │                          CDN / CloudFront                                        │ │
│  │              Static assets, video streams, H5P packages                         │ │
│  └───────────────────────────────────┬───────────────────────────────────────────┘ │
└──────────────────────────────────────┼───────────────────────────────────────────────┘
                                       │
┌──────────────────────────────────────┼───────────────────────────────────────────────┐
│                              LOAD BALANCER / NGINX                                   │
│                   SSL Termination | Rate Limiting | WAF | API Versioning             │
└──────────────────────────────────────┼───────────────────────────────────────────────┘
                                       │
┌──────────────────────────────────────┼───────────────────────────────────────────────┐
│                           APPLICATION LAYER                                          │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                          API GATEWAY (PHP/Node)                                │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐            │   │
│  │  │  Auth    │ │  Course  │ │  Content │ │  Grade   │ │  Exam    │            │   │
│  │  │ Service  │ │  Service │ │  Service │ │  Service │ │  Bridge  │            │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘            │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                        EVENT BUS (RabbitMQ)                                    │   │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐            │   │
│  │  │ Course   │ │Enrollment│ │ Grade    │ │  Sync    │ │ Notifica-│            │   │
│  │  │ Events   │ │ Events   │ │ Events   │ │  Events  │ │ tion Ev. │            │   │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘            │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────┼───────────────────────────────────────────────┘
                                       │
┌──────────────────────────────────────┼───────────────────────────────────────────────┐
│                            DATA LAYER                                                │
│                                                                                      │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐                   │
│  │   MySQL 8 InnoDB │  │    Redis Cache   │  │  Elasticsearch   │                   │
│  │   Cluster        │  │    + Sessions    │  │  + Search        │                   │
│  │   (Primary + Ro) │  │    + Queues      │  │  + Analytics     │                   │
│  └──────────────────┘  └──────────────────┘  └──────────────────┘                   │
│                                                                                      │
│  ┌──────────────────────────────────────────────────────────────────────────────┐   │
│  │                            OBJECT STORAGE (S3/MinIO)                           │   │
│  │        Videos | Documents | Images | H5P | Recordings | Snapshots             │   │
│  └──────────────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. TECHNOLOGY STACK

### Backend (LMS Core)

| Component | Technology | Justification |
|---|---|---|
| **Runtime** | PHP 8.2+ | Compatibility with existing Exam Portal |
| **Framework** | Custom MVC (modular) | Lightweight, no overhead, full control |
| **API** | REST + JSON | Universal compatibility |
| **Auth** | JWT (lcobucci/jwt) + OAuth2 | Stateless, scalable |
| **Validation** | Respect\Validation | Enterprise-grade validation |

### Backend (Future Microservices)

| Component | Technology |
|---|---|
| **Analytics Service** | Python 3.11 + FastAPI |
| **Content Pipeline** | Node.js 20 + Express |
| **ML Service** | Python + TensorFlow/PyTorch |
| **Notifications** | Node.js + Socket.io |

### Database & Storage

| Component | Technology |
|---|---|
| **Primary DB** | MySQL 8.0+ InnoDB Cluster |
| **Cache** | Redis 7+ |
| **Search** | Elasticsearch 8+ |
| **Object Storage** | AWS S3 / MinIO (self-hosted) |
| **Queue** | RabbitMQ / Redis Streams |

### Frontend

| Component | Technology |
|---|---|
| **Framework** | Vanilla JS SPA (ES Modules) |
| **CSS** | Bootstrap 5.3 + Custom Properties |
| **Charts** | Chart.js 4 |
| **HTTP Client** | Axios/Fetch |
| **Build** | Vite (for future migration to React) |

### DevOps

| Component | Technology |
|---|---|
| **Containers** | Docker + Docker Compose |
| **Orchestration** | Kubernetes (production) |
| **CI/CD** | GitHub Actions |
| **Reverse Proxy** | NGINX |
| **Monitoring** | Prometheus + Grafana |
| **Logging** | ELK Stack (Elasticsearch, Logstash, Kibana) |

---

## 5. DIRECTORY STRUCTURE

```
exam_portal/
│
├── lms/                                    # LMS Root Module
│   ├── ARCHITECTURE.md                     # This document
│   ├── bootstrap.php                       # LMS autoloader & bootstrap
│   │
│   ├── config/
│   │   ├── lms.php                         # LMS configuration
│   │   ├── permissions.php                 # RBAC permission definitions
│   │   └── integration.php                 # Exam Portal integration config
│   │
│   ├── database/
│   │   ├── migrations/                     # SQL migration files
│   │   │   ├── 001_create_courses.sql
│   │   │   ├── 002_create_modules.sql
│   │   │   ├── 003_create_lessons.sql
│   │   │   ├── 004_create_enrollments.sql
│   │   │   ├── 005_create_assignments.sql
│   │   │   ├── 006_create_submissions.sql
│   │   │   ├── 007_create_quizzes.sql
│   │   │   ├── 008_create_quiz_attempts.sql
│   │   │   ├── 009_create_gradebook.sql
│   │   │   ├── 010_create_discussions.sql
│   │   │   ├── 011_create_notifications.sql
│   │   │   ├── 012_create_messages.sql
│   │   │   ├── 013_create_analytics.sql
│   │   │   ├── 014_create_exam_sync.sql
│   │   │   ├── 015_create_media.sql
│   │   │   └── 016_create_audit_trail.sql
│   │   ├── seeders/                        # Sample data seeders
│   │   └── migrate.php                     # Migration runner
│   │
│   ├── src/
│   │   ├── Core/
│   │   │   ├── App.php                     # Application container
│   │   │   ├── Router.php                  # API router
│   │   │   ├── Controller.php              # Base controller
│   │   │   ├── Model.php                   # Base model (Active Record)
│   │   │   ├── Repository.php              # Base repository
│   │   │   ├── Service.php                 # Base service
│   │   │   ├── Database.php                # PDO wrapper + connection pool
│   │   │   ├── Request.php                 # HTTP request abstraction
│   │   │   ├── Response.php                # HTTP response abstraction
│   │   │   ├── Validator.php               # Input validation
│   │   │   ├── Event.php                   # Event dispatcher
│   │   │   ├── Middleware.php              # Middleware pipeline
│   │   │   ├── Logger.php                  # Structured logging
│   │   │   └── Cache.php                   # Redis cache adapter
│   │   │
│   │   ├── Controllers/
│   │   │   ├── AuthController.php          # SSO + JWT auth
│   │   │   ├── CourseController.php        # Course CRUD
│   │   │   ├── ModuleController.php        # Module management
│   │   │   ├── LessonController.php        # Lesson CRUD
│   │   │   ├── EnrollmentController.php    # Enrollment management
│   │   │   ├── AssignmentController.php    # Assignment CRUD
│   │   │   ├── SubmissionController.php    # Submission handling
│   │   │   ├── QuizController.php          # Quiz management
│   │   │   ├── QuizAttemptController.php   # Quiz taking + grading
│   │   │   ├── GradebookController.php     # Gradebook operations
│   │   │   ├── DiscussionController.php    # Forum discussions
│   │   │   ├── MessageController.php       # Direct messaging
│   │   │   ├── NotificationController.php  # Notifications
│   │   │   ├── AnalyticsController.php     # Analytics endpoints
│   │   │   ├── ExamBridgeController.php    # Exam Portal integration
│   │   │   ├── ContentController.php       # Media/content serving
│   │   │   └── AdminController.php         # Admin operations
│   │   │
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   ├── Permission.php
│   │   │   ├── Course.php
│   │   │   ├── CourseModule.php
│   │   │   ├── Lesson.php
│   │   │   ├── Enrollment.php
│   │   │   ├── Assignment.php
│   │   │   ├── Submission.php
│   │   │   ├── Quiz.php
│   │   │   ├── QuizQuestion.php
│   │   │   ├── QuizAttempt.php
│   │   │   ├── QuizAnswer.php
│   │   │   ├── GradebookItem.php
│   │   │   ├── Discussion.php
│   │   │   ├── DiscussionReply.php
│   │   │   ├── Message.php
│   │   │   ├── Notification.php
│   │   │   ├── MediaFile.php
│   │   │   ├── AnalyticsEvent.php
│   │   │   ├── ExamSyncLog.php
│   │   │   └── AuditLog.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php          # JWT authentication
│   │   │   ├── RoleMiddleware.php          # RBAC enforcement
│   │   │   ├── RateLimitMiddleware.php     # Rate limiting
│   │   │   ├── CorsMiddleware.php          # CORS headers
│   │   │   ├── CsrfMiddleware.php          # CSRF protection
│   │   │   └── AuditMiddleware.php         # Request audit logging
│   │   │
│   │   ├── Services/
│   │   │   ├── AuthService.php             # JWT + OAuth logic
│   │   │   ├── CourseService.php           # Course business logic
│   │   │   ├── EnrollmentService.php       # Enrollment workflow
│   │   │   ├── GradebookService.php        # Grade calculation
│   │   │   ├── QuizService.php             # Quiz engine
│   │   │   ├── AnalyticsService.php        # Analytics engine
│   │   │   ├── NotificationService.php     # Notification dispatch
│   │   │   ├── ExamBridgeService.php       # Exam Portal integration
│   │   │   ├── ContentService.php          # Media pipeline
│   │   │   ├── SyncService.php             # Data sync engine
│   │   │   └── CacheService.php            # Cache management
│   │   │
│   │   ├── Repositories/
│   │   │   ├── CourseRepository.php
│   │   │   ├── EnrollmentRepository.php
│   │   │   ├── GradebookRepository.php
│   │   │   └── ... (per entity)
│   │   │
│   │   └── Exceptions/
│   │       ├── AuthenticationException.php
│   │       ├── AuthorizationException.php
│   │       ├── ValidationException.php
│   │       ├── NotFoundException.php
│   │       ├── ConflictException.php
│   │       └── IntegrationException.php
│   │
│   ├── routes/
│   │   └── api.php                         # Route definitions
│   │
│   ├── public/
│   │   ├── index.php                       # LMS entry point
│   │   ├── .htaccess                       # URL rewriting
│   │   └── assets/                         # Compiled assets
│   │
│   ├── resources/
│   │   ├── js/
│   │   │   ├── lms-core.js                 # Core SPA framework
│   │   │   ├── lms-router.js               # Client-side router
│   │   │   ├── lms-api.js                  # API client
│   │   │   ├── lms-auth.js                 # Auth module
│   │   │   ├── components/                 # UI components
│   │   │   │   ├── Sidebar.js
│   │   │   │   ├── Header.js
│   │   │   │   ├── CourseCard.js
│   │   │   │   ├── LessonViewer.js
│   │   │   │   ├── QuizEngine.js
│   │   │   │   └── Gradebook.js
│   │   │   └── pages/                      # Page views
│   │   │       ├── Dashboard.js
│   │   │       ├── Courses.js
│   │   │       ├── CourseView.js
│   │   │       ├── LessonView.js
│   │   │       ├── QuizView.js
│   │   │       ├── Gradebook.js
│   │   │       ├── Analytics.js
│   │   │       └── Profile.js
│   │   └── css/
│   │       └── lms.css                     # LMS styles
│   │
│   ├── views/                              # Server-rendered views (fallback)
│   │   ├── layout.php
│   │   ├── login.php
│   │   └── error.php
│   │
│   └── storage/                            # Local file storage
│       ├── media/
│       └── logs/
│
├── ... (existing exam portal files)
```

---

## 6. DATABASE ARCHITECTURE

### 6.1 Entity Relationship Overview

```
users ──── user_roles ──── roles ──── role_permissions ──── permissions
  │
  ├── enrollments ──── courses ──── course_modules ──── lessons
  │                      │
  │                      └── course_instructors
  │
  ├── assignments ──── submissions
  │
  ├── quiz_attempts ──── quizzes ──── quiz_questions
  │       │                              │
  │       └── quiz_answers ──────────────┘
  │
  ├── gradebook_items ──── gradebook
  │
  ├── discussions ──── discussion_replies
  │
  ├── messages
  │
  ├── notifications
  │
  ├── enrollments ──── exam_sync_log ──── (exam_portal.exams)
  │
  └── analytics_events
```

### 6.2 Core Tables

#### `users`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| uuid | CHAR(36) | UNIQUE INDEX | Public identifier |
| email | VARCHAR(255) | UNIQUE INDEX | Login identifier |
| password_hash | VARCHAR(255) | NOT NULL | bcrypt |
| first_name | VARCHAR(100) | NOT NULL | |
| middle_name | VARCHAR(100) | NULLABLE | |
| last_name | VARCHAR(100) | NOT NULL | |
| phone | VARCHAR(30) | NULLABLE | |
| avatar_url | VARCHAR(500) | NULLABLE | |
| timezone | VARCHAR(50) | DEFAULT 'UTC' | |
| locale | VARCHAR(10) | DEFAULT 'en' | |
| is_active | TINYINT(1) | DEFAULT 1 | |
| last_login_at | DATETIME | NULLABLE | |
| created_at | DATETIME | NOT NULL | |
| updated_at | DATETIME | NOT NULL | |
| deleted_at | DATETIME | NULLABLE | Soft delete |

#### `roles`
| Column | Type | Constraints |
|---|---|---|
| id | INT UNSIGNED | PK AUTO_INCREMENT |
| name | VARCHAR(50) | UNIQUE |
| slug | VARCHAR(50) | UNIQUE INDEX |
| description | VARCHAR(255) | NULLABLE |
| is_system | TINYINT(1) | DEFAULT 0 |
| created_at | DATETIME | |

**Seed Roles:** `super_admin`, `admin`, `instructor`, `ta`, `student`

#### `permissions`
| Column | Type | Constraints |
|---|---|---|
| id | INT UNSIGNED | PK AUTO_INCREMENT |
| name | VARCHAR(100) | UNIQUE |
| slug | VARCHAR(100) | UNIQUE INDEX |
| group | VARCHAR(50) | NOT NULL |
| created_at | DATETIME | |

**Permission Groups:** `courses`, `users`, `grades`, `content`, `analytics`, `system`, `exams`

#### `courses`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| uuid | CHAR(36) | UNIQUE INDEX | |
| code | VARCHAR(20) | UNIQUE INDEX | Course code (e.g., "CSC401") |
| title | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE INDEX | URL-friendly name |
| description | TEXT | NULLABLE | |
| short_description | VARCHAR(500) | NULLABLE | |
| category_id | INT UNSIGNED | FK -> categories | |
| thumbnail_url | VARCHAR(500) | NULLABLE | |
| banner_url | VARCHAR(500) | NULLABLE | |
| level | VARCHAR(50) | NULLABLE | 100, 200, 300, 400, 500 |
| credits | INT | DEFAULT 3 | Credit hours |
| duration_weeks | INT | NULLABLE | |
| prerequisites | JSON | NULLABLE | Array of course IDs |
| learning_outcomes | JSON | NULLABLE | Array of outcome strings |
| syllabus | TEXT | NULLABLE | |
| status | ENUM('draft','published','archived') | DEFAULT 'draft' | |
| is_required | TINYINT(1) | DEFAULT 0 | |
| max_students | INT | NULLABLE | |
| start_date | DATE | NULLABLE | |
| end_date | DATE | NULLABLE | |
| enrollment_start | DATETIME | NULLABLE | |
| enrollment_end | DATETIME | NULLABLE | |
| created_by | BIGINT UNSIGNED | FK -> users | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULLABLE | |

#### `course_modules`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| order_index | INT | NOT NULL | Sort order |
| is_visible | TINYINT(1) | DEFAULT 1 | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

#### `lessons`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| module_id | BIGINT UNSIGNED | FK -> course_modules, CASCADE | |
| title | VARCHAR(255) | NOT NULL | |
| slug | VARCHAR(255) | UNIQUE | |
| content_type | ENUM('text','video','h5p','scorm','pdf','quiz','assignment') | NOT NULL | |
| content | LONGTEXT | NULLABLE | HTML/JSON content |
| video_url | VARCHAR(500) | NULLABLE | |
| video_duration | INT | NULLABLE | Seconds |
| h5p_id | INT | NULLABLE | |
| scorm_package_id | INT | NULLABLE | |
| file_url | VARCHAR(500) | NULLABLE | |
| duration_minutes | INT | DEFAULT 0 | |
| order_index | INT | NOT NULL | |
| is_visible | TINYINT(1) | DEFAULT 1 | |
| is_required | TINYINT(1) | DEFAULT 1 | |
| allow_download | TINYINT(1) | DEFAULT 1 | |
| created_by | BIGINT UNSIGNED | FK -> users | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULLABLE | |

#### `enrollments`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| user_id | BIGINT UNSIGNED | FK -> users, CASCADE | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| enrollment_type | ENUM('student','ta','audit') | DEFAULT 'student' | |
| status | ENUM('active','completed','dropped','pending') | DEFAULT 'active' | |
| enrolled_at | DATETIME | NOT NULL | |
| completed_at | DATETIME | NULLABLE | |
| dropped_at | DATETIME | NULLABLE | |
| grade | DECIMAL(5,2) | NULLABLE | Final grade |
| letter_grade | VARCHAR(2) | NULLABLE | A, B, C, D, F |
| gpa_value | DECIMAL(3,2) | NULLABLE | 4.0 scale |
| created_at | DATETIME | | |
| UNIQUE KEY | (user_id, course_id, enrollment_type) | | |

#### `assignments`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| module_id | BIGINT UNSIGNED | FK -> course_modules, NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| instructions | TEXT | NULLABLE | |
| max_points | DECIMAL(8,2) | NOT NULL | |
| passing_points | DECIMAL(8,2) | NULLABLE | |
| allow_late_submission | TINYINT(1) | DEFAULT 0 | |
| late_penalty_percent | DECIMAL(5,2) | DEFAULT 0 | |
| max_attempts | INT | DEFAULT 1 | |
| due_at | DATETIME | NULLABLE | |
| available_from | DATETIME | NULLABLE | |
| available_until | DATETIME | NULLABLE | |
| submission_type | ENUM('file','text','url','online_text') | DEFAULT 'file' | |
| allowed_extensions | JSON | NULLABLE | |
| max_file_size | INT | DEFAULT 52428800 | 50MB |
| allow_group_submission | TINYINT(1) | DEFAULT 0 | |
| group_size | INT | NULLABLE | |
| is_visible | TINYINT(1) | DEFAULT 1 | |
| created_by | BIGINT UNSIGNED | FK -> users | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULLABLE | |

#### `submissions`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| assignment_id | BIGINT UNSIGNED | FK -> assignments, CASCADE | |
| user_id | BIGINT UNSIGNED | FK -> users, CASCADE | |
| attempt_number | INT | DEFAULT 1 | |
| content | TEXT | NULLABLE | Text submission |
| file_url | VARCHAR(500) | NULLABLE | |
| file_size | INT | NULLABLE | |
| file_type | VARCHAR(100) | NULLABLE | |
| submitted_at | DATETIME | NOT NULL | |
| is_late | TINYINT(1) | DEFAULT 0 | |
| grade | DECIMAL(8,2) | NULLABLE | |
| graded_by | BIGINT UNSIGNED | FK -> users, NULLABLE | |
| graded_at | DATETIME | NULLABLE | |
| feedback | TEXT | NULLABLE | |
| plagiarism_score | DECIMAL(5,2) | NULLABLE | |
| status | ENUM('submitted','graded','returned') | DEFAULT 'submitted' | |
| UNIQUE KEY | (assignment_id, user_id, attempt_number) | | |

#### `quizzes`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| module_id | BIGINT UNSIGNED | FK -> course_modules, NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| instructions | TEXT | NULLABLE | |
| quiz_type | ENUM('graded','practice','survey') | DEFAULT 'graded' | |
| max_attempts | INT | DEFAULT 1 | |
| time_limit_minutes | INT | NULLABLE | 0 = no limit |
| passing_score | DECIMAL(5,2) | NULLABLE | |
| shuffle_questions | TINYINT(1) | DEFAULT 0 | |
| shuffle_options | TINYINT(1) | DEFAULT 0 | |
| show_results_after | ENUM('never','immediately','after_due','after_attempt') | DEFAULT 'immediately' | |
| show_correct_answers | TINYINT(1) | DEFAULT 1 | |
| show_points | TINYINT(1) | DEFAULT 1 | |
| random_question_count | INT | NULLABLE | Subset of pool |
| due_at | DATETIME | NULLABLE | |
| available_from | DATETIME | NULLABLE | |
| available_until | DATETIME | NULLABLE | |
| is_visible | TINYINT(1) | DEFAULT 1 | |
| created_by | BIGINT UNSIGNED | FK -> users | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |
| deleted_at | DATETIME | NULLABLE | |

#### `quiz_questions`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| quiz_id | BIGINT UNSIGNED | FK -> quizzes, CASCADE | |
| question_type | ENUM('mcq','multiple_answer','true_false','short_answer','essay','matching','ordering','fill_blank') | NOT NULL | |
| question_text | TEXT | NOT NULL | |
| question_image | VARCHAR(500) | NULLABLE | |
| options | JSON | NULLABLE | For MCQ, matching, ordering |
| correct_answer | TEXT | NULLABLE | JSON for complex types |
| points | DECIMAL(8,2) | DEFAULT 1.00 | |
| feedback_correct | TEXT | NULLABLE | |
| feedback_incorrect | TEXT | NULLABLE | |
| order_index | INT | NOT NULL | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

#### `quiz_attempts`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| quiz_id | BIGINT UNSIGNED | FK -> quizzes, CASCADE | |
| user_id | BIGINT UNSIGNED | FK -> users, CASCADE | |
| attempt_number | INT | NOT NULL | |
| started_at | DATETIME | NOT NULL | |
| finished_at | DATETIME | NULLABLE | |
| time_taken_seconds | INT | NULLABLE | |
| score | DECIMAL(8,2) | NULLABLE | |
| max_score | DECIMAL(8,2) | NULLABLE | |
| percentage | DECIMAL(5,2) | NULLABLE | |
| passed | TINYINT(1) | NULLABLE | |
| status | ENUM('in_progress','completed','reviewed') | DEFAULT 'in_progress' | |
| ip_address | VARCHAR(45) | NULLABLE | |
| user_agent | TEXT | NULLABLE | |
| UNIQUE KEY | (quiz_id, user_id, attempt_number) | | |

#### `quiz_answers`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| attempt_id | BIGINT UNSIGNED | FK -> quiz_attempts, CASCADE | |
| question_id | BIGINT UNSIGNED | FK -> quiz_questions, CASCADE | |
| given_answer | TEXT | NOT NULL | |
| is_correct | TINYINT(1) | NULLABLE | |
| points_earned | DECIMAL(8,2) | NULLABLE | |
| points_possible | DECIMAL(8,2) | NULLABLE | |
| feedback | TEXT | NULLABLE | |
| time_spent_seconds | INT | NULLABLE | |

#### `gradebook_items`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| item_type | ENUM('assignment','quiz','exam','participation','manual') | NOT NULL | |
| item_id | BIGINT UNSIGNED | NULLABLE | FK to assignments/quizzes/exams |
| title | VARCHAR(255) | NOT NULL | |
| max_points | DECIMAL(8,2) | NOT NULL | |
| weight | DECIMAL(5,2) | DEFAULT 1.00 | |
| category | VARCHAR(100) | NULLABLE | e.g., "Homework", "Midterm" |
| due_date | DATETIME | NULLABLE | |
| is_extra_credit | TINYINT(1) | DEFAULT 0 | |
| is_published | TINYINT(1) | DEFAULT 0 | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

#### `gradebook`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| user_id | BIGINT UNSIGNED | FK -> users, CASCADE | |
| enrollment_id | BIGINT UNSIGNED | FK -> enrollments, CASCADE | |
| item_id | BIGINT UNSIGNED | FK -> gradebook_items, CASCADE | |
| score | DECIMAL(8,2) | NULLABLE | |
| max_score | DECIMAL(8,2) | NULLABLE | |
| percentage | DECIMAL(5,2) | NULLABLE | |
| letter_grade | VARCHAR(2) | NULLABLE | |
| override_score | DECIMAL(8,2) | NULLABLE | Manually overridden |
| graded_by | BIGINT UNSIGNED | FK -> users, NULLABLE | |
| graded_at | DATETIME | NULLABLE | |
| feedback | TEXT | NULLABLE | |
| UNIQUE KEY | (user_id, item_id) | | |

#### `discussions`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses, CASCADE | |
| module_id | BIGINT UNSIGNED | FK -> course_modules, NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| is_announcement | TINYINT(1) | DEFAULT 0 | |
| is_pinned | TINYINT(1) | DEFAULT 0 | |
| is_closed | TINYINT(1) | DEFAULT 0 | |
| created_by | BIGINT UNSIGNED | FK -> users | |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

#### `notifications`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| user_id | BIGINT UNSIGNED | FK -> users, CASCADE | |
| type | VARCHAR(50) | NOT NULL | |
| title | VARCHAR(255) | NOT NULL | |
| body | TEXT | NULLABLE | |
| data | JSON | NULLABLE | Payload |
| link | VARCHAR(500) | NULLABLE | Deep link |
| is_read | TINYINT(1) | DEFAULT 0 | |
| read_at | DATETIME | NULLABLE | |
| channel | ENUM('in_app','email','push') | DEFAULT 'in_app' | |
| created_at | DATETIME | | |

#### `exam_sync_log`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| course_id | BIGINT UNSIGNED | FK -> courses | |
| exam_portal_exam_id | INT | NOT NULL | Exam Portal exam ID |
| action | ENUM('sync_create','sync_update','sync_delete','grade_push','enrollment_sync') | NOT NULL | |
| status | ENUM('pending','success','failed','retrying') | DEFAULT 'pending' | |
| request_payload | JSON | NULLABLE | |
| response_payload | JSON | NULLABLE | |
| error_message | TEXT | NULLABLE | |
| retry_count | INT | DEFAULT 0 | |
| last_attempt_at | DATETIME | NULLABLE | |
| created_at | DATETIME | | |
| completed_at | DATETIME | NULLABLE | |

#### `audit_logs`
| Column | Type | Constraints | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PK AUTO_INCREMENT | |
| user_id | BIGINT UNSIGNED | FK -> users, NULLABLE | |
| user_type | VARCHAR(20) | NULLABLE | |
| action | VARCHAR(100) | NOT NULL | |
| entity_type | VARCHAR(100) | NOT NULL | |
| entity_id | BIGINT UNSIGNED | NULLABLE | |
| old_values | JSON | NULLABLE | |
| new_values | JSON | NULLABLE | |
| ip_address | VARCHAR(45) | NULLABLE | |
| user_agent | TEXT | NULLABLE | |
| request_id | CHAR(36) | NULLABLE | Correlation ID |
| created_at | DATETIME | | |

---

## 7. API ARCHITECTURE

### 7.1 Base URL Structure

```
https://lms.veritas.edu.ng/api/v1/
```

### 7.2 API Endpoints

#### Authentication
```
POST   /api/v1/auth/login              # Email + password login
POST   /api/v1/auth/logout             # Invalidate token
POST   /api/v1/auth/refresh            # Refresh JWT token
POST   /api/v1/auth/forgot-password    # Request password reset
POST   /api/v1/auth/reset-password     # Reset password
GET    /api/v1/auth/me                 # Current user profile
POST   /api/v1/auth/register           # Self-registration
```

#### Courses
```
GET    /api/v1/courses                 # List courses (paginated, filterable)
POST   /api/v1/courses                 # Create course (instructor/admin)
GET    /api/v1/courses/{id}            # Get course details
PUT    /api/v1/courses/{id}            # Update course
DELETE /api/v1/courses/{id}            # Soft-delete course
GET    /api/v1/courses/{id}/modules    # List modules in course
POST   /api/v1/courses/{id}/modules    # Add module
GET    /api/v1/courses/{id}/enrollments  # List enrollments
GET    /api/v1/courses/{id}/gradebook    # View gradebook
GET    /api/v1/courses/{id}/analytics    # Course analytics
POST   /api/v1/courses/{id}/publish      # Publish course
POST   /api/v1/courses/{id}/clone        # Clone course
```

#### Modules
```
GET    /api/v1/modules/{id}           # Get module details
PUT    /api/v1/modules/{id}           # Update module
DELETE /api/v1/modules/{id}           # Delete module
GET    /api/v1/modules/{id}/lessons    # List lessons in module
POST   /api/v1/modules/{id}/lessons    # Add lesson
PATCH  /api/v1/modules/{id}/reorder    # Reorder modules
```

#### Lessons
```
GET    /api/v1/lessons/{id}           # Get lesson
PUT    /api/v1/lessons/{id}           # Update lesson
DELETE /api/v1/lessons/{id}           # Delete lesson
POST   /api/v1/lessons/{id}/complete  # Mark as complete
GET    /api/v1/lessons/{id}/progress  # Get completion status
```

#### Enrollments
```
GET    /api/v1/enrollments            # My enrollments
POST   /api/v1/enrollments            # Enroll in course (self)
DELETE /api/v1/enrollments/{id}       # Drop course
GET    /api/v1/enrollments/{id}       # Enrollment details
PATCH  /api/v1/enrollments/{id}/grade # Update grade (instructor)
```

#### Assignments
```
GET    /api/v1/assignments            # List (filterable by course)
POST   /api/v1/assignments            # Create
GET    /api/v1/assignments/{id}       # Get details
PUT    /api/v1/assignments/{id}       # Update
DELETE /api/v1/assignments/{id}       # Delete
GET    /api/v1/assignments/{id}/submissions  # List submissions
POST   /api/v1/assignments/{id}/submit       # Submit
```

#### Submissions
```
GET    /api/v1/submissions/{id}       # Get submission
PUT    /api/v1/submissions/{id}/grade # Grade submission
POST   /api/v1/submissions/{id}/feedback  # Add feedback
```

#### Quizzes
```
GET    /api/v1/quizzes                # List (filterable)
POST   /api/v1/quizzes                # Create
GET    /api/v1/quizzes/{id}           # Get quiz (questions if allowed)
PUT    /api/v1/quizzes/{id}           # Update
DELETE /api/v1/quizzes/{id}           # Delete
POST   /api/v1/quizzes/{id}/start    # Start attempt
POST   /api/v1/quizzes/{id}/submit   # Submit attempt
GET    /api/v1/quizzes/{id}/attempts  # My attempts
GET    /api/v1/quizzes/{id}/results/{attempt_id}  # View results
```

#### Gradebook
```
GET    /api/v1/gradebook              # My grades across courses
GET    /api/v1/courses/{id}/gradebook # Course gradebook (instructor)
PUT    /api/v1/gradebook/items/{id}    # Update grade (instructor)
GET    /api/v1/gradebook/transcript   # Full transcript
```

#### Notifications
```
GET    /api/v1/notifications          # My notifications
PUT    /api/v1/notifications/{id}/read  # Mark as read
PUT    /api/v1/notifications/read-all   # Mark all as read
DELETE /api/v1/notifications/{id}       # Delete
```

#### Discussions
```
GET    /api/v1/discussions            # List (filterable by course)
POST   /api/v1/discussions            # Create
GET    /api/v1/discussions/{id}       # Get with replies
POST   /api/v1/discussions/{id}/reply  # Add reply
```

#### Analytics
```
GET    /api/v1/analytics/courses/{id} # Course analytics (instructor)
GET    /api/v1/analytics/students/{id} # Student analytics
GET    /api/v1/analytics/institutional # Admin institutional analytics
GET    /api/v1/analytics/predictions/{student_id}  # Predictive risk
```

#### Exam Bridge
```
POST   /api/v1/exam-bridge/sync-course    # Sync course to exam portal
POST   /api/v1/exam-bridge/sync-enrollments # Sync enrollments
GET    /api/v1/exam-bridge/exams/{course_id}  # List linked exams
POST   /api/v1/exam-bridge/push-grades    # Push grades from exam portal
GET    /api/v1/exam-bridge/sync-status    # Sync status dashboard
POST   /api/v1/exam-bridge/webhook        # Webhook receiver from exam portal
```

### 7.3 API Response Format

```json
// Success
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8
  }
}

// Error
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "title": ["The title field is required."]
    }
  },
  "request_id": "abc-123-def-456"
}
```

### 7.4 Authentication Headers

```
Authorization: Bearer <jwt_token>
X-Request-ID: <uuid>
X-API-Version: 1.0
Content-Type: application/json
Accept: application/json
```

---

## 8. AUTHENTICATION & AUTHORIZATION

### 8.1 JWT Authentication Flow

```
┌─────────┐         ┌──────────┐         ┌──────────┐
│ Client  │         │  API     │         │  DB      │
└────┬────┘         └────┬─────┘         └────┬─────┘
     │                   │                     │
     │  POST /auth/login │                     │
     │  {email,password} │                     │
     │──────────────────>│                     │
     │                   │  SELECT * FROM      │
     │                   │  users WHERE email  │
     │                   │────────────────────>│
     │                   │  User row           │
     │                   │<────────────────────│
     │                   │                     │
     │                   │  Verify bcrypt      │
     │                   │  Generate JWT       │
     │                   │  (access + refresh) │
     │                   │                     │
     │  {access_token,   │                     │
     │   refresh_token,  │                     │
     │   expires_in:3600}│                     │
     │<──────────────────│                     │
     │                   │                     │
     │  GET /courses     │                     │
     │  Authorization:   │                     │
     │  Bearer <token>   │                     │
     │──────────────────>│                     │
     │                   │  Verify JWT         │
     │                   │  Extract claims     │
     │                   │  Check RBAC         │
     │                   │  Process request    │
     │  {courses data}   │                     │
     │<──────────────────│                     │
```

### 8.2 JWT Token Structure

```json
// Access Token (1 hour)
{
  "sub": "user-uuid",
  "email": "john@veritas.edu.ng",
  "roles": ["student"],
  "permissions": ["courses:read", "enrollments:read"],
  "iat": 1700000000,
  "exp": 1700003600,
  "jti": "unique-token-id"
}

// Refresh Token (30 days)
{
  "sub": "user-uuid",
  "type": "refresh",
  "iat": 1700000000,
  "exp": 1702592000,
  "jti": "unique-refresh-id"
}
```

### 8.3 RBAC Matrix

| Permission | Student | TA | Instructor | Admin | Super Admin |
|---|---|---|---|---|---|
| courses:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| courses:create | | | ✓ | ✓ | ✓ |
| courses:update | | | own | ✓ | ✓ |
| courses:delete | | | | ✓ | ✓ |
| courses:publish | | | | ✓ | ✓ |
| modules:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| modules:manage | | | own | ✓ | ✓ |
| lessons:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| lessons:manage | | | own | ✓ | ✓ |
| enrollments:read | own | all | own_course | ✓ | ✓ |
| enrollments:manage | | | ✓ | ✓ | ✓ |
| assignments:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| assignments:submit | ✓ | | | | |
| assignments:grade | | ✓ | own | ✓ | ✓ |
| quizzes:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| quizzes:attempt | ✓ | | | | |
| quizzes:grade | | | own | ✓ | ✓ |
| gradebook:read | own | all | own_course | ✓ | ✓ |
| gradebook:manage | | | own | ✓ | ✓ |
| discussions:read | ✓ | ✓ | ✓ | ✓ | ✓ |
| discussions:post | ✓ | ✓ | ✓ | ✓ | ✓ |
| discussions:moderate | | ✓ | own | ✓ | ✓ |
| analytics:read | own | course | course | ✓ | ✓ |
| users:manage | | | | ✓ | ✓ |
| roles:manage | | | | | ✓ |
| system:config | | | | | ✓ |
| exams:sync | | | | ✓ | ✓ |

### 8.4 Role Hierarchy

```
super_admin ───> admin ───> instructor ───> ta ───> student
```

Higher roles inherit all permissions from lower roles, plus their own.

---

## 9. LAYERED SERVICE ARCHITECTURE

Each service follows the **Service-Repository** pattern:

```
┌─────────────────────────────────────────────────────┐
│                    CONTROLLER                        │
│  - HTTP request handling                             │
│  - Input validation                                  │
│  - Response formatting                               │
│  - No business logic                                 │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│                     SERVICE                          │
│  - Business logic & workflow                         │
│  - Orchestration across repositories                 │
│  - Event dispatching                                 │
│  - Transaction management                            │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│                   REPOSITORY                         │
│  - Data access (queries)                             │
│  - Scopes, filters, pagination                       │
│  - No business logic                                 │
└─────────────────────┬───────────────────────────────┘
                      │
┌─────────────────────┴───────────────────────────────┐
│                      MODEL                           │
│  - Active Record / Data Mapper                       │
│  - Relationships, scopes, mutators                   │
│  - Single table logic                                │
└─────────────────────────────────────────────────────┘
```

---

## 10. EXAM PORTAL INTEGRATION

### 10.1 Integration Flow (Detailed)

```
┌────────────┐         ┌──────────────┐         ┌──────────────────┐
│    LMS     │         │  Exam Bridge │         │   Exam Portal    │
│            │         │   Service    │         │                  │
└─────┬──────┘         └──────┬───────┘         └────────┬─────────┘
      │                       │                          │
      │  Course Created       │                          │
      │──────────────────────>│                          │
      │                       │  POST /api/exams/sync    │
      │                       │  {course_data}           │
      │                       │─────────────────────────>│
      │                       │                          │  Create exam
      │                       │  {exam_id, status}       │  record
      │                       │<─────────────────────────│
      │  Sync Complete        │                          │
      │<──────────────────────│                          │
      │                       │                          │
      │  Enroll Student       │                          │
      │──────────────────────>│                          │
      │                       │  POST /api/exams/enroll  │
      │                       │  {exam_id, student_ids}  │
      │                       │─────────────────────────>│
      │                       │                          │  Assign students
      │                       │  {success, count}         │
      │                       │<─────────────────────────│
      │  Sync Complete        │                          │
      │<──────────────────────│                          │
      │                       │                          │
      │                       │    WEBHOOK: Exam Complete │
      │                       │<─────────────────────────│
      │                       │  {exam_id, student_id,   │
      │                       │   score, status}         │
      │                       │                          │
      │  Process Grade        │                          │
      │<──────────────────────│                          │
      │  Update gradebook     │                          │
      │  Notify student       │                          │
      │                       │                          │
```

### 10.2 SSO Integration

```
┌──────────┐     ┌────────┐     ┌──────────────┐     ┌──────────────┐
│  Browser │     │  LMS   │     │  Exam Portal │     │  Exam Portal │
│          │     │        │     │  Auth API    │     │  Session     │
└────┬─────┘     └───┬────┘     └──────┬───────┘     └──────┬───────┘
     │                │                 │                    │
     │  Click "Take   │                 │                    │
     │  Exam"         │                 │                    │
     │───────────────>│                 │                    │
     │                │                 │                    │
     │                │  Verify JWT +   │                    │
     │                │  Exam access    │                    │
     │                │                 │                    │
     │                │  Generate SSO   │                    │
     │                │  Token (JWT)    │                    │
     │                │                 │                    │
     │  Redirect +    │                 │                    │
     │  SSO Token     │                 │                    │
     │<───────────────│                 │                    │
     │                │                 │                    │
     │  POST /sso/    │                 │                    │
     │  login         │                 │                    │
     │  {token}       │                 │                    │
     │─────────────────────────────────>│                    │
     │                │                 │  Verify JWT        │
     │                │                 │  Lookup/create     │
     │                │                 │  local session     │
     │                │                 │                    │
     │  Set session   │                 │                    │
     │  cookie +      │                 │                    │
     │  redirect to   │                 │                    │
     │  exam page     │                 │                    │
     │<─────────────────────────────────│                    │
     │                │                 │                    │
     │  Take Exam     │                 │                    │
     │─────────────────────────────────────────────────────────>│
     │                │                 │                    │
     │                │  WEBHOOK:       │                    │
     │                │  Exam Complete  │                    │
     │                │<─────────────────────────────────────────│
     │                │                 │                    │
     │  Notify +      │                 │                    │
     │  Update        │                 │                    │
     │  Gradebook     │                 │                    │
     │                │                 │                    │
```

### 10.3 Webhook Protocol (Exam Portal -> LMS)

```json
{
  "event": "exam.completed",
  "timestamp": "2026-05-25T14:30:00Z",
  "signature": "sha256=...",
  "payload": {
    "exam_id": 42,
    "student_id": 1567,
    "matric_no": "2023/001",
    "attempt_id": 891,
    "attempt_no": 1,
    "score": 85.5,
    "total": 100,
    "percentage": 85.5,
    "status": "completed",
    "started_at": "2026-05-25T13:00:00Z",
    "finished_at": "2026-05-25T14:30:00Z",
    "time_taken_seconds": 5400,
    "violation_count": 0,
    "proctor_status": "clean"
  }
}
```

### 10.4 Sync Endpoints (Exam Portal Side)

The existing exam portal should expose the following endpoints for LMS integration:

```
POST   /api/exams/sync              # Create/update exam from LMS data
POST   /api/exams/enroll            # Enroll students in exam
POST   /api/exams/webhook           # Send exam completion webhook
GET    /api/exams/{id}/results      # Get exam results for grade sync
GET    /api/students/lookup         # Lookup student by matric_no
POST   /api/sso/login               # SSO login token verification
GET    /api/health                  # Health check
```

---

## 11. EVENT-DRIVEN ARCHITECTURE

### 11.1 Event Definitions

| Event | Publisher | Consumers | Description |
|---|---|---|---|
| `course.created` | Course Service | Sync, Analytics, Notifications | |
| `course.updated` | Course Service | Sync, Analytics | |
| `course.deleted` | Course Service | Sync | |
| `enrollment.created` | Enrollment Service | Sync, Notifications, Analytics | |
| `enrollment.completed` | Enrollment Service | Gradebook, Transcript | |
| `lesson.completed` | Progress Service | Analytics, Gamification | |
| `submission.submitted` | Assignment Service | Notifications, Analytics | |
| `submission.graded` | Assignment Service | Gradebook, Notifications | |
| `quiz.attempted` | Quiz Service | Analytics, Gradebook | |
| `quiz.graded` | Quiz Service | Gradebook, Notifications | |
| `grade.updated` | Gradebook Service | Analytics, Transcript, Notifications | |
| `exam.completed` | Exam Bridge | Gradebook, Analytics, Notifications | |
| `exam.synced` | Exam Bridge | LMS Core | |
| `notification.sent` | Notification Service | Analytics | |
| `discussion.created` | Discussion Service | Notifications | |
| `user.registered` | Auth Service | Analytics, Notifications | |

### 11.2 Event Payload Structure

```json
{
  "id": "evt_abc123def456",
  "type": "exam.completed",
  "version": "1.0",
  "timestamp": "2026-05-25T14:30:00.000Z",
  "source": "exam_bridge_service",
  "correlation_id": "corr_xyz789",
  "data": {
    "exam_id": 42,
    "student_id": 1567,
    "score": 85.5
  }
}
```

### 11.3 Event Processing Pipeline

```
Publisher ──> Event Bus (RabbitMQ) ──> Exchange ──> Queue ──> Consumer
                      │
                      ├───> analytics_queue ──> Analytics Service
                      ├───> sync_queue ──> Exam Bridge Service
                      ├───> notification_queue ──> Notification Service
                      └───> gradebook_queue ──> Gradebook Service
```

---

## 12. CACHING STRATEGY

### 12.1 Redis Cache Layers

```
┌─────────────────────────────────────────────────────────┐
│                    REDIS CACHE LAYERS                     │
│                                                           │
│  L1: Session Cache                                        │
│  ─────────────────                                        │
│  Key: sessions:{session_id}                               │
│  TTL: 3600s (1 hour)                                      │
│  Content: User session data, roles, permissions           │
│                                                           │
│  L2: Data Cache                                           │
│  ──────────────                                           │
│  Key: courses:{id}, lessons:{id}, etc.                    │
│  TTL: 600s (10 min) / invalidate on update                │
│  Content: Serialized model data                           │
│                                                           │
│  L3: Query Cache                                          │
│  ──────────────                                           │
│  Key: query:{md5_of_sql_and_params}                       │
│  TTL: 300s (5 min)                                        │
│  Content: Paginated query results                         │
│                                                           │
│  L4: Rate Limit Cache                                     │
│  ──────────────────                                       │
│  Key: ratelimit:{route}:{ip}:{user_id}                   │
│  TTL: dynamic                                             │
│  Content: Request count, window start                     │
│                                                           │
│  L5: Counter Cache                                        │
│  ────────────────                                         │
│  Key: counter:{entity}:{action}:{date}                   │
│  TTL: 86400s (24h)                                       │
│  Content: Incremental counters for analytics              │
│                                                           │
│  L6: Lock Cache (Distributed Mutex)                       │
│  ──────────────────────────────                           │
│  Key: lock:{resource}:{id}                               │
│  TTL: 30s (auto-expire)                                  │
│  Content: Lock holder ID                                  │
└─────────────────────────────────────────────────────────┘
```

### 12.2 Cache Invalidation Strategy

- **Write-Through:** On every write operation, update DB + invalidate cache
- **Tag-Based Invalidation:** All course-related caches tagged with `course:{id}`
- **Bulk Invalidation:** On course update, invalidate all caches matching `courses:*`,`modules:*`,`lessons:*`,`enrollments:*`
- **Stale-While-Revalidate:** Serve stale cache while async refreshing

---

## 13. QUEUE & ASYNC PROCESSING

### 13.1 Queue Architecture (RabbitMQ)

```
┌──────────────────────────────────────────────────────────────┐
│                     RABBITMQ ARCHITECTURE                     │
│                                                               │
│  Exchanges:                                                   │
│  ──────────                                                   │
│  lms.events       (Topic)  - All domain events               │
│  lms.dlx          (Direct) - Dead letter queue               │
│  lms.delayed      (Direct) - Delayed processing              │
│                                                               │
│  Queues:                                                      │
│  ──────                                                       │
│  lms.notifications        - Email/push notification delivery  │
│  lms.analytics            - Analytics event processing        │
│  lms.sync                 - Exam Portal sync jobs             │
│  lms.gradebook            - Grade recalculation               │
│  lms.transcript           - Transcript generation             │
│  lms.reports              - Report generation                 │
│  lms.media                - Media transcoding                 │
│  lms.dlx                  - Failed message retry              │
│                                                               │
│  Dead Letter Strategy:                                        │
│  ─────────────────────                                       │
│  1. Message fails -> DLX                                     │
│  2. Retry after delay (exponential: 10s, 30s, 60s, 5min)    │
│  3. Max 5 retries -> permanent failure queue                 │
│  4. Alert sent to operations team                            │
└──────────────────────────────────────────────────────────────┘
```

### 13.2 Queue Workflow Examples

```
ENROLLMENT WORKFLOW:
  1. Enrollment created
  2. Event: enrollment.created -> lms.events
  3. Consumers:
     a. Sync Service: Create student in Exam Portal
     b. Notification Service: Send welcome email
     c. Analytics Service: Record enrollment event
     d. Gradebook Service: Initialize empty gradebook

EXAM COMPLETION WORKFLOW:
  1. Exam Portal sends webhook
  2. Exam Bridge receives and validates signature
  3. Publish event: exam.completed -> lms.events
  4. Consumers:
     a. Gradebook Service: Update gradebook with exam score
     b. Analytics Service: Record exam analytics
     c. Notification Service: Notify student of result
     d. Transcript Service: Update transcript record
```

---

## 14. SECURITY ARCHITECTURE

### 14.1 Security Layers

```
┌─────────────────────────────────────────────────────────┐
│                 SECURITY ARCHITECTURE                     │
│                                                           │
│  L7: APPLICATION SECURITY                                 │
│  ─────────────────────────                                 │
│  ├── Input validation (server-side)                       │
│  ├── Output encoding (XSS prevention)                     │
│  ├── CSRF tokens on all state-changing requests          │
│  ├── SQL injection prevention (prepared statements)      │
│  └── File upload scanning (ClamAV)                       │
│                                                           │
│  L6: AUTHENTICATION & AUTHORIZATION                      │
│  ───────────────────────────────────────                 │
│  ├── JWT with short expiry (1 hour)                      │
│  ├── Refresh tokens (30 days, rotating)                  │
│  ├── RBAC with fine-grained permissions                  │
│  ├── 2FA for admin accounts                               │
│  ├── OAuth2 for third-party integrations                 │
│  └── Session management with invalidation                │
│                                                           │
│  L5: API SECURITY                                         │
│  ─────────────────                                         │
│  ├── Rate limiting (100 req/min per user)               │
│  ├── API key validation (machine-to-machine)             │
│  ├── Request signing (HMAC-SHA256)                       │
│  ├── CORS strict configuration                           │
│  └── API versioning with deprecation headers             │
│                                                           │
│  L4: DATA SECURITY                                        │
│  ─────────────────                                        │
│  ├── Encryption at rest (AES-256)                        │
│  ├── Encryption in transit (TLS 1.3)                     │
│  ├── Database backups encrypted                          │
│  ├── PII data masked in logs                             │
│  └── Data retention policies enforced                    │
│                                                           │
│  L3: NETWORK SECURITY                                     │
│  ─────────────────────                                    │
│  ├── WAF (ModSecurity)                                    │
│  ├── DDoS protection                                      │
│  ├── VPN for admin access                                 │
│  ├── IP whitelisting for sensitive endpoints              │
│  └── Internal network segmentation                        │
│                                                           │
│  L2: MONITORING & DETECTION                               │
│  ───────────────────────────                              │
│  ├── Audit logging (all data changes)                     │
│  ├── Anomaly detection (failed logins, unusual patterns)  │
│  ├── SIEM integration                                     │
│  ├── Real-time alerting                                   │
│  └── Penetration testing (quarterly)                      │
│                                                           │
│  L1: COMPLIANCE & GOVERNANCE                              │
│  ─────────────────────────────                            │
│  ├── FERPA compliance (student data privacy)              │
│  ├── GDPR compliance (right to erasure, data portability) │
│  ├── WCAG 2.1 AA (accessibility)                         │
│  ├── SOC 2 type II controls                               │
│  └── Regular security audits                              │
└─────────────────────────────────────────────────────────┘
```

### 14.2 Security Headers

```nginx
# Recommended NGINX Security Headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "
    default-src 'self';
    script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;
    style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com;
    img-src 'self' data: blob: https:;
    font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com;
    connect-src 'self' ws: wss: https:;
    media-src 'self' blob: https:;
    frame-src 'self' https://www.youtube.com;
" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header Permissions-Policy "camera=(self), microphone=(self), fullscreen=(self)" always;
```

---

## 15. DEVOPS & DEPLOYMENT

### 15.1 Docker Compose (Development)

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/conf.d:/etc/nginx/conf.d
      - ../:/var/www/html
    depends_on:
      - php
      - lms-api

  php:
    build:
      context: ./docker/php
    volumes:
      - ../:/var/www/html
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
      - RABBITMQ_HOST=rabbitmq

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: lms_db
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init:/docker-entrypoint-initdb.d

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  rabbitmq:
    image: rabbitmq:3-management-alpine
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASS}

  elasticsearch:
    image: elasticsearch:8.11.0
    environment:
      - discovery.type=single-node
      - ES_JAVA_OPTS=-Xms512m -Xmx512m
    ports:
      - "9200:9200"
    volumes:
      - elasticsearch_data:/usr/share/elasticsearch/data

  minio:
    image: minio/minio
    ports:
      - "9000:9000"
      - "9001:9001"
    command: server /data --console-address ":9001"
    volumes:
      - minio_data:/data
    environment:
      MINIO_ROOT_USER: ${MINIO_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_PASS}

volumes:
  mysql_data:
  redis_data:
  elasticsearch_data:
  minio_data:
```

### 15.2 Kubernetes Deployment (Production)

```yaml
# api-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: lms-api
  namespace: veritas-lms
spec:
  replicas: 5
  selector:
    matchLabels:
      app: lms-api
  template:
    metadata:
      labels:
        app: lms-api
    spec:
      containers:
      - name: lms-api
        image: veritas/lms-api:latest
        ports:
        - containerPort: 8080
        env:
        - name: APP_ENV
          value: "production"
        - name: DB_HOST
          valueFrom:
            secretKeyRef:
              name: db-secret
              key: host
        - name: REDIS_HOST
          value: "redis-service"
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /api/v1/health
            port: 8080
          initialDelaySeconds: 10
          periodSeconds: 15
        readinessProbe:
          httpGet:
            path: /api/v1/health/ready
            port: 8080
          initialDelaySeconds: 5
          periodSeconds: 10
---
apiVersion: v1
kind: Service
metadata:
  name: lms-api-service
spec:
  selector:
    app: lms-api
  ports:
  - port: 80
    targetPort: 8080
  type: ClusterIP
```

### 15.3 CI/CD Pipeline (GitHub Actions)

```yaml
name: LMS CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          tools: phpcs, phpstan
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
      - name: Run PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse src/ --level=max
      - name: Run Tests
        run: vendor/bin/phpunit --coverage-clover=coverage.xml
      - name: Security Check
        run: vendor/bin/security-checker security:check composer.lock

  build:
    needs: quality
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Build Docker image
        run: |
          docker build -t veritas/lms-api:${{ github.sha }} .
          docker tag veritas/lms-api:${{ github.sha }} veritas/lms-api:latest
      - name: Push to registry
        run: |
          docker login -u ${{ secrets.DOCKER_USER }} -p ${{ secrets.DOCKER_PASS }}
          docker push veritas/lms-api:${{ github.sha }}
          docker push veritas/lms-api:latest

  deploy-staging:
    needs: build
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to staging
        run: |
          kubectl set image deployment/lms-api lms-api=veritas/lms-api:${{ github.sha }} -n veritas-lms-staging
          kubectl rollout status deployment/lms-api -n veritas-lms-staging

  deploy-production:
    needs: build
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: production
    steps:
      - name: Deploy to production
        run: |
          kubectl set image deployment/lms-api lms-api=veritas/lms-api:${{ github.sha }} -n veritas-lms
          kubectl rollout status deployment/lms-api -n veritas-lms
      - name: Health check
        run: |
          sleep 10
          curl -f https://lms.veritas.edu.ng/api/v1/health
```

---

## 16. MONITORING & OBSERVABILITY

### 16.1 Monitoring Stack

```
┌────────────────────────────────────────────────────────────┐
│                    MONITORING STACK                          │
│                                                              │
│  METRICS:                                                    │
│  ├── Prometheus: System + application metrics               │
│  │   ├── Request rate, latency, error rate (RED method)     │
│  │   ├── Database query performance                         │
│  │   ├── Queue depth and processing time                    │
│  │   ├── Cache hit/miss rates                                │
│  │   └── Resource usage (CPU, memory, disk, network)        │
│  └── Grafana: Dashboards + alerting                          │
│                                                              │
│  LOGGING:                                                    │
│  ├── Filebeat: Ship logs from all containers                │
│  ├── Logstash: Parse and transform                          │
│  ├── Elasticsearch: Store and index                          │
│  └── Kibana: Explore and visualize                          │
│                                                              │
│  TRACING:                                                    │
│  ├── OpenTelemetry: Instrument all services                 │
│  ├── Jaeger: Distributed tracing                             │
│  └── Correlation IDs across all requests                    │
│                                                              │
│  ALERTING:                                                   │
│  ├── AlertManager: Route and silence                        │
│  ├── PagerDuty: On-call notifications                       │
│  ├── Slack: Team notifications                              │
│  └── Email: Admin notifications                             │
│                                                              │
│  APM:                                                        │
│  ├── Sentry: Error tracking + performance                   │
│  └── Custom dashboards for business metrics                 │
└────────────────────────────────────────────────────────────┘
```

### 16.2 Key Metrics to Monitor

| Category | Metric | Target | Critical |
|---|---|---|---|
| API | P95 Latency | <300ms | >1s |
| API | Error Rate | <0.1% | >1% |
| API | Requests/sec | N/A | >500 |
| DB | Query time (P95) | <50ms | >200ms |
| DB | Connections | <80% | >90% |
| Cache | Hit Rate | >80% | <50% |
| Queue | Processing Time | <1s | >30s |
| Queue | Queue Depth | <100 | >1000 |
| Auth | Failed Logins | <5/min | >20/min |

---

## 17. DISASTER RECOVERY

### 17.1 Backup Strategy

```
┌────────────────────────────────────────────────────────────┐
│                    BACKUP STRATEGY                           │
│                                                              │
│  DATABASE:                                                   │
│  ├── Full backup: Daily (00:00 UTC)                         │
│  ├── Incremental: Every hour                                │
│  ├── Retention: 30 daily, 12 monthly, 7 yearly             │
│  ├── Replication: 1 primary + 2 replicas (AZ distributed)  │
│  └── Point-in-time recovery: Last 7 days                   │
│                                                              │
│  FILES (S3/MinIO):                                           │
│  ├── Cross-region replication                               │
│  ├── Versioning enabled                                     │
│  └── Lifecycle: Standard -> Glacier after 90 days           │
│                                                              │
│  CONFIGURATION:                                              │
│  ├── Infrastructure as Code (Terraform)                     │
│  ├── Configs in Git                                         │
│  └── Secrets in Vault                                       │
│                                                              │
│  APPLICATION:                                                │
│  ├── Docker images in registry                             │
│  ├── Immutable deployment                                   │
│  └── Blue-green deployment                                  │
└────────────────────────────────────────────────────────────┘
```

### 17.2 Recovery Time Objectives

| Tier | RTO | RPO | Example |
|---|---|---|---|
| Critical | 15 min | 5 min | Authentication, Gradebook |
| High | 1 hour | 1 hour | Course content, Quizzes |
| Medium | 4 hours | 4 hours | Analytics, Reports |
| Low | 24 hours | 24 hours | Archives, Logs |

### 17.3 Disaster Recovery Playbook

```
1. DETECTION
   ├── Monitoring alert triggers
   ├── On-call engineer acknowledges (5 min)
   └── Incident severity classified

2. ASSESSMENT (10 min)
   ├── Check if primary DB is accessible
   ├── Check if application servers are healthy
   ├── Check if cache layer is functional
   └── Determine scope of outage

3. FAILOVER (15-30 min)
   ├── Promote read replica to primary
   ├── Update connection strings
   ├── Verify data integrity
   └── Route traffic to healthy servers

4. RECOVERY (1-4 hours)
   ├── Fix root cause
   ├── Resync data from replicas
   ├── Verify all services operational
   └── Run integration tests

5. POST-MORTEM (24-48 hours)
   ├── Root cause analysis
   ├── Action items
   ├── Update runbooks
   └── Implement preventive measures
```

---

## 18. PERFORMANCE OPTIMIZATION

### 18.1 Database Optimization

- **Indexing:** All foreign keys, frequently queried columns, and sort fields indexed
- **Query Optimization:** 
  - N+1 query prevention (eager loading)
  - Pagination using cursor-based for large datasets
  - Materialized views for analytics
  - Partitioning for `audit_logs` and `analytics_events` by month
- **Connection Pooling:** Use persistent connections with configurable pool size
- **Read/Write Splitting:** Writes to primary, reads from replicas

### 18.2 Caching Strategy

- **Application Cache:** Redis for session data, model caches, query results
- **HTTP Cache:** Browser caching for static assets (1 year), API responses (ETag)
- **CDN:** CloudFront for video, images, H5P packages, SCORM packages
- **OpCache:** PHP OPcache with validated timestamps

### 18.3 Code Optimization

- **Lazy Loading:** Course modules and lessons loaded on demand
- **Async Processing:** Email, notifications, analytics all async via queue
- **Batch Operations:** Enrollment sync, grade calculation in batches of 500
- **Minification:** CSS/JS minified, bundled, and versioned

---

## 19. AI INTEGRATION STRATEGY

### 19.1 Current AI-Ready Architecture

- **Event-driven analytics pipeline** — all user interactions emit events
- **Feature store** — Redis-based feature storage for ML models
- **Model serving** — Python FastAPI service for ML inference
- **Feedback loops** — student outcomes feed back into prediction models

### 19.2 Future AI Features Roadmap

| Feature | Description | Data Required | Priority |
|---|---|---|---|
| **Adaptive Learning** | Content difficulty adjusts based on performance | Quiz attempts, time per lesson, engagement | High |
| **AI Tutoring Assistant** | 24/7 chatbot answering course questions | Course content, FAQs, student queries | High |
| **Predictive Risk Detection** | Identify at-risk students before they fail | Grades, engagement, attendance, history | High |
| **AI Grading Assistant** | Auto-grade essays and open-ended answers | Rubrics, sample essays, instructor grades | Medium |
| **Personalized Recommendations** | Suggest courses, content, study paths | Learning history, goals, performance | Medium |
| **Plagiarism Detection** | Check submissions against academic sources | Submission text, web corpus, peer submissions | Medium |
| **Voice AI for VIVA** | Voice-based interaction for oral exams | Audio recordings, speech-to-text, NLP | Low |
| **Learning Path Optimization** | ML-optimized curriculum sequencing | All student interaction data | Low |

### 19.3 ML Architecture (Future)

```
┌──────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────┐
│ Feature  │     │  Training     │     │  Model        │     │  Serving  │
│ Store    │────>│  Pipeline     │────>│  Registry     │────>│  API      │
│ (Redis)  │     │  (Python)     │     │  (MLflow)     │     │  (K8s)    │
└──────────┘     └──────────────┘     └──────────────┘     └──────────┘
     │                                                        │
     │  Real-time features                                   │  Predictions
     │                                                        │
     ▼                                                        ▼
┌──────────────────────────────────────────────────────────────────────┐
│                       EVENT BUS (RabbitMQ)                            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│  │Click     │ │Quiz      │ │Time      │ │Submission│ │Enrollment│   │
│  │Events    │ │Attempts  │ │Spent     │ │Data      │ │Data      │   │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 20. IMPLEMENTATION ROADMAP

### Phase 1: Foundation (Weeks 1-3)

| Sprint | Deliverables |
|---|---|
| Sprint 1 | Database schema creation, Migration runner, Core framework (Router, Database, Model, Request, Response) |
| Sprint 2 | Authentication (JWT + OAuth2), RBAC system, User CRUD, Role/Permission management |
| Sprint 3 | API documentation (OpenAPI), Basic frontend SPA shell, Routing system |

**Milestone:** Working auth + API framework

### Phase 2: Core LMS (Weeks 4-6)

| Sprint | Deliverables |
|---|---|
| Sprint 4 | Course CRUD, Course categories, Course publishing workflow |
| Sprint 5 | Module management, Lesson CRUD, Rich text editor integration |
| Sprint 6 | Enrollment system, Course catalog, Student dashboard |

**Milestone:** Students can browse courses, enroll, and view lessons

### Phase 3: Assessments & Gradebook (Weeks 7-9)

| Sprint | Deliverables |
|---|---|
| Sprint 7 | Assignment CRUD, Submission system, File upload handling |
| Sprint 8 | Quiz engine (question types, attempts, auto-grading), Question bank |
| Sprint 9 | Gradebook, Grade calculation, Weight management, Transcript |

**Milestone:** Complete assessment cycle within LMS

### Phase 4: Communication (Weeks 10-11)

| Sprint | Deliverables |
|---|---|
| Sprint 10 | Discussion forums, Direct messaging, Announcements |
| Sprint 11 | Notification system (in-app, email, push), Digest preferences |

**Milestone:** Full communication features

### Phase 5: Exam Portal Integration (Weeks 12-14)

| Sprint | Deliverables |
|---|---|
| Sprint 12 | SSO implementation (JWT-based), Exam Bridge API endpoints on LMS side |
| Sprint 13 | Exam Portal webhook receiver, Grade sync, Exam enrollment sync |
| Sprint 14 | Webhook endpoints on Exam Portal side, Sync dashboard, Retry logic |

**Milestone:** Seamless LMS -> Exam Portal -> LMS flow

### Phase 6: Analytics & Reporting (Weeks 15-16)

| Sprint | Deliverables |
|---|---|
| Sprint 15 | Student analytics dashboard, Instructor analytics, Engagement metrics |
| Sprint 16 | Institutional analytics, Predictive risk scoring, Report export (CSV/PDF) |

**Milestone:** Full analytics suite

### Phase 7: DevOps & Deployment (Weeks 17-18)

| Sprint | Deliverables |
|---|---|
| Sprint 17 | Docker compose for development, Kubernetes manifests for staging/production |
| Sprint 18 | CI/CD pipeline, Monitoring setup (Prometheus/Grafana), Logging (ELK), Load testing |

**Milestone:** Production-ready deployment

---

## APPENDIX A: COMPLETE API DOCUMENTATION

See `routes/api.php` for all route definitions with middleware, validation rules, and controller methods.

## APPENDIX B: PERFORMANCE BUDGET

| Metric | Target | Method |
|---|---|---|
| Time to First Byte (TTFB) | <200ms | CDN + caching + opcache |
| API Response (P50) | <100ms | Query optimization + caching |
| API Response (P95) | <500ms | Pagination + eager loading |
| Page Load | <2s | Lazy loading + async |
| DB Queries per Request | <10 | Eager loading + caching |
| Memory per Request | <50MB | Stream processing |
| Concurrent Users | 10,000 | Horizontal scaling |

## APPENDIX C: ERROR CODES

| Code | HTTP Status | Description |
|---|---|---|
| AUTH_001 | 401 | Token expired |
| AUTH_002 | 401 | Invalid token |
| AUTH_003 | 403 | Insufficient permissions |
| AUTH_004 | 429 | Too many login attempts |
| VAL_001 | 422 | Validation failed |
| VAL_002 | 422 | Invalid input format |
| RES_001 | 404 | Resource not found |
| RES_002 | 409 | Resource conflict |
| SYS_001 | 500 | Internal server error |
| SYS_002 | 503 | Service unavailable |
| INT_001 | 502 | Integration service error |
| INT_002 | 504 | Integration timeout |

---

*End of Architecture Document*

**Next Steps:** Begin implementation with Phase 1, Sprint 1 — Database schema creation and core framework.
