<?php

declare(strict_types=1);

namespace IllumaLaw\LlmRouter;

use IllumaLaw\LlmRouter\Contracts\ProviderAvailability;
use IllumaLaw\LlmRouter\Support\ChainRowValidator;
use IllumaLaw\LlmRouter\Support\ConfigProviderAvailability;
use IllumaLaw\LlmRouter\Support\CooldownStore;
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
        $this->app->singleton(ChainRowValidator::class);
        $this->app->singleton(CooldownStore::class);
        $this->app->singleton(ConfigProviderAvailability::class);
        $this->app->alias(ConfigProviderAvailability::class, ProviderAvailability::class);

        $this->app->singleton(LLMRouterManager::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            return new LLMRouterManager($app);
        });

        $this->app->singleton(ChainResolver::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            /** @var LLMRouterManager $manager */
            $manager = $app->make(LLMRouterManager::class);

            return new ChainResolver($manager);
        });

        $this->app->alias(LLMRouterManager::class, 'llm-router');
    }
}
