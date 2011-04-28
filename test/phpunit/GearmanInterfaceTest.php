<?php
/**
 *  test the gearman specific messaging
 *
 *  @author Jay 
 *  @since  3-9-11
 ******************************************************************************/   
require(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'TestMessageInterface.php')));

class GearmanInterfaceTest extends TestMessageInterface
{
  /**
   *  get the args to pass to the publish/consume scripts
   *
   *  @since  3-9-11
   *  @return array key/val pairs      
   */
  public function getArgs()
  {
    $arg_map = array();
    $arg_map['interface'] = 'GearmanInterface';
    // if you use "localhost" instead of 127.0.0.1 it fails to consume the messages, sends
    // them just fine, but GearmanWorker fails to retrieve any of them
    $arg_map['host'] = '127.0.0.1:4730';
    
    return $arg_map;
    
  }//method
  
}//class
