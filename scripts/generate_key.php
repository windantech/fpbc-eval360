<?php
/**
 * generate_key.php — generate a 32-byte APP_KEY.
 *
 *   php scripts/generate_key.php
 *
 * Copy the output into .env as APP_KEY=...
 * Generate once per deployment and keep it. Changing it makes anything
 * already encrypted with it unreadable.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("Not found\n"); }
echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;
