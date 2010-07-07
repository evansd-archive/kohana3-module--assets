<?php
class CSSAggregator
{
	protected $load_function;
	
	public function __construct($load_function = NULL)
	{		
		$this->load_function = $load_function;
	}
	
	public function process($url, $keep_absolute_urls = FALSE)
	{
		$output = $this->import($url);
		
		if ( ! $keep_absolute_urls)
		{
			$url = parse_url($url);
			unset($url['query'], $url['fragment']);
			$url['path'] = '/';
			
			$base = self::build_url($url);
			
			$output = preg_replace('#url\("'.preg_quote($base, '#').'#', 'url(/', $output); 
		}
		
		return $output;
	}
	
	protected function import($url, &$imported_files = array())
	{
		// Load the file contents
		$contents = $this->load_file($url);
		
		$contents = $this->make_urls_absolute($contents, $url);
		
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
						$lines[$index] = $this->import($import_url, $imported_files);
					}
				}
			}
			
			$contents = join("\n", $lines);
		}
		
		return $contents;
	}
	
	protected function make_urls_absolute($contents, $base_url)
	{
		// Wrap bare @import strings with url()
		$contents = preg_replace('/@import\s+"([^"]+)"/', '@import url("$1")', $contents);
		
		// Find all URLs
		preg_match_all('/url\(.*[^\\\]\)/', $contents, $matches);
		
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
		
		return str_replace($urls, $replacements, $contents);
	}
	
	protected function load_file($url)
	{
		$contents = ($this->load_function)
		            ? call_user_func($this->load_function, $url)
		            : NULL;
		
		if ( ! is_string($contents))
		{
			$contents = file_get_contents($url);
		}
		
		return $contents;
	}
	
	protected static function url_join($url, $base)
	{
		$base = parse_url($base);
		$url  = parse_url($url);
			
		if(isset($base['path']))
		{
			$path = explode('/', $base['path']);
			$base['path'] = end($path);
			$path[key($path)] = '';
			$base['base'] = join('/', $path);
		}
		
		if(isset($url['path']) AND $url['path'][0] == '/')
		{
			$url['base'] = $url['path'];
			unset($url['path']);
		}
		
		foreach(array('scheme', 'host', 'port', 'base', 'path', 'query', 'fragment') as $key)
		{
			if(isset($url[$key]))
			{
				$base[$key] = $url[$key];
				$found = TRUE;
			}
			elseif( ! empty($found))
			{
				unset($base[$key]);	
			}
			
		}

		if(isset($base['base']))
		{
			$base['path'] = $base['base'] . @$base['path'];
			unset($base['base']);
		}
		
		return self::build_url($base);
	}


	protected static function build_url($parts)
	{
		$url = '';

		if(isset($parts['scheme'])) $url .= $parts['scheme'] . '://';
		if(isset($parts['host'])) $url .= $parts['host'];
		if(isset($parts['port'])) $url .= ':' . $parts['port'];
		if(isset($parts['path'])) $url .= $parts['path'];
		if(isset($parts['query'])) $url .= '?' . $parts['query'];
		if(isset($parts['fragment'])) $url .= '#' . $parts['fragment'];
		
		return $url;
	}
}
