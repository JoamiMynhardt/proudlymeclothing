<?php

namespace Botble\Theme\Providers;

use Botble\Base\Supports\Helper;
use Botble\Theme\Commands\ThemeAssetsPublishCommand;
use Botble\Theme\Commands\ThemeAssetsRemoveCommand;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Botble\Theme\Commands\ThemeActivateCommand;
use Botble\Theme\Commands\ThemeRemoveCommand;
use Botble\Theme\Contracts\Theme as ThemeContract;
use Botble\Theme\Http\Middleware\AdminBarMiddleware;
use Botble\Theme\Theme;
use Event;
use File;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Theme as ThemeFacade;

class ThemeServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register()
    {
        /**
         * @var Router $router
         */
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', AdminBarMiddleware::class);

        Helper::autoload(__DIR__ . '/../../helpers');

        $this->app->bind(ThemeContract::class, Theme::class);

        $this->commands([
            ThemeActivateCommand::class,
            ThemeRemoveCommand::class,
            ThemeAssetsPublishCommand::class,
            ThemeAssetsRemoveCommand::class,
        ]);
    }

    public function boot()
    {
        $this->setNamespace('packages/theme')
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->loadRoutes(['web'])
            ->publishAssets();

        $this->app->register(HookServiceProvider::class);

        Event::listen(RouteMatched::class, function () {
            dashboard_menu()
                ->registerItem([
                    'id'          => 'cms-core-appearance',
                    'priority'    => 996,
                    'parent_id'   => null,
                    'name'        => 'core/base::layouts.appearance',
                    'icon'        => 'fa fa-paint-brush',
                    'url'         => '#',
                    'permissions' => [],
                ])
                ->registerItem([
                    'id'          => 'cms-core-theme',
                    'priority'    => 1,
                    'parent_id'   => 'cms-core-appearance',
                    'name'        => 'packages/theme::theme.name',
                    'icon'        => null,
                    'url'         => route('theme.index'),
                    'permissions' => ['theme.index'],
                ])
                ->registerItem([
                    'id'          => 'cms-core-theme-option',
                    'priority'    => 4,
                    'parent_id'   => 'cms-core-appearance',
                    'name'        => 'packages/theme::theme.theme_options',
                    'icon'        => null,
                    'url'         => route('theme.options'),
                    'permissions' => ['theme.options'],
                ])
                ->registerItem([
                    'id'          => 'cms-core-appearance-custom-css',
                    'priority'    => 5,
                    'parent_id'   => 'cms-core-appearance',
                    'name'        => 'packages/theme::theme.custom_css',
                    'icon'        => null,
                    'url'         => route('theme.custom-css'),
                    'permissions' => ['theme.custom-css'],
                ]);

            admin_bar()
                ->registerLink(trans('packages/theme::theme.name'), route('theme.index'), 'appearance')
                ->registerLink(trans('packages/theme::theme.theme_options'), route('theme.options'), 'appearance');
        });

        $this->app->booted(function () {
            $theme = ThemeFacade::getThemeName();
            if ($theme) {
                $file = 'themes/' . $theme . '/css/style.integration.css';
                if (File::exists(public_path($file))) {
                    ThemeFacade::asset()
                        ->container('after_header')
                        ->add('theme-style-integration-css', $file);
                }
            }
        });

        $this->app->register(ThemeManagementServiceProvider::class);
    }
}
