<?php

// include the autoloader...
include(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'MessageAutoload_class.php')));
// add some paths...
MessageAutoload::addPath(
  realpath(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..','..','..')))
);
// turn the autoloader on...
MessageAutoload::register();

$arg_map = array();
parse_str($_SERVER['argv'][1],$arg_map);

$arg_map['count'] = empty($arg_map['count']) ? 100 : (int)$arg_map['count'];

$interface = $arg_map['interface'];

$handler = new $interface();
$handler->connect(
  empty($arg_map['host']) ? '' : $arg_map['host'],
  empty($arg_map['username']) ? '' : $arg_map['username'],
  empty($arg_map['password']) ? '' : $arg_map['password'],
  $arg_map
);
$handler->bind(
  $arg_map['name']
);
