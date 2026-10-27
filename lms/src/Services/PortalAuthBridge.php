<?php
namespace Lms\Services;

use PDO;

class PortalAuthBridge
{
    private PDO $db;

    public function __construct()
    {
        $this->db = \Lms\Core\Database::connection('lms');
    }

    private function exeatDb(): ?PDO
    {
        try {
            return \Lms\Core\Database::connection('exeat2');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function authenticate(string $login, string $password, ?string $type = null): ?array
    {
        if ($type === 'student' || $type === null) {
            $result = $this->authenticateStudent($login, $password);
            if ($result) return $result;
        }
        if ($type === 'staff' || $type === null) {
            $result = $this->authenticateStaff($login, $password);
            if ($result) return $result;
        }
        return null;
    }

    private function authenticateStudent(string $login, string $password): ?array
    {
        // ─── Tier 1: Portal DB (exam_lms.students) ──────────────────────
        $cleanLogin = preg_replace('/[\/\s\-]/', '', strtoupper($login));
        $stmt = $this->db->prepare("SELECT * FROM students WHERE deleted_at IS NULL AND (
            REPLACE(UPPER(matric_no), '/', '') = ? OR
            REPLACE(UPPER(username), '/', '') = ? OR
            matric_no = ? OR username = ? OR email = ?
        )");
        $stmt->execute([$cleanLogin, $cleanLogin, $login, $login, $login]);
        $student = $stmt->fetch();

        if ($student) {
            if (!password_verify($password, $student['password'])) {
                if ($student['password'] === $password) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $this->db->prepare("UPDATE students SET password = ? WHERE id = ?")->execute([$newHash, $student['id']]);
                } else {
                    return null;
                }
            }
            return [
                'portal_id' => (int) $student['id'],
                'portal_user_type' => 'student',
                'first_name' => $student['firstname'] ?: explode(' ', $student['name'] ?? 'Student')[0],
                'last_name' => $student['lastname'] ?: (explode(' ', $student['name'] ?? 'Student')[1] ?? ''),
                'full_name' => $student['name'] ?? '',
                'email' => $student['email'] ?? '',
                'matric_no' => $student['matric_no'] ?? '',
                'username' => $student['username'] ?? '',
                'role' => 'student',
                'source_table' => 'students',
                'source_db' => 'portal',
            ];
        }

        // ─── Tier 2: School ERP DB (exeat2.students) ────────────────────
        $exeat = $this->exeatDb();
        if ($exeat) {
            $stmt = $exeat->prepare("SELECT * FROM students WHERE (username = ? OR email = ? OR fname = ? OR lname = ?) AND status = 1");
            $stmt->execute([$login, $login, $login, $login]);
            $student = $stmt->fetch();

            if ($student) {
                if (!password_verify($password, $student['password'])) {
                    if ($student['password'] === $password) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $exeat->prepare("UPDATE students SET password = ? WHERE id = ?")->execute([$newHash, $student['id']]);
                    } else {
                        return null;
                    }
                }

                $matricNo = $student['username'] ?? '';
                $email = $student['email'] ?? '';
                $fname = $student['fname'] ?? ($student['username'] ?? 'Student');
                $lname = $student['lname'] ?? '';

                $this->syncPortalUserFromExeat2($student['id'], $fname, $lname, $email, $matricNo, $password);
                $portalId = $this->getPortalStudentIdByMatric($matricNo) ?: $student['id'];

                return [
                    'portal_id' => $portalId,
                    'portal_user_type' => 'student',
                    'first_name' => $fname,
                    'last_name' => $lname,
                    'full_name' => $fname . ' ' . $lname,
                    'email' => $email,
                    'matric_no' => $matricNo,
                    'username' => $student['username'] ?? '',
                    'role' => 'student',
                    'source_table' => 'students',
                    'source_db' => 'exeat2',
                ];
            }
        }

        return null;
    }

    private function authenticateStaff(string $login, string $password): ?array
    {
        // ─── Tier 1: Portal DB (exam_lms.examiners) ─────────────────────
        $stmt = $this->db->prepare("SELECT * FROM examiners WHERE deleted_at IS NULL AND (username = ? OR email = ?)");
        $stmt->execute([$login, $login]);
        $staff = $stmt->fetch();

        if ($staff) {
            if (!password_verify($password, $staff['password'])) {
                if ($staff['password'] === $password) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $this->db->prepare("UPDATE examiners SET password = ? WHERE id = ?")->execute([$newHash, $staff['id']]);
                } else {
                    return null;
                }
            }

            $parts = explode(' ', $staff['name'] ?? 'Staff');
            return [
                'portal_id' => (int) $staff['id'],
                'portal_user_type' => 'examiner',
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
                'full_name' => $staff['name'] ?? '',
                'email' => $staff['email'] ?? '',
                'username' => $staff['username'] ?? '',
                'role' => $staff['role'] ?? 'examiner',
                'source_table' => 'examiners',
                'source_db' => 'portal',
            ];
        }

        // ─── Tier 2: School ERP DB (exeat2.staff) ───────────────────────
        $exeat = $this->exeatDb();
        if ($exeat) {
            $stmt = $exeat->prepare("SELECT * FROM staff WHERE (email = ? OR fname = ? OR lname = ?) AND status = 1");
            $stmt->execute([$login, $login, $login]);
            $staff = $stmt->fetch();

            if ($staff) {
                if (!password_verify($password, $staff['password'])) {
                    if ($staff['password'] === $password) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $exeat->prepare("UPDATE staff SET password = ? WHERE id = ?")->execute([$newHash, $staff['id']]);
                    } else {
                        return null;
                    }
                }

                $fname = $staff['fname'] ?? ($staff['username'] ?? 'Staff');
                $lname = $staff['lname'] ?? '';
                $email = $staff['email'] ?? '';

                $this->syncPortalExaminerFromExeat2($staff['id'], $fname, $lname, $email, $staff['username'] ?? '', $password);

                return [
                    'portal_id' => (int) $staff['id'],
                    'portal_user_type' => 'examiner',
                    'first_name' => $fname,
                    'last_name' => $lname,
                    'full_name' => $fname . ' ' . $lname,
                    'email' => $email,
                    'username' => $staff['username'] ?? '',
                    'role' => $staff['user_type'] ?? 'examiner',
                    'source_table' => 'examiners',
                    'source_db' => 'exeat2',
                ];
            }
        }

        return null;
    }

    public function findOrCreateLmsUser(array $portalUser): int
    {
        $stmt = $this->db->prepare("SELECT id FROM lms_users WHERE portal_user_id = ? AND portal_user_type = ?");
        $stmt->execute([$portalUser['portal_id'], $portalUser['portal_user_type']]);
        $existing = $stmt->fetch();

        if ($existing) {
            return (int) $existing['id'];
        }

        $matricNo = $portalUser['matric_no'] ?? '';

        $stmt = $this->db->prepare("
            INSERT INTO lms_users (uuid, email, first_name, last_name, matric_no, portal_user_id, portal_user_type, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $uuid = \lms_uuid();
        $stmt->execute([
            $uuid,
            $portalUser['email'] ?: $portalUser['username'] . '@portal.local',
            $portalUser['first_name'],
            $portalUser['last_name'],
            $matricNo,
            $portalUser['portal_id'],
            $portalUser['portal_user_type'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getPortalRecord(int $portalId, string $userType): ?array
    {
        if ($userType === 'student') {
            $stmt = $this->db->prepare("SELECT * FROM students WHERE id = ? AND deleted_at IS NULL");
        } else {
            $stmt = $this->db->prepare("SELECT * FROM examiners WHERE id = ? AND deleted_at IS NULL");
        }
        $stmt->execute([$portalId]);
        $record = $stmt->fetch();
        if ($record) return $record;

        // Fallback to exeat2
        $exeat = $this->exeatDb();
        if (!$exeat) return null;

        if ($userType === 'student') {
            $stmt = $exeat->prepare("SELECT * FROM students WHERE id = ?");
        } else {
            $stmt = $exeat->prepare("SELECT * FROM staff WHERE id = ?");
        }
        $stmt->execute([$portalId]);
        return $stmt->fetch() ?: null;
    }

    public function setPortalSession(array $portalUser): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($portalUser['portal_user_type'] === 'student') {
            $_SESSION['role'] = 'student';
            $_SESSION['student_id'] = $portalUser['portal_id'];
            $_SESSION['student_name'] = $portalUser['full_name'] ?: $portalUser['first_name'] . ' ' . $portalUser['last_name'];
            $_SESSION['matric_no'] = $portalUser['matric_no'] ?? '';
        } else {
            $_SESSION['user'] = $portalUser['username'];
            $_SESSION['role'] = $portalUser['role'];
            $_SESSION['examiner_id'] = $portalUser['portal_id'];
        }
        $_SESSION['last_activity'] = time();
        $_SESSION['lms_authenticated'] = true;
    }

    public function mapRoleToLmsRole(string $portalRole): string
    {
        return match ($portalRole) {
            'admin' => 'super_admin',
            'examiner' => 'instructor',
            'instructor' => 'instructor',
            'lecturer' => 'instructor',
            'staff' => 'instructor',
            'student' => 'student',
            default => 'student',
        };
    }

    public function isStudent(string $userType): bool
    {
        return $userType === 'student';
    }

    // ─── Private: Mirror exeat2 student into portal DB ───────────────────
    private function syncPortalUserFromExeat2(int $exeatStudentId, string $fname, string $lname, string $email, string $matricNo, string $password): void
    {
        if (!$matricNo) return;

        $existing = $this->db->prepare("SELECT id FROM students WHERE matric_no = ? AND deleted_at IS NULL");
        $existing->execute([$matricNo]);
        if ($existing->fetch()) return;

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $fullName = $fname . ' ' . $lname;
        $this->db->prepare("
            INSERT INTO students (name, firstname, lastname, email, matric_no, username, password)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$fullName, $fname, $lname, $email, $matricNo, $matricNo, $hash]);
    }

    private function getPortalStudentIdByMatric(string $matricNo): ?int
    {
        if (!$matricNo) return null;
        $stmt = $this->db->prepare("SELECT id FROM students WHERE matric_no = ? AND deleted_at IS NULL");
        $stmt->execute([$matricNo]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    // ─── Private: Mirror exeat2 staff into portal DB ────────────────────
    private function syncPortalExaminerFromExeat2(int $exeatStaffId, string $fname, string $lname, string $email, string $username, string $password): void
    {
        if (!$username && !$email) return;

        $existing = $this->db->prepare("SELECT id FROM examiners WHERE (username = ? OR email = ?) AND deleted_at IS NULL");
        $existing->execute([$username, $email ?: $username]);
        if ($existing->fetch()) return;

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $fullName = $fname . ($lname ? ' ' . $lname : '');
        $this->db->prepare("
            INSERT INTO examiners (name, username, email, password, role, created_at)
            VALUES (?, ?, ?, ?, 'examiner', NOW())
        ")->execute([$fullName, $username ?: $email, $email, $hash]);
    }
}
