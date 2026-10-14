# 项目结构快照（自动生成）

项目目录：`/Users/qiaoyuan/www/php/teache_code`
设计版本：V1.11
源文件指纹：`9a4954fccb247b9a74d292eb3952dd43571a53bbd2ed02cb9a73e7ef51ec9c5a`

来源是当前磁盘文件，不代表测试通过、需求确认或部署完成。不读取数据库、环境凭据或聊天。

开发依据：项目根目录的 `docs/开发设计文档.md`、`docs/AI开发指令.md` 和 `README.md`。

## 锁定依赖

| 依赖 | 版本 |
| --- | --- |
| PHP 要求 | ^8.4 |
| Laravel | v13.35.0 |
| vue | 3.5.43 |
| typescript | 5.9.3 |
| vite | 8.3.4 |
| element-plus | 2.14.7 |

## 页面和接口声明

以下直接提取 `routes/web.php` 的声明，需结合 prefix、middleware 和 foreach 理解完整路径；不是运行时 route:list。

```php
Route::pattern('id', '[1-9][0-9]*');
Route::pattern('student', '[1-9][0-9]*');
Route::get('/', fn () => redirect('/app/'));
Route::get('/app/authorized-login', fn () => response()->view('app')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer'));
Route::get('/app/login', fn () => view('app'));
Route::get('/app/register', fn () => view('app'));
Route::get('/app/agreement', fn (Request $r) => view('agreement', ['embedded' => $r->boolean('embedded')]));
Route::get('/admin/login', fn () => view('admin'));
Route::get('/app/{path?}', fn () => view('app'))->where('path', '.*')->middleware('portal:parent');
Route::get('/admin/{path?}', fn () => view('admin'))->where('path', '.*')->middleware('portal:admin');
Route::prefix('api/v1/app')->group(function () {
Route::get('auth/csrf', fn (Request $r) => ['csrf_token' => $r->session()->token()]);
Route::post('auth/authorized-login', [P\AuthorizedLoginController::class, 'store'])->middleware('throttle:authorized-login');
Route::post('auth/register', [P\AuthController::class, 'register'])->middleware('throttle:register');
Route::post('auth/login', [P\AuthController::class, 'login'])->middleware('throttle:login');
Route::middleware('portal:parent')->group(function () {
Route::get('auth/me', [P\AuthController::class, 'me']);
Route::post('auth/logout', [P\AuthController::class, 'logout']);
Route::post('auth/change-password', [P\AuthController::class, 'changePassword']);
Route::get('students', [P\StudentController::class, 'index']);
Route::post('students', [P\StudentController::class, 'store']);
Route::get('students/{id}', [P\StudentController::class, 'show']);
Route::put('students/{id}', [P\StudentController::class, 'update']);
Route::delete('students/{id}', [P\StudentController::class, 'destroy']);
Route::get('students/{student}/exams', [P\ExamController::class, 'index']);
Route::post('students/{student}/exams', [P\ExamController::class, 'store']);
Route::get('students/{student}/analysis', [P\ExamController::class, 'analysis']);
Route::get('exams/{id}', [P\ExamController::class, 'show']);
Route::put('exams/{id}', [P\ExamController::class, 'update']);
Route::delete('exams/{id}', [P\ExamController::class, 'destroy']);
Route::get('students/{student}/intentions', [P\IntentionController::class, 'index']);
Route::post('students/{student}/intentions', [P\IntentionController::class, 'store']);
Route::put('intentions/{id}', [P\IntentionController::class, 'update']);
Route::delete('intentions/{id}', [P\IntentionController::class, 'destroy']);
Route::prefix('api/v1/admin')->group(function () {
Route::get('auth/csrf', fn (Request $r) => ['csrf_token' => $r->session()->token()]);
Route::post('auth/login', [A\AuthController::class, 'login'])->middleware('throttle:login');
Route::middleware('portal:admin')->group(function () {
Route::get('auth/me', [A\AuthController::class, 'me']);
Route::post('auth/logout', [A\AuthController::class, 'logout']);
foreach (['overview', 'parents', 'students', 'scores', 'exams', 'intentions', 'analysis'] as $action) {
Route::get($action, [A\DataController::class, $action]);
Route::get('administrators', [A\AdministratorController::class, 'index']);
Route::post('administrators', [A\AdministratorController::class, 'store'])->middleware('throttle:password-reset');
Route::post('administrators/{id}/password', [A\AdministratorController::class, 'password'])->middleware('throttle:password-reset');
Route::get('audit-logs', [A\DataController::class, 'audit']);
Route::get('parents/{id}', [A\DataController::class, 'parentDetail']);
Route::post('parents/{id}/authorize-login', [A\ParentAuthorizationController::class, 'store'])->middleware('throttle:password-reset');
Route::post('parents/{id}/reset-password', [A\DataController::class, 'resetPassword'])->middleware('throttle:password-reset');
Route::get('students/{id}', [A\DataController::class, 'studentDetail']);
Route::get('exams/{id}', [A\DataController::class, 'examDetail']);
Route::get('exports/{kind}', [A\ExportController::class, 'download']);
```

## 模型

- `app/Models/Administrator.php`
- `app/Models/AuditLog.php`
- `app/Models/Exam.php`
- `app/Models/ExamSubject.php`
- `app/Models/Intention.php`
- `app/Models/ParentAccount.php`
- `app/Models/Student.php`

## 控制器与请求校验

- `app/Http/Controllers/Admin/AdministratorController.php`
- `app/Http/Controllers/Admin/AuthController.php`
- `app/Http/Controllers/Admin/DataController.php`
- `app/Http/Controllers/Admin/ExportController.php`
- `app/Http/Controllers/Admin/ParentAuthorizationController.php`
- `app/Http/Controllers/Controller.php`
- `app/Http/Controllers/ParentPortal/AuthController.php`
- `app/Http/Controllers/ParentPortal/AuthorizedLoginController.php`
- `app/Http/Controllers/ParentPortal/ExamController.php`
- `app/Http/Controllers/ParentPortal/IntentionController.php`
- `app/Http/Controllers/ParentPortal/StudentController.php`
- `app/Http/Requests/Admin/ResetPasswordRequest.php`
- `app/Http/Requests/ParentPortal/ExamRequest.php`
- `app/Http/Requests/ParentPortal/IntentionRequest.php`
- `app/Http/Requests/ParentPortal/StudentRequest.php`

## 服务、授权与中间件

- `app/Http/Middleware/EnsurePortal.php`
- `app/Http/Middleware/PortalSession.php`
- `app/Policies/StudentPolicy.php`
- `app/Services/Admin/ExcelExport.php`
- `app/Services/Admin/ParentAuthorization.php`
- `app/Services/Admin/PasswordReset.php`
- `app/Services/Admin/Queries.php`
- `app/Services/Admin/Statistics.php`
- `app/Services/ParentPortal/Records.php`
- `app/Services/Shared/Analysis.php`
- `app/Services/Shared/Text.php`

## 数据库迁移与初始化

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`
- `database/migrations/2026_10_12_000001_create_growth_tables.php`
- `database/migrations/2026_10_12_000002_use_binary_normalized_keys.php`
- `database/migrations/2026_10_12_000003_widen_student_gender.php`
- `database/migrations/2026_10_12_000004_convert_business_ids_to_integers.php`
- `database/migrations/2026_10_12_000005_put_id_first_and_use_datetime.php`
- `database/migrations/2026_10_13_000001_add_registration_agreement_to_parents.php`
- `database/migrations/2026_10_13_000002_add_auth_version_to_administrators.php`
- `database/migrations/2026_10_13_000003_add_class_number_to_students.php`
- `database/migrations/2026_10_14_000001_add_admitted_school_to_students.php`
- `database/seeders/DatabaseSeeder.php`

## 前端与模板

- `resources/js/admin/Administrators.vue`
- `resources/js/admin/App.vue`
- `resources/js/admin/AuthorizeParentLogin.vue`
- `resources/js/admin/Data.vue`
- `resources/js/admin/Login.vue`
- `resources/js/admin/Overview.vue`
- `resources/js/admin/main.ts`
- `resources/js/admin/state.ts`
- `resources/js/admin/style.css`
- `resources/js/app/Account.vue`
- `resources/js/app/App.vue`
- `resources/js/app/Auth.vue`
- `resources/js/app/AuthorizedLogin.vue`
- `resources/js/app/ExamDetail.vue`
- `resources/js/app/ExamForm.vue`
- `resources/js/app/Exams.vue`
- `resources/js/app/Home.vue`
- `resources/js/app/Intentions.vue`
- `resources/js/app/StudentForm.vue`
- `resources/js/app/main.ts`
- `resources/js/app/state.ts`
- `resources/js/app/style.css`
- `resources/js/shared/TrendChart.vue`
- `resources/js/shared/api.ts`
- `resources/js/shared/types.ts`
- `resources/views/admin.blade.php`
- `resources/views/agreement.blade.php`
- `resources/views/app.blade.php`

## 测试

- `tests/Feature/AdministratorManagementTest.php`
- `tests/Feature/ExampleTest.php`
- `tests/Feature/GrowthSystemTest.php`
- `tests/Feature/ParentAuthorizationTest.php`
- `tests/Feature/RegistrationAgreementTest.php`
- `tests/Feature/StudentAdmissionTest.php`
- `tests/Feature/StudentClassTest.php`
- `tests/TestCase.php`
- `tests/skills/sync-project-skill.test.mjs`

## 运行、部署与同步

- `deploy/README.md`
- `scripts/node`
- `scripts/php`
- `scripts/preview-seed.php`
- `scripts/sync-project-skill.mjs`

