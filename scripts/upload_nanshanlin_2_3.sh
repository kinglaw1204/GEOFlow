#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="${GEOFLOW_RELEASE_DIR:-$REPO_ROOT/../releases}"
ARCHIVE_NAME="${GEOFLOW_ARCHIVE_NAME:-nanshanlin-geoflow-2.3.0-20260812.tar.gz}"
ARCHIVE="$RELEASE_DIR/$ARCHIVE_NAME"
SERVER="${NANSHANLIN_DEPLOY_SERVER:-barry@122.51.112.233}"
SSH_PORT="${NANSHANLIN_DEPLOY_SSH_PORT:-22022}"
REMOTE_DIR="${NANSHANLIN_REMOTE_RELEASE_DIR:-/srv/nanshanlin/releases/geoflow-2.3.0-20260812}"
PUBLISH_SCRIPT="$REPO_ROOT/scripts/publish_nanshanlin_2_3_on_server.sh"

[[ -f "$ARCHIVE" && -f "$ARCHIVE.sha256" && -f "$ARCHIVE.manifest.json" ]] || { echo "发布包不完整：$ARCHIVE" >&2; exit 1; }
(cd "$RELEASE_DIR" && shasum -a 256 -c "$ARCHIVE_NAME.sha256")

echo '[nanshanlin-geoflow-upload] 上传升级包、校验文件、清单和服务器更新脚本'
ssh -p "$SSH_PORT" "$SERVER" "mkdir -p '$REMOTE_DIR'"
scp -P "$SSH_PORT" "$ARCHIVE" "$ARCHIVE.sha256" "$ARCHIVE.manifest.json" "$PUBLISH_SCRIPT" "$SERVER:$REMOTE_DIR/"

printf '\n上传完成。服务器执行：\ncd %q\nchmod +x publish_nanshanlin_2_3_on_server.sh\nCONFIRM_GEOFLOW_UPGRADE=YES bash publish_nanshanlin_2_3_on_server.sh\n' "$REMOTE_DIR"
