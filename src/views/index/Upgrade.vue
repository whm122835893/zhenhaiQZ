<template>
  <!--
    版本更新弹窗 —— 复刻原站 /pages/index/upgrade
    源站实测（页面总高 844，纯弹窗页，底图被遮罩盖住）：
      .update-overlay.visible 390x844 fixed，遮罩 rgb(10,23,56,0.5)，flex 居中（底部对齐）
      .update-modal 390x393 @y=451，br 24.96px 24.96px 0 0，bg 白
        .um-banner 390x183：linear-gradient(118deg, rgb(84,160,255), rgb(74,br)），
          br 24.96px 24.96px 0 0
          .um-banner-title「发现」37.44px 白字 @y=478 + .um-badge「臻海艺术」67x23 @x=107 y=485
            br 5199px，渐变底，11.44px 白字
          .um-banner-sub「新版本啦」48.88px rgb(200,245,62) @y=528
          .um-rocket 171x222 @x=211 y=411（banner-DSbamNx8.webp，绝对定位，上溢出）
          .um-spark.s1「✦」22.88px 白 @x=25 y=463；.s2「✦」13.52px rgb(255,229,102) @x=304 y=483
        .um-close 31x31 @x=342 y=467 圆形，bg rgba(255,255,255,0.22)，内含两条交叉白条
        .um-body 390x260 @y=584，margin-top -49.92px，padding 66.56px 22.88px 24.96px
          .um-version「V  更新内容：」17.68px fw700 rgb(29,33,41)
          .um-actions 344x103 @y=716，margin-top 24.96px
            .um-btn-primary 344x56，渐变底，br 5199px，「立即更新」18.72px fw500 白字
            .um-btn-later 91x39 @y=780，margin-top 8.32px，「稍后提醒」14.56px rgb(134,144,156)
  -->
  <div class="upgradePage">
    <div class="update-overlay visible">
      <div class="update-modal">
        <div class="um-banner">
          <div class="um-banner-title">
            <span class="um-banner-zh">发现</span>
            <span class="um-badge">{{ brand.name }}</span>
          </div>
          <div class="um-banner-sub">
            <span class="um-sub-txt">新版本啦</span>
          </div>
          <img class="um-rocket" :src="rocket" alt="" />
          <span class="um-spark s1">✦</span>
          <span class="um-spark s2">✦</span>
        </div>

        <div class="um-close" @click="onClose">
          <span class="um-close-bar bar1" />
          <span class="um-close-bar bar2" />
        </div>

        <div class="um-body">
          <div class="um-version">V&nbsp;&nbsp;更新内容：</div>

          <div class="um-actions">
            <div class="um-btn-primary" @click="onUpdate">
              <span class="um-btn-primary-txt">立即更新</span>
            </div>
            <div class="um-btn-later" @click="onClose">
              <span class="um-btn-later-txt">稍后提醒</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { brand } from '@/api/request'
import rocket from '@/assets/p0final/upgrade/rocket.webp'

const router = useRouter()

function onClose() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

function onUpdate() {
  onClose()
}
</script>

<style scoped>
.upgradePage {
  min-height: 100vh;
  background-color: rgb(255, 255, 255);
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}
.update-overlay {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background-color: rgba(10, 23, 56, 0.5);
  display: flex;
  align-items: flex-end;
}
.update-modal {
  position: relative;
  width: 100%;
  height: 23.617788rem;
  border-radius: 1.5rem 1.5rem 0 0;
  background-color: rgb(255, 255, 255);
  overflow: visible;
}

/* 顶部蓝色 banner */
.um-banner {
  position: relative;
  width: 100%;
  height: 10.997596rem;
  border-radius: 1.5rem 1.5rem 0 0;
  background-image: linear-gradient(118deg, rgb(84, 160, 255), rgb(74, 116, 255));
  overflow: visible;
}
.um-banner-title {
  position: absolute;
  left: 1.502404rem;
  top: 1.622596rem;
  display: flex;
  align-items: center;
}
.um-banner-zh {
  font-size: 2.25rem;
  line-height: 37.44px;
  letter-spacing: -0.95px;
  color: rgb(255, 255, 255);
}
.um-badge {
  height: 1.382212rem;
  padding: 0.3125rem 0.625rem 0.375rem;
  box-sizing: border-box;
  margin-left: 0.5625rem;
  border-radius: 312.46875rem;
  background-image: linear-gradient(rgb(58, 106, 238), rgb(27, 69, 192));
  font-size: 0.6875rem;
  line-height: 11.44px;
  color: rgb(255, 255, 255);
}
.um-banner-sub {
  position: absolute;
  left: 1.262019rem;
  top: 4.627404rem;
}
.um-sub-txt {
  display: inline-block;
  font-size: 2.9375rem;
  line-height: 57px;
  letter-spacing: -3.4px;
  white-space: nowrap;
  color: rgb(200, 245, 62);
}
.um-rocket {
  position: absolute;
  left: 12.680288rem;
  top: -2.403846rem;
  width: 10.276442rem;
  height: 13.341346rem;
}
.um-spark {
  position: absolute;
}
.um-spark.s1 {
  left: 1.502404rem;
  top: 1.141827rem;
  font-size: 1.375rem;
  color: rgb(255, 255, 255);
}
.um-spark.s2 {
  left: 18.269231rem;
  top: 1.622596rem;
  font-size: 0.8125rem;
  color: rgb(255, 229, 102);
}

/* 关闭按钮 */
.um-close {
  position: absolute;
  right: 1.021635rem;
  top: 0.961538rem;
  width: 1.862981rem;
  height: 1.862981rem;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.22);
  cursor: pointer;
}
.um-close-bar {
  position: absolute;
  left: 0.600962rem;
  top: 0.901442rem;
  width: 0.661058rem;
  height: 0.661058rem;
  border-radius: 312.403846rem;
  background-color: rgb(255, 255, 255);
  margin: -0.0625rem 0 0 -0.40625rem;
}
.um-close-bar.bar1 {
  transform: rotate(45deg);
}
.um-close-bar.bar2 {
  transform: rotate(-45deg);
}

/* 内容区 */
.um-body {
  position: relative;
  width: 100%;
  height: 15.625rem;
  margin-top: -3rem;
  padding: 4rem 1.375rem 1.5rem;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  border-radius: 1.5rem 1.5rem 0 0;
  background-color: rgb(255, 255, 255);
}
.um-version {
  font-size: 1.0625rem;
  font-weight: 700;
  color: rgb(29, 33, 41);
  margin-bottom: 0.815009rem;
  position: relative;
  top: -1px;
}
.um-actions {
  margin-top: 1.5rem;
  position: relative;
  top: 1px;
}
.um-btn-primary {
  width: 20.673077rem;
  margin: 0 auto;
  height: 3.365385rem;
  border-radius: 312.46875rem;
  background-image: linear-gradient(90deg, rgb(107, 166, 255), rgb(46, 107, 255));
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}
.um-btn-primary-txt {
  font-size: 1.125rem;
  font-weight: 500;
  color: rgb(255, 255, 255);
  padding-left: 0.25rem;
}
.um-btn-later {
  width: 5.46875rem;
  height: 2.34375rem;
  margin: 0.5rem auto 0;
  position: relative;
  left: -0.5px;
  top: -0.4px;
  padding: 0.5rem 1rem;
  box-sizing: border-box;
  cursor: pointer;
}
.um-btn-later-txt {
  font-size: 0.875rem;
  color: rgb(134, 144, 156);
}
</style>