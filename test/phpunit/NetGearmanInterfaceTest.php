<?php
/**
 *  test Pear's net gearman specific messaging
 *
 *  @author Jay 
 *  @since  4-25-11
 ******************************************************************************/   
///require(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'TestMessageInterface.php')));

// include the autoloader...
include(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'scripts','MessageAutoload_class.php')));
// add some paths...
MessageAutoload::addPath(
  realpath(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'..','..')))
);
// turn the autoloader on...
MessageAutoload::register();

class NetGearmanInterfaceTest extends TestMessageInterface
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
    $arg_map['interface'] = 'NetGearmanInterface';
    // if you use "localhost" instead of 127.0.0.1 it fails to consume the messages, sends
    // them just fine, but GearmanWorker fails to retrieve any of them
    $arg_map['host'] = '127.0.0.1:4730';
    
    return $arg_map;
    
  }//method
  
}//class
