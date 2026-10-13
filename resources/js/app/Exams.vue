<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { api, errorText } from "../shared/api";
import type { Exam, SubjectAnalysis } from "../shared/types";
import { gradeLabel, sumScore, subtotalLabel } from "../shared/types";
import TrendChart from "../shared/TrendChart.vue";
import { loadStudents, state, selectStudent, readOnly } from "./state";
const route = useRoute(),
    id = Number(route.params.id),
    student = computed(() => state.students.find((s) => s.id === id));
const rows = ref<Exam[]>([]),
    subjects = ref<SubjectAnalysis[]>([]),
    selected = ref(""),
    page = ref(1),
    last = ref(1),
    total = ref(0),
    loading = ref(true),
    error = ref("");
const tab = ref(route.path.endsWith("/analysis") ? "analysis" : "list");
const current = computed(
    () =>
        subjects.value.find((s) => s.key === selected.value) ||
        subjects.value[0],
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const [e, a] = await Promise.all([
            api(`students/${id}/exams?page=${page.value}`),
            api(`students/${id}/analysis`),
        ]);
        rows.value = e.data;
        last.value = e.last_page;
        total.value = e.total;
        subjects.value = a.subjects;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
}
onMounted(async () => {
    try {
        await loadStudents();
        selectStudent(id);
        await load();
    } catch (e) {
        error.value = errorText(e);
        loading.value = false;
    }
});
function next(n: number) {
    page.value = n;
    load();
}
</script>
<template>
    <div class="page-title row">
        <div>
            <h1>成绩记录</h1>
            <p class="subtitle">
                {{ student?.name }} · 已记录 {{ total }} 次考试
            </p>
        </div>
        <RouterLink v-if="!readOnly" class="primary" :to="`/students/${id}/exams/new`"
            >＋ 录入成绩</RouterLink
        >
    </div>
    <div class="tabs">
        <button :class="{ active: tab === 'list' }" @click="tab = 'list'">
            考试记录</button
        ><button
            :class="{ active: tab === 'analysis' }"
            @click="tab = 'analysis'"
        >
            单科分析
        </button>
    </div>
    <p v-if="error" class="error" role="alert">
        {{ error }} <button @click="load">重试</button>
    </p>
    <div v-if="loading" class="empty">正在读取成绩…</div>
    <template v-else-if="tab === 'list'"
        ><RouterLink
            v-for="e in rows"
            :key="e.id"
            class="card exam-card"
            :to="`/exams/${e.id}`"
            ><div class="row">
                <div>
                    <h3>{{ e.title }}</h3>
                    <p class="subtitle">
                        {{ e.exam_date }} · {{ gradeLabel(e.grade_at_exam) }}
                    </p>
                </div>
                <span>›</span>
            </div>
            <div class="score-pills">
                <span v-for="s in e.subjects" :key="s.name"
                    ><small>{{ s.name }}</small
                    ><strong>{{
                        s.mode === "score" ? s.score : s.grade
                    }}</strong
                    ><em v-if="s.mode === 'score'">
                        / {{ s.max_score }}</em
                    ></span
                >
            </div>
            <div class="exam-foot">
                <span>{{ e.school_at_exam }}</span
                ><span v-if="e.subjects.some((s) => s.mode === 'score')"
                    >{{ subtotalLabel(e) }} {{ sumScore(e) }}</span
                >
            </div></RouterLink
        >
        <div v-if="!rows.length && !error" class="card empty">
            <h2>还没有考试记录</h2>
            <p>分数、等级都可以录入，科目也可以自由增减。</p>
            <RouterLink v-if="!readOnly" class="primary" :to="`/students/${id}/exams/new`"
                >录入第一次成绩</RouterLink
            >
        </div>
        <div v-if="last > 1" class="pager">
            <button
                class="secondary"
                :disabled="page === 1"
                @click="next(page - 1)"
            >
                上一页</button
            ><span>{{ page }} / {{ last }}</span
            ><button
                class="secondary"
                :disabled="page === last"
                @click="next(page + 1)"
            >
                下一页
            </button>
        </div></template
    ><template v-else
        ><div class="chips large">
            <button
                v-for="s in subjects"
                :key="s.key"
                class="chip"
                :class="{ active: current?.key === s.key }"
                @click="selected = s.key"
            >
                {{ s.name }}
            </button>
        </div>
        <template v-if="current"
            ><div class="card">
                <div class="section-head">
                    <h2>{{ current.name }} · 分数趋势</h2>
                    <span class="tag">{{ current.unit }}</span>
                </div>
                <div class="stats three">
                    <div>
                        <small>分数记录</small
                        ><strong>{{ current.score_count }}<em>次</em></strong>
                    </div>
                    <div>
                        <small>平均得分率</small
                        ><strong
                            >{{ current.mean_rate ?? "—"
                            }}<em v-if="current.mean_rate != null"
                                >%</em
                            ></strong
                        >
                    </div>
                    <div>
                        <small>{{
                            current.normalized ? "最好得分率" : "最高分"
                        }}</small
                        ><strong
                            >{{
                                (current.normalized
                                    ? current.best_rate
                                    : current.best_score) ?? "—"
                            }}<em v-if="current.best_rate != null">{{
                                current.normalized ? "%" : "分"
                            }}</em></strong
                        >
                    </div>
                </div>
                <TrendChart :subject="current" />
                <ul class="point-list">
                    <li v-for="(p, i) in current.points" :key="i">
                        <span>{{ p.date }} · {{ p.title }}</span
                        ><strong
                            >{{ p.score }} / {{ p.max_score }}
                            <small>({{ p.rate }}%)</small></strong
                        >
                    </li>
                </ul>
            </div>
            <div class="card spaced">
                <h2>{{ current.name }} · 等级分布</h2>
                <p class="subtitle">
                    等级独立统计，不换算分数 · {{ current.grade_count }} 条
                </p>
                <div
                    v-for="g in current.grades"
                    :key="g.label"
                    class="grade-row"
                >
                    <span>{{ g.label }}</span>
                    <div>
                        <i
                            :style="{
                                width:
                                    (g.count / current.grade_count) * 100 + '%',
                            }"
                        ></i>
                    </div>
                    <b>{{ g.count }} 次</b>
                </div>
                <div v-if="!current.grades.length" class="empty">
                    暂无等级成绩
                </div>
            </div></template
        >
        <div v-else-if="!error" class="card empty">
            录入成绩后即可查看单科趋势和等级分布
        </div></template
    >
</template>
