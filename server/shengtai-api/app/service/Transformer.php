<?php
declare(strict_types=1);

namespace app\service;

/**
 * 数据转换服务 —— 把数据库行转成源站响应格式
 *
 * 这是复刻的**核心层**。源站的字段命名、单位、类型都有自己的一套规矩，
 * 全部集中在这里收口，控制器只负责取数 + 调用这里。
 *
 * 三条铁律（都是实测踩出来的）：
 *
 *  1. **ID 一律转字符串** —— 源站 19 位雪花 ID 超 JS Number 安全范围
 *  2. **时间一律转字符串** —— 源站返回 "1791959018537"，是字符串不是数字
 *  3. **图片一律双字段** —— image（相对）+ imageUrl（绝对）
 */
final class Transformer
{
    /** 资源绝对地址前缀
 *
     *  ⚠️ 配置在 config/shengtai.php 的 'shengtai' 子数组里，
     *  所以完整路径是 shengtai.shengtai.media_base（两层 shengtai）。
     *  写成 config('shengtai.media_base') 会拿到 null，
     *  结果 imageUrl 变成 "/media/xxx.png" —— 缺域名，前端全变破图。
     */
    public static function mediaBase(): string
    {
        $base = (string)config('shengtai.shengtai.media_base', '');

        // 兜底：配置读不到时用源站域名，至少不会产出相对路径
        if ($base === '') {
            $base = 'https://h5.shengtai.cyou/';
        }

        return rtrim($base, '/') . '/';
    }

    /** 雪花 ID → 字符串 */
    public static function id(mixed $v): string
    {
        return (string)$v;
    }

    /** 13 位毫秒 → 字符串（源站就是字符串） */
    public static function ts(mixed $v): string
    {
        return (string)(int)$v;
    }

    /** 库里的 datetime → 13 位毫秒字符串 */
    public static function datetimeTs(mixed $datetime): string
    {
        $raw = (string)$datetime;
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '0';
        }
        $ts = strtotime($raw);
        return $ts === false ? '0' : (string)($ts * 1000);
    }

    /**
     * 补全图片双字段
     *
     * 源站：image = "media/xxx.png"，imageUrl = "https://.../media/xxx.png"
     */
    /**
     * 图片双字段 —— **平铺**（返回两个字段名，不是嵌套对象）
     *
     * ⚠️ 源站有两种写法，别混：
     *   A. 平铺（绝大多数端点）：{ image: "media/x.png", imageUrl: "https://.../media/x.png" }
     *      实测端点：/nft/secondary、/basic/notice 的 items[]、
     *                /item/merge 的 cover/coverUrl
     *   B. 嵌套：{ image: { image, imageUrl } }  ← 本项目踩过这个坑
     *
     * 用错会怎样：
     *   前端 `<img :src="item.image">` 拿到对象 → src 变成 "[object Object]"，
     *   页面全是破图；而 `item.image.image` 才是路径。
     *   所以默认一律用本方法的**平铺**版。
     *
     * @param string $field 目标字段名（image / cover / avatar）
     * @return array{0:string,1:string} [相对路径, 绝对URL]
     */
    public static function imagePair(string $relative, string $field = 'image'): array
    {
        $relative = (string)$relative;
        return [
            $field     => $relative,
            $field . 'Url' => $relative === '' ? '' : self::mediaBase() . $relative,
        ];
    }

    /**
     * 图片双字段 —— **嵌套**（返回 {field: {field, fieldUrl}}）
     *
     * 仅在确认源站该端点就是嵌套结构时使用。
     * 目前项目内**没有**这种端点，保留仅为对照排查用。
     *
     * @see imagePair() 说明了为什么默认不该用这个
     */
    public static function image(string $relative): array
    {
        return [
            'image'    => $relative,
            'imageUrl' => $relative === '' ? '' : self::mediaBase() . $relative,
        ];
    }

    /**
     * 金额：库里存的是分，原样返回
     *
     * 源站实测：secondaryHighPrice=500 表示 5 元。
     * 前端自己 /100 显示元，后端不做换算（换算了就和源站不一致）。
     */
    public static function amount(mixed $cents): int
    {
        return (int)$cents;
    }

    /**
     * 可空统计字段
     *
     * 实测：源站 priceChange / minPrice / tradeAmount / hourTradeQuantity /
     * todayTradeMax(Min)Amount / lastTradeAmount 恒为 null，不能填 0。
     */
    public static function nullable(mixed $v): mixed
    {
        return $v === null ? null : $v;
    }

    /* ================================================================
     * 各实体的转换（严格按源站实测字段）
     * ================================================================ */

    /**
     * NFT 对象
     *
     * 源站 GET /nft 实测：
     *   payWay 可为 null、item/owner 在列表接口为 null、isSelf 在列表接口为 null
     */
    public static function nft(array $row): array
    {
        return [
            'nftId'         => self::id($row['id'] ?? ''),
            'uid'           => self::id($row['uid'] ?? ''),
            'iid'           => self::id($row['iid'] ?? ''),
            'name'          => (string)($row['name'] ?? ''),
            'image'         => (string)($row['image'] ?? ''),
            'imageUrl'      => (string)($row['image_url'] ?? ''),
            'token'         => (int)($row['token'] ?? 0),
            'status'        => (int)($row['status'] ?? 0),
            'getTime'       => self::ts($row['get_time'] ?? 0),
            'price'         => (int)($row['price'] ?? 0),
            'buyPrice'      => (int)($row['buy_price'] ?? 0),
            'payLock'       => (bool)($row['pay_lock'] ?? false),
            // 源站实测可返回 null，不要转 0
            'payWay'        => $row['pay_way'] === null ? null : (int)$row['pay_way'],
            'chainId'       => (string)($row['chain_id'] ?? ''),
            'chainCompleted'=> (bool)($row['chain_completed'] ?? false),
        ];
    }

    /**
     * 藏品对象（item）
     *
     * 源站 GET /item/{iid} 实测 35 个字段。
     * ownQuantity / isFollow / buyTime 是**用户维度动态值**，
     * 未登录时也要返回（0 / false / 时间），不能省略。
     */
    public static function item(array $row, int $ownQuantity = 0, bool $isFollow = false): array
    {
        return [
            'iid'                    => self::id($row['iid'] ?? ''),
            'cid'                    => self::id($row['cid'] ?? ''),
            'authorId'               => self::id($row['author_id'] ?? ''),
            'contract'               => (string)($row['contract'] ?? ''),
            'name'                   => (string)($row['name'] ?? ''),
            // ⚠️ image_url 是冗余列（应由 image 派生）。补种子/插数据时容易漏填，
            //    一旦为空前端 :src="" 直接破图。这里做兜底派生，与公告端点实测行为一致。
            'image'                  => (string)($row['image'] ?? ''),
            'imageUrl'               => (string)($row['image_url'] ?? '')
                ?: ((string)($row['image'] ?? '') === ''
                    ? ''
                    : self::mediaBase() . (string)$row['image']),
            // ⚠️ 源站 content 为 NULL 时原样返回 null（不是空串）。
            //    强转 (string) 会把 null 变 ''，破坏与源站的逐字段一致性。
            'content'                => $row['content'] ?? null,
            'price'                  => (int)$row['price'],
            'type'                   => (int)($row['type'] ?? 0),
            'issueQuantity'          => (int)($row['issue_quantity'] ?? 0),
            'buyQuantity'            => (int)($row['buy_quantity'] ?? 0),
            'preStartTime'           => self::ts($row['pre_start_time'] ?? 0),
            'preEndTime'             => self::ts($row['pre_end_time'] ?? 0),
            'startTime'              => self::ts($row['start_time'] ?? 0),
            'endTime'                => self::ts($row['end_time'] ?? 0),
            'boxOpenTime'            => self::ts($row['box_open_time'] ?? 0),
            'isPrimary'              => (bool)($row['is_primary'] ?? false),
            'isPrimaryApply'         => (bool)($row['is_primary_apply'] ?? false),
            'isRecommend'            => (bool)($row['is_recommend'] ?? false),
            'isPopular'              => (bool)($row['is_popular'] ?? false),
            'isGive'                 => (bool)($row['is_give'] ?? false),
            'isResale'               => (bool)($row['is_resale'] ?? false),
            'isSecondary'            => (bool)($row['is_secondary'] ?? false),
            'isSecondaryBatch'       => (bool)($row['is_secondary_batch'] ?? false),
            'isSecondaryApply'       => (bool)($row['is_secondary_apply'] ?? false),
            'isSecondaryTrust'       => (bool)($row['is_secondary_trust'] ?? false),
            'secondaryLowPrice'      => self::amount($row['secondary_low_price'] ?? 0),
            'secondaryHighPrice'     => self::amount($row['secondary_high_price'] ?? 0),
            'secondaryApplyLowPrice' => self::amount($row['secondary_apply_low_price'] ?? 0),
            'secondaryApplyHighPrice'=> self::amount($row['secondary_apply_high_price'] ?? 0),
            'isSoldOut'              => (bool)($row['is_sold_out'] ?? false),
            'isPre'                  => (bool)($row['is_pre'] ?? false),
            'nftQuantity'            => (int)($row['nft_quantity'] ?? 0),
            'ownQuantity'            => $ownQuantity,
            'isFollow'               => $isFollow,
            'buyTime'                => self::ts($row['start_time'] ?? 0),
        ];
    }

    /**
     * 二级市场藏品详情（比 item 多统计字段）
     *
     * 源站 GET /nft/secondary/{iid} 实测 31 字段。
     * 注意：6 个统计字段恒为 null，必须原样透传。
     */
    public static function secondaryItem(array $row): array
    {
        $out = self::item($row);
        $out += [
            'sellQuantity'         => (int)($row['sell_quantity'] ?? 0),
            'minPrice'             => self::nullable($row['min_price'] ?? null),
            'maxPrice'             => self::nullable($row['max_price'] ?? null),
            'tradeQuantity'        => (int)($row['trade_quantity'] ?? 0),
            'tradeAmount'          => self::nullable($row['trade_amount'] ?? null),
            'lastTradeAmount'      => self::nullable($row['last_trade_amount'] ?? null),
            'todayTradeQuantity'   => (int)($row['today_trade_quantity'] ?? 0),
            'hourTradeQuantity'    => self::nullable($row['hour_trade_quantity'] ?? null),
            'todayTradeAmount'     => self::nullable($row['today_trade_amount'] ?? null),
            'todayTradeMaxAmount'  => self::nullable($row['today_trade_max_amount'] ?? null),
            'todayTradeMinAmount'  => self::nullable($row['today_trade_min_amount'] ?? null),
            'priceChange'          => self::nullable($row['price_change'] ?? null),
        ];
        return $out;
    }

    /**
     * 订单对象
     *
     * ⚠️ 关键：**items 数组的填充方式由调用方决定**
     *   - GET /order（列表）→ items 恒为 []（源站实测就是空数组）
     *   - GET /order/{id}（详情）→ items 才填真实明细
     * 这个不对称是源站的固定行为，必须复刻。
     *
     * @param bool $withItems 是否填充明细
     * @param array $items 明细数据
     */
    public static function order(array $row, bool $withItems = false, array $items = []): array
    {
        return [
            'orderId'      => self::id($row['id'] ?? ''),
            'buyer'        => self::id($row['uid'] ?? ''),
            'type'         => (int)($row['order_type'] ?? 0),
            'iid'          => self::id($row['iid'] ?? ''),
            'title'        => (string)($row['title'] ?? ''),
            'image'        => (string)($row['image'] ?? ''),
            'imageUrl'     => (string)($row['image_url'] ?? ''),
            'unitPrice'    => (int)$row['unit_price'],
            'quantity'     => (int)($row['quantity'] ?? 1),
            'itemQuantity' => (int)($row['item_quantity'] ?? 1),
            'amount'       => (int)$row['amount'],
            // 源站实测这两个字段可为 null
            'subject'      => $row['subject'] ?? null,
            'outTradeNo'   => $row['out_trade_no'] ?? null,
            'payTime'      => self::ts($row['pay_time'] ?? 0) === '0' ? null : self::ts($row['pay_time']),
            'status'       => (int)($row['status'] ?? 0),
            'payWay'       => (int)($row['pay_way'] ?? 1),
            'isSnapUp'     => (bool)($row['is_snap_up'] ?? false),
            'createTime'   => self::ts($row['created_ts'] ?? 0),
            'endPayTime'   => self::ts($row['end_pay_time'] ?? 0),
            'items'        => $withItems ? $items : [],
        ];
    }

    /**
     * 订单明细项
     *
     * 源站 GET /order/{id} 的 items[] 实测：
     *   id / orderId / nftId / token / amount / createTime
     */
    public static function orderItem(array $row): array
    {
        return [
            'id'         => self::id($row['id'] ?? ''),
            'orderId'    => self::id($row['order_id'] ?? ''),
            'nftId'      => self::id($row['nft_id'] ?? ''),
            'token'      => (int)($row['token'] ?? 0),
            'amount'     => (int)($row['amount'] ?? 0),
            'createTime' => self::ts($row['created_ts'] ?? 0),
        ];
    }

    /**
     * 求购单 / 委托单
     *
     * ⚠️ 源站实测：**这两个端点都不返回 status 字段**。
     * 别自作主张加，源站没有就是没有。
     */
    public static function marketOrder(array $row): array
    {
        return [
            'orderId'           => self::id($row['id'] ?? ''),
            'iid'               => self::id($row['iid'] ?? ''),
            'title'             => (string)($row['title'] ?? ''),
            'image'             => (string)($row['image'] ?? ''),
            'imageUrl'          => (string)($row['image_url'] ?? ''),
            'unitPrice'         => (int)$row['unit_price'],
            'quantity'          => (int)($row['quantity'] ?? 0),
            'completedQuantity' => (int)($row['completed_quantity'] ?? 0),
            'amount'            => (int)$row['amount'],
            'payWay'            => (int)($row['pay_way'] ?? 1),
            'createTime'        => self::ts($row['created_ts'] ?? 0),
        ];
    }

    /**
     * 成交记录
     *
     * ⚠️ 源站 GET /nft/secondary/sold 实测**只有 5 个字段**：
     *   nftId / token / soldPrice / soldTime / chainId
     * 没有 seller/buyer/iid，别加。
     */
    public static function tradeLog(array $row): array
    {
        return [
            'nftId'     => self::id($row['nft_id'] ?? ''),
            'token'     => (int)($row['token'] ?? 0),
            'soldPrice' => (int)$row['sold_price'],
            'soldTime'  => self::ts($row['sold_time'] ?? 0),
            'chainId'   => (string)($row['chain_id'] ?? ''),
        ];
    }

    /**
     * 合成配方主表
     *
     * ⚠️ 关键：**merges / needs 的填充方式由调用方决定**
     *   - GET /item/merge（列表）      → merges/needs 恒为 **null**（源站实测）
     *   - GET /item/merge/{id}（详情） → 才返回真实产出/消耗
     *
     * @param array|null $merges 产出数组，null 表示不填
     * @param array|null $needs  消耗数组，null 表示不填
     */
    public static function mergeRecipe(array $row, ?array $merges = null, ?array $needs = null): array
    {
        return [
            'id'             => self::id($row['id'] ?? ''),
            'name'           => (string)($row['name'] ?? ''),
            'cover'          => (string)($row['cover'] ?? ''),
            'coverUrl'       => (string)($row['cover_url'] ?? ''),
            'content'        => $row['content'] ?? null,
            'type'           => (int)($row['type'] ?? 0),
            'mergeQuantity'  => (int)($row['merge_quantity'] ?? 1),
            'preStartTime'   => self::ts($row['pre_start_time'] ?? 0),
            'preEndTime'     => self::ts($row['pre_end_time'] ?? 0),
            'startTime'      => self::ts($row['start_time'] ?? 0),
            'endTime'        => self::ts($row['end_time'] ?? 0),
            'isPre'          => (bool)($row['is_pre'] ?? false),
            'mergeTime'      => self::ts($row['merge_time'] ?? 0),
            'merges'         => $merges,
            'needs'          => $needs,
        ];
    }

    /**
     * 合成产出物 / 消耗物
     *
     * ownQuantity 是**当前用户持有量**，动态值，DAO 层实时算。
     */
    public static function mergeSubItem(array $row): array
    {
        return [
            'id'          => self::id($row['id'] ?? ''),
            'mergeId'     => self::id($row['merge_id'] ?? ''),
            'iid'         => self::id($row['iid'] ?? ''),
            'quantity'    => (int)($row['quantity'] ?? 0),
            'name'        => (string)($row['name'] ?? ''),
            'image'       => (string)($row['image'] ?? ''),
            'imageUrl'    => (string)($row['image_url'] ?? ''),
            'ownQuantity' => (int)($row['own_quantity'] ?? 0),
        ];
    }

    /**
     * 公告
     *
     * 源站 GET /basic/notice 实测：每条带 items 数组（最多 3 条）。
     */
    public static function notice(array $row, array $items = []): array
    {
        return [
            'id'         => self::id($row['id'] ?? ''),
            'cid'        => self::id($row['cid'] ?? ''),
            'title'      => (string)($row['title'] ?? ''),
            'content'    => (string)($row['content'] ?? ''),
            'createTime' => self::ts($row['published_ts'] ?? 0),
            'category'   => $row['category'] ?? null,
            'items'      => $items,
        ];
    }

    /**
     * 公告分类
     */
    public static function noticeCategory(array $row): array
    {
        return [
            'cid'      => self::id($row['cid'] ?? ''),
            'name'     => (string)($row['name'] ?? ''),
            'cover'    => (string)($row['cover'] ?? ''),
            'coverUrl' => (string)($row['cover_url'] ?? ''),
            'sort'     => (int)($row['sort'] ?? 0),
        ];
    }

    /**
     * 在线会话
     *
     * 源站 GET /passport/online 实测 8 字段，注意有 isSelf（是否当前会话）。
     * ⚠️ 不返回 uid（源站没返回）。
     */
    public static function onlineSession(array $row): array
    {
        return [
            'token'      => (string)($row['token'] ?? ''),
            'createTime' => self::ts($row['login_at'] ?? 0),
            'deviceId'   => (string)($row['device_id'] ?? ''),
            'deviceModel'=> (string)($row['device_model'] ?? ''),
            'platform'   => (string)($row['platform'] ?? ''),
            'signinWay'  => (int)($row['signin_way'] ?? 0),
            'ip'         => (string)($row['ip'] ?? ''),
            'isSelf'     => (bool)($row['is_self'] ?? false),
        ];
    }

    /**
     * 仓库分组（GET /nft/group）
     */
    public static function nftGroup(array $row): array
    {
        return [
            'iid'          => self::id($row['iid'] ?? ''),
            'name'         => (string)($row['name'] ?? ''),
            'image'        => (string)($row['image'] ?? ''),
            'imageUrl'     => (string)($row['image_url'] ?? ''),
            'type'         => (string)($row['type'] ?? 'normal'),
            'ownQuantity'  => (int)($row['own_quantity'] ?? 0),
            'minPrice'     => self::nullable($row['min_price'] ?? null),
            'priceChange'  => self::nullable($row['price_change'] ?? null),
        ];
    }

    /**
     * 分类（GET /item/category）
     */
    public static function category(array $row): array
    {
        return [
            'cid'  => self::id($row['cid'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'sort' => (int)($row['sort'] ?? 0),
        ];
    }

    /**
     * 作者
     */
    public static function author(array $row): array
    {
        // ⚠️ 源站实测只有 5 个字段，不含 worksCount / followerCount。
        //    多发字段会让前端 v-bind 拿到意外值，也破坏像素级一致。
        return [
            'id'           => self::id($row['id'] ?? ''),
            'name'         => (string)($row['name'] ?? ''),
            'avatar'       => (string)($row['avatar'] ?? ''),
            'avatarUrl'    => (string)($row['avatar_url'] ?? ''),
            'introduction' => (string)($row['introduction'] ?? ''),
        ];
    }

    /**
     * 版权（源站全局，不按 iid 过滤）
     */
    public static function copyright(array $row): array
    {
        return [
            'id'       => self::id($row['id'] ?? ''),
            'name'     => (string)($row['name'] ?? ''),
            'image'    => (string)($row['image'] ?? ''),
            'imageUrl' => (string)($row['image_url'] ?? ''),
        ];
    }
}
