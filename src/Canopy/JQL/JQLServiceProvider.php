<?php namespace Canopy\JQL;

use Illuminate\Support\ServiceProvider;

class JQLServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;
	/**
	 * Actual provider
	 *
	 * @var \Illuminate\Support\ServiceProvider
	 */
	protected $provider;

	/**
	 * Create a new service provider instance.
	 *
	 * @param  \Illuminate\Contracts\Foundation\Application $app
	 */
	public function __construct($app)
	{
		parent::__construct($app);
		$this->provider = $this->getProvider();
	}

	/**
	 * Bootstrap the application events.
	 *
	 */
	public function boot()
	{
		return $this->provider->boot();
	}

	/**
	 * Register the service provider.
	 */
	public function register()
	{
		return $this->provider->register();
	}

	/**
	 * Return ServiceProvider according to Laravel version
	 */
	private function getProvider()
	{
		if ($this->app instanceof \Laravel\Lumen\Application) {
			$provider = \Canopy\JQL\JQLServiceProviderLumen::class;
		} elseif (version_compare(\Illuminate\Foundation\Application::VERSION, '5.0', '<')) {
			$provider = \Canopy\JQL\JQLServiceProviderLaravel4::class;
		} else {
			$provider = \Canopy\JQL\JQLServiceProviderLaravel5::class;
		}
		return new $provider($this->app);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('jql');
	}
}
