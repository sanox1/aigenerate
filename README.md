# AI generate
uses openrouter api to generate video and images. For personal use only.

Models used:  heygen/heygen-video-1 and tencent/hy-image-v3.5-preview

Deploy checklist


place all files and folders on any hosting with php / curl enabled

1. make-hash.php → run once → copy hash and put it in auth.json as password → delete make-hash.php.
2. put auth.json with chmod 600, in the webroot and .htaccess with content <FilesMatch "^auth\.json$">
  Require all denied
</FilesMatch>
  change user1 to your username

4. replace OPENROUTER_KEY with your key in status.php, generate.php, download.php
5. replace UPLOAD_URL, HTTP-Referer in generate.php with your own url
6. load some credits in openrouter.ai for your balance
7. Generate a management key in openrouter.ai for balance.php and paste it there.
8. remember to clean uploads dir periodically or if your hosting allows it , clean it every day via a cronjob.
