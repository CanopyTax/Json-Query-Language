<?php namespace Canopy\JQL;

use Illuminate\Support\ServiceProvider;

class JQLServiceProviderLumen extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $app = $this->app;
        // merge default config
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'image');
        // create image
        $app['image'] = $app->share(
            function ($app) {
                return new ImageManager($app['config']->get('image'));
            }
        );
        $app->alias('image', 'Intervention\Image\ImageManager');
    }
}
