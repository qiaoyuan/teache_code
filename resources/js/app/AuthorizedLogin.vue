<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { api, ApiError, errorText } from "../shared/api";
import { state, selectStudent } from "./state";
const router = useRouter(), error = ref("");
onMounted(async () => {
    const token = new URLSearchParams(location.hash.slice(1)).get("token");
    // Clear the credential before any API call or navigation; never persist it.
    history.replaceState(history.state, "", location.pathname);
    if (!token) {
        error.value = "授权凭证缺失，请返回后台重新发起授权登录。";
        return;
    }
    try {
        const result = await api("auth/authorized-login", "POST", { token });
        state.user = result.user;
        state.authorizedLogin = result.authorized_login;
        state.students = [];
        selectStudent(null);
        state.ready = true;
        await router.replace("/");
    } catch (e) {
        error.value = e instanceof ApiError && [400, 403].includes(e.status)
            ? "授权凭证已失效或已使用，请返回后台重新发起授权登录。"
            : errorText(e);
    }
});
</script>
<template>
    <main class="card authorization-entry">
        <h1>授权登录家长前台</h1>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <p v-else role="status">正在验证授权，请稍候…</p>
        <p>可关闭此窗口，返回原来的管理后台。</p>
    </main>
</template>
