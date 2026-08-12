#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ARCHIVE_NAME="${GEOFLOW_ARCHIVE_NAME:-nanshanlin-geoflow-2.3.0-20260812.tar.gz}"
ARCHIVE="$SCRIPT_DIR/$ARCHIVE_NAME"
CHECKSUM="$ARCHIVE.sha256"
APP_DIR="${GEOFLOW_APP_DIR:-/srv/nanshanlin/geoflow/app}"
BACKUP_ROOT="${GEOFLOW_BACKUP_ROOT:-/srv/nanshanlin/geoflow/backups}"
COMPOSE_FILE="${GEOFLOW_COMPOSE_FILE:-docker-compose.prod.yml}"
timestamp="$(date +%Y%m%d-%H%M%S)"
work_dir=""
backup_dir=""
code_switched="false"

log() { printf '[nanshanlin-geoflow-2.3] %s\n' "$1"; }
fail() { printf '[nanshanlin-geoflow-2.3] ERROR %s\n' "$1" >&2; exit 1; }

cleanup() {
  status=$?
  trap - EXIT
  if [[ "$status" -ne 0 && "$code_switched" == "true" && -n "$backup_dir" && -d "$backup_dir/code" ]]; then
    log "更新失败，恢复上一版代码；数据库迁移为2.3兼容的增量表，不自动回滚数据"
    sudo rsync -a --delete \
      --exclude='.env.prod' --exclude='storage/' --exclude='docker-data/' \
      "$backup_dir/code/" "$APP_DIR/" || true
    (cd "$APP_DIR" && sudo docker compose --env-file .env.prod -f "$COMPOSE_FILE" up -d --build) || true
  fi
  [[ -n "$work_dir" && -d "$work_dir" ]] && rm -rf "$work_dir"
  exit "$status"
}
trap cleanup EXIT

[[ "${CONFIRM_GEOFLOW_UPGRADE:-NO}" == "YES" ]] || fail "请设置 CONFIRM_GEOFLOW_UPGRADE=YES"
[[ -f "$ARCHIVE" && -f "$CHECKSUM" ]] || fail "升级包或校验文件不存在"
[[ -d "$APP_DIR" && -f "$APP_DIR/.env.prod" && -f "$APP_DIR/$COMPOSE_FILE" ]] || fail "GEOFlow 生产目录、环境文件或 Compose 文件不存在"
for cmd in awk curl docker grep mktemp rsync sha256sum sudo tar; do command -v "$cmd" >/dev/null || fail "缺少命令：$cmd"; done

(cd "$SCRIPT_DIR" && sha256sum -c "$(basename "$CHECKSUM")")
tar -tzf "$ARCHIVE" | grep -Eq '^GEOFlow/version.json$' || fail "升级包缺少 version.json"
tar -tzf "$ARCHIVE" | grep -Eq '^GEOFlow/\.env\.prod$|^GEOFlow/(vendor|node_modules|docker-data)/' && fail "升级包包含环境密钥或运行依赖目录"

work_dir="$(mktemp -d "/tmp/nanshanlin-geoflow-2.3-${timestamp}.XXXXXX")"
tar -xzf "$ARCHIVE" -C "$work_dir"
new_dir="$work_dir/GEOFlow"
[[ -f "$new_dir/version.json" && -f "$new_dir/$COMPOSE_FILE" ]] || fail "升级包结构不完整"
grep -q '"version": "2.3.0"' "$new_dir/version.json" || fail "升级包不是 GEOFlow 2.3.0"
grep -q 'GEOFLOW_SSO_SECRET' "$new_dir/.env.prod.example" || fail "升级包缺少南山林 SSO 配置"
grep -q "Route::get('sso-login'" "$new_dir/routes/web.php" || fail "升级包缺少南山林 SSO 路由定义"
grep -q 'function ssoLogin' "$new_dir/app/Http/Controllers/Admin/AdminAuthController.php" || fail "升级包缺少南山林 SSO 控制器"

set -a
source "$APP_DIR/.env.prod"
set +a
db_user="${DB_USERNAME:-geo_user}"
db_name="${DB_DATABASE:-geo_flow}"

sudo mkdir -p "$BACKUP_ROOT"
backup_dir="$BACKUP_ROOT/geoflow-2.3-upgrade-$timestamp"
sudo mkdir -p "$backup_dir/code"

log "备份现有代码、PostgreSQL 和 storage"
sudo rsync -a \
  --exclude='.env.prod' --exclude='storage/' --exclude='docker-data/' \
  "$APP_DIR/" "$backup_dir/code/"
sudo docker exec geoflow-postgres-prod pg_dump -U "$db_user" -d "$db_name" | sudo tee "$backup_dir/postgres.sql" >/dev/null
sudo tar -C "$APP_DIR" -czf "$backup_dir/storage.tar.gz" storage

log "覆盖代码，保留环境配置、数据库目录和 storage"
sudo rsync -a --delete \
  --exclude='.env.prod' --exclude='storage/' --exclude='docker-data/' \
  "$new_dir/" "$APP_DIR/"
code_switched="true"

cd "$APP_DIR"
log "构建 GEOFlow 2.3 生产镜像"
sudo docker compose --env-file .env.prod -f "$COMPOSE_FILE" build app web
sudo docker compose --env-file .env.prod -f "$COMPOSE_FILE" up -d postgres redis

log "执行2.3增量数据库迁移，不运行 geoflow:install、不导入参考内容"
sudo docker compose --env-file .env.prod -f "$COMPOSE_FILE" run --rm --no-deps app php artisan migrate --force

log "启动全部 GEOFlow 服务并清理缓存"
sudo docker compose --env-file .env.prod -f "$COMPOSE_FILE" up -d --force-recreate
sudo docker exec geoflow-app-prod php artisan optimize:clear

log "执行只读安全审计；仅允许已确认的 MANAGED_REGISTRY_ORPHAN 中风险项"
audit_json="$(sudo docker exec geoflow-app-prod php artisan geoflow:security-audit --json 2>/dev/null || true)"
[[ -n "$audit_json" ]] || fail "安全审计没有返回可读结果"
if ! printf '%s' "$audit_json" | sudo docker exec -i geoflow-app-prod php -r '
    $report = json_decode(stream_get_contents(STDIN), true);
    if (! is_array($report) || ! isset($report["summary"], $report["findings"]) || ! is_array($report["findings"])) {
        exit(2);
    }
    if ((int) ($report["summary"]["critical"] ?? -1) !== 0 || (int) ($report["summary"]["high"] ?? -1) !== 0) {
        exit(3);
    }
    foreach ($report["findings"] as $finding) {
        if (($finding["code"] ?? "") !== "MANAGED_REGISTRY_ORPHAN" || ($finding["severity"] ?? "") !== "medium") {
            exit(4);
        }
    }
'; then
  printf '%s\n' "$audit_json" >&2
  fail "安全审计存在未获授权的风险项，停止发布"
fi
if grep -q '"code":"MANAGED_REGISTRY_ORPHAN"' <<<"$audit_json"; then
  log "WARN 已按授权保留 MANAGED_REGISTRY_ORPHAN 中风险项；不删除登记或图片，不触发版本回滚"
  printf '%s\n' "$audit_json"
else
  log "安全审计通过"
fi

log "验证版本、SSO 路由、容器和本机入口"
sudo docker exec geoflow-app-prod grep -q '"version": "2.3.0"' /var/www/html/version.json || fail "容器内版本不是2.3.0"
sudo docker exec geoflow-app-prod php artisan route:list --name=admin.sso-login | grep -q 'admin.sso-login' || fail "SSO 路由不存在"
for container in geoflow-postgres-prod geoflow-redis-prod geoflow-app-prod geoflow-web-prod geoflow-queue-prod geoflow-scheduler-prod geoflow-reverb-prod; do
  sudo docker ps --format '{{.Names}} {{.Status}}' | grep -Eq "^${container} .*Up" || fail "容器未运行：$container"
done
curl -fsS -o /dev/null http://127.0.0.1:18080/geo_admin/login || fail "GEOFlow 本机后台入口不可访问"

code_switched="false"
log "GEOFlow 2.3.0 南山林定制版更新成功"
log "现有账号、文章、配置、知识库、数据库和 storage 均已保留"
log "备份目录：$backup_dir"
