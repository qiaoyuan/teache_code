<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { api, errorText } from "../shared/api";
import type { Exam, Intention, SubjectAnalysis } from "../shared/types";
import { classLabel, gradeLabel, sumScore, subtotalLabel } from "../shared/types";
import TrendChart from "../shared/TrendChart.vue";
import { state, loadStudents, readOnly } from "./state";
const student = computed(() =>
    state.students.find((s) => s.id === state.selectedId),
);
const exams = ref<Exam[]>([]),
    intentions = ref<Intention[]>([]),
    analysis = ref<SubjectAnalysis[]>([]),
    subjectKey = ref(""),
    examCount = ref(0),
    loading = ref(true),
    error = ref("");
const current = computed(
    () =>
        analysis.value.find((a) => a.key === subjectKey.value) ||
        analysis.value[0],
);
let serial = 0;
async function load() {
    const n = ++serial;
    if (!state.selectedId) {
        loading.value = false;
        return;
    }
    loading.value = true;
    error.value = "";
    try {
        const id = state.selectedId;
        const [e, i, a] = await Promise.all([
            api(`students/${id}/exams`),
            api(`students/${id}/intentions`),
            api(`students/${id}/analysis`),
        ]);
        if (n !== serial) return;
        exams.value = e.data.slice(0, 3);
        intentions.value = i.data;
        analysis.value = a.subjects;
        examCount.value = a.exam_count;
    } catch (e) {
        if (n === serial) error.value = errorText(e);
    } finally {
        if (n === serial) loading.value = false;
    }
}
onMounted(async () => {
    try {
        await loadStudents();
        await load();
    } catch (e) {
        error.value = errorText(e);
        loading.value = false;
    }
});
watch(() => state.selectedId, load);
</script>
<template>
    <div class="greeting">
        <div>
            <div class="eyebrow">A LITTLE PROGRESS, EVERY DAY</div>
            <h1>
                {{ student ? student.name + "的成长记录" : "欢迎开启成长档案" }}
            </h1>
            <p class="subtitle">
                {{
                    student
                        ? student.school +
                          " · " +
                          classLabel(student)
                        : "先建立孩子的档案，再录入成绩和学校意向"
                }}
            </p>
        </div>
    </div>
    <section class="hero">
        <div>
            <div class="eyebrow">记录，陪伴，成长</div>
            <h2>成长的每一步，<br />都值得好好记录。</h2>
            <p>留下一次成绩，发现一点进步。</p>
            <RouterLink
                v-if="!readOnly"
                class="primary"
                :to="
                    student
                        ? `/students/${student.id}/exams/new`
                        : '/students/new'
                "
                >{{ student ? "＋ 录入新成绩" : "＋ 建立学生档案" }}</RouterLink
            >
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="leaf one"></div>
            <div class="leaf two"></div>
            <div class="leaf three"></div>
            <span>✦</span>
        </div>
    </section>
    <p v-if="error" role="alert" class="error">
        {{ error }} <button @click="load">重试</button>
    </p>
    <p v-if="loading" class="empty">正在加载成长记录…</p>
    <template v-else-if="student"
        ><div class="actions">
            <RouterLink class="action" :to="`/students/${student.id}/edit`"
                ><span class="tile-icon">◯</span>
                <div>
                    <strong>学生档案</strong><small>{{ readOnly ? "查看基本信息" : "查看与修改基本信息" }}</small>
                </div>
                <span>›</span></RouterLink
            ><RouterLink
                class="action"
                :to="`/students/${student.id}/intentions`"
                ><span class="tile-icon orange">◇</span>
                <div>
                    <strong>学校意向</strong><small>记录优先与备选学校</small>
                </div>
                <span>›</span></RouterLink
            >
        </div>
        <div class="layout">
            <main>
                <div class="section-head">
                    <h2>单科成绩概览</h2>
                    <RouterLink
                        class="text-btn"
                        :to="`/students/${student.id}/analysis`"
                        >查看分析 →</RouterLink
                    >
                </div>
                <section class="card">
                    <div class="stats">
                        <div>
                            <small>已记录考试</small
                            ><strong>{{ examCount }}<em>次</em></strong>
                        </div>
                        <div>
                            <small>{{
                                current?.normalized
                                    ? "当前科目最好得分率"
                                    : "当前科目最高分"
                            }}</small
                            ><strong
                                >{{
                                    (current?.normalized
                                        ? current?.best_rate
                                        : current?.best_score) ?? "—"
                                }}<em v-if="current?.best_rate != null">{{
                                    current?.normalized ? "%" : "分"
                                }}</em></strong
                            >
                        </div>
                    </div>
                    <div class="chips">
                        <button
                            v-for="a in analysis"
                            :key="a.key"
                            class="chip"
                            :class="{ active: current?.key === a.key }"
                            @click="subjectKey = a.key"
                        >
                            {{ a.name }}
                        </button>
                    </div>
                    <TrendChart v-if="current" :subject="current" />
                    <div v-else class="empty">
                        还没有成绩记录<br /><RouterLink v-if="!readOnly"
                            class="text-btn"
                            :to="`/students/${student.id}/exams/new`"
                            >录入第一次成绩 →</RouterLink
                        >
                    </div>
                </section>
                <div class="section-head">
                    <h2>最近考试</h2>
                    <RouterLink
                        class="text-btn"
                        :to="`/students/${student.id}/exams`"
                        >全部记录 →</RouterLink
                    >
                </div>
                <section class="card">
                    <RouterLink
                        v-for="e in exams"
                        :key="e.id"
                        class="exam"
                        :to="`/exams/${e.id}`"
                        ><div class="datebox">
                            <b>{{ e.exam_date.slice(8, 10) }}</b
                            ><span>{{ e.exam_date.slice(0, 7) }}</span>
                        </div>
                        <div class="exam-content">
                            <strong>{{ e.title }}</strong
                            ><small
                                >{{ e.subjects.length }} 个科目 ·
                                {{ gradeLabel(e.grade_at_exam) }}</small
                            >
                        </div>
                        <div class="exam-total">
                            {{
                                e.subjects.some((s) => s.mode === "score")
                                    ? sumScore(e)
                                    : "等级"
                            }}<small>{{
                                e.subjects.some((s) => s.mode === "score")
                                    ? subtotalLabel(e)
                                    : "查看各科等级"
                            }}</small>
                        </div></RouterLink
                    >
                    <div v-if="!exams.length" class="empty">
                        每一次考试，都从这里开始记录
                    </div>
                </section>
            </main>
            <aside>
                <div class="section-head">
                    <h2>学生档案</h2>
                    <RouterLink
                        class="text-btn"
                        :to="`/students/${student.id}/edit`"
                        >{{ readOnly ? "查看 →" : "编辑 →" }}</RouterLink
                    >
                </div>
                <section class="card">
                    <div class="profile-top">
                        <div class="avatar">{{ student.name.slice(0, 1) }}</div>
                        <div>
                            <h3>{{ student.name }}</h3>
                            <span class="tag">{{
                                classLabel(student)
                            }}</span>
                        </div>
                    </div>
                    <div class="profile-info">
                        <div><small>学校全称</small>{{ student.school }}</div>
                        <div><small>班级</small>{{ classLabel(student) }}</div>
                        <div>
                            <small>联系电话</small>{{ student.contact_phone }}
                        </div>
                    </div>
                </section>
                <div class="section-head">
                    <h2>学校意向</h2>
                    <RouterLink
                        class="text-btn"
                        :to="`/students/${student.id}/intentions`"
                        >管理 →</RouterLink
                    >
                </div>
                <section class="card">
                    <div
                        v-for="i in intentions.slice(0, 3)"
                        :key="i.id"
                        class="school-row"
                    >
                        <span class="tile-icon">◇</span
                        ><strong>{{ i.school }}</strong
                        ><span class="tag">{{
                            i.level === "first" ? "优先" : "备选"
                        }}</span>
                    </div>
                    <div v-if="!intentions.length" class="empty">
                        还没有填写意向学校
                    </div>
                </section>
                <div v-if="!readOnly" class="note">
                    填报长期开放。您可以随时补充、修改孩子的档案、成绩与学校意向。
                </div>
            </aside>
        </div></template
    >
    <div v-else-if="!error" class="card empty">
        <h2>{{ readOnly ? "该家长尚未创建学生档案" : "从一份学生档案开始" }}</h2>
        <p>{{ readOnly ? "当前仅可查看已有资料。" : "填写孩子姓名、学校全称和班级，即可开始记录。" }}</p>
        <RouterLink v-if="!readOnly" to="/students/new" class="primary">添加学生</RouterLink>
    </div>
    <p class="footer-caption">用心记录，陪伴每一步成长</p>
</template>
