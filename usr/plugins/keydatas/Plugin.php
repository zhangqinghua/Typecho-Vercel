<?php
/**
 * 简数采集器(keydatas.com)是一个通用、简单、智能、在线的网页数据采集和发布平台，功能强大，操作简单。支持定时采集、支持自动发布，定时发布；图片下载支持存储到阿里云OSS、七牛对象存储、腾讯云对象存储。
 * @package 简数采集器
 * @author keydatas
 * @version 1.0.2
 * @link http://www.keydatas.com/
 *
 */
class keydatas_Plugin implements Typecho_Plugin_Interface {

    public static function activate() {
			Typecho_Plugin::factory('index.php')->begin = array('keydatas_Plugin', 'post');
    }
   
    public static function deactivate() {
			Helper::removeAction("__kds_flag");
    }  
   
     public static function post() {
        $uri = $_SERVER['REQUEST_URI'];
				if(strpos($uri,"?__kds_flag=") !== false){
            require_once ( __DIR__."/Kds_typecho.php");
            $kdsTypecho = @new Kds_typecho(Typecho_Request::getInstance(),Typecho_Response::getInstance());
            $kdsTypecho->action();
        }
    }   

    public static function config(Typecho_Widget_Helper_Form $form) {
        $siteUrl = Helper::options()->siteUrl;
        $publishUrlLabel = new Typecho_Widget_Helper_Layout("label", array(
            'class' => 'typecho-label',
            'style' => 'margin-top:20px;'
        ));
        $publishUrlLabel->html('网站发布地址为：');
        $publishUrl = new Typecho_Widget_Helper_Layout("input",
            array(
                "disabled" => true,
                "readOnly" => true,
                "value" => $siteUrl,
                'type' => 'text',
                'class' => 'text',
                'style' => "width:80%;height:80%;"
            )
        );

        $rootDiv = new Typecho_Widget_Helper_Layout();
        $urldiv = new Typecho_Widget_Helper_Layout();
        $urldiv->setAttribute('class', 'typecho-option');
        $publishUrlLabel->appendTo($urldiv);
        $publishUrl->appendTo($urldiv);
        $form->addItem($urldiv);

        $kds_password = new Typecho_Widget_Helper_Form_Element_Text('kds_password', null, 'keydatas.com', _t('发布密码：'), "（请注意修改并保管好,简数控制台发布需要用到）");

         // 文章标题去重选项
        $duplicateOptions = array(
		   		'no_keydatas_title_unique' => _t('根据标题去重，如存在相同标题，则不插入')
		   	);
				$duplicateOptionsValue = array('no_keydatas_title_unique');
	    	$keydatas_title_unique = new Typecho_Widget_Helper_Form_Element_Checkbox('keydatas_title_unique', $duplicateOptions,
            $duplicateOptionsValue, _t('标题去重:'));

        $form->addInput($kds_password);
				$form->addInput($keydatas_title_unique->multiMode());
        $helperLayout = new Typecho_Widget_Helper_Layout();
        $itemOne_p = new Typecho_Widget_Helper_Layout('span', array(
            'style' => "floal:left;display:block;clear:left;margin-top:10px;"
        ));
        $itemOne_p->html("简介和使用教程：");
        $itemOne_ul = new Typecho_Widget_Helper_Layout('ul');
        $itemOne_ul->setAttribute('class', 'typecho-option');
        $itemOne_li0 = new Typecho_Widget_Helper_Layout('li');
        $itemOne_li0->html('简数采集是一个简单、智能、在线的网页数据采集器，功能强大，操作简单。采集和发布数据请到 <a href="http://dash.keydatas.com" target="_blank">简数控制台</a>');

        $itemOne_li1 = new Typecho_Widget_Helper_Layout('li');
        $descText = '1、简数官网<a href="http://www.keydatas.com" target="_blank">www.keydatas.com</a> &nbsp;&nbsp;&nbsp;&nbsp;QQ交流群：542942789</br>'.
		        '2、采集和发布教程：<a href="http://doc.keydatas.com/getting-started.html">数据采集快速入门</a>'.
		        '</br>'.
				'3.采集不需安装任何客户端，<strong>在线可视化点选</strong></br>'.
				'4.集成智能提取引擎,自动识别数据和规则，包括：翻页、标题，作者，发布日期，内容等</br>'.
				'5.图片下载支持存储到：阿里云OSS、七牛云、腾讯云;</br>'.
				'6.支持定时采集、支持自动发布</br>'.
				'7.支持按关键词采集。</br>';	
        $itemOne_li1->setAttribute('class', 'description')->html($descText);
        $itemOne_ul->addItem($itemOne_li0);
        $itemOne_ul->addItem($itemOne_li1);
        $helperLayout->addItem($itemOne_p);
        $helperLayout->addItem($itemOne_ul);

        $form->addItem($helperLayout);
        $form->appendTo($rootDiv);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {
    }
 
}
