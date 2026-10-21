-- =====================================================================
-- 吸收规范的种子数据：运营参数 + 默认管理员
-- 依赖：migration_absorb_v1.sql
USE shengtai;
-- =====================================================================

-- 运营参数（SystemConfigService 读取；键名与 config/shengtai.php 对齐）
INSERT INTO `system_configs` (`config_key`, `config_value`, `remark`) VALUES
  ('order.pay_window_ms',       '180000', '订单支付窗口（毫秒），源站实测 3 分钟'),
  ('sell.fee_rate',             '0.0500', '寄售成交手续费率'),
  ('sell.cooldown_minutes',     '3',      '寄售取消/成交后再次上架冷却（分钟）'),
  ('buy.per_user_limit',        '2',      '单藏品单人限购'),
  ('sign.reward_points',        '10',     '每日签到奖励积分'),
  ('consign.fee_rate',          '0.0500', '寄售申请手续费率（与 sell.fee_rate 同步使用）'),
  ('invite.enabled',            '1',      '邀请好友奖励开关（1 开启 0 关闭）'),
  ('invite.reward_iid',         '0',      '邀请好友奖励藏品ID（items.id）'),
  ('invite.reward_qty',         '1',      '邀请好友奖励藏品数量'),
  ('invite.settle_timing',      'register','邀请奖励结算时机（register=注册即发 first_order=首单支付后）'),
  ('activity.sign_milestones',  '',       '签到里程碑空投 CSV（连续天:items.id:数量），空=不启用')
ON DUPLICATE KEY UPDATE `remark` = VALUES(`remark`);
-- 邀请奖励已取消余额通道，清理历史配置键
DELETE FROM `system_configs` WHERE `config_key` IN ('invite.reward_type', 'invite.reward_amount');

-- 默认管理员（密码 Admin@123456，生产环境请立即修改）
INSERT INTO `admin_users` (`username`, `password`, `real_name`, `role`, `status`) VALUES
  ('admin', '$2y$10$IY69bC/BKjy0DEEB6okkvu5NsEBTF9qcL8JBcaa2hTT.SCjYZnthW', '平台管理员', 1, 1)
ON DUPLICATE KEY UPDATE `real_name` = VALUES(`real_name`);
