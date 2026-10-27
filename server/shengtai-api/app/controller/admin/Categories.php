<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\helper\Snowflake;

/**
 * 藏品分类管理 —— C 端市场页分类 tab 与管理端藏品表单下拉共用 item_categories
 *
 * - 列表按 sort,id 升序；itemCount 统计引用该分类的未删藏品数
 * - 新增时 id 与 cid 同取雪花串（cid 是 items.cid 引用的业务键，唯一索引）
 * - 删除保护：被藏品引用的分类不可删，避免 C 端藏品失去归属
 */
class Categories extends AdminBase
{
    /** GET /admin/categories */
    public function index()
    {
        $rows = \think\facade\Db::name('item_categories')
            ->order('sort', 'asc')->order('id', 'asc')->select()->toArray();

        $counts = \think\facade\Db::name('items')
            ->where('is_deleted', 0)
            ->group('cid')
            ->column('COUNT(*) AS c', 'cid');

        $list = [];
        foreach ($rows as $r) {
            $cid = (string)$r['cid'];
            $list[] = [
                'id'        => (string)$r['id'],
                'cid'       => $cid,
                'name'      => (string)$r['name'],
                'sort'      => (int)$r['sort'],
                'status'    => (string)$r['status'],
                'itemCount' => (int)($counts[$cid] ?? 0),
            ];
        }
        return ['list' => $list];
    }

    /** POST /admin/categories —— {name, sort} */
    public function create()
    {
        $this->needWriter();
        $body = $this->body();
        $name = trim((string)($body['name'] ?? ''));
        $sort = (int)($body['sort'] ?? 0);
        if ($name === '') {
            throw ApiException::missing('name');
        }
        if (mb_strlen($name) > 16) {
            throw new ApiException('分类名称不能超过 16 个字', ApiException::CODE_PARAM_TYPE);
        }

        $id = Snowflake::nextIdStr();
        \think\facade\Db::name('item_categories')->insert([
            'id'     => $id,
            'cid'    => $id,
            'name'   => $name,
            'sort'   => $sort,
            'status' => 'enabled',
        ]);
        $this->opLog('category', 'create', null, ['name' => $name, 'sort' => $sort], 'category:' . $id);
        return ['id' => $id];
    }

    /** PUT /admin/categories/:id —— {name, sort} */
    public function update()
    {
        $this->needWriter();
        $id   = (string)$this->int('id', 0);
        $body = $this->body();

        $row = \think\facade\Db::name('item_categories')->where('id', $id)->find();
        if (!$row) {
            throw ApiException::notFound('分类不存在');
        }

        $fields = [];
        if (array_key_exists('name', $body)) {
            $name = trim((string)$body['name']);
            if ($name === '') {
                throw ApiException::missing('name');
            }
            if (mb_strlen($name) > 16) {
                throw new ApiException('分类名称不能超过 16 个字', ApiException::CODE_PARAM_TYPE);
            }
            $fields['name'] = $name;
        }
        if (isset($body['sort'])) {
            $fields['sort'] = (int)$body['sort'];
        }
        if (isset($body['status'])) {
            $status = $body['status'] === 'enabled' ? 'enabled' : 'disabled';
            $fields['status'] = $status;
        }
        if ($fields) {
            \think\facade\Db::name('item_categories')->where('id', $id)->update($fields);
            $this->opLog('category', 'update', $row, $fields, 'category:' . $id);
        }
        return ['id' => $id];
    }

    /** DELETE /admin/categories/:id —— 被藏品引用时拒绝 */
    public function delete()
    {
        $this->needWriter();
        $id = (string)$this->int('id', 0);

        $row = \think\facade\Db::name('item_categories')->where('id', $id)->find();
        if (!$row) {
            throw ApiException::notFound('分类不存在');
        }
        $used = \think\facade\Db::name('items')
            ->where('cid', (string)$row['cid'])
            ->where('is_deleted', 0)
            ->count();
        if ($used > 0) {
            throw new ApiException('该分类下还有 ' . $used . ' 个藏品，无法删除', ApiException::CODE_PARAM_TYPE);
        }
        \think\facade\Db::name('item_categories')->where('id', $id)->delete();
        $this->opLog('category', 'delete', $row, null, 'category:' . $id);
        return ['id' => $id];
    }
}
