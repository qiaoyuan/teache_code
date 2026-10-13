import { computed, reactive } from "vue";
import { api, ApiError } from "../shared/api";
import type { User, Student } from "../shared/types";
export const state = reactive({
    user: null as User | null,
    students: [] as Student[],
    selectedId: Number(sessionStorage.getItem("selected_student")) || null,
    ready: false,
    authorizedLogin: null as { admin_username: string; expires_at: number; read_only: boolean } | null,
});
export const readOnly = computed(() => state.authorizedLogin?.read_only === true);
export async function init() {
    if (state.ready) return;
    try {
        const result = await api("auth/me");
        state.user = result.user;
        state.authorizedLogin = result.authorized_login || null;
    } catch {
        state.user = null;
        state.authorizedLogin = null;
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
    try {
        await api("auth/logout", "POST");
    } catch (e) {
        // An expired authorization is already logged out on the server.
        if (!(e instanceof ApiError) || e.status !== 401) throw e;
    }
    state.user = null;
    state.authorizedLogin = null;
    state.students = [];
    selectStudent(null);
}
