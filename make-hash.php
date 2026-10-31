<?php
// make-hash.php — upload, visit in browser, copy the output, paste output in auth.json, DELETE THIS FILE
$password = 'your_pass';
echo password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
