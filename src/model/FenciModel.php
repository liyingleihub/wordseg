<?php
namespace Wordseg;

namespace Fenci;

class FenciModel {
    //分词相关函数，李英磊，shixi_yinglei@staff.sina.com.cn, 2017.06
    /**
    / 利用php实现最大正向匹配算法
    / 此方法用于识别出提问语句中的球队名
    / 这个函数是先选取最大的字数，从大往小进行匹配，还没写好，有不对的地方，后面可以改一下
    **/
    public function max_match($query,$dict,$max_len=4){
        $slen=mb_strlen($query,'UTF8');
        $query_new = $query;
        $res = array();
        $c_bg = 0;
        while($c_bg<$slen){
            $matched = false;
            $long_len = mb_strlen($query_new,'UTF8');
            $c_len = (($long_len - $c_bg) > $max_len) ? $max_len : ($long_len - $c_bg);
            $t_str = mb_substr($query, $c_bg,$c_len,'UTF8');
            for ($j = $c_len; $j>1; $j--) {
                $ttts = mb_substr($t_str, 0,$j,'UTF8');
                if (!empty($dict[$ttts])) {
                    $matched = true;
                    $res['name'][] = $dict[$ttts];
                    $query = str_replace($ttts,'',$query);
                    break;
                }
            }
            if(!$matched){
                $len = strlen($ttts);
                $c_bg += $len;
            } else {
                $tmp = mb_substr($query_new,0,1,'UTF8');
                $query_new = str_replace($tmp,'',$query_new);
                $c_bg++;
            }
        }
        $res['num'] = count($res['name']);
        foreach ($res['name'] as $value) {
            $query = str_replace($value,'',$query);
        }
        $res['rest'] = $query;
        var_dump($res);
        return $res;
    }
    /**
    * 这个方法是最大正向匹配算法，比如最大匹配字符是4，先选4个字，如果能匹配上，就将这四个字取出来
    * 如果没有匹配上，则取三个字，再匹配，直到与字典中的能进行匹配为止
    * 如果这四个字及其子集没有匹配到任何词典中的词语，则删除掉第一个字，从第二个字开始再取四个字，
    * 重复上方的流程。
    **/
    public function match ($query, $dict, $max_len = 4) {
        $slen = mb_strlen($query,'UTF8');   //提问语句的长度
        $res = array();                     //返回结果
        $tmp = array();                     //在匹配到词语后，用于临时存储
        $c_bg = 0;                          //标记已经处理过的字符的长度，判断是否超出字符串的长度
        $round_len = 0;                     //标记匹配到的词语的长度，在本例中为2或4
        while ($c_bg < $slen) {
            $match = false;
            $c_len = (($slen - $c_bg) > $max_len) ? $max_len : ($slen - $c_bg);
            for ($i = $c_len; $i > 1; $i--) {
                $t_str = mb_substr($query,0,$i,'UTF8');
                if (empty($dict[$t_str])) {
                    continue;
                } else {
                    $match = true;
                    $res['name'][] = $dict[$t_str];
                    $query = str_replace($t_str, '', $query);
                    $round_len = $i;
                    break;
                }    
            }
            if ($match && !empty($round_len)) {
                $c_bg += $round_len;
            } else {
                $c_bg++;
                $tmp = mb_substr($query,0,1,'UTF8');
                $rest[] = $tmp;
                $query = $this->str_replace_limit($tmp,'',$query,1);
            }
        }
        $res['num'] = count($res['name']);
        if (!empty($rest)) {
            $res['rest'] = implode($rest);
        } else {
            $res['rest'] = array();
        }
        if (!isset($res['name'])) $res['name'] = array();
        return $res;
    }


    /**ram  Mixed $search   查找目标值 
    * @param  Mixed $replace  替换值 
    * @param  Mixed $subject  执行替换的字符串／数组 
    * @param  Int   $limit    允许替换的次数，默认为-1，不限次数 
    * @return Mixed 
    **/  
    function str_replace_limit($search, $replace, $subject, $limit=-1){  
        if(is_array($search)){  
            foreach($search as $k=>$v){  
                $search[$k] = '`'. preg_quote($search[$k], '`'). '`';  
            }  
        }else{  
            $search = '`'. preg_quote($search, '`'). '`';  
        }  
        return preg_replace($search, $replace, $subject, $limit);  
    }
    
    /**
    * 对去除掉球队名称的部分进行中文分词，这一块是为后面的信息素提取做准备
    * 信息素提取的主要工作是将同一个意思的问题表示成一个固定的str，便于从数据库中取到值
    * 比如问题是：介绍一下国安？这个问题还有很多种问法，如：国安简介；国安是个什么球队；请对国安做个简单介绍等
    * 这些问题要表达的是同一个意思，为了进行归纳总结，要根据分词的结果，将相同意思的问题归为同一个str
    * 比如用GAJJ来表示，意思是“国安简介”的首字母。
    **/
    public function segment ($content) {
        if (empty($content)) {
            return array();
        }
        $fileType = mb_detect_encoding($content, array('UTF-8','GBK','LATIN1','BIG5')) ;
        if ($fileType != 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $fileType);
        }
        $content = urlencode($content);
        
        $url = "http://segment.sae.sina.com.cn:81/urlclient.php?encoding=UTF-8&word_tag=1&context=".$content;
        $s = curl_init();
        curl_setopt($s,CURLOPT_URL,$url);
        curl_setopt($s,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_0);
        curl_setopt($s,CURLOPT_TIMEOUT,5);
        curl_setopt($s,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($s,CURLINFO_HEADER_OUT, true);
        curl_setopt($s,CURLOPT_POST,true);
        $ret = curl_exec($s);
        if (!empty($ret)) {
            return json_decode($ret,ture);
        } else {
            return array();
        }
    }
    
    //获取char在str中的全部位置，返回数组
    public function getCharpos($str, $char){
        $j = 0;
        $arr = array();
        $count = mb_substr_count($str, $char);
        for($i = 0; $i < $count; $i++){
             $j = mb_strpos($str, $char, $j);
             $arr[] = $j;
             $j = $j+1;
        }
        return $arr;
    }   

    /**
    / 针对文本片段字数的不同，对凝合度进行阶梯化处理
    / input:array('梅西'=>97,'C罗'=>108......)
    / output:对数组中的每个词标定是否满足阶梯化的凝合度值。
    **/
    public function cement_step ($arr) {
        $step = array( 
            2 => CEMENT_STEP_2, 
            3 => CEMENT_STEP_3,
            4 => CEMENT_STEP_4,
            5 => CEMENT_STEP_5,
            6 => CEMENT_STEP_6,            
            );
        if (!is_array($arr)) return false;
        foreach ($arr as $k => $v) {
            $len = mb_strlen($k);
            if ($step[$len] > $v) {
                $step_arr[$k] = false;
            } else {
                $step_arr[$k] = true;
            }
        }
        return $step_arr;
    }    

    /**
    / 枚举文本片段的全部拆分方式，只针对4个字及以上的文本片段
    / 先判断备选的文本片段分成两个词汇的情况，看其组成部分是否均在已知词表内
    / 如果找到了，给其赋值false，如果没有，则赋值为true，
    / 按照算法应该再将其分为三个部分，看这三部分是否都已经有了，但过程过于复杂，因此不再考虑
    / 对小于4个字的文本片段不进行判断，直接给其赋值true
    / input: 结巴分词中的词典，备选文本片段的数组
    / output: 
    **/
    public function split_word($old_dict,$new_dict) {
        if (!is_array($old_dict) || !is_array($new_dict)) return false;
        foreach ($new_dict as $k => $v) {
            if (mb_strlen($k) >= 4) {
                $split_arr[$k] = true;
                for ($i = 0; $i < count($v); $i++) {
                    if (in_array($v[$i]['fir'], $old_dict) || array_key_exists($v[$i]['fir'], $new_dict)) {
                        if (in_array($v[$i]['sec'], $old_dict) || array_key_exists($v[$i]['sec'], $new_dict)) {
                            $split_arr[$k] = false;
                        }
                    }
                }
            } else {
                $split_arr[$k] = true;
            }
        }
        return $split_arr; 
    }

    /**
    / 相邻词比较筛选 
    / 词长（设词长为N）相等，并且连续的N-1个字或字符相同，也即只有第一个词条的首（尾）字或字符与第二个词条
    / 的尾（首）字或字符不同，其余的字或者字符要全部相同，满足这个条件的两词条互称为相邻词。如“经贸”和“贸合”，
    / 互为相邻词。
    / 如果两个相邻词的词频相同，则两词均被过滤，若其中一个词条的词频高于另一个，则保留词频较高的词条。
    **/
    public function near_word ($alter_arr) {
        if (!is_array($alter_arr) || empty($alter_arr)) return array();
        $copy = $alter_arr;
        foreach ($alter_arr as $key => $value) {
            $rest = mb_substr($value['word'], 1);
            foreach ($copy as $k => $v) {
                if ($value['word'] == $k) {
                    continue;
                } else {
                    $temp = mb_substr($v['word'], 0, -1);
                    if ($rest == $temp) {
                        if ($value['count'] == $v['count']) {
                            $nearword_arr[$key] = false;
                            $nearword_arr[$k] = false;
                        } elseif ($value['count'] > $v['count']) {
                            $nearword_arr[$k] = false;
                        } elseif ($value['count'] < $v['count']) {
                            $nearword_arr[$key] = false;
                        }
                    } else {
                        continue;
                    }
                }
            }
        }
        if (!empty($nearword_arr)) {
            foreach ($nearword_arr as $key => $value) {
                if (array_key_exists($key, $alter_arr)) {
                    unset($alter_arr[$key]);
                }
            } 
        }
        return $alter_arr;
    }

    /**
    / 父串与子串的比较
    / 父串与子串的长度之差为p，父串与子串的词频之差为q
    / 对于子串而言:
    / 当与其父串的词频之差大于q，并且词长之差小于等于p，则删除父串
    / 如果子串与其父串的词频之差小于等于q，则删除子串
    / 对于父串而言:
    / 当其子串的词频减去父串的词频之差小于或等于q时，则将其子串过滤掉
    / 否则，当其子串的词频之差大于q，长度之差小于等于p，则删除父串。
    **/
    public function DadSon_word ($alter_arr) {
        $copy = $alter_arr;
        foreach ($alter_arr as $key => $value) {
            foreach ($copy as $k => $v) {
                if ($v['word'] == $value['word']) {
                    continue;
                } else {
                    if (mb_strpos($v['word'],$value['word'])) { // $v是父，$value是子
                        if ($value['count'] - $v['count'] <= DADSON_COUNT) {
                            $DS_arr[$key] = false;
                        }
                    } elseif (mb_strpos($value['word'], $v['word'])) { // $value是父，$v是子
                        $dad = mb_strlen($value['word']);
                        $son = mb_strlen($v['word']);
                        if (($dad - $son >= DADSON_LENGTH) && ($v['count'] - $value['count'] > DADSON_COUNT)) {
                            $DS_arr[$key] = false;
                        }
                    }
                }
            }
        }
        if (is_array($DS_arr) && !empty($DS_arr)) {
            foreach ($DS_arr as $key => $value) {
                if (array_key_exists($key, $alter_arr)) unset($alter_arr[$key]);
            }
        }
        return $alter_arr;
    } 

    /**
    文本片段出现的文章数小于阈值时，删除此文本片段
    **/
    public function article_count($alter_arr, $word_article_count) {
        foreach ($word_article_count as $k => $v) {
            if ($v < 3) {
                $temp[$k] = 1;
            }        
        }
        if (empty($temp)) {
            return $alter_arr;
        }

        foreach ($temp as $k => $v) {
            if (array_key_exists($k, $alter_arr)) unset($alter_arr[$k]);        
        }
        return $alter_arr;
    }


    public function _error ($num) {
        switch ($num) {
            case 0:
                return "key的值不符合标准";
                break;
            case 1:
                return "抱歉，此模型尚未建立";
                break;
        }    

    }
}  
