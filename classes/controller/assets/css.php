<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Controller class to pre-process CSS files.
 * 
 * This controller performs three functions:
 * 
 *   * CSS files are included as PHP and any members of the `$vars`
 *     array (defined in the config file) will be available within the
 *     scope of the included file. This allows you to, e.g., make
 *     colours and dimensions easily configurable in a single location.
 * 
 *   * @import statements can be processed and all imported files
 *     concatenated into a single file. This allows you to split your
 *     CSS into multiple files for ease of development and then serve up
 *     a single file in production for maximum load speed. 
 * 
 *   * CSS can be automatically 'minified' i.e., stripped of comments
 *     and extraneous whitespace for maximum load speed.
 *
 * @package    Assets
 * @author     David Evans
 * @copyright  (c) 2009-2010 David Evans
 * @license    MIT-style
 */
class Controller_Assets_CSS extends Controller_Assets
{
	/**
	 * @var  string  Config group to load
	 */
	public $config_group = 'css';
	
	/**
	 * @var  string  Content-Type header
	 */
	public $content_type = 'text/css';

	/**
	 * @var  string  Directory where CSS files are stored
	 */
	public $directory = 'css';
 
	/**
	 * @var  array    Variables to be available in the scope of included CSS files e.g., $header_color
	 */
	public $vars = array();

	/**
	 * @var  boolean Enables "minification" of CSS output
	 */
	public $compress = FALSE;
	
	/**
	 * @var  array    Minification library and settings to use
	 */
	public $compress_config = array();
	
	/**
	 * @var  boolean Enables concatinating of @import'ed files into a single file
	 */
	public $process_imports = FALSE;

	public function action_process($file)
	{
		// Straightforward loading of file
		if ( ! $this->process_imports)
		{
			$output = $this->load_file($file);
		}
		
		// Process @import statements
		else
		{
			require_once Kohana::find_file('vendor', 'CSSAggregator');
			
			$absolute_url = URL::site($this->request->uri, TRUE);
			
			// Pass in a reference to the `load_file_if_local()` method
			// which will be used by the aggregator to get the contents
			// of local CSS files
			$output = CSSAggregator::process($absolute_url, array(
				'load_callback' => array($this, 'load_file_if_local')));
		}
		
		if ($this->compress)
		{
			$output = $this->compress($output, $this->compress_config);
		}

		$this->request->response = $output;
	}
	
	protected function load_file($path)
	{
		$filename = Kohana::find_file($this->directory, $path, 'css');
		
		if ( ! $filename)
		{
			$this->request->status = 404;
			throw new Kohana_Request_Exception('Unable to find CSS file: <tt>:path</tt>',
				array(':path' => $path));
		}
		
		ob_start();

		// Import the supplied variables to local namespace
		extract($this->vars, EXTR_SKIP);

		include $filename;
	
		// Fetch the output and close the buffer
		return ob_get_clean();
	}
	
	public function load_file_if_local($url)
	{
		static $base_url;
		
		isset($base_url) or $base_url = URL::base(TRUE, TRUE);
		
		// If URL is local ...
		if (strpos($url, $base_url) === 0)
		{
			// ... extract the path
			$path = substr($url, strlen($base_url));
			
			// Check path matches the route of the current controller
			if ($params = $this->request->route->matches($path))
			{
				// Load the corresponding CSS file
				return $this->load_file($params['file']);
			}
		}
		
		// Otherwise, return NULL to let the CSS Aggregator know that
		// it should try to load this file itself
		return NULL;
	}
	
	protected function compress($css, $config)
	{
		switch($config['type'])
		{
			case 'strip':
				// Borrowed from the old Kohana media module:
				// http://code.google.com/p/kohanamodules/source/browse/tags/2.2/media/controllers/media.php

				// Remove comments
				$css = preg_replace('~/\*[^*]*\*+([^/][^*]*\*+)*/~', '', $css);

				// Replace all whitespace by single spaces
				$css = preg_replace('~\s+~', ' ', $css);

				// Remove needless whitespace
				$css = preg_replace('~ *+([{}+>:;,]) *~', '$1', trim($css));

				// Remove ; that closes last property of each declaration
				$css = str_replace(';}', '}', $css);

				// Remove empty CSS declarations
				$css = preg_replace('~[^{}]++\{\}~', '', $css);

				return $css;
				
				
			case 'yuicompressor':
				$options = isset($config['options']) ? $config['options'] : '';
				return YUICompressor::compress($css, 'css', $options);

			default:
				throw new Kohana_Exception('Unknown CSS compression type :type',
					array(':type' => $config['type']));

		}
	}
}
