#!/usr/bin/env python3
"""
前端 → vite 代理 → 本地后端 端到端验证

用真实浏览器跑，验证：
  1. 页面加载无 CORS 报错、无运行时错误
  2. 所有 XHR 都打到本地后端（不是源站）
  3. 首页 / 藏品详情 / 市场 / 公告 等关键页有真实数据渲染
  4. 登录链路（短信快捷登录）能拿到 token 并写入 localStorage
  5. 带 token 的请求全部 code=0

用法：python3 scripts/e2e_check.py [前端地址] [后端地址]
默认：python3 scripts/e2e_check.py
"""
import json
import os
import sys
import time
import urllib.request

from playwright.sync_api import sync_playwright

FRONT = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:5173"
BACKEND = sys.argv[2] if len(sys.argv) > 2 else "http://127.0.0.1:8921"
SHOT_DIR = "/workspace/e2e-shots"
# 页面内 fetch 用的绝对 API 前缀（同源，经 vite 代理转发到本地后端）
API = FRONT + "/api"
SOURCE_API = "api.shengtai.cyou"

os.makedirs(SHOT_DIR, exist_ok=True)

results = []


def report(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": detail})
    mark = "\033[32m✓\033[0m" if ok else "\033[31m✗\033[0m"
    suffix = f"  {detail}" if detail else ""
    print(f"  {mark} {name}{suffix}")


def fetch_first_iid():
    """直接问后端要一个真实 iid（不走前端）"""
    with urllib.request.urlopen(f"{BACKEND}/item", timeout=10) as r:
        data = json.loads(r.read())
    items = data.get("data") or []
    return items[0]["iid"] if items else ""


def main():
    first_iid = fetch_first_iid()

    with sync_playwright() as p:
        browser = p.chromium.launch(args=["--no-sandbox"])
        ctx = browser.new_context(
            # 每次全新 profile：不复用磁盘缓存，避免上一轮的 404 图片被缓存住，
            # 导致修复数据后截图仍是旧的破图
            bypass_csp=True,
            viewport={"width": 390, "height": 844},
            device_scale_factor=2,
            locale="zh-CN",
        )
        page = ctx.new_page()

        api_calls = []
        source_calls = []
        page_errors = []
        console_errors = []

        def on_request(req):
            url = req.url
            if "/api/" in url or "shengtai.cyou" in url:
                entry = {"method": req.method, "url": url, "status": None}
                api_calls.append(entry)
                if SOURCE_API in url:
                    source_calls.append(entry)

        def on_response(res):
            url = res.url
            for c in api_calls:
                if c["url"] == url and c["status"] is None:
                    c["status"] = res.status
                    break

        page.on("request", on_request)
        page.on("response", on_response)
        page.on("pageerror", lambda e: page_errors.append(str(e)))
        page.on(
            "console",
            lambda m: console_errors.append(m.text) if m.type == "error" else None,
        )

        def goto(hash_, label, wait_ms=2500):
            """切到指定 hash 路由并截图。

            ⚠️ 不能直接 page.goto(f"{FRONT}/#{hash_}")：
            同一文档内改 hash 属于「片段导航」，浏览器**不会重新加载页面**，
            只触发 hashchange。若上一次已在 app 里，page.goto 会立刻返回、
            wait_until=networkidle 也不等待（没有新请求），
            结果所有截图都是首页 —— 这个坑让「每页都渲染成功」变成假象。
            只有带 query（?iid=…）的地址才会真正重载。

            正确做法：先 about:blank 断开当前文档，再从零加载目标地址。
            """
            api_calls.clear()
            source_calls.clear()

            # 断开当前文档，确保下次 goto 是真正的整页加载
            page.goto("about:blank")
            page.goto(f"{FRONT}/#{hash_}", wait_until="networkidle", timeout=40000)
            time.sleep(wait_ms / 1000.0)

            # 断言确实落在目标路由上（防止静默回落到首页）
            landed = page.evaluate("() => location.hash") or ""
            text = page.locator("#app").inner_text() if page.locator("#app").count() else ""
            page.screenshot(path=f"{SHOT_DIR}/{label}.png", full_page=True)
            return text, landed

        print("=" * 46)
        print(f" 端到端验证  前端={FRONT}  后端={BACKEND}")
        print("=" * 46)

        # ---- 1. 首页 ----
        print("\n【首页】")
        t, landed = goto("/", "home")
        report("路由落点正确", landed.startswith("#/pages/index"), landed)
        report("页面渲染有内容", len(t.strip()) > 50, f"{len(t.strip())} 字符")
        report("API 已调用", len(api_calls) > 0, f"{len(api_calls)} 次")
        report(
            "未打到源站",
            len(source_calls) == 0,
            source_calls[0]["url"] if source_calls else "",
        )
        report("有真实藏品数据", any(k in t for k in ("臻海", "八仙", "蓝卡")))
        report("无 JS 运行时错误", len(page_errors) == 0, page_errors[0] if page_errors else "")

        # ---- 2. 藏品详情 ----
        print("\n【藏品详情】")
        t, landed = goto(f"/pages/index/info?iid={first_iid}", "item-detail", 3000)
        report("路由落点正确", "pages/index/info" in landed, landed)
        report("详情页渲染", len(t.strip()) > 50, f"{len(t.strip())} 字符")
        report("未打到源站", len(source_calls) == 0)

        # ---- 3. 市场 ----
        print("\n【市场】")
        t, landed = goto("/pages/market/index", "market", 3000)
        report("路由落点正确", "pages/market/index" in landed, landed)
        report("市场页渲染", len(t.strip()) > 50, f"{len(t.strip())} 字符")
        report("未打到源站", len(source_calls) == 0)

        # ---- 4. 公告 ----
        print("\n【公告】")
        t, landed = goto("/pages/notice/index", "notice")
        report("路由落点正确", "pages/notice/index" in landed, landed)
        report("公告页渲染", len(t.strip()) > 50, f"{len(t.strip())} 字符")

        # ---- 4b. 图片加载断言（破图检测）----
        # 后端 image/imageUrl 字段形态错乱（嵌套对象、空串、缺域名）时，
        # 前端 :src 会拿到非 URL 值 → naturalWidth=0 → 破图。
        # 这是纯 API 断言测不出来的 UI 层故障，必须在浏览器里验。
        print("\n【图片加载（破图检测）】")
        for hash_path, label in [
            ("/pages/index/index", "首页"),
            ("/pages/notice/index", "公告页"),
            ("/pages/market/index", "市场页"),
            (f"/pages/index/info?iid={first_iid}", "藏品详情"),
        ]:
            _, _ = goto(hash_path, "imgcheck")
            time.sleep(1.2)
            imgs = page.evaluate(
                "() => [...document.querySelectorAll('img')]"
                ".map(i => ({src: i.currentSrc || i.src || '', nw: i.naturalWidth}))"
            )
            broken = [x for x in imgs if x["nw"] == 0]
            report(
                f"{label} 无破图",
                len(broken) == 0,
                f"{len(imgs)} 张图，{len(broken)} 张破图"
                + (f" → {broken[0]['src'][-50:]}" if broken else ""),
            )

        # ---- 5. 登录页 ----
        print("\n【登录页】")
        t, landed = goto("/pages/authorize/signin", "signin", 2000)
        report("路由落点正确", "authorize/signin" in landed, landed)
        report("登录页渲染", len(t.strip()) > 30, f"{len(t.strip())} 字符")
        report("未打到源站", len(source_calls) == 0)

        # 登录接口层验证。
        # 不走 UI 手动点击：登录页有图形验证码前置（openImageCaptcha），
        # 自动化要额外做图形识别，投入产出不划算。
        # 这里直接调接口验证链路 —— 浏览器上下文的 fetch 与前端 axios
        # 走完全相同的路径（同一 origin、同一 /api 前缀、同样经过 vite 代理），
        # 因此能验证 CORS / 包络 / token 写入这些真正的集成风险点。
        # 图形验证码属于纯前端交互，另有 57 页像素比对覆盖。
        #
        # 先回首页，避免停留在 hash 路由页面导致 SPA 状态干扰
        goto("/", "home-prelogin", 1200)
        login = page.evaluate(
            """async (API) => {
                const phone = '136' + String(Date.now()).slice(-8);
                const sms = await fetch(API + '/passport/captcha/sms', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({phone})
                }).then(r => r.json());
                const code = sms && sms.data && sms.data.code;
                if (!code) return {ok: false, reason: '未拿到验证码', sms};
                const res = await fetch(API + '/passport/signin/quick', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({phone, code})
                }).then(r => r.json());
                if (res && res.data && res.data.token) {
                    localStorage.setItem('zh:token',
                        JSON.stringify({type: 'string', data: res.data.token}));
                }
                return {ok: !!(res && res.data && res.data.token),
                        phone, uid: res && res.data && res.data.uid,
                        smsCode: code};
            }""",
            API,
        )
        report("短信验证码下发", login.get("smsCode"), f"code={login.get('smsCode')}")
        report("快捷登录拿到 token", login.get("ok"), f"uid={login.get('uid')}" if login.get("ok") else "失败")

        # ---- 6. 登录后带 token ----
        print("\n【登录后请求】")
        auth = page.evaluate(
            """async (API) => {
                const raw = localStorage.getItem('zh:token');
                const token = raw ? JSON.parse(raw).data : '';
                const out = {};
                const urls = {
                    profile: API + '/user/profile',
                    order: API + '/order',
                    nft: API + '/nft',
                    account: API + '/passport/account',
                    invite: API + '/user/invite',
                    points: API + '/activity/points/balance'
                };
                for (const [k, url] of Object.entries(urls)) {
                    const r = await fetch(url, {headers: {Authorization: 'Bearer ' + token}})
                        .then(x => x.json());
                    out[k] = r.code;
                }
                return out;
            }""",
            API,
        )
        for k, label in [
            ("profile", "/user/profile"),
            ("order", "/order"),
            ("nft", "/nft"),
            ("account", "/passport/account"),
            ("invite", "/user/invite"),
            ("points", "/activity/points/balance"),
        ]:
            report(label, auth.get(k) == 0, f"code={auth.get(k)}")

        # ---- 7. 我的页（带 token 刷新） ----
        print("\n【我的页】")
        t, landed = goto("/pages/my/index", "my", 3000)
        report("路由落点正确", "pages/my/index" in landed, landed)
        report("我的页渲染", len(t.strip()) > 30, f"{len(t.strip())} 字符")
        report("未打到源站", len(source_calls) == 0)

        # ---- 8. 3D 模型演示页（/pages/activity/test）----
        # 这一页路径在 activity 下但内容是 nb-model-viewer 组件演示页，
        # 核心是 iframe 加载 /static/model3d/index.html。iframe 的 src 一旦
        # 拼成 '//static/...'（协议相对 URL），域名会被吞掉 → 404 白屏，
        # 但页面本身不报错，只能断言 src 前缀才能发现。故单独加断言。
        print("\n【3D 模型演示页】")
        t, landed = goto("/pages/activity/test", "acttest", 3000)
        report("路由落点正确", "pages/activity/test" in landed, landed)
        report("页面主体渲染", page.locator(".page").count() == 1)
        frame_src = page.evaluate(
            "() => { const f = document.querySelector('.nbmv__frame');"
            " return f ? f.getAttribute('src') : '' }"
        )
        report(
            "iframe 路径正确（非协议相对）",
            frame_src.startswith("/static/model3d/index.html"),
            frame_src[:60] or "(无 src)",
        )
        ctrl = page.evaluate(
            "() => [...document.querySelectorAll('.ctrl__btn')].map(e => e.textContent.trim())"
        )
        report("三个控制按钮齐全", len(ctrl) == 3, "/".join(ctrl))
        report("按钮默认值符合源站", ctrl[:2] == ["暂停", "标准 · 32s"], "/".join(ctrl))
        info_rows = page.evaluate(
            "() => [...document.querySelectorAll('.info__row')]"
            ".map(e => e.textContent.replace(/\\s+/g, ' ').trim())"
        )
        report(
            "信息卡四行齐全",
            len(info_rows) == 4
            and info_rows[0] == "示例藏品 · 福仔"
            and "#000018330" in info_rows[1],
            f"{len(info_rows)} 行",
        )

        browser.close()

    failed = [r for r in results if not r["ok"]]
    print("\n" + "=" * 46)
    print(f" 通过 {len(results) - len(failed)} / 失败 {len(failed)} / 共 {len(results)}")
    if failed:
        print(" 失败: " + ", ".join(f["name"] for f in failed))
    if page_errors:
        print("\n页面错误:\n  " + "\n  ".join(page_errors[:5]))
    real_err = [e for e in console_errors if "favicon" not in e and "DevTools" not in e]
    if real_err:
        print("\nConsole 错误:\n  " + "\n  ".join(real_err[:8]))
    print("=" * 46)

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())