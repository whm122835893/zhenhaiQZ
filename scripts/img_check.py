import time, json
from playwright.sync_api import sync_playwright

FRONT = "http://127.0.0.1:5173"
pages = [
    ("#/pages/index/index", "首页"),
    ("#/pages/notice/index", "公告页"),
    ("#/pages/market/index", "市场页"),
    ("#/pages/index/info?iid=2109928427009183745", "藏品详情"),
]

with sync_playwright() as p:
    b = p.chromium.launch(args=["--use-gl=swiftshader","--enable-unsafe-swiftshader"])
    for h, name in pages:
        pg = b.new_page(viewport={"width":390,"height":844})
        pg.goto("about:blank")
        pg.goto(f"{FRONT}/{h}")
        time.sleep(2.5)
        r = pg.evaluate("""() => {
          const imgs=[...document.querySelectorAll('img')];
          return imgs.map(i=>({src:(i.currentSrc||i.src||'').slice(-60), nw:i.naturalWidth, w:i.width}));
        }""")
        bad = [x for x in r if x["nw"]==0]
        print(f"[{name}] img总数={len(r)} 破图={len(bad)}")
        for x in bad[:5]: print("   BROKEN:", x)
        for x in r[:3]: print("   ok:", x["nw"], x["src"])
        pg.close()
    b.close()
