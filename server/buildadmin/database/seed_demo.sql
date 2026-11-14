-- 臻海 QZ × BuildAdmin 演示种子数据（验收用）
-- 库：nft（MariaDB）。金额一律「分」；时间一律「秒」。
-- 由 scripts/dev-restore.sh 在恢复环境时自动导入。

-- 藏品 3 个：发售中、开放二级市场
INSERT INTO `collection`
  (`name`,`category_id`,`author`,`publisher`,`cover`,`price`,`market_price`,`stock`,`sold`,`destroyed`,`detail`,
   `resale`,`transfer_enabled`,`status`,`sale_time`,`sale_end_time`,
   `resale_price_limit`,`resale_price_min`,`pre_sale_time`,`pre_rule_type`,`pre_rule_value`,`pre_limit`,
   `createtime`,`updatetime`)
VALUES
  ('潮汐回响 · 序章', 0, '林晚风工作室', '臻海数字艺术', '/storage/seed/work1.jpg',
   4999, 6999, 100, 0, 0,
   '<p>「潮汐回响」系列以海浪声波为灵感，将自然界的频率转译为可视化的数字艺术。序章共发行 100 份，持有者可参与后续章节的优先购。</p><p>本藏品支持二级市场寄售，限价 99.99 元。</p>',
   1, 1, 2, UNIX_TIMESTAMP()-3600, 0,
   9999, 1000, 0, 0, '', 0,
   UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('山海浮生 · 观岚', 0, '拾光画社', '臻海数字艺术', '/storage/seed/work2.jpg',
   9999, 13999, 50, 0, 0,
   '<p>「山海浮生」以《山海经》异兽为母题，采用水墨与赛博光的融合语言。观岚为本系列第二个篇章，发行 50 份。</p><p>本藏品支持二级市场寄售，限价 199.99 元。</p>',
   1, 1, 2, UNIX_TIMESTAMP()-3600, 0,
   19999, 3000, 0, 0, '', 0,
   UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('星屿幻旅 · 启航', 0, '流浪灯塔', '臻海数字艺术', '/storage/seed/work3.jpg',
   19999, 25999, 30, 0, 0,
   '<p>「星屿幻旅」描绘星际迁徙中的孤岛与灯塔，发行 30 份，为平台首发限量系列。</p><p>本藏品支持二级市场寄售，限价 299.99 元。</p>',
   1, 1, 2, UNIX_TIMESTAMP()-3600, 0,
   29999, 5000, 0, 0, '', 0,
   UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 轮播 2 张（weigh 大者靠前）
INSERT INTO `banner` (`title`,`image`,`link`,`weigh`,`status`,`createtime`,`updatetime`) VALUES
  ('山海浮生 · 观岚 已开启发售', '/storage/seed/cover-2.png', '', 20, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  ('臻海艺术 · 数字艺术收藏社区', '/storage/seed/cover-1.jpg', '', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 公告 2 条
INSERT INTO `notice` (`title`,`category`,`content`,`is_top`,`status`,`publish_time`,`createtime`,`updatetime`) VALUES
  ('「山海浮生 · 观岚」现已开启发售，限量 50 份', 1,
   '<p>各位藏友：「山海浮生 · 观岚」已于今日 10:00 正式发售，全球限量 50 份，售价 99.99 元。持有「潮汐回响」系列的用户可在后续优先购窗口获得优先购买资格。</p><p>请理性收藏，谨防炒作。</p>',
   1, 1, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()-3600, UNIX_TIMESTAMP()-3600),
  ('平台二级市场寄售规则说明', 3,
   '<p>1. 二级市场寄售手续费率为成交价的 5%，由卖家承担；</p><p>2. 寄售价格不得超过藏品限价，亦不得低于限价下限；</p><p>3. 成交后藏品即时过户，卖家收益实时入账钱包。</p><p>请遵守平台规则，理性交易。</p>',
   0, 1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()-86400);

-- 抽奖活动 1 个（启用中，前后 7 天）+ 奖池
INSERT INTO `lottery_activity`
  (`title`,`chance_rules`,`status`,`start_time`,`end_time`,`createtime`,`updatetime`)
VALUES
  ('启航幸运抽', '{"daily_free":{"num":1},"register":{"num":1},"hold":{"num":1},"invite":{"num":1}}',
   1, UNIX_TIMESTAMP()-86400, UNIX_TIMESTAMP()+7*86400, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `lottery_prize`
  (`activity_id`,`name`,`type`,`collection_id`,`num`,`probability`,`stock`,`sent`,`createtime`,`updatetime`)
SELECT la.id, x.name, x.type, x.collection_id, x.num, x.probability, x.stock, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM lottery_activity la, (
  SELECT 1 AS n, '星屿幻旅 · 启航' AS name, 1 AS type, 3 AS collection_id, 0 AS num, 500  AS probability, 5  AS stock UNION ALL
  SELECT 2, '抽奖次数 +1', 2, 0, 1, 2000, 999 UNION ALL
  SELECT 3, '谢谢参与', 3, 0, 0, 7500, 0
) x WHERE la.title = '启航幸运抽';

-- 签到阶梯奖励（1/3/7 天 → 抽奖次数）
INSERT INTO `checkin_rule` (`day_num`,`reward_type`,`reward_num`,`collection_id`,`pre_collection_id`,`createtime`,`updatetime`) VALUES
  (1, 1, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (3, 1, 2, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (7, 1, 3, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
