<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText, ApiError, refreshCsrf } from "../shared/api";
import { state } from "./state";
const route = useRoute(),
    router = useRouter();
const mode = computed(() =>
    route.path === "/register"
        ? "register"
        : route.path === "/change-password"
          ? "change"
          : "login",
);
const form = reactive({
        phone: "",
        password: "",
        password_confirmation: "",
        current_password: "",
    }),
    busy = ref(false),
    error = ref(""),
    showHelp = ref(false);
async function submit() {
    if (busy.value) return;
    busy.value = true;
    error.value = "";
    try {
        const d = await api(
            `auth/${mode.value === "change" ? "change-password" : mode.value}`,
            "POST",
            form,
        );
        state.user = d.user;
        state.ready = true;
        form.password = "";
        form.password_confirmation = "";
        form.current_password = "";
        await router.replace(
            d.user.must_change_password ? "/change-password" : "/",
        );
    } catch (e) {
        error.value =
            e instanceof ApiError && e.status === 401
                ? "手机号或密码不正确"
                : errorText(e);
        if (e instanceof ApiError && e.status === 419) await refreshCsrf();
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <main class="auth-page">
        <div class="auth-story">
            <RouterLink class="brand" to="/"
                ><span class="brand-mark">芽</span>成长档案</RouterLink
            >
            <div class="story-copy">
                <div class="eyebrow">GROWING, TOGETHER</div>
                <h1>每一次记录，<br />都看得见成长。</h1>
                <p>
                    为孩子整理成绩，记录学校意向。<br />让成长有迹可循，让选择更加清晰。
                </p>
                <div class="story-art"><span>✦</span><i></i><b>GROW</b></div>
            </div>
            <small>成绩记录与招生意向采集</small>
        </div>
        <div class="auth-form-wrap">
            <form class="auth-form" @submit.prevent="submit">
                <div class="eyebrow">PARENT PORTAL</div>
                <h1>
                    {{
                        mode === "register"
                            ? "创建家长账号"
                            : mode === "change"
                              ? "设置新密码"
                              : "欢迎回来"
                    }}
                </h1>
                <p class="subtitle">
                    {{
                        mode === "register"
                            ? "用手机号开启孩子的成长档案"
                            : mode === "change"
                              ? state.user?.must_change_password
                                  ? "请先修改管理员提供的临时密码"
                                  : "修改密码后，其他设备需要重新登录"
                              : "登录后，继续记录孩子的成长"
                    }}
                </p>
                <label v-if="mode !== 'change'" class="field"
                    ><span>手机号</span
                    ><input
                        v-model="form.phone"
                        type="tel"
                        inputmode="numeric"
                        pattern="1[3-9][0-9]{9}"
                        maxlength="11"
                        autocomplete="username"
                        placeholder="请输入家长手机号"
                        required /></label
                ><label v-if="mode === 'change'" class="field"
                    ><span>{{
                        state.user?.must_change_password
                            ? "临时密码"
                            : "当前密码"
                    }}</span
                    ><input
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        required /></label
                ><label class="field"
                    ><span>{{ mode === "change" ? "新密码" : "密码" }}</span
                    ><input
                        v-model="form.password"
                        type="password"
                        :autocomplete="
                            mode === 'login'
                                ? 'current-password'
                                : 'new-password'
                        "
                        :minlength="mode === 'login' ? undefined : 8"
                        maxlength="64"
                        :placeholder="
                            mode === 'login'
                                ? '请输入密码'
                                : '8–64 位，包含字母和数字'
                        "
                        required /></label
                ><label v-if="mode !== 'login'" class="field"
                    ><span>确认密码</span
                    ><input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="64"
                        placeholder="请再次输入密码"
                        required
                /></label>
                <div v-if="error" role="alert" class="error">{{ error }}</div>
                <button class="primary full" :disabled="busy">
                    {{
                        busy
                            ? "正在提交…"
                            : mode === "login"
                              ? "登录"
                              : mode === "register"
                                ? "注册并开始记录"
                                : "保存新密码"
                    }}
                </button>
                <div v-if="mode === 'login'" class="auth-links">
                    <RouterLink to="/register">还没有账号？立即注册</RouterLink
                    ><button type="button" @click="showHelp = !showHelp">
                        忘记密码
                    </button>
                </div>
                <div v-else-if="mode === 'register'" class="auth-links">
                    <RouterLink to="/login">已有账号，返回登录</RouterLink>
                </div>
                <p v-if="showHelp" class="step-note">
                    请联系平台管理员，提供注册手机号。管理员设置临时密码后，您可以重新登录并设置新密码。
                </p>
                <p class="privacy-note">
                    资料用于成绩记录和招生意向采集。<br />请填写真实信息，妥善保管账号密码。
                </p>
            </form>
        </div>
    </main>
</template>
