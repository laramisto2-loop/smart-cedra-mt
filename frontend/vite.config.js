import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

const backendTarget = globalThis.process?.env?.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: backendTarget,
        changeOrigin: true,
      },
      '/login': {
        target: backendTarget,
        changeOrigin: true,
      },
      '/logout': {
        target: backendTarget,
        changeOrigin: true,
      },
      '/sanctum': {
        target: backendTarget,
        changeOrigin: true,
      },
    },
  },
})
