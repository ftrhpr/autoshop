<?php
// Simple server-side endpoint to serve a small default WAV notification sound.
// Replace this by dropping your higher-quality files at:
// assets/sounds/notify.mp3 and/or assets/sounds/notify.ogg — those will be preferred by the <audio> element.

// Only serve the file to authenticated users — keep it simple and public-friendly
require_once __DIR__ . '/../../config.php';

// Optional: restrict to logged in users (admins/managers). If you want a public file, remove this block.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Minimal WAV (short click) base64. Replace with your own binary file on disk (notify.wav) if desired.
$wav_base64 = 'UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=';

header('Content-Type: audio/wav');
header('Cache-Control: public, max-age=86400');
echo base64_decode($wav_base64);
