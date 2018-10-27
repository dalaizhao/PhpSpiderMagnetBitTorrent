<?php

namespace SurgicalFruit\common;

class Encode
{
    /**
     * 保存编码数据
     * @var mixed
     */
    private $data;

    /**
     * 析构函数, 传入要编码的数据
     * @param mixed $data 要编码的数据
     */
    private function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * bencode编码
     * @param  mixed $data 要编码的数据
     * @return string       编码后的数据
     */
    static public function encode($data)
    {
        if (is_object($data)) {
            if (method_exists($data, 'toArray'))
                $data = $data->toArray();
            else
                $data = (array)$data;
        }

        $encode  = new self($data);
        $encoded = $encode->do_encode();

        return $encoded;
    }

    /**
     * 选择操作类型
     * @param  mixed $data 要编码的数据
     * @return string       编码后的数据
     */
    private function do_encode($data = null)
    {
        $data = is_null($data) ? $this->data : $data;

        if (is_array($data) && (isset($data[0]) || empty($data))) {
            return $this->encode_list($data);
        } elseif (is_array($data)) {
            return $this->encode_dict($data);
        } elseif (is_integer($data) || is_float($data)) {
            $data = sprintf("%.0f", round($data, 0));

            return $this->encode_integer($data);
        } else {
            return $this->encode_string($data);
        }
    }

    /**
     * 编码数字类型数据
     * @param  integer $data 要编码的数据
     * @return string       编码后的数据
     */
    private function encode_integer($data = null)
    {
        $data = is_null($data) ? $this->data : $data;

        return sprintf("i%.0fe", $data);
    }

    /**
     * 编码字符串类型数据
     * @param  string $data 要编码的数据
     * @return string       编码后的数据
     */
    private function encode_string($data = null)
    {
        $data = is_null($data) ? $this->data : $data;

        return sprintf("%d:%s", strlen($data), $data);
    }

    /**
     * 编码数组数据
     * @param  array $data 要编码的数据
     * @return string           编码后的数据
     */
    private function encode_list(array $data = null)
    {
        $data = is_null($data) ? $this->data : $data;
        $list = '';

        foreach ($data as $value)
            $list .= $this->do_encode($value);

        return "l{$list}e";
    }

    /**
     * 编码词典类型数据
     * @param  array $data 要编码的数据
     * @return string           编码后的数据
     */
    private function encode_dict(array $data = null)
    {
        $data = is_null($data) ? $this->data : $data;
        ksort($data);
        $dict = '';

        foreach ($data as $key => $value)
            $dict .= $this->encode_string($key) . $this->do_encode($value);

        return "d{$dict}e";
    }
}