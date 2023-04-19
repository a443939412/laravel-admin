<?php

namespace Encore\Admin\Actions;

use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use Psr\Log\LoggerInterface;

/**
 * Class Response.
 *
 * @method $this topCenter()
 * @method $this topLeft()
 * @method $this topRight()
 * @method $this bottomLeft()
 * @method $this bottomCenter()
 * @method $this bottomRight()
 * @method $this topFullWidth()
 * @method $this bottomFullWidth()
 * @method $this timeout($timeout = 5000)
 */
class Response
{
    /**
     * @var bool
     */
    public $status = true;

    /**
     * @var \Exception
     */
    public $exception;

    /**
     * @var array
     */
    public $toastrMethods = [
        'topCenter', 'topLeft', 'topRight',
        'bottomLeft', 'bottomCenter', 'bottomRight',
        'topFullWidth', 'bottomFullWidth', 'timeout',
    ];

    /**
     * @var
     */
    protected $plugin;

    /**
     * @var array
     */
    protected $then = [];

    /**
     * @var string
     */
    protected $html = '';

    /**
     * @return $this
     */
    public function toastr()
    {
        if (!$this->plugin instanceof Toastr) {
            $this->plugin = new Toastr();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function swal()
    {
        if (!$this->plugin instanceof SweatAlert2) {
            $this->plugin = new SweatAlert2();
        }

        return $this;
    }

    /**
     * @return SweatAlert2
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    public function success(string $message = '')
    {
        return $this->show('success', $message);
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    public function info(string $message = '')
    {
        return $this->show('info', $message);
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    public function warning(string $message = '')
    {
        return $this->show('warning', $message);
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    public function error(string $message = '')
    {
        return $this->show('error', $message);
    }

    /**
     * @param string $type
     * @param string $title
     *
     * @return $this
     */
    protected function show($type, $title = '')
    {
        $this->getPlugin()->show($type, $title);

        return $this;
    }

    /**
     * Send a redirect response.
     *
     * @param string $url
     *
     * @return $this
     */
    public function redirect(string $url)
    {
        $this->then = ['action' => 'redirect', 'value' => $url];

        return $this;
    }

    /**
     * Send a open new window response.
     *
     * @param string $url
     */
    public function open(string $url)
    {
        $this->then = ['action' => 'open', 'value' => $url];

        return $this;
    }

    /**
     * Send a location redirect response.
     *
     * @param string $location
     *
     * @return $this
     */
    public function location(string $location)
    {
        $this->then = ['action' => 'location', 'value' => $location];

        return $this;
    }

    /**
     * Send a download response.
     *
     * @param string $url
     *
     * @return $this
     */
    public function download($url)
    {
        $this->then = ['action' => 'download', 'value' => $url];

        return $this;
    }

    /**
     * Send a refresh response.
     *
     * @return $this
     */
    public function refresh()
    {
        $this->then = ['action' => 'refresh', 'value' => true];

        return $this;
    }

    /**
     * Send a html response.
     *
     * @param string $html
     *
     * @return $this
     */
    public function html($html = '')
    {
        $this->html = $html;

        return $this;
    }

    /**
     * @param \Exception $exception
     *
     * @return $this
     */
    public static function withException(\Exception $exception)
    {
        $response = new static();

        $response->status = false;

        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->implode("\n");
        } elseif ($exception instanceof QueryException) {
            try {
                Container::getInstance()->make(LoggerInterface::class)->debug($exception->getMessage());
            } catch (\Exception $ex) {
                throw $ex; // $hidden = Container::getInstance()->isProduction();
            }

            if ($exception->getCode() == '23000' && // 只适用于mysql驱动
                strpos(substr($ex->getMessage(), 40, 90), '1062') !== false) { // php 8: str_contains()
                $message = '重复数据[Duplicate entry]，请检查！';
            } else {
                $message = '内部错误[SQL Err]，请尝试联系系统维护人员！';
            }
        } elseif ($exception instanceof SpreadsheetException) {
            $message = $exception instanceof SpreadsheetReaderException
                ? '异常终止：请尝试将扩展名改为"xlsx"后重试(' . $exception->getMessage() . ')'
                : '异常终止：请尝试删除多余工作表后重试 (' . $exception->getMessage() . ')';
        } else {
            $message = $exception->getMessage();
        }

        return $response->toastr()->topCenter()->error($message);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function send()
    {
        $data = array_merge(
            ['status' => $this->status, 'then' => $this->then],
            $this->getPlugin()->getOptions()
        );

        if ($this->html) {
            $data['html'] = $this->html;
        }

        return response()->json($data);
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return $this
     */
    public function __call($method, $arguments)
    {
        if (in_array($method, $this->toastrMethods)) {
            $this->toastr();
        }

        $this->getPlugin()->{$method}(...$arguments);

        return $this;
    }
}
