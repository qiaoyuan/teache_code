<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { ElMessage } from "element-plus";
import { api, errorText } from "../shared/api";
import { state } from "./state";

type Administrator = { id: number; username: string; created_at: string };
const router = useRouter();
const rows = ref<Administrator[]>([]);
const page = ref(1), total = ref(0), loading = ref(false), saving = ref(false);
const error = ref(""), formError = ref(""), dialog = ref(false);
const target = ref<Administrator | null>(null);
const form = reactive({ username: "", password: "", password_confirmation: "", current_password: "" });

async function load() {
    loading.value = true;
    error.value = "";
    try {
        const result = await api(`administrators?page=${page.value}`);
        rows.value = result.data;
        total.value = result.total;
    } catch (e) { error.value = errorText(e); }
    finally { loading.value = false; }
}
function clearForm() {
    Object.assign(form, { username: "", password: "", password_confirmation: "", current_password: "" });
    formError.value = "";
}
function open(account: Administrator | null = null) {
    clearForm();
    target.value = account;
    dialog.value = true;
}
async function submit() {
    if (saving.value) return;
    formError.value = "";
    if (form.password !== form.password_confirmation) {
        formError.value = "两次输入的新密码不一致";
        return;
    }
    saving.value = true;
    try {
        const result = await api(target.value ? `administrators/${target.value.id}/password` : "administrators", "POST", { ...form });
        dialog.value = false;
        clearForm();
        ElMessage.success(result.message || "管理员已添加");
        if (result.require_login) {
            state.user = null;
            await router.replace("/login");
        } else { await load(); }
    } catch (e) { formError.value = errorText(e); }
    finally { saving.value = false; }
}
function date(value: string) {
    return new Date(value).toLocaleString("zh-CN", { timeZone: "Asia/Shanghai", hour12: false });
}
onMounted(load);
</script>

<template>
    <div class="page-heading">
        <div><h1>管理员账号</h1><p>新增后台账号，管理管理员登录密码</p></div>
        <el-button type="primary" @click="open()">新增管理员</el-button>
    </div>
    <el-alert title="所有管理员拥有相同的后台权限，请仅为可信人员创建账号。" type="info" :closable="false" show-icon />
    <div class="accounts-table" v-loading="loading">
        <el-alert v-if="error" :title="error" type="error" :closable="false"><el-button text @click="load">重新加载</el-button></el-alert>
        <el-table v-else :data="rows" empty-text="暂无管理员账号">
            <el-table-column prop="id" label="ID" width="90" />
            <el-table-column label="登录账号" min-width="180"><template #default="{ row }">{{ row.username }}<span v-if="row.id === state.user?.id" class="current-account">当前账号</span></template></el-table-column>
            <el-table-column label="创建时间" min-width="190"><template #default="{ row }">{{ date(row.created_at) }}</template></el-table-column>
            <el-table-column label="操作" width="130"><template #default="{ row }"><el-button link type="primary" @click="open(row)">修改密码</el-button></template></el-table-column>
        </el-table>
        <el-pagination v-if="total > 20" v-model:current-page="page" :page-size="20" :total="total" layout="prev, pager, next" @current-change="load" />
    </div>
    <el-dialog v-model="dialog" :title="target ? '修改管理员密码' : '新增管理员'" width="min(480px, 92vw)" :close-on-click-modal="false" :close-on-press-escape="!saving" :show-close="!saving" @closed="clearForm">
        <form class="account-form" @submit.prevent="submit">
            <p v-if="target">正在修改：<strong>{{ target.username }}</strong>。保存后该账号的所有旧登录将失效。</p>
            <label v-else for="admin-username">登录账号
                <input id="admin-username" v-model="form.username" required minlength="3" maxlength="64" pattern="[A-Za-z0-9][A-Za-z0-9_.-]*" autocomplete="off" :disabled="saving" />
                <small>3–64 位字母、数字、下划线、点或短横线，以字母或数字开头；统一保存为小写。</small>
            </label>
            <label for="admin-new-password">{{ target ? '新密码' : '初始密码' }}
                <input id="admin-new-password" v-model="form.password" type="password" required minlength="8" maxlength="64" autocomplete="new-password" :disabled="saving" />
                <small>8–64 位，须包含字母和数字。</small>
            </label>
            <label for="admin-confirm-password">确认密码<input id="admin-confirm-password" v-model="form.password_confirmation" type="password" required autocomplete="new-password" :disabled="saving" /></label>
            <label for="admin-current-password">你的当前登录密码<input id="admin-current-password" v-model="form.current_password" type="password" required autocomplete="current-password" :disabled="saving" /><small>用于验证操作者 {{ state.user?.username }} 的身份。</small></label>
            <el-alert v-if="formError" :title="formError" type="error" :closable="false" />
            <div class="dialog-actions"><el-button :disabled="saving" @click="dialog = false">取消</el-button><el-button type="primary" native-type="submit" :loading="saving">{{ target ? '保存密码' : '创建账号' }}</el-button></div>
        </form>
    </el-dialog>
</template>

<style scoped>
.accounts-table { margin-top: 20px; background: white; padding: 20px; border-radius: 16px; }
.current-account { margin-left: 10px; font-size: 12px; color: #00866a; }
.account-form { display: grid; gap: 18px; }
.account-form label { display: grid; gap: 8px; color: #344054; font-weight: 500; }
.account-form input { width: 100%; box-sizing: border-box; border: 1px solid #d0d5dd; border-radius: 8px; padding: 11px 12px; font: inherit; }
.account-form input:focus { outline: 2px solid #97d8c8; border-color: #00866a; }
.account-form small, .account-form p { color: #667085; font-size: 13px; font-weight: 400; line-height: 1.6; }
.el-pagination { margin-top: 18px; }
</style>
