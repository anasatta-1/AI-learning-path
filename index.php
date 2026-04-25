<?php
session_start();
require_once __DIR__ . '/db.php';

$conn = getDbConnection();
$loginError = '';
$studentId = $_SESSION['student_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $submittedStudentId = trim($_POST['student_id']);
    $stmt = $conn->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $stmt->bind_param('s', $submittedStudentId);
    $stmt->execute();
    $existingStudent = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingStudent) {
        $_SESSION['student_id'] = $submittedStudentId;
        header('Location: index.php');
        exit;
    }

    $loginError = 'Student ID not found. Please try again.';
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    unset($_SESSION['student_id']);
    header('Location: index.php');
    exit;
}

$student = null;
$records = [];
$recordsByYearSemester = [];

if ($studentId !== null && $studentId !== '') {
    // Fetch student profile
    $stmt = $conn->prepare('SELECT student_id, enrollment_year, age FROM students WHERE student_id = ?');
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student) {
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

        foreach ($records as $record) {
            $rawSemester = (int) ($record['semester'] ?? 0);
            if ($rawSemester <= 0) {
                continue;
            }

            $yearNumber = (int) ceil($rawSemester / 2);
            $semesterSeason = ($rawSemester % 2 === 1) ? 'Fall' : 'Spring';
            $groupTitle = sprintf('Year %d - Semester %d (%s)', $yearNumber, $rawSemester, $semesterSeason);

            if (!isset($recordsByYearSemester[$yearNumber])) {
                $recordsByYearSemester[$yearNumber] = [];
            }

            if (!isset($recordsByYearSemester[$yearNumber][$groupTitle])) {
                $recordsByYearSemester[$yearNumber][$groupTitle] = [];
            }

            $recordsByYearSemester[$yearNumber][$groupTitle][] = $record;
        }

        ksort($recordsByYearSemester);
    } else {
        unset($_SESSION['student_id']);
        $studentId = null;
    }
}

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
  <?php if (!$studentId): ?>
    <div class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="student-login-title">
      <div class="modal-card">
        <h2 id="student-login-title">Student Login</h2>
        <p class="modal-subtitle">Enter your student ID to continue.</p>
        <form method="POST" class="login-form">
          <label for="student_id">Student ID</label>
          <input
            type="text"
            id="student_id"
            name="student_id"
            required
            autofocus
            inputmode="numeric"
            autocomplete="off"
            placeholder="e.g. 1001"
          />
          <?php if ($loginError): ?>
            <p class="login-error"><?= htmlspecialchars($loginError) ?></p>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">Login</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
  <div class="container">
    <header>
      <h1>Learning Path</h1>
      <?php if ($studentId): ?>
        <div class="header-row">
          <p class="subtitle">Preview your courses and download your recommendations template.</p>
          <div class="student-chip">
            <span>Student ID: <strong><?= htmlspecialchars($studentId) ?></strong></span>
            <a href="index.php?logout=1" class="logout-link">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <p class="subtitle">Preview your courses and download your recommendations template.</p>
      <?php endif; ?>
    </header>

    <section class="preview-section">
      <h2>Your course history</h2>
      <?php if (!empty($recordsByYearSemester)): ?>
        <div class="year-groups">
          <?php foreach ($recordsByYearSemester as $yearNumber => $semesterGroups): ?>
            <section class="year-group">
              <h3 class="year-heading">Year <?= (int) $yearNumber ?></h3>
              <div class="semester-groups">
                <?php foreach ($semesterGroups as $groupTitle => $groupRecords): ?>
                  <details class="semester-group" open>
                    <summary class="semester-summary">
                      <span><?= htmlspecialchars($groupTitle) ?></span>
                      <span class="summary-count"><?= count($groupRecords) ?> course(s)</span>
                    </summary>
                    <div class="courses-table-wrap">
                      <table>
                        <thead>
                          <tr>
                            <th>Course</th>
                            <th>Semester</th>
                            <th>Letter grade</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($groupRecords as $r): ?>
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
                        </tbody>
                      </table>
                    </div>
                  </details>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (empty($records)): ?>
        <div id="empty-state" class="empty-state">No course records to display.</div>
      <?php endif; ?>
    </section>

    <div class="actions">
      <a href="results.php?student_id=<?= urlencode((string) $studentId) ?>" class="btn btn-primary" id="see-results">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        See Results
      </a>
    </div>
  </div>
</body>
</html>
