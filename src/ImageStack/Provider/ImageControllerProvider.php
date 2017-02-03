<?php
namespace ImageStack\Provider;

use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;
use ImageStack\Controller\ImageController;

class ImageControllerProvider implements ServiceProviderInterface, ControllerProviderInterface
{

	function register(Container $app) {
		$app['image.controller'] = function() use ($app) {
			return new ImageController();
		};
	}

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];
        
        $controllers->get('/{stack}/{path}', 'image.controller:stackImage')
            ->assert('stack', '[a-z0-9_]+')
            ->assert('path', '.+');
        
        return $controllers;
    }
}