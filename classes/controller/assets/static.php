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
 * Create a route in your `bootstrap.php` or `init.php` file pointing to
 * the `assets/static` controller. The `file` parameter determines which
 * file will be loaded.
 * 
 * Example:
 * 
 *     Route::set('my_flash_plugin', 'assets/flash-plugin.swf')
 *       ->defaults(array(
 *         'directory'  => 'assets',
 *         'controller' => 'static',
 *         'file'       => 'swf/flash_plugin.swf'
 *       ));
 * 
 * When a request is made for `/assets/flash-plugin.swf`, the controller
 * will search your Kohana include paths for a file called
 * `flash_plugin.swf` in a directory called `swf`. It will then serve
 * this up with the correct content-type and handle any caching required
 * (see the [Assets][] controller).
 * 
 * More complex mappings are possible by substituting request parameters
 * into the `file` parameter, e.g.,
 * 
 *     Route::set('my_flash_plugins', 'assets/flash-plugins/<plugin>.swf')
 *       ->defaults(array(
 *         'directory'  => 'assets',
 *         'controller' => 'static',
 *         'file'       => 'swf/<plugin>.swf'
 *       ));
 * 
 * This would cause a request for `assets/flash-plugins/video.swf` to be
 *  mapped to `swf/video.swf`.
 * 
 * Be careful here! It's possible to create mappings that accidentally
 * expose files that you don't want to be served statically.
 * 
 * If you want to pass config values to the parent [Assets][] controller
 * you can use the `config` parameter, e.g.,
 * 
 *     Route::set('some_file', 'assets/my-file.py')
 *       ->defaults(array(
 *         'directory'  => 'assets',
 *         'controller' => 'static',
 *         'file'       => 'misc/my-file.py',
 *         'config'     => array('cache' => FALSE, 'content_type' => 'text/plain')
 *       ));
 * 
 * This would disable caching for that particular request and force the
 * content type to 'text/plain'
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
	public function action_index()
	{
		$params = $this->request->param();
		
		// Set config values, if present
		if (isset($params['config']) AND is_array($params['config']))
		{
			$this->apply_config($params['config']);
			unset($params['config']);
		}
		
		// Substitute request paramters into file parameter 
		foreach($params as $key => $value)
		{
			$substitutions["<$key>"] = $value;
		}
		$file = strtr($params['file'], $substitutions);
		
		// Break up file path into the parts needed by Kohana::find_file
		$pathinfo = pathinfo($file);
		$directories = explode('/', $pathinfo['dirname']);
		$first_directory = array_shift($directories);
		$rest_of_path = join('/', $directories).'/'.$pathinfo['filename'];

		// Search for file using cascading file system, 404 if not found
		$path = Kohana::find_file($first_directory, $rest_of_path, $pathinfo['extension']);
		
		if ($path)
		{
			$this->request->response = file_get_contents($path);
		}
		else
		{
			$this->request->status = 404;
		}
	}
}
