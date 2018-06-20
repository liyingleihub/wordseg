<?php
namespace Wordseg;

namespace SRC\CONFIG\CONFIG;

class Config
{

    public function init ($config) 
    { 
        define(FILE_PATH_INPUT, $config['file_path_input']);              // 要进行新词发现的原数据
        define(FILE_PATH_OUTPUT, $config['file_path_output']);            // 新词发现的存储文件
        define(MEMORY_LIMIT, $config['memory_limit']);                    // 代码运行内存设置

        define(MAX_LENGTH, $config['max_length']);                // 词汇最大长度，大于六个字的不再考虑
        define(JBDICT_PATH, $config['jbdict_path']);              // 结巴老词典
        define(JBDICT_SWITCH, $config['jbdict_switch']);          // 是否使用结巴老词典剔除常用词
        define(TF_NUM, $config['tf_num']);                        // 单篇文章内出现次数大于TF_NUM的才被加入备选词汇
        define(LR_ENTROPY, $config['lr_entropy']);                // 左右邻字信息熵阈值，大于LR_ENTROPY时才满足条件
        define(DADSON_COUNT, $config['dadson_count']);            // 父串与子串筛选中，两者的词频之差
        define(DADSON_LENGTH, $config['dadson_length']);          // 父串与子串筛选中，两者的词长之差
        define(CEMENT_STEP_2, $config['cement_step_2']);          // 凝合度阶梯化，2字词的内部凝合度
        define(CEMENT_STEP_3, $config['cement_step_3']);          // 凝合度阶梯化，3字词的内部凝合度
        define(CEMENT_STEP_4, $config['cement_step_4']);          // 凝合度阶梯化，4字词的内部凝合度
        define(CEMENT_STEP_5, $config['cement_step_5']);          // 凝合度阶梯化，5字词的内部凝合度
        define(CEMENT_STEP_6, $config['cement_step_6']);          // 凝合度阶梯化，6字词的内部凝合度
    }
}
