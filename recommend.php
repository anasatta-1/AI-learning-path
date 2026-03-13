<?php
/**
 * API endpoint: GET /api/recommend.php?student_id=XXXXX
 *
 * 1. Fetches student profile + academic records from DB
 * 2. Sends transcript + available courses to Gemini AI
 * 3. Saves recommendations to ai_recommendations table
 * 4. Returns JSON response to the frontend
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db.php';

// ── 1. Validate input ──────────────────────────────────────
$studentId = $_GET['student_id'] ?? '';
if ($studentId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing student_id parameter.']);
    exit;
}

$conn = getDbConnection();

// ── 2. Fetch student profile ───────────────────────────────
$stmt = $conn->prepare('SELECT student_id, first_name, last_name, advisor_name FROM students WHERE student_id = ?');
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

// ── 3. Fetch completed courses with grades ─────────────────
$stmt = $conn->prepare(
    'SELECT c.course_id, c.course_name, c.credits, c.category, c.semester,
            sr.semester_taken, sr.letter_grade
     FROM student_records sr
     JOIN courses c ON c.course_id = sr.course_id
     WHERE sr.student_id = ?
     ORDER BY sr.semester_taken'
);
$stmt->bind_param('s', $studentId);
$stmt->execute();
$completedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 4. Fetch available (not-yet-taken) courses ─────────────
$completedIds = array_column($completedRows, 'course_id');
if (count($completedIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($completedIds), '?'));
    $types = str_repeat('s', count($completedIds));
    $sql = "SELECT course_id, course_name, credits, category, semester
            FROM courses
            WHERE course_id NOT IN ($placeholders)
            ORDER BY semester, course_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$completedIds);
} else {
    $stmt = $conn->prepare('SELECT course_id, course_name, credits, category, semester FROM courses ORDER BY semester, course_id');
}
$stmt->execute();
$availableCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 5. Build Gemini prompt ─────────────────────────────────
$completedText = '';
foreach ($completedRows as $row) {
    $completedText .= "- {$row['course_id']} ({$row['course_name']}): Grade {$row['letter_grade']}, {$row['credits']} credits, taken in {$row['semester_taken']}\n";
}

$availableText = '';
foreach ($availableCourses as $row) {
    $availableText .= "- {$row['course_id']} ({$row['course_name']}): {$row['credits']} credits, {$row['category']}, planned for {$row['semester']}\n";
}

$prompt = <<<PROMPT
You are an academic advisor AI. A student has completed the following courses:

$completedText

The following courses are still available in the curriculum:

$availableText

Based on the student's completed courses and grades, recommend the top 3 courses the student should take next. Consider:
1. Prerequisite logic (natural course progression)
2. The student's strengths (higher grades)
3. A mix of core and elective courses when appropriate

Respond with ONLY valid JSON in this exact format, no markdown, no code fences:
{"courses":[{"code":"COURSE_ID","name":"COURSE_NAME"},{"code":"COURSE_ID","name":"COURSE_NAME"},{"code":"COURSE_ID","name":"COURSE_NAME"}],"reason":"A 2-3 sentence explanation of why these courses are recommended based on the student's performance."}
PROMPT;

// ── 6. Call Gemini API ─────────────────────────────────────
$apiKey  = GEMINI_API_KEY;
$model   = GEMINI_MODEL;
$apiUrl  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$payload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.7,
        'maxOutputTokens' => 1024,
    ]
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response   = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Gemini API request failed: ' . $curlError]);
    $conn->close();
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Gemini API returned HTTP ' . $httpCode, 'detail' => json_decode($response, true)]);
    $conn->close();
    exit;
}

// ── 7. Parse Gemini response ───────────────────────────────
$geminiData = json_decode($response, true);
$rawText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Strip markdown code fences if present
$rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
$rawText = preg_replace('/\s*```\s*$/', '', $rawText);
$rawText = trim($rawText);

$aiResult = json_decode($rawText, true);

if (!$aiResult || !isset($aiResult['courses'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not parse Gemini response.', 'raw' => $rawText]);
    $conn->close();
    exit;
}

// ── 8. Save recommendations to DB ──────────────────────────
// Clear old recommendations for this student
$stmt = $conn->prepare('DELETE FROM ai_recommendations WHERE student_id = ?');
$stmt->bind_param('s', $studentId);
$stmt->execute();
$stmt->close();

$stmtInsert = $conn->prepare(
    'INSERT INTO ai_recommendations (student_id, course_id, recommendation_reason, status)
     VALUES (?, ?, ?, ?)'
);
$status = 'pending';
foreach ($aiResult['courses'] as $course) {
    $courseCode = $course['code'];
    $reason = $aiResult['reason'] ?? '';
    $stmtInsert->bind_param('ssss', $studentId, $courseCode, $reason, $status);
    $stmtInsert->execute();
}
$stmtInsert->close();
$conn->close();

// ── 9. Return JSON to frontend ─────────────────────────────
echo json_encode([
    'student' => [
        'name'    => $student['first_name'] . ' ' . $student['last_name'],
        'number'  => $student['student_id'],
        'advisor' => $student['advisor_name'],
    ],
    'courses' => $aiResult['courses'],
    'reason'  => $aiResult['reason'] ?? '',
    'completed_courses' => $completedRows,
]);
