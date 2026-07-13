<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Prospect::class => \App\Policies\ProspectPolicy::class,
        \App\Models\Visit::class => \App\Policies\VisitPolicy::class,
        \App\Models\VisitPlanEntry::class => \App\Policies\VisitPlanEntryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
