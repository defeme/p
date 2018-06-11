<?php
/**
 * 评论回复邮件提醒插件,SendCloud版
 *
 * @package CommentSendCloud
 * @author DEFE
 * @version beta
 * @link http://defe.me
 */
class CommentSendCloud implements Typecho_Plugin_Interface {
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {  
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('CommentSendCloud', 'toSendCloud');
        return _t('请对插件进行正确设置，以使插件顺利工作！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() { }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {        
        // API_USER
        $api_user = new Typecho_Widget_Helper_Form_Element_Text('api_user', NULL, NULL, _t('SendCloud发信API USER'), _t('使用SendCloud的API_USER'));
        $api_user->input->setAttribute('class', 'mini');
        $form->addInput($api_user);
        // API_KEY
        $api_key = new Typecho_Widget_Helper_Form_Element_Password('api_key', NULL, NULL, _t('SendCloud发信API KEY'), _t('SendCloud的API_KEY'));
        $form->addInput($api_key);
        // 发件人信箱
        $send_from = new Typecho_Widget_Helper_Form_Element_Text('send_from', NULL, NULL, _t('发件人邮件地址'), _t('邮箱域名尽量与SendCloud中配置的发信域名一致'));       
        $form->addInput($send_from->addRule('email', _t('请填写正确的邮箱！')));
        //收件邮箱
        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, NULL,
                _t('接收邮箱'),_t('接收邮件用的信箱,如为空则使用文章作者个人设置中的邮箱！'));
        $form->addInput($mail->addRule('email', _t('请填写正确的邮箱！')));
        // 模板名称
        $template = new Typecho_Widget_Helper_Form_Element_Text('template', NULL, NULL, _t( '模板名称'), _t('请填入在SendCloud配置的博主模板名称'));
        $template->input->setAttribute('class', 'mini');
        $form->addInput($template); 
        
        $templateg = new Typecho_Widget_Helper_Form_Element_Text('templateg', NULL, NULL, _t( '模板名称'), _t('发送给评论者的模板'));
        $templateg->input->setAttribute('class', 'mini');
        $form->addInput($templateg); 
        
        $titleForOwner = new Typecho_Widget_Helper_Form_Element_Text('titleForOwner',null,"[%site%]:《%title%》有新的评论",
                _t('博主接收邮件标题'));
        $form->addInput($titleForOwner);

        $titleForGuest = new Typecho_Widget_Helper_Form_Element_Text('titleForGuest',null,"[%site%]:您在《%title%》的评论有了回复",
                _t('访客接收邮件标题'));
        $form->addInput($titleForGuest);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {

    }

    /**
     * 组合邮件内容
     *
     * @access public
     * @param $post 调用参数
     * @return void
     */
    public static function toSendCloud($post) {  
        //$smtp=array();        
        $options = Typecho_Widget::widget('Widget_Options');
        $set = Helper::options()->plugin('CommentSendCloud');
        $site = $options->title;    
        $timezone = $options->timezone;
        $cid=$post->cid;
        $coid=$post->coid;
        $created=$post->created;
        $author=$post->author;
        $authorId=$post->authorId;
        $ownerId=$post->ownerId;
        $mail=$post->mail;
        $ip=$post->ip;
        $title = $post->title;
        $text=$post->text;
        $permalink=$post->permalink;
        $status=$post->status;
        $parent=$post->parent;     
        $time = date("Y-m-d H:i:s",$created+$timezone);
        
        $subject = '《' . $title . '》 有新回复!';
        $xsmtpapi = json_encode(
            array(
                'to' => array($set->mail),
                'sub' => array(
                    '%site%' => array(trim($site)),
                    '%title%' => array(trim($title)),
                    '%author%' => array(trim($author)),
                    '%mail%' => array(trim($mail)),
                    '%time%' => array(trim($time)),
                    '%permalink%' => array(trim($permalink)),
                    '%text%' => array(trim($text))
                )
            )           
        );       
        // 请求参数
        $param = array(
            'apiUser' => $set->api_user,
            'apiKey' => $set->api_key,
            'from' => $set->send_from,
            'fromName' => $site,
            'subject' =>$subject,
            'xsmtpapi' => $xsmtpapi,
            'templateInvokeName' => $set->template
        );
        self::sendMail($param);     
        
        if(0!=$parent && 'approved'==$status){
            $db = Typecho_Db::get();
            $original = $db->fetchRow($db->select('author', 'mail', 'text')
                    ->from('table.comments')
                    ->where('coid = ?', $parent));
            $subject = '您在[' . $site . '] 的评论有了回复!';  
            $xsmtpapi = json_encode(
                array(
                    'to' => array($original['mail']),
                    'sub' => array(
                        '%site%' => array(trim($site)),
                        '%title%' => array(trim($title)),                        
                        '%original_author%' => array(trim($original['author'])),
                        '%original_text%' => array(trim($original['text'])),
                        '%author%' => array(trim($author)),
                        '%time%' => array(trim($time)),
                        '%permalink%' => array(trim($permalink)),
                        '%text%' => array(trim($text))
                    )
                )           
            );       
            // 请求参数
            $param1 = array(
                'apiUser' => $set->api_user,
                'apiKey' => $set->api_key,
                'from' => $set->send_from,
                'fromName' => $site,
                'subject' =>$subject,
                'xsmtpapi' => $xsmtpapi,
                'templateInvokeName' => $set->templateg
            );
            self::sendMail($param1);
        }
    }    
     /**
     * 生成邮件内容并发送
     *
     * @access public
     * @param string $param sendcloud邮件参数    
     * @return void   
     * 
     *
     */
    public function sendMail($param){       

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_URL, 'http://api.sendcloud.net/apiv2/mail/sendtemplate');
      
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        // 执行请求
        $result = curl_exec($ch);
        // 获取错误代码
        $errno = curl_errno($ch);
        // 获取错误信息
        $error = curl_error($ch);
        
        if($result === false) {            
            file_put_contents('.'.__TYPECHO_PLUGIN_DIR__.'/CommentSendCloud_log.txt', $result.$error);
        }
        // 关闭curl
        curl_close($ch);        
        
    }    
}
