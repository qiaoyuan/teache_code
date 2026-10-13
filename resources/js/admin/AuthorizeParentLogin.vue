<script setup lang="ts">
import { ref } from "vue";
import { api, errorText } from "../shared/api";
const props = defineProps<{ parent: { id: number; phone: string } }>();
const emit = defineEmits<{ close: [] }>();
const password = ref(""), busy = ref(false), error = ref("");
async function authorize() {
    if (busy.value) return;
    error.value = "";
    const tab = window.open("about:blank", "_blank");
    if (!tab) {
        error.value = "浏览器阻止了新窗口，请允许本站弹出窗口后重试";
        return;
    }
    tab.opener = null;
    busy.value = true;
    try {
        const result = await api(`parents/${props.parent.id}/authorize-login`, "POST", { current_password: password.value });
        tab.location.replace(result.url);
        emit("close");
    } catch (e) {
        tab.close();
        error.value = errorText(e);
    } finally {
        password.value = "";
        busy.value = false;
    }
}
</script>
<template>
    <el-dialog :model-value="true" title="授权登录家长前台" width="min(480px, 94vw)" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" @close="emit('close')">
        <form @submit.prevent="authorize">
            <el-alert :title="'家长账号：' + parent.phone" type="info" :closable="false" />
            <p class="reset-help">将在新窗口查看该家长的学生、成绩和意向，有效期 30 分钟。仅可查看，不能修改资料或密码，操作会记录日志。</p>
            <p class="reset-help">本浏览器已登录的家长账号将切换为此账号，后台登录保持有效。</p>
            <label class="reset-field">你的管理员登录密码
                <el-input v-model="password" type="password" autocomplete="current-password" maxlength="100" :disabled="busy" required />
            </label>
            <p v-if="error" class="form-error" role="alert">{{ error }}</p>
            <div class="dialog-actions">
                <el-button :disabled="busy" @click="emit('close')">取消</el-button>
                <el-button type="primary" native-type="submit" :loading="busy" :disabled="!password">授权登录</el-button>
            </div>
        </form>
    </el-dialog>
</template>
