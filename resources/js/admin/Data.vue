<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { ElMessage } from "element-plus";
import { api, errorText, download } from "../shared/api";
import {
    gradeOptions,
    gradeLabel,
    subtotalLabel,
    sumScore,
} from "../shared/types";
import type { SubjectAnalysis } from "../shared/types";
import TrendChart from "../shared/TrendChart.vue";
const route = useRoute(),
    kind = computed(() => route.path.slice(1)),
    filters = reactive({ phone: "", school: "", grade: "" }),
    loading = ref(true),
    error = ref(""),
    page = ref(1),
    pageSize = ref(20),
    total = ref(0),
    rows = ref<any[]>([]),
    exporting = ref(false);
const titles: Record<string, string> = {
    parents: "家长账号",
    students: "学生档案",
    exams: "考试批次",
    scores: "科目成绩",
    intentions: "学校意向",
    "audit-logs": "操作日志",
};
const historic = computed(() => ["scores", "exams"].includes(kind.value));
const description = computed(() =>
    kind.value === "parents"
        ? "按注册手机号查看家长及名下学生，处理密码重置"
        : kind.value === "audit-logs"
          ? "查看管理员密码重置与数据导出记录"
          : historic.value
            ? "按考试发生时的学校、年级筛选，保留历史信息"
            : "按学生当前就读学校、当前年级筛选",
);
const columns = computed(
    () =>
        (({
            parents: [
                ["phone", "注册手机号", 170],
                ["students_count", "有效学生数", 130],
                ["must_change_password", "账号状态", 170],
                ["created_at", "注册时间", 190],
            ],
            students: [
                ["name", "学生姓名", 130],
                ["parent.phone", "注册家长手机号", 170],
                ["gender", "性别", 80],
                ["school", "当前就读学校", 200],
                ["current_grade", "当前年级", 130],
                ["entry_year", "入学年份", 110],
                ["contact_phone", "联系手机号", 170],
                ["exams_count", "考试次数", 100],
                ["intentions_count", "意向数", 100],
            ],
            exams: [
                ["title", "考试名称", 200],
                ["student.name", "学生姓名", 130],
                ["student.parent.phone", "注册家长手机号", 170],
                ["exam_date", "考试日期", 130],
                ["school_at_exam", "考试时学校", 200],
                ["grade_at_exam", "考试时年级", 130],
                ["subjects.length", "科目数", 100],
                ["note", "备注", 200],
            ],
            scores: [
                ["exam.student.parent.phone", "注册家长手机号", 170],
                ["exam.student.name", "学生姓名", 130],
                ["exam.title", "考试名称", 200],
                ["exam.exam_date", "考试日期", 130],
                ["exam.school_at_exam", "考试时学校", 200],
                ["exam.grade_at_exam", "考试时年级", 130],
                ["name", "科目", 110],
                ["mode", "记录方式", 100],
                ["score", "分数", 100],
                ["max_score", "满分", 100],
                ["rate", "得分率", 110],
                ["grade", "等级", 110],
            ],
            intentions: [
                ["student.parent.phone", "注册家长手机号", 170],
                ["student.name", "学生姓名", 130],
                ["student.school", "当前学校", 200],
                ["student.current_grade", "当前年级", 130],
                ["school", "意向学校", 220],
                ["level", "意向等级", 110],
                ["note", "备注", 220],
            ],
            "audit-logs": [
                ["created_at", "操作时间", 190],
                ["admin.username", "管理员", 130],
                ["action", "操作", 170],
                ["target", "对象/范围", 190],
                ["details_text", "详情", 300],
                ["ip_address", "IP 地址", 150],
            ],
        })[kind.value] || []) as [string, string, number][],
);
function value(row: any, key: string): string {
    if (key === "rate")
        return row.mode === "score"
            ? ((Number(row.score) / Number(row.max_score)) * 100).toFixed(2) +
                  "%"
            : "—";
    if (key === "details_text")
        return row.action === "data_export"
            ? JSON.stringify(row.details?.filters || {}) +
                  " · " +
                  JSON.stringify(row.details?.counts || {})
            : "临时密码已设置，要求下次登录改密";
    if (key === "target") return row.details?.phone || row.details?.kind || "—";
    let v = key.split(".").reduce((o, k) => o?.[k], row);
    if (key === "created_at")
        return new Date(v).toLocaleString("zh-CN", {
            timeZone: "Asia/Shanghai",
            hour12: false,
        });
    if (key.endsWith("grade_at_exam") || key.endsWith("current_grade"))
        return gradeLabel(v);
    if (key === "gender")
        return (
            { male: "男", female: "女", unspecified: "未填写" }[v as string] ||
            v
        );
    if (key === "mode") return v === "score" ? "分数" : "等级";
    if (key === "level") return v === "first" ? "优先" : "备选";
    if (key === "must_change_password") return v ? "待修改临时密码" : "正常";
    if (key === "action")
        return v === "parent_password_reset" ? "家长密码重置" : "数据导出";
    return v == null || v === "" ? "—" : String(v);
}
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const q = new URLSearchParams({
            ...filters,
            page: String(page.value),
            per_page: String(pageSize.value),
        });
        const d = await api(`${kind.value}?${q}`);
        rows.value = d.data;
        total.value = d.total;
    } catch (e) {
        error.value = errorText(e);
    } finally {
        loading.value = false;
    }
}
function search() {
    page.value = 1;
    load();
}
function clear() {
    Object.assign(filters, { phone: "", school: "", grade: "" });
    search();
}
onMounted(load);
async function exportRows() {
    exporting.value = true;
    try {
        await download(
            kind.value === "parents"
                ? "students"
                : kind.value === "exams"
                  ? "scores"
                  : kind.value,
            filters,
        );
        ElMessage.success("已导出全部符合条件的数据");
    } catch {
        ElMessage.error("导出失败，请稍后重试");
    } finally {
        exporting.value = false;
    }
}
const drawer = ref(false),
    detailLoading = ref(false),
    detail = ref<any>(null),
    detailType = ref(""),
    detailSubjects = ref<SubjectAnalysis[]>([]),
    selectedSubject = ref("");
const currentSubject = computed(
    () =>
        detailSubjects.value.find((s) => s.key === selectedSubject.value) ||
        detailSubjects.value[0],
);
async function show(row: any) {
    drawer.value = true;
    detailLoading.value = true;
    detail.value = null;
    detailSubjects.value = [];
    selectedSubject.value = "";
    try {
        const isExam = ["exams", "scores"].includes(kind.value);
        detailType.value = isExam
            ? "exam"
            : kind.value === "parents"
              ? "parent"
              : "student";
        const path =
            detailType.value === "exam"
                ? `exams/${kind.value === "scores" ? row.exam.id : row.id}`
                : detailType.value === "parent"
                  ? `parents/${row.id}`
                  : `students/${kind.value === "intentions" ? row.student.id : row.id}`;
        const d = await api(path);
        detail.value = d.data;
        detailSubjects.value = d.subjects || [];
    } catch (e) {
        ElMessage.error(errorText(e));
        drawer.value = false;
    } finally {
        detailLoading.value = false;
    }
}
const resetOpen = ref(false),
    resetTarget = ref<any>(null),
    resetBusy = ref(false),
    resetError = ref(""),
    resetForm = reactive({ password: "", password_confirmation: "" });
function openReset(row: any) {
    resetTarget.value = row;
    Object.assign(resetForm, { password: "", password_confirmation: "" });
    resetError.value = "";
    resetOpen.value = true;
}
async function reset() {
    if (resetBusy.value) return;
    resetBusy.value = true;
    resetError.value = "";
    try {
        await api(
            `parents/${resetTarget.value.id}/reset-password`,
            "POST",
            resetForm,
        );
        resetOpen.value = false;
        Object.assign(resetForm, { password: "", password_confirmation: "" });
        ElMessage.success("临时密码已设置，请通过您已有的联系渠道告知家长");
        await load();
    } catch (e) {
        resetError.value = errorText(e);
    } finally {
        resetBusy.value = false;
    }
}
</script>
<template>
    <div class="page-heading">
        <div>
            <p class="eyebrow">RECORDS & MANAGEMENT</p>
            <h1>{{ titles[kind] }}</h1>
            <p class="muted">{{ description }}</p>
        </div>
        <el-button
            v-if="kind !== 'audit-logs'"
            type="primary"
            :loading="exporting"
            @click="exportRows"
            >↓
            {{
                kind === "parents"
                    ? "导出关联学生"
                    : kind === "exams"
                      ? "导出科目成绩"
                      : "导出筛选结果"
            }}</el-button
        >
    </div>
    <section v-if="kind !== 'audit-logs'" class="panel filter-panel">
        <form class="filters" @submit.prevent="search">
            <label
                >家长注册手机号<el-input
                    v-model="filters.phone"
                    placeholder="支持部分手机号"
                    maxlength="11"
                    clearable /></label
            ><label
                >{{ historic ? "考试时学校" : "当前就读学校"
                }}<el-input
                    v-model="filters.school"
                    placeholder="输入学校关键词"
                    maxlength="160"
                    clearable /></label
            ><label
                >{{ historic ? "考试时年级" : "当前年级"
                }}<el-select
                    v-model="filters.grade"
                    placeholder="全部年级"
                    clearable
                    ><el-option
                        v-for="g in gradeOptions"
                        :key="g.value"
                        :value="g.value"
                        :label="g.label" /></el-select
            ></label>
            <div class="filter-buttons">
                <el-button
                    native-type="submit"
                    type="primary"
                    :loading="loading"
                    >查询</el-button
                ><el-button @click="clear">重置</el-button>
            </div>
        </form>
        <p class="filter-note">
            筛选条件按“并且”组合；导出包含全部匹配数据，跨越当前分页。
        </p>
    </section>
    <el-alert
        v-if="error"
        :title="error"
        type="error"
        :closable="false"
        show-icon
    />
    <section class="panel table-panel">
        <div class="table-heading">
            <h2>{{ titles[kind] }}一览</h2>
            <span
                >共 {{ total }}
                {{ kind === "parents" ? "个账号" : "条记录" }}</span
            >
        </div>
        <el-table
            v-loading="loading"
            :data="rows"
            style="width: 100%"
            empty-text="暂无符合条件的数据"
            stripe
            ><el-table-column
                v-for="[key, label, width] in columns"
                :key="key"
                :label="label"
                :min-width="width"
                show-overflow-tooltip
                ><template #default="scope"
                    ><span
                        :class="{
                            celltag: [
                                'level',
                                'mode',
                                'must_change_password',
                            ].includes(key),
                            priority:
                                key === 'level' && scope.row.level === 'first',
                        }"
                        >{{ value(scope.row, key) }}</span
                    ></template
                ></el-table-column
            ><el-table-column
                v-if="kind !== 'audit-logs'"
                label="操作"
                :width="kind === 'parents' ? 200 : 110"
                fixed="right"
                ><template #default="scope"
                    ><el-button link type="primary" @click="show(scope.row)"
                        >查看</el-button
                    ><el-button
                        v-if="kind === 'parents'"
                        link
                        type="primary"
                        @click="openReset(scope.row)"
                        >重置密码</el-button
                    ></template
                ></el-table-column
            ></el-table
        >
        <div class="pagination">
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="pageSize"
                :total="total"
                :page-sizes="[20, 50, 100]"
                layout="total, sizes, prev, pager, next"
                @current-change="load"
                @size-change="search"
            />
        </div>
    </section>
    <el-drawer v-model="drawer" title="记录详情" :size="'min(720px, 100vw)'"
        ><div v-loading="detailLoading" class="drawer-content">
            <template v-if="detail"
                ><template v-if="detailType === 'parent'"
                    ><h2>{{ detail.phone }}</h2>
                    <p class="muted">
                        注册家长 ·
                        {{
                            detail.must_change_password
                                ? "待修改临时密码"
                                : "正常"
                        }}
                    </p>
                    <h3>名下学生</h3>
                    <el-table
                        :data="detail.students"
                        empty-text="尚未创建学生档案"
                        ><el-table-column
                            prop="name"
                            label="姓名"
                        /><el-table-column
                            prop="school"
                            label="学校"
                        /><el-table-column label="年级"
                            ><template #default="s">{{
                                gradeLabel(s.row.current_grade)
                            }}</template></el-table-column
                        ></el-table
                    ></template
                ><template v-else-if="detailType === 'student'"
                    ><h2>{{ detail.name }}</h2>
                    <p class="muted">
                        {{ detail.school }} ·
                        {{ gradeLabel(detail.current_grade) }}
                    </p>
                    <el-descriptions :column="2" border
                        ><el-descriptions-item label="注册家长">{{
                            detail.parent.phone
                        }}</el-descriptions-item
                        ><el-descriptions-item label="联系手机号">{{
                            detail.contact_phone
                        }}</el-descriptions-item
                        ><el-descriptions-item label="入学年份">{{
                            detail.entry_year
                        }}</el-descriptions-item
                        ><el-descriptions-item label="入学年级">{{
                            gradeLabel(detail.entry_grade)
                        }}</el-descriptions-item></el-descriptions
                    >
                    <h3>单科成绩分析</h3>
                    <el-select
                        v-if="detailSubjects.length"
                        v-model="selectedSubject"
                        :placeholder="currentSubject?.name"
                        ><el-option
                            v-for="s in detailSubjects"
                            :key="s.key"
                            :label="s.name"
                            :value="s.key"
                    /></el-select>
                    <div v-if="currentSubject" class="drawer-chart">
                        <TrendChart :subject="currentSubject" />
                        <p class="muted">
                            平均得分率 {{ currentSubject.mean_rate ?? "—" }}% ·
                            最好得分率 {{ currentSubject.best_rate ?? "—" }}%
                        </p>
                        <p v-for="g in currentSubject.grades" :key="g.label">
                            等级 {{ g.label }} · {{ g.count }} 条
                        </p>
                    </div>
                    <el-empty v-else description="暂无成绩" :image-size="60" />
                    <h3>考试记录</h3>
                    <article
                        v-for="e in detail.exams"
                        :key="e.id"
                        class="drawer-record"
                    >
                        <div>
                            <strong>{{ e.title }}</strong
                            ><small
                                >{{ e.exam_date }} · {{ e.school_at_exam }} ·
                                {{ gradeLabel(e.grade_at_exam) }}</small
                            >
                        </div>
                        <p>
                            <span v-for="s in e.subjects" :key="s.id"
                                >{{ s.name }}
                                {{
                                    s.mode === "score"
                                        ? `${s.score} / ${s.max_score}`
                                        : s.grade
                                }}　</span
                            >
                        </p>
                    </article>
                    <h3>学校意向</h3>
                    <el-table
                        :data="detail.intentions"
                        empty-text="暂无学校意向"
                        ><el-table-column
                            prop="school"
                            label="学校" /><el-table-column
                            label="等级"
                            width="100"
                            ><template #default="s">{{
                                s.row.level === "first" ? "优先" : "备选"
                            }}</template></el-table-column
                        ><el-table-column
                            prop="note"
                            label="备注" /></el-table></template
                ><template v-else
                    ><h2>{{ detail.title }}</h2>
                    <p class="muted">
                        {{ detail.exam_date }} · {{ detail.student.name }} ·
                        {{ detail.student.parent.phone }}
                    </p>
                    <p class="muted">
                        {{ detail.school_at_exam }} ·
                        {{ gradeLabel(detail.grade_at_exam) }}
                    </p>
                    <el-table :data="detail.subjects"
                        ><el-table-column
                            prop="name"
                            label="科目"
                        /><el-table-column label="成绩"
                            ><template #default="s">{{
                                s.row.mode === "score"
                                    ? s.row.score + " / " + s.row.max_score
                                    : s.row.grade
                            }}</template></el-table-column
                        ><el-table-column label="得分率"
                            ><template #default="s">{{
                                s.row.mode === "score"
                                    ? (
                                          (Number(s.row.score) /
                                              Number(s.row.max_score)) *
                                          100
                                      ).toFixed(2) + "%"
                                    : "—"
                            }}</template></el-table-column
                        ></el-table
                    >
                    <p
                        v-if="
                            detail.subjects.some((s: any) => s.mode === 'score')
                        "
                    >
                        {{ subtotalLabel(detail) }} {{ sumScore(detail) }}
                    </p>
                    <p class="preserve">{{ detail.note }}</p></template
                ></template
            >
        </div></el-drawer
    ><el-dialog
        v-model="resetOpen"
        title="重置家长密码"
        width="min(460px, 94vw)"
        :close-on-click-modal="!resetBusy"
        :close-on-press-escape="!resetBusy"
        :show-close="!resetBusy"
        ><form @submit.prevent="reset">
            <el-alert
                :title="'注册手机号：' + resetTarget?.phone"
                type="info"
                :closable="false"
            />
            <p class="reset-help">
                设置临时密码后，家长现有登录会失效，下次登录必须修改密码。请通过已有联系渠道告知家长。
            </p>
            <label class="reset-field"
                >临时密码<el-input
                    v-model="resetForm.password"
                    type="password"
                    show-password
                    autocomplete="new-password"
                    placeholder="8–64 位，包含字母和数字"
                    maxlength="64" /></label
            ><label class="reset-field"
                >再次输入临时密码<el-input
                    v-model="resetForm.password_confirmation"
                    type="password"
                    show-password
                    autocomplete="new-password"
                    maxlength="64"
            /></label>
            <p v-if="resetError" role="alert" class="form-error">
                {{ resetError }}
            </p>
            <div class="dialog-actions">
                <el-button :disabled="resetBusy" @click="resetOpen = false"
                    >取消</el-button
                ><el-button
                    type="primary"
                    native-type="submit"
                    :loading="resetBusy"
                    >确认重置</el-button
                >
            </div>
        </form></el-dialog
    >
</template>
