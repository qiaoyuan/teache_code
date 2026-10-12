import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/app/main.ts", "resources/js/admin/main.ts"],
            refresh: true,
        }),
        vue(),
    ],
});
