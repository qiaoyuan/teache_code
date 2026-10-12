import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import {
    ElAlert,
    ElButton,
    ElConfigProvider,
    ElDescriptions,
    ElDescriptionsItem,
    ElDialog,
    ElDrawer,
    ElEmpty,
    ElInput,
    ElLoading,
    ElOption,
    ElPagination,
    ElSelect,
    ElTable,
    ElTableColumn,
} from "element-plus";
import "element-plus/dist/index.css";
import App from "./App.vue";
import Login from "./Login.vue";
import Overview from "./Overview.vue";
import Data from "./Data.vue";
import { init, state } from "./state";
import "./style.css";
const router = createRouter({
    scrollBehavior: (_to, _from, saved) => saved || { top: 0 },
    history: createWebHistory("/admin/"),
    routes: [
        { path: "/login", component: Login },
        { path: "/", component: Overview },
        ...[
            "parents",
            "students",
            "scores",
            "exams",
            "intentions",
            "audit-logs",
        ].map((p) => ({ path: `/${p}`, component: Data })),
        { path: "/:pathMatch(.*)*", redirect: "/" },
    ],
});
router.beforeEach(async (to) => {
    await init();
    if (!state.user && to.path !== "/login") return "/login";
    if (state.user && to.path === "/login") return "/";
});
window.addEventListener("session-expired", () => {
    state.user = null;
    router.push("/login");
});
const app = createApp(App).use(router);
for (const component of [
    ElAlert,
    ElButton,
    ElConfigProvider,
    ElDescriptions,
    ElDescriptionsItem,
    ElDialog,
    ElDrawer,
    ElEmpty,
    ElInput,
    ElOption,
    ElPagination,
    ElSelect,
    ElTable,
    ElTableColumn,
])
    app.component(component.name!, component);
app.use(ElLoading).mount("#admin");
