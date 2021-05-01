<?php

namespace Corbinjurgens\QTrans;

use Corbinjurgens\QTrans\Console\Commands\Refresh;
use Corbinjurgens\QTrans\Console\Commands\NameChange;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
	
	static $name = 'qtrans';
	
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
		
         // config
		$this->mergeConfigFrom(
			__DIR__.'/config/'.self::$name.'.php', self::$name
		);
		
		$this->app->singleton(self::$name, function () {
            return new Container();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
		
	  
		$this->publishes([
			__DIR__.'/config/'.self::$name.'.php' => config_path( self::$name.'.php' ),
		], self::$name. '-config');

		
    }
}
