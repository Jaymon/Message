<?php
/**
 *  tests the message interface
 *
 *  @author Jay 
 *  @since  3-1-11
 ******************************************************************************/   
abstract class TestMessageInterface extends PHPUnit_Framework_TestCase
{
  /**
   *  the command name
   *  
   *  @see  getBindName()
   *  @var  string
   */
  protected $bind_name = '';

  protected $cb_count = 0;

  /**
   *  this will be called before each test is run, so override if you want to do pre 
   *  individual test stuff   
   *  
   *  @link http://www.phpunit.de/manual/current/en/fixtures.html#fixtures.more-setup-than-teardown
   */
  public function setUp()
  {
    $this->cb_count = 0;
  }//method

  /**
   *  test passing messages using the orm
   *  
   *  basically, the orm is the final level of misdirection and is the part that most
   *  developers will interact with since the MessageCon and the interface or internal to the
   *  orm.            
   *
   *  @since  3-23-11   
   */
  public function testOrmPass()
  {
    $orm = $this->getOrm();
    
    $msg = array();
    $msg['i'] = 1;
    
    for($i = 0; $i < 7 ; $i++)
    {
      $msg['i'] = $i;
      if($i === 2)
      {
        $msg['sleep_count'] = 3;
      }//if
      
      $this->assertTrue($orm->publish($msg));
    
    }//for
    
    $consumed = $orm->consumeForCount(2);
    $this->assertSame(2,$consumed);
    
    $consumed = $orm->consumeForCount(2);
    $this->assertSame(2,$consumed);
  
    $consumed = $orm->consumeForTime(5);
    $this->assertSame(2,$consumed);
    
    $consumed = $orm->consumeForTime(100,1);
    $this->assertSame(1,$consumed);
  
  }//method

  /**
   *  publish one message, then consume it
   *   
   *  @since  3-23-11
   */
  public function testPassOne()
  {
    $handler = $this->getHandler($this->getBindName(true));
    $handler->setConsumer(array($this,'cbPassOne'));
    
    $msg = array();
    $msg['i'] = 1;
    
    $this->assertTrue($handler->publish($msg));
    
    $handler->consume();
  
  }//method
  
  public function cbPassOne($msg)
  {
    ///$this->assertInternalType('array',$msg);
    $this->assertArrayHasKey('i',$msg);
    $this->assertSame(1,$msg['i']);
  
    return true;
    
  }//method
  
  /**
   *  publish 2 messages from the same handler, then consume them
   *   
   *  @since  4-26-11
   */
  public function testSamePassTwo()
  {
    $method = 'cbSamePassTwo';
    $h = $this->getHandler($this->getBindName(true));
    $h->setConsumer(array($this,$method));
    
    $msg = array();
    $msg['method'] = $method;
    
    $msg['i'] = 1;
    $this->assertTrue($h->publish($msg));
    
    $msg['i'] = 2;
    $this->assertTrue($h->publish($msg));
    
    $h->consume();
    $h->consume();
    $this->assertSame(2,$this->cb_count);
  
  }//method
  
  public function cbSamePassTwo($msg)
  {
    $this->cb_count++;
    
    $this->assertArrayHasKey('i',$msg);
    $this->assertSame(__FUNCTION__,$msg['method']);
    return true;
    
  }//method
  
  /**
   *  publish one message from each handler, then consume them
   *   
   *  @since  3-23-11
   */
  public function testPassTwo()
  {
    $h1 = $this->getHandler($this->getBindName(true));
    $h1->setConsumer(array($this,'cbPassTwo1'));
    
    $h2 = $this->getHandler($this->getBindName(true));
    $h2->setConsumer(array($this,'cbPassTwo2'));
    
    $msg = array();
    
    $msg['i'] = 1;
    $this->assertTrue($h1->publish($msg));
    
    $msg['i'] = 2;
    $this->assertTrue($h2->publish($msg));
    
    
    $h1->consume();
    $h2->consume();
    $this->assertSame(2,$this->cb_count);
  
  }//method
  
  public function cbPassTwo1($msg)
  {
    $this->cb_count++;
    $this->assertSame(1,$msg['i']);
    return true;
    
  }//method
  
  public function cbPassTwo2($msg)
  {
    $this->cb_count++;
    $this->assertSame(2,$msg['i']);
    return true;
    
  }//method
  
  /**
   *  publish one message, fail on the first consume, succeed on the second
   *   
   *  @since  3-23-11
   */
  public function testPassFail()
  {
    $handler = $this->getHandler($this->getBindName(true));
    $handler->setConsumer(array($this,'cbPassFail'));
    
    $msg = array();
    $msg['i'] = 1;
    
    $this->assertTrue($handler->publish($msg));
    
    try
    {
      // this should fail...
      $handler->consume();
      $this->fail('');
      
    }
    catch(PHPUnit_Framework_AssertionFailedError $e)
    {
      $this->fail('the callback should have thrown an exception');
    }
    catch(Exception $e){}//try/catch
    
    // try to consume the message again (if the message didn't fail right, then this will hang)...
    $handler->consume();
    
    $this->assertSame(2,$this->cb_count);
  
  }//method
  
  public function cbPassFail($msg)
  {
    ///out::e($msg);
  
    $this->cb_count++;
    ///$this->assertInternalType('array',$msg);
    $this->assertArrayHasKey('i',$msg);
    $this->assertContains(1,$msg);
  
    if($this->cb_count === 1)
    {
      throw new RuntimeException('$this->cb_count was 1 so the message will fail');
    }//if
  
    return true;
    
  }//method

  /**
   *  publish a whole bunch of messages and consume them
   *  
   *  this is basically the same as testProcessMessagePassing except all in the same
   *  script         
   *   
   *  @since  4-26-11
   */
  public function testPassLots()
  {
    $handler_count = 10;
    $msg_count = 1000;
    $count = (int)($msg_count / $handler_count);
    
    $method = 'cbPassLots';
    $handler_list = array();
    
    for($i = 0; $i < $handler_count ; $i++)
    {
      $handler_list[$i] = $this->getHandler($this->getBindName(true));
      $handler_list[$i]->setConsumer(array($this,$method));
    
    }//for
  
    // publish messages...
    $publish_count = 0;
    for($i = 0; $i < $count ; $i++)
    {
      foreach($handler_list as $handler)
      {
        $msg = array();
    
        $msg['method'] = $method;
        $msg['i'] = $i;
        $this->assertTrue($handler->publish($msg));
        $publish_count++;
      
      }//foreach
  
    }//for
  
    $this->assertSame($msg_count,$publish_count);
  
    // consume messages...
    for($i = 0; $i < $count ; $i++)
    {
      foreach($handler_list as $handler)
      {
        $handler->consume();
      
      }//foreach
  
    }//for
    
    $this->assertSame($msg_count,$this->cb_count);
  
  }//method
  
  public function cbPassLots($msg)
  {
    $this->cb_count++;
    $this->assertSame(__FUNCTION__,$msg['method']);
    return true;
    
  }//method

  /**
   *  publish $cmd_count messages and consume them
   *
   *  @since  3-1-11   
   */
  public function testProcessMessagePassing()
  {
    $cmd_count = 10;
    $msg_count = 1000;
  
    // fire up the publishers and consumers...
    $publishers = $this->getPublishers($cmd_count,$msg_count);
    $consumers = $this->getConsumers($cmd_count,$msg_count);
    
    $ret_map_count = 0;
    $ret_map_list = array();
    
    foreach($publishers as $key => $publisher)
    {
      ///out::e($key);
      $ret_map_list[$key] = $this->getResponse($publisher);
      ///out::e($ret_map_list[$key]);
      $ret_map_count += count($ret_map_list[$key]['msg_publish']);
      pclose($publisher);
    
      $min_id = -1;
    
      foreach($ret_map_list[$key]['msg_publish'] as $msg_id => $msg)
      {
        $this->assertGreaterThan($min_id,$msg_id);
        $this->assertTrue($msg);
        $min_id = $msg_id;
        
      }//foreach
      
    }//foreach
    
    $this->assertEquals($msg_count,$ret_map_count);
    
    $ret_map_count = 0;
    $ret_map_list = array();
    $msg_consumed_map = array();
    
    foreach($consumers as $key => $consumer)
    {
      ///out::e($key);
      $ret_map_list[$key] = $this->getResponse($consumer);
      ///out::e($ret_map_list[$key]);
      $ret_map_count += count($ret_map_list[$key]['msg']);
      pclose($consumer);
    
      foreach($ret_map_list[$key]['msg'] as $msg_id => $msg)
      {
        $this->assertTrue($msg,sprintf('%s was false',$msg_id));
        $this->assertFalse(isset($msg_consumed_map[$msg_id]));
        
        $msg_consumed_map[$msg_id] = true;
        
      }//foreach
      
    }//foreach    
    
    $this->assertEquals($msg_count,$ret_map_count);
    
  }//method
  
  /**
   *  get the response of a $resource
   *  
   *  this will block (ie, wait for a response      
   *
   *  @param  resource  $resource the process to read from
   *  @return array the response      
   */
  protected function getResponse($resource)
  {
    $ret_map = array();
    $ret_str = '';
    $ret_str = stream_get_contents($resource);

    ///out::e($ret_str);
    $ret_map = unserialize($ret_str);
    if($ret_map === false)
    {
      out::e($ret_str);
    }//if
  
    return $ret_map;
  
  }//method
  
  /**
   *  run a command
   *  
   *  @param  string  $cmd  the command to run
   *  @return resource
   */
  protected function runCmd($cmd,$mode = 'r')
  {
    $fp = popen($cmd,$mode);
    if(!is_resource($fp))
    {
      throw new UnexpectedValueException(sprintf('popen failed running command: %s',$cmd));
    }//if
  
    ///stream_set_blocking($fp,0); // http://us2.php.net/stream_set_blocking (doesn't work in windows)
    return $fp;
  
  }//method
  
  /**
   *  get $cmd_count consumers
   *
   *  these publishers will pull messages
   *      
   *  @param  integer $cmd_count  how many command processes to run
   *  @param  integer $msg_count  how many messages to produce among all the processes, this means
   *                              if you have $cmd_count 10,$msg_count = 100 then each command
   *                              would send 10 messsages
   *  @return array a list of popen resources      
   */
  protected function getConsumers($cmd_count = 1,$msg_count = 100)
  {
    $ret_list = array();
    $count = (int)(($msg_count - ($msg_count % $cmd_count)) / $cmd_count);
  
    $arg_map = $this->getArgs();
    $arg_map['count'] = $count;
    $arg_map['name'] = $this->getBindName();
    $args = http_build_query($arg_map, '', '&');
  
    $path = join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'scripts','msg_consume.php'));

    for($i = 0; $i < $cmd_count ; $i++)
    {
      $ret_list[$i] = $this->runCmd(sprintf('php %s "%s"',$path,$args));
      usleep(rand(1,10)); // we stagger to avoid potential race conditions with the interface
    }//for
  
    return $ret_list;
  
  }//method
  
  /**
   *  get $cmd_count publishers
   *
   *  these publishers will push messages
   *      
   *  @param  integer $cmd_count  how many command processes to run
   *  @param  integer $msg_count  how many messages to produce among all the processes, this means
   *                              if you have $cmd_count 10,$msg_count = 100 then each command
   *                              would send 10 messsages         
   *  @return array a list of popen resources
   */
  protected function getPublishers($cmd_count = 1,$msg_count = 100)
  {
    $ret_list = array();
    $count = (int)(($msg_count - ($msg_count % $cmd_count)) / $cmd_count);
    
    $arg_map = $this->getArgs();
    $arg_map['count'] = $count;
    $arg_map['name'] = $this->getBindName();
  
    $path = join(DIRECTORY_SEPARATOR,array(dirname(__FILE__),'scripts','msg_publish.php'));
  
    $start_id = 0;
  
    for($i = 0; $i < $cmd_count ; $i++)
    {
      $arg_map['start_id'] = $start_id;
      $args = http_build_query($arg_map, '', '&');
    
      $ret_list[$i] = $this->runCmd(sprintf('php %s "%s"',$path,$args));
      usleep(rand(1,10)); // we stagger to avoid potential race conditions with the interface
      
      ///$start_id = ($count * ($i + 1)) + 1;
      $start_id = ($count * ($i + 1));
      
    }//for
  
    return $ret_list;
  
  }//method
  
  /**
   *  get a child of MessageOrm to use to test the orm stuff
   *  
   *  @since  3-23-11   
   *  @return MessageOrm
   */
  public function getOrm()
  {
    $orm = new MessageTestOrm($this->getBindName(true),$this->getHandler());
    return $orm;
  
  }//method
  
  /**
   *  get a MessageCon instance bound to the $name
   *  
   *  @param  string  $name the name to use
   *  @return MessageCon
   */
  public function getHandler($name = '')
  {
    $arg_map = $this->getArgs();
    if(empty($name))
    {
      if(empty($arg_map['name']))
      {
        // get a unique name for each request...
        ///$arg_map['name'] = sprintf('%s_%s',get_class($this),microtime(true));
        $arg_map['name'] = get_class($this);
        
      }//if
    
    }
    else
    {
      $arg_map['name'] = $name;
      
    }//if/else
    
    $interface = $arg_map['interface'];
    $handler = new $interface();
    $handler->connect(
      empty($arg_map['host']) ? '' : $arg_map['host'],
      empty($arg_map['username']) ? '' : $arg_map['username'],
      empty($arg_map['password']) ? '' : $arg_map['password'],
      $arg_map
    );
    $handler->bind(
      $arg_map['name'],
      $arg_map
    );
    
    return $handler;
  
  }//method
  
  /**
   *  get the command name
   *  
   *  this ensures concurrent commands can speak to each other      
   *
   *  @param  boolean $unique true to return a unique bind name   
   *  @return string   
   */
  protected function getBindName($unique = false)
  {
    // canary...
    if(!empty($unique))
    {
      return mb_substr(md5(sprintf('%s%s%s',get_class($this),microtime(true),rand(0,PHP_INT_MAX))),0,8);
    }//if
  
    if(empty($this->bind_name))
    {
      $this->bind_name = mb_substr(md5(sprintf('%s%s',get_class($this),microtime(true))),0,8); 
    }//if
    
    return $this->bind_name;
  
  }//method
  
  /**
   *  get the args to pass to the publish/consume scripts
   *
   *  this should set atleast the "host" and "interface" keys, but can also set keys like "username",
   *  "password", "name" and any other keys that will be passed through the $options
   *      
   *  @since  3-9-11
   *  @return array key/val pairs      
   */
  abstract public function getArgs();
  
}//class

class MessageTestOrm extends MessageOrm 
{
  protected $bind_name = '';

  public function __construct($bind_name,MessageInterface $con_handler)
  {
    $this->bind_name = $bind_name;
    parent::__construct($con_handler);
  
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
  public function callback($msg)
  {
    ///out::e($msg);
  
    $sleep_count = empty($msg['sleep_count']) ? 0 : (int)$msg['sleep_count'];
    if($sleep_count > 0)
    {
      sleep($sleep_count);
    }//if
    
    return true;
  
  }//method
  
  public function getName()
  {
    return $this->bind_name;
  }//method


}//class
