#!/bin/sh
set -e

cd /app

# 等待数据库就绪
echo "Waiting for database..."
i=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0);} catch (Throwable $e) { exit(1);}' 2>/dev/null; do
  i=$((i+1))
  if [ "$i" -ge 60 ]; then
    echo "Database not reachable after 60 attempts, continuing anyway"
    break
  fi
  sleep 2
done

# 自动执行迁移（幂等；为既有库补齐申诉相关表）
php artisan migrate --force || echo "Migration skipped/failed, continuing..."

# 确保证据文件目录可写（文件走鉴权下载，不依赖软链；软链仅为兼容）
mkdir -p storage/app/public/appeal_evidences
php artisan storage:link 2>/dev/null || true

exec "$@"
