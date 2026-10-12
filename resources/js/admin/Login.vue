<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { api, errorText, ApiError, refreshCsrf } from "../shared/api";
import { state } from "./state";
const router = useRouter(),
    form = reactive({ username: "", password: "" }),
    busy = ref(false),
    error = ref("");
async function submit() {
    busy.value = true;
    error.value = "";
    try {
        state.user = (await api("auth/login", "POST", form)).user;
        state.ready = true;
        form.password = "";
        router.replace("/");
    } catch (e) {
        error.value =
            e instanceof ApiError && e.status === 401
                ? "管理员账号或密码不正确"
                : errorText(e);
        if (e instanceof ApiError && e.status === 419) await refreshCsrf();
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <main class="admin-login">
        <section class="login-story">
            <div class="brand">
                <span>芽</span>成长档案<small>管理后台</small>
            </div>
            <div>
                <p class="eyebrow">GROWTH IN EVERY RECORD</p>
                <h1>看见成长，<br />连接未来。</h1>
                <p class="login-copy">
                    学生档案、考试成绩、学校意向<br />在一个工作空间里，清晰掌握。
                </p>
                <div class="login-shapes" aria-hidden="true">
                    <i></i><b>✦</b><span>GROWTH</span>
                </div>
            </div>
            <small>成绩记录与招生意向采集系统</small>
        </section>
        <section class="login-form-wrap">
            <form @submit.prevent="submit">
                <p class="eyebrow">ADMINISTRATION</p>
                <h2>管理员登录</h2>
                <p class="muted">进入后台，查看与管理全量填报数据</p>
                <label
                    >管理员账号<input
                        v-model="form.username"
                        autocomplete="username"
                        maxlength="64"
                        placeholder="请输入管理员账号"
                        required /></label
                ><label
                    >登录密码<input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        maxlength="100"
                        placeholder="请输入登录密码"
                        required
                /></label>
                <p v-if="error" class="form-error" role="alert">{{ error }}</p>
                <button class="green-button" :disabled="busy">
                    {{ busy ? "正在登录…" : "登录管理后台 →" }}
                </button>
                <p class="login-help">
                    管理员账号由系统初始化创建。<br />家长请前往
                    <a href="/app/login">家长登录入口 ↗</a>
                </p>
            </form>
        </section>
    </main>
</template>
