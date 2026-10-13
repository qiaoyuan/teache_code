<script setup lang="ts">
import { onMounted, reactive, ref, nextTick } from "vue";
import { useRoute } from "vue-router";
import { api, errorText } from "../shared/api";
import type { Intention } from "../shared/types";
import { loadStudents, selectStudent, readOnly } from "./state";
const id = String(useRoute().params.id),
    rows = ref<Intention[]>([]),
    loading = ref(true),
    busy = ref(false),
    error = ref(""),
    formError = ref(""),
    dialog = ref<HTMLDialogElement>(),
    editing = ref<number | null>(null);
const form = reactive({
    school: "",
    level: "first" as "first" | "backup",
    note: "",
});
async function load() {
    loading.value = true;
    error.value = "";
    try {
        rows.value = (await api(`students/${id}/intentions`)).data;
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
async function open(row?: Intention) {
    editing.value = row?.id || null;
    Object.assign(form, {
        school: row?.school || "",
        level: row?.level || "first",
        note: row?.note || "",
    });
    formError.value = "";
    dialog.value?.showModal();
    await nextTick();
    dialog.value?.querySelector("input")?.focus();
}
async function save() {
    busy.value = true;
    formError.value = "";
    try {
        await api(
            editing.value
                ? `intentions/${editing.value}`
                : `students/${id}/intentions`,
            editing.value ? "PUT" : "POST",
            form,
        );
        dialog.value?.close();
        await load();
    } catch (e) {
        formError.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
async function remove(row: Intention) {
    if (!confirm(`确定删除对「${row.school}」的意向？`)) return;
    busy.value = true;
    try {
        await api(`intentions/${row.id}`, "DELETE");
        await load();
    } catch (e) {
        error.value = errorText(e);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <div class="page-title row">
        <div>
            <h1>学校意向</h1>
            <p class="subtitle">记录期待，也保留更多选择</p>
        </div>
        <button v-if="!readOnly" class="primary" @click="open()">＋ 添加意向</button>
    </div>
    <div class="step-note">
        意向等级只有“优先”和“备选”。可以填写多所优先学校，同一学校只保留一条有效记录。
    </div>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="empty">正在读取学校意向…</p>
    <template v-else
        ><section v-for="level in ['first', 'backup']" :key="level">
            <div class="section-head">
                <h2>{{ level === "first" ? "优先学校" : "备选学校" }}</h2>
                <span class="hint"
                    >{{ rows.filter((i) => i.level === level).length }} 所</span
                >
            </div>
            <article
                v-for="i in rows.filter((i) => i.level === level)"
                :key="i.id"
                class="card intention-card"
            >
                <div class="row">
                    <div class="school-heading">
                        <span
                            class="tile-icon"
                            :class="{ orange: level === 'backup' }"
                            >◇</span
                        >
                        <h3>{{ i.school }}</h3>
                    </div>
                    <span
                        class="tag"
                        :class="{ neutral: level === 'backup' }"
                        >{{ level === "first" ? "优先" : "备选" }}</span
                    >
                </div>
                <p v-if="i.note" class="subtitle preserve">{{ i.note }}</p>
                <div v-if="!readOnly" class="intention-actions">
                    <button class="text-btn" :disabled="busy" @click="open(i)">
                        编辑</button
                    ><button class="danger" :disabled="busy" @click="remove(i)">
                        删除
                    </button>
                </div>
            </article>
            <div v-if="!rows.some((i) => i.level === level)" class="card empty">
                暂未填写{{ level === "first" ? "优先" : "备选" }}学校
            </div>
        </section></template
    >
    <dialog
        ref="dialog"
        class="modal"
        aria-labelledby="intention-title"
        @cancel="busy && $event.preventDefault()"
    >
        <form @submit.prevent="save">
            <div class="modal-header">
                <h2 id="intention-title">
                    {{ editing ? "编辑学校意向" : "添加学校意向" }}
                </h2>
                <button
                    class="close"
                    type="button"
                    :disabled="busy"
                    @click="dialog?.close()"
                    aria-label="关闭"
                >
                    ×
                </button>
            </div>
            <div class="modal-body">
                <label class="field"
                    ><span>学校名称</span
                    ><input
                        v-model="form.school"
                        placeholder="请输入完整学校名称"
                        maxlength="160"
                        required /></label
                ><label class="field"
                    ><span>意向等级</span
                    ><select v-model="form.level">
                        <option value="first">优先</option>
                        <option value="backup">备选</option>
                    </select></label
                ><label class="field"
                    ><span>备注（选填）</span
                    ><textarea
                        v-model="form.note"
                        maxlength="2000"
                        placeholder="记录选择这所学校的原因"
                    ></textarea>
                </label>
                <p v-if="formError" role="alert" class="error">
                    {{ formError }}
                </p>
            </div>
            <div class="modal-footer">
                <button class="primary" :disabled="busy">
                    {{ busy ? "保存中…" : "保存意向" }}
                </button>
            </div>
        </form>
    </dialog>
</template>
