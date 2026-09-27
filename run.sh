#!/usr/bin/env bash
set -e

echo "🧹 停掉旧容器..."
docker compose down --remove-orphans

echo "🔥 无缓存构建镜像..."
docker compose build --no-cache

echo "🚀 启动服务（强制重建容器）..."
docker compose up --force-recreate --renew-anon-volumes
