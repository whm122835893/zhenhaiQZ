#!/usr/bin/env bash
# 逐个探测源站公开端点的 data 结构，产出「哪些端点用裸数组、哪些用 {list,total}」清单。
#
# 为什么需要这个脚本：
#   源站的「分页」没有统一写法 —— 有的返回裸数组，有的返回 {list,total}。
#   本项目前端是照源站写的，后端必须逐端点对齐，
#   否则切到本地后端会报 .map is not a function 之类运行期错误。
#   api.md 里只写了「部分接口带 total」，没列举是哪些，
#   所以必须实测。
#
# 用法：bash scripts/probe_source_shapes.sh

SOURCE="${1:-https://api.shengtai.cyou}"
Q="appid=__UNI__22FD095&version=1.3.2&deviceId=shapeprobe&deviceModel=PC&platform=web"

# 只探测无需登录的端点（需登录的拿不到 11111 之后的真实结构）
EPISODES=(
  "/item"
  "/item/category"
  "/item/author"
  "/item/copyright"
  "/item/merge"
  "/basic/banner"
  "/basic/notice"
  "/basic/notice/category"
  "/product/tp_category"
  "/nft/secondary"
  "/nft/secondary/sell"
  "/nft/secondary/sold"
  "/nft/secondary/apply"
  "/nft/secondary/trust"
  "/nft/digitalAlbum/calendar"
  "/activity/recast/doll-type"
)

describe() {
  php -r '
    $j = json_decode(stream_get_contents(STDIN), true);
    if ($j === null)           { echo "PARSE_FAIL"; exit; }
    if (!array_key_exists("data", $j)) { echo "NO_DATA"; exit; }
    $d = $j["data"];
    if ($d === null)            { echo "null"; exit; }
    if (!is_array($d))          { echo gettype($d); exit; }
    if ($d === [])              { echo "empty-array"; exit; }
    $isList = array_keys($d) === range(0, count($d) - 1);
    if ($isList) {
        $first = $d[0];
        $n = is_array($first) ? count($first) : 0;
        echo "bare-array(" . count($d) . ")" . ($n ? "[{$n}字段]" : "[空]");
        exit;
    }
    if (array_key_exists("list", $d)) {
        echo "{list,total}";
        exit;
    }
    echo "object{" . implode(",", array_slice(array_keys($d), 0, 8)) . "}";
  '
}

printf "%-40s %-8s %s\n" "端点" "code" "data 结构"
printf "%.0s-" {1..90}; echo

declare -A SUMMARY

for ep in "${EPISODES[@]}"; do
  body=$(curl -s --max-time 20 "$SOURCE$ep?pageNo=1&pageSize=5&$Q")
  code=$(php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["code"] ?? "?";' <<< "$body")
  shape=$(describe <<< "$body")

  case "$shape" in
    bare-array*) key="裸数组" ;;
    "{list,total}") key="{list,total}" ;;
    "empty-array") key="空数组" ;;
    "null") key="null" ;;
    *) key="其它" ;;
  esac
  SUMMARY["$key"]=$((${SUMMARY["$key"]:-0} + 1))

  printf "%-40s %-8s %s\n" "$ep" "$code" "$shape"
done

printf "%.0s-" {1..90}; echo
echo "汇总："
for k in "${!SUMMARY[@]}"; do echo "  $k : ${SUMMARY[$k]} 个"; done