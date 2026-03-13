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

// ── Google Gemini API ───────────────────────────
define('GEMINI_API_KEY', 'AIzaSyBSNdPW3AXMfqkr0ZKfio0vJ6REIeix21w');
define('GEMINI_MODEL',   'gemini-2.0-flash');
