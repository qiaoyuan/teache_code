export class ApiError extends Error {
    constructor(
        public status: number,
        public code: string,
        message: string,
        public fields: Record<string, string[]> = {},
    ) {
        super(message);
    }
}
const portal = location.pathname.startsWith("/admin") ? "admin" : "app";
let csrf =
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content || "";
export async function api<T = any>(
    path: string,
    method = "GET",
    body?: unknown,
    retryCsrf = true,
): Promise<T> {
    const r = await fetch(`/api/v1/${portal}/${path}`, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = r.status === 204 ? {} : await r.json().catch(() => ({}));
    if (r.status === 419 && retryCsrf) {
        await refreshCsrf();
        return api<T>(path, method, body, false);
    }
    if (!r.ok) {
        const e = data.error || {};
        if (r.status === 401 && !path.startsWith("auth/"))
            window.dispatchEvent(new Event("session-expired"));
        if (e.code === "PASSWORD_CHANGE_REQUIRED")
            window.dispatchEvent(new Event("password-required"));
        throw new ApiError(
            r.status,
            e.code || "REQUEST_ERROR",
            e.message || "请求失败，请稍后再试",
            e.fields,
        );
    }
    if (data.csrf_token) csrf = data.csrf_token;
    return data;
}
export const errorText = (e: unknown) =>
    e instanceof ApiError
        ? Object.values(e.fields)[0]?.[0] || e.message
        : "网络连接失败，请检查网络后重试";
export async function refreshCsrf() {
    await api("auth/csrf");
}
export async function download(kind: string, filters: Record<string, string>) {
    const q = new URLSearchParams(filters);
    const r = await fetch(`/api/v1/admin/exports/${kind}?${q}`, {
        credentials: "same-origin",
        headers: {
            Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        },
    });
    if (!r.ok) {
        throw new Error("导出失败，请重新登录或稍后重试");
    }
    const url = URL.createObjectURL(await r.blob());
    const a = document.createElement("a");
    a.href = url;
    a.download = `成长档案-${kind}-${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
}
