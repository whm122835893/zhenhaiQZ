<?php
// 应用路由定义
//
// 严格对齐源站 api.shengtai.cyou 的路径与方法（见 docs/api.md 第 10 章，共 93 个接口）。
//
// 分组约定：
//   - 一、无需登录
//   - 二、需登录（挂 AuthMiddleware）
//   - 三、Route::miss 兜底，返回 10203
//
// 统一响应包络由 app/middleware.php 里的 ResponseMiddleware 负责（全局中间件），
// 它位于最外层，能把控制器抛出的 ApiException 也包成源站格式。

use think\facade\Route;

/* ========================================================================
 * 一、无需登录
 * ===================================================================== */
Route::group(function () {

    /* ---------- 账号与鉴权 ---------- */
    Route::get('passport/captcha/image', 'Passport/captchaImage');
    Route::post('passport/captcha/sms', 'Passport/captchaSms');
    Route::post('passport/signup', 'Passport/signup');
    Route::post('passport/signin', 'Passport/signin');
    Route::post('passport/signin/quick', 'Passport/signinQuick');
    Route::post('passport/signin/oneClick', 'Passport/signinOneClick');

    /* ---------- 藏品目录 ---------- */
    Route::get('item', 'Item/index');
    Route::get('item/category', 'Item/category');
    Route::get('item/author', 'Item/author');
    Route::get('item/copyright', 'Item/copyright');
    Route::get('item/merge', 'Item/mergeList');
    Route::get('item/merge/:id', 'Item/mergeDetail');
    // 源站正确用法是把 iid 拼在路径上（GET /item/detail 恒报 10101，是源站自身缺陷）
    Route::get('item/:iid', 'Item/detail');
    Route::get('product/tp_category', 'Item/tpCategory');
    Route::get('product/tp_list', 'Item/tpList');

    /* ---------- NFT 二级市场 ---------- */
    Route::get('nft/secondary', 'Nft/secondary');
    // ⚠️ 顺序要求：静态段必须排在 :iid 动态段之前。
    // ThinkPHP 路由是「首匹配即命中」，若 :iid 在前，
    // /nft/secondary/sell 会被 /nft/secondary/:iid 抢先匹配，
    // 把 "sell" 当成 iid 传给详情接口。
    Route::get('nft/secondary/sell', 'Nft/secondarySell');
    Route::get('nft/secondary/sold', 'Nft/secondarySold');
    // 关注列表需登录，放在鉴权组；:iid 已约束 \d+，不存在抢匹配
    Route::get('nft/secondary/apply', 'Nft/secondaryApply');
    Route::get('nft/secondary/trust', 'Nft/secondaryTrust');
    // iid 是雪花 ID（纯数字），用 \d+ 约束后，
    // 静态段 follow/sell/sold/apply/trust 无论写在前后都不会被误匹配。
    Route::get('nft/secondary/:iid', 'Nft/secondaryDetail')->pattern(['iid' => '\d+']);
    Route::get('nft/digitalAlbum/calendar', 'Nft/digitalAlbumCalendar');

    /* ---------- 基础内容 ---------- */
    Route::get('basic/banner', 'Basic/banner');
    Route::get('basic/configs', 'Basic/configs');
    Route::get('basic/notice', 'Basic/noticeList');
    Route::get('basic/notice/category', 'Basic/noticeCategory');
    Route::get('basic/notice/:id', 'Basic/noticeDetail');
    Route::get('common/rule', 'Basic/rule');
    Route::get('app/version/check', 'Basic/versionCheck');

    /* ---------- 活动（只读） ---------- */
    Route::get('activity/index', 'Activity/index');
    Route::get('activity/lottery/active', 'Activity/lotteryActive');
    Route::get('activity/lottery', 'Activity/lotteryList');
    Route::get('activity/lottery/:id', 'Activity/lotteryDetail');
    Route::get('activity/lottery/log', 'Activity/lotteryLog');
    Route::get('activity/recast/type', 'Activity/recastType');
    Route::get('activity/recast/doll-type', 'Activity/recastDollType');
    Route::get('activity/recast/draw-num', 'Activity/recastDrawNum');
    Route::get('activity/recast/prize', 'Activity/recastPrize');
    Route::get('activity/recast/log', 'Activity/recastLog');
    Route::get('product_merge', 'Activity/mergeActivityList');
    Route::get('product_merge/record', 'Activity/mergeRecord');

    /* ---------- IM（公开群列表） ---------- */
    Route::get('im/group', 'Im/groupList');

});

/* ========================================================================
 * 二、需登录
 * ===================================================================== */
Route::group(function () {

    /* ---------- 账号 ---------- */
    Route::post('passport/signout', 'Passport/signout');
    Route::post('passport/delete', 'Passport/delete');
    Route::get('passport/account', 'Passport/account');
    Route::put('passport/password', 'Passport/updatePassword');
    Route::put('passport/phone', 'Passport/updatePhone');
    Route::put('passport/secondPassword', 'Passport/updateSecondPassword');
    Route::post('passport/realName', 'Passport/realName');
    Route::get('passport/online', 'Passport/online');

    /* ---------- 用户 ---------- */
    Route::get('user/profile', 'User/profile');
    Route::post('user/profile/avatar', 'User/updateAvatar');
    Route::get('nft/secondary/follow', 'Nft/secondaryFollow');
    Route::put('user/profile/nickname', 'User/updateNickname');
    Route::put('user/profile/introduction', 'User/updateIntroduction');
    Route::get('user/invite', 'User/invite');
    Route::get('user/resource', 'User/resource');

    /* ---------- 关注 ---------- */
    Route::post('item/follow/:iid', 'Item/follow');
    Route::delete('item/unfollow/:iid', 'Item/unfollow');

    /* ---------- NFT ---------- */
    Route::get('nft', 'Nft/index');
    Route::get('nft/group', 'Nft/group');
    Route::get('nft/:nftId', 'Nft/detail');
    Route::post('nft/box/open', 'Nft/openBox');
    Route::post('nft/sell/up', 'Nft/sellUp');
    Route::post('nft/sell/up/batch', 'Nft/sellUpBatch');
    Route::put('nft/sell/down', 'Nft/sellDown');
    Route::get('nft/sell/sold', 'Nft/sellSold');
    Route::post('nft/sell/apply', 'Nft/sellApply');

    /* ---------- 订单 ---------- */
    Route::post('order/primary', 'Order/primary');
    Route::post('order/secondary', 'Order/secondary');
    Route::post('order/secondary/quick', 'Order/secondaryQuick');
    Route::post('order/secondary/batch', 'Order/secondaryBatch');
    Route::post('order/secondary/apply', 'Order/secondaryApply');
    Route::post('order/secondary/trust', 'Order/secondaryTrust');
    Route::get('order', 'Order/index');
    Route::get('order/:id', 'Order/detail');
    Route::put('order/cancel', 'Order/cancel');
    Route::post('order/pay/hfpay', 'Order/payHfpay');
    Route::post('order/pay/yeepay', 'Order/payYeepay');

    /* ---------- 活动（写） ---------- */
    Route::get('activity/sign', 'Activity/signStatus');
    Route::post('activity/sign', 'Activity/sign');
    Route::get('activity/points/balance', 'Activity/pointsBalance');
    Route::post('activity/lottery/start/:id', 'Activity/lotteryStart');
    Route::post('activity/recast/draw', 'Activity/recastDraw');
    Route::post('nft/merge/v1', 'Nft/merge');
    Route::post('ai/answer/get_answer', 'Ai/answer');

    /* ---------- 定向发放（C 端只读） ---------- */
    Route::get('grant/my', 'Grant/my');

    /* ---------- 钱包充值 ---------- */
    Route::get('wallet/hfpay', 'Wallet/hfpay');
    Route::get('wallet/yeepay', 'Wallet/yeepay');

    /* ---------- IM ---------- */
    Route::post('im/group/join/:id', 'Im/join');
    Route::post('im/group/leave/:id', 'Im/leave');
    Route::get('im/group/message', 'Im/messages');
    Route::get('im/group/user/:gid', 'Im/members');
    // 源站 im 分包 getUser：聊天页按 uid 拉昵称（前端只依赖 nickname）
    Route::get('im/user/:uid', 'Im/user');

})->middleware(\app\middleware\AuthMiddleware::class);

/* ========================================================================
 * 三、兜底 —— 与源站一致返回 10203
 *
 * 注意：这里直接返回 json() 而不是抛 ApiException。
 * Route::miss 闭包抛出的异常会被框架的路由层捕获并降级处理，
 * 只有直接构造响应才能稳定拿到 10203。
 * ===================================================================== */
Route::miss(function () {
    return json([
        'status'  => false,
        'code'    => \app\exception\ApiException::CODE_NOT_FOUND,
        'message' => '未找到',
        'data'    => null,
    ]);
});