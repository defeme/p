<?php
/**
 * 博客日志列表插件，包含随机列表、热门列表
 *
 * @package ArticleList
 * @author DEFE
 * @version 1.0.0 beta
 * @link http://defe.me
 */

class ArticleList implements Typecho_Plugin_Interface
{
    /**
     * 
     */
    public static function  activate() {}
    public static function deactivate(){}
    public static function  config(Typecho_Widget_Helper_Form $form) {
        $numset = new Typecho_Widget_Helper_Form_Element_Radio('numset',
        array('a'=>'与Blog设置中的"文章列表数目"相同','b'=>'单独设定文章列表数目'),
        'a','文章数目选项');
        $form->addInput($numset->multiMode());

        $rndnum = new Typecho_Widget_Helper_Form_Element_Text('rndnum', NULL, '10', _t('随机文章列表数目'));
        $rndnum->input->setAttribute('class', 'mini');
        $form->addInput($rndnum->addRule('required', _t('必须填写文章列表数目'))
        ->addRule('isInteger', _t('文章数目必须是纯数字')));

        $rndtime = new Typecho_Widget_Helper_Form_Element_Text('rndtime', NULL, '60', _t('随机列表缓存时间'),_t('缓存时间单位为秒，设为0则禁用缓存'));
        $rndtime->input->setAttribute('class', 'mini');
        $form->addInput($rndtime->addRule('isInteger', _t('缓存时间必须是整数')));

        $rndlen = new Typecho_Widget_Helper_Form_Element_Text('rndlen', NULL, '0', _t('随机标题长度'),_t('这里设置截取的长度值，标题过长可能会影响版面，默认为0则不截取。'));
        $rndlen->input->setAttribute('class', 'mini');
        $form->addInput($rndlen->addRule('isInteger', _t('标题长度必须是整数')));

        $listnum = new Typecho_Widget_Helper_Form_Element_Text('hotnum', NULL, '10', _t('热门文章列表数目'));
        $listnum->input->setAttribute('class', 'mini');
        $form->addInput($listnum->addRule('required', _t('必须填写文章列表数目'))
        ->addRule('isInteger', _t('文章数目必须是纯数字')));

        $title_len = new Typecho_Widget_Helper_Form_Element_Text('hotlen', NULL, '0', _t('热门列表标题长度'),_t('这里设置截取的长度值，标题过长可能会影响版面，默认为0则不截取。'));
        $title_len->input->setAttribute('class', 'mini');
        $form->addInput($title_len->addRule('isInteger', _t('标题长度必须是整数')));
    }
    public static function  personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 输出随机列表
     * 
     * @param string $format 输出格式
     */
    public static function random($format='<li><a href="{permalink}">{title}</a></li>'){
        $result=self::deal('random', true);
        foreach($result->rd as $rd)
        {
            echo str_replace(array('{permalink}','{title}'),array($rd->link,$rd->title),$format);
        }
    }

    /**
     *输出热门列表
     *
     * @param string $format
     */
    public static function hot($format='<li><a href="{permalink}">[{commentsNum}]{title}</a></li>'){
        $option = Typecho_Widget::widget('Widget_Options')->plugin('ArticleList');
        if ($option->numset == 'a'){
            $num = Typecho_Widget::widget('Widget_Options')->postsListSize;
        }else{
            $num = $option->hotnum;
        }
        $db = Typecho_Db::get();     
        $rst = $db->fetchAll($db->select('cid','title','slug','type','commentsNum')->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->order('table.contents.commentsNum',Typecho_Db::SORT_DESC)
            ->limit($num));
        foreach($rst as $result){
            $value = Typecho_Widget::widget('Widget_Abstract_Contents')->push($result);
            $title = $option->hotlen ? self::cutstr($value['title'],$option->hotlen) : $value['title'];
            echo str_replace(array('{permalink}','{title}','{commentsNum}'),array($value['permalink'],$title,$value['commentsNum']),$format);
        }
    }

    /**
     * 处理函数[留待进一步处理]
     *
     * @param string $type
     * @param boolean $return
     * @return SimpleXMLElement
     */
    private static function deal($type='hot',$return=false){
        $option=Typecho_Widget::widget('Widget_Options')->plugin('ArticleList');
        /**缓存文件*/
        $file="./usr/ArticleList.xml";
        /**获取日志列表数目*/
        if ($option->numset == 'a'){
            $randomNum= Typecho_Widget::widget('Widget_Options')->postsListSize;
        }else{
            $randomNum= $option->rndnum;         
        }
       
        /**处理随机列表*/
        if($type=='random'){
            $xml1=@simplexml_load_file($file);
            /**可以直接返回xml对象*/
            if($xml1 && $return && $option->rndtime!=0 && time()-$xml1->attributes()<$option->rndtime){
                return $xml1;
            }else{ //读取数据库，判断是否输出或是更新缓存
                 /**获取数据库连接*/
                $db=Typecho_Db::get();
                 /**获取日志总数*/
                $rs = $db->fetchRow($db->select(array('COUNT(cid)' => 'total'))->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', 'post'));
                $total=$rs['total'];

                /**设置随机数组*/
                srand((float) microtime() * 10000000);
                $ary=range(0,$total-1);
                if($randomNum>$total) $randomNum=$total;
                $rand = array_rand($ary, $randomNum);

                $list = '<lists/>';
                $xml = new SimpleXMLElement($list);
                $xml->addAttribute('time', time());

                foreach($rand as $index){
                    $result = $db->fetchRow($db->select('cid','title','slug','type')->from('table.contents')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.type = ?', 'post')
                    ->offset($index)
                    ->limit(1));

                    $value = Typecho_Widget::widget('Widget_Abstract_Contents')->push($result);
                    $title = $option->rndlen ? self::cutstr($value['title'], $option->rndlen ) : $value['title'];
                    //echo str_replace(array('{permalink}','{title}'),array($value['permalink'],$title),$format);
                    $rd=$xml->addChild('rd');
                    $rd->addChild('title',$title);
                    $rd->addChild('link',$value['permalink']);
                }
                if($option->rndtime!=0)file_put_contents($file, $xml->asXML());
                if($return){
                    return $xml;
                }
            }
        }

    }

    /**
     *字符串截断
     *
     * @param string $string
     * @param interger $length
     * @return string
     */
    private static function cutstr($string, $length) {
        $wordscut='';
        $j=0;
        preg_match_all("/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/", $string, $info);
        for($i=0; $i<count($info[0]); $i++) {
                $wordscut .= $info[0][$i];
                $j = ord($info[0][$i]) > 127 ? $j + 2 : $j + 1;
                if ($j > $length - 3) {
                        return $wordscut." ...";
                }
        }
        return join('', $info[0]);
    }
}
?>
