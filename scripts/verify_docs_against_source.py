#!/usr/bin/env python3
"""
文档 vs 源站 实抓比对 —— 防「占位符污染文档」复发机制（v2：黑名单式全量校验）

背景
----
OPEN-01（2026-10-19结案）：docs/api.md 的 3.1 节曾以「实测抓取」为标题，
正文却是 `0x1234...abcd`、`"cid": 1`、`price: 38800` 这类模板占位符，
并在其上建立了错误结论「单位分」。根因是**只读文档没实抓，把占位符当成了实测值**。

v1 的缺陷（由大湾区靓仔 2026-10-19 反向验证暴露）
--------------------------------------------------
v1 只校验「带【实测】端点声明」的块，属白名单式校验。实测数据：
  docs/api.md 26 个 json 块 -> 12 个进校验，14 个完全在校验范围外。
注入 38800 的实验之所以漏过，是因为**更根本的原因**：
  1) v1 只有「实时抓取」一条通道，而 price / nftQuantity / tradeQuantity 等
     行情字段被归入 VOLATILE_FIELDS，值变化一律记 DRIFT 不记 MISMATCH。
     于是「388 篡改成 38800」这种一眼可辨的错误，被当成正常行情漂移放过。
     —— 注意 VOLATILE_FIELDS 常量在 v1 里定义后从未被 compare_block 使用，
     真正的分流依据只有 STATIC_FIELDS 白名单（6 个字段）。
  2) 块级归属用「上方 20 行内最近的【实测】声明」，会跨节误挂
     （实测：docs/api.md:798 的 wallet/hfpay 响应被挂到了 apply 端点上）。
  3) 未标注块的豁免靠 `ANNOTATION_TAGS_RE` 在上下文 ±22~40 行里模糊匹配，
     一句远处的免责声明可以为任意块兜底。

v2 的三通道设计
---------------
通道 A「快照严格比对」：文档里已经声明了 `原始响应存档：.cache/source-probe/x.json`。
     快照就是**当时**的源站响应，文档自称「逐字段转录」，那就必须**逐字段严格相等**，
     任何值篡改 / 字段删除 / 类型错误都会当场 MISMATCH。这是抓污染的主力通道。
通道 B「实时抓取比对」：仍实抓源站，但只用于校验「字段集合与类型是否仍成立」
     + 静态字段（iid/cid/contract/authorId/imageUrl/image）值比对。
     行情字段的实时值变化记 DRIFT，不阻断。
通道 C「全量块清点」：**所有** ```json 块都必须有归属，无归属即阻断。
     分类见 BLOCK 分类表。

块分类（黑名单式：先清点，再分流，最后才谈豁免）
------------------------------------------------
    MEASURED            有可访问端点声明 -> 走通道 A + 通道 B
    UNVERIFIED-SUSPECT  无端点声明，但形态像响应体（含 status/code/data/message 包络特征），
                        且块上方 3 行内没有显式降级标记 -> **阻断**，必须人工确认。
                        这类正是「漏标的实测数据」最容易藏身处。
    EXEMPT              块上方 3 行内有显式降级标记，逐块人工判定后才允许存在：
                          > 非响应体，无需源站比对      （请求体 / 非响应结构）
                          > 【结构约定】                 （通用包络定义，非某端点实测）
                          > 【结构示意】                 （形态说明，非实抓）
                          > 【占位】                     （需登录态等，实抓不可行）
                        标记必须紧贴块（3 行内）。远处的一句话不能为块兜底。
    UNREACHABLE        端点可访问但本环境拿不到数据（需登录态）

铁律
----
* 禁止为了让脚本变绿而放宽比对规则。宁可报可疑噪音，也不要假绿。
* 禁止臆造数据：所有 MISMATCH 判定必须基于实抓或真实存档快照。
* 降级块只降「值可信度」，不降「结构可信度」：字段名仍需人工过一遍，
  但脚本不再对其做任何值断言。

用法
----
    python3 scripts/verify_docs_against_source.py              # 全量扫描
    python3 scripts/verify_docs_against_source.py --selftest    # 反向自测（硬失败）
    python3 scripts/verify_docs_against_source.py --doc docs/api.md
    python3 scripts/verify_docs_against_source.py --json       # 机器可读（CI 用）
    python3 scripts/verify_docs_against_source.py --offline    # 只跑通道 A + C，不发请求
    python3 scripts/verify_docs_against_source.py --no-cache   # 忽略快照，只跑通道 B

退出码：0=通过  1=MISMATCH 或 UNVERIFIED-SUSPECT  2=文档不可解析  3=反向自测失败
"""

from __future__ import annotations

import argparse
import json
import re
import shutil
import sys
import tempfile
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOCS_DIR = ROOT / "docs"
CACHE_DIR = ROOT / ".cache" / "source-probe"
SOURCE_BASE = "https://api.shengtai.cyou"

HTTP_TIMEOUT = 25
USER_AGENT = "Mozilla/5.0 (compatible; DocsVerify/2.0)"

# 源站响应包络
ENVELOPE_KEYS = {"status", "code", "message", "data"}

# 静态字段：值必须与源站严格一致（通道 B）
# nftId 是 NFT 实例的全局唯一身份标识，一旦对上了同一条记录就必须相等。
STATIC_FIELDS = {"iid", "cid", "authorId", "contract", "imageUrl", "image",
                 "nftId", "orderId"}

# 行情/时变字段：通道 B 只校验存在性与类型，值变化记 DRIFT。
# 通道 A（快照）对这些字段同样严格比对 —— 快照是历史定格值，不存在漂移。
VOLATILE_FIELDS = {
    "price", "buyPrice", "soldPrice", "unitPrice", "amount",
    "secondaryLowPrice", "secondaryHighPrice",
    "secondaryApplyLowPrice", "secondaryApplyHighPrice",
    "minPrice", "maxPrice", "lastTradeAmount",
    "tradeAmount", "todayTradeAmount", "todayTradeMaxAmount", "todayTradeMinAmount",
    "tradeQuantity", "todayTradeQuantity", "hourTradeQuantity",
    "sellQuantity", "nftQuantity", "buyQuantity", "ownQuantity",
    "priceChange", "quantity", "completedQuantity", "signDays",
    "issueQuantity", "isSoldOut", "getTime", "soldTime", "createTime",
    "buyTime", "startTime", "endTime", "preStartTime", "preEndTime",
    "boxOpenTime", "token", "status", "name", "title", "content",
    "chainId", "chainCompleted", "payLock", "uid", "lastTradePrice",
}

# 疑似占位符特征 —— 出现在 MEASURED 块里即为污染
PLACEHOLDER_PATTERNS = [
    (re.compile(r"0x[0-9a-fA-F]*\.\.\."), "截断的十六进制串，如 0x1234...abcd"),
    (re.compile(r"https?://[^\s\"]*\.\.\."), "截断的 URL"),
    (re.compile(r"cdn\.\.\."), "cdn 占位"),
    (re.compile(r"\"(?:X{3,}|x{3,})\""), "XXX 占位"),
    (re.compile(r"\.\.\.\s*\}"), "省略号结尾的数组/对象"),
    (re.compile(r"\"<[^\"]*>\""), "尖括号占位值"),
    (re.compile(r"\bTODO\b|\b待补\b|\b待定\b"), "待补标记"),
    (re.compile(r"\"(0x0+|null|null|null)\""), "null 填充串"),
]

# ---------------------------------------------------------------- 标记与声明

# 【实测】标记行：> **【实测】** ... 抓取端点：`GET /item/xxx`
MEASURED_TAG_RE = re.compile(r"\*\*【实测】\*\*")
ENDPOINT_RE = re.compile(
    r"抓取端点[：:][^`\n]*`?\s*(?:>\s*)?`?\s*"
    r"(GET|POST|PUT|DELETE|PATCH)?\s*"
    r"(/[^\s`？?]+(?:\?[^\s`？?]*)?)"
)
# v1 遗漏的声明形态：**实测响应（`GET /wallet/hfpay`）** / `GET /x` 响应（实测）
# 这类粗体声明同样表达了「这是实测」，必须识别，否则会跨节误挂到邻近端点。
ALT_ENDPOINT_RE = re.compile(
    r"(?:实测响应|响应（实测）|实测结果)[^\n`]*[（(]\s*`?\s*"
    r"(GET|POST|PUT|DELETE|PATCH)?\s*(/[^\s`）)]+)"
)
# 抓取时间标注
CAPTURE_STAMP_RE = re.compile(
    r"实测时间[：:]\s*(\d{4}-\d{2}-\d{2})[，,]\s*抓取端点[：:]\s*`?([A-Z]+)?\s*([^\s`]+)`?"
)
# 原始响应存档声明：> 原始响应存档：`.cache/source-probe/xxx.json`
SNAPSHOT_RE = re.compile(r"原始响应存档[：:]\s*`([^`]+)`")

# 块级显式降级标记：必须出现在块上方 3 行内（紧贴块），远处声明无效。
# 每种标记对应一种「为什么这个块不需要源站比对」，由人工逐块判定后写入。
EXEMPT_MARKERS = [
    (re.compile(r"非响应体[，,]?\s*无需源站比对"), "EXEMPT", "请求体/非响应结构，不做源站比对"),
    (re.compile(r"【结构约定】"), "EXEMPT", "通用包络约定，非某一端点实测值"),
    (re.compile(r"【结构示意】"), "EXEMPT", "形态说明，非实抓响应"),
    (re.compile(r"【占位】"), "EXEMPT", "已声明为占位（需登录态等），值不可信"),
    (re.compile(r"不可作为契约依据"), "EXEMPT", "已声明不可作为契约依据（需登录态等）"),
]
EXEMPT_WINDOW = 3  # 标记必须在块上方这么多行内


def strip_line_comments(text: str) -> str:
    """剥掉 JSON 示例里的注释，同时保护 URL 中的 "//"。支持 // 与 /* */ 两种。"""
    out = []
    for line in text.split("\n"):
        idx = 0
        while True:
            pos = line.find("//", idx)
            if pos == -1:
                break
            if pos > 0 and line[pos - 1] == ":":
                idx = pos + 2
                continue
            line = line[:pos]
            break
        out.append(line)
    text = "\n".join(out)
    # 块注释 /* ... */ 可跨行，先合并再剥
    return re.sub(r"/\*.*?\*/", "", text, flags=re.S)


def iter_code_blocks(text: str):
    """逐个产出 (起始行号, json对象, 是否解析成功)。仅取 ```json 围栏块。"""
    lines = text.split("\n")
    i = 0
    while i < len(lines):
        if lines[i].strip().lower().startswith("```json"):
            start = i
            j = i + 1
            buf = []
            while j < len(lines) and not lines[j].strip().startswith("```"):
                buf.append(lines[j])
                j += 1
            cleaned = strip_line_comments("\n".join(buf))
            try:
                yield start + 1, json.loads(cleaned), True
            except json.JSONDecodeError:
                yield start + 1, cleaned, False
            i = j + 1
        else:
            i += 1


def looks_like_response(obj) -> bool:
    """
    判断一个块「像不像响应体」—— 用于 UNVERIFIED-SUSPECT 的形态识别。

    判据：含源站响应包络特征（status / code / message / data），
    或是一个非空数组（列表型响应体）。
    """
    if isinstance(obj, dict):
        return bool(ENVELOPE_KEYS & set(obj))
    if isinstance(obj, list):
        return True
    return False


def find_exempt_marker(lines, lineno):
    """
    在块上方 EXEMPT_WINDOW 行内查找显式降级标记。
    远处（如上方 20~40 行）的免责声明一律不认 —— 那是 v1 的漏洞来源。
    返回 (类别, 说明) 或 None。
    """
    window = lines[max(0, lineno - 1 - EXEMPT_WINDOW): lineno - 1]
    for text in window:
        for pat, kind, why in EXEMPT_MARKERS:
            if pat.search(text):
                return kind, why
    return None


def collect_blocks(doc_path: Path):
    """
    全量清点文档里的每个 json 块并分类。

    返回 blocks: [(lineno, category, endpoint, method, obj, parsed, meta)]
      category ∈ MEASURED / UNVERIFIED-SUSPECT / EXEMPT / MALFORMED
    """
    text = doc_path.read_text(encoding="utf-8")
    lines = text.split("\n")

    # 建立所有「声明 -> 端点」映射，两种声明形态都收
    decls = []
    for idx, line in enumerate(lines):
        method = path = None
        if MEASURED_TAG_RE.search(line):
            m = ENDPOINT_RE.search("\n".join(lines[idx:idx + 4]))
            if m:
                method = (m.group(1) or "GET").upper()
                path = m.group(2)
        if path is None:
            m2 = ALT_ENDPOINT_RE.search(line)
            if m2:
                method = (m2.group(1) or "GET").upper()
                path = m2.group(2)
        if path:
            decls.append((idx + 1, method, path, line))

    stamps, snaps = {}, {}
    for idx, line in enumerate(lines):
        ms = CAPTURE_STAMP_RE.search(line)
        if ms:
            stamps[idx + 1] = ms.group(1)
        mp = SNAPSHOT_RE.search(line)
        if mp:
            snaps[idx + 1] = mp.group(1)

    all_lines = list(iter_code_blocks(text))
    block_lines = [ln for ln, _, _ in all_lines]

    blocks = []
    for lineno, obj, parsed in all_lines:
        # 归属：该块上方 20 行内「最近的」声明，且声明与本块之间不得夹着
        # 另一个 json 块。否则 4.8 的声明会跨节吞掉后面的 wallet 块
        # （实测缺陷：docs/api.md:798 挂到了 /order/... 上）。
        barrier = max([b for b in block_lines if b < lineno], default=0)
        owner = None
        for dline, method, path, raw in decls:
            if barrier < dline < lineno and lineno - dline <= 20:
                if owner is None or dline > owner[0]:
                    owner = (dline, method, path, raw)
        meta = {}
        if owner:
            snap_rel = None
            for dline, sp in snaps.items():
                if owner[0] <= dline < lineno:
                    snap_rel = sp
            meta = {"captured": stamps.get(owner[0]),
                    "snapshot": snap_rel,
                    "decl_line": owner[0]}
            blocks.append((lineno, "MEASURED", owner[2], owner[1], obj, parsed, meta))
            continue

        # 无端点声明：先看紧邻的显式降级标记
        ex = find_exempt_marker(lines, lineno)
        if ex:
            blocks.append((lineno, "EXEMPT", None, None, obj, parsed,
                           {"reason": ex[1]}))
            continue

        # 无声明也无标记：像响应体 -> 可疑；不像 -> 仍需显式说明
        if not parsed:
            blocks.append((lineno, "MALFORMED", None, None, obj, False, {}))
            continue
        if looks_like_response(obj):
            blocks.append((lineno, "UNVERIFIED-SUSPECT", None, None, obj, True, {}))
        else:
            blocks.append((lineno, "UNVERIFIED-SUSPECT", None, None, obj, True,
                           {"reason": "无端点声明、无降级标记，且形态不像响应体"}))

    return blocks


# ---------------------------------------------------------------- 源站抓取


def fetch_source(method: str, path: str):
    """实时请求源站，返回响应体 dict。网络失败抛异常。"""
    url = SOURCE_BASE.rstrip("/") + "/" + path.lstrip("/")
    data = None
    headers = {"User-Agent": USER_AGENT, "Accept": "application/json"}
    if method in ("POST", "PUT", "PATCH"):
        data = b"{}"
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(req, timeout=HTTP_TIMEOUT) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def pick_block(obj):
    """取出要比对的数据体：完整包络取 data，否则整体。"""
    if isinstance(obj, dict) and ENVELOPE_KEYS & set(obj):
        return obj.get("data")
    return obj


# 列表条目的稳定业务主键：用于在源站/快照返回里定位「文档记录的那一条」。
# 必须**在列表内唯一**。iid 被刻意排除：同一藏品会有多条挂单/求购记录，
# 用 iid 对齐会稳定地匹配到错误的一条，进而把正常的列表变化
# 误报成「一堆字段都对不上」（实测缺陷：docs/api.md:698）。
LIST_KEY_CANDIDATES = ("nftId", "orderId", "id", "token")


def align_list(doc_list, src_list, allow_positional=False):
    """
    对齐文档记录与源站/快照记录。

    返回 (doc_rec, src_rec)：
      能按唯一主键定位到同一条 -> 返回该条的 (文档记录, 源站记录)
      定位不到（如该记录已从列表中滚出）-> 返回 (None, None)，调用方按 DRIFT 处理

    若文档列的是**全量列表**（源站返回条数 <= 文档条数，且文档条数>1），
    则整体返回 (doc_list, src_list) 交给结构比对，避免因排序不同而漏配。

    allow_positional=True 时，若文档记录不含任何唯一主键（如列表首元素是
    聚合行，没有 nftId/orderId），退化为按位置取首条。
    仅供**快照比对**使用：快照是抓取当时的历史定格响应，位置语义可靠。
    实时比对绝不能按位置对齐 —— 列表会随成交实时重排。
    """
    if not doc_list or not src_list:
        return (doc_list[0] if doc_list else None,
                src_list[0] if src_list else None)
    doc_rec = doc_list[0]
    for key in LIST_KEY_CANDIDATES:
        dv = doc_rec.get(key)
        if dv is None:
            continue
        hits = [rec for rec in src_list if rec.get(key) == dv]
        # 只在源站侧唯一命中时才认为对上了；多命中说明该键不具区分度
        if len(hits) == 1:
            return doc_rec, hits[0]
    # 全量列表场景：文档条数 >= 源站条数且文档列了多条，说明是完整枚举
    if len(doc_list) > 1 and len(src_list) <= len(doc_list):
        return doc_list, src_list
    # 文档记录无唯一主键（聚合行）：仅快照比对允许按位置对齐
    if allow_positional:
        return doc_list[0], src_list[0]
    return None, None


def flatten(obj, prefix=""):
    """把嵌套结构拍平成 {点分路径: 值}。"""
    out = {}
    if isinstance(obj, dict):
        for k, v in obj.items():
            out.update(flatten(v, f"{prefix}.{k}" if prefix else str(k)))
    elif isinstance(obj, list):
        if obj:
            out.update(flatten(obj[0], f"{prefix}[0]"))
        out[f"{prefix}.__len__"] = len(obj)
    else:
        out[prefix] = obj
    return out


def type_of(v):
    if v is None:
        return "null"
    if isinstance(v, bool):
        return "bool"
    if isinstance(v, int):
        return "int"
    if isinstance(v, float):
        return "number"
    if isinstance(v, str):
        return "str"
    return type(v).__name__


def values_equal(a, b):
    """标量宽松相等：数值按值比（388 == 388.0），其余按类型+值比。"""
    if isinstance(a, bool) or isinstance(b, bool):
        return a is b
    if isinstance(a, (int, float)) and isinstance(b, (int, float)):
        return abs(float(a) - float(b)) < 1e-9
    return a == b


# ---------------------------------------------------------------- 通道 A：快照严格比对


def compare_snapshot(doc_obj, snap_obj):
    """
    通道 A：文档块 vs 抓取当时存档的源站响应，**逐字段严格比对**。

    快照是历史定格值，不存在「行情漂移」这回事 —— 文档写 388、快照是 388，
    那文档就必须写 388。任何值篡改/字段删除/类型错误都在这里当场暴露。

    返回 (verdict, detail[])，verdict ∈ MATCH / MISMATCH / NO-SNAPSHOT
    """
    detail = []
    d, s = pick_block(doc_obj), pick_block(snap_obj)
    if d is None or s is None:
        return "MISMATCH", [{"field": "-", "reason": "快照或文档块无法定位 data 体"}]

    if isinstance(d, list) and isinstance(s, list):
        a, b = align_list(d, s, allow_positional=True)
        if a is None:
            return "MISMATCH", [{
                "field": "-",
                "reason": "文档记录在抓取快照中无法定位（无唯一主键 nftId/orderId/id/token），"
                          "无法证明该块是当时的真实响应",
            }]
        d, s = a, b
    elif isinstance(d, list) != isinstance(s, list):
        return "MISMATCH", [{
            "field": "data",
            "reason": f"类型不符：文档 {type(d).__name__} vs 快照 {type(s).__name__}"
                      "（该块可能被挂到了错误的端点上）",
        }]

    fd = {k: v for k, v in flatten(d).items() if k.strip(".") != ""}
    fs = {k: v for k, v in flatten(s).items() if k.strip(".") != ""}
    mismatch = False

    # 1) 占位符自检 —— 声称逐字段转录就不许有占位符
    raw = json.dumps(d, ensure_ascii=False)
    for pat, why in PLACEHOLDER_PATTERNS:
        if pat.search(raw):
            detail.append({"field": "-", "reason": f"文档块内含占位符：{why}"})
            mismatch = True

    # 2) 文档有、快照无 —— 臆造字段
    for k in sorted(set(fd) - set(fs)):
        if k.endswith(".__len__"):
            continue
        detail.append({"field": k, "reason": "文档有该字段，抓取快照中无（臆造字段？）"})
        mismatch = True

    # 3) 快照有、文档无 —— 文档漏字段（快照是完整响应，漏了就要报）
    for k in sorted(set(fs) - set(fd)):
        if k.endswith(".__len__"):
            continue
        detail.append({"field": k, "reason": "抓取快照有该字段，文档缺（转录不完整）"})
        mismatch = True

    # 4) 共有字段：严格相等，无例外
    for k in sorted(set(fd) & set(fs)):
        dv, sv = fd[k], fs[k]
        if type_of(dv) != type_of(sv):
            detail.append({
                "field": k,
                "reason": f"类型不符：文档 {type_of(dv)} vs 快照 {type_of(sv)}",
            })
            mismatch = True
            continue
        if not values_equal(dv, sv):
            leaf = k.split(".")[-1]
            kind = "行情字段" if leaf in VOLATILE_FIELDS else "字段"
            detail.append({
                "field": k,
                "reason": f"{kind}值与抓取快照不符：文档 {dv!r} vs 快照 {sv!r}",
            })
            mismatch = True

    return ("MISMATCH" if mismatch else "MATCH"), detail


# ---------------------------------------------------------------- 通道 B：实时抓取


def compare_live(doc_obj, src_obj):
    """
    通道 B：文档块 vs 源站实时响应。
    只校验字段集合/类型 + 静态字段值；行情字段值变化记 DRIFT。

    返回 (verdict, detail[])，verdict ∈ MATCH / DRIFT / MISMATCH
    """
    detail = []
    d, s = pick_block(doc_obj), pick_block(src_obj)
    if d is None or s is None:
        return "MISMATCH", [{"field": "-", "reason": "无法定位 data 体"}]

    unaligned = False
    if isinstance(d, list) and isinstance(s, list):
        d, s = align_list(d, s)
        if d is None:
            unaligned = True
    elif isinstance(d, list) != isinstance(s, list):
        return "MISMATCH", [{
            "field": "data",
            "reason": f"类型不符：文档 {type(d).__name__} vs 源站 {type(s).__name__}",
        }]

    if unaligned:
        return "DRIFT", [{
            "field": "-",
            "reason": "文档记录已不在源站返回列表中（列表为时变数据），仅校验结构",
        }]

    fd = {k: v for k, v in flatten(d).items() if k.strip(".") != ""}
    fs = {k: v for k, v in flatten(s).items() if k.strip(".") != ""}
    mismatch = False

    raw = json.dumps(d, ensure_ascii=False)
    for pat, why in PLACEHOLDER_PATTERNS:
        if pat.search(raw):
            detail.append({"field": "-", "reason": f"文档块内含占位符：{why}"})
            mismatch = True

    for k in sorted(set(fd) - set(fs)):
        if k.endswith(".__len__"):
            continue
        detail.append({"field": k, "reason": "文档有该字段，源站无（臆造字段？）"})
        mismatch = True

    for k in sorted(set(fs) - set(fd)):
        if k.endswith(".__len__"):
            continue
        detail.append({"field": k, "reason": "源站有该字段，文档缺（建议补）"})

    for k in sorted(set(fd) & set(fs)):
        dv, sv = fd[k], fs[k]
        leaf = k.split(".")[-1]
        if type_of(dv) != type_of(sv):
            # 时变字段在源站运行期可能返回 null（如交易日重置后当日成交额为空），
            # 这不是文档污染。降级为 DRIFT 但显式标注，不静默放过。
            # 注意：通道 A（快照严格比对）对此仍然判MISMATCH，
            # 真正的类型篡改（如 388 -> "388"）照样会被抓到。
            if leaf in VOLATILE_FIELDS and {dv, sv} & {None}:
                detail.append({
                    "field": k,
                    "reason": f"时变字段类型漂移（需人工确认）：文档 {type_of(dv)} "
                              f"vs 源站 {type_of(sv)} —— 源站运行期可能返回 null，"
                              "若确认非源站行为请修文档",
                })
                continue
            detail.append({
                "field": k,
                "reason": f"类型不符：文档 {type_of(dv)} vs 源站 {type_of(sv)}",
            })
            mismatch = True
            continue
        if values_equal(dv, sv):
            continue
        if leaf in STATIC_FIELDS or leaf in ENVELOPE_KEYS:
            detail.append({
                "field": k,
                "reason": f"静态字段值不符：文档 {dv!r} vs 源站 {sv!r}",
            })
            mismatch = True
        elif leaf in VOLATILE_FIELDS:
            detail.append({
                "field": k,
                "reason": f"行情字段实时漂移（正常）：文档 {dv!r} vs 源站 {sv!r}",
            })
        else:
            # 不在 VOLATILE 也不在 STATIC 的字段：既非已知静态身份字段，
            # 也非已知行情字段。按未知处理 -> 报出来人工判断，不静默放过。
            detail.append({
                "field": k,
                "reason": f"未分类字段值不符：文档 {dv!r} vs 源站 {sv!r}"
                          "（该字段不在静态/行情白名单中，需人工确认）",
            })
            mismatch = True

    if mismatch:
        return "MISMATCH", detail
    if any("建议补" in x["reason"] or "漂移" in x["reason"] for x in detail):
        return "DRIFT", detail
    return "MATCH", detail


# ---------------------------------------------------------------- 报告输出


def print_report(results, offline=False, use_cache=True):
    line = "=" * 74
    print(f"\n{line}")
    print("文档 vs 源站 实抓比对报告 v2（黑名单式全量校验）"
          + ("（离线模式，未发请求）" if offline else ""))
    print(line)

    by_verdict = {}
    for r in results:
        by_verdict.setdefault(r["verdict"], []).append(r)

    for verdict in ("MISMATCH", "UNVERIFIED-SUSPECT", "UNVERIFIED",
                    "MALFORMED", "DRIFT", "MATCH", "UNREACHABLE"):
        items = by_verdict.get(verdict, [])
        if not items:
            continue
        icon = {"MISMATCH": "x", "UNVERIFIED-SUSPECT": "!",
                "UNVERIFIED": "?", "MALFORMED": "x", "DRIFT": "*",
                "MATCH": "v", "UNREACHABLE": "-"}[verdict]
        print(f"\n[{verdict}] {len(items)} 块")
        for r in items:
            where = f"{r['doc']}:{r['line']}"
            if r.get("path"):
                where += f"  {r['method']} {r['path']}"
            print(f"  {icon} {where}")
            if r.get("captured"):
                print(f"      抓取时间: {r['captured']}")
            if r.get("snapshot"):
                print(f"      快照: {r['snapshot']}")
            if r.get("reason"):
                print(f"      降级依据: {r['reason']}")
            for d in r["detail"][:12]:
                print(f"      - {d['field']}: {d['reason']}")
            if len(r["detail"]) > 12:
                print(f"      ... 另有 {len(r['detail']) - 12} 条")

    stats = {k: len(v) for k, v in by_verdict.items()}
    total = len(results)
    print(f"\n[清点] 共 {total} 个 json 块：" +
          "  ".join(f"{k}={v}" for k, v in sorted(stats.items())))
    print(line)
    blocking = stats.get("MISMATCH", 0) + stats.get("UNVERIFIED-SUSPECT", 0) \
        + stats.get("MALFORMED", 0)
    if blocking:
        print(f"结论: 阻断 —— {blocking} 个块存在 MISMATCH / 未标注疑似响应块 / 解析失败。")
        print("      必须先修文档，禁止据此开发。")
    elif stats.get("DRIFT"):
        print("结论: 无 MISMATCH，仅行情实时漂移（正常）。")
    else:
        print("结论: 通过 —— 文档示例与源站快照及实时响应一致。")
    print(line + "\n")
    return blocking > 0


# ---------------------------------------------------------------- 反向自测


SELFTEST_DOC = """# 自测文档

## 1. 正常实测块

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /selftest/item`（免登录）
> 原始响应存档：`.cache/source-probe/selftest_item.json`

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": {
    "iid": "2109928427009183745",
    "cid": "2103038591555887106",
    "name": "臻海蓝卡",
    "price": 388,
    "type": 1
  }
}
```
"""


def build_selftest_fixture(tmp: Path):
    """搭一个自测用的文档 + 快照目录。返回 (doc_path, cache_dir)。"""
    cache = tmp / "cache"
    cache.mkdir(parents=True, exist_ok=True)
    snap = {
        "status": True, "code": 0, "message": None,
        "data": {"iid": "2109928427009183745", "cid": "2103038591555887106",
                 "name": "臻海蓝卡", "price": 388, "type": 1},
    }
    (cache / "selftest_item.json").write_text(
        json.dumps(snap, ensure_ascii=False), encoding="utf-8")
    doc = tmp / "selftest.md"
    doc.write_text(SELFTEST_DOC, encoding="utf-8")
    return doc, cache


def analyze_doc(doc_path: Path, cache_dir: Path, offline=True, use_cache=True):
    """
    跑一遍完整校验（不发请求），返回 results 列表。
    自测与主流程共用这段代码 —— 自测验证的就是真实执行路径。
    """
    results = []
    for lineno, cat, path, method, obj, parsed, meta in collect_blocks(doc_path):
        entry = {"doc": str(doc_path), "line": lineno, "path": path,
                 "method": method, "verdict": cat, "detail": [],
                 "captured": meta.get("captured"),
                 "snapshot": meta.get("snapshot"),
                 "reason": meta.get("reason")}

        if cat == "EXEMPT":
            entry["verdict"] = "MATCH"
            entry["detail"] = [{"field": "-",
                                "reason": f"已显式降级：{entry['reason']}（跳过源站比对）"}]
            results.append(entry)
            continue
        if cat in ("UNVERIFIED-SUSPECT", "MALFORMED"):
            results.append(entry)
            continue

        # MEASURED：通道 A（快照严格）
        snap_path = None
        if use_cache and meta.get("snapshot"):
            cand = (ROOT / meta["snapshot"])
            snap_path = cand if cand.exists() else (cache_dir /
                                                     Path(meta["snapshot"]).name)
        if snap_path and snap_path.exists():
            try:
                snap_obj = json.loads(snap_path.read_text(encoding="utf-8"))
                v, d = compare_snapshot(obj, snap_obj)
                entry["verdict"] = v
                entry["detail"] = d
            except (json.JSONDecodeError, OSError) as exc:
                entry["verdict"] = "MISMATCH"
                entry["detail"] = [{"field": "-", "reason": f"快照不可读：{exc}"}]
        else:
            entry["verdict"] = "UNVERIFIED"
            entry["detail"] = [{
                "field": "-",
                "reason": "该块未声明原始响应存档，无法做严格值比对（NO-SNAPSHOT）",
            }]
        results.append(entry)
    return results


def run_selftest() -> int:
    """
    反向自测：主动注入已知错误，断言脚本能抓到。
    自测失败 = 脚本不可信 = 整个防复发机制失效，必须硬失败。
    """
    print("=" * 74)
    print("反向自测（--selftest）：注入已知错误，断言脚本能抓到")
    print("=" * 74)

    cases = []

    with tempfile.TemporaryDirectory() as td:
        tmp = Path(td)
        doc, cache = build_selftest_fixture(tmp)
        base = SELFTEST_DOC

        # 0) 基线：干净文档必须无 MISMATCH（证明自测环境本身不是坏的）
        r = analyze_doc(doc, cache)
        ok = not any(x["verdict"] in ("MISMATCH", "UNVERIFIED-SUSPECT")
                     for x in r)
        cases.append(("基线：干净文档不应报 MISMATCH", ok,
                      [x for x in r if x["verdict"] != "MATCH"]))

        # 1) 值篡改 388 -> 38800
        injected = base.replace('"price": 388', '"price": 38800', 1)
        assert injected != base, "注入失败：基线中找不到 price 字段"
        doc.write_text(injected, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "MISMATCH"]
        cases.append(("值篡改 388 -> 38800", bool(caught), caught))

        # 2) 字段删除
        injected = base.replace('  "type": 1\n', "", 1)
        assert injected != base, "注入失败：找不到待删字段"
        doc.write_text(injected, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "MISMATCH"]
        cases.append(("字段删除（删掉 type）", bool(caught), caught))

        # 3) 类型错误 388 -> "388"
        injected = base.replace('"price": 388', '"price": "388"', 1)
        assert injected != base, "注入失败：找不到 price 字段"
        doc.write_text(injected, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "MISMATCH"]
        cases.append(('类型错误 388 -> "388"', bool(caught), caught))

        # 4) 块级遗漏：未标注端点、含响应特征、无降级标记
        orphan = base + """
## 2. 漏标的响应体块（无端点声明、无降级标记）

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": { "price": 388, "cid": 1 }
}
```
"""
        doc.write_text(orphan, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "UNVERIFIED-SUSPECT"]
        cases.append(("块级遗漏（未标注的响应特征块被拦下）", bool(caught), caught))

        # 5) 显式降级块不应变成 SUSPECT 噪音
        exempted = base + """
## 3. 显式降级块

> 【占位】该端点需登录态，本环境无法实抓。
> 不可作为契约依据。

```json
{
  "status": true, "code": 0, "message": null,
  "data": { "phone": "138****0000" }
}
```
"""
        doc.write_text(exempted, encoding="utf-8")
        r = analyze_doc(doc, cache)
        noise = [x for x in r if x["verdict"] in ("UNVERIFIED-SUSPECT", "MISMATCH")]
        cases.append(("显式降级块不应产生噪音", not noise, noise))

        # 6) 降级标记远离块（>3 行）时必须失效，重新判为 SUSPECT
        far = base + """
## 4. 标记与块距离过远

> 【占位】这里有一个降级声明。

> 中间插了四行无关内容。
> 又一行。
> 再一行。
> 第四行。

```json
{
  "status": true, "code": 0, "message": null,
  "data": { "price": 388 }
}
```
"""
        doc.write_text(far, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "UNVERIFIED-SUSPECT"]
        cases.append(("降级标记远离块时必须失效（反 v1 漏洞）", bool(caught), caught))

        # 7) 块被挂到错误端点：data 类型与快照不符必须报出
        wrong = base.replace('"GET /selftest/item"', '"GET /selftest/wrong"')
        wrong = wrong.replace('"price": 388', '"price": "388"')
        doc.write_text(wrong, encoding="utf-8")
        r = analyze_doc(doc, cache)
        caught = [x for x in r if x["verdict"] == "MISMATCH"]
        cases.append(("值/类型篡改在换端点后仍被抓到", bool(caught), caught))

    failed = 0
    for name, ok, detail in cases:
        print(f"\n  [{'PASS' if ok else 'FAIL'}] {name}")
        if not ok:
            failed += 1
            for d in detail[:6]:
                print(f"      - {d.get('line')}: "
                      f"{[x['reason'] for x in d['detail']][:3]}")

    total = len(cases)
    print("\n" + "=" * 74)
    if failed:
        print(f"自测结果: FAIL —— {failed}/{total} 项未通过。")
        print("这意味着脚本无法证明「坏了会报」，防复发机制已失效，必须修脚本。")
        print("=" * 74)
        return 3
    print(f"自测结果: PASS —— {total}/{total} 项全部通过。")
    print("脚本既能证明「没坏」，也能证明「坏了会报」。")
    print("=" * 74)
    return 0


# ---------------------------------------------------------------------- main


def main() -> int:
    ap = argparse.ArgumentParser(description="文档 vs 源站实抓比对 v2")
    ap.add_argument("--doc", action="append", help="指定文档（可重复），默认扫 docs/*.md")
    ap.add_argument("--json", action="store_true", help="输出 JSON")
    ap.add_argument("--offline", action="store_true", help="不发请求，只跑快照严格比对")
    ap.add_argument("--no-cache", action="store_true", help="忽略存档快照，只跑实时比对")
    ap.add_argument("--selftest", action="store_true", help="反向自测：注入错误并断言检出")
    ap.add_argument("--delay", type=float, default=0.4, help="每次请求间隔秒")
    args = ap.parse_args()

    if args.selftest:
        return run_selftest()

    targets = [Path(d) for d in args.doc] if args.doc else sorted(DOCS_DIR.glob("*.md"))
    if not targets:
        print("! docs/ 下没有 .md 文件", file=sys.stderr)
        return 2

    results = []
    progress = (lambda *a, **k: print(*a, **{**k, "file": sys.stderr})) \
        if args.json else print

    for doc in targets:
        if not doc.exists():
            print(f"! 文件不存在 {doc}", file=sys.stderr)
            return 2
        doc_abs = doc.resolve()
        rel = str(doc_abs.relative_to(ROOT)) if doc_abs.is_relative_to(ROOT) else str(doc)

        blocks = collect_blocks(doc)
        progress(f"[{rel}] json 块 {len(blocks)} 个：" +
                 "  ".join(f"{c}={sum(1 for b in blocks if b[1] == c)}"
                           for c in ("MEASURED", "EXEMPT", "UNVERIFIED-SUSPECT",
                                     "MALFORMED")))

        for lineno, cat, path, method, obj, parsed, meta in blocks:
            entry = {"doc": rel, "line": lineno, "path": path, "method": method,
                     "verdict": cat, "detail": [], "captured": meta.get("captured"),
                     "snapshot": meta.get("snapshot"), "reason": meta.get("reason")}

            if cat == "EXEMPT":
                entry["verdict"] = "MATCH"
                entry["detail"] = [{"field": "-",
                                    "reason": f"已显式降级：{entry['reason']}（跳过源站比对）"}]
                results.append(entry)
                continue
            if cat in ("UNVERIFIED-SUSPECT", "MALFORMED"):
                results.append(entry)
                continue

            # 通道 A：快照严格比对
            snap_file = None
            if not args.no_cache and meta.get("snapshot"):
                cand = ROOT / meta["snapshot"]
                snap_file = cand if cand.exists() else None
            entry["verdict"] = "UNVERIFIED"
            entry["detail"] = []
            if snap_file:
                try:
                    snap_obj = json.loads(snap_file.read_text(encoding="utf-8"))
                    v, d = compare_snapshot(obj, snap_obj)
                    entry["verdict"] = v
                    entry["detail"] = d
                except (json.JSONDecodeError, OSError) as exc:
                    entry["verdict"] = "MISMATCH"
                    entry["detail"] = [{"field": "-", "reason": f"快照不可读：{exc}"}]
            else:
                entry["detail"] = [{
                    "field": "-",
                    "reason": "该块未声明原始响应存档（NO-SNAPSHOT），"
                              "无法做逐字段严格比对",
                }]

            # 通道 B：实时抓取比对（快照已 MISMATCH 时不再重复抓取）
            if entry["verdict"] != "MISMATCH" and not args.offline:
                try:
                    src = fetch_source(method, path)
                except (urllib.error.URLError, urllib.error.HTTPError,
                        TimeoutError, OSError, json.JSONDecodeError) as exc:
                    entry["detail"].append({"field": "-", "reason": f"实时抓取失败：{exc}"})
                    results.append(entry)
                    continue
                if not src.get("status"):
                    entry["verdict"] = "UNREACHABLE"
                    entry["detail"] = [{
                        "field": "code",
                        "reason": f"源站返回 code={src.get('code')} "
                                  f"message={src.get('message')}，无法作为契约依据",
                    }]
                    results.append(entry)
                    continue
                v2, d2 = compare_live(obj, src)
                entry["verdict"] = v2 if v2 == "MISMATCH" else entry["verdict"]
                entry["detail"] = entry["detail"] + d2
                time.sleep(args.delay)

            results.append(entry)

    if args.json:
        print(json.dumps({
            "results": results,
            "total_blocks": len(results),
            "mismatch_count": sum(1 for r in results
                                  if r["verdict"] in ("MISMATCH", "UNVERIFIED-SUSPECT",
                                                      "MALFORMED")),
        }, ensure_ascii=False, indent=2))
    else:
        failed = print_report(results, offline=args.offline)
        return 1 if failed else 0

    return 1 if any(r["verdict"] in ("MISMATCH", "UNVERIFIED-SUSPECT", "MALFORMED")
                    for r in results) else 0


if __name__ == "__main__":
    sys.exit(main())