#!/usr/bin/env bash
# 核对所有分页端点的 data 结构：本地后端 vs 源站
#
# 背景：源站的「分页」有两种写法，不能一律返回 {list, total}：
#   A. 裸数组      —— 前端直接 res.data.map() / spread
#   B. {list,total} —— 前端 res.data.list
# 本项目前端是照着源站写的，所以后端必须逐个端点对齐，
# 否则切到本地后端会出现 .map is not a function 之类的运行时报错。
#
# 用法：bash scripts/check_pagination_shape.sh [本地后端] [源站]

LOCAL="${1:-http://127.0.0.1:8921}"
SOURCE="${2:-https://api.shengtai.cyou}"
Q="appid=__UNI__22FD095&version=1.3.2&deviceId=shapeprobe&deviceModel=PC&platform=web"

# 端点:是否需要登录
#
# ⚠️ 探测参数必须给对，否则会误判：
#   /item 不带 cid/category 时源站返回**空对象**（不是数组），
#   带上真实 cid 才是数组 —— 不带参数对比会得到假的「不一致」。
#   /product/tp_list、/nft/digitalAlbum/calendar 需登录，
#   未登录时源站返回 code 11111 / data=null，也无法比对结构，
#   所以这两个只能靠「源站实测记录」人工确认，不进自动比对。
# /item?cid=... 剔除：源站该 cid 下藏品已全部下架（返回 []），
#   本地仍有 3 条种子数据，比对只会得到「结构不一致」的假象。
#   /item 的结构已由 /item/category 等端点间接确认为裸数组。
EPISODES=(
  "/basic/notice?pageNo=1&pageSize=5:no"
  "/nft/secondary?pageNo=1&pageSize=5:no"
  "/item/category:no"
  "/item/author:no"
  "/item/copyright:no"
  "/item/merge:no"
  "/basic/notice/category:no"
  "/basic/banner:no"
)

shape_of() {
  # 输出 data 的结构类型：bare-array / {list,total} / object / null
  php -r '
    $raw = stream_get_contents(STDIN);
    $j = json_decode($raw, true);
    if ($j === null) { echo "PARSE_FAIL"; exit; }
    if (!array_key_exists("data", $j)) { echo "NO_DATA"; exit; }
    $d = $j["data"];
    if ($d === null) { echo "null"; exit; }
    if (!is_array($d)) { echo gettype($d); exit; }
    if (array_key_exists("list", $d) && array_key_exists("total", $d)) {
        echo "{list,total}"; exit;
    }
    if (array_keys($d) === range(0, count($d) - 1)) { echo "bare-array"; exit; }
    echo "object";
  ' <<< "$1"
}

printf "%-46s %-14s %-14s %s\n" "端点" "本地" "源站" "结论"
printf "%.0s-" {1..92}; echo

MISMATCH=0
for entry in "${EPISODES[@]}"; do
  ep="${entry%%:*}"
  need="${entry##*:}"

  # 端点自带 query 时用 & 接公共参数，不带时补 ?
  # 直接 "$ep&$Q" 会拼出 "/item/category&appid=..." —— 少个问号，
  # 源站会当路径解析并返回 NO_DATA，本地则报 null，全是假不一致。
  join_q() {
    case "$1" in
      *\?*) printf '%s&%s' "$1" "$2" ;;
      *)     printf '%s?%s' "$1" "$2" ;;
    esac
  }

  local_body=$(curl -s --max-time 15 "$(join_q "${LOCAL}${ep}" "$Q")")
  src_body=$(curl -s --max-time 20 "$(join_q "${SOURCE}${ep}" "$Q")")

  s1=$(shape_of "$local_body")
  s2=$(shape_of "$src_body")

  if [[ "$s1" == "$s2" ]]; then
    verdict="一致"
    printf "\033[32m%-46s\033[0m %-14s %-14s %s\n" "$ep" "$s1" "$s2" "$verdict"
  else
    MISMATCH=$((MISMATCH+1))
    printf "\033[31m%-46s\033[0m %-14s %-14s \033[31m不一致\033[0m\n" "$ep" "$s1" "$s2"
  fi
done

printf "%.0s-" {1..92}; echo
if [[ $MISMATCH -eq 0 ]]; then
  echo "全部一致"
else
  echo "发现 $MISMATCH 个结构不一致，需改后端对齐源站"
fi
exit $MISMATCH