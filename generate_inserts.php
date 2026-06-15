<?php
/**
 * Reads the four CSV files in the same directory and generates
 * insert_sample_data.sql with all INSERT statements.
 *
 * Usage:  php generate_inserts.php
 */

$dir = __DIR__;
$out = fopen("$dir/insert_sample_data.sql", 'w');

fwrite($out, "-- Auto-generated sample data from CSV files\n");
fwrite($out, "-- Generated on " . date('Y-m-d H:i:s') . "\n\n");
fwrite($out, "USE `capstonef`;\n\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n");
fwrite($out, "TRUNCATE TABLE `ai_recommendations`;\n");
fwrite($out, "TRUNCATE TABLE `student_records`;\n");
fwrite($out, "TRUNCATE TABLE `prerequisites`;\n");
fwrite($out, "TRUNCATE TABLE `students`;\n");
fwrite($out, "TRUNCATE TABLE `courses`;\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n\n");

// ────────────────────────────────────────────
// 1. courses.csv
// ────────────────────────────────────────────
$file = fopen("$dir/courses.csv", 'r');
$header = fgetcsv($file); // course_id, course_name, description, credits, category, semester
$batch = [];
while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 6 || empty(trim($row[0]))) continue;
    $cid  = addslashes(trim($row[0]));
    $cname = addslashes(trim($row[1]));
    $desc  = addslashes(trim($row[2]));
    $cred  = (int)trim($row[3]);
    $cat   = addslashes(trim($row[4]));
    $sem   = addslashes(trim($row[5]));
    $batch[] = "('$cid','$cname','$desc',$cred,'$cat','$sem')";
}
fclose($file);
if ($batch) {
    fwrite($out, "-- ── Courses ──\n");
    fwrite($out, "INSERT INTO `courses` (`course_id`,`course_name`,`description`,`credits`,`category`,`semester`) VALUES\n");
    fwrite($out, implode(",\n", $batch) . ";\n\n");
}
echo "Courses: " . count($batch) . " rows\n";

// ────────────────────────────────────────────
// 2. students.csv
// ────────────────────────────────────────────
$file = fopen("$dir/students.csv", 'r');
$header = fgetcsv($file); // student_id, enrollment_year, gender, age
$batch = [];
$batchCount = 0;
fwrite($out, "-- ── Students ──\n");
while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 4 || empty(trim($row[0]))) continue;
    $sid  = addslashes(trim($row[0]));
    $year = (int)trim($row[1]);
    $gen  = addslashes(trim($row[2]));
    $age  = (int)trim($row[3]);
    $batch[] = "('$sid',$year,'$gen',$age)";
    $batchCount++;

    // flush every 500 rows to avoid huge statements
    if (count($batch) >= 500) {
        fwrite($out, "INSERT INTO `students` (`student_id`,`enrollment_year`,`gender`,`age`) VALUES\n");
        fwrite($out, implode(",\n", $batch) . ";\n\n");
        $batch = [];
    }
}
if ($batch) {
    fwrite($out, "INSERT INTO `students` (`student_id`,`enrollment_year`,`gender`,`age`) VALUES\n");
    fwrite($out, implode(",\n", $batch) . ";\n\n");
}
fclose($file);
echo "Students: $batchCount rows\n";

// ────────────────────────────────────────────
// 3. prerequisites.csv
// ────────────────────────────────────────────
$file = fopen("$dir/prerequisites.csv", 'r');
$header = fgetcsv($file); // course_id, prerequisite_course_id
$batch = [];
while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 2 || empty(trim($row[0]))) continue;
    $cid   = addslashes(trim($row[0]));
    $pcid  = addslashes(trim($row[1]));
    $batch[] = "('$cid','$pcid')";
}
fclose($file);
if ($batch) {
    fwrite($out, "-- ── Prerequisites ──\n");
    fwrite($out, "INSERT INTO `prerequisites` (`course_id`,`prerequisite_course_id`) VALUES\n");
    fwrite($out, implode(",\n", $batch) . ";\n\n");
}
echo "Prerequisites: " . count($batch) . " rows\n";

// ────────────────────────────────────────────
// 4. student_records.csv
// ────────────────────────────────────────────
$file = fopen("$dir/student_records.csv", 'r');
$header = fgetcsv($file); // student_id, course_name, course_id, final_result, score, letter_grade
$batch = [];
$batchCount = 0;
fwrite($out, "-- ── Student Records ──\n");
while (($row = fgetcsv($file)) !== false) {
    if (count($row) < 6) continue;
    $sid     = addslashes(trim($row[0]));
    $cname   = addslashes(trim($row[1]));
    $cid     = addslashes(trim($row[2]));
    $result  = addslashes(trim($row[3]));
    $score   = addslashes(trim($row[4]));
    $grade   = addslashes(trim($row[5]));

    // Skip garbage rows (course_id = '0' or empty student_id)
    if (empty($sid) || $cid === '0') continue;

    $batch[] = "('$sid','$cid','$cname','$result','$score','$grade')";
    $batchCount++;

    // flush every 500 rows
    if (count($batch) >= 500) {
        fwrite($out, "INSERT INTO `student_records` (`student_id`,`course_id`,`course_name`,`final_result`,`score`,`letter_grade`) VALUES\n");
        fwrite($out, implode(",\n", $batch) . ";\n\n");
        $batch = [];
    }
}
if ($batch) {
    fwrite($out, "INSERT INTO `student_records` (`student_id`,`course_id`,`course_name`,`final_result`,`score`,`letter_grade`) VALUES\n");
    fwrite($out, implode(",\n", $batch) . ";\n\n");
}
fclose($file);
echo "Student records: $batchCount rows\n";

fclose($out);
echo "\nDone! insert_sample_data.sql has been generated.\n";
