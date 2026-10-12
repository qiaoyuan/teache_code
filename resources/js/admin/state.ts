import { reactive } from "vue";
import { api } from "../shared/api";
import type { User } from "../shared/types";
export const state = reactive({ user: null as User | null, ready: false });
export async function init() {
    if (state.ready) return;
    try {
        state.user = (await api("auth/me")).user;
    } catch {
        state.user = null;
    }
    state.ready = true;
}
