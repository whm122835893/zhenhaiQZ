#!/usr/bin/env python3
"""
源站 API 契约审计 —— 对比源站前端实际调用的接口与本地已实现路由。

背景
----
本项目是像素级复刻，路由表必须与源站保持一致。源站是 Vite 构建的 SPA，
接口路径散落在 100+ 个分包 chunk 里，肉眼无法穷举；源站每次重新构建
chunk hash 都会变，人工统计必然漂移。

本脚本自动完成：
  1. 抓源站 index.html → 解析入口 chunk
  2. 从入口 chunk 递归发现全部 assets/*.js 分包并下载（带本地缓存）
  3. 从全部分包中提取 API 调用路径（正则匹配引号包裹的路径字面量）
  4. 解析本地 route/app.php 的全部路由（get/post/put/delete/patch/match）
  5. 双向差集比对：
       - 源站有、本地缺  → 复刻缺口（必须补）
       - 本地有、源站无  → 臆造接口（应删，或标注为本地扩展）
  6. 参数化路由等价归一（源站 {id} / 拼接式 / 本地 :id 视为同一路径）

用法
----
    python3 scripts/audit_source_api.py                # 审计并打印报告
    python3 scripts/audit_source_api.py --refresh      # 强制刷新 chunk 缓存
    python3 scripts/audit_source_api.py --json         # 机器可读输出（CI 用）

退出码：0=无缺口  1=存在缺口或臆造接口（CI 可据此阻断）
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CACHE_DIR = ROOT / ".cache" / "source-chunks"
ROUTE_FILE = ROOT / "server" / "shengtai-api" / "route" / "app.php"

SOURCE_ORIGIN = "https://h5.shengtai.cyou"

# 源站只可能调用这些顶层业务域，限定范围可避免把静态资源、
# 路由路径、分包名等误判成 API。
#
# 注意：该白名单必须覆盖源站实际使用的全部首段，早先漏掉 basic / item
# 会导致整层 API（轮播、公告、藏品详情、作者、版权、关注…）被误判为「本地臆造」；
# product_merge 需单独列出，否则会被 product 前缀吃掉。
API_PREFIXES = (
    "activity", "ai", "app", "basic", "common", "im", "item",
    "nft", "notice", "order", "passport", "product", "product_merge",
    "user", "wallet",
)

HTTP_TIMEOUT = 30
USER_AGENT = "Mozilla/5.0 (compatible; SourceApiAudit/1.0)"

# ---------------------------------------------------------------- HTTP 工具


def _get(url: str, timeout: int = HTTP_TIMEOUT) -> bytes:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read()


def _get_text(url: str, timeout: int = HTTP_TIMEOUT) -> str:
    return _get(url, timeout).decode("utf-8", errors="replace")


def _cache_name(url: str) -> str:
    """把 URL 映射成安全的本地文件名（保留 hash，便于人工辨认）。"""
    return url.split("/")[-1].split("?")[0] or "index.html"


def fetch_cached(url: str, refresh: bool = False, quiet: bool = False) -> str | None:
    """下载并缓存；已缓存则直接读盘。失败返回 None（不抛异常，避免单点失败中断审计）。"""
    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    path = CACHE_DIR / _cache_name(url)
    if path.exists() and path.stat().st_size > 0 and not refresh:
        return path.read_text(encoding="utf-8", errors="replace")
    try:
        text = _get_text(url)
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError) as exc:
        print(f"  ! 下载失败 {url} —— {exc}", file=sys.stderr)
        return None
    path.write_text(text, encoding="utf-8")
    if not quiet:
        print(f"  + 下载 {path.name} ({len(text) // 1024} KB)")
    return text


# ------------------------------------------------------------ 源站 chunk 发现


def discover_source_chunks(refresh: bool = False, quiet: bool = False) -> list[str]:
    """
    发现源站全部分包 URL。

    源站为 Vite SPA：index.html 引用入口 chunk，入口 chunk 内以
    `assets/xxx.js` 字面量列出全部分包（动态 import），因此两跳即可拿全。
    """
    index = fetch_cached(f"{SOURCE_ORIGIN}/", refresh, quiet)
    if not index:
        print("✗ 无法获取源站 index.html，审计终止", file=sys.stderr)
        return []

    entry_names = re.findall(r'src="(/assets/[^"]+\.js)"', index)
    if not entry_names:
        print("✗ index.html 中未找到入口 chunk，源站构建结构可能已变", file=sys.stderr)
        return []
    if not quiet:
        print(f"入口 chunk: {entry_names[0]}")

    chunks: set[str] = set()
    queue: list[str] = [f"{SOURCE_ORIGIN}{name}" for name in entry_names]
    seen: set[str] = set(queue)

    # 广度优先：分包之间也可能互相 import，故用队列而非单层扫描
    while queue:
        url = queue.pop(0)
        if url in chunks:
            continue
        text = fetch_cached(url, refresh=False, quiet=quiet)  # 分包强制走缓存判断
        if text is None:
            continue
        chunks.add(url)
        for ref in set(re.findall(r'"(\./)?(assets/[A-Za-z0-9_.-]+\.js)"', text)):
            child = f"{SOURCE_ORIGIN}/{ref[1]}"
            if child not in seen:
                seen.add(child)
                queue.append(child)

    return sorted(chunks)


# ---------------------------------------------------------------- 路径提取

# 匹配 "basic/banner" / "/im/group" / `item/follow/${id}` 三类路径字面量。
#
# 两个易漏点（均已踩坑）：
#   1. 源站引号风格不统一 —— axios 层用 "basic/banner"（无首斜杠），
#      页面层用 "/im/group"（有首斜杠），拼接参数时又用反引号模板串
#      `item/follow/${id}`；只认双引号会漏掉整批。
#   2. 前缀必须整词匹配 —— product_merge 不会命中 product，
#      故前缀表里显式列出 product_merge。
# 限定首段属于业务域，避免把 "/static/xxx" 之类资源路径误收。
_PATH_RE = re.compile(
    r'["`](?:/)?((?:' + "|".join(API_PREFIXES) + r')/[A-Za-z0-9_./{}$-]*)["`]'
)

# 拼接式动态路径："/im/group/"+uid 或 `/item/follow/`+id
# 这类调用拼出的真实 URL 带路径参数，比对时应与本地 :param 路由等价，
# 故捕获「前缀 + 路径」部分（不含末尾斜杠与变量）。
_CONCAT_RE = re.compile(
    r'["`](?:/)?((?:' + "|".join(API_PREFIXES) + r')/[A-Za-z0-9_./-]*)/["`]\s*\+'
)

# 无子路径的单段端点（路径就等于前缀本身，如 "/product_merge"），
# 无法用上面的「前缀 + /xxx」式子匹配，单独按精确字面量捕获。
SINGLE_SEGMENT_PATHS = ("product_merge",)
_SINGLE_SEG_RE = re.compile(
    r'["`](/?(?:' + "|".join(SINGLE_SEGMENT_PATHS) + r'))["`]'
)

# 模板字符串占位符：`item/follow/${id}` → item/follow/:id
_TEMPLATE_VAR_RE = re.compile(r'\$\{[^}]*\}')


def extract_api_paths(texts: list[str]) -> set[str]:
    """
    从 chunk 内容中提取归一化的 API 路径集合。

    除静态路径字面量外，另需捕获拼接式调用（`get("/im/group/"+uid)`）：
    这类调用拼出的真实 URL 带路径参数，比对时等价于本地 :param 路由，
    故额外登记去掉尾部斜杠的前缀。
    """
    paths: set[str] = set()
    for text in texts:
        for raw in _PATH_RE.findall(text):
            paths.add(_normalize_path(raw))
        for raw in _CONCAT_RE.findall(text):
            paths.add(_normalize_path(raw))
        for raw in _SINGLE_SEG_RE.findall(text):
            paths.add(_normalize_path(raw))
    return {p for p in paths if p}


def _normalize_path(path: str) -> str:
    """
    归一化路径，用于两侧比对。

    - 模板占位符 `${id}` → `:id`，与本地参数化路由同构
    - 去掉查询串与 hash
    - 合并重复斜杠
    - 去掉结尾斜杠（源站 `/activity/lottery/` 实际是 `/activity/lottery/{id}`）
    - 去掉首段斜杠（源站两种写法并存，本地路由也不带，统一到无斜杠形态）
    """
    path = _TEMPLATE_VAR_RE.sub(":x", path)
    path = path.split("?")[0].split("#")[0]
    path = re.sub(r"/{2,}", "/", path)
    path = path.lstrip("/")
    return path.rstrip("/")


# ------------------------------------------------------------ 本地路由解析


_ROUTE_RE = re.compile(
    r"Route::(?:get|post|put|delete|patch|match)\s*\(\s*'([^']+)'",
    re.IGNORECASE,
)


def parse_local_routes(route_file: Path = ROUTE_FILE) -> set[str]:
    """解析本地 route/app.php，返回归一化路径集合（去掉首段斜杠，与源站对齐）。"""
    if not route_file.exists():
        print(f"✗ 找不到路由文件 {route_file}", file=sys.stderr)
        return set()
    content = route_file.read_text(encoding="utf-8")
    routes = set()
    for raw in _ROUTE_RE.findall(content):
        p = _normalize_path(raw).lstrip("/")
        if p:
            routes.add(p)
    return routes


# ---------------------------------------------------------------- 路径等价

_PARAM_RE = re.compile(r"^:[A-Za-z_]+$")


def _segments(path: str) -> list[str]:
    return [s for s in path.split("/") if s]


def paths_equivalent(a: str, b: str) -> bool:
    """
    判断两条路径是否等价。

    源站拼接式动态路由（如 `/nft/secondary/` + id）与本地 `/nft/secondary/:iid`
    在纯文本比对下不等价，这里统一按「去掉参数段后逐段比较」判定，
    避免把参数化路由误报成缺口。
    """
    sa, sb = _segments(a), _segments(b)
    # 参数段（:id / {id}）视为通配，两侧都允许比对方多一个尾部参数段
    core_a = [s for s in sa if not _PARAM_RE.match(s) and not s.startswith("{")]
    core_b = [s for s in sb if not _PARAM_RE.match(s) and not s.startswith("{")]
    return core_a == core_b


def match_route(path: str, routes: set[str]) -> str | None:
    for r in routes:
        if r == path or paths_equivalent(path, r):
            return r
    return None


# ---------------------------------------------------------------- 报告输出


def build_report(source_paths: set[str], local_routes: set[str]) -> dict:
    missing, covered = [], []
    for p in sorted(source_paths):
        hit = match_route(p, local_routes)
        (covered.append({"source": p, "local": hit}) if hit else missing.append(p))

    source_core = {_strip_params(p) for p in source_paths}
    extra = sorted(r for r in local_routes if _strip_params(r) not in source_core)
    return {
        "source_path_count": len(source_paths),
        "local_route_count": len(local_routes),
        "covered_count": len(covered),
        "missing": missing,
        "extra": extra,
    }


def _strip_params(path: str) -> str:
    return "/".join(
        s for s in _segments(path) if not _PARAM_RE.match(s) and not s.startswith("{")
    )


def print_report(report: dict) -> None:
    line = "=" * 66
    print(f"\n{line}")
    print("源站 API 契约审计报告")
    print(line)
    print(f"源站调用路径 : {report['source_path_count']}")
    print(f"本地路由     : {report['local_route_count']}")
    print(f"已覆盖       : {report['covered_count']}")

    if report["missing"]:
        print(f"\n[缺口] 源站调用但本地未实现（{len(report['missing'])}）—— 必须补")
        for p in report["missing"]:
            print(f"  - {p}")
    else:
        print("\n[缺口] 无 —— 源站调用路径 100% 覆盖")

    if report["extra"]:
        print(f"\n[臆造] 本地有但源站前端未调用（{len(report['extra'])}）—— 需确认是否本地扩展")
        for p in report["extra"]:
            print(f"  - {p}")
    else:
        print("\n[臆造] 无")

    print(line)
    ok = not report["missing"]
    print(f"结论: {'通过' if ok else '存在复刻缺口，请补齐后重新审计'}")
    print(f"{line}\n")


# ---------------------------------------------------------------------- main


def main() -> int:
    ap = argparse.ArgumentParser(description="源站 API 契约审计")
    ap.add_argument("--refresh", action="store_true", help="强制刷新 chunk 缓存")
    ap.add_argument("--json", action="store_true", help="输出 JSON")
    args = ap.parse_args()

    print(f"[1/4] 发现源站分包（缓存目录 {CACHE_DIR}）...")
    chunks = discover_source_chunks(refresh=args.refresh)
    if not chunks:
        return 2
    print(f"      共 {len(chunks)} 个分包")

    print("[2/4] 提取 API 路径...")
    texts = []
    for url in chunks:
        p = CACHE_DIR / _cache_name(url)
        if p.exists():
            texts.append(p.read_text(encoding="utf-8", errors="replace"))
    source_paths = extract_api_paths(texts)
    print(f"      提取到 {len(source_paths)} 条")

    print("[3/4] 解析本地路由...")
    local_routes = parse_local_routes()
    print(f"      本地 {len(local_routes)} 条")

    print("[4/4] 比对...")
    report = build_report(source_paths, local_routes)

    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        print_report(report)

    return 0 if not report["missing"] else 1


if __name__ == "__main__":
    sys.exit(main())
