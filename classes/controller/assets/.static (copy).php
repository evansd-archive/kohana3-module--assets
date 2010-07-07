<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Controller to serve up static content from application and module
 * directories.
 * 
 * This is useful for plugins which contain a mixture of static assets,
 * e.g. JavaScript, CSS, Flash and image files, which can be bundled
 * together in a single module and easily shared between multiple
 * projects.
 * 
 * #### Usage
 * 
 * The `assets.php` config file defines a set of 'routes' (similar to
 * the old Kohana-2 style routes) which map URLs to resource paths.
 * 
 * For example, if you had the following in your assets config:
 * 
 *     return array(
 *         'someflash.swf' => 'swf/someflash.swf'
 *     );
 * 
 * and then requested `/assets/someflash.swf`, the controller would
 * search your Kohana include paths looking for a file called
 * `someflash.swf` in a directory called `swf`.
 * 
 * More complex mappings are possible using regular expressions, e.g.:
 * 
 *     return array(
 *         'images/([\w]+\.jpg)' => 'media/images/$1'
 *     );
 * 
 * which would catch any request of the form `/assets/images/<file>.jpg`
 * and try to match it to a jpg file in a `media/images` directory
 * in your application or modules.
 * 
 * If you want to set specific config values for the parent [Assets][]
 * controller, e.g. to control caching behvaiour, you can use an array
 * instead of a string route. For example:
 * 
 *     return array(
 *         'images/([\w]+\.jpg)' => array(
 *             'route' => 'media/images/$1',
 *             'cache' => FALSE
 *          )
 *     );
 * 
 * This would disable caching for requests that match this particular
 * route.
 * 
 * The 'route' key defines the route, and any other settings are passed
 * straight through to the [Assets][] controller.
 * 
 * [Assets]: api/Controller_Assets
 *
 * @package    Assets
 * @author     David Evans
 * @copyright  (c) 2009-2010 David Evans
 * @license    MIT-style
 */
class Controller_Assets_Static extends Controller_Assets
{
	public function action_load($path)
	{
		// Loop through the routes and see if anything matches
		foreach ((array) Kohana::config('assets') as $key => $val)
		{
			if (preg_match('#^'.$key.'$#u', $path))
			{
				// If the supplied value is a config array ...
				if (is_array($val))
				{
					// ... get the mapped route ...
					$route = $val['route'];
					unset($val['route']);

					// ... and apply the rest of the config settings
					$this->apply_config($val);
				}

				// Otherwise treat the value as a simple routing string
				else
				{
					$route = $val;
				}

				if (strpos($route, '$') !== FALSE)
				{
					// Use regex routing
					$routed_path = preg_replace('#^'.$key.'$#u', $route, $path);
				}
				else
				{
					// Standard routing
					$routed_path = $route;
				}

				// A valid route has been found
				break;
			}
		}

		if (isset($routed_path))
		{
			$pathinfo = pathinfo($routed_path);

			$directories = explode('/', $pathinfo['dirname']);

			$first_directory = array_shift($directories);

			$path = join('/', $directories).'/'.$pathinfo['filename'];

			// Search for file using cascading file system, 404 if not found
			$file = Kohana::find_file($first_directory, $path, $pathinfo['extension']);
		}
		
		if ( ! empty($file))
		{
			$this->request->response = file_get_contents($file);
		}
		else
		{
			$this->request->status = 404;
		}
	}
}
