import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { readFileSync } from 'node:fs'
import { resolve } from 'path'

const moduleManifest = JSON.parse(
  readFileSync(new URL('./module.json', import.meta.url), 'utf8')
)

/**
 * Keep the module on the host application's Vue instance. This also makes
 * globally registered Base* components available to the compiled page.
 */
function vueGlobalPlugin() {
  return {
    name: 'invoiceshelf-vue-global',
    enforce: 'pre',
    resolveId(source) {
      if (source === 'vue') {
        return { id: 'vue', external: true }
      }
    },
    renderChunk(code) {
      return code.replace(
        /import\s*(\{[^}]+\})\s*from\s*"vue"\s*;?/g,
        'const $1 = window.__invoiceshelf_vue;'
      )
    },
  }
}

export default defineConfig({
  define: {
    __MODULE_VERSION__: JSON.stringify(moduleManifest.version),
  },
  build: {
    outDir: resolve(import.meta.dirname, 'dist'),
    emptyOutDir: true,
    lib: {
      entry: resolve(import.meta.dirname, 'resources/js/init.ts'),
      formats: ['es'],
      fileName: () => 'init.js',
      cssFileName: 'style',
    },
  },
  plugins: [
    tailwindcss(),
    vueGlobalPlugin(),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(import.meta.dirname, 'resources/js'),
    },
  },
})
