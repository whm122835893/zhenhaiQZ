#!/usr/bin/env python3
"""修复「假图」—— 之前抓取时把源站 404 页面存成了 .png/.jpg

识别方式：读文件头若干字节，首字节是 '<'（即 <!doctypehtml> / <html）即中招。
源站的静态资源真实路径是 https://h5.shengtai.cyou/static/<name>，
本项目已把这些文件放到 src/assets/**，因此按**文件名**去源站取回真实字节即可。

用法：
  python3 scripts/fix_fake_images.py            # 预演，列出待修
  python3 scripts/fix_fake_images.py --apply    # 实际覆盖写回
"""
import os
import sys
import pathlib
import urllib.request

SRC_HOST = "https://h5.shengtai.cyou"
ROOT = pathlib.Path("src/assets")
APPLY = "--apply" in sys.argv


def is_fake(path: pathlib.Path) -> bool:
    """文件内容其实是 HTML —— 以 '<' 开头"""
    try:
        with open(path, "rb") as f:
            head = f.read(16)
    except OSError:
        return False
    return head[:1] == b"<"


def fetch(name: str):
    """按文件名从源站取真实资源

    源站静态资源分布在两个根目录（Vite 构建产物 + uni-app static 目录）：
      /assets/<name>            —— 带 hash 的构建产物，如 logo-mZgOf6Q4.jpg
      /static/nb-index/<name>   —— 页面级素材，如 qa-invite.png
    两个都试，取先命中的。
    """
    for prefix in ("/assets/", "/static/nb-index/", "/static/images/", "/static/"):
        url = f"{SRC_HOST}{prefix}{name}"
        try:
            with urllib.request.urlopen(url, timeout=20) as r:
                data = r.read()
        except Exception:
            continue
        # 源站对不存在的路径返回 404 HTML（约 1017 字节），必须识别出来
        if data[:1] == b"<":
            continue
        return url, data
    return None, None


def main():
    fakes = [p for p in ROOT.rglob("*") if p.is_file() and p.suffix.lower() in
             (".png", ".jpg", ".jpeg", ".gif", ".webp") and is_fake(p)]

    print("=" * 64)
    print(f" 假图清查（内容实为 HTML）  共 {len(fakes)} 个")
    print("=" * 64)

    fixed, unfixable = [], []
    for p in fakes:
        url, data = fetch(p.name)
        if data:
            print(f"  ✓ {p}  <- {url}  ({len(data)} 字节)")
            if APPLY:
                p.write_bytes(data)
            fixed.append(p)
        else:
            print(f"  ✗ {p}  源站同名资源已下线，无法取回")
            unfixable.append(p)

    print("\n" + "=" * 64)
    verb = "已修复" if APPLY else "可修复（加 --apply 生效）"
    print(f" {verb} {len(fixed)} 个 / 源站已下线 {len(unfixable)} 个 / 共 {len(fakes)} 个")
    if unfixable:
        print("\n 源站已下线，需人工处理：")
        for p in unfixable:
            print(f"   {p}")
    print("=" * 64)
    return 0 if not unfixable else 1


if __name__ == "__main__":
    sys.exit(main())