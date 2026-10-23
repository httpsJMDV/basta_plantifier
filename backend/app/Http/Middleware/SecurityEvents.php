<?php

namespace App\Http\Middleware;

use App\Services\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SecurityEvents
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $error) {
            if ($error instanceof ValidationException && $request->allFiles()) {
                app(SecurityAudit::class)->write($request->user()?->id, 'security.upload_rejected', null, ['category' => 'validation', 'response_code' => 422]);
            }
            if ($error instanceof HttpExceptionInterface && $error->getStatusCode() === 429) {
                app(SecurityAudit::class)->write($request->user()?->id, 'security.throttled', null, ['response_code' => 429]);
            }
            throw $error;
        }
        if ($response->getStatusCode() === 422 && $request->allFiles()) {
            app(SecurityAudit::class)->write($request->user()?->id, 'security.upload_rejected', null, ['category' => 'validation', 'response_code' => 422]);
        }
        if ($response->getStatusCode() === 422 && $request->is('admin/mfa')) {
            app(SecurityAudit::class)->write($request->user()?->id, 'admin.mfa_failed', null, ['category' => 'authentication']);
        }
        if ($response->getStatusCode() === 413) {
            app(SecurityAudit::class)->write($request->user()?->id, 'security.upload_rejected', null, ['category' => 'size', 'response_code' => 413]);
        }
        if ($response->getStatusCode() === 429) {
            app(SecurityAudit::class)->write($request->user()?->id, 'security.throttled', null, ['response_code' => 429]);
        }

        return $response;
    }
}
