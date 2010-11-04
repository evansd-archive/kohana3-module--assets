<?php
class CSSAggregator
{
	public static function process($url, $config)
	{
		$config = $config + array('base_url' => NULL, 'document_root' => NULL,
			'load_callback' => NULL, 'keep_absolute_urls' => FALSE);
		
		if ($config['base_url'])
		{
			$url = self::url_join($url, $config['base_url']);
		}
		
		$output = self::import($url, $config);
		
		if ( ! $config['keep_absolute_urls'])
		{
			$root_url = self::url_join('/', $url);
			$output = preg_replace('#url\("'.preg_quote($root_url, '#').'#', 'url("/', $output); 
		}
		
		return $output;
	}
	
	protected static function import($url, $config, &$imported_files = array())
	{
		// Load the file contents
		$contents = self::load_file($url, $config['document_root'], $config['base_url'], $config['load_callback']);
		
		$contents = self::make_urls_absolute($contents, $url);
		
		if (strpos($contents, '@import') !== FALSE)
		{
			$lines = explode("\n", $contents);
			
			foreach($lines as $index => $line)
			{
				if (preg_match('/^\s*@import\s+url\("([^"]+)"\)/', $line, $matches))
				{
					$import_url = $matches[1];
					if ( ! in_array($import_url, $imported_files))
					{
						$imported_files[] = $import_url;
						$lines[$index] = self::import($import_url, $config, $imported_files);
					}
				}
			}
			
			$contents = join("\n", $lines);
		}
		
		return $contents;
	}
	
	public static function make_urls_absolute($css, $base_url)
	{
		// Wrap bare @import strings with url()
		$css = preg_replace('/@import\s+"([^"]+)"/', '@import url("$1")', $css);
		
		// Find all URLs
		preg_match_all('/url\(.*?[^\\\]\)/', $css, $matches);
		
		$urls = array_unique($matches[0]);
		$replacements = array();
		
		foreach($urls as $index => $url)
		{
			// We can guarantee URL begins 'url(' and terminates with ')'
			$url = substr($url, 4, -1);
			$url = trim($url, '"\' ');
			
			// Join URL with current base URL
			$url = self::url_join($url, $base_url);
			
			$replacements[$index] = 'url("'.$url.'")';
		}
		
		// Replace all URLs with absolute-ised versions
		return str_replace($urls, $replacements, $css);
	}
	
	protected static function load_file($url, $doc_root, $base_url, $callback)
	{
		// Call user defined load function, if defined
		$contents = ($callback)
		            ? call_user_func($callback, $url)
		            : NULL;
		
		if ( ! is_string($contents))
		{
			if ($base_url AND strpos($url, $base_url) === 0)
			{
				$path = $doc_root.'/'.substr($url, strlen($base_url));
				$contents = file_get_contents($path);
			}
			else
			{
				$contents = file_get_contents($url);
			}
		}
		
		return $contents;
	}
	
	public static function url_join($url, $base)
	{
		$base = parse_url($base);
		$url  = parse_url($url);
		
		foreach(array('scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment') as $key)
		{
			if (isset($url[$key]))
			{
				if ($key == 'path' AND isset($base['path']))
				{
					$url['path'] = self::url_path_join($url['path'], $base['path']);
				}
				
				break;
			}
			elseif (isset($base[$key]))
			{
				$url[$key] = $base[$key];
			}
		}
		
		if (isset($url['path']))
		{
			$url['path'] = self::path_simplify($url['path']);
		}

		return self::build_url($url);
	}
	
	public static function url_path_join($path, $base)
	{
		return ($path == '')
		       ? $base
		       : (($path[0] == '/')
		          ? $path
		          : substr($base, 0, strrpos($base, '/')).'/'.$path);
	}
	
	public static function path_simplify($path)
	{
		// Regex to match any url segment that's not a single dot or two dots
		$any_char = '[^/]';
		$not_a_dot ='[^/\.]';
		$url_segment = "($not_a_dot|$not_a_dot$any_char|$any_char$not_a_dot|$any_char{3,})";
		
		$dotted_paths = array
		(
			'#/\.(/|$)#',                   // Single dot
			'#/'.$url_segment.'/\.\.(/|$)#' // Double dot
		);
		
		do
		{
			$path = preg_replace($dotted_paths, '/', $path, -1, $count);
		}
		while ($count > 0);
		
		return $path;
	}

	public static function build_url($parts)
	{
		return 
				 ((isset($parts['scheme'])) ? $parts['scheme'].'://' : '')
				.((isset($parts['user'])) ? $parts['user'].((isset($parts['pass'])) ? ':'.$parts['pass'] : '').'@' : '')
				.((isset($parts['host'])) ? $parts['host'] : '')
				.((isset($parts['port'])) ? ':'.$parts['port'] : '')
				.((isset($parts['path'])) ? $parts['path'] : '')
				.((isset($parts['query'])) ? '?'.$parts['query'] : '')
				.((isset($parts['fragment'])) ? '#'.$parts['fragment'] : '');
	}
}
