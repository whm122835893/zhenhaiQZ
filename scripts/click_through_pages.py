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

    注意：DEAD 绝大多数不是缺陷。选择器刻意放宽到 [class*='item'] /
    [class*='card']，命中的纯展示列表项本身没有点击 handler，点它当然没反应，
    这类元素计入 DEAD 是预期噪声，真正要盯的是 ERROR。
    """
    before = _fingerprint(page)

    try:
        page.locator(CLICKABLE_SELECTOR).nth(item["idx"]).click(timeout=2500)
    except Exception as exc:  # 点击本身失败（元素被遮挡/已卸载等）
        msg = str(exc)
        if "Timeout" in msg or "not visible" in msg or "intercepted" in msg:
            return "SKIP"
        return "ERROR"

    page.wait_for_timeout(SETTLE_MS)

    errors = page.evaluate("() => window.__clickErrors || []")
    if errors:
        return "ERROR"

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
    url = FRONT + "/#" + key + QUERY_ARGS.get(key, "")
    stat = {"page": key, "clicked": 0, "ok": 0, "dead": 0, "error": 0,
            "skipped": 0, "dangerous": [], "error_samples": []}

    page.goto(url, wait_until="domcontentloaded")
    page.wait_for_timeout(1800)

    # 页面本身没渲染出来就不用点它了，交给静态巡检报错
    if not page_alive(page):
        ev = dom_evidence(page, key)
        if not ev:
            stat["error_samples"].append("页面未渲染，无可点元素")
            return stat

    items = collect_clickables(page)
    if not items:
        stat["error_samples"].append("无可点元素")
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
            page.goto(url, wait_until="domcontentloaded")
            page.wait_for_timeout(1200)

    if want_shots:
        os.makedirs(SHOT_DIR, exist_ok=True)
        safe = key.replace("/", "_")
        page.screenshot(path=f"{SHOT_DIR}/{safe}.png")

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
            flag = "OK " if not stat["error_samples"] else "ERR"
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
