export interface User {
    id: number;
    phone?: string;
    username?: string;
    must_change_password?: boolean;
    created_at?: string;
    students_count?: number;
    students?: Student[];
}
export interface Student {
    id: number;
    parent_id: number;
    name: string;
    gender: string;
    school: string;
    admitted_schools: string[];
    class_number: number | null;
    entry_year: number | null;
    entry_grade: string | null;
    current_grade: string;
    contact_phone: string;
    exams_count?: number;
    intentions_count?: number;
    parent?: User;
    exams?: Exam[];
    intentions?: Intention[];
}
export interface Subject {
    id?: number;
    name: string;
    subject_key?: string;
    mode: "score" | "grade";
    score: string | number | null;
    grade: string | null;
    exam?: Exam;
}
export interface Exam {
    id: number;
    student_id: number;
    title: string;
    exam_date: string;
    grade_at_exam: string;
    school_at_exam: string;
    note: string;
    version: number;
    subjects: Subject[];
    student?: Student;
}
export interface Intention {
    id: number;
    student_id: number;
    school: string;
    level: "first" | "backup";
    note: string;
    student?: Student;
}
export interface Point {
    date: string;
    title: string;
    exam_id: number;
    score: number;
    value: number;
}
export interface SubjectAnalysis {
    key: string;
    name: string;
    score_count: number;
    grade_count: number;
    unit: string;
    mean_score: number | null;
    best_score: number | null;
    points: Point[];
    grades: { label: string; count: number }[];
}
export interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}
export const gradeOptions = Object.entries({
    P1: "小学一年级",
    P2: "小学二年级",
    P3: "小学三年级",
    P4: "小学四年级",
    P5: "小学五年级",
    P6: "小学六年级",
    J1: "初一",
    J2: "初二",
    J3: "初三",
    S1: "高一",
    S2: "高二",
    S3: "高三",
}).map(([value, label]) => ({ value, label }));
export const gradeLabel = (v: string) =>
    gradeOptions.find((g) => g.value === v)?.label || v;
export const admittedSchoolOptions = [
    "宜宾市一中",
    "宜宾市三中",
    "宜宾市四中",
    "宜宾市五中",
    "宜宾市六中",
    "三江成外k12",
    "叙州二中巴蜀班",
    "凉水井中学",
    "三江一中",
];
export const classGradeLabel = (grade: string) => gradeLabel(grade).replace(/^小学/, "");
export const classLabel = (student: Pick<Student, "current_grade" | "class_number">) =>
    `${classGradeLabel(student.current_grade)}${student.current_grade.startsWith("P") ? "" : " "}${student.class_number == null ? "（班号未填写）" : student.class_number + "班"}`;
export const sumScore = (e: Exam) =>
    e.subjects
        .filter((s) => s.mode === "score")
        .reduce((n, s) => n + Number(s.score), 0)
        .toFixed(2)
        .replace(/\.00$/, "");
export const subtotalLabel = (e: Exam) =>
    e.subjects.some((s) => s.mode === "grade")
        ? "分数科目合计"
        : "本次分数合计";
export const cleanSubject = (s: Subject) =>
    s.mode === "score"
        ? {
              name: s.name,
              mode: s.mode,
              score: s.score,
              grade: null,
          }
        : {
              name: s.name,
              mode: s.mode,
              score: null,
              grade: s.grade,
          };
