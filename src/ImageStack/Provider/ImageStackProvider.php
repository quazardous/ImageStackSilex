<?php
namespace ImageStack\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use ImageStack\ImageBackend\FileImageBackend;
use ImageStack\ImageBackend\HttpImageBackend;
use ImageStack\ImageBackend\CacheImageBackend;
use ImageStack\ImageBackend\SequentialImageBackend;
use ImageStack\StorageBackend\FileStorageBackend;
use ImageStack\StorageBackend\OptimizedFileStorageBackend;
use ImageStack\ImageBackend\CallbackImageBackend;
use ImageStack\Cache\RawFileCache;
use ImageStack\ImageStack;
use ImageStack\ImageManipulator\ConverterImageManipulator;
use ImageStack\ImageManipulator\OptimizerImageManipulator;
use ImageStack\ImageOptimizer\JpegtranImageOptimizer;
use ImageStack\ImageOptimizer\PngcrushImageOptimizer;
use ImageStack\ImageManipulator\ThumbnailerImageManipulator;
use ImageStack\ImageManipulator\ThumbnailRule\PatternThumbnailRule;
use ImageStack\ImageBackend\PathRule\PatternPathRule;
use ImageStack\ImageBackend\PathRuleImageBackend;
use ImageStack\ImageManipulator\WatermarkImageManipulator;

class ImageStackProvider implements ServiceProviderInterface {
	
	function register(Container $app) {
	    // IMAGE STACKS
        $app['image.stacks.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            if (!isset($app['image.stacks.options'])) {
                $app['image.stacks.options'] = ['default' => isset($app['image.backend.options']) ? $app['image.backend.options'] : []];
            }
        });
	    
        $app['image.stacks'] = function ($app) {
            $app['image.stacks.options.initializer']();
            
            $stacks = new Container();
            foreach ($app['image.stacks.options'] as $name => $options) {
                $stacks[$name] = function ($stacks) use ($app, $options) {
                    return $app['image.stack_factory']($options);
                };
            }
            return $stacks;
        };
        
        $app['image.stack'] = function ($app) {
            $app['image.stacks.options.initializer']();
            
            $keys = array_keys($app['image.stacks.options']);
            if (in_array('default', $keys)) return $app['image.stacks']['default'];
            $key = reset($keys);
            return $app['image.stacks'][$key];
        };
	    
	    $app['image.stack_factory'] = $app->protect(function ($options) use ($app) {
	        $stack = new ImageStack($app['image.backend_loader']($options['backend']));
	        if (isset($options['manipulators'])) {
	            foreach ((array)$options['manipulators'] as $manipulator) {
	                $stack->addImageManipulator($app['image.manipulator_loader']($manipulator));
	            }
	        }
	        if (isset($options['storage'])) {
	            $stack->setStorageBackend($app['image.storage_loader']($options['storage']));
	        }
	        return $stack;
	    });
	    
	    // IMAGE BACKENDS
        $app['image.backends.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            if (!isset($app['image.backends.options'])) {
                $app['image.backends.options'] = ['default' => isset($app['image.backend.options']) ? $app['image.backend.options'] : []];
            }
        });
        
        $app['image.backends'] = function ($app) {
            $app['image.backends.options.initializer']();

            $backends = new Container();
            foreach ($app['image.backends.options'] as $name => $options) {
                $backends[$name] = function ($backends) use ($app, $options) {
                    $driver = $options['driver'];
                    unset($options['driver']);
                    return $app['image.backend_factory.' . $driver]($options);
                };
            }

            return $backends;
        };
        
        $app['image.backend'] = function ($app) {
            $app['image.backends.options.initializer']();
            
            $keys = array_keys($app['image.backends.options']);
            if (in_array('default', $keys)) return $app['image.backends']['default'];
            $key = reset($keys);
            return $app['image.backends'][$key];
        };
        
        $app['image.backend_factory.file'] = $app->protect(function ($options) use ($app) {
            $root = $options['root'];
            unset($options['root']);
            return new FileImageBackend($root, $options);
        });
        
        $app['image.backend_factory.http'] = $app->protect(function ($options) use ($app) {
            $rootUrl = $options['root_url'];
            unset($options['root_url']);
            return new HttpImageBackend($rootUrl, $options);
        });
        
        $app['image.backend_factory.cache'] = $app->protect(function ($options) use ($app) {
            $backend = $app['image.backend_loader']($options['backend']);
            $cache = $app['image.cache_loader']($options['cache']);
            unset($options['backend']);
            unset($options['cache']);
            return new CacheImageBackend($backend, $cache, $options);
        });

        $app['image.backend_factory.path_rule'] = $app->protect(function ($options) use ($app) {
            $backend = $app['image.backend_loader']($options['backend']);
            unset($options['backend']);
            $rules = [];
            if (isset($options['rules'])) {
                foreach ((array)$options['rules'] as $rule) {
                    $rules[] = $app['image.path_rule_factory']($rule);
                }
                unset($options['rules']);
            }
            return new PathRuleImageBackend($backend, $rules, $options);
        });
        
        $app['image.backend_factory.callback'] = $app->protect(function ($options) use ($app) {
            $callback = $options['callback'];
            unset($options['callback']);
            return new CallbackImageBackend($callback, $options);
        });
        
        $app['image.backend_factory.sequential'] = $app->protect(function ($options) use ($app) {
            $imageBackends = [];
            if (isset($options['backends'])) {
                foreach ($options['backends'] as $name) {
                    $imageBackends[] = $app['image.backend_loader']($name);
                }
                unset($options['backends']);
            }
            return new SequentialImageBackend($imageBackends, $options);
        });
        
        $app['image.backend_loader'] = $app->protect(function ($backend) use ($app) {
            if (is_callable($backend)) {
                return call_user_func($backend);
            } else {
                return $app['image.backends'][$backend];
            }
        });
        
        // STORAGE BACKENDS
        $app['image.storages.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            if (!isset($app['image.storages.options'])) {
                $app['image.storages.options'] = ['default' => isset($app['image.storage.options']) ? $app['image.storage.options'] : []];
            }
        });
        
        $app['image.storages'] = function ($app) {
            $app['image.storages.options.initializer']();

            $storages = new Container();
            foreach ($app['image.storages.options'] as $name => $options) {
                $storages[$name] = function ($storages) use ($app, $options) {
                    if (!is_array($options)) {
                        $options = [
                            'driver' => $options,
                        ]; 
                    }
                    $driver = $options['driver'];
                    unset($options['driver']);
                    return $app['image.storage_factory.' . $driver]($options);
                };
            }

            return $storages;
        };
                
        $app['image.storage'] = function ($app) {
            $app['image.storages.options.initializer']();
            
            $keys = array_keys($app['image.storages.options']);
            if (in_array('default', $keys)) return $app['image.storages']['default'];
            $key = reset($keys);
            return $app['image.storages'][$key];
        };
        
        $app['image.storage_factory.file'] = $app->protect(function ($options) use ($app) {
            $root = $options['root'];
            unset($options['root']);
            return new FileStorageBackend($root, $options);
        });
        
        $app['image.storage_factory.optimized_file'] = $app->protect(function ($options) use ($app) {
            $root = $options['root'];
            unset($options['root']);
            $optimizers = [];
            if (isset($options['optimizers'])) {
                foreach ((array)$options['optimizers'] as $optimizer) {
                    $optimizers[] = $app['image.optimizer_loader']($optimizer);
                }
                unset($options['optimizers']);
            }
            return new OptimizedFileStorageBackend($root, $optimizers, $options);
        });
        
        $app['image.storage_loader'] = $app->protect(function ($storage) use ($app) {
            if (is_callable($storage)) {
                return call_user_func($storage);
            } else {
                return $app['image.storages'][$storage];
            }
        });
        
        // IMAGE MANIPULATORS
        $app['image.manipulators.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            if (!isset($app['image.manipulators.options'])) {
                $app['image.manipulators.options'] = ['default' => isset($app['image.manipulator.options']) ? $app['image.manipulator.options'] : []];
            }
        });
        
        $app['image.manipulators'] = function ($app) {
            $app['image.manipulators.options.initializer']();

            $manipulators = new Container();
            foreach ($app['image.manipulators.options'] as $name => $options) {
                $manipulators[$name] = function ($manipulators) use ($app, $options) {
                    if (!is_array($options)) {
                        $options = [
                            'driver' => $options,
                        ]; 
                    }
                    $driver = $options['driver'];
                    unset($options['driver']);
                    return $app['image.manipulator_factory.' . $driver]($options);
                };
            }

            return $manipulators;
        };
        
        $app['image.manipulator_factory.converter'] = $app->protect(function ($options) use ($app) {
            $conversions = $options['conversions'];
            unset($options['conversions']);
            return new ConverterImageManipulator($app['imagine'], $conversions, $options);
        });

        $app['image.manipulator_factory.optimizer'] = $app->protect(function ($options) use ($app) {
            $optimizers = [];
            if (isset($options['optimizers'])) {
                foreach ((array)$options['optimizers'] as $optimizer) {
                    $optimizers[] = $app['image.optimizer_loader']($optimizer);
                }
            }
            return new OptimizerImageManipulator($optimizers);
        });
        
        $app['image.manipulator_factory.thumbnailer'] = $app->protect(function ($options) use ($app) {
            $rules = [];
            if (isset($options['rules'])) {
                foreach ((array)$options['rules'] as $rule) {
                    $rules[] = $app['image.thumbnail_rule_factory']($rule);
                }
            }
            return new ThumbnailerImageManipulator($app['imagine'], $rules);
        });

        $app['image.manipulator_factory.watermark'] = $app->protect(function ($options) use ($app) {
            $watermark = $options['watermark'];
            unset($options['watermark']);
            if (isset($options['anchor'])) {
                if (is_string($options['anchor'])) {
                    $anchor = 0x00;
                    foreach (preg_split('/[^A-Z]+/', strtoupper($options['anchor'])) as $v) {
                        $c = WatermarkImageManipulator::class . '::ANCHOR_' . $v;
                        if (!defined($c)) {
                            throw new \InvalidArgumentException(sprintf('Invalid anchor value: %s', $v));
                        }
                        $anchor |= constant($c);
                    }
                    $options['anchor'] = $anchor;
                }
            }
            if (isset($options['repeat'])) {
                if (is_string($options['repeat'])) {
                    $repeat = 0x00;
                    foreach (preg_split('/[^A-Z]+/', strtoupper($options['repeat'])) as $v) {
                        $c = WatermarkImageManipulator::class . '::REPEAT_' . $v;
                        if (!defined($c)) {
                            throw new \InvalidArgumentException(sprintf('Invalid repeat value: %s', $v));
                        }
                        $repeat |= constant($c);
                    }
                    $options['repeat'] = $repeat;
                }
            }
            if (isset($options['reduce'])) {
                if (is_string($options['reduce'])) {
                    $reduce = 0x00;
                    foreach (preg_split('/[^A-Z]+/', strtoupper($options['reduce'])) as $v) {
                        $c = WatermarkImageManipulator::class . '::REDUCE_' . $v;
                        if (!defined($c)) {
                            throw new \InvalidArgumentException(sprintf('Invalid reduce value: %s', $v));
                        }
                        $reduce |= constant($c);
                    }
                    $options['reduce'] = $reduce;
                }
            }
            return new WatermarkImageManipulator($app['imagine'], $watermark, $options);
        });
        
        $app['image.manipulator_loader'] = $app->protect(function ($manipulator) use ($app) {
            if (is_callable($manipulator)) {
                return call_user_func($manipulator);
            } else {
                return $app['image.manipulators'][$manipulator];
            }
        });
        
        // IMAGE OPTIMIZERS
        $app['image.optimizers.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;
            if ($initialized) return;
            $initialized = true;

            if (!isset($app['image.optimizers.options'])) {
                $app['image.optimizers.options'] = ['default' => isset($app['image.optimizer.options']) ? $app['image.optimizer.options'] : []];
            }
        });
        
        $app['image.optimizers'] = function ($app) {
            $app['image.optimizers.options.initializer']();

            $optimizers = new Container();
            foreach ($app['image.optimizers.options'] as $name => $options) {
                $optimizers[$name] = function ($optimizers) use ($app, $options) {
                    if (!is_array($options)) {
                        $options = [
                            'driver' => $options,
                        ]; 
                    }
                    $driver = $options['driver'];
                    unset($options['driver']);
                    return $app['image.optimizer_factory.' . $driver]($options);
                };
            }

            return $optimizers;
        };
        
        $app['image.optimizer_factory.jpegtran'] = $app->protect(function ($options) use ($app) {
            return new JpegtranImageOptimizer($options);
        });

        $app['image.optimizer_factory.pngcrush'] = $app->protect(function ($options) use ($app) {
            return new PngcrushImageOptimizer($options);
        });
        
        $app['image.optimizer_loader'] = $app->protect(function ($optimizer) use ($app) {
            if (is_callable($optimizer)) {
                return call_user_func($optimizer);
            } else {
                return $app['image.optimizers'][$optimizer];
            }
        });
        
        // OTHER
        $app['image.thumbnail_rule_factory'] = $app->protect(function ($rule) use ($app) {
            if (is_callable($rule)) {
                return call_user_func($rule);
            }
            if ([0, 1] === array_keys($rule)) {
                // pattern rule shortcut
                $rule = [
                    'pattern' => $rule[0],
                    'format' => $rule[1],
                ];
            }
            $rule += [
                'driver' => 'pattern',
            ];
            $driver = $rule['driver'];
            unset($rule['driver']);
            return $app['image.thumbnail_rule_factory.' . $driver]($rule);
        });
                
        $app['image.thumbnail_rule_factory.pattern'] = $app->protect(function ($options) use ($app) {
            return new PatternThumbnailRule($options['pattern'], $options['format']);
        });
        
        $app['image.path_rule_factory'] = $app->protect(function ($rule) use ($app) {
            if (is_callable($rule)) {
                return call_user_func($rule);
            }
            if ([0, 1] === array_keys($rule)) {
                // pattern rule shortcut
                $rule = [
                    'pattern' => $rule[0],
                    'output' => $rule[1],
                ];
            }
            $rule += [
                'driver' => 'pattern',
            ];
            $driver = $rule['driver'];
            unset($rule['driver']);
            return $app['image.path_rule_factory.' . $driver]($rule);
        });
        
        $app['image.path_rule_factory.pattern'] = $app->protect(function ($options) use ($app) {
            return new PatternPathRule($options['pattern'], $options['output']);
        });
        
        $app['image.cache_loader'] = $app->protect(function ($cache) use ($app) {
            if (is_callable($cache)) {
                return call_user_func($cache);
            } else {
                // https://github.com/sergiors/doctrine-cache-service-provider
                return $app['caches'][$cache];
            }
        });
        
        // add driver for raw file cache
        // https://github.com/sergiors/doctrine-cache-service-provider
        $app['cache_factory.raw_file'] = $app->protect(function ($options) use ($app) {
            $root = $options['root'];
            unset($options['root']);
            return new RawFileCache($root, $options);
        });
	}
}