import { reactive } from "vue";
import { api } from "../shared/api";
import type { User, Student } from "../shared/types";
export const state = reactive({
    user: null as User | null,
    students: [] as Student[],
    selectedId: Number(sessionStorage.getItem("selected_student")) || null,
    ready: false,
});
export async function init() {
    if (state.ready) return;
    try {
        state.user = (await api("auth/me")).user;
    } catch {
        state.user = null;
    }
    state.ready = true;
}
export async function loadStudents() {
    state.students = (await api("students")).data;
    if (!state.students.some((s) => s.id === state.selectedId))
        selectStudent(state.students[0]?.id || null);
}
export function selectStudent(id: number | string | null) {
    const value = Number(id);
    state.selectedId = Number.isSafeInteger(value) && value > 0 ? value : null;
    if (state.selectedId)
        sessionStorage.setItem("selected_student", String(state.selectedId));
    else sessionStorage.removeItem("selected_student");
}
export async function logout() {
    await api("auth/logout", "POST");
    state.user = null;
    state.students = [];
    selectStudent(null);
}
