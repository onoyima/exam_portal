You are a Senior Enterprise Software Architect, Principal Backend Engineer, LMS Systems Specialist, and Educational Technology Infrastructure Expert.

Your task is to design and implement a COMPLETE WORLD-CLASS ENTERPRISE LEARNING MANAGEMENT SYSTEM (LMS) fully integrated with the existing Veritas University Exam Portal (NextGen Assessment System).

This project must be treated as a REAL PRODUCTION-GRADE EDUCATIONAL ECOSYSTEM intended for deployment at enterprise university scale with support for 10,000+ concurrent users, high availability infrastructure, secure examination integration, analytics, scalability, modularity, and future AI extensibility.

The solution must be developed with EXTREME PROFESSIONALISM and must include:

- Enterprise-grade architecture
- Production-ready backend engineering
- Complete database architecture
- Scalable API design
- LMS + Exam Portal synchronization
- Security hardening
- Advanced analytics
- Role-based workflows
- Real-time event systems
- Modular microservice-oriented design
- AI-ready extensibility
- CI/CD and DevOps architecture
- Monitoring and observability
- High-performance caching and queues
- CDN and media handling
- Full implementation roadmap

========================================================
PROJECT CONTEXT
========================================================

Institution:
Veritas University Abuja

Target System:
Integrated Learning Management System (ILMS)

Primary Goal:
Create a centralized educational ecosystem where:

- Students learn through the LMS
- Instructors manage courses and content
- Administrators manage institutional operations
- Exams are handled through the existing Veritas Exam Portal
- Grades synchronize automatically
- Analytics provide deep institutional insights
- Learning outcomes map directly to assessment performance

========================================================
CORE OBJECTIVES
========================================================

The system MUST achieve:

1. Unified Educational Platform
   - Single dashboard for students
   - Unified experience between learning and assessment
   - SSO integration across all systems

2. Enterprise Content Delivery
   - Videos
   - PDFs
   - Interactive content
   - Simulations
   - H5P modules
   - Virtual labs
   - Live classes
   - Discussion systems

3. Assessment Ecosystem
   - LMS formative assessments
   - Integration with Exam Portal for summative exams
   - Grade synchronization
   - Transcript generation
   - GPA computation

4. Advanced Analytics
   - Student performance tracking
   - Predictive risk detection
   - Instructor insights
   - Institutional reporting
   - Engagement metrics
   - Learning behavior analysis

5. Scalability
   - Support 10,000+ concurrent users
   - <500ms API response
   - High availability architecture
   - Distributed infrastructure

6. Security & Compliance
   - FERPA
   - GDPR
   - WCAG 2.1 AA
   - RBAC
   - Audit logging
   - Encryption
   - Secure API communication

========================================================
SYSTEM ARCHITECTURE REQUIREMENTS
========================================================

Design the architecture with the following layers:

1. CLIENT LAYER
   - Student Portal
   - Instructor Portal
   - Admin Dashboard
   - Responsive Web App
   - Mobile-ready architecture

Frontend Technologies:
- React.js / Next.js
- TypeScript
- TailwindCSS
- Zustand/Redux
- React Query
- Socket.io client

2. API GATEWAY
   - NGINX
   - SSL termination
   - Rate limiting
   - Request logging
   - API versioning
   - Load balancing
   - DDoS protection

3. BACKEND SERVICES

Design modular services for:

A. LMS Core Service
- Courses
- Lessons
- Modules
- Enrollments
- Gradebook
- Progress tracking

B. Content Service
- Media uploads
- Video transcoding
- CDN management
- Version control
- File optimization

C. Analytics Service
- Engagement tracking
- Predictive analysis
- Learning analytics
- Reporting engine

D. Integration Service
- OAuth2
- SSO
- LTI 1.3
- Webhooks
- Third-party APIs

E. Exam Bridge Service
- Exam synchronization
- Grade synchronization
- Exam enrollment
- Transcript integration

Preferred Backend Stack:
- Node.js (NestJS preferred)
- PHP Laravel acceptable where necessary
- REST + GraphQL hybrid
- WebSocket support

4. DATA LAYER

Primary Database:
- MySQL 8
- InnoDB Cluster
- Read replicas
- Query optimization

Cache Layer:
- Redis
- Session management
- Queue management
- Real-time caching

Search:
- Elasticsearch

Storage:
- AWS S3 / MinIO

Queue:
- RabbitMQ

========================================================
USER ROLES
========================================================

Implement FULL workflow support for:

1. STUDENTS
- Registration
- Course enrollment
- Content consumption
- Quiz participation
- Assignment submissions
- Discussion participation
- Messaging
- Exam access
- Analytics dashboard
- Notifications

2. INSTRUCTORS
- Course creation
- Module management
- Lesson publishing
- Assignment creation
- Quiz management
- Grading
- Student analytics
- Announcements
- Zoom/live session management

3. ADMINISTRATORS
- User management
- Course approvals
- Institutional analytics
- System monitoring
- Audit logs
- Role permissions
- Integration settings

4. TEACHING ASSISTANTS
- Grading support
- Discussion moderation
- Student support

========================================================
COURSE MANAGEMENT REQUIREMENTS
========================================================

The LMS must support:

- Course creation
- Categories
- Prerequisites
- Learning outcomes
- Course cloning
- Syllabus management
- Multiple instructors
- Draft/public/private modes
- Course scheduling
- Access control

Course Structure:
Course
 ├── Modules
 │    ├── Lessons
 │    ├── Quizzes
 │    ├── Assignments
 │    ├── Discussions
 │    └── Resources

========================================================
CONTENT MANAGEMENT REQUIREMENTS
========================================================

Support:

1. Text Content
- Rich HTML editor
- Markdown
- LaTeX equations

2. Video Content
- Uploads
- YouTube/Vimeo embedding
- HLS streaming
- Adaptive bitrate
- Analytics heatmaps
- Captions/transcripts

3. Interactive Content
- H5P
- Simulations
- Branching learning
- Virtual labs

4. Documents
- PDF
- DOCX
- PPTX
- ZIP
- Audio files

5. Version Control
- Content history
- Rollback support

========================================================
ASSESSMENT REQUIREMENTS
========================================================

FORMATIVE ASSESSMENTS (Inside LMS)
- MCQ
- Essay
- Short answer
- Matching
- Fill in the blank
- Ordering
- Hotspot
- Timed quizzes
- Randomization
- Question banks
- Auto grading

SUMMATIVE ASSESSMENTS (Exam Portal)
- Objective exams
- Practical exams
- Viva exams
- Proctored exams

========================================================
EXAM PORTAL INTEGRATION
========================================================

The LMS MUST integrate with the existing Veritas Exam Portal.

Implement:

1. SSO Authentication
2. Automatic exam enrollment
3. Exam scheduling sync
4. Grade synchronization
5. Learning outcome mapping
6. Transcript generation
7. Real-time webhooks
8. Sync retry mechanisms
9. Conflict resolution logic
10. Audit logging

Integration Flow:

1. Student enrolls in course
2. LMS syncs student to Exam Portal
3. Student studies LMS content
4. Student launches exam through LMS
5. SSO redirects to Exam Portal
6. Exam completed
7. Exam Portal sends webhook to LMS
8. LMS recalculates grades
9. Transcript updated
10. Student notified

========================================================
DATABASE REQUIREMENTS
========================================================

Design FULL enterprise database schema for:

- users
- roles
- permissions
- courses
- modules
- lessons
- enrollments
- assignments
- submissions
- quizzes
- quiz_attempts
- grades
- gradebook_items
- analytics
- notifications
- discussions
- messaging
- exam mappings
- sync logs
- API tokens
- media files
- audit trails

Requirements:
- Soft deletes
- Proper indexing
- Foreign keys
- Audit history
- Query optimization
- Scalability

========================================================
ANALYTICS REQUIREMENTS
========================================================

Implement:

STUDENT ANALYTICS
- Course progress
- Time spent
- Weakness detection
- Recommendations
- Performance trends

INSTRUCTOR ANALYTICS
- Class performance
- At-risk students
- Engagement metrics
- Content effectiveness
- Assessment analysis

ADMIN ANALYTICS
- Institutional KPIs
- GPA distribution
- Enrollment trends
- Usage metrics
- Infrastructure health

PREDICTIVE ANALYTICS
- Pass/fail prediction
- Retention prediction
- Risk scoring

========================================================
COMMUNICATION FEATURES
========================================================

Implement:

- Discussion forums
- Direct messaging
- Group messaging
- Announcements
- Push notifications
- Email notifications
- Zoom integration
- Live sessions
- Q&A sessions

========================================================
NOTIFICATION SYSTEM
========================================================

Support:

- In-app notifications
- Email notifications
- SMS optional
- Push notifications
- Digest preferences
- Quiet hours
- Priority alerts

========================================================
SECURITY REQUIREMENTS
========================================================

Implement enterprise security:

- JWT authentication
- Refresh tokens
- OAuth2
- RBAC
- Rate limiting
- CSRF protection
- XSS prevention
- SQL injection prevention
- File scanning
- Encryption at rest
- Encryption in transit
- Audit logging
- Session expiration
- 2FA for admins

========================================================
DEVOPS & INFRASTRUCTURE
========================================================

Design complete infrastructure for:

- Docker
- Kubernetes
- CI/CD
- GitHub Actions
- NGINX
- AWS deployment
- Auto scaling
- Monitoring
- Logging
- Disaster recovery
- Backup systems
- Observability

Monitoring Stack:
- ELK Stack
- Datadog
- Sentry
- Prometheus
- Grafana

========================================================
API REQUIREMENTS
========================================================

Design enterprise APIs:

- RESTful architecture
- GraphQL where beneficial
- API versioning
- Swagger/OpenAPI documentation
- Webhook systems
- Secure token auth

Include:
- Request examples
- Response examples
- Error handling standards
- Pagination
- Filtering
- Rate limiting

========================================================
IMPLEMENTATION ROADMAP
========================================================

Provide detailed implementation phases:

PHASE 1
- Authentication
- RBAC
- Core LMS

PHASE 2
- Course management
- Content management

PHASE 3
- Assessments
- Gradebook

PHASE 4
- Communication systems

PHASE 5
- Analytics

PHASE 6
- Exam Portal integration

PHASE 7
- DevOps and deployment

Include:
- Timelines
- Team structure
- Sprint planning
- Dependencies
- Milestones

========================================================
OUTPUT FORMAT REQUIREMENTS
========================================================

Generate the solution in EXTREME ENTERPRISE DETAIL.

The output must include:

1. System architecture diagrams
2. Database ERDs
3. Backend folder structure
4. API architecture
5. Service architecture
6. Authentication flow
7. Sync flow
8. Deployment architecture
9. CI/CD pipelines
10. Kubernetes setup
11. Queue workflows
12. Redis strategies
13. Caching architecture
14. Security architecture
15. Detailed implementation guide
16. Production deployment strategy
17. Monitoring strategy
18. Disaster recovery plan
19. Performance optimization strategy
20. Future AI integration strategy

========================================================
ENGINEERING EXPECTATIONS
========================================================

The generated solution MUST reflect:

- FAANG-level architecture
- Enterprise software engineering principles
- Real production scalability
- Clean architecture
- SOLID principles
- Domain-driven design
- Event-driven architecture
- Modular monolith or microservices
- CQRS where beneficial
- Enterprise DevOps maturity
- Maintainability
- Testability
- Security-first engineering

========================================================
FUTURE AI EXTENSIONS
========================================================

Architect the system for future support of:

- AI tutoring
- Adaptive learning
- AI grading assistants
- Predictive interventions
- AI-generated quizzes
- AI-powered recommendations
- Learning personalization
- Intelligent transcript analysis
- Voice AI assistants
- AI proctoring integrations

========================================================
FINAL REQUIREMENT
========================================================

The final solution must be COMPLETE ENOUGH that a senior engineering team can immediately begin implementation without ambiguity.

Do not provide generic explanations.

Provide:
- real enterprise structure,
- production-grade workflows,
- implementation-ready architecture,
- detailed backend engineering guidance,
- deployment-ready infrastructure,
- scalable database architecture,
- complete LMS lifecycle engineering,
- and real-world system integration patterns.

