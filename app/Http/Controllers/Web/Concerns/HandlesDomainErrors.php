<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The backend speaks JSON: {@see BusinessRuleException} subclasses render an API
 * envelope and policy denials throw {@see AuthorizationException}. The web layer
 * reuses those exact Services/Policies, so this trait translates their failures
 * into the redirect-back-with-error flow a browser expects — keeping error
 * handling DRY across every web controller instead of repeating try/catch.
 */
trait HandlesDomainErrors
{
    /**
     * Run a controller action, converting domain/authorization failures into a
     * redirect back with a flashed error message.
     *
     * @param  Closure(): RedirectResponse  $action
     */
    protected function handleDomain(Closure $action): RedirectResponse
    {
        try {
            return $action();
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (AuthorizationException $e) {
            return back()->withInput()->with('error', $e->getMessage() ?: 'Anda tidak diizinkan melakukan tindakan ini.');
        } catch (AccessDeniedHttpException $e) {
            return back()->withInput()->with('error', $e->getMessage() ?: 'Anda tidak diizinkan melakukan tindakan ini.');
        }
    }
}
