<?php
declare(strict_types=1);

use app\middleware\AdminAuthMiddleware;
use think\facade\Route;

/**
 * 管理后台路由（/admin/*）
 *
 * 独立于 C 端：Bearer 管理员 JWT（AdminAuthMiddleware），响应包络沿用 {code:0,message,data}。
 * 写接口（POST/PUT/DELETE）在控制器内做角色校验（AdminBase::needWriter/needSuper）。
 */

Route::group('admin', function () {

    /* ---------- 认证（免登录） ---------- */
    Route::post('auth/login', 'admin/Auth/login');

    /* ---------- 以下全部需管理员登录 ---------- */
    Route::group(function () {

        Route::get('auth/me', 'admin/Auth/me');
        Route::post('auth/logout', 'admin/Auth/logout');
        Route::post('auth/password', 'admin/Auth/password');

        Route::get('dashboard', 'admin/Dashboard/index');

        Route::get('users', 'admin/Users/index');
        Route::get('users/:id', 'admin/Users/detail')->pattern(['id' => '\d+']);
        Route::post('users/:id/status', 'admin/Users/status')->pattern(['id' => '\d+']);

        Route::get('items', 'admin/Items/index');
        Route::get('items/meta', 'admin/Items/meta');
        Route::post('items', 'admin/Items/create');
        Route::get('items/:id', 'admin/Items/detail')->pattern(['id' => '\d+']);
        Route::put('items/:id', 'admin/Items/update')->pattern(['id' => '\d+']);

        Route::get('activity/lotteries', 'admin/Activity/lotteries');
        Route::post('activity/lotteries', 'admin/Activity/lotterySave');
        Route::put('activity/lotteries/:id', 'admin/Activity/lotterySave')->pattern(['id' => '\d+']);
        Route::delete('activity/lotteries/:id', 'admin/Activity/lotteryDelete')->pattern(['id' => '\d+']);
        Route::get('activity/merges', 'admin/Activity/merges');
        Route::post('activity/merges', 'admin/Activity/mergeSave');
        Route::put('activity/merges/:id', 'admin/Activity/mergeSave')->pattern(['id' => '\d+']);
        Route::delete('activity/merges/:id', 'admin/Activity/mergeDelete')->pattern(['id' => '\d+']);
        Route::get('activity/invites', 'admin/Activity/invites');
        Route::get('activity/reward-config', 'admin/Activity/rewardConfig');
        Route::put('activity/reward-config', 'admin/Activity/rewardConfigSave');

        Route::get('wallet/transactions', 'admin/Wallet/transactions');
        Route::get('wallet/summary', 'admin/Wallet/summary');

        Route::get('orders', 'admin/Orders/index');
        Route::get('orders/:id', 'admin/Orders/detail')->pattern(['id' => '\d+']);
        Route::post('orders/:id/cancel', 'admin/Orders/cancel')->pattern(['id' => '\d+']);
        Route::post('orders/:id/mark-paid', 'admin/Orders/markPaid')->pattern(['id' => '\d+']);

        Route::get('nfts', 'admin/Nfts/index');

        Route::get('grant/tasks', 'admin/Grant/index');
        Route::get('grant/tasks/:id', 'admin/Grant/detail')->pattern(['id' => '\d+']);
        Route::post('grant/task', 'admin/Grant/create');
        Route::put('grant/tasks/:id', 'admin/Grant/update')->pattern(['id' => '\d+']);
        Route::post('grant/tasks/:id/submit', 'admin/Grant/submit')->pattern(['id' => '\d+']);
        Route::post('grant/tasks/:id/audit', 'admin/Grant/audit')->pattern(['id' => '\d+']);
        Route::post('grant/tasks/:id/cancel', 'admin/Grant/cancel')->pattern(['id' => '\d+']);
        Route::post('grant/tasks/:id/execute', 'admin/Grant/execute')->pattern(['id' => '\d+']);
        Route::get('grant/tasks/:id/preview', 'admin/Grant/preview')->pattern(['id' => '\d+']);
        Route::get('grant/tasks/:id/records', 'admin/Grant/records')->pattern(['id' => '\d+']);
        Route::post('grant/tasks/:id/retry', 'admin/Grant/retry')->pattern(['id' => '\d+']);

        Route::get('market/item-groups', 'admin/Market/itemGroups');
        Route::get('market/sell-orders', 'admin/Market/sellOrders');
        Route::post('market/sell-orders/:id/off', 'admin/Market/off')->pattern(['id' => '\d+']);

        Route::post('upload/image', 'admin/Upload/image');

        Route::get('cms/notices', 'admin/Cms/notices');
        Route::get('cms/notices/:id', 'admin/Cms/noticeDetail')->pattern(['id' => '\d+']);
        Route::post('cms/notices', 'admin/Cms/noticeSave');
        Route::put('cms/notices/:id', 'admin/Cms/noticeSave')->pattern(['id' => '\d+']);
        Route::delete('cms/notices/:id', 'admin/Cms/noticeDelete')->pattern(['id' => '\d+']);
        Route::get('cms/notice-categories', 'admin/Cms/noticeCategories');
        Route::get('cms/banners', 'admin/Cms/banners');
        Route::put('cms/banners/:id', 'admin/Cms/bannerUpdate')->pattern(['id' => '\d+']);

        Route::get('system/configs', 'admin/System/configs');
        Route::put('system/configs', 'admin/System/configsSave');
        Route::get('system/logs/audit', 'admin/System/auditLogs');
        Route::get('system/logs/operation', 'admin/System/operationLogs');

    })->middleware(AdminAuthMiddleware::class);

});
