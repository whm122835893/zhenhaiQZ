<?php
// +----------------------------------------------------------------------
// | 路由设置
// +----------------------------------------------------------------------

return [
    // pathinfo分隔符
    'pathinfo_depr'         => '/',
    // 是否开启路由延迟解析
    'url_lazy_route'        => false,
    // 是否强制使用路由
    'url_route_must'        => false,
    // 是否区分大小写
    'url_case_sensitive'    => false,
    // 自动扫描子目录分组
    'route_auto_group'      => false,
    // 合并路由规则
    'route_rule_merge'      => false,
    // 路由是否完全匹配
    // ⚠️ 必须为 true：源站的 item/category 与 item/{iid}、
    // nft/secondary/sell 与 nft/secondary/{iid} 都是"完整路径才匹配"，
    // 非完全匹配时 /nft/secondary/sell 会被 /nft/secondary 规则吃掉
    // （前缀匹配），导致所有二级市场子端点都返回同一个列表。
    'route_complete_match'  => true,
    // 去除斜杠
    'remove_slash'          => false,
    // 默认的路由变量规则
    'default_route_pattern' => '[\w\.]+',
    // URL伪静态后缀
    // ⚠️ 本项目是纯 API，不启用 html 后缀，
    // 否则 index.html 这类请求行为会与预期不符
    'url_html_suffix'       => '',
    // 访问控制器层名称
    'controller_layer'      => 'controller',
    // 空控制器名
    'empty_controller'      => 'Error',
    // 是否使用控制器后缀
    'controller_suffix'     => false,
    // 默认模块名（开启自动多模块有效）
    'default_module'        => 'index',
    // 默认控制器名
    'default_controller'    => 'Index',
    // 默认操作名
    'default_action'        => 'index',
    // 操作方法后缀
    'action_suffix'         => '',
    // 非路由变量是否使用普通参数方式（用于URL生成）
    'url_common_param'      => true,
    // 操作方法的参数绑定方式 route get param
    'action_bind_param'     => 'get',
    // 请求缓存规则
    // ⚠️ 必须为 false：API 接口绝不能缓存，
    // 否则同一个 URI 的不同请求会命中旧响应
    'request_cache_key'     => false,
    // 请求缓存有效期
    'request_cache_expire'  => null,
    // 全局请求缓存排除规则
    'request_cache_except'  => [],
    // 请求缓存的Tag
    'request_cache_tag'     => '',
    // API版本header变量
    'api_version'           => 'Api-Version',
];
