-- ============================================================
-- 源站真实数据装载脚本（验证表结构与源站响应字段一一对齐）
-- 数据来源：api-deep.json（30 页运行时抓取）
-- ============================================================
USE `shengtai`;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `notice_item_relations`;
TRUNCATE TABLE `notices`;
TRUNCATE TABLE `notice_categories`;
TRUNCATE TABLE `nfts`;
TRUNCATE TABLE `nft_groups`;
TRUNCATE TABLE `items`;
TRUNCATE TABLE `item_authors`;
TRUNCATE TABLE `item_categories`;
TRUNCATE TABLE `user_profiles`;
TRUNCATE TABLE `user_wallets`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- 分类（源站 GET /item/category）----------
INSERT INTO `item_categories` (`id`, `cid`, `name`, `sort`) VALUES
  (1, 2103038591555887106, '臻海', 0),
  (2, 2106316966198300674, '寄售公告', 1),
  (3, 2109916747625762818, '合成活动', 2),
  (4, 2109916572316438529, '发放通知', 3);

-- ---------- 作者（源站 GET /item/{iid}.author）----------
INSERT INTO `item_authors` (`id`, `name`, `avatar`, `avatar_url`, `introduction`, `works_count`, `follower_count`) VALUES
  (2109922931380424706, '闹翻天',
   'media/2109922767282475009.png',
   'https://h5.shengtai.cyou/media/2109922767282475009.png',
   '插画师、建筑师，曾任职中国国家画院-公共艺术中心研究员中国建筑设计研究院-主创设计师。', 2, 0),
  (2103038557930151938, '臻海艺术',
   'media/2103038524870647809.png',
   'https://h5.shengtai.cyou/media/2103038524870647809.png',
   '有着丰富的独立艺术品创作经验。', 1, 0);

-- ---------- 藏品（源站 GET /nft/secondary + GET /item/{iid}）----------
-- 实测时间：2026-10-19，抓取端点：GET /item/2109928427009183745
-- 【重要】源站**所有**金额字段单位统一为【分】，不存在混用。
--        price=388 即 3.88 元，secondaryHighPrice=500 即 5.00 元，
--        本地一律原样存分，DAO 层不换算，前端÷100。
--        旧注释曾误称"price 单位为元、切勿统一除 100"，该结论已撤销（OPEN-01）。
INSERT INTO `items` (
  `id`, `iid`, `cid`, `author_id`, `name`, `contract`, `image`, `image_url`, `content`,
  `price`, `type`, `issue_quantity`, `buy_quantity`, `nft_quantity`,
  `pre_start_time`, `pre_end_time`, `start_time`, `end_time`, `is_pre`, `is_sold_out`,
  `is_primary`, `is_primary_apply`, `is_recommend`, `is_popular`, `is_give`, `is_resale`,
  `is_secondary`, `is_secondary_batch`, `is_secondary_apply`, `is_secondary_trust`,
  `secondary_low_price`, `secondary_high_price`,
  `secondary_apply_low_price`, `secondary_apply_high_price`,
  `sell_quantity`, `trade_quantity`, `last_trade_amount`,
  `today_trade_quantity`, `today_trade_amount`, `status`
) VALUES (
  1, 2109928427009183745, 2103038591555887106, 2103038557930151938,
  '臻海蓝卡', '0xd2de6137500648aa98300ac8f947457a',
  'media/2109928079460765697.png',
  'https://h5.shengtai.cyou/media/2109928079460765697.png',
  '通往财富大门的钥匙',
  388.00, '1', 888888, 100, 171409,
  0, 0,
  1792067400000, 1792078800000, 0, 1,
  0, 0, 0, 0, 0, 0,
  1, 1, 0, 1,
  0.00, 500.00,
  0.00, 0.00,
  14713, 292813, 5.00,
  0, 8272.00, 'sold_out'
),
(
  2, 2111310949005721602, 2103038591555887106, 2109922931380424706,
  '八仙过海·韩湘子', '0x179c6ceabcdc463793e1b76f160a356b',
  'media/2111019767830740994.jpg',
  'https://h5.shengtai.cyou/media/2111019767830740994.jpg',
  '',
  9900.00, '1', 2500, 1, 1481,
  0, 0,
  0, 0, 0, 1,
  0, 0, 0, 0, 0, 0,
  1, 0, 1, 1,
  0.00, 100000.00,
  100000.00, 120000.00,
  0, 590, 1000.00,
  12, 12570.00, 'sold_out'
),
(
  3, 2109922045660860418, 2103038591555887106, 2109922931380424706,
  '八仙过海·蓝采和', '0x101dc4141f114173af92d233e0e8994d',
  'media/2109921517002395649.jpg',
  'https://h5.shengtai.cyou/media/2109921517002395649.jpg',
  NULL,
  6990.00, '1', 5000, 1, 2016,
  1792054800000, 1792056600000,
  1792060200000, 1792063800000, 0, 1,
  0, 0, 0, 0, 0, 0,
  1, 1, 1, 0,
  0.00, 50000.00,
  40000.00, 50000.00,
  0, 2369, 500.00,
  0, 1000.00, 'on'
);

-- ---------- NFT 资产（源站 GET /nft/{nftId} + GET /nft/group）----------
INSERT INTO `nfts` (
  `id`, `nft_id`, `uid`, `iid`, `name`, `image`, `image_url`, `token`,
  `status`, `get_time`, `price`, `buy_price`, `pay_lock`, `pay_way`,
  `chain_id`, `chain_completed`, `sell_status`
) VALUES (
  1, 2111134550152249345, 2110255189743443969, 2109928427009183745,
  '臻海蓝卡',
  'media/2109928079460765697.png',
  'https://h5.shengtai.cyou/media/2109928079460765697.png',
  '564851',
  '0', 1792332674000, 0.00, 0.00, 0, NULL,
  '0xa1479742e3bb4b9699c35c7eee51070d', 1, 'none'
);

-- ---------- 仓库分组聚合（源站 GET /nft/group）----------
INSERT INTO `nft_groups` (`id`, `iid`, `name`, `image`, `image_url`, `type`, `own_quantity`, `min_price`, `price_change`) VALUES
  (1, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png',
   '1', 1, 0.00, 0.0000);

-- ---------- 用户（源站 GET /passport/account + GET /user/profile）----------
INSERT INTO `users` (
  `id`, `phone`, `email`, `account`, `password_hash`, `has_password`,
  `has_second_password`, `has_real_name`, `real_name`, `id_card`, `status`, `last_login_at`
) VALUES (
  2110255189743443969, '17587881293', '', '17587881293', '', 1,
  0, 1, '王*绵', '5202****9838', 'normal', 1792214400000
);

INSERT INTO `user_profiles` (
  `id`, `uid`, `nickname`, `avatar`, `avatar_url`, `sex`, `birthday`,
  `country`, `region_code`, `introduction`, `chain_address`,
  `invite_code`, `inviter`, `token`
) VALUES (
  1, 2110255189743443969, 'cFImFe', '', '', 0, NULL,
  '', '', '', '0x47a8b8a3eae24124a9375087a216fd6a',
  '10K9BP', 0, 'fda1b88d59f8409a807a0138da001005'
);

INSERT INTO `user_wallets` (`id`, `uid`, `balance`, `frozen_balance`, `currency`) VALUES
  (1, 2110255189743443969, 0.00, 0.00, 'CNY');

-- ---------- 公告（源站 GET /basic/notice，实抓 9 条）----------
-- 源站 GET /basic/notice/category 实抓5条（含封面图，sort 多条为1）
INSERT INTO `notice_categories` (`id`, `cid`, `name`, `cover`, `cover_url`, `sort`) VALUES
  (1, 2104397579064586241, '限价公告','media/2110255538370572290.png','https://h5.shengtai.cyou/media/2110255538370572290.png', 0),
  (2, 2106316966198300674, '寄售公告','media/2110255500084965378.png','https://h5.shengtai.cyou/media/2110255500084965378.png', 1),
  (3, 2109916572316438529, '运营公告','media/2110255584302395394.png','https://h5.shengtai.cyou/media/2110255584302395394.png', 1),
  (4, 2109916747625762818, '活动公告','media/2110255461090521090.png','https://h5.shengtai.cyou/media/2110255461090521090.png', 1),
  (5, 2109916773097771010, '空投公告','media/2110257400981286913.jpg','https://h5.shengtai.cyou/media/2110257400981286913.jpg', 1);

INSERT INTO `notices` (`id`, `cid`, `title`, `content`, `view_count`, `is_top`, `published_ts`) VALUES
  (2111422577789435906, 2106316966198300674,
   '《八仙过海·韩湘子》开启求购功能!',
   '<p style="text-align: left;"><strong>尊敬的臻海艺术用户:</strong></p><p style="text-align: left;"><strong>您好!</strong></p><p style="text-align: left;"><br></p><p style="text-align: left;"><span style="color: rgb(207, 19, 34);"><strong>《八仙过海·韩湘子》自公告发出后将开启求购功能，求购限价设定为1200元，</strong></span><span style="color: rgb(0, 0, 0);"><strong>需要收藏或寄售的用户可前往交易。</strong></span></p><p><br></p><p style="text-align: right;"><strong>敬请各位用户知悉，谢谢！</strong></p><p style="text-align: left;"><strong>臻海艺术</strong></p><p style="text-align: left;"><strong>2026年10月17日</strong></p>',
   0, 0, 1792218145000),
  (2111417341762928642, 2109916747625762818,
   '全新藏品《八仙过海·张果老》《张果老·纸驴云符》限量合成活动',
   '', 0, 1, 1792208112000),
  (2111335862911434754, 2106316966198300674,
   '藏品《八仙过海·韩湘子》开启寄售通知！', '', 0, 0, 1792146682000),
  (2111308128952844290, 2109916747625762818,
   '《批量购买资格卡》常驻合成活动来袭！', '', 0, 0, 1792116760000),
  (2111304618949541890, 2106316966198300674,
   '《八仙过海·蓝采和》开启求购功能！', '', 0, 0, 1792114863000),
  (2111253678766751746, 2109916572316438529,
   '臻海艺术注册藏品《臻海蓝卡》发放通知！', '', 0, 0, 1792066477000),
  (2111026900055949314, 2109916747625762818,
   '八仙过海，仙乐再临，韩湘子携紫金箫踏云而来。全新藏品《八仙过海·韩湘子》', '', 0, 0, 1791962940000),
  (2110783333899268098, 2106316966198300674,
   '藏品《八仙过海·蓝采和》《臻海蓝卡》开启寄售通知！', '', 0, 0, 1791882499000),
  (2109916146355507201, 2109916572316438529,
   '尽揽东方幻象，焕启臻海华章', '', 0, 0, 1791604520000);

-- ---------- 公告-藏品关联（源站 notice.items 数组，实抓 13 条）----------
INSERT INTO `notice_item_relations` (`id`, `notice_id`, `iid`, `name`, `image`, `image_url`, `is_secondary`, `sort`) VALUES
  (1,  2111422577789435906, 2111310949005721602, '八仙过海·韩湘子',
   'media/2111019767830740994.jpg',
   'https://h5.shengtai.cyou/media/2111019767830740994.jpg', 1, 1),
  (2,  2111417341762928642, 2109922045660860418, '八仙过海·蓝采和',
   'media/2109921517002395649.jpg',
   'https://h5.shengtai.cyou/media/2109921517002395649.jpg', 1, 1),
  (3,  2111417341762928642, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png', 1, 2),
  (4,  2111417341762928642, 2111310949005721602, '八仙过海·韩湘子',
   'media/2111019767830740994.jpg',
   'https://h5.shengtai.cyou/media/2111019767830740994.jpg', 1, 3),
  (5,  2111335862911434754, 2111310949005721602, '八仙过海·韩湘子',
   'media/2111019767830740994.jpg',
   'https://h5.shengtai.cyou/media/2111019767830740994.jpg', 1, 1),
  (6,  2111308128952844290, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png', 1, 1),
  (7,  2111304618949541890, 2109922045660860418, '八仙过海·蓝采和',
   'media/2109921517002395649.jpg',
   'https://h5.shengtai.cyou/media/2109921517002395649.jpg', 1, 1),
  (8,  2111253678766751746, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png', 1, 1),
  (9,  2111026900055949314, 2109922045660860418, '八仙过海·蓝采和',
   'media/2109921517002395649.jpg',
   'https://h5.shengtai.cyou/media/2109921517002395649.jpg', 1, 1),
  (10, 2111026900055949314, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png', 1, 2),
  (11, 2110783333899268098, 2109922045660860418, '八仙过海·蓝采和',
   'media/2109921517002395649.jpg',
   'https://h5.shengtai.cyou/media/2109921517002395649.jpg', 1, 1),
  (12, 2110783333899268098, 2109928427009183745, '臻海蓝卡',
   'media/2109928079460765697.png',
   'https://h5.shengtai.cyou/media/2109928079460765697.png', 1, 2),
  (13, 2109916146355507201, 2109922045660860418, '八仙过海·蓝采和',
   'media/2109921517002395649.jpg',
   'https://h5.shengtai.cyou/media/2109921517002395649.jpg', 1, 1);
-- ---------- 版权信息（源站 GET /item/copyright，实抓 6 条，全局不按 iid 过滤） ----------
INSERT INTO `item_copyrights` (`id`, `name`, `image`, `image_url`, `sort`) VALUES
  (2111418830619213825, '1','media/2111418504742764545.jpg','https://h5.shengtai.cyou/media/2111418504742764545.jpg', 1),
  (2111418812780838913, '1','media/2111418525756227585.jpg','https://h5.shengtai.cyou/media/2111418525756227585.jpg', 2),
  (2111081372530573313, '八仙过海·韩湘子','media/2111019767830740994.jpg','https://h5.shengtai.cyou/media/2111019767830740994.jpg', 3),
  (2110308732563943426, '臻海蓝卡','media/2109928079460765697.png','https://h5.shengtai.cyou/media/2109928079460765697.png', 4),
  (2110308683163430913, '八仙过海·蓝彩和','media/2109921517002395649.jpg','https://h5.shengtai.cyou/media/2109921517002395649.jpg', 5),
  (2104395744614428674, '第1个版权','media/2103038524870647809.png','https://h5.shengtai.cyou/media/2103038524870647809.png', 6);
