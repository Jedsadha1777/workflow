<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Database\Eloquent\Model;

class FilamentAuthenticate extends Middleware
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);
            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();
        $panel = Filament::getCurrentPanel();

        // เช็คว่าเป็น FilamentUser และไม่มีสิทธิ์เข้า panel
        $cannotAccess = $user instanceof FilamentUser && !$user->canAccessPanel($panel);
        
        // ถ้าไม่มีสิทธิ์ ให้ abort 403 ตามเดิม (ไม่ logout)
        if ($cannotAccess) {
            abort(403);
        }

        // เช็คกรณีที่ไม่ใช่ FilamentUser
        if (!($user instanceof FilamentUser) && config('app.env') !== 'local') {
            abort(403);
        }
    }

    protected function redirectTo($request): ?string
    {
        return Filament::getLoginUrl();
    }
}