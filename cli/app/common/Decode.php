<?php
/**
 * bencode解码类
 */

namespace SurgicalFruit\common;

class Decode
{
    /**
     * 解码数据
     * @var string
     */
    private $source;
    /**
     * 数据长度
     * @var integer
     */
    private $length;
    /**
     * 当前索引
     * @var integer
     */
    private $offset = 0;

    /**
     * 析构函数, 传入数据并计算长度
     * @param string $source 要解码的数据
     */
    private function __construct($source)
    {
        $this->source = $source;
        $this->length = strlen($source);
    }

    /**
     * 解码bencode数据
     * @param  string $source 要解码的数据
     * @return mixed         解码后的数据
     */
    static public function decode($source)
    {
        // 检查数据是否正确
        if (!is_string($source))
            return '';

        // 调用类本身完成解码
        $decode  = new self($source);
        $decoded = $decode->do_decode();

        // 验证数据
        if ($decode->offset != $decode->length)
            return '';

        return $decoded;
    }

    /**
     * 选择操作类型
     * @return mixed 解码后的数据
     */
    private function do_decode()
    {
        // 截取数据字符判断操作类型
        switch ($this->get_char()) {
            case 'i':
                ++$this->offset;

                return $this->decode_integer();
            case 'l':
                ++$this->offset;

                return $this->decode_list();
            case 'd':
                ++$this->offset;

                return $this->decode_dict();
            default:
                if (ctype_digit($this->get_char()))
                    return $this->decode_string();
        }

        return '';
    }

    /**
     * 解码数字类型数据
     * @return integer 解码后的数据
     */
    private function decode_integer()
    {
        $offset_e = strpos($this->source, 'e', $this->offset);

        if ($offset_e === false)
            return '';

        $current_off = $this->offset;

        if ($this->get_char($current_off) == '-')
            ++$current_off;

        if ($offset_e === $current_off)
            return '';

        while ($current_off < $offset_e) {
            if (!ctype_digit($this->get_char($current_off)))
                return '';

            ++$current_off;
        }

        $value          = substr($this->source, $this->offset, $offset_e - $this->offset);
        $absolute_value = (string)abs($value);

        if (1 < strlen($absolute_value) && '0' == $value[0])
            return '';

        $this->offset = $offset_e + 1;

        return $value + 0;
    }

    /**
     * 解码字符串类型数据
     * @return string 解码后的数据
     */
    private function decode_string()
    {
        if ('0' === $this->get_char() && ':' != $this->get_char($this->offset + 1))
            return '';

        $offset_o = strpos($this->source, ':', $this->offset);

        if ($offset_o === false)
            return '';

        $content_length = (int)substr($this->source, $this->offset, $offset_o);

        if (($content_length + $offset_o + 1) > $this->length)
            return '';

        $value        = substr($this->source, $offset_o + 1, $content_length);
        $this->offset = $offset_o + $content_length + 1;

        return $value;
    }

    /**
     * 解码数组类型数据
     * @return array 解码后的数据
     */
    private function decode_list()
    {
        $list        = array();
        $terminated  = false;
        $list_offset = $this->offset;

        while ($this->get_char() !== false) {
            if ($this->get_char() == 'e') {
                $terminated = true;
                break;
            }

            $list[] = $this->do_decode();
        }

        if (!$terminated && $this->get_char() === false)
            return '';

        $this->offset++;

        return $list;
    }

    /**
     * 解码词典类型数据
     * @return array 解码后的数据
     */
    private function decode_dict()
    {
        $dict        = array();
        $terminated  = false;
        $dict_offset = $this->offset;

        while ($this->get_char() !== false) {
            if ($this->get_char() == 'e') {
                $terminated = true;
                break;
            }

            $key_offset = $this->offset;

            if (!ctype_digit($this->get_char()))
                return '';

            $key = $this->decode_string();

            if (isset($dict[$key]))
                return '';

            $dict[$key] = $this->do_decode();
        }

        if (!$terminated && $this->get_char() === false)
            return '';

        $this->offset++;

        return $dict;
    }

    /**
     * 截取数据
     * @param  integer $offset 截取索引
     * @return string|false         截取到的数据
     */
    private function get_char($offset = null)
    {
        if ($offset === null)
            $offset = $this->offset;

        if (empty($this->source) || $this->offset >= $this->length)
            return false;

        return $this->source[$offset];
    }
}