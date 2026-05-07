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
define('GEMINI_API_KEY', 'AIzaSyB8MKItEuBAsbfxDF58mwAyAULH5gxer-Q');
define('GEMINI_MODEL',   'gemini-2.0-flash');

// ── External AI Model ───────────────────────────
// Set to your model's prediction endpoint, e.g.:
//   http://localhost:5000/predict
//   https://your-cloud-model.example.com/api/recommend
// Leave empty to use the local Python bridge.
define('AI_MODEL_URL',     '');
define('AI_MODEL_TIMEOUT', 60);          // request timeout in seconds
define('AI_MODEL_API_KEY', '');          // auth key sent as Bearer token

// ── Local Python Model ──────────────────────────
define('PYTHON_EXECUTABLE', 'python');   // e.g. 'python', 'python3', or 'C:/Path/To/python.exe'
define('PYTHON_MODEL_PATH', __DIR__ . '/python/predict.py');

