<?php

use App\Http\Controllers\Admin as A;
use App\Http\Controllers\ParentPortal as P;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Reject malformed IDs before MySQL can coerce strings to matching integer keys.
Route::pattern('id', '[1-9][0-9]*');
Route::pattern('student', '[1-9][0-9]*');

Route::get('/', fn () => redirect('/app/'));
Route::get('/app/login', fn () => view('app'));
Route::get('/app/register', fn () => view('app'));
Route::get('/app/agreement', fn (Request $r) => view('agreement', ['embedded' => $r->boolean('embedded')]));
Route::get('/admin/login', fn () => view('admin'));
Route::get('/app/{path?}', fn () => view('app'))->where('path', '.*')->middleware('portal:parent');
Route::get('/admin/{path?}', fn () => view('admin'))->where('path', '.*')->middleware('portal:admin');
Route::prefix('api/v1/app')->group(function () {
    Route::get('auth/csrf', fn (Request $r) => ['csrf_token' => $r->session()->token()]);
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
    });
});
Route::prefix('api/v1/admin')->group(function () {
    Route::get('auth/csrf', fn (Request $r) => ['csrf_token' => $r->session()->token()]);
    Route::post('auth/login', [A\AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware('portal:admin')->group(function () {
        Route::get('auth/me', [A\AuthController::class, 'me']);
        Route::post('auth/logout', [A\AuthController::class, 'logout']);
        foreach (['overview', 'parents', 'students', 'scores', 'exams', 'intentions', 'analysis'] as $action) {
            Route::get($action, [A\DataController::class, $action]);
        }
        Route::get('administrators', [A\AdministratorController::class, 'index']);
        Route::post('administrators', [A\AdministratorController::class, 'store'])->middleware('throttle:password-reset');
        Route::post('administrators/{id}/password', [A\AdministratorController::class, 'password'])->middleware('throttle:password-reset');
        Route::get('audit-logs', [A\DataController::class, 'audit']);
        Route::get('parents/{id}', [A\DataController::class, 'parentDetail']);
        Route::post('parents/{id}/reset-password', [A\DataController::class, 'resetPassword'])->middleware('throttle:password-reset');
        Route::get('students/{id}', [A\DataController::class, 'studentDetail']);
        Route::get('exams/{id}', [A\DataController::class, 'examDetail']);
        Route::get('exports/{kind}', [A\ExportController::class, 'download']);
    });
});
