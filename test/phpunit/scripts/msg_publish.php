<?php

// include the common stuff...
include(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'msg_common.php')));

$ret_map = array();
$ret_map['arg_Map'] = $arg_map;
$ret_map['msg_publish'] = array();
$ret_map['msg_raw'] = array();
$ret_map['published'] = 0;

for($i = 0; $i < $arg_map['count'] ;$i++)
{
  $msg = array();
  $msg['time'] = date('j M Y, g:ia',time());
  $msg['id'] = $arg_map['start_id'] + $i;
  $msg['message'] = sprintf('this is message %s',$i);
  $msg['count'] = $i;
  $msg['name'] = $arg_map['name'];
  
  $ret_map['msg_raw'][$msg['id']] = $msg;  
  $ret_map['msg_publish'][$msg['id']] = $handler->publish($msg);
  $ret_map['published']++;
  
}//if

echo serialize($ret_map);
