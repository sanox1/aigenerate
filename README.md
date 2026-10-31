# AI generate
uses openrouter api to generate video and images (currenlty only video). For personal use only.


Deploy checklist


place all files and folders on any hosting with php / curl enabled

1. make-hash.php → run once → copy hash and put it in auth.json as password → delete make-hash.php.
2. put auth.json with chmod 600, in the webroot and .htaccess with content <FilesMatch "^auth\.json$">
  Require all denied
</FilesMatch>
  change user1 to your username

4. replace OPENROUTER_KEY with your key in status.php, generate.php
5. replace UPLOAD_URL, HTTP-Referer in generate.php with your own url
6. load some credits in openrouter.ai for your balance
7. remember to clean uploads dir periodically or if your hosting allows it , clean it every day via cronjob.
