<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Learning Path – Recommended Courses</title>
  <meta name="description"
    content="Your personalised learning path with recommended courses based on your academic history." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=JetBrains+Mono:wght@400;500&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* ── Results-page extras ─────────────────────────────────── */
    .results-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.25rem;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.82rem;
      color: var(--muted);
      text-decoration: none;
      transition: color 0.15s;
    }

    .back-link:hover {
      color: var(--accent);
    }

    .back-link svg {
      width: 0.9em;
      height: 0.9em;
    }

    /* card grid */
    .results-grid {
      display: grid;
      gap: 1.25rem;
      margin-bottom: 2rem;
    }

    /* ── student info card ── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
    }

    .card-header {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.9rem 1.25rem;
      background: rgba(255, 255, 255, 0.02);
      border-bottom: 1px solid var(--border);
    }

    .card-header h2 {
      font-size: 0.85rem;
      font-weight: 600;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
    }

    .card-header .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
    }

    .card-body {
      padding: 1.25rem;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1rem;
    }

    .info-item label {
      display: block;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      margin-bottom: 0.25rem;
    }

    .info-item span {
      font-size: 0.95rem;
      font-weight: 500;
      color: var(--text);
    }

    /* ── recommended courses table ── */
    .rec-table th {
      color: var(--accent);
    }

    .rec-table .course-code {
      font-family: "JetBrains Mono", monospace;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text);
    }

    .rec-table .course-name {
      color: var(--muted);
    }

    .rank-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.5rem;
      height: 1.5rem;
      border-radius: 50%;
      font-size: 0.7rem;
      font-weight: 700;
      background: rgba(34, 197, 94, 0.12);
      color: var(--accent);
    }

    /* ── why section ── */
    .why-box {
      background: rgba(14, 165, 233, 0.06);
      border: 1px solid rgba(14, 165, 233, 0.25);
      border-radius: 10px;
      padding: 1.1rem 1.25rem;
      font-size: 0.92rem;
      line-height: 1.7;
      color: var(--text);
    }

    /* ── footer ── */
    .results-footer {
      margin-top: 2.5rem;
      padding-top: 1.25rem;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .results-footer .gen-date {
      font-size: 0.78rem;
      color: var(--muted);
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text);
    }

    .btn-outline:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    /* page banner — dark surface with accent left border */
    .page-banner {
      background: var(--surface);
      border: 1px solid var(--border);
      border-left: 3px solid var(--accent);
      padding: 1.5rem 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
    }

    .page-banner h1 {
      margin: 0 0 0.2rem;
      font-size: 1.35rem;
      color: var(--text);
      letter-spacing: -0.02em;
    }

    .page-banner p {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    /* ── loading spinner ── */
    .loading-overlay {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 1rem;
      gap: 1rem;
    }

    .spinner {
      width: 36px;
      height: 36px;
      border: 3px solid var(--border);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .loading-text {
      font-size: 0.88rem;
      color: var(--muted);
    }

    /* error state */
    .error-box {
      background: rgba(239, 68, 68, 0.08);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 10px;
      padding: 1.1rem 1.25rem;
      color: #f87171;
      font-size: 0.9rem;
      display: none;
    }
  </style>
  <script>
    (function () {
      const theme = localStorage.getItem('theme') || 'dark';
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
</head>

<body>
  <button id="theme-toggle" class="theme-switch" aria-label="Toggle Theme">
    <svg class="sun-icon" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="5"></circle>
      <line x1="12" y1="1" x2="12" y2="3"></line>
      <line x1="12" y1="21" x2="12" y2="23"></line>
      <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
      <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
      <line x1="1" y1="12" x2="3" y2="12"></line>
      <line x1="21" y1="12" x2="23" y2="12"></line>
      <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
      <line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line>
    </svg>
    <svg class="moon-icon" viewBox="0 0 24 24">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>
  </button>

  <script>
    (function () {
      const themeToggle = document.getElementById('theme-toggle');
      if (themeToggle) {
        themeToggle.addEventListener('click', () => {
          const currentTheme = document.documentElement.getAttribute('data-theme');
          const newTheme = currentTheme === 'light' ? 'dark' : 'light';
          document.documentElement.setAttribute('data-theme', newTheme);
          localStorage.setItem('theme', newTheme);
        });
      }
    })();
  </script>

  <div class="container">

    <!-- Back link -->
    <div style="margin-bottom:1.25rem;">
      <a href="index.php" class="back-link">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to course history
      </a>
    </div>

    <!-- Banner -->
    <div class="page-banner">
      <h1>Learning Path</h1>
      <p>Your personalised course recommendations based on your academic record.</p>
    </div>

    <!-- Loading state -->
    <div id="loading-state" class="loading-overlay">
      <div class="spinner"></div>
      <span class="loading-text">Analysing your academic record with AI…</span>
    </div>

    <!-- Error state -->
    <div id="error-state" class="error-box"></div>

    <!-- Results (hidden until loaded) -->
    <div id="results-content" style="display:none;">
      <div class="results-grid">

        <!-- ① Student header card -->
        <div class="card">
          <div class="card-header">
            <span class="dot"></span>
            <h2>Student Information</h2>
          </div>
          <div class="card-body">
            <div class="info-grid">
              <div class="info-item">
                <label>Student name</label>
                <span id="res-student-name">—</span>
              </div>
              <div class="info-item">
                <label>Student number</label>
                <span id="res-student-number">—</span>
              </div>
              <div class="info-item">
                <label>Advisor</label>
                <span id="res-advisor">—</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ② Best recommended courses -->
        <div class="card">
          <div class="card-header">
            <span class="dot"></span>
            <h2>Best Recommended Courses</h2>
          </div>
          <div class="courses-table-wrap">
            <table class="rec-table">
              <thead>
                <tr>
                  <th style="width:2.5rem;">#</th>
                  <th>Course code</th>
                  <th>Course name</th>
                </tr>
              </thead>
              <tbody id="rec-tbody"></tbody>
            </table>
          </div>
        </div>

        <!-- ③ Why recommended -->
        <div class="card">
          <div class="card-header">
            <span class="dot"></span>
            <h2>Why Those Are the Recommended Courses</h2>
          </div>
          <div class="card-body">
            <p class="why-box" id="res-why">—</p>
          </div>
        </div>

      </div><!-- /.results-grid -->

      <!-- Footer actions -->
      <div class="results-footer">
        <span class="gen-date" id="res-gen-date"></span>
        <div class="actions">
          <a href="index.php" class="btn btn-outline" id="btn-back-footer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
              stroke-width="2" style="width:1em;height:1em;">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back
          </a>
          <button type="button" class="btn btn-primary" id="download-pdf" aria-label="Download PDF">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
              stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Download PDF
          </button>
        </div>
      </div>
    </div><!-- /#results-content -->

  </div><!-- /.container -->

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script>
    (function () {
      const loadingEl = document.getElementById('loading-state');
      const errorEl = document.getElementById('error-state');
      const contentEl = document.getElementById('results-content');

      /* ── Shared data (populated after fetch) ── */
      let studentName = '';
      let studentNumber = '';
      let advisorName = '';
      let recommendedCourses = [];
      let whyRecommended = '';

      /* ── Fetch recommendations from API ── */
      const params = new URLSearchParams(window.location.search);
      const studentId = params.get('student_id') || '1001';
      const semestersCompleted = "<?= $_SESSION['semesters_completed'] ?? '' ?>";
      let apiUrl = 'api/recommend.php?student_id=' + encodeURIComponent(studentId);
      if (semestersCompleted !== '') {
        apiUrl += '&semesters_completed=' + encodeURIComponent(semestersCompleted);
      }
      fetch(apiUrl)
        .then(function (res) {
          if (!res.ok) return res.json().then(function (d) { throw new Error(d.error || 'Request failed'); });
          return res.json();
        })
        .then(function (data) {
          studentName = data.student.name;
          studentNumber = data.student.number;
          advisorName = data.student.advisor;
          recommendedCourses = data.courses.map(function (c) { return [c.code, c.name]; });
          whyRecommended = data.reason;

          populatePage();
          loadingEl.style.display = 'none';
          contentEl.style.display = 'block';
        })
        .catch(function (err) {
          loadingEl.style.display = 'none';
          errorEl.style.display = 'block';
          errorEl.textContent = 'Failed to load recommendations: ' + err.message;
        });

      /* ── Populate page ── */
      function populatePage() {
        document.getElementById('res-student-name').textContent = studentName;
        document.getElementById('res-student-number').textContent = studentNumber;
        document.getElementById('res-advisor').textContent = advisorName;
        document.getElementById('res-why').textContent = whyRecommended;

        var genDate = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        document.getElementById('res-gen-date').textContent = 'Learning Path – Generated ' + genDate;

        var tbody = document.getElementById('rec-tbody');
        tbody.innerHTML = recommendedCourses.map(function (row, i) {
          return '<tr>' +
            '<td><span class="rank-badge">' + (i + 1) + '</span></td>' +
            '<td><span class="course-code">' + escapeHtml(row[0]) + '</span></td>' +
            '<td><span class="course-name">' + escapeHtml(row[1]) + '</span></td>' +
            '</tr>';
        }).join('');
      }

      function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
      }

      /* ── PDF download ── */
      document.getElementById('download-pdf').addEventListener('click', function () {
        if (!recommendedCourses.length) return;

        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF();
        var margin = 20;
        var pageW = 210;
        var y = 25;

        // Color Palette (Modern & Professional)
        var cPrimary = [15, 23, 42];    // Slate 900
        var cSecondary = [71, 85, 105]; // Slate 600
        var cAccent = [37, 99, 235];    // Blue 600
        var cBorder = [226, 232, 240];  // Slate 200

        // 1. Report Title & Date
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(22);
        doc.setTextColor.apply(doc, cPrimary);
        doc.text('ACADEMIC LEARNING PATH', margin, y);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor.apply(doc, cSecondary);
        var genDate = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
        doc.text('GENERATED ON ' + genDate.toUpperCase(), margin, y + 8);

        // Accent line under title
        doc.setDrawColor.apply(doc, cAccent);
        doc.setLineWidth(1.5);
        doc.line(margin, y + 14, margin + 40, y + 14);
        y += 30;

        // 2. Student Profile Section
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor.apply(doc, cPrimary);
        doc.text('STUDENT PROFILE', margin, y);

        doc.setDrawColor.apply(doc, cBorder);
        doc.setLineWidth(0.2);
        doc.line(margin, y + 3, pageW - margin, y + 3);
        y += 12;

        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100, 116, 139); // Slate 500
        doc.text('NAME', margin, y);
        doc.text('STUDENT ID', margin + 60, y);
        doc.text('ADVISOR', margin + 120, y);

        y += 6;
        doc.setFont('helvetica', 'bold');
        doc.setTextColor.apply(doc, cPrimary);
        doc.text(studentName.toUpperCase(), margin, y);
        doc.text(studentNumber, margin + 60, y);
        doc.text(advisorName.toUpperCase(), margin + 120, y);
        y += 20;

        // 3. Recommended Courses Table
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.text('COURSE RECOMMENDATIONS', margin, y);
        doc.setDrawColor.apply(doc, cBorder);
        doc.line(margin, y + 3, pageW - margin, y + 3);
        y += 12;

        // Table Header
        doc.setFontSize(8);
        doc.setTextColor.apply(doc, cSecondary);
        doc.text('CODE', margin + 2, y);
        doc.text('COURSE DESCRIPTION', margin + 40, y);
        doc.text('PRIORITY', pageW - margin - 15, y, { align: 'right' });

        y += 4;
        doc.setDrawColor.apply(doc, cPrimary);
        doc.setLineWidth(0.5);
        doc.line(margin, y, pageW - margin, y);
        y += 8;

        // Table Rows
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        recommendedCourses.forEach(function (row, i) {
          doc.setTextColor.apply(doc, cPrimary);
          doc.setFont('helvetica', 'bold');
          doc.text(row[0], margin + 2, y);

          doc.setFont('helvetica', 'normal');
          doc.setTextColor.apply(doc, cSecondary);
          doc.text(row[1], margin + 40, y);

          doc.setTextColor.apply(doc, cAccent);
          doc.text('#' + (i + 1), pageW - margin - 15, y, { align: 'right' });

          y += 4;
          doc.setDrawColor.apply(doc, cBorder);
          doc.setLineWidth(0.1);
          doc.line(margin, y, pageW - margin, y);
          y += 8;
        });
        y += 5;

        // 4. Strategic Reasoning Section
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(10);
        doc.setTextColor.apply(doc, cPrimary);
        doc.text('ACADEMIC ADVISOR INSIGHTS', margin, y);
        doc.setDrawColor.apply(doc, cBorder);
        doc.line(margin, y + 3, pageW - margin, y + 3);
        y += 12;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9.5);
        doc.setTextColor.apply(doc, cSecondary);

        var lines = doc.splitTextToSize(whyRecommended, pageW - 2 * margin);
        doc.text(lines, margin, y);

        // Footer
        doc.setFontSize(7);
        doc.setTextColor(148, 163, 184); // Slate 400
        var footerText = 'This is an automated academic recommendation generated by the Capstone AI Advisor system.';
        doc.text(footerText, pageW / 2, 285, { align: 'center' });

        doc.save('Learning_Path_' + studentNumber + '.pdf');
      });
    })();
  </script>
</body>

</html>