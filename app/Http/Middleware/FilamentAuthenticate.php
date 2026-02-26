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

        if ($user instanceof FilamentUser && !$user->canAccessPanel($panel)) {
            $url = $user->isAdmin() ? '/admin' : '/';
            abort(redirect($url));
         }
     }
     protected function redirectTo($request): ?string
     {
         return Filament::getLoginUrl();
     }
 }