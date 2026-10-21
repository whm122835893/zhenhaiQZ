<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\helper\Snowflake;

/**
 * 藏品管理 —— GET /admin/items | GET :id | PUT :id | POST | GET meta
 *
 * 创建：iid/contract 由服务端生成，时间字段前端传 'YYYY-MM-DD HH:mm:ss'
 *      字符串，这里转毫秒时间戳落库（items.start_time 等为 bigint ms）。
 * 编辑：白名单字段，含二级市场开关组（is_secondary*）与限价区间 ——
 *      is_secondary 就是「单品寄售开关」，C 端 sellUp/sellUpBatch 会校验。
 */
class Items extends AdminBase
{
    public function index()
    {
        $q = \think\facade\Db::name('items')->where('is_deleted', 0)
            ->field('id,iid,cid,author_id,name,price,type,issue_quantity,buy_quantity,nft_quantity,start_time,end_time,is_recommend,is_popular,status,is_sold_out,is_secondary,is_secondary_batch,is_secondary_apply,is_secondary_trust,secondary_low_price,secondary_high_price,view_count,follower_count,image,image_url,created_at');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->whereLike('name', "%{$kw}%");
        }
        $cid = $this->int('cid', 0);
        if ($cid > 0) {
            $q->where('cid', $cid);
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r['id']        = (string)$r['id'];
            $r['iid']       = (string)$r['iid'];
            $r['price']     = (float)$r['price'];
            $r['imageUrl']  = (string)($r['image_url'] ?: $r['image']);
            unset($r['image'], $r['image_url']);
        }
        unset($r);
        return $data;
    }

    /** GET /admin/items/meta —— 分类 + 作者（创建/编辑表单下拉）；雪花 ID 一律转字符串防 JS 精度丢失 */
    public function meta()
    {
        $cats = \think\facade\Db::name('item_categories')
            ->where('status', 'enabled')
            ->field('cid,name')->order('sort', 'asc')->select()->toArray();
        foreach ($cats as &$c) {
            $c['cid'] = (string)$c['cid'];
        }
        unset($c);

        $authors = \think\facade\Db::name('item_authors')
            ->field('id,name')->order('id', 'desc')->select()->toArray();
        foreach ($authors as &$a) {
            $a['id'] = (string)$a['id'];
        }
        unset($a);

        return ['categories' => $cats, 'authors' => $authors];
    }

    public function detail()
    {
        $id = $this->needInt('id');
        $it = \think\facade\Db::name('items')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$it) {
            throw ApiException::notFound('藏品不存在');
        }
        $it['id']      = (string)$it['id'];
        $it['iid']     = (string)$it['iid'];
        $it['price']   = (float)$it['price'];
        $it['category']= \think\facade\Db::name('item_categories')->where('cid', (int)$it['cid'])->value('name') ?: '';
        $it['author']  = \think\facade\Db::name('item_authors')->where('id', (int)$it['author_id'])->value('name') ?: '';
        return $it;
    }

    /** POST /admin/items —— 创建藏品 */
    public function create()
    {
        $this->needWriter();
        $body = $this->body();

        $name = trim((string)($body['name'] ?? ''));
        $cid  = (string)($body['cid'] ?? '');
        $authorId = (string)($body['author_id'] ?? '');
        $image = trim((string)($body['image_url'] ?? ''));
        if ($name === '' || $cid === '' || $image === '') {
            throw ApiException::missing('name/cid/image_url');
        }

        $price = (float)($body['price'] ?? 0);
        if ($price <= 0) {
            throw new ApiException('价格必须大于 0', ApiException::CODE_PARAM_TYPE);
        }

        $issue = max(0, (int)($body['issue_quantity'] ?? 0));

        $iid = Snowflake::nextIdStr();
        $data = [
            'id'            => Snowflake::nextIdStr(),
            'iid'           => $iid,
            'cid'           => $cid,
            'author_id'     => $authorId !== '' ? $authorId : 0,
            'name'          => $name,
            'contract'      => '0x' . substr(md5($iid . microtime()), 0, 32),
            'image'         => $image,
            'image_url'     => $image,
            'content'       => (string)($body['content'] ?? ''),
            'price'         => $price,
            'type'          => '1',
            'issue_quantity'=> $issue,
            'nft_quantity'  => $issue,
            'start_time'    => $this->toMs($body['start_time'] ?? ''),
            'end_time'      => $this->toMs($body['end_time'] ?? ''),
            'is_primary'    => 1,
            'is_recommend'  => !empty($body['is_recommend']) ? 1 : 0,
            'is_popular'    => !empty($body['is_popular']) ? 1 : 0,
            'is_secondary'  => !empty($body['is_secondary']) ? 1 : 0,
            'is_secondary_batch' => !empty($body['is_secondary_batch']) ? 1 : 0,
            'is_secondary_apply' => !empty($body['is_secondary_apply']) ? 1 : 0,
            'is_secondary_trust' => !empty($body['is_secondary_trust']) ? 1 : 0,
            'secondary_low_price'  => (float)($body['secondary_low_price'] ?? 0),
            'secondary_high_price' => (float)($body['secondary_high_price'] ?? 0),
            'status'        => 'on',
        ];

        \think\facade\Db::name('items')->insert($data);
        $this->opLog('items', 'create', null, ['iid' => $iid, 'name' => $name], 'item:' . $iid);

        return ['id' => (string)$data['id'], 'iid' => $iid];
    }

    /** 编辑（白名单字段） */
    public function update()
    {
        $this->needWriter();
        $id = $this->needInt('id');
        $it = \think\facade\Db::name('items')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$it) {
            throw ApiException::notFound('藏品不存在');
        }

        $body  = $this->body();
        // int 组：0/1 开关；decimal 组：限价；其余原样
        $allow = ['name', 'price', 'issue_quantity', 'start_time', 'end_time', 'is_recommend', 'is_popular', 'status', 'is_sold_out',
                  'image_url', 'content', 'type',
                  'is_secondary', 'is_secondary_batch', 'is_secondary_apply', 'is_secondary_trust',
                  'secondary_low_price', 'secondary_high_price'];
        $data  = [];
        foreach ($allow as $f) {
            if (array_key_exists($f, $body)) {
                $data[$f] = match (true) {
                    in_array($f, ['is_recommend', 'is_popular', 'status', 'is_sold_out', 'is_secondary', 'is_secondary_batch', 'is_secondary_apply', 'is_secondary_trust'], true)
                        => $f === 'status' ? (string)$body[$f] : (int)$body[$f],
                    in_array($f, ['secondary_low_price', 'secondary_high_price', 'price'], true)
                        => (float)$body[$f],
                    $f === 'start_time' || $f === 'end_time'
                        => $this->toMs($body[$f]),
                    default => $body[$f],
                };
            }
        }
        if ($data === []) {
            throw ApiException::missing('可编辑字段');
        }

        // 换图时双字段同步
        if (isset($data['image_url'])) {
            $data['image'] = (string)$data['image_url'];
        }

        \think\facade\Db::name('items')->where('id', $id)->update($data);
        $this->opLog('items', 'update', $it, $data, 'item:' . $it['iid']);

        return ['id' => (string)$id, 'updated' => array_keys($data)];
    }

    /** datetime 字符串 → 毫秒时间戳（空值返回 null，保持列可空语义） */
    private function toMs(mixed $v): ?int
    {
        $s = trim((string)$v);
        if ($s === '') {
            return null;
        }
        $ts = strtotime($s);
        return $ts === false ? null : $ts * 1000;
    }
}
