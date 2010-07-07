<?php
/**
 * Copyright (c) 2008-2010 David Evans
 * License: MIT-style (see license.txt)
**/

class YUICompressor
{
	public static function compress($data, $type = 'js', $options = '')
	{
		// Find the YUI Compressor JAR file
		$yui = Kohana::find_file('vendor', 'yuicompressor', 'jar');
		
		if ( ! $yui)
		{
			throw new Kohana_Exception("Couldn't find YUI Compressor JAR file at <tt>vendor/yuicompressor.jar</tt>");
		}
		
		// Create the command line call
		$command = 'java -jar '.escapeshellarg($yui).' --type '.$type.' '.$options;

		// Windows needs the whole command encased in quotes
		if (Kohana::$is_windows)
		{
			$command = '"'.$command.'"';
		}

		// Specify how we're going to connect to the process
		$descriptorspec = array
		(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		   2 => array("pipe", "w"),  // sterr is a pipe that the child will write to
		);

		// Start the process
		$process = proc_open($command, $descriptorspec, $pipes);

		// Check that it started
		if ( ! is_resource($process))
		{
			throw new Kohana_Exception('Failed to open YUI Compressor. Try running, from the command line: java -jar :yui --help',
				array(':yui' => escapeshellarg($yui)));
		}

		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// 2 => readable handle connected to child sterr

		// Send the data to the process, then close the pipe
		fwrite($pipes[0], $data);
		fclose($pipes[0]);

		// Get the response, then close the pipe
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		// Get any errors, then close the pipe
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		// It is important that you close any pipes before calling
		// proc_close in order to avoid a deadlock

		// Close the process and check that it was successful
		if (proc_close($process) != 0)
		{
			throw new Kohana_Exception('YUI Compressor Error: :response',
				array(':response' => trim($error."\n\n".$output)));
		}

		return $output;
	}
}
