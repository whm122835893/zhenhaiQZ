/**
 * 发布用网关：静态 dist + 反代 /api /media /static → houtai(127.0.0.1:8787)
 * 优化：
 *   1. gzip 压缩（主页面 9.3MB→约6.3MB，model-viewer.min.js 935KB→约250KB）
 *   2. /static/model3d 本地直出 + 长缓存（二次进入详情秒开）
 * 监听 $PORT（发布脚本注入），绑定 0.0.0.0。
 */
const http = require('http');
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const BACKEND_HOST = '127.0.0.1';
const BACKEND_PORT = 8787;
const DIST = path.join(__dirname, 'dist');
const PROXY_PREFIXES = ['/api/', '/media/', '/static/'];
const LOCAL_STATIC = '/static/model3d/'; // 3D 播放器与模型：C 端自带，不走 houtai

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.gltf': 'model/gltf+json',
  '.glb': 'model/gltf-binary',
  '.bin': 'application/octet-stream',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml',
  '.woff': 'font/woff',
  '.ttf': 'font/ttf',
};
const GZIP_TYPES = new Set(['.html', '.js', '.css', '.gltf', '.svg', '.json']);

function acceptsGzip(req) {
  return /gzip/i.test(req.headers['accept-encoding'] || '');
}

/** 带压缩与缓存的文件输出 */
function sendFile(res, file, cache, canGzip) {
  const ext = path.extname(file).toLowerCase();
  const headers = {
    'Content-Type': MIME[ext] || 'application/octet-stream',
    'Cache-Control': cache,
  };
  let stream = fs.createReadStream(file);
  stream.on('error', () => {
    if (!res.headersSent) { res.writeHead(404); res.end('not found'); }
  });
  if (canGzip && GZIP_TYPES.has(ext)) {
    headers['Content-Encoding'] = 'gzip';
    headers['Vary'] = 'Accept-Encoding';
    res.writeHead(200, headers);
    stream = stream.pipe(zlib.createGzip({ level: 6 }));
  } else {
    res.writeHead(200, headers);
  }
  stream.pipe(res);
}

function serveLocalStatic(req, url, res) {
  const rel = decodeURIComponent(url.split('?')[0].slice(LOCAL_STATIC.length - 1));
  const base = path.join(DIST, 'static', 'model3d');
  const file = path.join(base, path.normalize(rel).replace(/^(\.\.[\/\\])+/, ''));
  if (!file.startsWith(base)) { res.writeHead(403); res.end(); return; }
  sendFile(res, file, 'public, max-age=86400', acceptsGzip(req));
}

function serveIndex(req, res) {
  sendFile(res, path.join(DIST, 'index.html'), 'no-cache', acceptsGzip(req));
}

function proxy(req, url, res) {
  const upstream = http.request(
    {
      host: BACKEND_HOST,
      port: BACKEND_PORT,
      path: url,
      method: req.method,
      headers: { ...req.headers, host: `${BACKEND_HOST}:${BACKEND_PORT}` },
    },
    (pr) => {
      res.writeHead(pr.statusCode || 502, pr.headers);
      pr.pipe(res);
    }
  );
  upstream.on('error', () => {
    if (!res.headersSent) res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ status: false, code: 20000, message: '后端服务暂不可用', data: null }));
  });
  req.pipe(upstream);
}

const server = http.createServer((req, res) => {
  const url = req.url || '/';

  if (url.startsWith(LOCAL_STATIC)) { serveLocalStatic(req, url, res); return; }
  if (PROXY_PREFIXES.some((p) => url === p.slice(0, -1) || url.startsWith(p))) { proxy(req, url, res); return; }
  serveIndex(req, res); // SPA（hash 路由）一律回 index.html
});

const port = Number(process.env.PORT || 3000);
server.listen(port, '0.0.0.0', () => {
  console.log(`[zhenhai-gateway] listening on 0.0.0.0:${port} -> backend ${BACKEND_HOST}:${BACKEND_PORT}`);
});
