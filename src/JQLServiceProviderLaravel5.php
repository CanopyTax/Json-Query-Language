<?php namespace Canopy\JQL;

use Illuminate\Support\ServiceProvider;

class JQLServiceProviderLaravel5 extends ServiceProvider
{

	/**
	 * Bootstrap the application events.
	 */
	public function boot()
	{
		//
	}

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
		$app['image'] = $app->share(function ($app) {
			return new ImageManager($app['config']->get('image'));
		});
		$app->alias('image', 'Intervention\Image\ImageManager');
	}

	/**
	 * Bootstrap imagecache
	 *
	 * @return void
	 */
	private function bootstrapImageCache()
	{
		$app = $this->app;
		$config = __DIR__ . '/../../../../imagecache/src/config/config.php';
		$this->publishes(array(
			$config => config_path('imagecache.php')
		));
		// merge default config
		$this->mergeConfigFrom($config, 'imagecache');
		// imagecache route
		if (is_string(config('imagecache.route'))) {
			$filename_pattern = '[ \w\\.\\/\\-\\@]+';
			// route to access template applied image file
			$app['router']->get(config('imagecache.route') . '/{template}/{filename}', array(
				'uses' => 'Intervention\Image\ImageCacheController@getResponse',
				'as' => 'imagecache'
			))->where(array('filename' => $filename_pattern));
		}
	}
}