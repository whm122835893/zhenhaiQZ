<template>
  <!--
    聊天窗口 —— 复刻原站 /pages/chat/web
    源站实测（页面总高 844，uni-page-body 从 y=44 起，bg rgb(246,247,251)）：
      .chat-container 390x800 flex 纵向
      .chat-content 390x743 @y=44，padding 12.48px 12.48px 4.16px（可滚动）
        .chat-messages 365x259 @x=12 y=56，padding-bottom 12.48px
          .empty-chat 365x247，padding-top 104px，居中
            .empty-icon 83x83 @x=153 y=160，圆形 bg rgb(238,240,244)，margin-bottom 14.56px
              内 .empty-bubble 44x31 @x=173 y=186，br 8.32px，bg 白
            .empty-title「暂无消息」14.56px rgb(138,143,153)
            .empty-desc「在下方输入内容，开启第一段对话」12.48px rgb(176,181,191)，margin-top 5.2px
      .chat-input-bar 390x57 @y=787，bg 白，padding 8.32px 12.48px，flex
        .input-wrapper 290x40 @x=12 y=796，br 20.8px，bg rgb(243,244,248)，padding-left 15.6px
          输入 14.56px rgb(31,31,31)，placeholder rgb(176,181,191)
        .send-btn 67x40 @x=311 y=796，br 19.76px，bg rgb(223,227,236)，margin-left 8.32px，
          文字 14.56px 白「发送」
  -->
  <div class="chatPage">
    <UniPageHead title="聊天" />

    <div class="chat-container">
      <div class="chat-content">
        <div class="chat-messages">
          <div v-if="!messages.length" class="empty-chat">
            <div class="empty-icon">
              <div class="empty-bubble" />
            </div>
            <div class="empty-title">暂无消息</div>
            <div class="empty-desc">在下方输入内容，开启第一段对话</div>
          </div>

          <div v-for="(m, i) in messages" :key="i" class="msg" :class="{ 'is-me': m.me }">
            <div class="msg-bubble">{{ m.text }}</div>
          </div>
        </div>
      </div>

      <div class="chat-input-bar">
        <div class="input-wrapper">
          <input v-model="text" class="chat-input" placeholder="请输入消息..." @keyup.enter="onSend" />
        </div>
        <div class="send-btn" @click="onSend">发送</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import UniPageHead from '@/components/UniPageHead.vue'

const text = ref('')
const messages = ref([])

function onSend() {
  const v = text.value.trim()
  if (!v) return
  messages.value.push({ text: v, me: true })
  text.value = ''
}
</script>

<style scoped>
.chatPage {
  min-height: 100vh;
  background-color: rgb(246, 247, 251);
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}
.chat-container {
  height: 48.076923rem;
  display: flex;
  flex-direction: column;
  background-color: rgb(246, 247, 251);
}
.chat-content {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem 0.75rem 0.25rem;
  box-sizing: border-box;
}
.chat-messages {
  padding-bottom: 0.75rem;
}

/* 空态 */
.empty-chat {
  padding-top: 6.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.empty-icon {
  position: relative;
  left: -0.5px;
  width: 4.987981rem;
  height: 4.987981rem;
  margin-bottom: 0.875rem;
  border-radius: 50%;
  background-color: rgb(238, 240, 244);
  display: flex;
  justify-content: center;
  align-items: center;
}
.empty-bubble {
  margin-left: 0.5px;
  width: 2.644231rem;
  height: 1.862981rem;
  border-radius: 0.5rem;
  background-color: rgb(255, 255, 255);
}
.empty-title {
  font-size: 0.875rem;
  line-height: 22px;
  color: rgb(138, 143, 153);
}
.empty-desc {
  font-size: 0.75rem;
  color: rgb(176, 181, 191);
  margin-top: 0.3125rem;
}

/* 消息气泡 */
.msg {
  display: flex;
  margin-bottom: 0.5rem;
}
.msg.is-me {
  justify-content: flex-end;
}
.msg-bubble {
  max-width: 75%;
  padding: 0.5rem 0.75rem;
  border-radius: 0.625rem;
  background-color: rgb(255, 255, 255);
  font-size: 0.875rem;
  color: rgb(31, 31, 31);
  line-height: 1.5;
}
.msg.is-me .msg-bubble {
  background-color: rgb(68, 120, 255);
  color: rgb(255, 255, 255);
}

/* 底部输入条 */
.chat-input-bar {
  height: 3.425481rem;
  padding: 0.5rem 0.75rem;
  box-sizing: border-box;
  background-color: rgb(255, 255, 255);
  display: flex;
  align-items: center;
  flex: 0 0 auto;
}
.input-wrapper {
  flex: 1;
  position: relative;
  top: 0.060096rem;
  height: 2.403846rem;
  padding: 0 0.9375rem;
  box-sizing: border-box;
  border-radius: 1.25rem;
  background-color: rgb(243, 244, 248);
  display: flex;
  align-items: center;
}
.chat-input {
  width: 100%;
  height: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.875rem;
  color: rgb(31, 31, 31);
}
.chat-input::placeholder {
  color: rgb(176, 181, 191);
}
.send-btn {
  position: relative;
  top: 0.060096rem;
  width: 4.026442rem;
  height: 2.403846rem;
  margin-left: 0.5rem;
  border-radius: 1.1875rem;
  background-color: rgb(223, 227, 236);
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  flex: 0 0 auto;
}
</style>