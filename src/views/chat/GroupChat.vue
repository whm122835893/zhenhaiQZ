<template>
  <!--
    群聊列表 —— 复刻原站 /pages/chat/group
    源站实测（页面总高 888，uni-page-body 从 y=44 起，bg rgb(245,245,245)）：
      .group-container 390x844，padding 10.4px 12.48px
      .group-item 365x94 @y=54 / y=159：br 8.32px，bg 白，margin-bottom 10.4px，padding 14.56px 12.48px
        .group-avatar 50x50 @x=25，br 10.4px，margin-right 12.48px
        .group-info margin-left 8.32px：
          .group-name 15.6px fw600 rgb(26,26,26)
          .group-desc 12.48px rgb(153,153,153)
          .member-count 11.44px rgb(187,187,187)「2000人」
        .group-action 靠右：
          .action-enter「进入聊天」79x31，br 15.6px，bg rgba(68,120,255,0.1)，字 rgb(68,120,255) 12.48px
          .action-join「加入」54x31，br 15.6px，bg rgb(68,120,255)，字白 12.48px
  -->
  <div class="groupPage">
    <UniPageHead title="我们" />

    <div class="group-container">
      <div v-for="(g, i) in groups" :key="i" class="group-item" @click="onEnter">
        <div class="group-avatar">
          <img class="group-avatar-img" :src="g.avatar" alt="" />
        </div>

        <div class="group-info">
          <div class="group-name">{{ g.name }}</div>
          <div class="group-desc">{{ g.desc }}</div>
          <div class="group-meta">
            <span class="member-count">{{ g.members }}</span>
          </div>
        </div>

        <div class="group-action">
          <div v-if="g.joined" class="action-btn action-enter">
            <span class="action-txt">进入聊天</span>
          </div>
          <div v-else class="action-btn action-join" @click.stop="onJoin(g)">
            <span class="action-txt">加入</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import UniPageHead from '@/components/UniPageHead.vue'
import avatar from '@/assets/p0final/group/logo.png'

const router = useRouter()

const groups = reactive([
  { name: '我们', desc: '娃哈哈', members: '2000人', joined: true, avatar },
  { name: '我们', desc: '娃哈哈', members: '2000人', joined: false, avatar },
])

function onJoin(g) {
  g.joined = true
}

function onEnter() {
  router.push('/pages/chat/web')
}
</script>

<style scoped>
.groupPage {
  min-height: 100vh;
  background-color: rgb(245, 245, 245);
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}
.group-container {
  min-height: 50.721154rem;
  box-sizing: border-box;
  padding: 0.625rem 0.75rem;
}
.group-item {
  width: 21.935096rem;
  min-height: 5.649038rem;
  max-height: 5.649038rem;
  box-sizing: border-box;
  padding: 0.875rem 0.75rem 0.815009rem;
  margin-bottom: 0.625rem;
  border-radius: 0.5rem;
  background-color: rgb(255, 255, 255);
  display: flex;
  align-items: center;
  cursor: pointer;
}
.group-avatar {
  width: 3.004808rem;
  height: 3.004808rem;
  margin-right: 0.75rem;
  border-radius: 0.625rem;
  overflow: hidden;
  flex: 0 0 auto;
}
.group-avatar-img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}
.group-info {
  flex: 1;
  min-width: 0;
  margin-right: 0.540865rem;
  height: 65px;
  position: relative;
  top: -0.5px;
  letter-spacing: -0.55px;
  overflow: hidden;
}
.group-name {
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 23px;
  color: rgb(26, 26, 26);
  margin-bottom: 0.240385rem;
}
.group-desc {
  font-size: 0.75rem;
  line-height: 18px;
  color: rgb(153, 153, 153);
  margin-bottom: 0.180289rem;
}
.group-meta {
  height: 16px;
}
.member-count {
  display: inline-block;
  position: relative;
  top: -0.5px;
  font-size: 0.6875rem;
  height: 16px;
  line-height: 16px;
  color: rgb(187, 187, 187);
}
.group-action {
  flex: 0 0 auto;
}
.action-btn {
  height: 1.862981rem;
  border-radius: 0.9375rem;
  padding: 0 0.875rem;
  box-sizing: border-box;
  display: flex;
  justify-content: center;
  align-items: center;
}
.action-enter {
  background-color: rgba(68, 120, 255, 0.1);
}
.action-enter .action-txt {
  font-size: 0.75rem;
  color: rgb(68, 120, 255);
}
.action-join {
  background-color: rgb(68, 120, 255);
}
.action-join .action-txt {
  font-size: 0.75rem;
  color: rgb(255, 255, 255);
}
</style>