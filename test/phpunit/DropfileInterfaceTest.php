<?php
/**
 *  tests the dropfile specific messaging
 *
 *  @author Jay 
 *  @since  3-1-11
 ******************************************************************************/
require(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'TestMessageInterface.php')));
  
class DropfileInterfaceTest extends TestMessageInterface
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
    $arg_map['interface'] = 'DropfileInterface';
    
    $tmpdir = sys_get_temp_dir();
    if(mb_substr($tmpdir,-1) !== DIRECTORY_SEPARATOR)
    {
      $tmpdir .= DIRECTORY_SEPARATOR;
    }//if
    
    $arg_map['host'] = sprintf('%smsg',$tmpdir);
    
    return $arg_map;
    
  }//method
  
}//class
