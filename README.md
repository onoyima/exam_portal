# 🎓 Secure AI-Proctored Assessment Portal

A professional-grade, web-based examination system designed for high-stakes assessments. This portal features advanced AI-driven proctoring, real-time video monitoring, and sophisticated exam scheduling capabilities.

---

## 🌟 Key Features

### 🛡️ Advanced Proctoring System
*   **Live Video Monitoring**: Administrators can view real-time webcam feeds of students during active exams.
*   **Security Gate Logic**: The exam interface is locked until a valid webcam connection is established and verified.
*   **AI Anti-Cheating Lockdown**: 
    *   **Tab-Lock**: Detects when a student switches tabs or leaves the browser.
    *   **Fullscreen Enforcement**: Forces the browser into fullscreen mode to prevent external tool usage.
    *   **Keyboard Lock**: Restricts shortcut keys (Alt+Tab, Windows Key, etc.).
*   **Violation Management**: Automatic tagging of violations with a "3-strike" rule before auto-submission.

### 📅 Exam Administration & Scheduling
*   **Precise Scheduling**: Set specific Start and End dates/times for every assessment.
*   **Automated Access**: Exams automatically lock/unlock based on server time.
*   **Multi-Type Support**: 
    *   **Objective**: Auto-graded MCQ assessments with question shuffling.
    *   **Practical**: Manual grading for essay or task-based exams.
*   **Dynamic Question Pooling**: Show a random subset of questions from a larger pool.

### 📊 Administrative Controls
*   **Live Control Center**: Monitor all active sessions in a grid/gallery view with pagination (20 per page).
*   **Force Submit**: Administrators can manually end and grade any active student session instantly.
*   **Comprehensive Analytics**: View detailed student performance, attempt history, and violation logs.
*   **Bulk Management**: CSV-based import for students and questions.

---

## 🛠️ Technical Documentation

### 🚀 Requirements
*   **Server**: XAMPP / Apache / Nginx
*   **Database**: MySQL (PDO Support)
*   **Environment**: HTTPS connection (Mandatory for Webcam/Proctoring)
*   **Browser**: Google Chrome or Microsoft Edge (Latest versions)

### 📁 Project Structure
```bash
├── admin_dashboard.php      # Admin Home
├── admin_live_proctor.php   # Real-time monitoring center
├── admin_exams.php          # Practical exam management
├── admin_objective_exams.php# Objective exam management
├── student_dashboard.php    # Student portal
├── student_objective_exam.php # Core examination engine
├── objective_exam_api.php   # Backend data-saving API
├── webrtc_api.php           # Video signaling bridge
├── db.php                   # Database connection
└── style_enhanced.css       # Premium UI Styling
```

### 💾 Installation
1.  **Clone the directory** into your `htdocs` folder.
2.  **Database Setup**:
    *   Create a database named `assessment_portal`.
    *   Import the provided `.sql` files found in the root directory.
3.  **Configuration**:
    *   Edit `db.php` to match your MySQL credentials.
4.  **Security**:
    *   Ensure the site is hosted on **HTTPS**. Browsers will block camera access on `http://` connections.

### 🧪 API Endpoints (Objective Exam)
The system uses a background API (`objective_exam_api.php`) for seamless data handling:
*   `GET ?action=get_remaining_time`: Syncs the server-side countdown.
*   `POST ?action=save_answer`: Persists selections without page refreshes.
*   `POST ?action=start_attempt`: Initializes a new proctored session.

---

## 🧑‍💻 Student Experience
1.  **Login**: Use matric number and secure password.
2.  **Scheduled View**: See upcoming exams, but cannot start until the designated time.
3.  **Identity Verification**: System initializes the webcam and secures the browser environment.
4.  **Auto-Submission**: If the timer expires or violation limits are reached, the system auto-saves and exits.

---

## ⚖️ Security Notice
The proctoring system relies on **Secure Contexts**. If accessing through a local network IP (e.g., `192.168.x.x`), camera access will be denied unless the server is using an SSL certificate.

---

## 📝 Credits & Licensing
Developed for professional educational institutions.
**Copyright © 2026 BMXcode Initiator - All Rights Reserved.**