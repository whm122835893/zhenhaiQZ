#!/usr/bin/env bash
# 臻海 QZ 沙箱一键恢复脚本：MariaDB + 数据库重建 + 演示数据 + 后端 8787 + 前端 5173
# 用法：bash scripts/dev-restore.sh （在仓库根目录执行）
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BA="$ROOT/server/buildadmin"
B=http://127.0.0.1:8787
DEMO_PWD=demo123456

echo "== 1. MariaDB =="
if ! command -v mariadbd >/dev/null 2>&1 && ! command -v mysqld >/dev/null 2>&1; then
  apt-get update -q && apt-get install -y -q mariadb-server
fi
if ! mysqladmin ping >/dev/null 2>&1; then
  setsid nohup mysqld_safe > /tmp/mysqld.log 2>&1 &
  for i in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
fi
mysqladmin ping >/dev/null 2>&1 && echo "MariaDB: ok"

echo "== 2. 建库建用户 =="
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS nft DEFAULT CHARSET utf8mb4;
CREATE USER IF NOT EXISTS 'nft'@'localhost' IDENTIFIED BY 'nft123456';
CREATE USER IF NOT EXISTS 'nft'@'127.0.0.1' IDENTIFIED BY 'nft123456';
GRANT ALL PRIVILEGES ON nft.* TO 'nft'@'localhost'; GRANT ALL PRIVILEGES ON nft.* TO 'nft'@'127.0.0.1';
FLUSH PRIVILEGES;"

echo "== 3. 迁移 + 业务表 + 种子 =="
cd "$BA"
php think migrate:run > /dev/null 2>&1 || true
mysql -unft -pnft123456 nft < database/nft.sql
mysql -unft -pnft123456 nft < database/seed_demo.sql
# 幂等：种子重复导入时清掉旧数据重来
mysql -unft -pnft123456 nft -e "SELECT COUNT(*) FROM collection;" | grep -q 3 && echo "数据: 3 藏品就绪"

echo "== 4. 重置后台密码 admin/123456 =="
H=$(php -r "define('ROOT_PATH', '$BA/'); require '$BA/vendor/autoload.php'; \$app = new think\App(); \$app->initialize(); echo hash_password('123456');")
mysql -unft -pnft123456 nft -e "UPDATE admin SET password='$H', salt='' WHERE username='admin';"

echo "== 5. 启动后端 8787 =="
if ! curl -s -o /dev/null http://127.0.0.1:8787/api/nft_collection/index; then
  setsid nohup php think run --host 0.0.0.0 --port 8787 > /tmp/think-run.log 2>&1 &
  sleep 3
fi
echo "backend: $(curl -s -o /dev/null -w '%{http_code}' $B/api/nft_collection/index)"

echo "== 6. 演示用户 + 一级购 + 挂单 + 二级闭环 =="
reg(){ curl -s -X POST $B/api/nft_auth/register -d "username=$1&password=$DEMO_PWD" | grep -q '"code":1' && echo "注册 $1 ok"; }
reg userA; reg userB; reg 13800000001; reg 13800000002
TA=$(curl -s -X POST $B/api/nft_auth/login -d "username=13800000001&password=$DEMO_PWD" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['token'])")
TB=$(curl -s -X POST $B/api/nft_auth/login -d "username=13800000002&password=$DEMO_PWD" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['token'])")
for cid in 1 2 3; do
  o=$(curl -s -X POST $B/api/nft_collection/order -H "ba-user-token: $TA" -d "collection_id=$cid&num=2" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['order_no'])" 2>/dev/null)
  [ -n "$o" ] && curl -s -X POST $B/api/nft_collection/pay_mock -H "ba-user-token: $TA" -d "order_no=$o" > /dev/null
done
curl -s "$B/api/nft_hold/index?status=0&page=1&limit=50" -H "ba-user-token: $TA" | python3 -c "
import sys, urllib.request, json
rows=json.load(sys.stdin)['data']['list']
price={'1':'59.99','2':'129.99','3':'259.99'}
B='$B'; TA='$TA'
for r in rows:
    if r['sn'].endswith('N1'):
        d='hold_id=%s&price=%s'%(r['id'],price[str(r['collection_id'])])
        req=urllib.request.Request(B+'/api/nft_market/sell', data=d.encode(), headers={'ba-user-token':TA}, method='POST')
        print('sell', json.load(urllib.request.urlopen(req))['msg'])
"
mid=$(curl -s "$B/api/nft_market/index?collection_id=1" | python3 -c "import sys,json;l=json.load(sys.stdin)['data']['list'];print(l[0]['id'] if l else '')")
[ -n "$mid" ] && curl -s -X POST $B/api/nft_market/buy -H "ba-user-token: $TB" -d "id=$mid" | python3 -c "import sys,json;print('buy:',json.load(sys.stdin)['msg'])"
echo "市场在售: $(curl -s "$B/api/nft_market/index?page=1&limit=10" | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['total'])")"

echo "== 7. 启动前端 5173 =="
cd "$ROOT"
if ! curl -s -o /dev/null http://127.0.0.1:5173/; then
  setsid nohup npm run dev > /tmp/vite-dev.log 2>&1 &
  for i in $(seq 1 20); do curl -s -o /dev/null http://127.0.0.1:5173/ && break; sleep 1; done
fi
echo "vite: $(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:5173/)"
echo "== 完成 =="
echo "C 端: http://127.0.0.1:5173   后台: http://127.0.0.1:8787/index.html#/admin (admin/123456)"
echo "演示账号: 13800000001 / 13800000002 / userA / userB  密码 $DEMO_PWD"
