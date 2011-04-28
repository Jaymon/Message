<?php
/**
 *  all messages should extend this class 
 *
 *  @version 0.4
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 4-26-10
 *  @package Message
 ******************************************************************************/
abstract class MessageOrm
{
  /**
   *  you can't touch this object, get to it in your methods by calling {@link getConnection()}  
   *
   *  @see  getConnection(), setConnection()
   *  @var  MessageCon
   */
  private $con_handler = null;

  /**
   *  create an instance of this class
   *  
   *  @param  MessageCon  $con_handler  the connection this instance will use to publish and consume.      
   */
  function __construct(MessageInterface $con_handler)
  {
    $this->setConnection($con_handler);
  
  }//method
  
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
  public abstract function callback($msg);
  
  /**
   *  set the connection to use
   *  
   *  @param  MessageCon  $con  the connection
   */
  public function setConnection(MessageInterface $con_handler){ $this->con_handler = $con_handler; }//method
  
  /**
   *  get the connection this class is going to use
   *
   *  @return MessageCon
   */
  public function getConnection()
  {
    if(empty($this->con_handler))
    {
      throw new UnexpectedValueException('No connection was found, use setConnection() to set one');
    }//if
    
    if(!$this->con_handler->isConnected())
    {
      $this->con_handler->connect();
    }//if
    
    $bind_name = $this->getName();
    if(!$this->con_handler->isBoundTo($bind_name))
    {
      $this->con_handler->bind($bind_name);
    
      // set the callback that will be used for any consumed messages
      $this->con_handler->setConsumer(array($this,'callback'));
    
    }//if
    
    return $this->con_handler;
    
  }//method
  
  /**
   *  publish a message
   *  
   *  @param  mixed $msg
   *  @return boolean
   */
  public function publish($msg)
  {
    $con_handler = $this->getConnection();
    return $con_handler->publish($msg);
  }//method
  
  /**
   *  consume one message
   *  
   *  this is a blocking method, so it will wait until one message is consumed
   *  
   *  @return boolean if false, methods like {@link consumeForCount()} will stop consuming
   */
  public function consume()
  {
    $con_handler = $this->getConnection();
    $con_handler->consume();
    return true;
  
  }//method
  
  /**
   *  consume $count published messages
   *   
   *  @return integer how many messages were consumed
   */
  public function consumeForCount($count)
  {
    // canary...
    $count = (int)$count;
    if(empty($count)){ throw new UnexpectedValueException('cannot consume without a $count greater than zero'); }//if
  
    // consume $count messages...
    $consumed = 0;
    while($consumed < $count)
    {
      $ret_bool = $this->consume();
      $consumed++;
      
      if($ret_bool === false){ break; }//if 

    }//while
  
    return $consumed;
  
  }//method
  
  /**
   *  set up a callback to consume received messages for $time seconds
   *                   
   *  @param  integer $time how many seconds you want to listen for
   *  @param  integer $count  use if you would like to consume count messages or for a
   *                          time period, whichever comes first (time is up or count is reached)
   *                          will cause the consuming to end   
   *  @return integer how many messages were consumed
   */
  public function consumeForTime($time,$count = 0)
  {
    // canary...
    $time = (int)$time;
    if(empty($time))
    {
      throw new UnexpectedValueException('cannot consume without a $time greater than zero seconds');
    }//if
    
    $consumed = 0;
    $count = (int)$count;
    $now = time();
    $stop_time = $now + $time;
  
    // listen for $time seconds...
    while($now < $stop_time)
    {
      $ret_bool = $this->consume();
      $consumed++;
      $now = time();
      
      if($ret_bool === false){ break; }//if
      if(($count > 0) && ($consumed >= $count)){ break; }//if
      
    }//while
  
    return $consumed;
  
  }//method
  
  /**
   *  the name to use to bind this orm to the message interface
   * 
   *  @since  4-27-11
   *  @return string  the name
   */
  protected function getName()
  {
    return mb_strtolower(get_class($this));
  }//method

}//class
