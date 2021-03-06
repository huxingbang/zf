<?php

namespace zf\components;

use JsonSerializable, stdClass;

class Response
{
	protected $statuses = [
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported'
	];
	protected $headers = [];
	protected $viewEngine;
	protected $router;
	protected $buffer;

	public $status = 200;
	public $body = '';
	public $contentType = 'text/html';
	public $charset = 'utf-8';

	public $debug;

	public function __construct($engine, $router)
	{
		$this->viewEngine = $engine;
		$this->router = $router;
	}

	public function lastModified($time)
	{
		header('Last-Modified: '. gmdate(DATE_RFC2822, $time));
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $time)
		{
			 header('HTTP/1.0 304 Not Modified');
			 exit;
		}
	}

	public function cacheControl(...$args)
	{
		if(is_array(end($args)))
		{
			$args += array_pop($args);
		}
		$this->header('Cache-Control', $args);
	}

	public function stderr($content)
	{
		file_put_contents('php://stderr', $content, FILE_APPEND);
	}

	public function trace($object)
	{
		$this->stderr($this->repr($object).PHP_EOL);
		ob_flush();
	}

	public function flush()
	{
		ob_flush();
	}

	public function send()
	{
		if (!array_key_exists($this->status, $this->statuses))
		{
			throw new \Exception("invalid status code '$this->status'");
		}

		$buffer = ob_get_clean();

		header('HTTP/1.1 ' . $this->status. ' ' . $this->statuses[$this->status]);
		header('Status: ' . $this->status);
		header('Content-Type: ' . $this->contentType . '; charset=' . $this->charset);
		$this->sendHeader();

		if ($buffer)
		{
			$this->stderr($buffer);
		}
		exit($this->body);
	}

	public function notFound($message='')
	{
        $this->status = 404;
        $this->body = $message;
        $this->send();
	}

	public function redirect($url, $permanent=false)
	{
		header('Location: ' . $url, true, $permanent ? 301 : 302);
		exit;
	}

	public function header($name, $value=null)
	{
		$this->headers[$name] = $value;
		return $this;
	}

	public function status($code)
	{
		$this->status = $code;
		return $this;
	}

	public function sendHeader()
	{
		foreach($this->headers as $name => $value)
		{
			if(is_array($value))
			{
				foreach($value as $k=>$v)
				{
					$options[] = is_int($k) ? $v : $k.'='.$v;
				}
				$value = implode(', ', $options);
			}
			header(is_null($value) ? $name : $name. ': '. $value);
		}
	}

	public function body($body, $contentType=null, $charset=null)
	{
		$this->body = $body;
		if ($contentType) $this->contentType = $contentType;
		if ($charset) $this->charset = $charset;
	}

	public function render($template, $vars=null)
	{
		return $this->viewEngine->render($template, $vars);
	}

	public function download($content, $name, $size=null)
	{
		header('Cache-Control: public, must-revalidate');
		header('Pragma: hack');
		header('Content-Type: application/octet-stream');
		if($size) header('Content-Length: ' . $size);
		header('Content-Disposition: attachment; filename="'.$name.'"');
		header("Content-Transfer-Encoding: binary\n");
		exit($content);
	}

	private function repr($object)
	{
		if(is_string($object))
		{
			return $object;
		}
		elseif(is_array($object) || $object instanceof JsonSerializable || $object instanceof stdClass)
		{
			return json_encode($object, JSON_UNESCAPED_UNICODE);
		}
		else
		{
			return var_export($object, true);
		}
	}
}
