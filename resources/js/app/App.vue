<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { state, selectStudent, readOnly, logout } from "./state";
import { errorText } from "../shared/api";
const exitBusy = ref(false), exitError = ref("");
async function exitAuthorization() {
    exitBusy.value = true;
    exitError.value = "";
    try { await logout(); await router.replace("/login"); }
    catch (e) { exitError.value = errorText(e); }
    finally { exitBusy.value = false; }
}
const route = useRoute(),
    router = useRouter();
const isAuth = computed(() =>
    ["/login", "/register", "/change-password", "/authorized-login"].includes(route.path),
);
function nav(kind: string) {
    router.push(
        kind === "home"
            ? "/"
            : kind === "account"
              ? "/account"
              : state.selectedId
                ? `/students/${state.selectedId}/${kind}`
                : readOnly.value ? "/" : "/students/new",
    );
}
</script>
<template>
    <div :class="{ shell: !isAuth }">
        <aside v-if="state.authorizedLogin && !isAuth" class="authorization-banner" role="status">
            <div><strong>管理员授权查看 · {{ state.user?.phone }}</strong><small>{{ state.authorizedLogin.admin_username }} · 仅查看 · {{ new Date(state.authorizedLogin.expires_at * 1000).toLocaleTimeString('zh-CN', { timeZone: 'Asia/Shanghai', hour12: false }) }} 到期</small></div>
            <button class="secondary" :disabled="exitBusy" @click="exitAuthorization">退出授权查看</button>
            <p v-if="exitError" class="error" role="alert">{{ exitError }}</p>
        </aside>
        <header v-if="!isAuth" class="topbar">
            <RouterLink to="/" class="brand"
                ><span class="brand-mark">芽</span>成长档案</RouterLink
            ><span class="top-right">记录每一步成长</span>
        </header>
        <div
            v-if="!isAuth && state.students.length && route.path !== '/account'"
            class="student-switch"
        >
            <span>当前学生</span
            ><select
                aria-label="切换学生"
                :value="state.selectedId"
                @change="
                    selectStudent(($event.target as HTMLSelectElement).value);
                    nav('home');
                "
            >
                <option v-for="s in state.students" :key="s.id" :value="s.id">
                    {{ s.name }}
                </option>
            </select>
        </div>
        <RouterView :key="route.fullPath" />
        <nav v-if="!isAuth" class="bottom-nav" aria-label="主导航">
            <button
                :class="{ active: route.path === '/' }"
                @click="nav('home')"
            >
                <b>⌂</b>首页</button
            ><button
                :class="{
                    active:
                        route.path.includes('exam') ||
                        route.path.includes('analysis'),
                }"
                @click="nav('exams')"
            >
                <b>▥</b>成绩</button
            ><button
                :class="{ active: route.path.includes('intentions') }"
                @click="nav('intentions')"
            >
                <b>◇</b>意向</button
            ><button
                :class="{ active: route.path === '/account' }"
                @click="nav('account')"
            >
                <b>◯</b>我的
            </button>
        </nav>
    </div>
</template>
