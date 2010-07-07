<?php defined('SYSPATH') or die('No direct script access.');

Route::set('assets/javascript', 'assets/javascript/<file>.js', array('file' => '.+'))
	->defaults(array(
		'directory'  => 'assets',
		'controller' => 'javascript',
		'action'     => 'process',
		'file'       => NULL
	));

Route::set('assets/css', 'assets/css/<file>.css', array('file' => '.+'))
	->defaults(array(
		'directory'  => 'assets',
		'controller' => 'css',
		'action'     => 'process',
		'file'       => NULL
	));
