<?php

class Kds_typecho extends Typecho_Widget implements Widget_Interface_Do
{

    public function action()
    {
        include_once "Kds_util.php";

        $this->db = Typecho_Db::get();
        $wpdb = $this->db;
				$reqData = $this->keydatasMergeRequest();
        if ($_GET["__kds_flag"] == "post") {
			
            $this->verifyPassword($reqData['kds_password']);
            $title = $reqData["title"];
            $text = $reqData["text"];
			
            if (empty($title)||empty($text)) {
                keydatas_failRsp('1405', "title and content are empty", "文章标题与内容都不能为空");
            }
           
            $indexUrl = Helper::options()->siteUrl;
            
				//文章标题重复处理
				$keydatas_title_unique = Typecho_Widget::widget('Widget_Options')->plugin('keydatas')->keydatas_title_unique;
            if (!is_null($keydatas_title_unique) && !empty($title)) {
                $post = $wpdb->fetchRow($wpdb->select()->from('table.contents')->where('title = ?', $title));
                if ($post) {
                    $postId = $post['cid'];
                    $relationships = $wpdb->fetchAll($wpdb->select()->from('table.relationships')->where('cid = ?', $postId));
                    $re = array_pop($relationships);
                    $cate = $wpdb->fetchRow($wpdb->select()->from('table.metas')->where('mid = ?', $re['mid']));
                    $lastCategory = $cate['name'];
                    $slug = $post['slug'];
                    $time = $post['created'];
                    $docUrl = $this->genDocUrl($indexUrl, $postId, $slug, $lastCategory, $time);
				
					$_REQ = $this->keydatasMergeRequest();
					$loadImages = $this->downloadImages($_REQ);	
                    keydatas_successRsp(array("url" => $docUrl . "?p={$postId}"."#相同标题文章已存在"));

                }
            }
            
			//发布时间
            $created = Helper::options()->gmtTime;
            if (!empty($reqData["created"])) {
                $created = $reqData["created"];
                if (preg_match('/\d{10,13}/', $created)) {
                    $created = intval($created);
                } else {
                    $created = intval(strtotime($created));
                }
            }
            //
            $authorId = 1;
            $author = htmlspecialchars_decode($reqData["author"]);
			if (!empty($author)) {
				$existUid = $this->isUserExist($author);
				if (!empty($existUid)) {
					$authorId=$existUid;
				} else{
					$userId = $this->createUser($author);
					if (!empty($userId)){
						$authorId = $userId;
					}
				}
			}

            //插入到文章表相应字段中
            $insertContents = array(
                'title' => empty($title) ? '' : htmlspecialchars_decode($title),
                'created' => $created,
                'modified' => time(),
				'text' => empty($text) ? '' :  htmlspecialchars_decode($text),
                'order' => !isset($reqData['order']) ? 0 : intval($reqData['order']),
                'authorId' => $authorId,
                'template' => !isset($reqData['template']) ? NULL : $reqData['template'],
                'type' => !isset($reqData['type']) ? 'post' : $reqData['type'],
				'status' => !isset($reqData['status']) ? 'publish' : $reqData['status'],
                'password' => !isset($reqData['password']) ? NULL : $reqData['password'],
                'commentsNum' => 0,
				'allowComment' => !isset($reqData['allowComment']) ? '1' : $reqData['allowComment'],
				'allowPing' => !isset($reqData['allowPing']) ? '1' : $reqData['allowPing'],
				'allowFeed' => !isset($reqData['allowFeed']) ? '1' : $reqData['allowFeed'],
                'parent' => !isset($reqData['parent']) ? 0 : intval($reqData['parent'])
            );
            try {
                $postId = $wpdb->query($wpdb->insert('table.contents')->rows($insertContents));
            }catch (Exception $e){
                keydatas_failRsp('1405',$e->getMessage(),"新增文章失败");
            }
            
            $slug = $postId;
            if ($postId > 0) {
                $randSlug = Typecho_Common::randString(6);
                $slug = empty($reqData['slug']) ? $randSlug : $reqData['slug'];
                $slug = Typecho_Common::slugName($slug, $postId);
                $this->db->query($this->db->update('table.contents')->rows(array('slug' => $slug))
                    ->where('cid = ?', $postId));
            } else {
                keydatas_failRsp('1405', "add document failed", "新增文章失败");
            }
            
			
			//文章分类处理
            $categories = $reqData["categories"];
            $lastCategory = "default";
           
		    if (!empty($categories)) {
				
        $categories = str_replace("，",",",$categories);//把中文逗号替换成英文逗号
				$cates = explode(',',$categories);

                if (is_array($cates)) {
                    $cates = array_unique($cates);

                    //获取所有的分类id和名称
                    $allCates = $this->getAllCates();

                    for ($c = 0; $c < count($cates); $c++) {
                        $lastCategory = $cates[$c];
                        //分类不存在则创建
                        $metaCate = $this->isCateExist($cates[$c], $allCates);
                        $cateId = $metaCate[0];
                        if (!$metaCate) {
                            $cateId = $wpdb->query($wpdb->insert('table.metas')
                                ->rows(array(
                                    'name' => $cates[$c],
                                    'slug' => Typecho_Common::slugName($cates[$c]),
                                    'type' => 'category',
                                    'count' => 1,
                                    'order' => 1,
                                    'parent' => 0
                                )));
                        } else {
                            //更新分类对应的文章数量
                            $update = $wpdb->update('table.metas')->rows(array('count' => ($metaCate[2] + 1)))->where('mid=?', $metaCate[0]);
                            $updateRows = $wpdb->query($update);
                        }
						try {
							//插入关联分类和文章
							$wpdb->query($wpdb->insert('table.relationships')->rows(array('cid' => $postId, 'mid' => $cateId)));
						}catch (Exception $e){
							keydatas_failRsp('1405','add category error','新增文章分类错误');
						}
                    }
                }
   
		    } else {
							//当没有传入分类时，取得typecho系统初始化时的默认分类
							$defaultMid = 1;
              $lastrelation = $wpdb->query($wpdb->insert('table.relationships')
                  ->rows(array(
                      'cid' => $postId,
                      'mid' => $defaultMid
                  )));
            }
            
			
			//标签
            $reqTags = $reqData["tags"];
            $lastTag = "default";
           
		    if (!empty($reqTags)) {
					$reqTags = str_replace("，",",",$reqTags);//把中文逗号替换成英文逗号
					$tags = explode(',',$reqTags);

                if (is_array($tags)) {
                    $tags = array_unique($tags);
                    $allTags = $this->getAllTags();
                    for ($c = 0; $c < count($tags); $c++) {
                        $lastTag = $tags[$c];
                        $oneTag = $this->isTagExist($tags[$c], $allTags);
                        $tagId = $oneTag[0];
                        if (!$oneTag) {

                            $tagId = $wpdb->query($wpdb->insert('table.metas')
                                ->rows(array(
                                    'name' => $tags[$c],
                                    'slug' => Typecho_Common::slugName($tags[$c]),
                                    'type' => 'tag',
                                    'count' => 1,
                                    'order' => 1,
                                    'parent' => 0
                                )));
                        } else {
                            $update = $wpdb->update('table.metas')->rows(array('count' => ($oneTag[2] + 1)))->where('mid=?', $oneTag[0]);
                            $updateRows = $wpdb->query($update);
                        }

						try {
							$wpdb->query($wpdb->insert('table.relationships')->rows(array('cid' => $postId, 'mid' => $tagId)));
						}catch (Exception $e){
							keydatas_failRsp('1405','add tag error','新增文章标签错误');
						}
                    }
                }
   
		    } 


			/////图片http下载，不能用_POST
			$_REQ = $this->keydatasMergeRequest();
			$loadImages = $this->downloadImages($_REQ);	
			
            $docUrl = $this->genDocUrl($indexUrl, $postId, $slug, $lastCategory, $insertContents['created']);
            keydatas_successRsp(array("url" => $docUrl));
       
	    } 
		
    }//..action end
	
	 /**
     * 获取文件完整路径
     * @return string
     */
	public function getFilePath(){
		$rootUrl=dirname(dirname(dirname(dirname(__FILE__))));
		return $rootUrl.'/usr/uploads';
	}
	
    /**
     * 查找文件夹，如不存在就创建并授权
     * @return string
     */
	public function createFolders($dir){ 
		return is_dir($dir) or ($this->createFolders(dirname($dir)) and mkdir($dir, 0777)); 
	}	
	
	public function keydatasMergeRequest() {
		if (isset($_GET['__kds_flag'])) {
			$_REQ  = array_merge($_GET, $_POST);
		} else {
			$_REQ  = $_POST;
		}
		return $_REQ ;
	}



	public function  downloadImages($post){
	
	  try{
		
		$downloadFlag = isset($post['__kds_download_imgs_flag']) ? $post['__kds_download_imgs_flag'] : '';
		if (!empty($downloadFlag) && $downloadFlag== "true") {
			$docImgsStr = isset($post['__kds_docImgs']) ? $post['__kds_docImgs'] : '';
			
			if (!empty($docImgsStr)) {
				$docImgs = explode(',',$docImgsStr);
				if (is_array($docImgs)) {
					$uploadDir = $this->getFilePath();
					foreach ($docImgs as $imgUrl) {
						$urlItemArr = explode('/',$imgUrl);
						$itemLen=count($urlItemArr);
						if($itemLen>=3){
							
							$fileRelaPath=$urlItemArr[$itemLen-3].'/'.$urlItemArr[$itemLen-2];
							$imgName=$urlItemArr[$itemLen-1];
							$finalPath=$uploadDir. '/'.$fileRelaPath;
							if ($this->createFolders($finalPath)) {
								$file = $finalPath . '/' . $imgName;
								if(!file_exists($file)){
									$doc_image_data = file_get_contents($imgUrl);
									file_put_contents($file, $doc_image_data);
								}
							}
						}
					}
				}
			}				
		}
	 } catch (Exception $ex) {
		
	 }		
	}
	
	

	
    //取得所有的分类
    public function getAllCates()
    {
        $categories = null;
        $this->widget('Widget_Metas_Category_List')->to($categories);
        $categoriesArr = array();
        if ($categories->have()) {
            $next = $categories->next();
            while ($next) {
                $mid = $next['mid'];
                $catename = $next['name'];
                $count = $next['count'];
                $parent = $next['parent'];
                array_push($categoriesArr, array($mid, $catename, $count, $parent));
                $next = $categories->next();
            }
        }
        return $categoriesArr;
    }
    //取得所有的标签
    public function getAllTags()
    {
        $tags = null;
        Typecho_Widget::widget('Widget_Metas_Tag_Admin')->to($tags);    
        $tagsArr = array();
        while ($tags->next()) {
            array_push($tagsArr, array($tags->mid, $tags->name, $tags->count));
        }
        return $tagsArr;
    }

    //通过分类名判断分类是否存在
    public function isCateExist($cate, $allCates)
    {
        foreach ($allCates as $m) {
            if ($m[1] == $cate) {
                return $m;
            }
        }
        return false;
    }

    //通过标签名判断标签是否存在
    public function isTagExist($tag, $allTags)
    {
        foreach ($allTags as $t) {
            if ($t[1] == $tag) {
                return $t;
            }
        }
        return false;
    }

    //
    public function createUser($author)
    {
        $existUid = $this->isUserExist($author);
        if (!$existUid) {
            $hasher = new PasswordHash(8, true);
            $randString6 = Typecho_Common::randString(6);
            $user = array(
                'name' => $author,
                'url' => '',
                'group' => 'contributor',
                'created' => $this->options->gmtTime,
                'password' => $randString6
            );
            $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];
            $user['password'] = $hasher->HashPassword($user['password']);
            $authCode = function_exists('openssl_random_pseudo_bytes') ? bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Typecho_Common::randString(20));
            $user['authCode'] = $authCode;

            try {
                $insertId = $this->db->query($this->db->insert('table.users')->rows($user));
            }catch (Exception $e){
                keydatas_failRsp('1406',"add user failed","新增用户失败");
            }
            if ($insertId) {
                return $insertId;
            }
        } else {
            return $existUid;
        }
        return false;
    }

    //判断用户名是否存在
    public function isUserExist($author)
    {
        //先用作者名查找是否已存在用户
		$user = $this->db->fetchRow($this->db->select('uid')->from('table.users')->where('name = ?', $author)->limit(1));
        $uid = $user["uid"];
        if ($uid) {
            return $uid;
        } else {
		    //如找不到的话，使用uid再找找看
			$user = $this->db->fetchRow($this->db->select('uid')->from('table.users')->where('uid = ?', $author)->limit(1));
			$uid = $user["uid"];
			if ($uid) {
				return $uid;
			} else {
				return false;
			}
        }
    }

    //
    public function genDocUrl($indexUrl, $cid, $slug, $category, $time){
        $today = date("Y-m-d", $time);
        $todayArr = explode('-', $today);
        $year = $todayArr[0];
        $month = $todayArr[1];
        $day = $todayArr[2];
        $rule = Typecho_Widget::widget('Widget_Options')->routingTable['post']['url'];
        $rule = preg_replace("/\[([_a-z0-9-]+)[^\]]*\]/i", "{\\1}", $rule);
        $partUrl = str_replace(array('{cid}', '{slug}', '{category}', '{directory}', '{year}', '{month}', '{day}', '{mid}'),array($cid, $slug, $category, '[directory:split:0]',$year, $month, $day, 1), $rule);
        $partUrl = ltrim($partUrl, '/');
        $siteurl = $indexUrl;
        if (!Typecho_Widget::widget('Widget_Options')->rewrite) {
            $siteurl = $siteurl . 'index.php/';
        }
        $docUrl = $siteurl.$partUrl;
        return $docUrl;
    }




    public function verifyPassword($kds_password){
		$this->options = Typecho_Widget::widget('Widget_Options')->plugin('keydatas');
        if(empty($kds_password) || $kds_password != $this->options->kds_password){
            keydatas_failRsp('1403', "wrong password", "发布密码错误");
        }
    }
	


}