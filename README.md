# aigenerate
uses openrouter api to generate video and images (currenlty only video). For personal use only.
Deploy checklist
place all files and folders on any hosting with php / curl enabled

1. make-hash.php → run once → copy hash and put it in auth.json as password → delete make-hash.php.
2. put auth.json with chmod 600, in the webroot and .htaccess with content <FilesMatch "^auth\.json$">
  Require all denied
</FilesMatch>
change user1 to your username
3. replace OPENROUTER_KEY with your key in status.php, generate.php
4. replace UPLOAD_URL, HTTP-Referer in generate.php with your own url
5. load some credits in openrouter for your balance
