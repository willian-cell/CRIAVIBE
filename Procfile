web: sh -c "php -d upload_max_filesize=50M -d post_max_size=60M -S 0.0.0.0:${PORT:-8080} router.php"
worker: sh -c "php api/workers/image_worker.php"
