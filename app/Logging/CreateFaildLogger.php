<?php

namespace App\Logging;

use App\Commons\Consts;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class CreateFaildLogger
{
    /**
     * カスタムMonologインスタンスの生成
     *
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        // monologが理解できるlevel表記に変更
        $level = Logger::toMonologLevel($config["level"]);
        // ルーティング設定
        $hander = new RotatingFileHandler($config["path"], $config["days"], $level);  
        // ログのフォーマット指定
        // ここでは指定(null)しないが、1つ目の引数にログのformatを指定することも可能
        $hander->setFormatter(new LineFormatter(null, Consts::DATE_FORMAT_YMDHIS, true, true));
        // ログ作成 Custom1はログに表記される
        $logger = new Logger("Faild");  
        $logger->pushHandler($hander);
        return $logger;
    }
}
