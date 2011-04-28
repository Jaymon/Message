<?php
 
// include the common stuff...
include(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'msg_common.php')));

$ret_map = array();
$ret_map['msg'] = array();
$ret_map['consume'] = 0;
$handler->setConsumer('consumeCallback');

function consumeCallback($msg)
{
  global $ret_map;
  $ret_map['msg'][$msg['id']] = !isset($ret_map['msg'][$msg['id']]);
  $ret_map['consume']++;
  ///out::fe($ret_map['consume']);
  
  ///$str = sprintf('consume %s - %s',$ret_map['consume'],getmypid()); out::fe($str);

  return true;

}//method

for($i = 0; $i < $arg_map['count'] ;$i++)
{
  $handler->consume($arg_map);

}//for

echo serialize($ret_map);
