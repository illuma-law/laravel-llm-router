<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LLMRouterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-llm-router')
            ->hasConfigFile('llm-router');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FailureClassifier::class);

        $this->app->singleton(FailoverRunner::class);

        $this->app->singleton(LLMRouterManager::class, function ($app) {
            return new LLMRouterManager($app);
        });

        $this->app->singleton(ChainResolver::class, function ($app) {
            return new ChainResolver($app->make(LLMRouterManager::class));
        });

        $this->app->alias(LLMRouterManager::class, 'llm-router');
    }
}
