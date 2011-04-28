<?php
/**
 *  Use gearman to pass messages back and forth
 *
 *  @version 0.3
 *  @author Jay Marcyes
 *  @since 3-9-11
 *  @package Message
 ******************************************************************************/
class GearmanInterface extends MessageInterface
{
  /**
   *  connect to the {@link $con_handler}
   *
   *  @param  string  $host if you want a port then attach it to the end (eg, host:port)   
   *  @param  string  $username
   *  @param  string  $password
   *  @param  array $options  any other options needed to connect can be passed in through here            
   *  @return boolean
   */
  function _connect($host,$username,$password,array $options)
  {
    // canary...
    if(mb_stripos($host,'localhost') === 0)
    {
      throw new InvalidArgumentException('Use 127.0.0.1 instead of "localhost" for $host');
    }//if
    
    // all the variables should be set in connect() before this is called so getPublisher() 
    // and getConsumer() will work (they will do the actual connecting)
    
    return true;
  
  }//method
  
  /**
   *  bind a name to this interface
   *  
   *  basically, the $name is what the interface will be listening on to publish
   *  and consume messages   
   *      
   *  @param  string  $name the name you want to bind to                  
   *  @return boolean
   */
  public function _bind($name)
  {
    // bind_name will get set in bind()
    return true;
    
  }//method
  
  /**
   *  publish a message
   *  
   *  @param  string  $msg   
   *  @return boolean
   */
  public function _publish($msg)
  {
    $gearman = $this->getPublisher(); 
    $job_handle = $gearman->doBackground($this->getField('bind_name'),$msg);
    return !empty($job_handle);
  
  }//method
  
  /**
   *  listen for a message to be delivered and then pass it to the defined callback
   *  set in {@link setConsumer()}
   *  
   *  this method blocks (ie, it won't return until a message is delivered)         
   *  
   *  once a message is delivered, the callback will need to be called, some backends
   *  might do that automatically, but if they don't then it will need to be done in
   *  this method                  
   */
  public function _consume()
  {
    $gearman = $this->getConsumer();
    return $gearman->work();
    
  }//method
  
  /**
   *  set a consume callback that will be called when {@link consume()} retrieves a message
   *  
   *  @param  callback  $callback a guaranteed valid php callback
   *  @return boolean
   */
  public function _setConsumer($callback)
  {
    $gearman = $this->getConsumer(); 
    $gearman->addFunction(
      $this->getField('bind_name'),
      $callback
    );
    
    return true;
    
  }//method
  
  /**
   *  acknowledge succesful receipt of a message
   *  
   *  gearman deletes the message automatically, so we don't need to really do anything      
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean        
   */
  public function ackSuccess($msg)
  {}//method
  
  /**
   *  acknowledge failed receipt of a message
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean
   */
  public function ackFailure($msg)
  {
    // resend the message since Gearman deletes it automatically...
    $this->_publish($this->getMsg($msg));
  
  }//method
  
  /**
   *  take the raw returned $msg and return the actual message
   *  
   *  the raw msg is the $msg that is returned directly from whatever interface we are
   *  using, it might be an object, array, or whatever. The actual message is the $msg that
   *  was passed into {@link publish()}
   *      
   *  different interfaces might wrap the string message in an object or something,
   *  this will facilitate easy retrieval of the actual string message         
   *  
   *  @param  GearmanJob  $msg  the message returned from gearman raw is a GearmanJob instance
   *  @return string  the actual message GearmanJob instance wraps
   */
  public function getMsg($msg)
  {
    return $msg->workload();
  }//method
  
  /**
   *  return the raw connection object this connection is using
   *  
   *  @since  3-22-11   
   *  @return array
   */
  public function getConnection()
  {
    return array($this->getField('gearman_publisher'),$this->getField('gearman_consumer'));
  }//method
  
  /**
   *  get the publisher instance that gearman will use
   *
   *  @return GearmanClient      
   */
  protected function getPublisher()
  {
    if(!$this->hasField('gearman_publisher'))
    {
      $this->setField('gearman_publisher',$this->addServers(new GearmanClient(),$this->getField('host')));
    
    }//if
    
    return $this->getField('gearman_publisher');
  
  }//method
  
  /**
   *  get the consumer instance that gearman will use
   *
   *  @return GearmanWorker
   */
  protected function getConsumer()
  {
    if(!$this->hasField('gearman_consumer'))
    {
      $this->setField('gearman_consumer',$this->addServers(new GearmanWorker(),$this->getField('host')));
    
    }//if
    
    return $this->getField('gearman_consumer');
  
  }//method
  
  /**
   *  add the server to the gearman instance
   *  
   *  @param  GearmanClient|GearmanWorker $gearman
   *  @param  string|array  $host_list  the hosts that will be connected to
   */
  protected function addServers($gearman,$host_list)
  {
    // canary...
    if(!($gearman instanceof GearmanClient) && !($gearman instanceof GearmanWorker))
    {
      throw new InvalidArgumentException('$gearman was not the right type of object');
    }//if
  
    $host_list = (array)$host_list;
  
    foreach($host_list as $i => $host)
    {
      // actually connect...
      list($host,$port) = $this->splitHost($host);
      if(empty($port)){ $port = 4730; }//if
      
      ///$host_list[$i] = sprintf('%s:%s',$host,$port);
      $gearman->addServer($host,$port);
      
    }//foreach
    
    ///$gearman->addServers(join(',',$host_list));
  
    return $gearman;
  
  }//method
  
}//class
