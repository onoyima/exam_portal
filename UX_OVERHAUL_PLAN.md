# Admin UI Overhaul Plan — Professional-Grade Enterprise Interface

## Objective

Transform every admin page into a **professional, enterprise-grade interface** that institutions would trust for high-stakes examinations. Every page must feel like it belongs to a premium SaaS platform — consistent, polished, and deliberate. No novice design, no inconsistent spacing, no emoji-as-icons, no inline styles, no bare tables.

**Target benchmark:** Canvas, Blackboard, Turnitin, Grammarly, Notion — clean, purposeful, consistent.

---

## 1. Unified Admin Layout Template

Every admin page must follow this exact structure. No exceptions.

```
┌─────────────────────────────────────────────────────────┐
│  SIDEBAR (collapsible)    │  MAIN CONTENT               │
│  ┌──────────────────┐     │  ┌────────────────────────┐│
│  │ Logo             │     │  │ Navbar                 ││
│  │ Brand Name       │     │  │  ☰ Back   Page Title   ││
│  │━━━━━━━━━━━━━━━━━ │     │  │               👤 Name  ││
│  │                  │     │  └────────────────────────┘│
│  │ 🏠 Dashboard     │     │                            │
│  │ 📋 Exams         │     │  ┌─ Breadcrumb ──────────┐ │
│  │ 👥 Students      │     │  │ Dashboard > Exams     │ │
│  │ 👤 Staff         │     │  └────────────────────────┘ │
│  │ 📊 Reports       │     │                            │
│  │ ⚙️ Settings      │     │  ┌─ Page Header ─────────┐ │
│  │                  │     │  │  Exams (12)    [+New]  │ │
│  │                  │     │  └────────────────────────┘ │
│  │ Documentation   │     │                            │
│  │ 🚪 Logout       │     │  ┌─ Content Card ─────────┐ │
│  └──────────────────┘     │  │  ┌─ Filter Bar ──────┐ │ │
│                           │  │  │ [Search...] [All] │ │ │
│                           │  │  └───────────────────┘ │ │
│                           │  │  ┌─ Table ───────────┐ │ │
│                           │  │  │ Name │ Status │ .. │ │ │
│                           │  │  ├───────────────────┤ │ │
│                           │  │  │ Exam A │ Active  │ │ │
│                           │  │  │ Exam B │ Inactive│ │ │
│                           │  │  └───────────────────┘ │ │
│                           │  │                         │ │
│                           │  │  ┌─ Empty State ──────┐ │ │
│                           │  │  │  [illustration]    │ │ │
│                           │  │  │  No exams yet      │ │ │
│                           │  │  │  Create your first │ │ │
│                           │  │  └───────────────────┘ │ │
│                           │  └─────────────────────────┘│
│                           │                            │
│                           │  ┌─ Footer ──────────────┐ │
│                           │  │ © 2026 Assessment    │ │
│                           │  └────────────────────────┘│
└─────────────────────────────────────────────────────────┘
```

### 1.1 Required HTML Skeleton

Every admin page must start from this base:

```php
<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// ── Page-specific PHP logic here ──
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Page Title - Assessment Portal</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  
  <div class="main-content">
    <!-- ═══ UNIFIED NAVBAR ═══ -->
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <?php if (isset($back_url)): ?>
            <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back">
              <i class="bi bi-arrow-left"></i>
            </a>
          <?php endif; ?>
          <h1 class="admin-navbar-title">Page Title</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <!-- ═══ BREADCRUMB ═══ -->
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li><a href="#">Parent Page</a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Current Page</li>
        </ol>
      </nav>

      <!-- ═══ FLASH MESSAGES ═══ -->
      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($_GET['success']) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= htmlspecialchars($_GET['error']) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <!-- ═══ PAGE HEADER ═══ -->
      <div class="admin-page-header">
        <h2 class="admin-page-title">Page Title</h2>
        <?php if (isset($page_actions)): ?>
          <div class="admin-page-actions"><?= $page_actions ?></div>
        <?php endif; ?>
      </div>

      <!-- ═══ CONTENT CARDS ═══ -->
      <!-- Each card uses this pattern -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title">Card Title</h3>
          <?php if (isset($card_action)): /* button or badge */ endif; ?>
        </div>
        <div class="admin-card-body">
          <!-- Content goes here -->
        </div>
      </div>

    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <!-- Page-specific scripts -->
</body>
</html>
```

### 1.2 CSS for the Unified Layout

```css
/* ═══════════════════════════════════════
   ADMIN LAYOUT
   ═══════════════════════════════════════ */

/* ── Navbar ── */
.admin-navbar {
  background: #fff;
  border-bottom: 1px solid var(--color-border);
  padding: 0 var(--space-6);
  height: 64px;
  display: flex;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
  backdrop-filter: blur(8px);
  background: rgba(255, 255, 255, 0.92);
}

.admin-navbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.admin-navbar-start {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.admin-navbar-toggle {
  width: 36px;
  height: 36px;
  border: none;
  background: var(--color-bg);
  border-radius: var(--radius-sm);
  color: var(--color-text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}
.admin-navbar-toggle:hover {
  background: var(--color-border-light);
  color: var(--color-text);
}

.admin-back-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  border-radius: var(--radius-sm);
  color: var(--color-text-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all var(--transition-fast);
}
.admin-back-btn:hover {
  background: var(--color-bg);
  color: var(--color-text);
}

.admin-navbar-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
  white-space: nowrap;
}

.admin-navbar-end {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.admin-user-badge {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-3);
  background: var(--color-bg);
  border-radius: var(--radius-full);
}

.admin-user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-primary);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-bold);
}

.admin-user-name {
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-secondary);
}

.admin-logout-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all var(--transition-fast);
}
.admin-logout-btn:hover {
  background: #fef2f2;
  color: var(--color-danger);
}

/* ── Content area ── */
.admin-content {
  padding: var(--space-6);
  max-width: 1400px;
  margin: 0 auto;
}

/* ── Breadcrumb ── */
.admin-breadcrumb {
  margin-bottom: var(--space-5);
}
.admin-breadcrumb ol {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}
.admin-breadcrumb a {
  color: var(--color-text-secondary);
  text-decoration: none;
  transition: color var(--transition-fast);
}
.admin-breadcrumb a:hover { color: var(--color-primary); }
.admin-breadcrumb .sep { color: var(--color-border); }
.admin-breadcrumb .current { color: var(--color-text); font-weight: var(--font-weight-medium); }

/* ── Page Header ── */
.admin-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-6);
  flex-wrap: wrap;
  gap: var(--space-3);
}

.admin-page-title {
  font-size: var(--font-size-2xl);
  font-weight: var(--font-weight-bold);
  color: var(--color-text);
  margin: 0;
}

.admin-page-actions {
  display: flex;
  gap: var(--space-2);
}

/* ── Cards ── */
.admin-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-5);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition-normal);
}

.admin-card-header {
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--color-border-light);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.admin-card-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin: 0;
}

.admin-card-body {
  padding: var(--space-6);
}
/* ── Card body flush (for tables) ── */
.admin-card-body-flush {
  padding: 0;
}

/* ── Tables ── */
.admin-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--font-size-sm);
}

.admin-table thead {
  position: sticky;
  top: 0;
  z-index: 2;
}

.admin-table th {
  padding: var(--space-3) var(--space-4);
  font-weight: var(--font-weight-semibold);
  font-size: var(--font-size-xs);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  background: var(--color-bg);
  border-bottom: 2px solid var(--color-border);
  white-space: nowrap;
  text-align: left;
}

.admin-table td {
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  border-bottom: 1px solid var(--color-border-light);
  vertical-align: middle;
}

.admin-table tbody tr {
  transition: background var(--transition-fast);
}
.admin-table tbody tr:hover {
  background: var(--color-primary-subtle);
}
.admin-table tbody tr:last-child td {
  border-bottom: none;
}

/* ── Table action buttons cell ── */
.admin-table .actions-cell {
  text-align: right;
  white-space: nowrap;
}

.admin-table .actions-cell .admin-btn {
  margin-left: var(--space-1);
}

/* ── Buttons ── */
.admin-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  font-family: var(--font-primary);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  line-height: 1.4;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all var(--transition-fast);
  white-space: nowrap;
  text-decoration: none;
  background: #fff;
  color: var(--color-text);
}
.admin-btn:hover { 
  background: var(--color-bg); 
  border-color: var(--color-text-muted);
}

.admin-btn-primary {
  background: var(--color-primary);
  color: #fff;
  border-color: var(--color-primary);
}
.admin-btn-primary:hover {
  background: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
  box-shadow: 0 2px 8px rgba(25, 135, 84, 0.25);
  color: #fff;
}

.admin-btn-danger {
  background: var(--color-danger);
  color: #fff;
  border-color: var(--color-danger);
}
.admin-btn-danger:hover {
  background: #b91c1c;
  border-color: #b91c1c;
  color: #fff;
}

.admin-btn-outline {
  background: transparent;
  color: var(--color-text-secondary);
  border-color: var(--color-border);
}
.admin-btn-outline:hover {
  background: var(--color-bg);
  border-color: var(--color-text-muted);
}

.admin-btn-sm {
  padding: var(--space-1) var(--space-3);
  font-size: var(--font-size-xs);
}

.admin-btn-lg {
  padding: var(--space-3) var(--space-6);
  font-size: var(--font-size-base);
}

.admin-btn-icon {
  width: 32px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* ── Badges ── */
.admin-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 10px;
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-medium);
  border-radius: var(--radius-full);
  line-height: 1.5;
  white-space: nowrap;
}
.admin-badge-success { background: #d1e7dd; color: #0f5132; }
.admin-badge-danger  { background: #f8d7da; color: #842029; }
.admin-badge-warning { background: #fff3cd; color: #664d03; }
.admin-badge-info    { background: #cff4fc; color: #055160; }
.admin-badge-neutral { background: var(--color-bg); color: var(--color-text-secondary); }
.admin-badge-primary { background: #e7f1ff; color: #084298; }

/* ── Alerts ── */
.admin-alert {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-sm);
  margin-bottom: var(--space-4);
  animation: alert-slide-in var(--transition-normal) ease-out;
}
@keyframes alert-slide-in {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.admin-alert-success { background: #d1e7dd; color: #0f5132; border-left: 3px solid var(--color-success); }
.admin-alert-danger  { background: #f8d7da; color: #842029; border-left: 3px solid var(--color-danger); }
.admin-alert-warning { background: #fff3cd; color: #664d03; border-left: 3px solid var(--color-warning); }

.admin-alert-close {
  margin-left: auto;
  background: none;
  border: none;
  font-size: 1.2em;
  cursor: pointer;
  color: inherit;
  opacity: 0.5;
  padding: 0 4px;
}
.admin-alert-close:hover { opacity: 1; }

/* ── Empty States ── */
.admin-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-16) var(--space-6);
  text-align: center;
}
.admin-empty-icon {
  width: 64px;
  height: 64px;
  color: var(--color-text-muted);
  opacity: 0.3;
  margin-bottom: var(--space-4);
}
.admin-empty-title {
  font-size: var(--font-size-lg);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
  margin-bottom: var(--space-2);
}
.admin-empty-desc {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  max-width: 360px;
  margin-bottom: var(--space-5);
}

/* ── Filter Bar ── */
.admin-filters {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  margin-bottom: var(--space-5);
  align-items: end;
}

.admin-filter-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.admin-filter-group label {
  font-size: var(--font-size-xs);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.admin-filter-input {
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: var(--font-size-sm);
  font-family: var(--font-primary);
  color: var(--color-text);
  background: var(--color-surface);
  outline: none;
  min-width: 180px;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.admin-filter-input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.12);
}

/* ── Forms ── */
.admin-form-group {
  margin-bottom: var(--space-5);
}

.admin-form-label {
  display: block;
  margin-bottom: var(--space-2);
  font-size: var(--font-size-sm);
  font-weight: var(--font-weight-medium);
  color: var(--color-text-secondary);
}

.admin-input {
  width: 100%;
  padding: var(--space-3) var(--space-4);
  font-family: var(--font-primary);
  font-size: var(--font-size-base);
  color: var(--color-text);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  outline: none;
  transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.admin-input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.10);
}
.admin-input::placeholder { color: var(--color-text-muted); }

.admin-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235f636b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
  cursor: pointer;
}

.admin-checkbox-group {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--color-bg);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border-light);
}

.admin-checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  cursor: pointer;
  font-size: var(--font-size-sm);
}

.admin-checkbox input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary);
  cursor: pointer;
}

/* ── Pagination ── */
.admin-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
  padding: var(--space-4) 0;
}

.admin-pagination a, .admin-pagination span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--radius-sm);
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  text-decoration: none;
  transition: all var(--transition-fast);
}
.admin-pagination a:hover { background: var(--color-bg); }
.admin-pagination .active {
  background: var(--color-primary);
  color: #fff;
  font-weight: var(--font-weight-semibold);
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .admin-content { padding: var(--space-4); }
  .admin-page-header { flex-direction: column; align-items: flex-start; }
  .admin-filters { flex-direction: column; align-items: stretch; }
  .admin-filter-input { min-width: 0; }
  .admin-user-name { display: none; }
  .admin-card-header { flex-direction: column; align-items: flex-start; gap: var(--space-2); }
  .admin-navbar-title { font-size: var(--font-size-base); }
}
```

---

## 2. Page-by-Page Redesign

### 2.1 `admin_exams.php` — Exam Management

**Current problems:**
- `📋` emoji in heading
- 4 "step" cards with inconsistent badge colors
- No wrapper card around the table
- Missing `table-hover`
- Edit modal too small on desktop
- No sticky table header
- No empty state
- Inline `onsubmit="return confirm(...)"`

**Redesigned layout:**

```
┌─ Admin Content ─────────────────────────────────────────┐
│  Breadcrumb: Dashboard > Exams                          │
│                                                         │
│  Page Header:                                           │
│  Exams (12)                         [+ Create Exam]     │
│                                                         │
│  ┌─ Admin Card ───────────────────────────────────────┐ │
│  │  Header: Create New Exam                            │ │
│  │                                                     │ │
│  │  form.row: [Session ▼] [Exam Name ___]              │ │
│  │  form.row: [Duration __] [Max Attempts __]          │ │
│  │  form.row: [Start ___] [End ___]                    │ │
│  │                                                     │ │
│  │  ┌─ Proctoring (collapsible) ───────────────────┐   │ │
│  │  │  ☐ Fullscreen  ☐ Tab Lock  ☐ KB Lock        │   │ │
│  │  │  ☐ Auto Submit ☐ Webcam   ☐ Live Video      │   │ │
│  │  └──────────────────────────────────────────────┘   │ │
│  │                                                     │ │
│  │                               [Cancel]  [Create]    │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Admin Card ───────────────────────────────────────┐ │
│  │  Header: All Exams (12)            [Search...]      │ │
│  │                                                     │ │
│  │  ┌─ Admin Table ─────────────────────────────────┐ │ │
│  │  │  Exam Name │ Status │ Created │ S  │ Q  │ E  │ │ │
│  │  ├───────────────────────────────────────────────┤ │ │
│  │  │  MCE 101   │ Active │ Mar 12  │ 45 │ 60 │ 3  │ │ │
│  │  │  PHS 202   │ Inactive│Feb 28  │ 0  │ 0  │ 0  │ │ │
│  │  └───────────────────────────────────────────────┘ │ │
│  └─────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

**Specific changes:**

1. **Remove emoji**: Replace `📋` with `<i class="bi bi-clipboard-data"></i>`
2. **Create Exam form**: Replace 4 separate step cards with a single `.admin-card` containing:
   - A horizontal stepper at top (if multi-step is needed) or a clean single form
   - Proctoring section as a collapsible `<details>` element
   - Footer with Cancel + Create buttons
3. **Exam table**: Wrap in `.admin-card` with `.admin-card-body-flush` and `.admin-table`
   - Add status toggle inline (click to toggle active/inactive without page reload)
   - Add student count as clickable badge linking to `admin_assign_students?exam_id=X`
   - Action buttons in `.actions-cell`
4. **Edit modal**: Add `modal-lg`, use `.admin-input` classes
5. **Empty state**: Use `.admin-empty` with an icon when no exams exist

### 2.2 `admin_objective_exams.php` — Objective Exams

**Current problems:**
- **No navbar** — page starts with `<h2>` directly
- No card wrapper around table
- Uses `table-bordered` — inconsistent
- Edit modals inside `<tbody>` (invalid HTML)
- create modal is extremely long

**Redesigned layout:**

Follow the unified template with:

1. **Add the navbar**: Include the standard `.admin-navbar` with sidebar toggle + page title
2. **Wrap table in `.admin-card`**: Consistent with all other pages
3. **Move modals outside `<tbody>`**: Place all modals before `</body>`
4. **Modal tabs**: Split the long create modal into tabs or a collapsible accordion:
   - Tab 1: Basic Info (name, session, duration)
   - Tab 2: Proctoring (checkboxes)
   - Tab 3: Advanced (shuffle, subset, grading policy)
5. **Action buttons**: Standardize with `.admin-btn`, `.admin-btn-sm`

### 2.3 `admin_objective_questions.php` — Objective Questions

**Current problems:**
- **No navbar**
- Edit modals inside `<tbody>`
- Image library card uses Bootstrap blue (`bg-primary`) instead of theme green

**Redesigned layout:**

1. **Add navbar**: Same pattern as exams page
2. **Move modals**: Place all edit modals before `</body>`
3. **Fix image library card**: Use `bg-primary` → `--color-primary` (green)
4. **Question table**: `.admin-table` with `.admin-card` wrapper
5. **Add question types**: Visual indicator for question type (MCQ, True/False, Theory)

### 2.4 `admin_assign_students.php` — Assign Students

**Current problems:**
- Table is wrapped in `bg-white rounded-3 shadow-sm p-3 mb-4 border` — inconsistent with card pattern
- Search input has no visible focus ring (`border-0 bg-transparent`)
- Bulk action area low visual weight
- No sticky table header

**Redesigned layout:**

```
┌─ Admin Content ─────────────────────────────────────────┐
│  Breadcrumb: Exams > Assign Students                    │
│                                                         │
│  Page Header:                                           │
│  MCE 101 — Assign Students       [Enroll All] [By Domain]│
│                                                         │
│  ┌─ Admin Card ───────────────────────────────────────┐ │
│  │  Header: Student Source                             │ │
│  │  [○ Exam Portal DB]  [○ School Portal]  [○ Upload] │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                         │
│  ┌─ Admin Card ───────────────────────────────────────┐ │
│  │  ┌─ Filters ─────────────────────────────────────┐  │ │
│  │  │  [Search name/matric...] [Dept ▼] [Level ▼]  │  │ │
│  │  └───────────────────────────────────────────────┘  │ │
│  │                                                     │ │
│  │  ┌─ Admin Table (sticky header) ─────────────────┐  │ │
│  │  │  ☐ │ Name │ Matric │ Email │ Status          │  │ │
│  │  ├───────────────────────────────────────────────┤  │ │
│  │  │  ☐ │ Doe J│ VUG/...│ j@... │ 🟢 New          │  │ │
│  │  │  ☐ │ Lee A│ VUG/...│ a@... │ 🔵 Exists       │  │ │
│  │  └───────────────────────────────────────────────┘  │ │
│  │                                                     │ │
│  │  [Assign Selected (3)]  [Unassign Selected]         │ │
│  └─────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────┘
```

**Specific changes:**
1. Wrap table in `.admin-card` with `.admin-card-body-flush`
2. Make search input use `.admin-filter-input` with proper focus ring
3. Add sticky header to thead
4. Add `.admin-empty` when no students match
5. Show count badge: "Assign Selected (3)"
6. Add loading state on assign button with spinner

### 2.5 `admin_staff.php` — Staff/Examiners

**Current problems:**
- Navbar toggler uses `<span class="navbar-toggler-icon">` instead of icon
- "Reset PW" is ambiguous and dangerous
- No confirmation on add
- Table missing empty state (though one exists)

**Redesigned layout:**
1. Fix navbar toggler: replace `<span class="navbar-toggler-icon">` with `<i class="bi bi-list"></i>`
2. Add source selector (Exam Portal / School Portal / Upload) matching student pattern
3. Staff table: use `.admin-table` with passport thumbnail column
4. "Reset PW" → "Reset to Default" with warning modal explaining it sets `12345`
5. Add confirmation modal for Add Staff: shows entered details for review
6. Add `.admin-empty` state for zero staff

### 2.6 `admin_upload.php` — Bulk Upload

**Current problems:**
- No drag-and-drop upload zone
- No `<optgroup>` in exam select
- No upload progress bar
- Note box at bottom uses `border-start border-primary` instead of green

**Redesigned layout:**
1. Two `.admin-card` columns with `.admin-card-body`
2. Upload zone with dashed border, file icon, and "Click or drag file here" text
3. Show selected file name + size before upload
4. Upload progress bar using JavaScript
5. `<optgroup label="Practical">` and `<optgroup label="Objective">` in select
6. Fix note box border to `--color-primary`

### 2.7 `admin_live_proctor.php` — Live Proctoring

**Current problems:**
- **Critical**: `<meta http-equiv="refresh" content="60">` destroys WebRTC
- Massive inline styles (font-size, z-index, colors everywhere)
- Inconsistent font sizes: `0.55rem` to `0.75rem` scattered inline
- Filter bar has no proper gap on mobile
- Action buttons have no loading state
- No success toast

**Redesigned layout:**
1. **Remove meta refresh** → replace with `setInterval(fetchUpdates, 15000)` AJAX
2. **Remove ALL inline font-size styles** → use utility classes
3. Filter bar: use `.admin-filters` with `.admin-filter-group`
4. Proctor cards: use `.admin-card` with `h-100`
5. Video area: fixed aspect-ratio container with overlay controls
6. Action buttons: use `.admin-btn` `.admin-btn-sm` with loading states
7. Add `.admin-alert` toast on successful actions
8. Add recording count as `.admin-badge-info` in navbar

### 2.8 `admin_portal_settings.php` — Settings

**Current problems:**
- 1272-line page — extremely long
- Tab nav overflows on mobile with no scroll hint
- Save button not sticky
- No "unsaved changes" warning
- Emoji for section visibility

**Redesigned layout:**
1. Add sticky save button: `position: sticky; bottom: 0; background: white; padding: 16px; border-top: 1px solid...`
2. Group settings into collapsible sections with icons:
   - 🎨 Branding (logo, title, favicon)
   - 📄 Hero Section (badge, title, CTAs)
   - 🔒 Security Stack (layer descriptions)
   - 📊 Stats (stat labels)
   - 👥 About (about section content)
   - ⚙️ Advanced (footer, login content)
3. Tab nav: add `mask-image` gradient for overflow hint
4. Replace emoji visibility: "Published" in green / "Draft" in grey
5. Add `beforeunload` when form is dirty using JS
6. Add "Unsaved changes" indicator dot in the save button

### 2.9 `admin_students.php` — Manage Students

**Current problems:**
- Navbar has both "Back" and sidebar toggle — redundant
- No search filter
- Add form fields are `col-md-2` — too cramped
- No table hover
- No empty state
- Title tooltip `title="Usually Username"` — unprofessional

**Redesigned layout:**
1. Use `.admin-table` with hover
2. Add search input in card header
3. Add `.admin-empty` state for zero students
4. Remove unprofessional tooltip
5. Fix form field widths to `col-md-3` or `col-md-4`
6. Add passport thumbnail column
7. Add student count to page header

### 2.10 `assess.php` — Exam Grading

**Current problems:**
- Uses CDN Bootstrap (version mismatch risk)
- jQuery UI dependency (heavy for autocomplete)
- No running score total
- "Second Attempt" badge hard to read
- Sidebar inline implementation

**Redesigned layout:**
1. Switch to local Bootstrap
2. Replace jQuery UI autocomplete with vanilla JS or `<datalist>`
3. Add sticky score footer: "Score: 14/25 (56%)" with progress bar
4. Add keyboard shortcuts indicator: `[S] Save  [N] Next  [P] Prev`
5. Add breadcrumb: "Assessments" → "Practical" → "Exam Name" → "Student Name"
6. Student info card: show passport + name + matric in a compact header
7. Checklist table: use `.admin-table` with row striping
8. Fix "Second Attempt" badge: use `.admin-badge-warning`

### 2.11 `report.php` — Reports & Analytics

**Current problems:**
- No chart visualization
- No table hover
- No empty state for per-exam report
- Stats are just plain numbers

**Redesigned layout:**
1. Add CSS bar charts (div with width-based colored bars — no library needed)
2. Stat cards at top: use `.admin-stat-card`:
   ```css
   .admin-stat-card {
     padding: var(--space-5);
     background: var(--color-surface);
     border: 1px solid var(--color-border);
     border-radius: var(--radius-sm);
     text-align: center;
   }
   .admin-stat-value { font-size: 2rem; font-weight: 700; color: var(--color-primary); }
   .admin-stat-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
   ```
3. Use `.admin-table` with hover
4. Add `.admin-empty` for exams with no submissions
5. Add export buttons (PDF, CSV) as `.admin-btn-outline`

---

## 3. Cross-Cutting Fixes

### 3.1 Remove All Inline `onsubmit="return confirm(...)"`

Replace EVERY instance with a JS event listener:

```javascript
// In admin-common.js
document.addEventListener('submit', function(e) {
  const form = e.target;
  if (form.hasAttribute('data-confirm')) {
    e.preventDefault();
    const msg = form.getAttribute('data-confirm') || 'Are you sure?';
    if (confirm(msg)) { HTMLFormElement.prototype.submit.call(form); }
  }
});
```

Usage: `<form method="post" data-confirm="Delete this exam?">`

### 3.2 Extract SweetAlert Injection to a Single File

Create `assets/js/admin-swal.js`:
- Include SweetAlert2 CDN
- Override `window.alert` globally
- One-time initialization

Include it once in the unified layout head:
```php
<script src="assets/js/admin-swal.js"></script>
```

**Remove** from every individual page — it's copy-pasted identically across all 20+ pages.

### 3.3 Remove All Inline Styles

Every `style="..."` attribute on every page must be:
1. Extracted to a CSS class
2. Replaced with a utility class

Targets:
- `admin_live_proctor.php`: `style="font-size:0.7rem;"` (10+ occurrences) → class `admin-btn-sm`
- `student_objective_exam.php`: all z-index overrides → use `--z-*` variables
- `footer.php`: inline chat styles → extract to `footer.css`
- `admin_exams.php`: `style="font-size: 0.8rem;"` on label → CSS class

### 3.4 Fix All Emoji in UI

| Location | Emoji | Replace With |
|---|---|---|
| `admin_exams.php:503` | `📋` | `<i class="bi bi-clipboard-data"></i>` |
| `footer.php` | `👥` | `<i class="bi bi-people"></i>` |
| `footer.php` | `🗑️` | `<i class="bi bi-trash"></i>` |
| `footer.php` | `🌙` / `☀️` | `<i class="bi bi-moon"></i>` / `<i class="bi bi-sun"></i>` |
| `student_login.php:559` | `🔒` | `<i class="bi bi-shield-lock-fill"></i>` |

### 3.5 Remove Bootstrap CDN Duplication

- `assess.php` uses CDN Bootstrap 5.3.0 — switch to local `assets/css/bootstrap.min.css`
- All pages load `bootstrap.bundle.min.js` twice (once in sidebar, once in page) — keep only the page-level load

### 3.6 Fix SQL Injection in `admin_exams.php:181-183`

Replace:
```php
$exam_students[$exam['id']] = $db->query("SELECT student_id FROM exam_students WHERE exam_id=" . $exam['id'])->fetchAll(PDO::FETCH_COLUMN);
```
With:
```php
$stmt = $db->prepare("SELECT student_id FROM exam_students WHERE exam_id=?");
$stmt->execute([$exam['id']]);
$exam_students[$exam['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
```

### 3.7 Add Page Transition Animation

```css
@keyframes admin-page-enter {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.admin-content {
  animation: admin-page-enter 300ms ease-out;
}
```

### 3.8 Respect `prefers-reduced-motion`

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 4. Implementation Order

### Sprint 1 — Foundation (Day 1)
1. Add all CSS from §1.2 to `style_enhanced.css`
2. Create `assets/js/admin-common.js` with shared JS (confirm helper, sidebar toggle, etc.)
3. Create `assets/js/admin-swal.js` — extract SweetAlert injection once
4. Add `bootstrap-icons.css` CDN to all admin pages that lack it

### Sprint 2 — Templates (Day 2)
5. Convert `admin_exams.php` to the unified layout (navbar, breadcrumb, cards, table)
6. Fix SQL injection on lines 181-183
7. Remove all inline `onsubmit` → use `data-confirm`
8. Remove SweetAlert copy-paste → include `admin-swal.js`

### Sprint 3 — Remaining Admin Pages (Day 3)
9. Convert `admin_objective_exams.php` — add navbar, fix modal position
10. Convert `admin_objective_questions.php` — add navbar, fix modal position
11. Convert `admin_assign_students.php` — card wrapper, sticky header, filter bar
12. Convert `admin_staff.php` — fix toggler, source selector, confirm modals

### Sprint 4 — Complex Pages (Day 4)
13. Convert `admin_live_proctor.php` — remove meta refresh→AJAX, extract inline styles, toasts
14. Convert `assess.php` — local Bootstrap, remove jQuery UI, score footer, keyboard shortcuts
15. Convert `admin_portal_settings.php` — sticky save, collapsible sections, unsaved warning
16. Convert `report.php` — stat cards, CSS charts, export buttons

### Sprint 5 — Student Pages & Polish (Day 5)
17. Convert `student_dashboard.php` — card stagger, progress rings
18. Convert `student_objective_exam.php` — extract ALL inline styles, z-index scale, skeleton loader
19. Convert `admin_students.php` — search, empty state, table hover
20. Fix all emoji → icons, test responsive on all pages

---

## 5. Verification Checklist

After every page conversion:

- [ ] Page uses the unified `.admin-navbar`, `.admin-content`, `.admin-breadcrumb` structure
- [ ] Page heading uses `.admin-page-title` with `.admin-page-actions`
- [ ] Content is wrapped in `.admin-card` with `.admin-card-header` + `.admin-card-body`
- [ ] Tables use `.admin-table` with sticky header and hover
- [ ] Buttons use `.admin-btn`, `.admin-btn-primary`, `.admin-btn-sm`, etc.
- [ ] Forms use `.admin-input`, `.admin-select`, `.admin-form-group`, `.admin-form-label`
- [ ] Badges use `.admin-badge-*`
- [ ] Alerts use `.admin-alert-*`
- [ ] Empty states use `.admin-empty`
- [ ] Filter bars use `.admin-filters` with `.admin-filter-group`
- [ ] No inline `style="..."` attributes remain
- [ ] No emoji icons (all replaced with `<i class="bi ...">`)
- [ ] No `onsubmit="return confirm(...)"` (all use `data-confirm` or event listeners)
- [ ] No SweetAlert copy-paste (uses single `admin-swal.js`)
- [ ] Breadcrumb shows correct hierarchy with "Dashboard > Current Page"
- [ ] Page transitions are smooth (fade-in on load)
- [ ] Works at 360px width (no overflow, touch targets ≥ 44px)
- [ ] Works at 2560px width (content max-width 1400px, centered)
- [ ] Hover states on all interactive elements (cards, buttons, table rows)
- [ ] Loading state on all form submissions (button shows spinner)
