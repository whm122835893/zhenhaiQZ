<?php
declare(strict_types=1);

namespace app\controller;

use app\model\AppVersion;
use app\model\Banner;
use app\model\CommonRule;
use app\model\Notice;
use app\model\NoticeCategory;
use app\model\NoticeItemRelation;
use app\model\ProductTpCategory;
use app\model\ProductTpItem;
use app\service\Transformer;

/**
 * 基础内容控制器
 *
 * 对应源站 basic / common / app / product 分包，全部为只读端点。
 *
 * 源站实测要点：
 *  - GET /app/version/check 恒报 10101（源站未上线/参数规则未明），
 *    本地按同样的包络返回，实现按平台取最新版本的可用逻辑
 *  - 公告详情会带关联藏品列表（notice_item_relations）
 */
class Basic extends Base
{
    /**
     * GET /basic/banner —— 首页轮播
     *
     * 源站按 position 分区返回；不传position 时返回全部启用中的。
     */
    public function banner()
    {
        $query = Banner::where('status', 1)->where('is_deleted', 0);

        $position = $this->str('position');
        if ($position !== '') {
            $query->where('position', $position);
        }

        $rows = $query->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'        => Transformer::id($row['id']),
                'title'     => $row['title'],
                // 平铺双字段（源站形态），不是 {image:{...}} 嵌套
                ...Transformer::imagePair($row['image'] ?? '', 'image'),
                'linkType'  => (int)$row['link_type'],
                'linkValue' => (string)$row['link_value'],
                'position'  => (string)$row['position'],
                'sort'      => (int)$row['sort'],
            ];
        }

        return $data;
    }

    /**
     * GET /basic/notice/category —— 公告分类
     */
    public function noticeCategory()
    {
        $rows = NoticeCategory::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        return array_map(static fn ($row) => Transformer::noticeCategory($row), $rows);
    }

    /**
     * GET /basic/notice —— 公告列表
     *
     * 源站支持按 cid 过滤；isTop=1 只看置顶。
     * 列表不返回 content 全文，只返回标题与封面（详情才给全文）。
     */
    public function noticeList()
    {
        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = Notice::where('is_deleted', 0);

        $cid = $this->str('cid');
        if ($cid !== '') {
            $query->where('cid', $cid);
        }

        if ($this->int('isTop', 0) === 1) {
            $query->where('is_top', 1);
        }

        $total = (clone $query)->count();

        $rows = $query
            ->order('is_top', 'desc')
            ->order('published_ts', 'desc')
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $row) {
            // ⚠️ 列表同样要带 category 和 items —— 源站实测
            // GET /basic/notice 的每条记录都有这两个字段，
            // 前端公告页模板直接用 item.category.name.slice(0,2) 渲染标签。
            // 早期实现只传 $row，导致 category=null，
            // 页面白屏并报 "Cannot read properties of null (reading 'name')"。
            $data[] = $this->composeNotice($row);
        }

        // 源站实测返回裸数组（前端直接 res.data 展开），非 {list,total}
        return $this->pageList($data, $total);
    }

    /**
     * 组装单条公告 —— 列表与详情共用
     *
     * 源站两个端点返回的字段集完全一致（id/cid/title/content/createTime/category/items），
     * 所以抽成一个方法，避免两处各写一份、日后再走偏。
     */
    private function composeNotice(array $notice): array
    {
        // 关联分类对象（源站返回完整分类对象，不是只有 cid）
        $category = null;
        if (!empty($notice['cid'])) {
            $catRow = NoticeCategory::where('cid', $notice['cid'])->find();
            if ($catRow !== null) {
                $category = Transformer::noticeCategory($catRow->toArray());
            }
        }

        // 关联藏品
        $relations = NoticeItemRelation::where('notice_id', $notice['id'])
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $itemList = [];
        foreach ($relations as $it) {
            // ⚠️ 字段形态必须与源站逐字对齐（实测 GET /basic/notice）：
            //     image       是**字符串**相对路径（不是 {image,imageUrl} 对象）
            //     imageUrl    是拼好 media_base 的**完整 URL**
            //     isSecondary 是 **bool**（不是 0/1）
            //   之前误用 Transformer::image()（返回对象）导致前端拿到
            //   item.image.image === undefined，图片全变破图。
            $relative = (string)($it['image'] ?? '');
            $itemList[] = [
                'iid'         => Transformer::id($it['iid']),
                'name'        => (string)($it['name'] ?? ''),
                'imageUrl'    => $relative === '' ? '' : Transformer::mediaBase() . $relative,
                'image'       => $relative,
                'isSecondary' => (bool)$it['is_secondary'],
            ];
        }

        // 显式赋值而不是 $notice + [...数组并集]：
        // 并集运算在 $notice 已有 category 键时会保留原值（这里是 null），
        // 显式赋值语义更明确，也避免以后 select() 加了 join 时被静默覆盖。
        $notice['category'] = $category;

        return Transformer::notice($notice, $itemList);
    }

    /**
     * GET /basic/notice/{id} —— 公告详情
     *
     * 详情会带 notice_item_relations（公告关联的藏品）。
     */
    public function noticeDetail()
    {
        $id = $this->str('id');
        if ($id === '') {
            $id = (string)($this->request->param('id') ?? '');
        }

        $row = Notice::where('id', $id)->where('is_deleted', 0)->find();

        if (!$row) {
            throw \app\exception\ApiException::notFound();
        }

        $notice = $row->toArray();

        // 浏览量自增（源站行为：GET 详情即计数）
        Notice::where('id', $notice['id'])->inc('view_count')->update();

        // 与列表共用组装逻辑，保证两端字段集一致
        return $this->composeNotice($notice);
    }

    /**
     * GET /common/rule —— 协议条款
     *
     * 源站按 ruleKey 取（如 userAgreement / privacyPolicy）。
     * 不传则返回全部启用中的。
     */
    public function rule()
    {
        $query = CommonRule::where('status', 1);

        $key = $this->str('ruleKey');
        if ($key !== '') {
            $query->where('rule_key', $key);
        }

        $rows = $query->order('id', 'asc')->select()->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'      => Transformer::id($row['id']),
                'ruleKey' => $row['rule_key'],
                'title'   => $row['title'],
                'content' => $row['content'],
                'version' => (string)$row['version'],
            ];
        }

        return $data;
    }

    /**
     * GET /app/version/check —— 版本检查
     *
     * 源站该端点实测恒报 10101（未上线）。
     * 本地实现为按平台返回最新启用版本，供前端做升级提示。
     */
    public function versionCheck()
    {
        $platform = $this->str('platform', 'web');

        $row = AppVersion::where('platform', $platform)
            ->where('status', 1)
            ->order('id', 'desc')
            ->find();

        if (!$row) {
            // 与源站一致的包络：查不到视为无更新
            return [
                'needUpdate'  => false,
                'version'     => (string)config('shengtai.shengtai.version'),
                'minVersion'  => (string)config('shengtai.shengtai.version'),
                'forceUpdate' => false,
                'description' => '',
                'downloadUrl' => '',
            ];
        }

        $v = $row->toArray();

        return [
            'needUpdate'  => true,
            'version'     => $v['version'],
            'minVersion'  => $v['min_version'],
            'forceUpdate' => (int)$v['force_update'] === 1,
            'description' => $v['description'],
            'downloadUrl' => $v['download_url'],
        ];
    }

    /**
     * GET /product/tp_category —— 藏品分类（tp 体系）
     */
    public function tpCategory()
    {
        $rows = ProductTpCategory::order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'    => Transformer::id($row['id']),
                'name'  => $row['name'],
                ...Transformer::imagePair($row['image'] ?? '', 'image'),
                'sort'  => (int)$row['sort'],
            ];
        }

        return $data;
    }

    /**
     * GET /product/tp_list —— 藏品列表（tp 体系）
     *
     * 源站支持按 cid 过滤。
     */
    public function tpList()
    {
        $query = ProductTpItem::whereRaw('1=1');

        $cid = $this->str('cid');
        if ($cid !== '') {
            $query->where('cid', $cid);
        }

        $rows = $query->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'    => Transformer::id($row['id']),
                'cid'   => Transformer::id($row['cid']),
                'iid'   => Transformer::id($row['iid']),
                'name'  => $row['name'],
                ...Transformer::imagePair($row['image'] ?? '', 'image'),
                'sort'  => (int)$row['sort'],
            ];
        }

        return $data;
    }
}