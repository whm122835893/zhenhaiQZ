import { defineStore } from 'pinia'
import { storage, TOKEN_KEY } from '@/utils/storage'
import { userApi, passportApi } from '@/api'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: storage.get(TOKEN_KEY) || '',
    profile: null,
  }),
  getters: {
    isLogin: (s) => !!s.token,
  },
  actions: {
    setToken(t) {
      this.token = t
      storage.set(TOKEN_KEY, t)
    },
    async fetchProfile() {
      const res = await userApi.getProfile()
      if (res && res.status) this.profile = res.data
      return res
    },
    async signout() {
      await passportApi.signout()
      this.token = ''
      this.profile = null
      storage.remove(TOKEN_KEY)
    },
  },
})