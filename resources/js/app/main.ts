import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import Auth from "./Auth.vue";
import Home from "./Home.vue";
import StudentForm from "./StudentForm.vue";
import Exams from "./Exams.vue";
import ExamForm from "./ExamForm.vue";
import ExamDetail from "./ExamDetail.vue";
import Intentions from "./Intentions.vue";
import Account from "./Account.vue";
import { init, state } from "./state";
import "./style.css";
const router = createRouter({
    scrollBehavior: (_to, _from, saved) => saved || { top: 0 },
    history: createWebHistory("/app/"),
    routes: [
        { path: "/login", component: Auth },
        { path: "/register", component: Auth },
        { path: "/change-password", component: Auth },
        { path: "/", component: Home },
        { path: "/students/new", component: StudentForm },
        { path: "/students/:id/edit", component: StudentForm },
        { path: "/students/:id/exams", component: Exams },
        { path: "/students/:id/analysis", component: Exams },
        { path: "/students/:id/exams/new", component: ExamForm },
        { path: "/exams/:id", component: ExamDetail },
        { path: "/exams/:id/edit", component: ExamForm },
        { path: "/students/:id/intentions", component: Intentions },
        { path: "/account", component: Account },
        { path: "/:pathMatch(.*)*", redirect: "/" },
    ],
});
router.beforeEach(async (to) => {
    await init();
    if (!state.user && !["/login", "/register"].includes(to.path))
        return "/login";
    if (state.user?.must_change_password && to.path !== "/change-password")
        return "/change-password";
    if (state.user && ["/login", "/register"].includes(to.path)) return "/";
});
window.addEventListener("session-expired", () => {
    state.user = null;
    router.push("/login");
});
window.addEventListener("password-required", () => {
    router.push("/change-password");
});
createApp(App).use(router).mount("#app");
