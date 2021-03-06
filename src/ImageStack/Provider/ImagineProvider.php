<?php
namespace ImageStack\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ImagineProvider implements ServiceProviderInterface {
	
	function register(Container $app) {
		$app['imagine'] = function() use ($app) {
			$driver = 'gd';
			if (isset($app['imagine.driver'])) {
				$driver = $app['imagine.driver'];
			}
			switch ($driver) {
				case 'imagick':
					return new \Imagine\Imagick\Imagine();
				case 'gmagick':
					return new \Imagine\Gmagick\Imagine();
				default:
					return new \Imagine\Gd\Imagine();
			}
		};
	}
}