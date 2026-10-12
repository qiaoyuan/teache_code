<script setup lang="ts">
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { state, selectStudent } from "./state";
const route = useRoute(),
    router = useRouter();
const isAuth = computed(() =>
    ["/login", "/register", "/change-password"].includes(route.path),
);
function nav(kind: string) {
    router.push(
        kind === "home"
            ? "/"
            : kind === "account"
              ? "/account"
              : state.selectedId
                ? `/students/${state.selectedId}/${kind}`
                : "/students/new",
    );
}
</script>
<template>
    <div :class="{ shell: !isAuth }">
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
                </option></select
            ><RouterLink to="/students/new">＋ 添加</RouterLink>
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
