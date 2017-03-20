<?php

/**
 * @copyright 2016 David Zurborg
 * @author    David Zurborg <zurborg@cpan.org>
 * @link      https://github.com/zurborg/liblog-gelfsocket-php
 * @license   https://opensource.org/licenses/ISC The ISC License
 */

namespace Log;

use \Pirate\Hooray\Str;
use \Pirate\Hooray\Arr;

class GelfSocket extends \Psr\Log\AbstractLogger
{

    const MAX_LENGTH = 65534;

    /**
     * System hostname
     *
     * @var string Defaults to the result of `gethostname()`
     * @see https://php.net/manual/function.gethostname.php
     */
    public $hostname;

    /**
     * Default level, if not given.
     *
     * @var int Should be a single digit between 1 and 9, inclusive.
     */
    public $defaultLevel = 6;

    /**
     * @internal
     */
    protected $defaults = [];

    /**
     * @internal
     */
    const GELF_SPEC_VERSION = '1.1';

    /**
     * Translation of level name to level code
     *
     * @var int[]
     */
    const LEVELS = [
        'fatal'     => 1,
        'emerg'     => 1,
        'emergency' => 1,

        'alert'     => 2,

        'crit'      => 3,
        'critical'  => 3,

        'error'     => 4,

        'warn'      => 5,
        'warning'   => 5,

        'note'      => 6,
        'notice'    => 6,

        'info'      => 7,

        'debug'     => 8,

        'trace'     => 9,
        'core'      => 9,
    ];

    /**
     * @internal
     */
    protected $socket;

    /**
     * @internal
     */
    protected $sockfile;

    /**
     * @internal
     */
    protected $buffer = [];

    /**
     * @internal
     */
    protected $autoflush = true;

    /**
     * @internal
     */
    protected $silence = false;

    /**
     * @internal
     * @return Exception
     */
    private static function sockerr(string $info)
    {
        $code = socket_last_error();
        $text = socket_strerror($code);
        socket_clear_error();
        return new \Exception("$info: $text", $code);
    }

    private static function croak(\Throwable $exception)
    {
        error_log($exception->getMessage());
        return;
    }

    private static function throwOrCroak(\Throwable $exception, bool $quiet)
    {
        if ($quiet) {
            self::croak($exception);
        } else {
            throw $exception;
        }
    }

    /**
     * Instanciates a new GelfSocket logger
     *
     * @param string $sockfile Path to the unix domain socket, defaults to `/var/run/gelf.sock`.
     */
    public function __construct(string $sockfile = '/var/run/gelf.sock')
    {
        $this->sockfile = $sockfile;
        $this->hostname = gethostname();
    }

    /**
     * Enable autoflushing of log messages
     *
     * This is the default behavior.
     *
     * @return self
     */
    public function enableAutoflush()
    {
        $this->autoflush = true;
        return $this;
    }

    /**
     * Disable autoflushing of log messages
     *
     * @return self
     */
    public function disableAutoflush()
    {
        $this->autoflush = false;
        return $this;
    }

    /**
     * Flush all outstanding messages at the end of script execution.
     *
     * This is done with `register_shutdown_function`, which calls `flush()`.
     *
     * @return self
     */
    public function registerShutdown()
    {
        $self = $this;
        register_shutdown_function(
            function () use ($self) {
                if ($self instanceof \Log\GelfSocket) {
                    try {
                        $this->flush($this->silence);
                    } catch (\Exception $e) {
                        error_log("$e");
                    }
                }
            }
        );
        return $this;
    }

    /**
     * Defer flushing log messages to the end of script execution
     *
     * This is a shorthand for:
     *
     * ```php
     * $logger->disableAutoflush();
     * $logger->registerShutdown();
     * ```
     *
     * @return self
     */
    public function defer()
    {
        $this->disableAutoflush();
        $this->registerShutdown();
        return $this;
    }

    public function close()
    {
        if (!is_null($this->socket)) {
            @socket_close($this->socket);
            $this->socket = null;
        }

        return $this;
    }

    /**
     * Flush all outstanding messages
     *
     * This method does almost nothing if autoflushing is enabled.
     *
     * @throws Exception
     * @return self
     */
    public function flush(bool $quiet = false)
    {
        if (is_null($this->socket)) {
            socket_clear_error();
            $socket = @socket_create(AF_UNIX, SOCK_DGRAM, 0);

            if ($socket === false) {
                self::throwOrCroak(self::sockerr('socket_create'), $quiet);
                return $this->flush(false);
            }

            socket_clear_error();
            $result = @socket_connect($socket, $this->sockfile);

            if ($result == false) {
                self::throwOrCroak(self::sockerr('socket_connect'), $quiet);
                return $this->flush(false);
            }

            $this->socket = $socket;
        }

        while (count($this->buffer) > 0) {
            $message = array_shift($this->buffer);
            $length = strlen($message);

            if (!($length > 2)) {
                continue;
            }

            socket_clear_error();
            $sent = @socket_send($this->socket, $message, $length, 0);

            if ($sent === false) {
                $this->buffer[] = $message;
                $this->socket = null;
                throwself::throwOrCroak(self::sockerr('socket_send')->getMessage(), $quiet);
                return $this->flush(false);
            }

            if ($sent !== $length) {
                $this->buffer[] = $message;
                throw new \Exception("only $sent of $length bytes sent");
            }
        }

        return $this;
    }

    /**
     * Set default value, if `$key` is present in `$context`
     *
     * @param string $key
     * @param mixed  $val May be also a callable function
     * @return self
     */
    public function setDefault(string $key, $val)
    {
        $this->defaults[$key] = $val;
        return $this;
    }

    /**
     * @internal
     * @return void
     */
    private static function setIf(array &$array, string $key, $value)
    {
        if (!is_null(Arr::get($array, $key))) {
            return;
        }

        if (is_callable($value)) {
            $value = $value();
        }

        if (is_null($value)) {
            return;
        }

        if (!Str::ok($value)) {
            return;
        }

        $array[$key] = $value;

        return;
    }

    /**
     * @internal
     */
    private static function getServerVar(string $key, $default = null)
    {
        return Arr::get($_SERVER, $key, $default);
    }

    /**
     * @internal
     */
    private static function flatten(array $input, string $prefix = '')
    {
        $output = [];

        foreach ($input as $key => $val) {
            if (is_callable($val)) {
                $val = $val();
            }

            if (is_null($val)) {
                continue;
            }

            if (is_array($val)) {
                $val = self::flatten($val, $prefix.$key.'_');
                $output = array_merge($output, $val);
                continue;
            }

            if (!is_string($val)) {
                $val = "$val";
            }

            if (!strlen($val)) {
                continue;
            }

            $key = strtolower($prefix.$key);

            if (substr($key, 0, 1) !== '_') {
                $key = "_$key";
            }

            $output[$key] = $val;
        }

        return $output;
    }

    /**
     * @internal
     */
    protected function prepare(string $level, string $message, array $context)
    {
        $now = (float) microtime(true);
        $started = (float) self::getServerVar('REQUEST_TIME_FLOAT', 0);
        $offset = 0;
        if ($started > 0 and $started < $now) {
            $offset = (float) ($now - $started);
        }

        if (is_null($level)) {
            $level = $this->defaultLevel;
        }

        if (!is_int($level)) {
            if (!Arr::get(self::LEVELS, $level)) {
                $level = $this->defaultLevel;
            }

            $level = self::LEVELS[$level];
        }

        $context = array_merge($this->defaults, $context);

        if (strstr($message, "\n")) {
            list($short_message, $long_message) = explode("\n", $message, 2);
            $extras['full_message'] = trim($long_message);
            $message                = trim($short_message);
        }

        self::setIf($context, 'time_offset', sprintf('%0.06f', $offset));
        self::setIf($context, 'php_script', self::getServerVar('SCRIPT_FILENAME'));
        self::setIf($context, 'http_query', self::getServerVar('QUERY_STRING'));
        self::setIf($context, 'http_path', self::getServerVar('PATH_INFO'));
        self::setIf($context, 'http_addr', self::getServerVar('SERVER_ADDR'));
        self::setIf($context, 'http_vhost', self::getServerVar('SERVER_NAME'));
        self::setIf($context, 'http_proto', self::getServerVar('SERVER_PROTOCOL'));
        self::setIf($context, 'http_method', self::getServerVar('REQUEST_METHOD'));
        self::setIf($context, 'http_uri', self::getServerVar('REQUEST_URI'));
        self::setIf($context, 'http_host', self::getServerVar('HTTP_HOST'));
        self::setIf($context, 'http_connection', self::getServerVar('HTTP_CONNECTION'));
        self::setIf($context, 'http_user', self::getServerVar('REMOTE_USER'));
        self::setIf($context, 'client_addr', self::getServerVar('REMOTE_ADDR'));
        self::setIf($context, 'client_port', self::getServerVar('REMOTE_PORT'));
        self::setIf($context, 'session_id', session_id());

        return array_merge(
            self::flatten($context),
            [
                'host'      => $this->hostname,
                'timestamp' => sprintf('%0.06f', $now),
                'message'   => $message,
                'level'     => $level,
                'version'   => self::GELF_SPEC_VERSION,
            ]
        );
    }

    /**
     * Pushes a message into buffer
     *
     * Any values of `$context`, at any deep level, which are callable (a function for example) are executed at this point.
     *
     * This may be useful together with default values:
     *
     * ```php
     * $logger->setDefault('timestamp', function() { return time(); });
     * $logger->log(...);
     * ```
     *
     * The whole array of `$context` will be flattened, the subarrays are merged and the names of subkeys are concatenated together with an underscore.
     *
     * These keywords are set by default, when not present:
     *
     * | GELF key | PHP value |
     * | --- | --- |
     * | host             | `gethostname()` |
     * | timestamp        | `sprintf('%0.06', microtime(true))` |
     * | level            | `$level` |
     * | message          | `$message` |
     * | _time_offset     | `sprintf('%0.06', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])` |
     * | _php_script      | `$_SERVER['SCRIPT_FILENAME']` |
     * | _http_query      | `$_SERVER['QUERY_STRING']` |
     * | _http_path       | `$_SERVER['PATH_INFO']` |
     * | _http_addr       | `$_SERVER['SERVER_ADDR']` |
     * | _http_vhost      | `$_SERVER['SERVER_NAME']` |
     * | _http_proto      | `$_SERVER['SERVER_PROTOCOL']` |
     * | _http_method     | `$_SERVER['REQUEST_METHOD']` |
     * | _http_uri        | `$_SERVER['REQUEST_URI']` |
     * | _http_host       | `$_SERVER['HTTP_HOST']` |
     * | _http_connection | `$_SERVER['HTTP_CONNECTION']` |
     * | _http_user       | `$_SERVER['REMOTE_USER']` |
     * | _client_addr     | `$_SERVER['REMOTE_ADDR']` |
     * | _client_port     | `$_SERVER['REMOTE_PORT']` |
     * | _session_id      | `session_id()` |
     *
     * and they will be only filled if the corresponding variables are available. In a CLI application, only the `$_SERVER` variable `SCRIPT_FILENAME` is present, for example.
     *
     * @param  string $level   The severity level of log you are making.
     * @param  string $message The message you want to log.
     * @param  array  $context Additional information about the logged message
     * @return self
     */
    public function log($level, $message, array $context = [])
    {
        $gelf = $this->prepare("$level", "$message", $context);

        $json = \json_encode($gelf);
        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(\json_last_error_msg(), \json_last_error());
        }

        $length = strlen($json);
        if (strlen($json) > self::MAX_LENGTH) {
            throw new \Exception(sprintf("log message too large: %d bytes exceeds max length of %d bytes", $length, self::MAX_LENGTH));
        }

        $this->buffer[] = $json;

        if ($this->autoflush) {
            try {
                $this->flush();
            } catch (\Exception $e) {
                error_log("$e");
            }
        }

        return $this;
    }
}
