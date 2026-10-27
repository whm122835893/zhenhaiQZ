<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * CMS 内容管理 —— 公告 CRUD + 轮播列表/状态
 */
class Cms extends AdminBase
{
    /* ---------- 公告 ---------- */

    public function notices()
    {
        $q = \think\facade\Db::name('notices')->where('is_deleted', 0)
            ->field('id,cid,title,cover,cover_url,view_count,is_top,published_ts,created_at');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->whereLike('title', "%{$kw}%");
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        $cids = array_values(array_unique(array_column($data['list'], 'cid')));
        // 分类列按雪花 cid 关联（与 C 端 composeNotice 一致）
        $cats = $cids === [] ? [] : \think\facade\Db::name('notice_categories')->whereIn('cid', $cids)->column('name', 'cid');
        foreach ($data['list'] as &$r) {
            $r['id']       = (string)$r['id'];
            $r['cid']      = (string)$r['cid'];
            $r['category'] = (string)($cats[$r['cid']] ?? '');
            $r['coverUrl'] = (string)($r['cover_url'] ?: $r['cover']);
            $r['isTop']    = (int)$r['is_top'] === 1;
            unset($r['cover'], $r['cover_url'], $r['is_top']);
        }
        unset($r);
        return $data;
    }

    /**
     * GET /admin/cms/notices/{id} —— 公告详情（编辑回填 + 预览用）
     * 返回 content 正文 HTML 与关联藏品列表（与 C 端详情页展示一致）
     */
    public function noticeDetail()
    {
        $id = $this->needInt('id');
        $row = \think\facade\Db::name('notices')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            throw ApiException::notFound('公告不存在');
        }

        $row['id']  = (string)$row['id'];
        $row['cid'] = (string)$row['cid'];
        $row['isTop'] = (int)$row['is_top'] === 1;
        $row['coverUrl'] = (string)($row['cover_url'] ?: $row['cover']);

        // 关联藏品（notice_item_relations），编辑回填与预览共用
        $row['items'] = array_map(static function ($it) {
            return [
                'iid'         => (string)$it['iid'],
                'name'        => (string)$it['name'],
                'imageUrl'    => (string)($it['image_url'] ?: $it['image']),
                'isSecondary' => (int)$it['is_secondary'] === 1,
            ];
        }, \think\facade\Db::name('notice_item_relations')
            ->where('notice_id', $id)
            ->order('sort', 'asc')
            ->select()
            ->toArray());

        return $row;
    }

    public function noticeSave()
    {
        $this->needWriter();
        $id   = $this->int('id', 0);
        $body = $this->body();

        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') {
            throw ApiException::missing('title');
        }

        $cid = (int)($body['cid'] ?? 0);
        // 校验分类有效性（按雪花 cid）：脏 cid 会让 C 端 category=null 导致公告页渲染报错
        if ($cid > 0 && !\think\facade\Db::name('notice_categories')->where('cid', $cid)->where('is_deleted', 0)->find()) {
            throw new ApiException('公告分类不存在，请重新选择', ApiException::CODE_PARAM_TYPE);
        }

        $fields = [
            'cid'          => $cid,
            'title'        => $title,
            'content'      => (string)($body['content'] ?? ''),
            'cover'        => (string)($body['cover'] ?? ''),
            'is_top'       => !empty($body['isTop']) ? 1 : 0,
            'published_ts' => (int)round(microtime(true) * 1000),
        ];

        // 关联藏品：iid 数组，name/image/is_secondary 从 items 冗余带出防脏数据
        $itemIids = array_values(array_unique(array_filter(array_map(
            static fn ($v) => (string)$v,
            (array)($body['items'] ?? [])
        ), static fn ($v) => $v !== '' && ctype_digit($v))));

        if ($id > 0) {
            $old = \think\facade\Db::name('notices')->where('id', $id)->where('is_deleted', 0)->find();
            if (!$old) {
                throw ApiException::notFound('公告不存在');
            }
            unset($fields['published_ts']);
            \think\facade\Db::name('notices')->where('id', $id)->update($fields);
            $this->syncNoticeItems((string)$id, $itemIids);
            $this->opLog('cms', 'notice_update', $old, $fields, 'notice:' . $id);
            return ['id' => (string)$id];
        }

        // notices.id 为雪花 ID（无自增），插入时显式生成；
        // 显式主键插入后 lastInsertId 不可靠，直接用生成值回传
        $idStr = \app\helper\Snowflake::nextIdStr();
        \think\facade\Db::name('notices')->insert(['id' => $idStr] + $fields + ['is_deleted' => 0]);
        $id = $idStr;
        $this->syncNoticeItems((string)$id, $itemIids);
        $this->opLog('cms', 'notice_create', null, $fields, 'notice:' . $id);
        return ['id' => $id];
    }

    /**
     * 同步公告-藏品关联：全量删旧插新（关系表量小，简单可靠）。
     * iid 不存在时跳过，冗余字段自动带出。
     */
    private function syncNoticeItems(string $noticeId, array $iids): void
    {
        $table = \think\facade\Db::name('notice_item_relations');
        $table->where('notice_id', $noticeId)->delete();
        if ($iids === []) {
            return;
        }

        $items = \think\facade\Db::name('items')
            ->whereIn('iid', $iids)
            ->where('is_deleted', 0)
            ->field('iid,name,image,image_url,is_secondary')
            ->select()
            ->toArray();

        $rows = [];
        foreach ($items as $idx => $it) {
            $rows[] = [
                'id'           => \app\helper\Snowflake::nextIdStr(),
                'notice_id'    => $noticeId,
                'iid'          => $it['iid'],
                'name'         => (string)$it['name'],
                'image'        => (string)$it['image'],
                'image_url'    => (string)$it['image_url'],
                'is_secondary' => (int)$it['is_secondary'],
                'sort'         => $idx,
            ];
        }
        if ($rows !== []) {
            $table->insertAll($rows);
        }
    }

    public function noticeDelete()
    {
        $this->needWriter();
        $id = $this->needInt('id');
        $old = \think\facade\Db::name('notices')->where('id', $id)->find();
        if (!$old || (int)$old['is_deleted'] === 1) {
            throw ApiException::notFound('公告不存在');
        }
        \think\facade\Db::name('notices')->where('id', $id)->update(['is_deleted' => 1]);
        $this->opLog('cms', 'notice_delete', $old, ['is_deleted' => 1], 'notice:' . $id);
        return ['ok' => true];
    }

    /**
     * GET /admin/cms/notice-categories —— 公告分类下拉
     *
     * ⚠️ 返回 cid（雪花 ID）而非自增主键 id：notices.cid 存的是分类雪花 ID，
     * C 端 composeNotice 按 cid 关联。此前下拉绑定自增 id（1-5），
     * 保存后 C 端查不到分类 → category=null → C 端公告页白屏。
     */
    public function noticeCategories()
    {
        return ['list' => \think\facade\Db::name('notice_categories')
            ->where('is_deleted', 0)
            ->field('cid,name')
            ->order('sort', 'asc')
            ->select()
            ->toArray()];
    }

    /* ---------- 轮播 ---------- */

    public function banners()
    {
        $data = \think\facade\Db::name('banners')->where('is_deleted', 0)
            ->order('sort', 'asc')->order('id', 'desc')
            ->field('id,title,image,image_url,link_type,link_value,position,sort,status')
            ->select()->toArray();
        foreach ($data as &$r) {
            $r['id']       = (string)$r['id'];
            $r['imageUrl'] = (string)($r['image_url'] ?: $r['image']);
            unset($r['image'], $r['image_url']);
        }
        unset($r);
        return ['list' => $data];
    }

    public function bannerUpdate()
    {
        $this->needWriter();
        $id = $this->needInt('id');
        $b  = \think\facade\Db::name('banners')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$b) {
            throw ApiException::notFound('轮播不存在');
        }

        $body  = $this->body();
        $allow = ['title', 'link_value', 'position', 'sort', 'status'];
        $data  = [];
        foreach ($allow as $f) {
            if (array_key_exists($f, $body)) {
                $data[$f] = in_array($f, ['sort', 'status'], true) ? (int)$body[$f] : $body[$f];
            }
        }
        if ($data === []) {
            throw ApiException::missing('可编辑字段');
        }

        \think\facade\Db::name('banners')->where('id', $id)->update($data);
        $this->opLog('cms', 'banner_update', $b, $data, 'banner:' . $id);
        return ['id' => (string)$id, 'updated' => array_keys($data)];
    }
}
