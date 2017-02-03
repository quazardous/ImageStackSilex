<?php
namespace ImageStack\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ImageStack\Api\ImageStackInterface;
use Silex\Application;
use ImageStack\Api\Exception\ImageNotFoundException;
use ImageStack\ImagePath;

class ImageController {
	
	public function stackImage(Application $app, Request $request, $stack, $path) {
	    $prefix = $stack;
	    if (empty($app['image.stacks'][$stack])) {
	        $app->abort(404, 'Stack not found');
	    }
	    /** @var ImageStackInterface $stack */
	    $stack = $app['image.stacks'][$stack];
	    
        try {
            $image = $stack->stackImage(new ImagePath($path, $prefix));
        } catch (ImageNotFoundException $e) {
            $app->abort(404, 'Image not found');
        }

		if (!$image) {
			$app->abort(404, sprintf("%s not found", $path));
		}
		
		$response = new Response($image->getBinaryContent());
		$response->headers->set('Content-Type', $image->getMimeType());

		return $response;
	}
	
}