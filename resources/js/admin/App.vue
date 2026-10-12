<script setup lang="ts">
import { ref } from "vue";
import zhCn from "element-plus/es/locale/lang/zh-cn";
import { useRoute, useRouter } from "vue-router";
import { ElMessage } from "element-plus";
import { state } from "./state";
import { api, errorText } from "../shared/api";
const route = useRoute(),
    router = useRouter(),
    menu = ref(false),
    busy = ref(false);
const links = [
    { path: "/", label: "数据总览", icon: "▦" },
    { path: "/parents", label: "家长账号", icon: "◯" },
    { path: "/students", label: "学生档案", icon: "▤" },
    { path: "/exams", label: "考试批次", icon: "▧" },
    { path: "/scores", label: "科目成绩", icon: "▥" },
    { path: "/intentions", label: "学校意向", icon: "◇" },
    { path: "/audit-logs", label: "操作日志", icon: "◷" },
];
async function logout() {
    busy.value = true;
    try {
        await api("auth/logout", "POST");
        state.user = null;
        router.replace("/login");
    } catch (e) {
        ElMessage.error(errorText(e));
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <el-config-provider :locale="zhCn">
        <RouterView v-if="route.path === '/login'" />
        <div v-else class="admin-layout">
            <aside class="sidebar" :class="{ open: menu }">
                <RouterLink to="/" class="brand"
                    ><span>芽</span>成长档案<small>管理后台</small></RouterLink
                >
                <div class="nav-caption">工作空间</div>
                <nav>
                    <RouterLink
                        v-for="link in links"
                        :key="link.path"
                        :to="link.path"
                        :class="{ active: route.path === link.path }"
                        @click="menu = false"
                        ><b>{{ link.icon }}</b
                        >{{ link.label }}</RouterLink
                    >
                </nav>
                <div class="sidebar-note">
                    <span>填报长期开放</span>
                    <p>汇集成长记录<br />让每个选择更清晰</p>
                </div>
                <a
                    class="parent-entry"
                    href="/app/"
                    target="_blank"
                    rel="noopener"
                    >打开家长端 ↗</a
                >
            </aside>
            <div v-if="menu" class="menu-shade" @click="menu = false"></div>
            <main class="admin-main">
                <header class="admin-top">
                    <button
                        class="mobile-menu"
                        @click="menu = !menu"
                        aria-label="展开菜单"
                    >
                        ☰</button
                    ><span
                        >工作空间 <i>/</i>
                        {{
                            links.find((l) => l.path === route.path)?.label
                        }}</span
                    >
                    <div>
                        <span class="admin-avatar">管</span
                        ><strong>{{ state.user?.username }}</strong
                        ><button :disabled="busy" @click="logout">
                            退出登录
                        </button>
                    </div>
                </header>
                <div class="admin-content">
                    <RouterView :key="route.path" />
                </div>
                <footer>成长档案 · 成绩记录与招生意向采集</footer>
            </main>
        </div>
    </el-config-provider>
</template>
