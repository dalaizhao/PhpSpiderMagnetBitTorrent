<?php

namespace SurgicalFruit\library;

class SendEmailUtil
{
    function sendEmail($username, $email, $token, $textType)
    {
        $smtpserver     = "smtp.163.com";    //SMTP服务器，如：smtp.163.com
        $smtpserverport = 25; //SMTP服务器端口，一般为25
        $smtpusermail   = "@163.com"; //SMTP服务器的用户邮箱，如xxx@163.com
        $smtpuser       = ""; //SMTP服务器的用户帐号xxx@163.com
        $smtppass       = ""; //SMTP服务器的用户密码
        $smtp           = new Smtp($smtpserver, $smtpserverport, true, $smtpuser, $smtppass); //实例化邮件类
        $emailtype      = "HTML"; //信件类型，文本:text；网页：HTML
        $smtpemailto    = $email; //接收邮件方，本例为注册用户的Email
        $smtpemailfrom  = $smtpusermail; //发送邮件方，如xxx@163.com
        //邮件主体内容
        switch ($textType) {
            case 'find':
                $emailsubject = "";//邮件标题
                $emailbody    = "";
                break;
        }
        //发送邮件
        $rs = $smtp->sendmail($smtpemailto, $smtpemailfrom, $emailsubject, $emailbody, $emailtype);

        return $rs;
    }
}

?>