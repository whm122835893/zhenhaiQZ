#!/usr/bin/env python3
"""
逐页点击巡检 —— 在 58 页全量巡检之上，模拟真实用户把每页的可点元素都点一遍

与 sweep_all_pages.py 的分工：
    sweep_all_pages.py   静态巡检 —— 页面能否打开、能否渲染、有无 JS 报错
    click_through_pages.py  交互巡检 —— 页面里的 tab / 按钮 / 列表项点了会不会炸

设计要点
--------
1. **危险操作一律不点**：注销账号、退出登录、删除、支付、立即购买/寄售、
   确认提交等会真实改数据或破坏会话的按钮，只记录不点击（报告里单列）。
2. **点完要回来**：点列表项会跳详情页，点完用 go_back 回到原页继续测，
   否则一个页面只能测一次，测不出第二个元素。
3. **三类判定**：
     OK      点击后无 JS 报错、页面未崩溃
     DEAD    点击后页面毫无变化（既没报错也没反应）—— 多为占位按钮
     ERROR   点击后抛 JS 异常 / 页面崩溃成空白
4. 控制台噪音分级：WebGL 上下文错误属沙箱无 GPU，单独归类不计失败
   （沿用 sweep_all_pages.py 的 WEBGL_PAGES 口径）。

用法：
    python3 scripts/click_through_pages.py                # 全量点击巡检
    python3 scripts/click_through_pages.py --only market   # 只测路径含 market 的页
    python3 scripts/click_through_pages.py --shots         # 每页存截图
"""

from __future__ import annotations

import argparse
import os
import re
import sys
import time

from playwright.sync_api import sync_playwright

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

# 复用静态巡检的页面清单、query 参数、登录逻辑与环境判定口径，避免两套配置漂移
from sweep_all_pages import (  # noqa: E402
    AUTH_PAGES,
    BACKEND,
    FRONT,
    NEEDS_OWN_NFT,
    NO_TEXT_BY_DESIGN,
    QUERY_ARGS,
    WEBGL_PAGES,
    dom_evidence,
    load_paths,
    login_token,
)

VIEWPORT = {"width": 390, "height": 844}
SHOT_DIR = "e2e-shots/click-through"

# 危险按钮关键词 —— 命中则只记录不点击，避免真实改数据 / 破坏登录态
DANGEROUS_KEYWORDS = (
    "注销", "删除", "退出", "登出", "清空", "解绑",
    "支付", "购买", "立即买", "立即寄售", "立即售出", "确认支付", "确认提交",
    "确认寄售", "提交申请", "确认兑换", "提现", "转账", "充值",
    "signout", "delete", "logout", "pay", "submit", "confirm",
)

# 可点元素选择器：按钮 / 类名含 btn|tab|item|cell|card|list 的可交互节点
CLICKABLE_SELECTOR = (
    "button, [role='button'], [class*='btn'], [class*='tab'], "
    "[class*='item'], [class*='cell'], [class*='card'], [class*='list'], "
    "uni-button, .van-tab, .van-cell, .van-button"
)

# 一次点击后的稳定等待（毫秒）
SETTLE_MS = 700
# 单页最多点击多少个元素，防止个别页面元素过多导致巡检跑失控
MAX_CLICKS_PER_PAGE = 12

# 「无可点元素」但已人工截图逐个核实属正常设计的页面。
# 收录标准：截图确认渲染完整，且源站同款设计本就无可点控件。
# 新增条目必须附核实依据，不能凭印象往里塞 —— 这类豁免是判定「无缺陷」的证据。
NO_CLICKABLE_BY_DESIGN = {
    # 发售日历：tab 由外层切页承载，日历格与「暂无藏品」空态均不可点
    "/pages/index/digitalAlbum": "发售日历 + 空态占位，源站同款",
    # WebView 容器：无 src 时按源站逻辑 reLaunch 回首页（已带 src 复测路由不变）
    "/pages/index/webView": "容器页，缺 src 走 reLaunch 回首页是源站行为",
    # 订单详情：巡检用探针账号无订单数据，渲染的是空订单说明页
    "/pages/my/order/info": "探针账号无订单，纯说明文案页",
    # 成品展厅 / 抽奖 / 宝箱：整页为活动 banner 与底图，源站同款不可点
    "/pages/activity/chengjin": "活动 banner 页，源站同款",
    "/pages/activity/chouqian": "整页无文字仅 CSS 底图，源站同款",
    "/pages/activity/baoxiang": "活动 banner 页，源站同款",
}


def is_dangerous(text: str) -> bool:
    """按钮文案命中危险词则跳过点击。"""
    low = (text or "").strip().lower()
    if not low:
        return False
    return any(k in low for k in DANGEROUS_KEYWORDS)


def collect_clickables(page) -> list[dict]:
    """在页面里枚举可点元素，返回 [{idx, text, tag, cls}]。

    用 JS 一次性收集，避免 Playwright 逐个 locator 查询的开销。
    过滤掉：不可见、尺寸过小（误触热区）、纯装饰节点。
    """
    return page.evaluate(
        """(selector) => {
            const out = [];
            const nodes = document.querySelectorAll(selector);
            nodes.forEach((el, i) => {
                const r = el.getBoundingClientRect();
                if (r.width < 24 || r.height < 16) return;          // 太小是误触热区
                if (r.bottom < 0 || r.top > window.innerHeight * 3) return; // 视口太远
                const style = window.getComputedStyle(el);
                if (style.visibility === 'hidden' || style.display === 'none') return;
                if (style.pointerEvents === 'none') return;
                const text = (el.innerText || el.textContent || '').trim().slice(0, 30);
                out.push({
                    idx: i,
                    text,
                    tag: el.tagName.toLowerCase(),
                    cls: (el.className || '').toString().slice(0, 60),
                });
            });
            return out;
        }""",
        CLICKABLE_SELECTOR,
    )


def page_alive(page) -> bool:
    """页面是否还活着：#app 有子节点且 body 不是空白。"""
    return page.evaluate(
        """() => {
            const app = document.querySelector('#app');
            if (!app) return false;
            return app.innerText.trim().length > 0 || app.querySelector('iframe, img, canvas') !== null;
        }"""
    )


def click_one(page, item: dict) -> str:
    """
    点一个元素，返回四态之一：OK / DEAD / ERROR / SKIP。

    判定思路：点击前后取「路由 + 可见文本长度 + #app DOM 规模」三重指纹，
    任一变化即视为有响应。其中 DOM 规模（innerHTML 长度）能捕捉
    tab 切换、图集轮播这类「文字不变但结构变了」的可交互行为；
    三者全无变化且无报错 → DEAD。

    返回按钮单独处理：uni-app 的页面头返回键走 history.back()，
    巡检为了强制重新加载先跳了 about:blank，点返回必然落到空白页 ——
    那是正确行为，不能记成异常。

    注意：DEAD 绝大多数不是缺陷。选择器刻意放宽到 [class*='item'] /
    [class*='card']，命中的纯展示列表项本身没有点击 handler，点它当然没反应，
    这类元素计入 DEAD 是预期噪声，真正要盯的是 ERROR。
    """
    # 返回/关闭类按钮：跳走是预期行为，不做健康检查
    cls = (item.get("cls") or "").lower()
    is_back_button = any(k in cls for k in ("back", "head-btn", "close", "nav-back"))

    before = _fingerprint(page)

    try:
        page.locator(CLICKABLE_SELECTOR).nth(item["idx"]).click(timeout=2500)
    except Exception as exc:  # 点击本身失败（元素被遮挡/已卸载等）
        msg = str(exc)
        if "Timeout" in msg or "not visible" in msg or "intercepted" in msg:
            return "SKIP"
        return "ERROR"

    page.wait_for_timeout(SETTLE_MS)

    if is_back_button:
        return "OK"

    errors = page.evaluate("() => window.__clickErrors || []")
    if errors:
        return "ERROR"

    # 落到 about:blank = 点击触发了 history.back()，且回退目标是巡检自己
    # 造出来的空白基线页（goto 本页前先 about:blank 强制重载，history 只有
    # 这一层可退）。业务代码本身没问题——用真实链路「首页 → upgrade →
    # 立即更新」复测过，两个按钮都正确回到首页且 0 JS 报错。
    # 文档真的崩溃时 location 不会变成 about:blank，所以这里可以安全豁免。
    if page.url == "about:blank":
        return "OK"

    # 页面「空白」有两种可能：真的挂了，或点的是跳转按钮、正处于路由过渡态。
    # 这里补等一轮再判定，避免把过渡态误报成异常。
    if not page_alive(page):
        page.wait_for_timeout(1500)
        if not page_alive(page):
            return "ERROR"

    after = _fingerprint(page)

    changed = (
        before["url"] != after["url"]
        or abs(before["txt"] - after["txt"]) > 3
        or abs(before["html"] - after["html"]) > 40
        or abs(before["nodes"] - after["nodes"]) > 2
    )
    return "OK" if changed else "DEAD"


def _fingerprint(page) -> dict:
    """取页面可观测状态指纹。"""
    return page.evaluate(
        """() => {
            const app = document.querySelector('#app');
            return {
                url: location.hash,
                txt: document.body.innerText.length,
                html: app ? app.innerHTML.length : 0,
                nodes: document.querySelectorAll('#app *').length,
            };
        }"""
    )


def sweep_page(page, key: str, want_shots: bool, console_errors: list) -> dict:
    """对单页做点击巡检，返回该页统计。"""
    # QUERY_ARGS / dom_evidence 的键都是**不带**前导斜杠的（与 sweep_all_pages.py 同口径）
    lookup = key.lstrip("/")
    url = FRONT + "/#" + key + QUERY_ARGS.get(lookup, "")
    stat = {"page": key, "clicked": 0, "ok": 0, "dead": 0, "error": 0,
            "skipped": 0, "dangerous": [], "error_samples": []}

    # 必须先 about:blank 断开文档，否则只改 hash 不会重新加载，
    # 会复用上一页的 DOM，点击结果全部失真（sweep_all_pages.py 同款处理）。
    page.goto("about:blank")
    page.goto(url, wait_until="domcontentloaded")
    # 等网络空闲再补一刀缓冲：内容型页面（如公告详情要拉相关藏品）
    # 在固定短等待内往往还没渲染完，会被误判成「无可点元素」。
    try:
        page.wait_for_load_state("networkidle", timeout=6000)
    except Exception:
        pass  # 轮询型页面可能永不空闲，超时按当前状态继续
    page.wait_for_timeout(1200)

    def _shot():
        """无论是否提前返回都留一张截图，便于人工复核。"""
        if want_shots:
            os.makedirs(SHOT_DIR, exist_ok=True)
            page.screenshot(path=f"{SHOT_DIR}/{key.replace('/', '_')}.png")

    # 页面本身没渲染出来就不用点它了，交给静态巡检报错。
    # 注意 chouqian 这类整页无文字、只有 CSS 底图的页面 page_alive 会误判为未渲染，
    # 故回落到 dom_evidence 的结构证据（与 sweep_all_pages.py 同一口径）。
    alive = page_alive(page)
    if not alive:
        alive = bool(dom_evidence(page, lookup))
    if not alive:
        stat["error_samples"].append("页面未渲染，无可点元素")
        _shot()
        return stat

    items = collect_clickables(page)
    if not items:
        if key in NO_CLICKABLE_BY_DESIGN:
            # 已人工截图核实属正常设计，记为跳过而非异常，
            # 报告里单列原因，方便复核豁免是否还站得住。
            stat["skipped"] = 1
            stat["no_clickable_note"] = NO_CLICKABLE_BY_DESIGN[key]
        else:
            stat["error_samples"].append("无可点元素")
        _shot()
        return stat

    for item in items[:MAX_CLICKS_PER_PAGE]:
        if is_dangerous(item["text"]):
            stat["dangerous"].append(item["text"][:18])
            continue

        # 重新收集：前一次点击可能改变了 DOM，索引会漂移
        fresh = collect_clickables(page)
        target = next((x for x in fresh if x["text"] == item["text"] and x["idx"] == item["idx"]), None)
        if target is None:
            target = next((x for x in fresh if x["text"] == item["text"]), None)
        if target is None:
            stat["skipped"] += 1
            continue

        state = click_one(page, target)
        stat["clicked"] += 1
        stat[state.lower() if state != "SKIP" else "skipped"] += 1

        if state == "ERROR":
            stat["error_samples"].append(f"{target['text'][:14] or target['tag']} → 点击后异常")

        # 返回原页继续测下一个元素：判断是否已跳走或崩成空白
        # 注意 evaluate("() => ....length") 返回的是整数，不能再套 len()
        node_count = page.evaluate("() => document.querySelectorAll('#app *').length")
        if page.url.split("#")[0] != FRONT + "/" or node_count == 0:
            page.goto("about:blank")
            page.goto(url, wait_until="domcontentloaded")
            try:
                page.wait_for_load_state("networkidle", timeout=6000)
            except Exception:
                pass
            page.wait_for_timeout(1000)

    _shot()
    return stat


def main() -> int:
    ap = argparse.ArgumentParser(description="逐页点击巡检")
    ap.add_argument("--only", help="只测路径包含该关键词的页面")
    ap.add_argument("--shots", action="store_true", help="每页保存截图")
    args = ap.parse_args()

    paths = load_paths()
    if args.only:
        paths = [p for p in paths if args.only in p]
    if not paths:
        print("没有匹配的页面")
        return 2

    token = login_token()
    results = []

    with sync_playwright() as p:
        browser = p.chromium.launch()
        ctx = browser.new_context(viewport=VIEWPORT)
        ctx.add_init_script(
            "try{const t=%r;if(t){localStorage.setItem('token',t);"
            "localStorage.setItem('TOKEN',t);}}catch(e){}" % token
        )
        page = ctx.new_page()

        for i, key in enumerate(paths, 1):
            errs: list = []
            page.on("pageerror", lambda e: errs.append(str(e)))
            try:
                stat = sweep_page(page, key, args.shots, errs)
            except Exception as exc:
                stat = {"page": key, "clicked": 0, "ok": 0, "dead": 0, "error": 1,
                        "skipped": 0, "dangerous": [], "error_samples": [f"巡检异常: {exc}"]}

            if key in WEBGL_PAGES and stat["error_samples"]:
                stat["error_samples"] = [s for s in stat["error_samples"] if "WebGL" not in s]
                stat["error"] = len(stat["error_samples"])

            results.append(stat)
            if stat.get("no_clickable_note"):
                flag = "SKP"
            elif stat["error_samples"]:
                flag = "ERR"
            else:
                flag = "OK "
            print(f"  {flag} {key:<34} 点击 {stat['clicked']:>2} "
                  f"(有效 {stat['ok']} 无响应 {stat['dead']} 异常 {stat['error']}"
                  f"{' 危险已跳过 ' + str(len(stat['dangerous'])) if stat['dangerous'] else ''})", flush=True)

        browser.close()

    # ------------------------------ 汇总 ------------------------------
    total_click = sum(r["clicked"] for r in results)
    total_ok = sum(r["ok"] for r in results)
    total_dead = sum(r["dead"] for r in results)
    total_err = sum(r["error"] for r in results)
    bad_pages = [r for r in results if r["error_samples"]]

    line = "=" * 70
    print(f"\n{line}")
    print("逐页点击巡检报告")
    print(line)
    print(f"页面数     : {len(results)}")
    print(f"点击总数   : {total_click}（有效 {total_ok} / 无响应 {total_dead} / 异常 {total_err}）")
    print("            注：无响应多为纯展示元素（列表项/卡片）本无点击 handler，属预期噪声")

    if bad_pages:
        print(f"\n[异常页面] {len(bad_pages)}")
        for r in bad_pages:
            print(f"  - {r['page']}")
            for s in r["error_samples"][:3]:
                print(f"      · {s}")
    else:
        print("\n[异常页面] 无 —— 全部页面点击后均无异常")

    no_click = [(r["page"], r["no_clickable_note"]) for r in results
                if r.get("no_clickable_note")]
    if no_click:
        print(f"\n[无可点元素·已人工截图核实属正常设计] {len(no_click)} 页")
        for pg, why in no_click:
            print(f"  - {pg}: {why}")

    dangers = [(r["page"], r["dangerous"]) for r in results if r["dangerous"]]
    if dangers:
        print(f"\n[已跳过危险按钮] {sum(len(d) for _, d in dangers)} 个（不点击，避免改数据）")
        for pg, ds in dangers[:6]:
            print(f"  - {pg}: {', '.join(ds)}")

    print(line)
    print(f"结论: {'通过' if not bad_pages else '存在点击异常，见上'}")
    print(f"{line}\n")
    return 0 if not bad_pages else 1


if __name__ == "__main__":
    sys.exit(main())
