# Plan: Fix Student CSV Upload

## Problem
Current CSV upload uses positional mapping (`row[0]=firstname, row[1]=middlename...`). Existing template (`student_bulk_template.csv`) has mismatched headers (`Matric No` with space vs DB column `matric_no`). Many existing students in `exam_lms.students` have NULL firstname (1008), NULL middlename (1569), NULL lastname (1008), NULL email (1008) because current upload doesn't correctly map or fill these fields.

## Goal
Upload students via CSV that matches the actual DB columns, and for existing students (matched by matric_no), only fill NULL fields with the new data — without overwriting existing non-null values.

---

## 1. New CSV Template Format

Headers must match DB column names exactly (any order accepted):

```
firstname,middlename,lastname,email,matric_no,password
```

- **No `name` (fullname) column** — full name is auto-generated from firstname + middlename + lastname
- **No `username` column** — auto-set to matric_no by the code
- **`matric_no` is the unique key** for matching existing students
- **`password` defaults to `12345`** if omitted

---

## 2. Upload Logic Changes (in `admin_upload.php`)

### Header-based column mapping
- Read first row, match each header against DB column names (`firstname`, `middlename`, `lastname`, `email`, `matric_no`, `password`)
- Build a column index map so headers can be in **any order**
- If no known headers found → fall back to positional parsing (backward compat)

### On each row

**Lookup:** Find student by `matric_no` (include soft-deleted to prevent crash)

**If student is new:**
- INSERT with columns from CSV
- `username` set to `matric_no`
- `name` (fullname) auto-generated from firstname + middlename + lastname

**If student already exists and `update_existing` is CHECKED:**
- Full overwrite — all fields replaced with CSV values
- `deleted_at` set to NULL (restore if soft-deleted)

**If student already exists and `update_existing` is UNCHECKED (default):**
- **NULL-fill merge only** — for each field (firstname, middlename, lastname, email, password):
  - If DB value is **NULL** and CSV value is **non-empty** → update it
  - If DB value already has data → **keep it, do NOT overwrite**
- `name` (fullname) recomputed from the merged fields
- `deleted_at` set to NULL (restore if soft-deleted)
- `username` remains as `matric_no` (no change)

**Enroll in exams:** After insert/update, enroll student into selected exams via `INSERT IGNORE INTO exam_students` (silently skips if already enrolled)

---

## 3. Summary

| Scenario | Behavior |
|---|---|
| New student (matric_no not found) | INSERT all fields + enroll |
| Existing, `update_existing` ON | Full overwrite all fields + enroll |
| Existing, `update_existing` OFF | Fill only NULL DB fields from CSV + enroll |
| Soft-deleted found | Restore (deleted_at=NULL) + merge/overwrite per above |
