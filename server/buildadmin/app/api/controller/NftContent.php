<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use think\facade\Db;

/**
 * C 端内容接口（轮播 / 公告），全部只读、无需登录
 *
 * 接口：
 *   GET /api/nft_content/banner       轮播列表（启用中，weigh 降序）
 *   GET /api/nft_content/notice       公告列表（page/limit/category/title）
 *   GET /api/nft_content/notice?id=3  公告详情（含富文本 content）
 *
 * 数据表：banner / notice（后台「内容管理」维护）
 * 前端约定：createTime 为毫秒时间戳；category 为 {cid,name,coverUrl} 对象
 */
class NftContent extends Frontend
{
    protected array $noNeedLogin = ['*'];
    protected array $noNeedRight = ['*'];

    /** 公告分类枚举（notice.category 1=公告,2=活动,3=系统） */
    private const CATEGORIES = [1 => '公告', 2 => '活动', 3 => '系统'];

    /**
     * 轮播列表
     */
    public function banner(): void
    {
        $rows = Db::name('banner')->where('status', 1)
            ->order('weigh', 'desc')->order('id', 'desc')
            ->field('id,title,image,link')
            ->select()->toArray();
        $this->success('', $rows);
    }

    /**
     * 公告列表 / 详情
     */
    public function notice($id = 0): void
    {
        // 兼容历史调用：id 可能以数组形式误传（如 id[pageNo]=1），一律按缺省处理
        $id = is_array($id) ? 0 : (int) $id;
        if ($id <= 0) {
            $id = (int) $this->request->get('id/d', 0);
        }

        // 详情
        if ($id > 0) {
            $row = Db::name('notice')->where('status', 1)->where('id', $id)->find();
            if (!$row) {
                $this->error('公告不存在');
            }
            $this->success('', $this->formatNotice($row, true));
        }

        // 列表
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit/d', 10)));
        $cid   = (int) $this->request->get('category/d', 0);
        $title = trim((string) $this->request->get('title/s', ''));

        $query = Db::name('notice')->where('status', 1);
        if ($cid > 0) {
            $query->where('category', $cid);
        }
        if ($title !== '') {
            $query->whereLike('title', '%' . $title . '%');
        }
        $rows = $query->order('is_top', 'desc')->order('publish_time', 'desc')->order('id', 'desc')
            ->page($page, $limit)->select()->toArray();

        $this->success('', array_map(fn ($r) => $this->formatNotice($r), $rows));
    }

    /**
     * 输出形状对齐 C 端：createTime 毫秒、category 对象、items 空数组
     */
    private function formatNotice(array $row, bool $detail = false): array
    {
        $time = (int) ($row['publish_time'] ?: $row['createtime']);
        $out  = [
            'id'         => (int) $row['id'],
            'title'      => (string) $row['title'],
            'createTime' => $time * 1000,
            'category'   => [
                'cid'      => (int) $row['category'],
                'name'     => self::CATEGORIES[(int) $row['category']] ?? '公告',
                'coverUrl' => '',
            ],
            'items'      => [],
        ];
        if ($detail) {
            $out['content']   = (string) ($row['content'] ?? '');
            $out['viewCount'] = 0;
        }
        return $out;
    }
}
