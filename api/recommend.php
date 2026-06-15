<?php
/**
 * REST API endpoint: GET /api/recommend.php?student_id=XXXXX
 *
 * Flow:
 *   1. Validate student_id param
 *   2. Fetch student profile from DB
 *   3. Calculate completed_semesters (integer 1–8) from student_records
 *   4. POST { student_id, completed_semesters } to AI_MODEL_URL
 *   5. Parse model response { courses, reason }
 *   6. Cache results in ai_recommendations table
 *   7. Return JSON to frontend
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ── 1. Validate input ──────────────────────────────────────────────────────
$studentId = trim($_GET['student_id'] ?? '');
if ($studentId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing student_id parameter.']);
    exit;
}

$conn = getDbConnection();

// ── 2. Fetch student profile ───────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT student_id, enrollment_year, age FROM students WHERE student_id = ?'
);
$stmt->bind_param('s', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(404);
    echo json_encode(['error' => 'Student not found.']);
    $conn->close();
    exit;
}

// ── 3. Calculate completed_semesters ──────────────────────────────────────
// Count the number of distinct semester numbers the student has records in.
// The courses.semester column stores labels like "1st fall", "2nd spring", etc.
// We map those labels to integers 1–8.
$stmt = $conn->prepare(
    'SELECT DISTINCT c.semester
     FROM student_records sr
     JOIN courses c ON c.course_id = sr.course_id
     WHERE sr.student_id = ?'
);
$stmt->bind_param('s', $studentId);
$stmt->execute();
$semesterRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Map semester label → integer (e.g. "1st fall"→1, "2nd spring"→2 … "8th spring"→8)
$semesterMap = [
    '1st fall'   => 1,
    '2nd spring' => 2,
    '3rd fall'   => 3,
    '4th spring' => 4,
    '5th fall'   => 5,
    '6th spring' => 6,
    '7th fall'   => 7,
    '8th spring' => 8,
];

$completedSemesters = 0;
$overriddenSemesters = $_GET['semesters_completed'] ?? null;

if ($overriddenSemesters !== null && $overriddenSemesters !== '') {
    $completedSemesters = (int)$overriddenSemesters;
} else {
    foreach ($semesterRows as $row) {
        $label = strtolower(trim($row['semester']));
        if (isset($semesterMap[$label])) {
            $completedSemesters = max($completedSemesters, $semesterMap[$label]);
        }
    }
}

// Also fetch completed course list to return to frontend
$stmt = $conn->prepare(
    'SELECT c.course_id, c.course_name, c.credits, c.category, c.semester,
            sr.letter_grade
     FROM student_records sr
     JOIN courses c ON c.course_id = sr.course_id
     WHERE sr.student_id = ?
     ORDER BY c.semester'
);
$stmt->bind_param('s', $studentId);
$stmt->execute();
$completedCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 4. Determine model execution path ──────────────────────────────────────
try {
    $payload = [
        'student_id' => $studentId,
        'semester'   => $completedSemesters,
    ];

    if (AI_MODEL_URL !== '') {
        // ── Remote AI Model (HTTP) ──
        $headers = ['Content-Type: application/json'];
        if (AI_MODEL_API_KEY !== '') {
            $headers[] = 'Authorization: Bearer ' . AI_MODEL_API_KEY;
        }

        $ch = curl_init(AI_MODEL_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => AI_MODEL_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Remote AI model request failed: $curlError");
        }
        if ($httpCode !== 200) {
            throw new Exception("Remote AI model returned HTTP $httpCode. Response: $response");
        }

        $aiResult = json_decode($response, true);
        if (!$aiResult) {
            throw new Exception("Failed to decode remote AI model response.");
        }
    } else {
        // ── Local Python Model (Bridge) ──
        require_once __DIR__ . '/python_bridge.php';
        $aiResult = call_python_model($payload);
    }
} catch (Exception $e) {
    http_response_code(502);
    echo json_encode([
        'error'  => 'AI model integration error.',
        'detail' => $e->getMessage(),
    ]);
    $conn->close();
    exit;
}

// ── 5. Parse model response ────────────────────────────────────────────────
// The Python engine returns 'recommended_courses' and 'explanation' (via Gemini in Python)
// or we can map them to the format the frontend expects: 'courses' and 'reason'.
$courses = [];
if (isset($aiResult['recommended_courses'])) {
    foreach ($aiResult['recommended_courses'] as $rc) {
        $courses[] = [
            'code' => $rc['course_id'],
            'name' => $rc['course_name']
        ];
    }
} elseif (isset($aiResult['courses'])) {
    $courses = $aiResult['courses'];
}

$reason = $aiResult['reason'] ?? $aiResult['explanation'] ?? '';

if (empty($courses)) {
    http_response_code(502);
    echo json_encode([
        'error' => 'AI model returned no recommendations.',
        'raw'   => $aiResult,
    ]);
    $conn->close();
    exit;
}


// ── 8. Cache recommendations in DB ────────────────────────────────────────
// Clear previous recommendations for this student
$stmt = $conn->prepare('DELETE FROM ai_recommendations WHERE student_id = ?');
$stmt->bind_param('s', $studentId);
$stmt->execute();
$stmt->close();

// Insert new ones (only courses that exist in the courses table)
$stmtInsert = $conn->prepare(
    'INSERT INTO ai_recommendations (student_id, course_id, recommendation_reason, status)
     VALUES (?, ?, ?, ?)'
);
$status = 'pending';
foreach ($courses as $course) {
    $courseCode = $course['code'] ?? '';
    if ($courseCode === '') continue;
    $stmtInsert->bind_param('ssss', $studentId, $courseCode, $reason, $status);
    $stmtInsert->execute();
}
$stmtInsert->close();
$conn->close();

// ── 9. Return JSON to frontend ─────────────────────────────────────────────
echo json_encode([
    'student' => [
        'name'    => 'Student ' . $student['student_id'],
        'number'  => $student['student_id'],
        'advisor' => 'N/A',
    ],
    'completed_semesters' => $completedSemesters,
    'courses'             => $courses,
    'reason'              => $reason,
    'completed_courses'   => $completedCourses,
]);
