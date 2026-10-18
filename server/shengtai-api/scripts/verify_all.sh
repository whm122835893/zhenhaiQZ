#!/usr/bin/env bash
# shengtai-api 全量端点验证
# 用法: bash scripts/verify_all.sh [BASE_URL]
# 默认 http://127.0.0.1:8921

B="${1:-http://127.0.0.1:8921}"
PASS=0; FAIL=0; FAILED=()

# 断言 code 字段
# $1=端点 $2=期望code $3=是否需登录(yes/no) $4=描述
check() {
  local ep="$1" want="$2" need="$3" desc="$4"
  local args=()
  [[ "$need" == "yes" ]] && args=(-H "Authorization: Bearer $TOKEN")
  local body
  body=$(curl -s "${args[@]}" "$B$ep")
  local code
  code=$(echo "$body" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["code"] ?? "PARSE_FAIL";' 2>/dev/null)
  if [[ "$code" == "$want" ]]; then
    PASS=$((PASS+1))
    printf "  \033[32m✓\033[0m %-42s code=%s  %s\n" "$ep" "$code" "$desc"
  else
    FAIL=$((FAIL+1)); FAILED+=("$ep")
    printf "  \033[31m✗\033[0m %-42s code=%s (期望 %s)  %s\n" "$ep" "$code" "$want" "$desc"
  fi
}

echo "=============================================="
echo " shengtai-api 端点验证  BASE=$B"
echo "=============================================="

# ---- 登录拿 token ----
PHONE="139$(printf '%06d' $((RANDOM % 1000000)))"
SMS=$(curl -s -X POST "$B/passport/captcha/sms" -H 'Content-Type: application/json' -d "{\"phone\":\"$PHONE\"}")
CODE=$(echo "$SMS" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["code"] ?? "";')
TOKEN=$(curl -s -X POST "$B/passport/signin/quick" -H 'Content-Type: application/json' \
  -d "{\"phone\":\"$PHONE\",\"code\":\"$CODE\"}" \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["token"] ?? "";')

if [[ -z "$TOKEN" ]]; then
  echo "\033[31m登录失败，终止\033[0m"; exit 1
fi
echo "登录成功 phone=$PHONE token=${#TOKEN}字符"

# 取一个真实 iid 供需要 iid 的端点使用
IID=$(curl -s "$B/item" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"][0]["iid"] ?? "";')
echo "样本 iid=$IID"
echo

echo "【公开端点】"
check "/item"                    0 no  "藏品目录"
check "/item/category"           0 no  "藏品分类"
check "/item/author"             0 no  "作者"
check "/item/copyright"          0 no  "版权"
check "/item/merge"              0 no  "合成配方列表(merges/needs=null)"
check "/basic/notice"            0 no  "公告列表"
check "/basic/notice/category"   0 no  "公告分类"
check "/basic/banner"            0 no  "轮播图"
check "/product/tp_category"      0 no  "图谱分类"
check "/nft/secondary"           0 no  "市场挂单"
check "/nft/secondary/sell?iid=$IID" 0 no "某藏品的寄售挂单"
check "/nft/secondary/sold"      0 no  "已售记录"
check "/nft/secondary/apply"     0 no  "求购单"
check "/nft/secondary/trust"     0 no  "委托单"
check "/activity/recast/type"  10203 no  "源站未上线"
check "/activity/product_merge" 10203 no  "源站未上线"
check "/wallet/yeepay"         11111 no  "易宝在鉴权组内，未登录先拦"
check "/nonexistent/path"      10203 no  "404兜底"

echo
echo "【未登录应 11111】"
check "/order"                11111 no "订单列表"
check "/nft"                  11111 no "我的藏品"
check "/user/profile"         11111 no "用户信息"
check "/passport/account"     11111 no "账号信息"

echo
echo "【需登录端点】"
check "/passport/account"        0 yes "账号信息"
check "/user/profile"            0 yes "用户信息"
check "/order"                   0 yes "订单列表(items恒空)"
check "/nft"                     0 yes "我的藏品"
check "/nft/secondary/follow"    0 yes "我的关注"
check "/nft/secondary/sell?iid=$IID" 0 yes "某藏品寄售挂单"
check "/im/group"                0 yes "群组列表"
check "/activity/points/balance" 0 yes "积分余额"
check "/user/invite"             0 yes "邀请信息"

echo
echo "=============================================="
echo " 通过 $PASS / 失败 $FAIL / 共 $((PASS+FAIL))"
[[ $FAIL -gt 0 ]] && echo " 失败清单: ${FAILED[*]}"
echo "=============================================="
exit $FAIL
