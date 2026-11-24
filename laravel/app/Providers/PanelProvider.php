<?php
namespace KarlixLaravel\Providers;
final class PanelProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->app->singleton('session', \KarlixLaravel\Session\SessionManager::class);
        config(['session.driver' => 'karlix-shared']);
        \Illuminate\Support\Facades\Gate::policy(\KarlixLaravel\Models\Task::class, \KarlixLaravel\Policies\TaskPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\KarlixLaravel\Models\Project::class, \KarlixLaravel\Policies\ProjectPolicy::class);
        \Illuminate\Support\Facades\Auth::viaRequest('karlix', static function () {
            $r = \App\Core\Request::capture();
            $id = $r->params['_api_user'] ?? (\KarlixLaravel\Auth\Authentication::user()['id'] ?? null);
            return $id ? \KarlixLaravel\Models\User::find($id) : null;
        });
        config(['auth.defaults.guard' => 'karlix', 'auth.guards.karlix' => ['driver' => 'karlix']]);
    }
}
