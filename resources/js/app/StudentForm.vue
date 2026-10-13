<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../shared/api";
import { gradeOptions } from "../shared/types";
import { loadStudents, selectStudent, state, readOnly } from "./state";
const route = useRoute(),
    router = useRouter(),
    id = String(route.params.id || "");
const form = reactive({
    name: "",
    gender: "unspecified",
    school: "",
    entry_year: new Date().getFullYear(),
    entry_grade: "P1",
    current_grade: "P1",
    contact_phone: state.user?.phone || "",
});
const busy = ref(false),
    loading = ref(!!id),
    error = ref("");
onMounted(async () => {
    if (id)
        try {
            Object.assign(form, (await api(`students/${id}`)).data);
        } catch (e) {
            error.value = errorText(e);
        } finally {
            loading.value = false;
        }
});
async function save() {
    if (readOnly.value) return;
    busy.value = true;
    error.value = "";
    try {
        const d = await api(
            id ? `students/${id}` : "students",
            id ? "PUT" : "POST",
            form,
        );
        await loadStudents();
        selectStudent(d.data.id);
        router.push("/");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function remove() {
    if (!confirm("删除学生后，名下成绩和意向也将不可见。确定删除？")) return;
    if (readOnly.value) return;
    busy.value = true;
    try {
        await api(`students/${id}`, "DELETE");
        await loadStudents();
        router.push("/");
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="page-title">
        <RouterLink to="/" class="text-btn">← 返回首页</RouterLink>
        <h1>{{ readOnly ? "学生档案" : id ? "编辑学生档案" : "建立学生档案" }}</h1>
        <p class="subtitle">一个家长账号可以管理多个孩子的资料</p>
    </div>
    <p v-if="loading" class="empty">正在加载档案…</p>
    <form v-else class="card form-card" @submit.prevent="save">
        <label class="field"
            ><span>学生姓名</span
            ><input :disabled="readOnly"
                v-model="form.name"
                maxlength="50"
                placeholder="请输入孩子的姓名"
                required
        /></label>
        <div class="form-grid">
            <label class="field"
                ><span>性别</span
                ><select :disabled="readOnly" v-model="form.gender">
                    <option value="unspecified">暂不填写</option>
                    <option value="male">男</option>
                    <option value="female">女</option>
                </select></label
            ><label class="field"
                ><span>当前年级</span
                ><select :disabled="readOnly" v-model="form.current_grade">
                    <option
                        v-for="g in gradeOptions"
                        :key="g.value"
                        :value="g.value"
                    >
                        {{ g.label }}
                    </option>
                </select></label
            >
        </div>
        <label class="field"
            ><span>当前就读学校</span
            ><input :disabled="readOnly"
                v-model="form.school"
                maxlength="160"
                placeholder="请输入完整学校名称"
                required
        /></label>
        <div class="form-grid">
            <label class="field"
                ><span>入学年份</span
                ><input :disabled="readOnly"
                    v-model.number="form.entry_year"
                    type="number"
                    min="1990"
                    :max="new Date().getFullYear() + 1"
                    required /></label
            ><label class="field"
                ><span>入学年级</span
                ><select :disabled="readOnly" v-model="form.entry_grade">
                    <option
                        v-for="g in gradeOptions"
                        :key="g.value"
                        :value="g.value"
                    >
                        {{ g.label }}
                    </option>
                </select></label
            >
        </div>
        <label class="field"
            ><span>家长联系手机号</span
            ><input :disabled="readOnly"
                v-model="form.contact_phone"
                type="tel"
                inputmode="numeric"
                pattern="1[3-9][0-9]{9}"
                maxlength="11"
                required
        /></label>
        <p class="hint">
            联系手机号可以与登录手机号不同。修改当前学校和年级，不会改变历史考试资料。
        </p>
        <p v-if="error" role="alert" class="error">{{ error }}</p>
        <div v-if="!readOnly" class="form-actions">
            <button class="primary" :disabled="busy">
                {{ busy ? "保存中…" : "保存档案" }}</button
            ><button
                v-if="id"
                type="button"
                class="danger"
                :disabled="busy"
                @click="remove"
            >
                删除学生
            </button>
        </div>
    </form>
</template>
