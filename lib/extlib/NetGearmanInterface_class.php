<?php
/**
 *  Use Pear's Net_Gearman gearman lib to pass messages back and forth
 *
 *  install using command: pear install Net_Gearman-alpha
 *    or pear install "channel://pear.php.net/Net_Gearman-0.2.3" Net_Gearman
 *  
 *  @link http://pear.php.net/package/Net_Gearman/ 
 *  @link http://smorgasbork.com/component/content/article/34-web/108-building-a-distributed-app-with-netgearman-part-1
 *  
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 4-25-11
 *  @package Message
 ******************************************************************************/
class NetGearmanInterface extends MessageInterface
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
    $gearman = $this->getConsumer();
    ///$gearman->addAbility($this->con_map['bind_class']);
    $gearman->addAbility($name);
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
    
    // publish the message...
    $task = new Net_Gearman_Task(
      $this->getField('bind_name'),
      $msg,
      $this->getUid(),
      Net_Gearman_Task::JOB_BACKGROUND
    );
    
    $set = new Net_Gearman_Set();
    $set->addTask($task);
    
    // turn off strict errors since Net Gearman throws a lot of strict errors, this is
    // a terrible way to do it, but I don't want to edit the original code...
    $current_error_level = error_reporting();
    $change_error_level = (boolean)($current_error_level & E_STRICT);
    if($change_error_level){ error_reporting($current_error_level ^ E_STRICT); }//if
    
    $gearman->runSet($set);
    
    if($change_error_level){ error_reporting($current_error_level); }//if
    
    $job_handle = $task->handle;
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
    ///return $gearman->beginWork($this->con_map['consume_monitor_callback']);
    return $gearman->beginWork();
    
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
    return $gearman->setConsumer($this->getField('bind_name'),$callback);
    
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
    return $msg;
    
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
   *  format any type of $msg into one suitable to be stored
   *  
   *  @param  mixed $msg
   *  @return string  base 64 encoded string
   */
  /* protected function encodeMsg($msg)
  {
    // we need to wrap the message to send some meta info along for the ride also...
    $msg_job = array(
      'bind_name' => $this->getField('bind_name'),
      'msg' => $msg
    );
  
    return parent::encodeMsg($msg_job);
  
  }//method */
  
  /**
   *  get the publisher instance that gearman will use
   *
   *  @return Net_Gearman_Client     
   */
  protected function getPublisher()
  {
    if(!$this->hasField('gearman_publisher'))
    {
      $server_list = $this->getServers();
      $this->setField('gearman_publisher',new Net_Gearman_Client($server_list));
    
    }//if
    
    return $this->getField('gearman_publisher');
  
  }//method
  
  /**
   *  get the consumer instance that gearman will use
   *
   *  @return Net_Gearman_Worker
   */
  protected function getConsumer()
  {
    if(!$this->hasField('gearman_consumer'))
    {
      $server_list = $this->getServers();
      ///$this->con_map['gearman_consumer'] = new Net_Gearman_Worker($server_list);
      $this->setField('gearman_consumer',new NetGearmanInterfaceWorker($server_list));
    
    }//if
    
    return $this->getField('gearman_consumer');
  
  }//method
  
  /**
   *  get all the servers that will be used to connect to geraman
   *  
   *  @return array  a list of host:port to use for connecting
   */
  protected function getServers()
  {
    // canary...
    if($this->hasField('host_normalized')){ return $this->getField('host'); }//if
  
    $host_list = (array)$this->getHost();
  
    foreach($host_list as $i => $host)
    {
      list($host,$port) = $this->splitHost($host);
      if(empty($port)){ $port = 4730; }//if
      $host_list[$i] = sprintf('%s:%s',$host,$port);
      
    }//foreach
    
    $this->setField('host_normalized',true);
    $this->setHost($host_list);
    return $host_list;
  
  }//method
  
  /**
   *  get a unique id           
   *  
   *  @since  4-26-11     
   *  @return string
   */
  protected function getUid()
  {
    $pid = (string)getmypid();
    $uid = str_replace('.','',uniqid('',true));
    $ret_str = sprintf('%s%s%s',$pid,$uid,rand(0,10000));
    return $ret_str;
  
  }//method
  
}//class

class NetGearmanInterfaceWorker extends Net_Gearman_Worker
{
  protected $callback_map = array();
  protected $consumed = false;

  public function setConsumer($name,$callback)
  {
    $this->callback_map[$name] = $callback;
    return true;
  }//method

  public function beginWork($monitor = null)
  {
    $this->consumed = false;
    return parent::beginWork($monitor);
    
  }//method

  /**
   * Listen on the socket for work
   *
   * Sends the 'grab_job' command and then listens for either the 'noop' or
   * the 'no_job' command to come back. If the 'job_assign' comes down the
   * pipe then we run that job. 
   *
   * @param resource $socket The socket to work on 
   * 
   * @return boolean Returns true if work was done, false if not
   * @throws Net_Gearman_Exception
   * @see Net_Gearman_Connection::send()
   */
  protected function doWork($socket)
  {
      Net_Gearman_Connection::send($socket, 'grab_job');

      $resp = array('function' => 'noop');
      while (count($resp) && $resp['function'] == 'noop') {
          $resp = Net_Gearman_Connection::blockingRead($socket);
      } 

      if (in_array($resp['function'], array('noop', 'no_job'))) {
          return false;
      }

      if ($resp['function'] != 'job_assign') {
          throw new Net_Gearman_Exception('Holy Cow! What are you doing?!');
      }

      $name   = $resp['data']['func'];
      $handle = $resp['data']['handle'];
      $arg    = array();

      if (isset($resp['data']['arg']) && 
          Net_Gearman_Connection::stringLength($resp['data']['arg'])) {
          $arg = json_decode($resp['data']['arg'], true);
          if($arg === null){
              $arg = $resp['data']['arg'];
          }
      }

      // =======================================================================
      // this is the part of the code I changed, everything else was copy/paste
      // =======================================================================

      ///$job = Net_Gearman_Job::factory($name, $socket, $handle);
      $job = new NetGearmanInterfaceJob($this->callback_map,$socket,$handle);
      try {
          $this->start($handle, $name, $arg);
          $res = $job->run($arg); 
          if (!is_array($res)) {
              $res = array('result' => $res);
          }

          $job->complete($res);
          $this->complete($handle, $name, $res);
          $this->consumed = true;
          
      } catch (NetGearmanInterfaceJobException $e) {
          
          $job->fail(); 
          $this->fail($handle, $name, $e);
          
          // Force the job's destructor to run
          $job = null;
          
          // re-throw the original exception...
          throw $e->e;
           
      } catch (Net_Gearman_Job_Exception $e) {
          
          $job->fail(); 
          $this->fail($handle, $name, $e);
           
      }
      
      // =======================================================================
      
      // Force the job's destructor to run
      $job = null;

      return true;
  }
  
  /**
   * Should we stop work?
   *
   * @return boolean
   */
  public function stopWork()
  {
    return $this->consumed;
  }//method


}//method

/**
 *  this is here entirely to get around some really bad design in Net_Gearman where
 *  every job has to be a class at a certain path instead of just a valid callback
 *    
 *  @version 0.1
 *  @author Jay Marcyes
 *  @since 4-25-11
 *  @package Message 
 ******************************************************************************/
class NetGearmanInterfaceJob extends Net_Gearman_Job_Common
{
  protected $callback_map = array();

  public function __construct(array $callback_map,$conn,$handle)
  {
    $this->callback_map = $callback_map;
    parent::__construct($conn,$handle);
  
  }//method

  /**
   * Run your job here
   *
   * @param array $arg Arguments passed from the client
   * 
   * @return void
   * @throws Net_Gearman_Exception
   */
  public function run($arg)
  {
    $ret = null;
  
    try
    {
      // technically, while we've left it open to have multiple callbacks, the current
      // message interface doesn't really allow that...
      foreach($this->callback_map as $callback)
      {
        $ret = call_user_func($callback,$arg);
        
      }//foreach
    }
    catch(Exception $e)
    {
      throw new NetGearmanInterfaceJobException($e);
    
    }//try/catch
    
    return $ret;
  
  }//method
    
}//class

class NetGearmanInterfaceJobException extends Net_Gearman_Job_Exception
{
  public $e = null;

  public function __construct(Exception $e)
  {
    $this->e = $e;
    parent::__construct($e->getMessage());
  }//method

}//class
