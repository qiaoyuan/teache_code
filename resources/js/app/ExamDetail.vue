<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, errorText } from "../shared/api";
import { readOnly } from "./state";
import type { Exam } from "../shared/types";
import { gradeLabel, sumScore, subtotalLabel } from "../shared/types";
const route = useRoute(),
    router = useRouter(),
    exam = ref<Exam>(),
    loading = ref(true),
    busy = ref(false),
    error = ref("");
onMounted(async () => {
    try {
        exam.value = (await api(`exams/${route.params.id}`)).data;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
});
async function remove() {
    if (!confirm("确定删除这次考试及全部科目成绩？")) return;
    busy.value = true;
    try {
        await api(`exams/${exam.value!.id}`, "DELETE");
        router.push(`/students/${exam.value!.student_id}/exams`);
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <div v-if="loading" class="empty">正在读取考试…</div>
    <template v-if="exam"
        ><div class="page-title">
            <RouterLink
                class="text-btn"
                :to="`/students/${exam.student_id}/exams`"
                >← 返回成绩记录</RouterLink
            >
            <h1>{{ exam.title }}</h1>
            <p class="subtitle">
                {{ exam.exam_date }} · {{ gradeLabel(exam.grade_at_exam) }} ·
                {{ exam.school_at_exam }}
            </p>
        </div>
        <section class="card form-card">
            <div class="section-head">
                <h2>本次各科成绩</h2>
                <RouterLink v-if="!readOnly" class="text-btn" :to="`/exams/${exam.id}/edit`"
                    >编辑成绩 →</RouterLink
                >
            </div>
            <div
                v-for="s in exam.subjects"
                :key="s.name"
                class="detail-subject"
            >
                <div class="row">
                    <strong>{{ s.name }}</strong
                    ><span class="detail-score"
                        >{{ s.mode === "score" ? s.score : s.grade
                        }}<small v-if="s.mode === 'score'">
                            / {{ s.max_score }}</small
                        ></span
                    >
                </div>
                <div v-if="s.mode === 'score'" class="score-bar">
                    <i
                        :style="{
                            width:
                                (Number(s.score) / Number(s.max_score)) * 100 +
                                '%',
                        }"
                    ></i>
                </div>
                <small class="hint">{{
                    s.mode === "score"
                        ? "得分率 " +
                          (
                              (Number(s.score) / Number(s.max_score)) *
                              100
                          ).toFixed(2) +
                          "%"
                        : "等级成绩 · 不换算分数"
                }}</small>
            </div>
            <div
                v-if="exam.subjects.some((s) => s.mode === 'score')"
                class="subtotal row"
            >
                <span>{{ subtotalLabel(exam) }}</span
                ><strong>{{ sumScore(exam) }}</strong>
            </div>
            <p class="hint">
                各科条形按得分率展示，便于对照本次考试；等级独立展示。
            </p>
            <p v-if="exam.note" class="note preserve">{{ exam.note }}</p>
            <div v-if="!readOnly" class="form-actions">
                <RouterLink class="primary" :to="`/exams/${exam.id}/edit`"
                    >修改成绩</RouterLink
                ><button class="danger" :disabled="busy" @click="remove">
                    删除本次考试
                </button>
            </div>
        </section></template
    >
</template>
