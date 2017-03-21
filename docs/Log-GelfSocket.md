Log\GelfSocket
===============






* Class name: GelfSocket
* Namespace: Log
* Parent class: Psr\Log\AbstractLogger



Constants
----------


### MAX_LENGTH

    const MAX_LENGTH = 65534





### LEVELS

    const LEVELS = array('fatal' => 1, 'emerg' => 1, 'emergency' => 1, 'alert' => 2, 'crit' => 3, 'critical' => 3, 'error' => 4, 'warn' => 5, 'warning' => 5, 'note' => 6, 'notice' => 6, 'info' => 7, 'debug' => 8, 'trace' => 9, 'core' => 9)





Properties
----------


### $hostname

    public string $hostname

System hostname



* Visibility: **public**


### $defaultLevel

    public integer $defaultLevel = 6

Default level, if not given.



* Visibility: **public**


Methods
-------


### __construct

    mixed Log\GelfSocket::__construct(string $sockfile)

Instanciates a new GelfSocket logger



* Visibility: **public**


#### Arguments
* $sockfile **string** - &lt;p&gt;Path to the unix domain socket, defaults to &lt;code&gt;/var/run/gelf.sock&lt;/code&gt;.&lt;/p&gt;



### enableAutoflush

    \Log\GelfSocket Log\GelfSocket::enableAutoflush()

Enable autoflushing of log messages

This is the default behavior.

* Visibility: **public**




### disableAutoflush

    \Log\GelfSocket Log\GelfSocket::disableAutoflush()

Disable autoflushing of log messages



* Visibility: **public**




### registerShutdown

    \Log\GelfSocket Log\GelfSocket::registerShutdown()

Flush all outstanding messages at the end of script execution.

This is done with `register_shutdown_function`, which calls `flush()`.

* Visibility: **public**




### defer

    \Log\GelfSocket Log\GelfSocket::defer()

Defer flushing log messages to the end of script execution

This is a shorthand for:

```php
$logger->disableAutoflush();
$logger->registerShutdown();
```

* Visibility: **public**




### close

    mixed Log\GelfSocket::close()





* Visibility: **public**




### flush

    \Log\GelfSocket Log\GelfSocket::flush(\Log\bool $quiet)

Flush all outstanding messages

This method does almost nothing if autoflushing is enabled.

* Visibility: **public**


#### Arguments
* $quiet **Log\bool**



### setDefault

    \Log\GelfSocket Log\GelfSocket::setDefault(string $key, mixed $val)

Set default value, if `$key` is present in `$context`



* Visibility: **public**


#### Arguments
* $key **string**
* $val **mixed** - &lt;p&gt;May be also a callable function&lt;/p&gt;



### log

    \Log\GelfSocket Log\GelfSocket::log(string $level, string $message, array $context)

Pushes a message into buffer

Any values of `$context`, at any deep level, which are callable (a function for example) are executed at this point.

This may be useful together with default values:

```php
$logger->setDefault('timestamp', function() { return time(); });
$logger->log(...);
```

The whole array of `$context` will be flattened, the subarrays are merged and the names of subkeys are concatenated together with an underscore.

These keywords are set by default, when not present:

| GELF key | PHP value |
| --- | --- |
| host             | `gethostname()` |
| timestamp        | `sprintf('%0.06', microtime(true))` |
| level            | `$level` |
| message          | `$message` |
| _time_offset     | `sprintf('%0.06', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])` |
| _php_script      | `$_SERVER['SCRIPT_FILENAME']` |
| _http_query      | `$_SERVER['QUERY_STRING']` |
| _http_path       | `$_SERVER['PATH_INFO']` |
| _http_addr       | `$_SERVER['SERVER_ADDR']` |
| _http_vhost      | `$_SERVER['SERVER_NAME']` |
| _http_proto      | `$_SERVER['SERVER_PROTOCOL']` |
| _http_method     | `$_SERVER['REQUEST_METHOD']` |
| _http_uri        | `$_SERVER['REQUEST_URI']` |
| _http_host       | `$_SERVER['HTTP_HOST']` |
| _http_connection | `$_SERVER['HTTP_CONNECTION']` |
| _http_user       | `$_SERVER['REMOTE_USER']` |
| _client_addr     | `$_SERVER['REMOTE_ADDR']` |
| _client_port     | `$_SERVER['REMOTE_PORT']` |
| _session_id      | `session_id()` |

and they will be only filled if the corresponding variables are available. In a CLI application, only the `$_SERVER` variable `SCRIPT_FILENAME` is present, for example.

* Visibility: **public**


#### Arguments
* $level **string** - &lt;p&gt;The severity level of log you are making.&lt;/p&gt;
* $message **string** - &lt;p&gt;The message you want to log.&lt;/p&gt;
* $context **array** - &lt;p&gt;Additional information about the logged message&lt;/p&gt;



### logException

    \Log\GelfSocket Log\GelfSocket::logException(string $level, \Exception $exception, array $context)

Log an exception

```php
try {
  // do something that throws
} catch (\Exception $e) {
  $logger->logException($e);
}
```

The full log message contains of the exception message and the stack trace as string

The log fields contains of:
- `class` The class name of the exception, whereas `\` is replaced by dots and the keyword `Exception` at the end is replaced by `~`
- `code` The code of the exception
- `file` The filename
- `line` The line number

* Visibility: **public**


#### Arguments
* $level **string**
* $exception **Exception**
* $context **array** - &lt;p&gt;Additional params&lt;/p&gt;



### installExceptionHandler

    \Log\GelfSocket Log\GelfSocket::installExceptionHandler()

Install an exception handler



* Visibility: **public**




### installErrorHandler

    \Log\GelfSocket Log\GelfSocket::installErrorHandler($mask)

Install an error handler



* Visibility: **public**


#### Arguments
* $mask **mixed** - &lt;p&gt;Bitmask of errors to be handled.&lt;/p&gt;


