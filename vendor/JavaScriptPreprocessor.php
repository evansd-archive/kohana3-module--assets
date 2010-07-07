<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/


class JavaScriptPreprocessor
{
	// List of paths in which to search for JavaScript files
	protected $include_paths = array();
	
	// Variables to be made available in the scope of any included
	// JavaScript files.
	public $vars = array();
	
	// Command types
	const REQUIRES = 0x01;
	const ASSUMES  = 0x02;
	
	public function __construct($include_paths = NULL, $vars = array())
	{
		// If no include paths are supplied we use PHP's defaults
		if ($include_paths === NULL)
		{
			$include_paths = get_include_path();
		}
		
		// Transform into array
		if( ! is_array($include_paths))
		{
			$include_paths = explode(PATH_SEPARATOR, $include_paths);
		}
		
		// Make sure all paths have a trailing slash
		foreach($include_paths as $path)
		{
			$this->include_paths[] = rtrim($path, '\\/').DIRECTORY_SEPARATOR;
		}
		
		$this->vars = $vars;
	}
	
	
	public function load($filename)
	{
		$included_files = array();
		
		return $this->requires($filename, $included_files);
	}
	
	
	protected function requires($filename, &$included_files)
	{
		// Capture the file output, including the variables defined in
		// `$vars` in its scope
		$content = $this->load_file($filename, $this->vars);
		
		// Break into lines
		$lines = explode("\n", $content);
		
		// Replace all directives with their processed response
		foreach(preg_grep('#^\s*//=#', $lines) as $index => $line)
		{
			$lines[$index] = $this->process_directive($line, $filename, $included_files);
		}
		
		return join("\n", $lines);
	}
	
	
	protected function process_directive($line, $path_context, &$included_files)
	{
		$directive = $this->parse_directive($line);
		if ( ! $directive) return $line;
		
		// Find the specified file (throws an exeception if it can't be found)
		$filename = $this->find_file($directive['path'], $directive['search_include_paths'], $path_context);
		
		// If it's not already included ..
		if( ! in_array($filename, $included_files))
		{
			// ... add it to the list ...
			$included_files[] = $filename;
			
			// ... and execute the command.
			switch($directive['command'])
			{
				case self::REQUIRES:
					return $this->requires($filename, $included_files);
				
				case self::ASSUMES:
					// For assumed files, we run the pre-processor on them, but discard the contents
					// and just merge their dependencies into the included files list so they won't
					// get included later, even if another file requires them.
					$this->requires($filename, $included_files);
					return '';
			}
		}
	}
	
	
	protected function load_file($filename, $vars)
	{
		ob_start();

		// Import the supplied variables to local namespace
		extract($vars, EXTR_SKIP);

		include $filename;

		// Fetch the output and close the buffer
		return ob_get_clean();
	}
	
	
	protected function parse_directive($directive)
	{
		if (preg_match('#^\s*//= *([a-z]+) +("(.*?)"|<(.*?)>) *$#', $directive, $matches))
		{
			if($command = $this->command_from_alias($matches[1]))
			{
				return array
				(
					'command'              => $command,
					'search_include_paths' => isset($matches[4]),
					'path'                 => end($matches),
				);
			}
		}
		
		return NULL;
	}
	
	
	protected function command_from_alias($alias)
	{
		switch($alias)
		{
			case 'require':
			case 'requires':
			case 'include':
			case 'includes':
				return self::REQUIRES;
			
			case 'assume':
			case 'assumes':
				return self::ASSUMES;
			
			default:
				return NULL;
		}
	}
	
	
	protected function find_file($path, $search_include_paths, $context)
	{
		$path = $path.'.js';
		
		if ($search_include_paths)
		{
			foreach($this->include_paths as $include_path)
			{
				if (file_exists($file = $include_path.$path))
				{
					return realpath($file);
				}
			}
			throw new JavaScriptPreprocessor_Exception
			(
				"Error in <tt>$context</tt>: <tt>$path</tt> could not be found in <tt>".
				join('</tt>'.PATH_SEPARATOR.'<tt>', $this->include_paths).
				'</tt>'
			);
		}
		else
		{
			// If the path is relative, prepend the appropriate directory
			$file = $this->path_is_relative($path) ? dirname($context).DIRECTORY_SEPARATOR.$path : $path;
			if ( ! file_exists($file))
			{
				throw new JavaScriptPreprocessor_Exception
				(
					"Error in <tt>$context</tt>: <tt>$file</tt> does not exist"
				);
			}
			return realpath($file);
		}
		
		
	}
	
	
	protected function path_is_relative($path)
	{
		// Paths beginning with a slash are absolute on any platform
		if ($path[0] === DIRECTORY_SEPARATOR)
		{
			return FALSE;
		}
		// If we're on Windows ...
		elseif(DIRECTORY_SEPARATOR === '\\')
		{
			// ... we also check for paths beginning with drive letters e.g., C:\
			return ! (ctype_alpha($path[0]) AND $path[1] === ':' AND $path[2] === '\\');
		}
		else
		{
			return TRUE;
		}
	}
}


class JavaScriptPreprocessor_Exception extends Exception
{
	// Empty
}
