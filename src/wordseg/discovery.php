<?php 
/**
/ 2017-11-24 @yinglei
/ 新词发现程序，用于补充结巴分词的基础词库，进而形成体育特有的分词系统
/ 结巴分词的分词方式为，先以词库进行匹配，再对剩余的部分进行HMM运算，得出最符合概率的分词结果
**/
namespace Wordseg;

namespace SRC\WORDSEG\DISCOVERY;

class discovery 
{
    public function main() {
        ini_set('memory_limit', MEMORY_LIMIT);
        $max_length = MAX_LENGTH; //新词的最大长度
        $zhe_sum = 0;   //“这”字出现的总次数
        $fenciModel = new \Fenci\FenciModel();
        $content_total = "";
        $alter_arr = array();

        //获取结巴分词的基础词库，用于对比新发现的词汇
        $jbdict_path = JBDICT_PATH;
        if (file_exists($jbdict_path)) {
            $jbdict = file($jbdict_path);
            $count = count($jbdict);
            for ($i = 0; $i<$count; $i++){                
                $temp = explode(" ",$jbdict[$i]);       
                $jbdict_arr[$i] = $temp[0];
            }
        } else {
            echo "未找到结巴分词的老词典";
            exit;
        }

        //新词发现主程序
        $file_path = FILE_PATH_INPUT;
        if (file_exists($file_path)) {
            $file_json = file($file_path);
            $article_num = count($file_json);
            for ($i = 0; $i < $article_num; $i++) {
                $alter_word = array();
                //计算“这”字出现的次数
                $zhe_num = mb_substr_count($content,'这','UTF8');
                $zhe_sum += $zhe_num;

                //将一些不必要的空格，换行符等删掉
                $content = json_decode($file_json[$i],true);
                $content = mb_ereg_replace('^(([ \r\n\t])*(　)*)*', '', $content);  
                $content = mb_ereg_replace('(([ \r\n\t])*(　)*)*$', '', $content); 
                $content = mb_ereg_replace('(　)*', '', $content);//全角空格
                $content = mb_ereg_replace(PHP_EOL, '', $content);//分段符

                $content_total .= " " . $content;
                //词频大于等于5的为备选词汇
                $slen = mb_strlen($content,'UTF8');
                $start = 0;      //每轮匹配的起始位置
                while ($start < $slen) {
                    for ($j = $max_length; $j >= 2; $j--) {
                        $flag = false;
                        $word = mb_substr($content, $start, $j);
                        if (array_key_exists($word, $alter_word)) {
                            continue;
                        }

                        $num = mb_substr_count($content, $word, 'UTF8');
                        if ($num < TF_NUM) {
                            continue;
                        } else {
                            $left = array();
                            $right = array();
                            $flag = true;
                            $start += $j;
                            $position = $fenciModel->getcharpos($content,$word);
                            foreach ($position as $pos) {
                                if ($pos !== 0) {
                                    $left[] = mb_substr($content, $pos - 1, 1);
                                }
                                if (($pos + mb_strlen($word)) !== $slen) {
                                    $temp = $pos + mb_strlen($word); 
                                    $right[] = mb_substr($content, $temp, 1);
                                }
                            }
                            $left = array_count_values($left);
                            $right = array_count_values($right);
                            $alter_word[$word] = array($word,$num,$left,$right);
                            break;
                        }
                    }
                    if ($flag == false) {
                        $start += 1;  
                    }
                }

                //删除带有标点和结巴词库中已经包含的词汇
                foreach ($alter_word as $k => $v){
                    $word = $v[0];
                    $word = urlencode($word);
                    if (preg_match("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/",$word)) {
                        unset($alter_word[$k]);
                    }
                    $word = urldecode($word);
                    if (in_array($word,$jbdict_arr)) {
                        unset($alter_word[$k]);
                    }
                    if (strstr($word, '《') || strstr($word,'》') || strstr($word,'的') || strstr($word,'是') || strstr($word,'了') || strstr($word,'呢') || strstr($word,'啊') || preg_match("/[0-9]+/",$word)) {
                        unset($alter_word[$k]);
                    }
                    if (strstr($word, ' ') || strstr($word,'【') || strstr($word,'）') || strstr($word, '（')) {
                        unset($alter_word[$k]);
                    } 
                    if (!preg_match("/([\x{4e00}-\x{9fa5}])/u", $word) || preg_match_all("/([\x{4e00}-\x{9fa5}])/u", $word) == 1) {
                        unset($alter_word[$k]);
                    }
                    if (mb_strlen($word) >= 5 && strstr($word,'和')) {
                        unset($alter_word[$k]);
                    }
                }

                //计算备选文本片段的左右邻字信息熵，按照算法应该以所有文章为基础进行计算，但根据实际情况，考虑
                //到每一篇文章仅围绕一个主题展开，因此在单篇文章内计算备选文本片段的左右邻字信息熵同样具有科学性
                foreach ($alter_word as $k => $v) {
                    $left = $v[2];                //注意：这里要对$left重新赋值，因为上面77行已经出现过left，如果不赋值将会出错
                    $sum_left = array_sum($left);
                    $rate_left = array();
                    foreach ($left as $key => $value) {
                        $rate_left[$key] = round($value/$sum_left, 3); 
                    }
                    $left_res = 0;
                    foreach ($rate_left as $key => $value) {
                        $left_res += (0 - $value*log($value)); 
                    }
                    
                    $right = $v[3];
                    $sum_right = array_sum($right);
                    $rate_right = array();
                    foreach ($right as $key => $value) {
                        $rate_right[$key] = round($value/$sum_right, 3); 
                    }
                    $right_res = 0;
                    foreach ($rate_right as $key => $value) {
                        $right_res += (0 - $value*log($value)); 
                    }

                    $lr_res = $left_res >= $right_res ? $right_res : $left_res;
                    if ($lr_res < LR_ENTROPY) {
                        unset($alter_word[$k]);
                    }
                }

                foreach ($alter_word as $k => $v) {
                    @$alter_arr[$k]['word'] = $v[0];
                    @$alter_arr[$k]['count'] += $v[1];
                    $word_article_count[$k] += 1;
                } 
                echo "对第".$i."篇文章进行分析";
                echo "\n";
                /**
                / 到这里为止，第一步根据词频筛选文本字段，满足以下三个条件：
                /（1）在单篇文章中“文本片段”的词频大于5，
                /（2）“文本片段”不含标点符号，
                /（3）结巴老词库中没有该“文本片段”
                / 就完成了，留下的“文本片段”为需要进行凝合度和左右邻字信息熵计算的备选词汇。
                ///
                / 第二步，根据左右邻字对文本片段进行筛选也已经完成了，判断阈值为左右邻字的
                / 最小信息熵不能小于1.5,
                / 注意：这一步同样是在单篇文章内对文本片段进行计算，原因在上面已进行说明。
                **/
                

            }
        } else {
            echo "文件不存在，无法进行新词发现，请核对！";
            exit;
        }

        if (empty($alter_arr) || !is_array($alter_arr)) {
            echo "数据中未发现新词，退出程序";
            exit;
        }

        $time_show[] = array('开始' => date('h:i:s'));

        /**
        相邻词比较筛选程序
        何为相邻词？
        比如“经贸”和“贸合”，去掉“经贸”的第一个字，去掉“贸合”的第二个字，剩余的部分如果相同，就称为相邻词
        相邻词通常是长词汇的一部分，这些是应该被删除掉的。具体逻辑可见FenciModel->near_word()方法。
        **/
        if (is_array($alter_arr) && !empty($alter_arr)) { 
            $alter_arr = $fenciModel->near_word($alter_arr);
        }
        $time_show[] = array('相邻词' => date('h:i:s'));

        /**
        父串与子串对比，对于符合条件的备选词汇数组，在数组内寻找子串，如果有子串，
        并且子串与其父串的词频之差小于等于q，则删除子串
        **/
        if (is_array($alter_arr) && !empty($alter_arr)) {
            $alter_arr = $fenciModel->DadSon_word($alter_arr);
        }
        $time_show[] = array('父子串' => date('h:i:s'));

        // 文本片段的凝合度计算
        $total = mb_strlen($content_total);
        foreach ($alter_arr as $k => $v) {
            $word = $v['word'];
            $coagu = array();
            switch (mb_strlen($word)) { 
            case 2:
                $coagu[0]['fir'] = mb_substr($word, 0, 1);
                $coagu[0]['sec'] = mb_substr($word, 1, 1);
                break;
            case 3:
                $coagu[0]['fir'] = mb_substr($word, 0, 1);
                $coagu[0]['sec'] = mb_substr($word, 1, 2);
                $coagu[1]['fir'] = mb_substr($word, 0, 2);
                $coagu[1]['sec'] = mb_substr($word, 2, 1);
                break;
            case 4:
                $coagu[0]['fir'] = mb_substr($word, 0, 1);
                $coagu[0]['sec'] = mb_substr($word, 1, 3);
                $coagu[1]['fir'] = mb_substr($word, 0, 2);
                $coagu[1]['sec'] = mb_substr($word, 2, 2);
                $coagu[2]['fir'] = mb_substr($word, 0, 3);
                $coagu[2]['sec'] = mb_substr($word, 3, 1);
                break;
            case 5:
                $coagu[0]['fir'] = mb_substr($word, 0, 1);
                $coagu[0]['sec'] = mb_substr($word, 1, 4);
                $coagu[1]['fir'] = mb_substr($word, 0, 2);
                $coagu[1]['sec'] = mb_substr($word, 2, 3);
                $coagu[2]['fir'] = mb_substr($word, 0, 3);
                $coagu[2]['sec'] = mb_substr($word, 3, 2);
                $coagu[3]['fir'] = mb_substr($word, 0, 4);
                $coagu[3]['sec'] = mb_substr($word, 4, 1);
                break;
            case 6:
                $coagu[0]['fir'] = mb_substr($word, 0, 1);
                $coagu[0]['sec'] = mb_substr($word, 1, 5);
                $coagu[1]['fir'] = mb_substr($word, 0, 2);
                $coagu[1]['sec'] = mb_substr($word, 2, 4);
                $coagu[2]['fir'] = mb_substr($word, 0, 3);
                $coagu[2]['sec'] = mb_substr($word, 3, 3);
                $coagu[3]['fir'] = mb_substr($word, 0, 4);
                $coagu[3]['sec'] = mb_substr($word, 4, 2);
                $coagu[4]['fir'] = mb_substr($word, 0, 5);
                $coagu[4]['sec'] = mb_substr($word, 5, 1);
                break;
                
            }
            $coagu_num = count($coagu);
            for ($i = 0; $i < $coagu_num; $i++) {
                $fir_num = mb_substr_count($content_total, $coagu[$i]['fir'], 'UTF8');
                $sec_num = mb_substr_count($content_total, $coagu[$i]['sec'], 'UTF8');
                $coagu_value = $v['count'] * $total / ($fir_num * $sec_num);
                $coagu_value = round($coagu_value, 2);
                $cement[$word][$i] = $coagu_value;
            }
        //$coagu_bi[$word] = $coagu;
        }

        //凝合度阶梯化，判断其是否满足成词条件
        foreach ($cement as $k => $v) {
            $cement[$k] = min($v);
        }
        $step_arr = $fenciModel->cement_step($cement);
        foreach ($step_arr as $k => $v) {
            if ($v == false) {
                unset($alter_arr[$k]);
            }
        }
        $time_show[] = array('凝合度梯度化' => date('h:i:s'));

        foreach ($alter_arr as $v) {
            $temp = '';
            $temp = implode(' ',$v);
            $in[] = $temp;
        }
        $b = implode(PHP_EOL,$in);
        file_put_contents(FILE_PATH_OUTPUT, $b);
        $time_show[] = array('写入文件' => date('h:i:s'));

        var_dump($time_show);
        exit;

    }
}


/***


//文本片段出现的文章数小于某个阈值时，删除此文本片段
if (is_array($alter_arr) && !empty($alter_arr)) {
    if (is_array($word_article_count) && !empty($word_article_count)) {
        $alter_arr = $fenciModel->article_count($alter_arr, $word_article_count);
    }
}
//var_dump($cement);
var_dump($step_arr);
exit;
var_dump($alter_arr);
exit;

//拆分文本片段，判断其是否能成词。经过试验，发现有些好词可以拆分成已有的词汇，这给此条件的
//准确性带来了质疑。我需要再考虑是否将其作为一条判断依据
$split_arr = $fenciModel->split_word($jbdict_arr,$coagu_bi);
var_dump($split_arr);
exit;

foreach ($alter_arr as $k => $v) {
    foreach ($v['left'] as $key => $value) {
        $tem = count($value);
        $alter_ltemp = array();
        for ($i = 0; $i < $tem; $i++) {
            $alter_ltemp = array_merge_recursive($alter_ltemp,$value[$i]);
        }
    }
    foreach ($v['right'] as $key => $value) {
        $tem = count($value);
        $alter_rtemp = array();
        for ($i = 0; $i < $tem; $i++) {
            $alter_rtemp = array_merge_recursive($alter_rtemp,$value[$i]);
        }
    }
}
echo '$zhe_sum =>';
var_dump($zhe_sum);
var_dump($alter_arr);
exit;       

$a = array( array('C罗',3,n),
            array('梅西',3,n),
            array('姆巴佩',3,n),
          );
foreach ($a as $v) {
    $temp = '';
    $temp = implode(' ',$v);
    $in[] = $temp;
}
$b = implode(PHP_EOL,$in);
file_put_contents("newdict.txt",$b);

if (in_array('梅西',$jbdict)) {
    echo 11;
exit;
} else {
    echo 22;
    exit;
}

**/
