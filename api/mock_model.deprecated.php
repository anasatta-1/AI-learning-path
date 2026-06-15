<?php
/**
 * Mock AI Model Endpoint
 * 
 * This script simulates the response from an external AI model.
 * It expects a JSON POST request with:
 *   { "student_id": "...", "completed_semesters": N }
 * 
 * It returns a JSON response with:
 *   { "courses": [...], "reason": "..." }
 */

header('Content-Type: application/json; charset=utf-8');

// Read input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$studentId = $data['student_id'] ?? 'unknown';
$semesters = (int)($data['completed_semesters'] ?? 0);

// Simple mock logic: recommend courses from the next semester
$nextSemester = $semesters + 1;

// Define some mock recommendations based on the next semester
$recommendations = [
    1 => [
        ['code' => 'ENGL142', 'name' => 'READING AND WRITING SKILLS-II'],
        ['code' => 'PHYS102', 'name' => 'GENERAL PHYSICS-II'],
        ['code' => 'CMPE124', 'name' => 'ALGORITHMS AND PROGRAMMING']
    ],
    2 => [
        ['code' => 'CMPE221', 'name' => 'DIGITAL LOGIC DESIGN'],
        ['code' => 'MATH205', 'name' => 'INTRODUCTION TO PROBABILITY AND STATISTICS'],
        ['code' => 'AIEN201', 'name' => 'PRINCIPLES OF ARTIFICIAL INTELLIGENCE']
    ],
    3 => [
        ['code' => 'CMPE214', 'name' => 'VISUAL PROGRAMMING'],
        ['code' => 'AIEN202', 'name' => 'INTRODUCTION TO DATA SCIENCE'],
        ['code' => 'MATH202', 'name' => 'MATHEMATICAL METHODS FOR ENGINEERS']
    ],
    4 => [
        ['code' => 'CMPE343', 'name' => 'DATABASE MANAGEMENT SYSTEMS AND PROGRAMMING-I'],
        ['code' => 'CMPE351', 'name' => 'OPERATING SYSTEMS'],
        ['code' => 'AIEN301', 'name' => 'PROGRAMMING FOR ARTIFICIAL INTELLIGENCE']
    ],
    5 => [
        ['code' => 'CMPE326', 'name' => 'SIGNAL AND IMAGE PROCESSING'],
        ['code' => 'CMPE332', 'name' => 'FUNDAMENTALS OF COMPUTER NETWORKS'],
        ['code' => 'AIEN302', 'name' => 'MACHINE LEARNING']
    ],
    6 => [
        ['code' => 'EELE411', 'name' => 'ROBOTICS'],
        ['code' => 'AIEN421', 'name' => 'FUNDAMENTALS OF NEURAL NETWORKS'],
        ['code' => 'ENGI401', 'name' => 'PROJECT MANAGEMENT']
    ],
    7 => [
        ['code' => 'ENGI402', 'name' => 'CAPSTONE PROJECT'],
        ['code' => 'AIEN422', 'name' => 'NATURAL LANGUAGE PROCESSING'],
        ['code' => 'BIOE305', 'name' => 'AREA ELECTIVE (BIOINFORMATICS)']
    ]
];

$courses = $recommendations[$nextSemester] ?? [
    ['code' => 'RTVC342', 'name' => 'UNIVERSITY ELECTIVE (FILM ANALYSIS)'],
    ['code' => 'ISYE427', 'name' => 'AREA ELECTIVE (INTRODUCTION TO HUMAN-COMPUTER INTERACTION)'],
    ['code' => 'JOUR450', 'name' => 'UNIVERSITY ELECTIVE (JOURNALISM CASE STUDIES)']
];

$reason = "Based on your completion of $semesters semesters, the AI advisor recommends these courses to advance your path through the curriculum, focusing on core requirements and prerequisites for future subjects.";

// Return response
echo json_encode([
    'courses' => $courses,
    'reason' => $reason
]);
