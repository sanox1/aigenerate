<?php
// make-hash.php — upload, visit in browser, copy the output, DELETE THIS FILE
$password = '741852';
echo password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);