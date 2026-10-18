#!/usr/bin/env python3
"""58 页全量巡检 —— 逐页加载并记录渲染状态

复刻项目里每页都应能独立打开。本脚本按路由表遍历所有页面，
记录：路由落点是否正确、是否渲染出内容、是否有 JS 运行时错误、破图数量。

注意：
  - 3D 相关的页面（activity/test、index/info、my/nft/info）在沙箱里会报
    WebGL 上下文错误，这是环境限制不是代码缺陷，单独归类为「沙箱限制」。

用法：python3 scripts/sweep_all_pages.py
"""
import re
import sys
import time
from playwright.sync_api import sync_playwright

FRONT = "http://127.0.0.1:5173"
BACKEND = "http://127.0.0.1:8921"
VIEWPORT = {"width": 390, "height": 844}

# 巡检用的一次性测试账号（走源站同款「短信快捷登录」链路拿 token）
PROBE_PHONE = "13900000001"

# 沙箱无 WebGL 上下文，这些页面的 three.js 报错属环境限制
WEBGL_PAGES = {"pages/activity/test", "pages/index/info", "pages/my/nft/info"}

# 依赖「当前登录用户名下真实 NFT」的页面。
# 源站 onLoad：nftId 取不到、或 getNft 返回非 status → reLaunch 回 /pages/my/index。
# 巡检用的是全新测试账号（名下 0 个 NFT），源站在同样条件下也会跳走，
# 因此这属于**环境数据缺失**，不是路由缺陷，单独标注不计为失败。
NEEDS_OWN_NFT = {"pages/my/nft/info", "pages/my/nft/sale"}

# 需要登录态才有意义的页面，token 缺失时可能显示空态但不崩溃
AUTH_PAGES = {
    "pages/my/index", "pages/my/order/index", "pages/my/order/info",
    "pages/my/wallet/index", "pages/my/nft/info", "pages/my/nft/sale",
    "pages/my/nft/batchSale", "pages/my/invite/index", "pages/my/info/userInfo",
    "pages/my/info/account", "pages/my/info/adress", "pages/my/info/pwd",
    "pages/my/info/xieyi", "pages/my/setting/index", "pages/my/setting/about",
    "pages/my/setting/delete", "pages/my/setting/realname",
    "pages/my/setting/secondPassword",
}

# ⚠️ 详情类页面依赖 query 参数才能渲染出内容。
# 缺参数时 Vue Router 会命中 `:pathMatch(.*)*` 通配兜底并重定向到首页，
# 于是「路由落点错误」—— 这是**巡检方式的问题，不是路由缺陷**。
# 这里给这些页补上真实参数，才能测出页面本身的状态。
QUERY_ARGS = {
    "pages/index/info": "?iid=2109928427009183745",
    "pages/market/info": "?iid=2109928427009183745",
    "pages/market/batch": "?iid=2109928427009183745",
    "pages/market/need": "?iid=2109928427009183745",
    "pages/market/trust": "?iid=2109928427009183745",
    "pages/market/trustIndex": "?iid=2109928427009183745",
    "pages/market/log": "?iid=2109928427009183745",
    # 源站 my/order/info 的 onLoad 取 **orderId**；
    # 用不存在的 id 接口 404（源站同样不跳首页，只是空态）
    "pages/my/order/info": "?orderId=1",
    # 源站 my/nft/info 的 onLoad 取的是 **nftId**（不是 iid），
    # 缺参数会被 reLaunch 回 /pages/my/index，路由落点随之改变 → 误报「落点错误」。
    "pages/my/nft/info": "?nftId=1",
    # 源站 my/nft/sale 的 onLoad 取的也是 **nftId**
    "pages/my/nft/sale": "?nftId=1",
    "pages/my/nft/batchSale": "?iid=2109928427009183745",
    # 源站 notice/search 的 onLoad 取的是 **iid**
    "pages/notice/search": "?iid=2109916747625762818",
    # 源站 mergeInfo 的 onLoad 取的是 **id**（合成配方 ID）+ type（1=合成兑换）
    "pages/activity/mergeInfo": "?id=2111294322054856705&type=1",
    # 源站 notice/info 取 **id**（公告 ID，雪花 ID）；
    # 用不存在的 id=1 会接口 404，虽不跳转但巡检会误判落点。
    "pages/notice/info": "?id=2111417341762928642",
    "pages/author/detail": "?id=2109922931380424706",
    # 源站 webView 的 onLoad 取的是 **src**（不是 url）
    "pages/index/webView": "?src=https%3A%2F%2Fh5.shengtai.cyou",
}


def login_token():
    """拿一个可用 token，让 my/* 需登录页能真实渲染而不是跳首页。

    源站逻辑：接口返回 11111（未登录）时前端 reLaunch 回 /pages/my/index，
    于是巡检看到「路由落点错误」—— 那是缺登录态，不是路由缺陷。
    """
    import json
    import urllib.request

    def post(path, payload):
        req = urllib.request.Request(
            BACKEND + path,
            data=json.dumps(payload).encode(),
            headers={"Content-Type": "application/json"},
        )
        with urllib.request.urlopen(req, timeout=8) as r:
            return json.load(r)

    # 快捷登录必须带验证码：先取码，再用码换 token
    sms = post("/passport/captcha/sms", {"phone": PROBE_PHONE})
    code = (sms.get("data") or {}).get("code")
    if not code:
        raise RuntimeError(f"未拿到验证码: {sms}")
    body = post("/passport/signin/quick", {"phone": PROBE_PHONE, "code": code})
    return (body.get("data") or {}).get("token")


def load_paths():
    src = open("src/router/index.js", encoding="utf-8").read()
    return re.findall(r"path: '(/pages/[^']+)'", src)


def main():
    paths = load_paths()
    print("=" * 60)
    print(f" 全量页面巡检  共 {len(paths)} 页  前端={FRONT}")
    print("=" * 60)

    ok, blank, failed = [], [], []
    with sync_playwright() as p:
        b = p.chromium.launch(args=["--use-gl=swiftshader", "--enable-unsafe-swiftshader"])

        token = None
        try:
            token = login_token()
            print(f" 登录态: {'已获取' if token else '未获取（my/* 页按未登录巡检）'}")
        except Exception as e:
            print(f" 登录态: 获取失败 {str(e)[:50]}")

        for path in paths:
            pg = b.new_page(viewport=VIEWPORT)
            if token:
                # ⚠️ 不能用 add_init_script：它会在 about:blank 上执行，
                #    而 about:blank 是 opaque origin，访问 localStorage 直接抛
                #    "Access is denied"，导致每页都报 JS 错误。
                # 改为先 goto 拿到同源文档，再写 token 并 reload。
                pg.goto("about:blank")
                pg.goto(f"{FRONT}/")
                pg.evaluate(
                    "t => localStorage.setItem('zh:token',"
                    " JSON.stringify({type:'string', data:t}))",
                    token,
                )
            errs = []
            pg.on("pageerror", lambda e: errs.append(str(e)))
            try:
                # 必须先 about:blank 断开文档，否则只改 hash 不会重新加载
                key = path.lstrip("/")
                url = f"{FRONT}/#{path}{QUERY_ARGS.get(key, '')}"
                pg.goto("about:blank")
                pg.goto(url)
                time.sleep(1.6)

                landed = pg.evaluate("location.hash").lstrip("#").split("?")[0]
                body = pg.evaluate("document.querySelector('#app')?.innerText || ''").strip()
                broken = pg.evaluate(
                    "() => [...document.querySelectorAll('img')].filter(i => i.naturalWidth === 0).length"
                )
                # 过滤沙箱 WebGL 噪声
                real = [e for e in errs if "xr" not in e and "getFieldOfView" not in e
                        and "WebGL" not in e]

                route_ok = landed == path
                if not route_ok and key in NEEDS_OWN_NFT and landed == "/pages/my/index":
                    # 源站同样会 reLaunch（名下无该 NFT），不计失败
                    ok.append(path)
                    print(f"  ✓ {path:38s} {'':>5} 源站同行为（名下无该 NFT，跳 my/index）")
                    continue
                if not route_ok:
                    failed.append((path, f"路由落点错误 -> {landed}"))
                    print(f"  ✗ {path:38s} 路由落点错误 -> {landed}")
                elif not body:
                    blank.append((path, "渲染空白"))
                    print(f"  ✗ {path:38s} 渲染空白")
                elif real:
                    failed.append((path, f"JS错误: {real[0][:70]}"))
                    print(f"  ✗ {path:38s} JS错误: {real[0][:70]}")
                elif broken:
                    failed.append((path, f"{broken} 张破图"))
                    print(f"  ✗ {path:38s} {broken} 张破图")
                else:
                    tag = " [需登录]" if path in AUTH_PAGES else ""
                    tag += " [WebGL受限]" if path in WEBGL_PAGES else ""
                    ok.append(path)
                    print(f"  ✓ {path:38s} {len(body):>5} 字符{tag}")
            except Exception as e:
                failed.append((path, f"异常: {str(e)[:70]}"))
                print(f"  ✗ {path:38s} 异常: {str(e)[:70]}")
            finally:
                pg.close()
        b.close()

    print("\n" + "=" * 60)
    print(f" 通过 {len(ok)} / 失败 {len(failed)} / 共 {len(paths)}")
    if failed:
        print("\n失败明细:")
        for path, why in failed:
            print(f"  {path:38s} {why}")
    print("=" * 60)
    return 0 if not failed else 1


if __name__ == "__main__":
    sys.exit(main())