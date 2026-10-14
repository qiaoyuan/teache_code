<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../shared/api";
import { gradeOptions, classGradeLabel, admittedSchoolOptions } from "../shared/types";
import { loadStudents, selectStudent, state, readOnly } from "./state";
const route = useRoute(),
    router = useRouter(),
    id = String(route.params.id || "");
const form = reactive({
    name: "",
    gender: "unspecified",
    school: "",
    admitted_schools: [] as string[],
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
                admitted_schools: form.admitted_schools,
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
        <fieldset class="admission-field" :disabled="readOnly || busy" aria-describedby="admission-help">
            <legend>已被录取学校 <span>选填 · 可多选</span></legend>
            <div class="admission-heading">
                <p id="admission-help">点击选择，已选学校可再次点击取消</p>
                <span class="admission-count" aria-live="polite">已选 {{ form.admitted_schools.length }} 所</span>
            </div>
            <div class="admission-options">
                <label v-for="school in admittedSchoolOptions" :key="school" class="admission-option">
                    <input type="checkbox" v-model="form.admitted_schools" :value="school" />
                    <span class="admission-choice">
                        <span>{{ school }}</span>
                        <span class="admission-check" aria-hidden="true">
                            <svg viewBox="0 0 16 16" fill="none"><path d="m4 8 2.5 2.5L12 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>
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

<style scoped>
.admission-field {
    min-width: 0;
    margin: 25px 0;
    padding: 0;
    border: 0;
}
.admission-field legend {
    padding: 0;
    font-size: 14px;
    font-weight: 650;
}
.admission-field legend span {
    margin-left: 6px;
    color: var(--muted);
    font-size: 11px;
    font-weight: 400;
}
.admission-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px 10px;
    margin: 9px 0 13px;
    font-size: 11px;
}
.admission-heading p {
    margin: 0;
    color: var(--muted);
    line-height: 1.6;
}
.admission-count {
    flex-shrink: 0;
    padding: 4px 9px;
    border-radius: 20px;
    background: #edf3e8;
    color: var(--green);
}
.admission-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.admission-option {
    position: relative;
    min-width: 0;
    cursor: pointer;
}
.admission-option input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    opacity: 0;
    cursor: inherit;
}
.admission-choice {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    height: 100%;
    min-height: 56px;
    padding: 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fafbf8;
    font-size: 13px;
    line-height: 1.5;
    transition: background 150ms ease, border-color 150ms ease;
}
.admission-check {
    display: grid;
    place-items: center;
    flex: 0 0 18px;
    width: 18px;
    height: 18px;
    border: 1px solid #cdd8cf;
    border-radius: 50%;
    background: white;
    color: white;
}
.admission-check svg {
    width: 14px;
    height: 14px;
    visibility: hidden;
}
.admission-option input:checked + .admission-choice {
    border-color: #69917a;
    background: #eef5e8;
    color: var(--green);
    font-weight: 600;
}
.admission-option input:checked + .admission-choice .admission-check {
    border-color: var(--green);
    background: var(--green);
}
.admission-option input:checked + .admission-choice svg {
    visibility: visible;
}
.admission-option input:focus-visible + .admission-choice {
    outline: 3px solid #aacb7e;
    outline-offset: 3px;
}
.admission-field:disabled .admission-option {
    cursor: default;
}
@media (hover: hover) {
    .admission-option input:not(:disabled):hover + .admission-choice {
        border-color: #69917a;
    }
}
@media (min-width: 600px) {
    .admission-options {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 380px) {
    .admission-options {
        gap: 8px;
    }
    .admission-choice {
        gap: 6px;
        padding: 10px 9px;
        font-size: 12px;
    }
    .admission-check {
        flex-basis: 16px;
        width: 16px;
        height: 16px;
    }
}
</style>
