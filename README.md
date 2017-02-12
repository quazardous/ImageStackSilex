# Silex ImageStack
A PHP image serving framework.

The main goal is to provide a robust framework to create an "on the fly" image thumbnailer generator similar to [imagecache](https://www.drupal.org/project/imagecache) / [image style](https://www.drupal.org/docs/8/core/modules/image/working-with-images) in [Drupal](https://www.drupal.org/).

Here is some [Silex](https://github.com/silexphp/Silex) provider for [ImageStack](https://github.com/quazardous/ImageStack).

See [SilexImageStackDemo](https://github.com/quazardous/SilexImageStackDemo) for more infos.

## Installation

    composer require quazardous/silex-imagestack

## Usage

Here is a Silex bootstrap:
```php
<?php
date_default_timezone_set('Europe/Paris');

include __DIR__.'/../vendor/autoload.php';

use Silex\Application;
$app = new Application();
$app['debug'] = true;

use Sergiors\Silex\Provider\DoctrineCacheServiceProvider;
$app->register(new DoctrineCacheServiceProvider(), [
    // default cache
    'cache.options' => [
        'driver' => 'raw_file',
        'root' => __DIR__ . '/../var/cache/pexels',
    ]
]);

use ImageStack\Provider\ImageStackProvider;
$app->register(new ImageStackProvider(), [
    'image.backends.options' => [
        // http backend on pexels.com
        'web' => [
            'driver' => 'http',
            'root_url' => 'https://static.pexels.com/photos/',
        ],
        // we create a backend that can cache photo from pexels.com locally
        'web_cache' => [
            'driver' => 'cache',
            'backend' => 'web',
            'cache' => 'default',
        ],
        // we create a backend that will rewrite path before fetching from pexels.com (cached)
        'web_final' => [
            'driver' => 'path_rule',
            'backend' => 'web_cache',
            'rules' => [
                ['@^((style|format)/[^/]+/)(.*)$@', [3]], // path is the 3rd parenthesis
                ['@^(original/)(.*)$@', [2]], // path is the 2nd parenthesis
            ],
        ],
    ],
    'image.manipulators.options' => [
        'thumbnails' => [
            'driver' => 'thumbnailer',
            'rules' => [
                ['@^style/big/.*$@', '<800x500'],
                ['@^style/small/.*$@', '300x200'],
                ['@^style/thumb/.*$@', '100'],
                ['@^format/([0-9]+)x([0-9]+)/.*$@', function ($macthes) { return sprintf('%sx%s', $macthes[1], $macthes[2]); }],
                ['@^original/.*$@', true], // original image
                ['/.*/', false], // trigger a 404
            ],
        ],
        'watermark' => [
            'driver' => 'watermark',
            'watermark' => __DIR__ . '/resources/coffee.png',
            'anchor' => 'bottom right',
        ],
    ],
    'image.storages.options' => [
        // mount the image on the web bootstrap base folder
        'img' => [
            'driver' => 'optimized_file',
            'root' => __DIR__ . '/../web/img/',
            'use_prefix' => true,
            'optimizers' => 'jpeg'
        ],
    ],
    // the stack
    'image.stacks.options' => [
        'pexels' => [
            'backend' => 'web_final',
            'manipulators' => ['thumbnails', 'watermark'],
            'storage' => 'img',
        ],
    ],
    'image.optimizers.options' => [
        'jpeg' => 'jpegtran',
    ],
]);

use ImageStack\Provider\ImagineProvider;
$app->register(new ImagineProvider());

$app['session.storage.handler'] = null; // no sessions

use Silex\Provider\ServiceControllerServiceProvider;
$app->register(new ServiceControllerServiceProvider());

use ImageStack\Provider\ImageControllerProvider;
$provider = new ImageControllerProvider();
$app->register($provider);
$app->mount('/img/', $provider);

return $app;
```

## Credits
[quazardous](https://github.com/quazardous).

## License
MIT