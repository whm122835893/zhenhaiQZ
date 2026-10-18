<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Item as ItemModel;
use app\model\ItemCategory;
use app\model\ItemAuthor;
use app\model\ItemCopyright;
use app\model\ItemFollow;
use app\model\Nft;
use app\model\NftMergeRecipe;
use app\model\NftMergeProduce;
use app\model\NftMergeNeed;
use app\service\Transformer;
use think\facade\Db;

/**
 * 藏品目录控制器
 *
 * 对应源站 item 分包。所有路径与方法严格对齐 api.shengtai.cyou。
 *
 * 源站实测要点：
 *  - GET /item/{iid} 是正确用法（GET /item/detail 恒报 10101，是源站自身问题）
 *  - GET /item/copyright 返回**全局**版权，不按 iid 过滤
 *  - GET /item/merge 列表的 merges/needs 恒为 null，只有详情接口才填
 */
class Item extends Base
{
    /**
     * GET /item —— 藏品列表
     */
    public function index()
    {
        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = ItemModel::where('is_deleted', 0);

        if (($cid = $this->str('cid')) !== '') {
            $query->where('cid', $cid);
        }
        if (($type = $this->str('type')) !== '') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $rows = $query->order('id', 'asc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $uid = $this->request->auth_uid ?? null;

        return array_map(
            fn($r) => Transformer::item($r, $this->ownQuantityOf($r, $uid), false),
            $rows
        );
    }

    /**
     * GET /item/category —— 分类列表
     */
    public function category()
    {
        $rows = ItemCategory::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        return array_map([Transformer::class, 'category'], $rows);
    }

    /**
     * GET /item/author —— 作者列表
     *
     * ⚠️ 源站实测排序为 id **降序**（闹翻天 2109922931380424706 在臻海艺术之前）。
     */
    public function author()
    {
        $rows = ItemAuthor::order('id', 'desc')->select()->toArray();
        return array_map([Transformer::class, 'author'], $rows);
    }

    /**
     * GET /item/copyright —— 版权列表
     *
     * ⚠️ 源站实测：即使传了 iid 也返回**全局 6 条**，不按 iid 过滤。
     * 这里保持与源站一致 —— iid 参数被忽略。
     */
    public function copyright()
    {
        $rows = ItemCopyright::order('sort', 'asc')->order('id', 'asc')->select()->toArray();
        return array_map([Transformer::class, 'copyright'], $rows);
    }

    /**
     * GET /item/merge —— 合成配方列表
     *
     * ⚠️ 源站实测：merges / needs 恒为 **null**，列表接口不加载子表。
     */
    public function mergeList()
    {
        $rows = NftMergeRecipe::order('id', 'asc')->select()->toArray();
        // 第二三个参数传 null → 输出 null，复刻源站行为
        return array_map(
            fn($r) => Transformer::mergeRecipe($r, null, null),
            $rows
        );
    }

    /**
     * GET /item/merge/{id} —— 合成配方详情
     *
     * 源站实测：只有这个接口才返回真实的 merges / needs 子数组。
     */
    public function mergeDetail(string $id)
    {
        $recipe = NftMergeRecipe::find($id);
        if ($recipe === null) {
            return \app\exception\ApiException::notFound();
        }
        $recipe = $recipe->toArray();

        $uid = $this->request->auth_uid ?? null;

        $merges = NftMergeProduce::where('merge_id', $id)
            ->order('id', 'asc')->select()->toArray();
        $needs = NftMergeNeed::where('merge_id', $id)
            ->order('id', 'asc')->select()->toArray();

        // ownQuantity 是动态值，实时算
        foreach ($merges as &$m) { $m['own_quantity'] = $this->ownNftQuantity($m['iid'], $uid); }
        foreach ($needs  as &$n) { $n['own_quantity'] = $this->ownNftQuantity($n['iid'], $uid); }

        return Transformer::mergeRecipe(
            $recipe,
            array_map([Transformer::class, 'mergeSubItem'], $merges),
            array_map([Transformer::class, 'mergeSubItem'], $needs)
        );
    }

    /**
     * GET /item/{iid} —— 藏品详情
     *
     * 源站实测返回 35 个字段，其中 category 和 author 是**嵌套对象**
     *（源站没有独立的 /item/{iid}/author 端点，那个端点返回 10203）。
     */
    public function detail(string $iid)
    {
        $item = ItemModel::where('iid', $iid)->where('is_deleted', 0)->find();
        if ($item === null) {
            throw \app\exception\ApiException::notFound();
        }
        $row = $item->toArray();

        $uid = $this->request->auth_uid ?? null;

        $out = Transformer::item(
            $row,
            $this->ownNftQuantity($iid, $uid),
            $this->isFollow($iid, $uid)
        );

        // 嵌套：category
        if (!empty($row['cid'])) {
            $cat = ItemCategory::where('cid', $row['cid'])->find();
            if ($cat !== null) {
                $c = $cat->toArray();
                $out['category'] = [
                    'cid'  => Transformer::id($c['cid']),
                    'name' => (string)$c['name'],
                    'sort' => (int)$c['sort'],
                ];
            }
        }

        // 嵌套：author
        if (!empty($row['author_id'])) {
            $au = ItemAuthor::find($row['author_id']);
            if ($au !== null) {
                $out['author'] = Transformer::author($au->toArray());
            }
        }

        return $out;
    }

    /* ---------- 未上线端点（源站本身返回 10203，这里保持一致） ---------- */

    /** GET /product/tp_category —— 源站 10203 */
    public function tpCategory()
    {
        return \app\exception\ApiException::notFound();
    }

    /** GET /product/tp_list —— 源站 10203 */
    public function tpList()
    {
        return \app\exception\ApiException::notFound();
    }

    /* ---------- 关注 ---------- */

    /** POST /item/follow/{iid} */
    public function follow(string $iid)
    {
        $uid = $this->uid();
        $exists = ItemFollow::where('uid', $uid)->where('iid', $iid)->find();
        if ($exists !== null) {
            return ['isFollow' => true];
        }
        ItemFollow::create([
            'id'   => \app\helper\Snowflake::nextIdStr(),
            'uid'  => $uid,
            'iid'  => $iid,
        ]);
        return ['isFollow' => true];
    }

    /** DELETE /item/unfollow/{iid} */
    public function unfollow(string $iid)
    {
        $uid = $this->uid();
        ItemFollow::where('uid', $uid)->where('iid', $iid)->delete();
        return ['isFollow' => false];
    }

    /* ---------- 内部辅助 ---------- */

    /** 某用户持有该藏品的 NFT 数 */
    private function ownQuantityOf(array $item, ?string $uid): int
    {
        if ($uid === null || empty($item['iid'])) {
            return 0;
        }
        return (int)Nft::where('uid', $uid)->where('iid', $item['iid'])->count();
    }

    /** 某用户持有某 iid 的 NFT 数（合成页 ownQuantity） */
    private function ownNftQuantity(string $iid, ?string $uid): int
    {
        if ($uid === null || $iid === '') {
            return 0;
        }
        return (int)Nft::where('uid', $uid)->where('iid', $iid)->count();
    }

    /** 是否已关注该藏品 */
    private function isFollow(string $iid, ?string $uid): bool
    {
        if ($uid === null || $iid === '') {
            return false;
        }
        return ItemFollow::where('uid', $uid)->where('iid', $iid)->count() > 0;
    }
}
