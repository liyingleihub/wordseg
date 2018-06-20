<?php
namespace Wordseg;

namespace SRC\TEST\WORDSEG;

use \SRC\CONFIG\CONFIG;

error_reporting(E_ALL ^ E_NOTICE);
require_once("../src/config/Config.php");
require_once("../src/model/FenciModel.php");
require_once("../src/wordseg/discovery.php");

ini_set('memory_limit',MEMORY_LIMIT);

class wordseg 
{
    public static $para_config = array(
    
        'file_path_input' => '../src/wordseg/sample14.txt',

        'file_path_output' => 'new_dict.txt',

        'memory_limit' => '1024m',

        'max_length' => 6,

        'jbdict_path' => '../src/wordseg/dict.txt',

        'jbdict_switch' => 'on',

        'tf_num' => 3,

        'lr_entropy' => 0.6,

        'dadson_count' => 5,

        'dadson_length' => 2,

        'cement_step_2' => 40,

        'cement_step_3' => 60,

        'cement_step_4' => 80,

        'cement_step_5' => 100,

        'cement_step_6' => 150,

        //'cement_step_7' = ....

    );

    public function main () 
    {
        $config = new \SRC\CONFIG\CONFIG\Config();
        $config->init(self::$para_config);             

        $discovery = new \SRC\WORDSEG\DISCOVERY\discovery();
        $discovery->main();
    }

     
}

$wordseg = new wordseg();
$wordseg->main();
exit;
