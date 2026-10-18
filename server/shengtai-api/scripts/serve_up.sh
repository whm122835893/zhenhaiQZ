#!/usr/bin/env bash
# 一键恢复本地三件套（沙箱休眠后进程会被清掉，用这个拉起来）
#
#   bash scripts/serve_up.sh
#
# 顺序有依赖：数据库 -> 后端(PHP) -> 前端(Vite)
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"   # server/shengtai-api
WEB="$(cd "$ROOT/../.." && pwd)"                          # 前端项目根

ok()   { printf '  \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; }

echo "[1/3] MariaDB"
if mysqladmin ping >/dev/null 2>&1; then
  ok "已在运行"
else
  # 沙箱休眠后 mysqld 进程消失，但数据目录完好，直接拉起即可
  ( mysqld_safe >/tmp/mysqld_safe.log 2>&1 & )
  for i in $(seq 1 30); do
    mysqladmin ping >/dev/null 2>&1 && break
    sleep 1
  done
  mysqladmin ping >/dev/null 2>&1 && ok "已启动" || warn "启动失败，见 /tmp/mysqld_safe.log"
fi

echo "[2/3] ThinkPHP 后端 :8921"
if curl -s -m 3 -o /dev/null "http://127.0.0.1:8921/item/2109928427009183745" 2>/dev/null; then
  ok "已在运行"
else
  # ⚠️ 必须重启 PHP，不能只等它自己恢复：
  #    数据库重启后旧连接失效，进程会「在但不响应」（curl 返回 000）
  pkill -f "php.*8921" >/dev/null 2>&1
  sleep 1
  ( cd "$ROOT" && setsid nohup php -d opcache.enable_cli=0 -S 0.0.0.0:8921 \
      -t public public/router.php >/tmp/tp8921.log 2>&1 </dev/null & )
  for i in $(seq 1 20); do
    curl -s -m 3 -o /dev/null "http://127.0.0.1:8921/item/2109928427009183745" 2>/dev/null && break
    sleep 1
  done
  curl -s -m 3 -o /dev/null "http://127.0.0.1:8921/item/2109928427009183745" 2>/dev/null \
    && ok "已启动" || warn "启动失败，见 /tmp/tp8921.log"
fi

echo "[3/3] Vite 前端 :5173"
if curl -s -m 3 -o /dev/null "http://127.0.0.1:5173/" 2>/dev/null; then
  ok "已在运行"
else
  ( cd "$WEB" && setsid nohup npx vite --host 0.0.0.0 --port 5173 \
      >/tmp/vite5173.log 2>&1 </dev/null & )
  for i in $(seq 1 40); do
    curl -s -m 3 -o /dev/null "http://127.0.0.1:5173/" 2>/dev/null && break
    sleep 1
  done
  curl -s -m 3 -o /dev/null "http://127.0.0.1:5173/" 2>/dev/null \
    && ok "已启动" || warn "启动失败，见 /tmp/vite5173.log"
fi

echo
echo "地址：前端 http://127.0.0.1:5173   后端 http://127.0.0.1:8921"