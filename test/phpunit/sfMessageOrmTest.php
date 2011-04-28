<?php
/**
 *  test the gearman specific messaging
 *
 *  @author Jay 
 *  @since  3-9-11
 ******************************************************************************/   
require(join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'TestMessageInterface.php')));

class sfMessageOrmTest extends TestMessageInterface
{
  /**
   *  get the args to pass to the publish/consume scripts
   *
   *  @since  3-9-11
   *  @return array key/val pairs      
   */
  public function getArgs()
  {
    $db_manager = sfContext::getInstance()->getDatabaseManager();
    $db_handler = $db_manager->getDatabase('message');
    return $db_handler->getParameterHolder()->getAll();
    
  }//method
  
  /**
   *  get a child of MessageOrm to use to test the orm stuff
   *  
   *  @since  3-23-11   
   *  @return MessageOrm
   */
  public function getOrm()
  {
    sfConfig::set('sf_message_on',true);
    $orm = new sfMessageTestOrm();
    $orm->setOption('bind_name',$this->getBindName(true));
    return $orm;
  
  }//method
  
}//class

class sfMessageTestOrm extends sfMessageOrm 
{
  /**
   *  called from the consume* methods, this is the method that will be used to
   *  process a retrieved message
   *  
   *  this callback is used in the consume* methods to process a message after it
   *  is retrieved  
   *  
   *  a callback that will take a $msg, it doesn't have to worry about returning 
   *  anything, if error is encountered it would be best to throw an exception
   *      
   *  @param  mixed $msg  the message that was sent
   */
  public function callback($msg)
  {
    ///out::e($msg);
  
    $sleep_count = $this->getOption('sleep_count',0);
    if($sleep_count > 0)
    {
      sleep($sleep_count);
    }//if
    
    return true;
  
  }//method


}//class
