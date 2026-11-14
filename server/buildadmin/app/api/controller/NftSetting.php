<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use think\facade\Db;

/**
 * C 端品牌信息（只读、无需登录）
 *
 * 接口：
 *   GET /api/nft_setting/brand   平台名称 / Logo（setting 表 brand 分组）
 *
 * 数据表：setting（后台「运营参数 → 平台品牌」维护，仅超级管理员可改）
 * 约定：logo 文件不存在时返回 ''，前端回退到内置品牌图，避免破图
 */
class NftSetting extends Frontend
{
    protected array $noNeedLogin = ['*'];
    protected array $noNeedRight = ['*'];

    public function brand(): void
    {
        $rows = Db::name('setting')->where('group', 'brand')
            ->whereIn('name', ['site_name', 'site_logo'])
            ->column('value', 'name');

        // logo：值非空且文件真实存在才下发完整 URL，否则给空串走前端兜底
        $logo    = (string) ($rows['site_logo'] ?? '');
        $logoReal = '';
        if ($logo !== '') {
            $path = $this->app->getRootPath() . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $logo);
            if (is_file($path)) {
                $logoReal = full_url($logo);
            }
        }

        $this->success('', [
            'name' => (string) ($rows['site_name'] ?? '') ?: '臻海艺术',
            'logo' => $logoReal,
        ]);
    }
}
