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
          <tbody id="courses-tbody"></tbody>
        </table>
      </div>
      <div id="empty-state" class="empty-state" style="display: none;">No course records to display.</div>
    </section>

    <div class="actions">
      <button type="button" class="btn btn-primary" id="download-pdf" aria-label="Download PDF">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        Download PDF (recommendations template)
      </button>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="students.js"></script>
  <script src="app.js"></script>
</body>
</html>
