<?php
require_once __DIR__ . '/../db_exeat2.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!$exeat2_db) {
    echo json_encode(['error' => $exeat2_error ?: 'School Portal database not reachable.']);
    exit;
}

try {

$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';

$conditions = ["st.status = 1"];
$params = [];

if ($department_id > 0) {
    $conditions[] = "swp.department_id = ?";
    $params[] = $department_id;
}
if ($search !== '') {
    $conditions[] = "(st.fname LIKE ? OR st.lname LIKE ? OR st.email LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where = implode(' AND ', $conditions);

$sql = "SELECT
  st.id            AS exeat2_staff_id,
  CONCAT(st.fname, ' ', COALESCE(st.mname,''), ' ', st.lname) AS name,
  st.email         AS email,
  COALESCE(st.email, CONCAT(LOWER(st.lname), '.', LOWER(SUBSTRING(st.fname,1,1)), '@veritas.edu.ng')) AS username,
  st.password      AS password,
  st.passport      AS passport
FROM exeat2.staff st
LEFT JOIN exeat2.staff_work_profiles swp ON swp.staff_id = st.id
WHERE $where
GROUP BY st.id
ORDER BY st.lname ASC
LIMIT 500";

$stmt = $exeat2_db->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

$local_map = [];
if (!empty($staff)) {
    $usernames = array_column($staff, 'username');
    $placeholders = implode(',', array_fill(0, count($usernames), '?'));
    $local_stmt = $db->prepare("SELECT username, id, source FROM examiners WHERE username IN ($placeholders)");
    $local_stmt->execute($usernames);
    foreach ($local_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $local_map[$row['username']] = $row;
    }
}

foreach ($staff as &$s) {
    $uname = $s['username'];
    if (isset($local_map[$uname])) {
        $local = $local_map[$uname];
        $s['local_id'] = $local['id'];
        $s['local_source'] = $local['source'];
        if ($local['source'] === 'exeat2') {
            $s['status'] = 'exists_exeat2';
        } else {
            $s['status'] = 'exists_' . $local['source'];
        }
    } else {
        $s['local_id'] = null;
        $s['local_source'] = null;
        $s['status'] = 'new';
    }
}
unset($s);

$response = $staff;
foreach ($response as &$r) {
    unset($r['passport']);
}
unset($r);

echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Query error: ' . $e->getMessage()]);
}
