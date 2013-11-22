<?php
/**
 * Created by Miljenko Rebernisak <miljenko.rebernisak@prelovac.com>.
 * Version: 1.0.0
 */
namespace ChurnBee\Library;
include(__DIR__ . "/CBConf.php");
include(__DIR__ . "/CurlUtil.php");
class Async {

    public function run(){
        $stdin = fopen('php://stdin', 'r');
        $timeout= trim(fgets($stdin));
        $method= trim(fgets($stdin));
        $url = trim(fgets($stdin));


        $curl=new CurlUtil($method,$url);
        $curl->setTimeOut($timeout);
        $curl->send();
        $response=$curl->getResult();
        $error=$curl->getError();

        if(count($error)){
            $stderr = fopen('php://stderr', 'r');
            fputs($stderr,implode("",$error));
            fclose($stderr);
        }else{
            $stdout = fopen('php://stdout', 'w');
            fputs($stdout,$curl->getStatusCode()."\r\n");
            fputs($stdout,$response,strlen($response));

            fclose($stdout);

        }
        fclose($stdin);

    }

}

$a=new Async();
$a->run();