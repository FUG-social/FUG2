<?php
// config.php

// Define Turso API Credentials
// IMPORTANT: Use the HTTPS URL for the HTTP API, NOT libsql://
define('TURSO_URL', 'https://fugsocial-umerhamaaz.aws-ap-south-1.turso.io');
define('TURSO_TOKEN', 'eyJhbGciOiJFZERTQSIsInR5cCI6IkpXVCJ9.eyJhIjoicnciLCJpYXQiOjE3NzE1Mjk2NjAsImlkIjoiYjdmZTI0NWEtZDcyMS00OGNlLTk4NGUtZDgzYjcxZjc3MTc4IiwicmlkIjoiNjQxNGFlYzMtZTBlMS00OWFhLWI2ZTEtZjc2ZDZkYjgxNWIxIn0.XHnqZ3FElU26LVDwJRklb8hX3hsk9723Atq6cigotrxVGh8nB08ytwxxA4rLsaT4MYnleYj3XRBGpnYzrWkcAA'); // Replace with your actual token

// Default Fallback Location (Bahawalpur, Pakistan)
define('DEFAULT_LAT', 29.3956);
define('DEFAULT_LNG', 71.6836);

// Logging Configuration
define('LOGS_DIR', __DIR__ . '/logs/');
define('ENABLE_LOGGING', true);
define('LIVE_USERS_FILE', __DIR__ . '/live_users.json');