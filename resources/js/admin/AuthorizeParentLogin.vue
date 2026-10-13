<script setup lang="ts">
import { ref } from "vue";
import { ElMessage } from "element-plus";
import { api, errorText } from "../shared/api";
const props = defineProps<{ parent: { id: number; phone: string } }>();
const busy = ref(false);
async function authorize() {
    if (busy.value) return;
    const tab = window.open("about:blank", "_blank");
    if (!tab) {
        ElMessage.error("浏览器阻止了新窗口，请允许本站弹出窗口后重试");
        return;
    }
    tab.opener = null;
    busy.value = true;
    try {
        const result = await api<{ url: string }>(`parents/${props.parent.id}/authorize-login`, "POST");
        tab.location.replace(result.url);
    } catch (e) {
        tab.close();
        ElMessage.error(errorText(e));
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <el-button
        link
        type="primary"
        :loading="busy"
        :title="`在新窗口查看家长 ${parent.phone} 的资料，有效期 30 分钟，仅可查看`"
        @click="authorize"
    >授权登录</el-button>
</template>
