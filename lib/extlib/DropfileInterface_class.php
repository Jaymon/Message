<?php
/**
 *  implement a MessageInterface using dropfiles
 *  
 *  @version 0.5
 *  @author Jay Marcyes {@link http://marcyes.com}
 *  @since 5-16-10
 *  @package Message
 ******************************************************************************/
class DropfileInterface extends MessageInterface
{
  /**
   *  a unique hash that will be used to generate file names when {@link publish()} is
   *  called
   *
   *  @see  getHash()      
   *  @since  3-2-11
   *  @var  string
   */
  protected $hash = '';

  /**
   *  hold information about the opened message
   *  
   *  @since  3-23-11   
   *  @var  array
   */
  protected $file_map = array();

  /**
   *  connect to the {@link $con_handler}
   *
   *  @param  string  $host this is the basepath where the dropfiles will be placed
   *  @param  string  $username
   *  @param  string  $password
   *  @param  array $options  any other options needed to connect can be passed in through here            
   *  @return boolean
   */
  protected function _connect($host,$username,$password,array $options)
  { 
    $this->setField('extension','msg');
    
    // we don't actually use a connection since everything is done with files...
    $this->con_handler = null;
    
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
  protected function _bind($name)
  {
    // canary...
    $this->assureFields('host');
  
    // host / name
    $this->setField(
      'path',
      $this->assurePath(
        join(
          DIRECTORY_SEPARATOR,
          array(
            $this->formatPath($this->getField('host')),
            $name
          )
        )
      )
    );
    
    return true;
    
  }//method
  
  /**
   *  publish a message
   *  
   *  @param  string  $msg   
   *  @return boolean
   */
  protected function _publish($msg)
  {
    // canary...
    $this->assureFields('path','extension');

    // find a unique filename...
    $is_file = false;
    $hash = $this->getHash();
    do
    {
      $path = join(
        DIRECTORY_SEPARATOR,
        array(
          $this->getField('path'),
          sprintf('%s%s.%s',microtime(true),$hash,$this->getField('extension'))
        )
      );
      
      $is_file = is_file($path);
      
    }while($is_file);
    
    $ret = file_put_contents($path,$msg,LOCK_EX);
    if($ret === false)
    {
      throw new RuntimeException('message failed to be written');
    }//if
    
    return true;
  
  }//method
  
  /**
   *  get a published message
   *  
   *  NOTE: I'm suppressing warnings because when more than one instance is
   *  being run you'll get lots of permission denied, or file no longer exists warnings, which
   *  are annoying, so we are suprressing them because it's ok we get them (ie, they are
   *  not really an error-like condition we need to be warned about)         
   *      
   *  @return string  a message, or null if no message has been published
   */
  protected function _get()
  {
    $ret_str = $msg = null;
    $this->file_map = array();
    $this->silence();
  
    $path_iterator = new RecursiveDirectoryIterator($this->getField('path'));
    foreach($path_iterator as $file)
    {
      // canary...
      if(!$file->isFile()){ continue; }//if
      
      $file_path = $file->getRealPath();
      // for some reason, when multiple processes are running sometimes $file_path is empty...
      if(empty($file_path)){ continue; }//if
      // make sure the file hasn't already been truncated (ie, read)...
      if($file->getSize() <= 0){ continue; }//if
      
      $fp = fopen($file_path,'r+');
      
      if(is_resource($fp))
      {
        // lock the file if it isn't empty...
        if(flock($fp,LOCK_EX | LOCK_NB))
        {
          $msg = stream_get_contents($fp,-1,0);
        
          if(empty($msg))
          {
            flock($fp,LOCK_UN);
            fclose($fp);
            
            $this->deletePath($file_path,true);
            
          }else{
            
            $this->file_map['file_path'] = $file_path;
            $this->file_map['fp'] = $fp;
            
            $ret_str = $this->getMsg($msg);
            break;
            
          }//if/else
        
        }else{
          fclose($fp);
        }//if/else
          
      }//if
          
    }//foreach
    
    $this->unsilence();
    
    return $ret_str;
  
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
  protected function _consume()
  {
    $msg = null;
    $this->silence();
    
    do
    {
      $msg = $this->_get();
      
      // sleep for a bit to see if that gives the processor a break...
      if($msg === null){ usleep(100); }//if  
      
    }while($msg === null);
    
    $this->unsilence();
    
    return call_user_func($this->getField('consumer_callback'),$msg);
    
  }//method
  
  /**
   *  set a consume callback that will be called when {@link listen()} retrieves a message
   *  
   *  @param  callback  $callback a guaranteed valid php callback
   *  @return boolean
   */
  protected function _setConsumer($callback)
  {
    $this->setField('consumer_callback',$callback);
    return true;
    
  }//method
  
  /**
   *  acknowledge succesful receipt of a message
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean        
   */
  public function ackSuccess($msg)
  {
    // clear the message so other get() requests will move on (this is the
    // one thing we can do with an exclusive lock to tell other processes
    // we have already looked at the file, I wish we could delete under an 
    // exclusive lock)...
    ftruncate($this->file_map['fp'],0);
    clearstatcache();
    flock($this->file_map['fp'],LOCK_UN);
    fclose($this->file_map['fp']);
    
    $this->deletePath($this->file_map['file_path'],true);
  
  }//method
  
  /**
   *  acknowledge failed receipt of a message
   *  
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return boolean
   */
  public function ackFailure($msg)
  {
    flock($this->file_map['fp'],LOCK_UN);
    fclose($this->file_map['fp']);
  
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
   *  @param  mixed $msg  the raw message returned/supported by this interface
   *  @return string  the actual message $msg might wrap          
   */
  public function getMsg($msg)
  {
    return $msg;
  }//method

  /**
   *  remove the file found at path
   *
   *  @since  4-27-11
   *  @param  string  $path the path to a file to remove
   *  @param  boolean $keep_trying  true to keep trying until the file is deleted
   *  @return boolean            
   */
  protected function deletePath($path,$keep_trying = false)
  {
    // canary...
    if(empty($path)){ return false; }//if
    
    $ret_bool = true;
    $this->silence();
    
    // we might keep trying to delete it until we can...
    // the reason we might have to try multiple times is another process
    // might have locked it but since we've already truncated it under our
    // exclusive lock they should drop it relatively quickly...
    while(is_file($path) && !($ret_bool = unlink($path)))
    {
      if($keep_trying)
      {
        usleep(10); // sleep for a tenth of a second
        // if the file is deleted by another process after is_file is called but
        // before unlink finishes, then is_file will still return true and unlink
        // will still return false, causing an infinite loop
        clearstatcache();
        
      }else{
        break;
      }//if/else
    }//while
    
    $this->unsilence();
    
    return $ret_bool;
  
  }//method

  /**
   *  make sure a path exists and is writable, also make sure it doesn't end with
   *  a directory separator
   *  
   *  @param  string  $path
   *  @return string  the $path
   */
  protected function assurePath($path)
  {
    // make sure path isn't empty...
    if(empty($path))
    {
      throw new InvalidArgumentException('cannot verify that an empty $path exists');
    }//if
    
    // make sure path is directory, try to create it if it isn't...
    if(!is_dir($path))
    {
      if(!mkdir($path,0777,true))
      {
        // sometimes mkdir will fail with a warning "mkdir(): File exists ..." that means that another
        // process beat this process to the punch in creating the path and the path actually does exist now,
        // so we want to check again before declaring it a failure...
        clearstatcache();
        if(!is_dir($path))
        {
          throw new UnexpectedValueException(
            sprintf('"%s" is not a valid directory and the attempt to create it failed.',$path)
          );
        }//if
      }//if
    }//if
  
    // make sure the path is writable...
    if(!is_writable($path))
    {
      throw new RuntimeException(sprintf('cannot write to $path (%s)',$path));
    }//if

    return $this->formatPath($path);
  
  }//method
  
  /**
   *  format $path to a standard format so we can guarrantee that all paths are formatted
   *  the same
   *  
   *  @since  4-20-10   
   *  @param  string  $path
   *  @return string  the $path, formatted for consistency
   */
  protected function formatPath($path)
  {
    // canary...
    if(empty($path)){ return ''; }//if
  
    // make sure path doesn't end with a slash...
    if(mb_substr($path,-1) == DIRECTORY_SEPARATOR)
    {
      $path = mb_substr($path,0,-1);
    }//if
    
    return $path;
  
  }//method
  
  /**
   *  this hash will be used to generated the dropfile name
   *  
   *  the reason we have this is because in really concurrent processes, 2 processes
   *  might pick the same file name, so this will ensure that the msg name is even more
   *  unique by having a unique hash for each process            
   *  
   *  @since  3-2-11   
   *  @return string
   */
  protected function getHash()
  {
    if(empty($this->hash))
    {
      $this->hash = (string)getmypid();
      if(empty($this->hash))
      {
        $this->hash = uniqid('',true);
        $bits = explode('.',$this->hash);
        $this->hash = sprintf('%s%s',end($bits),rand(0,100));
        
      }//if
      
    }//if
    
    return $this->hash;
  
  }//method
  
  /**
   *  turn certain error levels off because some external libraries and internal
   *  php functions rais errors even when there is no problem   
   *
   *  @since  4-27-11   
   */        
  protected function silence()
  {
    // canary, don't silence if we've already done it...
    $count = $this->getField('error.silenced',0);
    if($count > 0)
    {
      $count++;
      $this->setField('error.silenced',$count);
      return true;
    }//if
  
    $current_error_level = error_reporting();
    $change_error_level = (boolean)($current_error_level & E_WARNING);
    if($change_error_level)
    {
      error_reporting($current_error_level ^ E_WARNING);
      
      $this->setField('error.silenced',1);
      $this->setField('error.current',$current_error_level);
      
    }//if
    
    return true;
  
  }//method
  
  /**
   *  return error reporting back to what it originally was
   *  
   *  @since  4-27-11
   */
  protected function unsilence()
  {
    $count = $this->getField('error.silenced',0);
    $count--;
  
    if($count === 0)
    {
      $this->setField('error.silenced',0);
      error_reporting($this->getField('error.current'));  
      
    }//if
  
    return true;
  
  }//method

}//class
