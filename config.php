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



define('AI_MODEL_URL',     '');
define('AI_MODEL_TIMEOUT', 60);          // request timeout in seconds
define('AI_MODEL_API_KEY', '');          // auth key sent as Bearer token

// ── Local Python Model ──────────────────────────
define('PYTHON_EXECUTABLE', 'python');   // e.g. 'python', 'python3', or 'C:/Path/To/python.exe'
define('PYTHON_MODEL_PATH', __DIR__ . '/python/predict.py');

