<?php
/**
 *  handle the interface that that Message class will use so we can switch 
 *  protocals/backends without too much hassle
 *  
 *  any classes that want to implement this interface should extend this class  
 *
 *  This interface defines all the public methods that will do all the error checking
 *  etc., then those methods will call the abstract _* methods after error checking, this
 *  way you don't have to do the same CRUD code for every new interface since the public 
 *  wrapper methods do all that   
 *  
 *  @version 0.5
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 4-26-10
 *  @package Message
 ******************************************************************************/
abstract class MessageInterface
{
  /**
   *  holds all the connection information this class uses or might need
   *  
   *  @var  array associative array
   */
  protected $con_map = array();
  
  /**
   *  holds the actual connection, established by calling {@link connect()}
   *  @var  object
   */
  protected $con_handler = null;
  
  /**
   *  hold the consume callback
   *  
   *  @see  setConsumer()      
   *  @var  callback
   */
  protected $consumer_callback = null;
  
  /**
   *  connect to the {@link $con_handler}
   *
   *  @param  string  $host the host to use, defaults to {@link getHost()}. if you want a specific
   *                        port, attach it to host (eg, localhost:27017 or example.com:27017)            
   *  @param  string  $username the username to use, defaults to {@link getUsername()}
   *  @param  string  $password the password to use, defaults to {@link getPassword()}
   *  @param  array $options  specific options you might want to use for connecting, defaults to {@link getOptions()}           
   *  @return boolean
   */
  public function connect($host = '',$username = '',$password = '',array $options = array())
  {
    // set all the connection variables...
    $host = $this->checkField('host',$host,true);
    $username = $this->checkField('username',$username);
    $password = $this->checkField('password',$password);
    $options = $this->checkField('options',$options);
    
    $is_connected = $this->_connect($host,$username,$password,$options);
    $this->setField('connected',$is_connected);
    return $is_connected;
    
  }//method
  
  /**
   *  connect to the {@link $con_handler}
   *
   *  @param  string  $host if you want a port then attach it to the end (eg, host:port)   
   *  @param  string  $username
   *  @param  string  $password
   *  @param  array $options  any other options needed to connect can be passed in through here            
   *  @return boolean
   */
  protected abstract function _connect($host,$username,$password,array $options);
  
  /**
   *  true if the connection has been established
   *  
   *  @return boolean
   */
  public function isConnected(){ return $this->hasField('connected'); }//method
  
  /**
   *  return the raw connection object this connection is using
   *  
   *  @since  3-22-11   
   *  @return object
   */
  public function getConnection(){ return $this->con_handler; }//method
  
  /**
   *  bind a name to this interface
   *  
   *  basically, the $name is what the interface will be listening on to publish
   *  and consume messages   
   *      
   *  @param  string  $name the name you want to bind to                 
   *  @return boolean
   */
  public function bind($name)
  {
    // canary...
    if(empty($name)){ throw new UnexpectedValueException('$name was empty'); }//if

    $ret_bool = $this->_bind($name);
    $this->setField('bind_name',$name);
    $this->setField('bound',$ret_bool);
    
    return $ret_bool;
    
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
  protected abstract function _bind($name);
  
  /**
   *  true if the connection has been bound (is listening on a name)
   *  
   *  @return boolean
   */
  public function isBound(){ return $this->hasField('bound'); }//method

  /**
   *  return true if this instance is bound to $name
   * 
   *  @since  3-24-11    
   *  @param`string $name the same name that would be used in a {@link bind()} call
   *  @return boolean true if $name matches the currently bound name
   */
  public function isBoundTo($name)
  {
    // canary...
    if(!$this->isBound()){ return false; }//if
  
    return ((string)$this->getField('bind_name','') === (string)$name);
    
  }//method
  
  /**
   *  publish a message
   *  
   *  @param  mixed $msg  anything you want
   *  @return boolean
   */
  public function publish($msg)
  {
    // canary...
    $this->assure();
  
    // convert message into a string, the interface should only take a string message...
    $msg = $this->encodeMsg($msg);
    return $this->_publish($msg);
  
  }//method
  
  /**
   *  publish a message
   *  
   *  @param  string  $msg
   *  @return boolean
   */
  protected abstract function _publish($msg);
  
  /**
   *  get a published message
   *      
   *  @param  array $options  any other options a specific interface might need
   *  @return string  a message, or null if no message has been published
   */
  public function get()
  {
    // canary...
    $this->assure();
  
    $msg_actual = $this->_get();
    $msg = $this->decodeMsg($msg_actual);
    if($msg !== null)
    {
      $this->ackSuccess($msg_actual);
    }//if
  
    return $msg;
  
  }//method
  
  /**
   *  get a published message
   *  
   *  note: most interfaces don't support this because they are based on a listen until
   *  you get a message model   
   *      
   *  this method should also acknowledge the message was received
   *      
   *  @return string  a message, or null if no message has been published
   */
  protected function _get()
  {
    throw new RuntimeException(sprintf('get() is not supported in %s',get_class($this)));
  }//method
  
  /**
   *  this will validate the $callback and set it up to consume
   *  
   *  @param  callback  $callback a valid php callback
   */
  public function setConsumer($callback)
  {
    // canary...
    if(!$this->isBound()){ throw new RuntimeException('Cannot set callback before bound() is called'); }//if
    if(!is_callable($callback)){ throw new DomainException('$callback is not a valid php callback'); }//if

    $this->consumer_callback = $callback;
    $this->_setConsumer(array($this,'consumerCallback'));
    return true;
    
  }//method
  
  /**
   *  set a consume callback that will be called when {@link consume()} retrieves a message
   *  
   *  @param  callback  $callback a guaranteed valid php callback
   *  @return boolean
   */
  protected abstract function _setConsumer($callback);
  
  /**
   *  listen for a message to be delivered and then pass it to the defined callback
   *  set in {@link setConsumer()}
   *  
   *  this method blocks (ie, it won't return until a message is delivered)         
   */
  public function consume()
  {
    // canary...
    $this->assure();
    if(empty($this->consumer_callback))
    {
      throw new UnexpectedValueException('callback is not set, it should be set using setConsumer()');
    }//if
    
    return $this->_consume();
  
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
  protected abstract function _consume();
  
  /**
   *  this will be called everytime a message is consumed from {@link consume()}
   *  
   *  it is a wrapper callback that will assure $msg is the actual message, that an
   *  acknowledgement is sent and that the user-defined callback is ran
   *  
   *  I would prefer this to be protected, but it has to be public so other classes can
   *  call it         
   *  
   *  @param  mixed $msg  the raw message returned from the interface, it will be converted to the
   *                      actual saved message and decoded before being passed to the user-defined
   *                      callback                     
   *  @return mixed
   */
  public function consumerCallback($msg)
  {
    try
    {
      $msg_actual = $this->decodeMsg($this->getMsg($msg));
      $ret_mixed = call_user_func($this->consumer_callback,$msg_actual);
      
      // no exceptions thrown, so acknowledge the receipt of the message...
      $this->ackSuccess($msg);
      
    }catch(Exception $e){
    
      $this->ackFailure($msg);
      throw $e;
    
    }//try/catch
    
    return $ret_mixed;
  
  }//method
  
  /**
   *  acknowledge succesful receipt of a message
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean        
   */
  public abstract function ackSuccess($msg);
  
  /**
   *  acknowledge failed receipt of a message
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean
   */
  public abstract function ackFailure($msg);
  
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
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return string  the actual message $msg might wrap          
   */
  public abstract function getMsg($msg);
  
  /**
   *  format any type of $msg into one suitable to be stored
   *  
   *  @param  mixed $msg
   *  @return string  base 64 encoded string
   */
  protected function encodeMsg($msg)
  {
    $ret_str = serialize($msg);
    return base64_encode($ret_str);
  }//method

  /**
   *  format the encoded $msg into the original value
   *  
   *  @param  string  $msg
   *  @return mixed the original message
   */
  protected function decodeMsg($msg)
  {
    // canary...
    if(empty($msg)){ return $msg; }//if
  
    return unserialize(base64_decode($msg));
  }//method

  public function setHost($val){ return $this->setField('host',$val); }//method
  public function getHost(){ return $this->getField('host',''); }//method
  public function hasHost(){ return $this->hasField('host'); }//method
  
  public function setUsername($val){ return $this->setField('username',$val); }//method
  public function getUsername(){ return $this->getField('username',''); }//method
  public function hasUsername(){ return $this->hasField('username'); }//method
  
  public function setPassword($val){ return $this->setField('password',$val); }//method
  public function getPassword(){ return $this->getField('password',''); }//method
  public function hasPassword(){ return $this->hasField('password'); }//method
  
  /**
   *  @since  3-6-11
   */
  public function setOptions($val){ return $this->setField('options',$val); }//method
  public function getOptions(){ return $this->getField('options',array()); }//method
  public function hasOptions(){ return $this->hasField('options'); }//method
  
  /**
   *  set a field that this class can then use internally
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @param  mixed $val  the value of the field         
   */
  public function setField($name,$val){ $this->con_map[$name] = $val; }//method
  
  /**
   *  get a field
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @param  mixed $default_val  what value the field should have if not present
   *  @return mixed            
   */
  public function getField($name,$default_val = null)
  {
    return isset($this->con_map[$name]) ? $this->con_map[$name] : $default_val;
  }//method
  
  /**
   *  does a field exist and is that field non-empty?
   *
   *  @since  4-26-11
   *  @param  string  $name the name of the field
   *  @return boolean       
   */
  public function hasField($name){ return !empty($this->con_map[$name]); }//method
  
  /**
   *  remove a field
   *
   *  @since  4-27-11   
   *  @param  string  $name the name of the field
   */
  public function killField($name)
  {
    if(isset($this->con_map[$name])){ unset($this->con_map[$name]); }//if
  }//method
  
  /**
   *  gets the port and host from a combined host:port
   *  
   *  @param  string  $host
   *  @return array array($host,$port);
   */
  protected function splitHost($host)
  {
    // canary...
    if(empty($host)){ throw new UnexpectedValueException('cannot split an empty $host'); }//if
    
    $url_map = parse_url($host);
    $host = empty($url_map['host']) ? '' : $url_map['host'];
    $port = empty($url_map['port']) ? '' : (int)$url_map['port'];
  
    return array($host,$port);
  
  }//method
  
  /**
   *  make sure there is a bound connection
   *  
   *  @return boolean
   */
  protected function assure()
  {
    if(!$this->isConnected())
    {
      throw new UnexpectedValueException('cannot perform requested operation because there is no active connection');
    }//if
    
    if(!$this->isBound())
    {
      throw new UnexpectedValueException('cannot perform requested operation because bind() has not been called');
    }//if
    
    return true;
  
  }//method
  
  /**
   *  assure that one or more fields exist and are not empty 
   *
   *  @since  4-27-11
   *  @throws InvalidArgumentException  if field does not exist or is empty   
   */
  protected function assureFields()
  {
    $field_name_list = func_get_args();
    foreach($field_name_list as $field_name)
    {
      if(is_array($field_name))
      {
        call_user_func_array(array($this,__FUNCTION__),$field_name);
      }else{
        if(!$this->hasField($field_name))
        {
          throw InvalidArgumentException(sprintf('no field with name "%s" found',$field_name));
        }//if
      }//if/else
    }//foreach
  
  }//method
  
  /**
   *  get the best value for a field
   *
   *  @since  3-24-11
   *  @param  string  $name the partial method name that will be appended to set and get to make the full
   *                        method names
   *  @param  mixed $val  the value that was originally passed in
   *  @param  boolean $assure true to have an exception thrown if no valid value is found
   *  @return mixed the found value               
   */
  protected function checkField($name,$val,$assure = false)
  {
    if(empty($val))
    {
      $field_val = $this->getField($name);
      if(empty($field_val))
      {
        if($assure)
        {
          throw new UnexpectedValueException(
            sprintf('no %s found. Please set it',$name)
          );
        }//if
      }else{
        $val = $field_val;
      }//if/else
    
    }else{
      $this->setField($name,$val);
    }//if/else

    return $val;

  }//method

}//class
