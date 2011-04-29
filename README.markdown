# What do you use message passing for?

Message passing is great when you want to move some functionality of your code to the backend where it can be processed without making the user, or the code, wait.

# How the messaging system works

The generic MessageOrm is the base class that all messages should extend, so if you want an email message, you would do:

    class EmailMsg extends MessageOrm
    {
      /**
       *  this will be called every time a message is consumed
       */
      public function callback($msg)
      {
        /* do what you will to the $msg here */
      }//method

    }//class

The messages are passed back and forth using the `MessageInterface` class, specifically, the classes that extend `MessageInterface` (eg, `DropfileInterface` and `NetGearmanInterface`). So, for example, to have our `EmailMsg` class use the `DropfileInterface`:

    $interface = new DropfileInterface();
    $interface->setHost('path/to/store/messages');

    $msg = array(
      'to' => 'bob@example.com',
      'from' => 'alice@example.com',
      'title' => 'test email',
      'body' => 'testing, testing, 1, 2, 3.'
    );

    $email_msg = new EmailMsg($interface);
    $email_msg->publish($msg);

Now to process that message in another script somewhere else:

    $interface = new DropfileInterface();
    $interface->setHost('path/to/store/messages');

    $email_msg = new EmailMsg($interface);
    $email_msg->consume();
        
Take a look at the `MessageOrm` class to see how to use the other consume methods like `consumeForCount()` or `consumeForTime()`.

# Adding Your Interface

The included interfaces happen to be the interfaces we use at [Plancast](http://plancast.com), but you can add your own interface just by extending `MessageInterface` and implementing the required abstract methods. So, for instance, if you wanted to use [RabbitMQ](http://www.rabbitmq.com/) to pass messages you would just need to extend `MessageInterface` and implement the required methods to interact with RabbitMQ.

# Dependencies

PHP >= 5.0

We've tested this on php 5.2.4 and 5.3 but I think it should work on anything 5.0. The tests require PHPUnit.

The code should run on both Windows and Linux.

# License

[The MIT License](http://www.opensource.org/licenses/mit-license.php)
