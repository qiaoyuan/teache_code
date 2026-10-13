<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { state, loadStudents, selectStudent, logout, readOnly } from "./state";
import { errorText } from "../shared/api";
import { gradeLabel } from "../shared/types";
const router = useRouter(),
    error = ref(""),
    busy = ref(false);
const contactPhone =
    document.querySelector<HTMLMetaElement>('meta[name="contact-phone"]')
        ?.content || "";
onMounted(async () => {
    try {
        await loadStudents();
    } catch (e) {
        error.value = errorText(e);
    }
});
async function exit() {
    busy.value = true;
    try {
        await logout();
        router.replace("/login");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="page-title">
        <h1>我的</h1>
        <p class="subtitle">管理账号与孩子的成长档案</p>
    </div>
    <section class="card">
        <div class="profile-top">
            <span class="avatar">家</span>
            <div>
                <h3>{{ state.user?.phone }}</h3>
                <span class="tag">家长账号</span>
            </div>
        </div>
        <RouterLink v-if="!readOnly" class="account-row" to="/change-password"
            ><span>修改登录密码</span><small>›</small></RouterLink
        >
        <div class="account-row">
            <span>忘记密码</span><small>请联系平台管理员</small>
        </div>
        <a
            class="account-row"
            href="/app/agreement"
            target="_blank"
            rel="noopener"
            ><span>用户协议与隐私政策</span><small>›</small></a
        >
        <a v-if="contactPhone" class="account-row" :href="`tel:${contactPhone}`"
            ><span>隐私与授权咨询</span><small>{{ contactPhone }}</small></a
        >
        <p class="privacy-contact">申请注销账号或撤回授权，请联系平台处理。</p>
    </section>
    <div class="section-head">
        <h2>我的学生</h2>
        <RouterLink v-if="!readOnly" class="text-btn" to="/students/new">＋ 添加学生</RouterLink>
    </div>
    <section class="card">
        <div v-for="s in state.students" :key="s.id" class="account-row">
            <button
                class="student-link"
                @click="
                    selectStudent(s.id);
                    router.push('/');
                "
            >
                <strong>{{ s.name }}</strong
                ><small
                    >{{ s.school }} · {{ gradeLabel(s.current_grade) }}</small
                ></button
            ><RouterLink class="text-btn" :to="`/students/${s.id}/edit`"
                >{{ readOnly ? "查看" : "编辑" }}</RouterLink
            >
        </div>
        <div v-if="!state.students.length" class="empty">还没有学生档案</div>
    </section>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <button class="secondary full spaced" :disabled="busy" @click="exit">
        {{ readOnly ? "退出授权查看" : "退出登录" }}
    </button>
</template>
