(function () {
  const tbody = document.getElementById("courses-tbody");
  const emptyState = document.getElementById("empty-state");
  const downloadBtn = document.getElementById("download-pdf");

  const records = (window.STUDENT_RECORDS || []).filter(
    (r) => String(r.id_student) === String(window.CURRENT_STUDENT_ID || "")
  );

  function renderCourses() {
    if (!records.length) {
      tbody.innerHTML = "";
      emptyState.style.display = "block";
      return;
    }
    emptyState.style.display = "none";
    tbody.innerHTML = records
      .map(
        (r) => `
      <tr>
        <td><span class="code-mod">${escapeHtml(r.code_mod || "")}</span></td>
        <td><span class="code-pres">${escapeHtml(r.code_pres || "")}</span></td>
        <td><span class="letter-grade ${(r.letter_grad || "").toUpperCase() === "N/A" ? "na" : ""}">${escapeHtml(r.letter_grad || "—")}</span></td>
      </tr>
    `
      )
      .join("");
  }

  function escapeHtml(s) {
    const div = document.createElement("div");
    div.textContent = s;
    return div.innerHTML;
  }

  function downloadPdf() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const margin = 18;
    const pageW = 210;
    let y = 0;

    // Colors: primary teal, accent blue, indigo, light bg
    const cTeal = [34, 197, 94];
    const cBlue = [14, 165, 233];
    const cIndigo = [99, 102, 241];
    const cBg1 = [240, 253, 244];
    const cBg2 = [224, 242, 254];
    const cDark = [15, 23, 42];
    const cMuted = [100, 116, 139];

    const studentName = "Maria Garcia";
    const studentNumber = window.CURRENT_STUDENT_ID || "11391";
    const advisorName = "Dr. Sarah Chen";
    const recommendedCourses = [
      ["CS301", "Advanced Programming"],
      ["MATH202", "Linear Algebra"],
      ["EE401", "Digital Systems"],
    ];
    const whyRecommended = "Based on your strong performance in EEE and CCC, we recommend CS301 to build on your programming foundation. MATH202 complements your mathematical background from BBB. EE401 aligns with your interest in systems shown by DDD.";

    // Top banner
    doc.setFillColor.apply(doc, cTeal);
    doc.rect(0, 0, pageW, 28, "F");
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.setFont(undefined, "bold");
    doc.text("Learning Path – Recommendations", pageW / 2, 18, { align: "center" });
    y = 36;

    // Section 1: Header
    doc.setFillColor.apply(doc, cBg1);
    doc.setDrawColor.apply(doc, cTeal);
    doc.rect(margin, y, pageW - 2 * margin, 38, "FD");
    doc.setTextColor.apply(doc, cIndigo);
    doc.setFontSize(12);
    doc.setFont(undefined, "bold");
    doc.text("Header", margin + 6, y + 10);
    doc.setTextColor.apply(doc, cDark);
    doc.setFont(undefined, "normal");
    doc.setFontSize(11);
    doc.text("Student name: " + studentName, margin + 6, y + 20);
    doc.text("Student number: " + studentNumber, margin + 6, y + 27);
    doc.text("Advisor: " + advisorName, margin + 6, y + 34);
    y += 46;

    // Section 2: Best recommended courses
    doc.setTextColor.apply(doc, cIndigo);
    doc.setFontSize(12);
    doc.setFont(undefined, "bold");
    doc.text("Best recommended courses", margin, y);
    y += 8;
    // Table header
    doc.setFillColor.apply(doc, cBlue);
    doc.rect(margin, y - 5, pageW - 2 * margin, 10, "F");
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(10);
    doc.setFont(undefined, "bold");
    doc.text("Course code", margin + 4, y + 2);
    doc.text("Course name", margin + 55, y + 2);
    y += 10;
    doc.setDrawColor(200, 200, 200);
    doc.setFont(undefined, "normal");
    doc.setTextColor.apply(doc, cDark);
    doc.setFontSize(10);
    recommendedCourses.forEach(function (row, i) {
      if (i % 2 === 1) {
        doc.setFillColor(248, 250, 252);
        doc.rect(margin, y - 4, pageW - 2 * margin, 8, "F");
      }
      doc.setTextColor.apply(doc, cDark);
      doc.text(row[0], margin + 4, y + 2);
      doc.setTextColor.apply(doc, cMuted);
      doc.text(row[1], margin + 55, y + 2);
      doc.setDrawColor(230, 230, 230);
      doc.line(margin, y + 4, pageW - margin, y + 4);
      y += 8;
    });
    y += 8;

    // Section 3: Why those are recommended
    doc.setTextColor.apply(doc, cIndigo);
    doc.setFontSize(12);
    doc.setFont(undefined, "bold");
    doc.text("Why those are the recommended courses", margin, y);
    y += 10;
    doc.setFillColor.apply(doc, cBg2);
    doc.setDrawColor.apply(doc, cBlue);
    const whyBoxH = 36;
    doc.rect(margin, y, pageW - 2 * margin, whyBoxH, "FD");
    doc.setTextColor.apply(doc, cDark);
    doc.setFont(undefined, "normal");
    doc.setFontSize(10);
    const lines = doc.splitTextToSize(whyRecommended, pageW - 2 * margin - 12);
    doc.text(lines, margin + 6, y + 8);
    y += whyBoxH + 10;

    // Footer accent line
    doc.setDrawColor.apply(doc, cTeal);
    doc.setLineWidth(0.8);
    doc.line(margin, y, pageW - margin, y);
    doc.setFontSize(8);
    doc.setTextColor.apply(doc, cMuted);
    doc.text("Learning Path – Generated " + new Date().toLocaleDateString(), pageW / 2, y + 6, { align: "center" });

    doc.save("learning-path-recommendations.pdf");
  }

  downloadBtn.addEventListener("click", downloadPdf);
  renderCourses();
})();
