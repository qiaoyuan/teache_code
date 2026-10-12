<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import { ElMessage } from "element-plus";
import { api, errorText, download } from "../shared/api";
import type { SubjectAnalysis } from "../shared/types";
const loading = ref(true),
    error = ref(""),
    exporting = ref(false),
    stats = ref<Record<string, number>>({}),
    subjects = ref<SubjectAnalysis[]>([]),
    intentions = ref<
        { school: string; students: number; first: number; backup: number }[]
    >([]),
    selected = ref("");
const current = computed(
    () =>
        subjects.value.find((s) => s.key === selected.value) ||
        subjects.value[0],
);
const metrics = [
    { key: "parents", label: "注册家长", unit: "人", icon: "◯" },
    { key: "students", label: "在册学生", unit: "人", icon: "▤" },
    { key: "exams", label: "考试批次", unit: "次", icon: "▧" },
    { key: "subjects", label: "科目成绩", unit: "条", icon: "▥" },
    { key: "intentions", label: "有效意向", unit: "条", icon: "◇" },
];
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const [s, a] = await Promise.all([api("overview"), api("analysis")]);
        stats.value = s;
        subjects.value = a.subjects;
        selected.value =
            a.subjects.find((s: SubjectAnalysis) => s.grade_count > 0)?.key ||
            a.subjects[0]?.key ||
            "";
        intentions.value = a.intentions;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
}
onMounted(load);
async function exportAll() {
    exporting.value = true;
    try {
        await download("all", {});
        ElMessage.success("Excel 已生成");
    } catch {
        ElMessage.error("导出失败，请稍后重试");
    } finally {
        exporting.value = false;
    }
}
</script>
<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">OVERVIEW</p>
            <h1>数据总览</h1>
            <p class="muted">汇集每一份成长记录，掌握整体填报情况</p>
        </div>
        <el-button type="primary" :loading="exporting" @click="exportAll"
            >↓ 导出全部数据</el-button
        >
    </div>
    <el-alert
        v-if="error"
        :title="error"
        type="error"
        show-icon
        :closable="false"
    />
    <div v-loading="loading" class="overview-area">
        <div class="metric-grid">
            <article v-for="m in metrics" :key="m.key" class="metric-card">
                <div>
                    <span>{{ m.label }}</span
                    ><b>{{ m.icon }}</b>
                </div>
                <strong
                    >{{ stats[m.key] ?? "—"
                    }}<small>{{ m.unit }}</small></strong
                >
                <p>
                    {{
                        m.key === "exams"
                            ? "按考试批次统计"
                            : m.key === "subjects"
                              ? "分数和等级记录总数"
                              : m.key === "intentions"
                                ? "学生删除后不计入"
                                : "当前有效记录"
                    }}
                </p>
            </article>
        </div>
        <div class="overview-banner">
            <div>
                <h2>每一次填报，都让未来更清晰。</h2>
                <p>数据随家长填写实时更新 · 填报长期开放</p>
            </div>
            <span>✦</span>
        </div>
        <div class="dashboard-grid">
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>各科平均得分率</h2>
                        <p>每条成绩按自己的满分计算，再取算术平均</p>
                    </div>
                    <span class="tag">分数成绩</span>
                </div>
                <div
                    v-for="s in subjects.filter((s) => s.score_count)"
                    :key="s.key"
                    class="horizontal-bar"
                >
                    <span>{{ s.name }}</span>
                    <div><i :style="{ width: s.mean_rate + '%' }"></i></div>
                    <strong>{{ s.mean_rate }}%</strong
                    ><small>{{ s.score_count }} 条</small>
                </div>
                <el-empty
                    v-if="!subjects.some((s) => s.score_count)"
                    description="录入分数后可查看科目对比"
                    :image-size="80"
                />
            </section>
            <section class="panel">
                <div class="panel-heading">
                    <div>
                        <h2>等级分布</h2>
                        <p>按科目与等级标签独立统计</p>
                    </div>
                    <el-select
                        v-if="subjects.length"
                        v-model="selected"
                        :placeholder="current?.name"
                        style="width: 115px"
                        ><el-option
                            v-for="s in subjects"
                            :key="s.key"
                            :label="s.name"
                            :value="s.key"
                    /></el-select>
                </div>
                <template v-if="current?.grades.length"
                    ><div class="grade-total">
                        <strong>{{ current.grade_count }}</strong
                        ><span>条等级成绩 · {{ current.name }}</span>
                    </div>
                    <div
                        v-for="g in current.grades"
                        :key="g.label"
                        class="horizontal-bar"
                    >
                        <span>{{ g.label }}</span>
                        <div>
                            <i
                                :style="{
                                    width:
                                        (g.count / current.grade_count) * 100 +
                                        '%',
                                    background: '#a6bf79',
                                }"
                            ></i>
                        </div>
                        <strong>{{ g.count }} 条</strong>
                    </div></template
                ><el-empty
                    v-else
                    description="这个科目暂无等级成绩"
                    :image-size="80"
                />
            </section>
            <section class="panel wide">
                <div class="panel-heading">
                    <div>
                        <h2>意向学校汇总</h2>
                        <p>同一学生在同一学校只计一次，按人数排序</p>
                    </div>
                    <RouterLink to="/intentions">查看明细 →</RouterLink>
                </div>
                <el-table
                    :data="intentions"
                    style="width: 100%"
                    empty-text="暂无有效意向"
                    ><el-table-column
                        prop="school"
                        label="意向学校"
                        min-width="220" /><el-table-column
                        prop="students"
                        label="意向学生数"
                        width="150" /><el-table-column
                        prop="first"
                        label="优先"
                        width="130" /><el-table-column
                        prop="backup"
                        label="备选"
                        width="130"
                /></el-table>
            </section>
        </div>
    </div>
</template>
