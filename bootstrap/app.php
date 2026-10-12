<?php

use App\Http\Middleware\EnsurePortal;
use App\Http\Middleware\PortalSession;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $m) {
        $m->prepend(PortalSession::class);
        $m->alias(['portal' => EnsurePortal::class]);
    })
    ->withExceptions(function (Exceptions $e) {
        $e->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
        $e->dontReport([UniqueConstraintViolationException::class]);
        $e->report(function (QueryException $ex) {
            Log::error('Database operation failed', ['sql_state' => $ex->errorInfo[0] ?? null]);

            return false;
        });
        $e->render(function (UniqueConstraintViolationException $ex, Request $r) {
            if ($r->is('api/*')) {
                return response()->json(['error' => ['code' => 'CONFLICT', 'message' => '该手机号、科目或学校已存在，请检查后重试']], 409);
            }
        });
        $e->render(function (ValidationException $ex, Request $r) {
            if ($r->is('api/*')) {
                return response()->json(['error' => ['code' => 'VALIDATION_ERROR', 'message' => '请检查填写的信息', 'fields' => $ex->errors()]], 400);
            }
        });
        $e->render(function (HttpExceptionInterface $ex, Request $r) {
            if (! $r->is('api/*')) {
                return null;
            } $status = $ex->getStatusCode();
            $codes = [401 => 'UNAUTHENTICATED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND', 409 => 'CONFLICT', 419 => 'CSRF_EXPIRED', 429 => 'RATE_LIMITED'];
            $messages = [401 => '请先登录', 403 => '无权访问此数据', 404 => '记录不存在或已删除', 409 => '数据发生冲突，请刷新后重试', 419 => '页面已过期，请刷新后重试', 429 => '操作过于频繁，请稍后再试'];

            return response()->json(['error' => ['code' => $codes[$status] ?? 'REQUEST_ERROR', 'message' => $messages[$status] ?? '请求失败']], $status, $ex->getHeaders());
        });
        $e->render(function (Throwable $ex, Request $r) {
            if ($r->is('api/*')) {
                return response()->json(['error' => ['code' => 'INTERNAL_ERROR', 'message' => '服务暂时不可用，请稍后重试']], 500);
            }
        });
    })->create();
