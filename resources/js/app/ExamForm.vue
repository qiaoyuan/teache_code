<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../shared/api";
import type { Subject } from "../shared/types";
import { gradeOptions, cleanSubject } from "../shared/types";
import { loadStudents, selectStudent } from "./state";
const route = useRoute(),
    router = useRouter(),
    id = String(route.params.id || "");
const editing = route.path.endsWith("/edit");
const studentId = ref<number | null>(editing ? null : Number(id)),
    loading = ref(true),
    busy = ref(false),
    error = ref("");
const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Shanghai",
}).format(new Date());
const blank = (name = ""): Subject => ({
    name,
    mode: "score",
    score: null,
    grade: "",
});
const form = reactive({
    title: "",
    exam_date: today,
    grade_at_exam: "P1",
    school_at_exam: "",
    note: "",
    version: 1,
    subjects: ["语文", "数学", "英语"].map((n) => blank(n)),
});
onMounted(async () => {
    try {
        await loadStudents();
        if (editing) {
            const e = (await api(`exams/${id}`)).data;
            studentId.value = e.student_id;
            Object.assign(form, e);
            form.subjects = e.subjects.map((s: Subject) => ({ ...s }));
        } else {
            const s = (await api(`students/${id}`)).data;
            form.grade_at_exam = s.current_grade;
            form.school_at_exam = s.school;
        }
        selectStudent(studentId.value);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
});
async function save() {
    busy.value = true;
    error.value = "";
    try {
        const d = await api(
            editing ? `exams/${id}` : `students/${studentId.value}/exams`,
            editing ? "PUT" : "POST",
            {
                title: form.title,
                exam_date: form.exam_date,
                grade_at_exam: form.grade_at_exam,
                note: form.note,
                version: form.version,
                subjects: form.subjects.map(cleanSubject),
            },
        );
        router.replace(`/exams/${d.data.id}`);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="page-title">
        <RouterLink
            class="text-btn"
            :to="studentId ? `/students/${studentId}/exams` : '/'"
            >← 返回成绩记录</RouterLink
        >
        <h1>{{ editing ? "编辑考试成绩" : "录入考试成绩" }}</h1>
        <p class="subtitle">每科可选分数或等级，科目可自由增减</p>
    </div>
    <div v-if="loading" class="empty">正在加载资料…</div>
    <form v-else class="form-card" @submit.prevent="save">
        <section class="card">
            <label class="field"
                ><span>考试名称</span
                ><input
                    v-model="form.title"
                    maxlength="100"
                    placeholder="如：2026 秋季期中考试"
                    required
            /></label>
            <div class="form-grid">
                <label class="field"
                    ><span>考试日期</span
                    ><input
                        v-model="form.exam_date"
                        type="date"
                        :max="today"
                        required /></label
                ><label class="field"
                    ><span>考试时年级</span
                    ><select v-model="form.grade_at_exam">
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
            <p class="hint">
                考试时学校：{{ form.school_at_exam || "未填写" }}。{{
                    editing
                        ? "保留本次考试记录的学校。"
                        : "自动使用学生档案中的学校，无需重复填写。"
                }}
            </p>
        </section>
        <div class="section-head">
            <h2>各科成绩</h2>
            <span class="hint">{{ form.subjects.length }} 个科目</span>
        </div>
        <section
            v-for="(s, index) in form.subjects"
            :key="index"
            class="subject-card"
        >
            <div class="subject-top">
                <strong>科目 {{ index + 1 }}</strong>
                <div class="segmented">
                    <button
                        type="button"
                        :class="{ active: s.mode === 'score' }"
                        @click="s.mode = 'score'"
                    >
                        分数</button
                    ><button
                        type="button"
                        :class="{ active: s.mode === 'grade' }"
                        @click="s.mode = 'grade'"
                    >
                        等级
                    </button>
                </div>
                <button
                    v-if="form.subjects.length > 1"
                    type="button"
                    class="danger"
                    :aria-label="'删除科目 ' + (index + 1)"
                    @click="form.subjects.splice(index, 1)"
                >
                    删除
                </button>
            </div>
            <label class="field"
                ><span>科目名称</span
                ><input
                    v-model="s.name"
                    maxlength="80"
                    list="subjects"
                    placeholder="选择或输入科目"
                    required
            /></label>
            <label v-if="s.mode === 'score'" class="field"
                ><span>分数</span
                ><input
                    v-model="s.score"
                    type="number"
                    min="0"
                    max="10000"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="0 分也可以填写"
                    required
            /></label>
            <label v-else class="field"
                ><span>等级</span
                ><input
                    v-model="s.grade"
                    list="grades"
                    maxlength="30"
                    placeholder="如 A、B 或 优秀"
                    required
            /></label>
        </section>
        <datalist id="subjects">
            <option
                v-for="n in [
                    '语文',
                    '数学',
                    '英语',
                    '科学',
                    '物理',
                    '化学',
                    '生物',
                    '历史',
                    '地理',
                    '道德与法治',
                    '体育',
                ]"
                :key="n"
                :value="n"
            /></datalist
        ><datalist id="grades">
            <option
                v-for="g in ['A', 'B', 'C', 'D', '优秀', '良好', '合格']"
                :key="g"
                :value="g"
            /></datalist
        ><button
            type="button"
            class="add-subject"
            :disabled="form.subjects.length >= 50"
            @click="form.subjects.push(blank())"
        >
            ＋ 添加科目
        </button>
        <section class="card spaced">
            <label class="field"
                ><span>备注（选填）</span
                ><textarea
                    v-model="form.note"
                    maxlength="2000"
                    placeholder="记录本次考试的情况"
                ></textarea>
            </label>
        </section>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <div class="sticky-actions">
            <button class="primary full" :disabled="busy || !studentId">
                {{ busy ? "正在保存…" : "保存考试成绩" }}
            </button>
        </div>
    </form>
</template>
