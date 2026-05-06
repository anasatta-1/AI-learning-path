<?php
/**
 * Application configuration — DB credentials & API keys.
 * ⚠  This file is in .gitignore — never commit it.
 */

// ── Database ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // default XAMPP — change in production
define('DB_NAME', 'capstonef');

// ── Google Gemini API (legacy fallback) ─────────
define('GEMINI_API_KEY', 'AIzaSyBSNdPW3AXMfqkr0ZKfio0vJ6REIeix21w');
define('GEMINI_MODEL',   'gemini-2.0-flash');

// ── External AI Model ───────────────────────────
// Set to your model's prediction endpoint, e.g.:
//   http://localhost:5000/predict
//   https://your-cloud-model.example.com/api/recommend
// Leave empty to use the mock endpoint for testing.
define('AI_MODEL_URL',     '');
define('AI_MODEL_TIMEOUT', 30);          // request timeout in seconds
define('AI_MODEL_API_KEY', '');          // auth key sent as Bearer token
