<?php
require_once __DIR__ . '/db.php';

$studentId = '1001';  // current student
$conn = getDbConnection();

// Fetch student profile
$stmt = $conn->prepare('SELECT student_id, enrollment_year, age FROM students WHERE student_id = ?');
$stmt->bind_param('s', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch completed courses
$stmt = $conn->prepare(
    'SELECT c.course_id, c.course_name, c.semester, sr.letter_grade
     FROM student_records sr
     JOIN courses c ON c.course_id = sr.course_id
     WHERE sr.student_id = ?
     ORDER BY c.semester'
);
$stmt->bind_param('s', $studentId);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Learning Path – Student Record</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="container">
    <header>
      <h1>Learning Path</h1>
      <p class="subtitle">Preview your courses and download your recommendations template.</p>
    </header>

    <section class="preview-section">
      <h2>Your course history</h2>
      <div class="courses-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Course</th>
              <th>Semester</th>
              <th>Letter grade</th>
            </tr>
          </thead>
          <tbody id="courses-tbody">
            <?php if (empty($records)): ?>
            <?php else: ?>
              <?php foreach ($records as $r): ?>
              <tr>
                <td><span class="code-mod"><?= htmlspecialchars($r['course_id']) ?></span></td>
                <td><span class="code-pres"><?= htmlspecialchars($r['semester'] ?? 'N/A') ?></span></td>
                <td>
                  <span class="letter-grade <?= strtoupper($r['letter_grade'] ?? '') === 'N/A' ? 'na' : '' ?>">
                    <?= htmlspecialchars($r['letter_grade'] ?? '—') ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (empty($records)): ?>
        <div id="empty-state" class="empty-state">No course records to display.</div>
      <?php endif; ?>
    </section>

    <div class="actions">
      <a href="results.php" class="btn btn-primary" id="see-results">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        See Results
      </a>
    </div>
  </div>
</body>
</html>
