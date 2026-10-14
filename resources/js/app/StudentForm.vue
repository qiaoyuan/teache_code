<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../shared/api";
import { gradeOptions, classGradeLabel } from "../shared/types";
import { loadStudents, selectStudent, state, readOnly } from "./state";
const route = useRoute(),
    router = useRouter(),
    id = String(route.params.id || "");
const form = reactive({
    name: "",
    gender: "unspecified",
    school: "",
    class_number: "",
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
            {
                name: form.name,
                gender: form.gender,
                school: form.school,
                current_grade: form.current_grade,
                class_number: form.class_number,
                contact_phone: form.contact_phone,
            },
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
        <h1>
            {{ readOnly ? "学生档案" : id ? "编辑学生档案" : "建立学生档案" }}
        </h1>
        <p class="subtitle">一个家长账号可以管理多个孩子的资料</p>
    </div>
    <p v-if="loading" class="empty">正在加载档案…</p>
    <form v-else class="card form-card" @submit.prevent="save">
        <label class="field"
            ><span>学生姓名</span
            ><input
                :disabled="readOnly"
                v-model="form.name"
                maxlength="50"
                placeholder="请输入孩子的姓名"
                required
        /></label>
        <label class="field"
            ><span>性别</span
            ><select :disabled="readOnly" v-model="form.gender">
                <option value="unspecified">暂不填写</option>
                <option value="male">男</option>
                <option value="female">女</option>
            </select></label
        >
        <label class="field"
            ><span>学校全称</span
            ><input
                :disabled="readOnly"
                v-model="form.school"
                maxlength="160"
                placeholder="xx区（县）xx学校"
                required
        /></label>
        <label class="field">
            <span>年级</span>
            <select class="student-grade-select" :disabled="readOnly" v-model="form.current_grade" required>
                <option v-for="g in gradeOptions" :key="g.value" :value="g.value">
                    {{ classGradeLabel(g.value) }}
                </option>
            </select>
        </label>
        <label class="field">
            <span>班级</span>
            <span class="class-number">
                <input :disabled="readOnly" v-model="form.class_number"
                    type="text" inputmode="numeric"
                    pattern="[1-9][0-9]?" maxlength="2"
                    title="请输入 1–99 的整数" placeholder="请输入班号，例如 3"
                    aria-describedby="class-number-help" required />
                <span aria-hidden="true">班</span>
            </span>
            <small id="class-number-help" class="class-help">班号为 1–99 的整数</small>
        </label>
        <label class="field"
            ><span>家长联系手机号</span
            ><input
                :disabled="readOnly"
                v-model="form.contact_phone"
                type="tel"
                inputmode="numeric"
                pattern="1[3-9][0-9]{9}"
                maxlength="11"
                required
        /></label>
        <p class="hint">
            联系手机号可以与登录手机号不同。修改学校和班级，不会改变历史考试资料。
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
