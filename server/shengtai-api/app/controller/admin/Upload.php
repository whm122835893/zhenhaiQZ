<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * 管理端图片上传 —— POST /admin/upload/image（multipart，字段名 file）
 *
 * 存储与现有藏品图一致：站点根 public/media/{雪花ID}.{ext}，
 * 返回相对路径 /media/xxx.png（C 端同域直接访问，生产网关同域）。
 * 仅超管/运营可上传（needWriter），与藏品编辑权限对齐。
 */
class Upload extends AdminBase
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB
    private const ALLOW_EXT = ['jpg', 'jpeg', 'png', 'webp'];

    /** 站点根 media 目录：app/controller/admin 上溯 5 级即项目根 */
    private function mediaDir(): string
    {
        return dirname(__DIR__, 5) . '/public/media';
    }

    public function image()
    {
        $this->needWriter();

        $file = $this->request->file('file');
        if ($file === null || !$file->isValid()) {
            throw ApiException::missing('file');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new ApiException('图片不能超过 5MB', ApiException::CODE_PARAM_TYPE);
        }

        $ext = strtolower((string)$file->getOriginalExtension());
        if (!in_array($ext, self::ALLOW_EXT, true)) {
            throw new ApiException('仅支持 jpg / png / webp 格式', ApiException::CODE_PARAM_TYPE);
        }

        // 二次校验真实图片内容，防止伪造扩展名
        $realExt = strtolower((string)(image_type_to_extension(@getimagesize($file->getRealPath())[2] ?? 0, false) ?: ''));
        $realExt = str_replace('jpeg', 'jpg', $realExt);
        if (!in_array($realExt, self::ALLOW_EXT, true)) {
            throw new ApiException('文件内容不是有效图片', ApiException::CODE_PARAM_TYPE);
        }

        $dir = $this->mediaDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new ApiException('存储目录创建失败', ApiException::CODE_SYSTEM_ERROR);
        }

        $name = \app\helper\Snowflake::nextIdStr() . '.' . $ext;
        // move 会把临时文件移走，size 必须先取
        $size = (int)$file->getSize();
        $file->move($dir, $name);
        if (!is_file($dir . '/' . $name)) {
            throw new ApiException('图片保存失败', ApiException::CODE_SYSTEM_ERROR);
        }

        $url = '/media/' . $name;
        $this->opLog('upload', 'image', null, ['url' => $url, 'size' => $size], $url);

        return ['url' => $url];
    }
}
