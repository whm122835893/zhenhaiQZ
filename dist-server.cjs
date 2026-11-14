/**
 * 发布用网关：静态 dist 直出 + 反代 /api /storage → houtai(127.0.0.1:8787)
 * 优化：
 *   1. gzip 压缩（主页面 9.3MB→约6.3MB，model-viewer.min.js 935KB→约250KB）
 *   2. /static 长缓存（3D 模型与播放器二次进入详情秒开）
 * 路由（按行业"后端显式前缀 + 前端兜底"约定）：
 *   /api/ /storage/ → 反代 houtai（接口与上传图片）
 *   dist/ 内存在的文件 → 本地直出（含 /static/* 全部目录：model3d、
 *     images、album、html、my…，均为 C 端自有资源）
 *   其余 → SPA 兜底 index.html（hash 路由）
 * ⚠️ 不反代 /static：前后端同名目录，dist 里已含 C 端版本；BuildAdmin
 *    管理后台的 /static 只服务管理后台（直连后端源），C 端域名无此需求。
 *    也不反代 /media：后端无此目录。
 * 监听 $PORT（发布脚本注入），绑定 0.0.0.0。
 */
const http = require('http');
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const BACKEND_HOST = '127.0.0.1';
const BACKEND_PORT = 8787;
const DIST = path.join(__dirname, 'dist');
const PROXY_PREFIXES = ['/api/', '/storage/'];

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

/**
 * dist 内文件直出：/static/*（model3d、images、album、html、my… 全部 C 端
 * 自有目录）及其它构建产物。文件不存在返回 null，由调用方走 SPA 兜底。
 */
function resolveDistFile(url) {
  const pathname = decodeURIComponent(url.split('?')[0]);
  const file = path.join(DIST, path.normalize(pathname).replace(/^(\.\.[\/\\])+/, ''));
  if (!file.startsWith(DIST) || file === DIST) return null;
  try {
    if (!fs.statSync(file).isFile()) return null;
  } catch (e) {
    return null;
  }
  return file;
}

function serveDistFile(req, file, res) {
  const inStatic = file.startsWith(path.join(DIST, 'static') + path.sep);
  // /static 指纹外资源但内容稳定（3D 模型/播放器/页面图），24h 缓存；
  // 构建产物与入口 HTML 不缓存，保证发版即时生效
  sendFile(res, file, inStatic ? 'public, max-age=86400' : 'no-cache', acceptsGzip(req));
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

  if (PROXY_PREFIXES.some((p) => url === p.slice(0, -1) || url.startsWith(p))) { proxy(req, url, res); return; }
  const file = resolveDistFile(url);
  if (file) { serveDistFile(req, file, res); return; }
  serveIndex(req, res); // SPA（hash 路由）一律回 index.html
});

const port = Number(process.env.PORT || 3000);
server.listen(port, '0.0.0.0', () => {
  console.log(`[zhenhai-gateway] listening on 0.0.0.0:${port} -> backend ${BACKEND_HOST}:${BACKEND_PORT}`);
});
