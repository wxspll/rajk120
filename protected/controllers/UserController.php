<?php
/*
    用户中心
*/
class UserController extends Controller
{
    
    /*public function init()  
    {     
        //parent::init();     
        Yii::app()->clientScript->registerCssFile(WEB_SITE_CSS_URL.'user.css'); 
        Yii::app()->clientScript->registerCssFile(SBT_CSS_URL.'css.css'); 
        
    }*/
    
	public function filters()
    {
        return array(
            'accessControl',
        );
    }
    
    public function accessRules()
    {
        return array(
				array('allow',  // 允许所有用户访问 'login' 动作.
					'actions'=>array('error'),
					'users'=>array('*'),
				),
				array('allow', // 允许认证用户访问所有动作
					'users'=>array('@'),
				),
				array('deny',  // 拒绝所有的用户。
					'users'=>array('*'),
				),
			);
    }
    
    public function actionGetAcceptList()
    {
       $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
       if(!$task_id) exit('NO_TASK_ID');
       //寻找该任务的申请者
       $count = Usertasklist::model()->count('task_id='.$task_id.' AND state=0');
       if($count > 0)
       {
            $userList = Usertasklist::model()->findAll(
                array('condition' => 'task_id='.$task_id.' AND state=0') 
            );
            $divStart="<div class='choseBuyer'><div style='font-size:14px; line-height:35px;'>选择接手此任务的买号：</div>";
            $divEnd="</div>";
            $radioItemStr="";
            foreach($userList as $k=>$v)
            {
                //查询此旺旺的信息
                $wangwangInfo = Blindwangwang::model()->findByAttributes( array('userid' => $v['uid'], 'wangwang' => $v['user_wangwang']));
                $userInfo = User::model()->findByPk($v['uid']);
                $vip = '';
                if($userInfo['VipLv'] > 0) $vip = '<img src="'.VERSION2.'img/newlevel/VIP.png" style="vertical-align: text-top;cursor:pointer;" />';
                //获取此买手的等级
                if($k==0)
                {
                    $radioItemStr=$radioItemStr."<li><input type='radio' name='buyerSelected' checked='checked' value='".$v['id']."' />&nbsp;接手买号：".$v['user_wangwang']."<img src=\"".VERSION2."img/level/".$wangwangInfo->wangwanginfo.".gif\" style=\"vertical-align: text-top;cursor:pointer;\" />&nbsp;&nbsp;(".Blindwangwang::getWwTaskNum($v['user_wangwang'], 1)."/".Blindwangwang::getWwTaskNum($v['user_wangwang'], 7)."/".Blindwangwang::getWwTaskNum($v['user_wangwang'], 1,'month').")，会员等级：<img src=\"".VERSION2."img/newlevel/".User::getuserlevelnum($v['uid']).".gif\" style=\"vertical-align: text-top;cursor:pointer;\" />{$vip}</li>";
                }
                else
                {
                    $radioItemStr=$radioItemStr."<li><input type='radio' name='buyerSelected' value='".$v['id']."' />&nbsp;接手买号：".$v['user_wangwang']."<img src=\"".VERSION2."img/level/".$wangwangInfo->wangwanginfo.".gif\" style=\"vertical-align: text-top;cursor:pointer;\" />&nbsp;&nbsp;".Blindwangwang::getWwTaskNum($v['user_wangwang'], 1)."/".Blindwangwang::getWwTaskNum($v['user_wangwang'], 7)."/".Blindwangwang::getWwTaskNum($v['user_wangwang'], 1,'month')."，会员等级：<img src=\"".VERSION2."img/newlevel/".User::getuserlevelnum($v['uid']).".gif\" style=\"vertical-align: text-top;cursor:pointer;\" />{$vip}</li>";
                }
            }
            echo $divStart.$radioItemStr.$divEnd;
       }else{
           exit('NO_ACCEPT_USER');
       }
       
    }
    
    //新的审核接手
    public function actionTaskBindingBuyer()
    {
        $buyerId = intval($_POST['buyer']);
        $taskId = intval($_POST['task_id']);
        //查询旺旺信息
        $buyer = Usertasklist::model()->findByPk($buyerId);
        //处理任务信息
        $taskInfo=Companytasklist::model()->findByPk($taskId);//查询任务基本信息
        $taskInfo->taskerid=$buyer->uid;//接手id
        $taskInfo->taskerWangwang=$buyer->user_wangwang;//接手买号旺旺
        $taskInfo->taskfristTime=time();//接手接任务时间
        $taskInfo->status=2;//任务状态变2，即暂停通过审核，等待接手付款
        $taskInfo->tasksecondTime=time();//商家审核通过，即设置任务开始时间
        $taskInfo->save();
        $buyer->state=1;
        $buyer->save();
        //删除此任务的其他申请者
        Usertasklist::model()->deleteAll('task_id='.$taskId.' AND state=0');
        //删除此买号的其他未审核任务
        Usertasklist::model()->deleteAllByAttributes( array('uid' => $buyer->uid,'user_wangwang' => $buyer->user_wangwang, 'state' => 0));
        echo 'SUCCESS';
    }
    
    /**
     * 获取用户的等级信息
     * @param unknown $uid
     */
    public function getUserLevelInfo($uid)
    {
        $userInfo = User::model()->find('id='.$uid);
        if($userInfo)
        {
            if($userInfo->VipLv == 0 ) return '新手会员';
            if($userInfo->VipLv == 1 ) return 'VIP会员';
        }
        return false;
    }
    
    /*
        任务大厅-任务投诉
    */
    public function actionUserTaskComplian()
    {
        if(isset($_POST['taskid']) && $_POST['taskid']!="" && isset($_POST['reason']) && $_POST['reason']!="" && isset($_POST['userStyle']))
        {
            $checkExist=Complianlist::model()->findByAttributes(array('taskid'=>$_POST['taskid']));
            if($checkExist)//对该任务已经投诉过
            {
                echo "EXIST";
            }else{
                $taskinfo=Companytasklist::model()->findByPk($_POST['taskid']);
                $taskinfo->complian_status=1;//改变状态为投诉
                $taskinfo->complian_introduce=$_POST['reason'];//投诉原因说明
                $taskinfo->save();
                
                $complianinfo=new Complianlist;
                if($_POST['userStyle']==0)//发起投诉的人为商家
                {
                    $complianinfo->uid=$taskinfo->taskerid;//则被投诉人为接手
                }else//发起人为威客
                {
                    $complianinfo->uid=$taskinfo->publishid;//则被投诉人为商家
                }
                $complianinfo->douid=Yii::app()->user->getId();//发起投诉的人的id
                $complianinfo->taskid=$_POST['taskid'];//投诉对应的任务id
                $complianinfo->reason=$_POST['reason'];//投诉原因说明
                $complianinfo->reasonImg=$_POST['reasonImg'];//投诉的图片证据
                $complianinfo->time=time();//发起投诉的时间
                if($complianinfo->save())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }
            Yii::app()->end();
        }
        $this->renderPartial('userTaskComplian');
    }
    
    /*
        用户中心-购买米粒
    */
    public function actionUserBuyPoint()
    {
        if(isset($_POST['MinLinNum']) && $_POST['MinLinNum']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($_POST['MinLinNum']*0.63>$userinfo->Money)//余额不足
            {
                echo "MONEYNOTENOUGH";
            }
            else//余额充足
            {
                //添加流水
                //1.保存米粒回收流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=2;//购买米粒类型
                $record->number=$_POST['MinLinNum'];//购买米粒数量
                $record->time=time();//操作时间
                $record->save();//保存流水
                
                //2.改变购买米粒后帐户的余额与米粒数量
                $userinfo->Money=$userinfo->Money-$_POST['MinLinNum']*0.63;//在原有余额基本上减去购买米粒使用掉的金额
                $userinfo->MinLi=$userinfo->MinLi+$_POST['MinLinNum'];//在原有米粒基础上加上购买的米粒
                $userinfo->save();
                echo "SUCCESS";
            }
            Yii::app()->end();
        }
        else
            $this->render('userBuyPoint');
    }
    
    /*
        用户中心-购买vip等级
    */
    public function actionUserBuyVipLevel()
    {
        if(isset($_POST['vipType']) && isset($_POST['month']) && isset($_POST['vipPrice']))
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            $userinfo->VipLv=$_POST['vipType'];//VIP等级
            $userinfo->VipBuyTime=time();//VIP购买时间
            $userinfo->VipStopTime=$userinfo->VipBuyTime+$_POST['month']*30*24*3600;//VIP购买多久
            $userinfo->Money=$userinfo->Money-$_POST['vipPrice'];//余额发生变化
            
            if($userinfo->save())
            {
                //保存金额流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=3;//发布任务使用金额
                $record->number=$_POST['vipPrice'];//操作数量
                $record->time=$userinfo->VipBuyTime;//操作时间
                $record->save();//保存金额流水
                echo "SUCCESS";
            }else
                echo "FAIL";
        }
    }
    
    /*
        用户中心-加入商保
    */
    public function actionUserSBcenter()
    {
        //加入商保
        if(isset($_POST['joinProtectPlan']) && $_POST['joinProtectPlan']=="DONE")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($userinfo->JoinProtectPlan==0)//未加入商保
            {
                $userinfo->JoinProtectPlan=1;//将商保状态改变为加入
                $userinfo->JoinProtectPlanMoney=50;//将商保金额改变300
                $userinfo->Money=$userinfo->Money-50;//余额减少300
                $userinfo->JoinProtectPlanTime=time();//加入商保的时间
                
                if($userinfo->save())
                {
                    //保存金额流水
                    $record=new Recordlist();
                    $record->userid=Yii::app()->user->getId();//用户id
                    $record->catalog=11;//加入商保使用的金额
                    $record->number=50;//操作数量
                    $record->time=$userinfo->JoinProtectPlanTime;//操作时间
                    $record->save();//保存金额流水
                    //进行返利判断
                    if(!empty($userinfo->IdNumber))
                    {
                        //处理推荐返利,获取返利配置
                        $ptConfig = Config::model()->findByPk(1);
                        $referrerInfo = User::model()->findByPk($userinfo->IdNumber);
                        //判断返利1
                        if(isset($referrerInfo) && $referrerInfo->tj_award_result < 1)
                        {
                            //如果完成任务的这个人存在上级推荐会员，且上级会员的奖励1还没有给他，则进行判断是否符合条件，如果符号条件，就给他发放奖励
                            if($ptConfig->tj_is_sb > 0 && $ptConfig->tj_award_num > 0)
                            {
                                //如果开启了商保，则进行返利
                                if($ptConfig->tj_task_num > 0)
                                {
                                    //如果指定了要完成的任务数量，就判断是否符合这个条件
                                    //统计这个刷手已完成任务数量
                                    $count = Companytasklist::model()->count('taskerid='.$userinfo->id.' AND taskCompleteStatus=1');
                                    if($count >= $ptConfig->tj_task_num && $ptConfig->tj_award_num > 0)
                                    {
                                        //符合条件，则进行返利
                                        $record=new Recordlist();
                                        $record->userid=$referrerInfo->id;//当前任务接手的上级会员ID
                                        $record->catalog=12;//返利获得金额
                                        $record->number=$ptConfig->tj_award_num;//操作金额
                                        $record->time=time();//操作时间
                                        $record->taskid=0;//任务id
                                        $record->save();//保存金额流水
                                        $referrerInfo->tj_award_result = 1;
                                        $referrerInfo->Money = ($referrerInfo->Money+$ptConfig->tj_award_num);
                                        $referrerInfo->save();
                                    }
                                }else{
                                    //没有任务数量限制，直接就进行返利
                                    //符合条件，则进行返利
                                    $record=new Recordlist();
                                    $record->userid=$referrerInfo->id;//当前任务接手的上级会员ID
                                    $record->catalog=12;//返利获得金额
                                    $record->number=$ptConfig->tj_award_num;//操作金额
                                    $record->time=time();//操作时间
                                    $record->taskid=0;//任务id
                                    $record->save();//保存金额流水
                                    $referrerInfo->tj_award_result = 1;
                                    $referrerInfo->Money = ($referrerInfo->Money+$ptConfig->tj_award_num);
                                    $referrerInfo->save();
                                }
                            }
                        }
                    }
                    echo "SUCCESS";
                }
                else
                    echo "FAIL";
                
            }else//已加入商保，无法
            {
                echo "JOINYET";
            }
            Yii::app()->end();
        }
        
        //退出商保申请
        if(isset($_POST['exitProtectPlan']) && $_POST['exitProtectPlan']=="DONE")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($userinfo->JoinProtectPlan==1)//已加入商保，可以申请退出
            {
                //保存申请记录
                $exitProtectPlanRecord=new Exitprotectplanrecord();
                $exitProtectPlanRecord->uid=Yii::app()->user->getId();//用户id
                $exitProtectPlanRecord->status=0;//申请中
                $exitProtectPlanRecord->time=time();//申请提交时间
                if($exitProtectPlanRecord->save())
                    echo "SUCCESS";
                else
                    echo "FAIL";
                
            }else//未加入商保，无法申请
            {
                echo "NOTJOINYET";
            }
            Yii::app()->end();
        }
        
        //取消退出商保
        if(isset($_POST['DelexitProtectPlan']) && $_POST['DelexitProtectPlan']=="DONE")
        {
            $exitProtectPlanRecord=Exitprotectplanrecord::model()->findByAttributes(array(
                'uid'=>Yii::app()->user->getId(),
                'status'=>0
            ));
            if($exitProtectPlanRecord)//存在即可以取消申请
            {
                if($exitProtectPlanRecord->delete())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }
            else
            {
                echo "NOTEXIT";
            }
            Yii::app()->end();   
        }
        
        $this->render('userSBcenter');
    }
    
    /*
        用户中心-成为职业威客
    */
    public function actionUserZYWK()
    {
        $this->render('userZYWK');
    }
    
    /*
        用户中心-查看排行榜
    */
    public function actionUserTop10()
    {
        $this->render('userTop10');
    }
    
    /*
        用户中心-用户推广赚钱
    */
    public function actionUserSpread()
    {
        //查询此会员推荐的会员
        $userList = User::model()->findAll('IdNumber='.Yii::app()->user->getId());
        $this->render('userSpread', array('userList'=>$userList));
    }
    
    /*
        用户中心-生成用户推广的专属链接
    */
    public function actionuserSpreadLink()
    {
        $this->renderPartial('userSpreadLink');
    }
    
    public function actionGetTitle()
    {
        
        if(isset($_POST['url']) && $_POST['url']!="")
        {
            $url=$_POST['url'];
            if(@preg_match('/(<div class="tb-detail-hd">)(.*)(<\/div>)/is',file_get_contents($url), $matches) && strpos($url,"detail.tmall.com")){
                $arrNew=explode("</h1>",$matches[0]);
                $promotionlisttitle=strip_tags($arrNew[0]);
            } 
            else if(@preg_match('/(<h3 class="tb-main-title">)(.*)(<\/h3>)/is',file_get_contents($url), $matches) && strpos($url,"item.taobao.com"))
            {
                $protitle=strip_tags($matches[0]);
            }
            else   
            {
                $protitle=0;
            }
            
            echo iconv("gbk//TRANSLIT","UTF-8",trim($protitle));
        }
    }
    
    /*
        用户中心-手机号码绑定激活
    */
    public function actionUserPhonActive()
    {
        if(isset($_POST['phone']) && isset($_POST['phoneCode']))
        {
            if($_POST['phoneCode']==Yii::app()->session['code'])
            {
                if($_POST['phone']==Yii::app()->session['phone'])
                {
                    unset(Yii::app()->session['code']);//清除验证码
                    unset(Yii::app()->session['phone']);//清除手机号
                    $userinfo=User::model()->findByPk(Yii::app()->user->getId());
                    $userinfo->Phon=$_POST['phone'];//重新配置用户的手机号码
                    $userinfo->PhonActive=1;//改变用户手机激活的状态
                    $userinfo->save();
                    echo "SUCCESS";
                }
                else
                {
                    echo "PHONEFAIL";
                }
            }else
            {
                echo "CODEFAIL";//验证码不正确
            }
        }
        else
            $this->render('userPhonActive');
    }
    
    /*
        用户中心-检查安全码是否正确
    */
    public function actionCheckSafePwd()
    {
        if(isset($_POST['safePwd']) && $_POST['safePwd']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($userinfo->SafePwd==md5($_POST['safePwd']))
                echo "SUCCESS";
            else
                echo "FAIL";
        }else
            echo "FAIL";
    }
    
    /*
        用户中心-修改安全操作码
    */
    public function actionUserSafePwdFirstSet()
    {
        $userInfo=User::model()->findByPk(Yii::app()->user->getId());
        if(isset($_POST['setSafePwd']) && $_POST['setSafePwd']=="Done" && isset($_POST['safePwd']))
        { 
            if($userInfo->SafePwd=="")//没有设置过安全码
            {
                $userInfo->SafePwd=md5($_POST['safePwd']);
                if($userInfo->save())
                {
                    $this->redirect(array('user/index'));
                }
                else
                {
                    $this->redirect_message('安全码设置失败，请联系客服人员！','success',10,$this->createUrl('site/index'));
                }
            }else
            {
                $this->redirect_message('您已经设置过安全码！','success',10,$this->createUrl('site/index'));
            }
        }
        else{
            if($userInfo->SafePwd=="")//没有设置过安全码
                $this->render('userSafePwdFirstSet');
            else
                $this->redirect_message('您已经设置过安全码！','success',10,$this->createUrl('user/index'));
        }
    }
    
    /*
        用户中心-修改绑定手机号码
    */
    public function actionUserChangPhone()
    {
        if(isset($_POST['phone']) && $_POST['phone']!="")//修改手机号码
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            $userinfo->Phon=$_POST['phone'];//修改为新的手机号码
            if($userinfo->save())
                echo "SUCCESS";
            else
                echo "FAIL";
        }
        else
            $this->renderPartial('userChangPhone');
    }
    
    /*
        用户中心-投诉中心-发起的投诉
    */
    public function actionUserTsCenter()
    {
        $criteria = new CDbCriteria;
        $criteria->order ="time desc";
        $criteria->addCondition('douid='.Yii::app()->user->getId());//查询我发起的投诉
    
        //分页开始
        $total = Complianlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Complianlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userTsCenter',array(
            'proInfo' => $proreg,
            'pages' => $pages,
            'total'=>$total
        ));
    }
    
    /*
        用户中心-投诉中心-收到的投诉
    */
    public function actionUserTsCenterGet()
    {
        $criteria = new CDbCriteria;
        $criteria->order ="time desc";
        $criteria->addCondition('uid='.Yii::app()->user->getId());//查询我发起的投诉
    
        //分页开始
        $total = Complianlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Complianlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userTsCenterGet',array(
            'proInfo' => $proreg,
            'pages' => $pages,
            'total'=>$total
        ));
    }
    
    /*
        用户中心-添加卡号
    */
    public function actionUserAddBank()
    {
        $this->renderPartial('userAddBank');
    }
    
    /*
        用户中心-确认添加银行卡
    */
    public function actionUserAddBankCertain()
    {
        if(isset($_POST['truename']) && $_POST['truename']!="" && isset($_POST['bankAccount']) && $_POST['bankAccount']!="")
        {
            $checkBank=Banklist::model()->findByAttributes(array('bankAccount'=>$_POST['bankAccount']));
            if($checkBank)//银行卡已存在
            {
                echo "BANKACCOUNTEXIT";
            }else//不存在则进行添加
            {
                $bankInfo=new Banklist();
                $bankInfo->userid=Yii::app()->user->getId();//用户id
                $bankInfo->truename=$_POST['truename'];//真实姓名
                $bankInfo->phone=$_POST['Phon'];//添加银行卡时绑定的手机号码
                $bankInfo->bankCatalog=$_POST['bankCatalog'];//银行卡的类型即银行名称
                $bankInfo->bankAccount=$_POST['bankAccount'];//银行卡号
                $bankInfo->time=time();//添加银行卡时间
                if($bankInfo->save())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }
        }
        else
            echo "FAIL";
    }
 
    /*
        用户中心-用户中心首页-总览
    */
    public function actionIndex()
    {
        //开启异地登录验证
        if(isset($_POST['otherPlaceLogin']) && $_POST['otherPlaceLogin']=="Done")
        {
            $userInfo=User::model()->findByPk(Yii::app()->user->getId());
            $userInfo->PlaceOtherLogin=1;//开启异常登录
            if($userInfo->save())
                echo 'SUCCESS';
            else
                echo 'FAIL';
            Yii::app()->end();
        }
        
        //关闭异地登录验证
        if(isset($_POST['otherPlaceLoginClose']) && $_POST['otherPlaceLoginClose']=="Done")
        {
            $userInfo=User::model()->findByPk(Yii::app()->user->getId());
            $userInfo->PlaceOtherLogin=0;//关闭异常登录
            if($userInfo->save())
                echo 'SUCCESS';
            else
                echo 'FAIL';
            Yii::app()->end();
        }
        
        $this->render('index');
    }
    
    
    /*
        用户中心-新手考试
    */
    public function actionUserExam()
    {
        //提交试卷
        if(isset($_POST['qeustion']) && isset($_POST['answer']))
        {
            $total=count($_POST['qeustion']);//题目总数
            $answerCount=0;//计算答对数量
            foreach($_POST['answer'] as $k=>$v)
            {
                $exam=Exam::model()->find(array(
                    'condition'=>'id='.$v.' and answer=1'
                ));
                if($exam)
                    $answerCount++;
            }
            
            //如果答对数量超过60%则通过考试
            if($answerCount/$total>0.6 || $answerCount/$total==0.6)
            {
                $userInfo=User::model()->findByPk(Yii::app()->user->getId());
                $userInfo->ExamPass=1;//考试通过，改变考试状态
                $userInfo->save();
                echo "SUCCESS";
            }
            else
            {
                echo "FAIL";
            }
            Yii::app()->end();
        }
        
        $this->render('userExam');
    }
    
    /*
        用户中心-淘宝大厅-已接任务
    */
    public function actionTaobaoInTask()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition=' id IN(select task_id from zxjy_usertasklist WHERE uid='.Yii::app()->user->getId().' AND state=0) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="time desc";
            
            
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoInTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //我接手的任务
	    $criteria = new CDbCriteria;
        $criteria->condition=' id IN(select task_id from zxjy_usertasklist WHERE uid='.Yii::app()->user->getId().' AND state=0)';//不查询已完成的任务
        $criteria->order ="status asc";
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        $this->render('taobaoInTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /**
     * 取消任务
     */
    public function actionCancelTask()
    {
        $taskId = intval($_POST['taskId']);
        if($taskId > 0)
        {
            //查询任务申请状态
            $aTaskInfo = Usertasklist::model()->findByAttributes( array('uid' => Yii::app()->user->getId(),'task_id'=> $taskId));
            if(!isset($aTaskInfo))
            {
                //任务不存在
                exit('NOT_A_THIS_TASKER');
            }
            if($aTaskInfo->state == 1)
            {
                exit('YIJINGSHENHETONGGUO');
            }
            //取消此申请
            $aTaskInfo->delete();
            exit('success');
        }else{
            echo 'fail';
        }
    }
    
    /**
     * 已审核通过，取消任务
     */
    public function actionSellerCancelTask()
    {
        $taskId = intval($_POST['taskId']);
        if($taskId > 0)
        {
            //重置任务的部分字段
            $taskInfo = Companytasklist::model()->findByPk($taskId);
            //查询任务申请状态
            $aTaskInfo = Usertasklist::model()->findByAttributes( array('uid' => $taskInfo->taskerid,'task_id'=> $taskId));
            if(!isset($aTaskInfo))
            {
                //任务不存在
                exit('NOT_A_THIS_TASKER');
            }
            
            if(isset($taskInfo))
            {
                //取消此申请
                $aTaskInfo->delete();
                if($taskInfo->status != 2) exit('STATUS_ERROR');
                $taskInfo->taskerid = 0;
                $taskInfo->taskerWangwang = '';
                $taskInfo->taskfristTime = 0;
                $taskInfo->tasksecondTime = 0;
                $taskInfo->taskthirdTime = 0;
                $taskInfo->taskforthTime = 0;
                $taskInfo->status =0;
                $taskInfo->tasktime = 0;
                $taskInfo->save();
                exit('success');
            }else{
                exit('fail');
            }
        }else{
            echo 'fail';
        }
    }
    
    /*
        已接任务-等待对商品付款
    */ 
    public function actionTaobaoInTaskWaitPay()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='taskerid='.Yii::app()->user->getId().' and status IN(2,3) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="tasksecondTime desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoInTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //我接手的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='taskerid='.Yii::app()->user->getId().' and  status IN(2,3)';//不查询已完成的任务
        $criteria->order ="tasksecondTime desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoInTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        已接任务-等待收货好评
    */ 
    public function actionTaobaoInTaskWaitSHHP()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='taskerid='.Yii::app()->user->getId().' and status<>6 and status=4 and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="taskthirdTime desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoInTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //我接手的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='taskerid='.Yii::app()->user->getId().' and status<>6 and status=4';//不查询已完成的任务
        $criteria->order ="taskthirdTime desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoInTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        已接任务-已完成任务
    */ 
    public function actionTaobaoInTaskComplete()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='taskerid='.Yii::app()->user->getId().' and taskCompleteStatus=1 and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="taskcompleteTime desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoInTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //我接手的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='taskerid='.Yii::app()->user->getId().' and taskCompleteStatus=1';//不查询已完成的任务
        $criteria->order ="taskcompleteTime desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoInTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        已接任务-全部任务
    */
    public function actionTaobaoInTaskAllList()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='taskerid='.Yii::app()->user->getId().' and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="taskcompleteTime desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoInTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //我接手的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='taskerid='.Yii::app()->user->getId();//不查询已完成的任务
        $criteria->order ="taskcompleteTime desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoInTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    
     /*
        用户中心-淘宝大厅-已发任务
    */
    public function actionTaobaoOutTask()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='publishid='.Yii::app()->user->getId().' id IN(select task_id from zxjy_usertasklist WHERE task_id IN(select id from zxjy_companytasklist  WHERE publishid='.Yii::app()->user->getId().' AND state=0)) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="status desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoOutTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //发布的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='publishid='.Yii::app()->user->getId().' and id IN(select task_id from zxjy_usertasklist WHERE task_id IN(select id from zxjy_companytasklist  WHERE publishid='.Yii::app()->user->getId().' AND state=0))';//不查询已完成的任务
        $criteria->order ="`time` desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoOutTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-淘宝大厅-已发任务-暂停的任务
    */
    public function actionTaobaoOutTaskStop()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(2,3) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="time desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoOutTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //发布的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(2,3)';//商家暂停的任务
        $criteria->order ="time desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoOutTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }

    /*
     用户中心-淘宝大厅-已发任务-暂停的任务-没有人申请的
     */
    public function actionTaobaoOutTaskStop2()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
            $criteria = new CDbCriteria;
            $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(6,0) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="time desc";
    
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
    
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
    
            $this->render('taobaoOutTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //发布的任务
        $criteria = new CDbCriteria;
        $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(6,0)';//商家暂停的任务
        $criteria->order ="time desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
    
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
    
        $this->render('taobaoOutTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    /*
        用户中心-淘宝大厅-已发任务-已完成的任务
    */
    public function actionTaobaoOutTaskComplete()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(4) and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="time desc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoOutTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //发布的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='publishid='.Yii::app()->user->getId().' and status IN(4,5) AND taskCompleteStatus=0';//商家暂停的任务
        $criteria->order ="time desc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoOutTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-淘宝大厅-已发任务-已完成的任务
    */
    public function actionTaobaoOutTaskAllList()
    {
        //关键词搜索
        if(isset($_POST['keywords']) && $_POST['keywords']!="")
        {
            $keywordsArr=explode('*',$_POST['keywords']);//分解关键词
            //任务大厅
    	    $criteria = new CDbCriteria;
            $criteria->condition='publishid='.Yii::app()->user->getId().' and time='.trim($keywordsArr[0]).' and id='.trim($keywordsArr[1]);
            $criteria->order ="`time` asc";
        
            //分页开始
            $total =Companytasklist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Companytasklist::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoOutTask',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        //发布的任务
	    $criteria = new CDbCriteria;
        $criteria->condition='publishid='.Yii::app()->user->getId();//商家暂停的任务
        $criteria->order ="`time` asc";
    
        //分页开始
        $total =Companytasklist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Companytasklist::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoOutTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-任务完成-任务好中差评价
    */
    public function actionTaskAppraise()
    {
        if(isset($_POST['taskid']) && $_POST['taskid']!="" && isset($_POST['pjstatus']) && $_POST['pjstatus']!="")
        {
            $taskinfo=Companytasklist::model()->findByPk($_POST['taskid']);
            $appraise=new Appraise;
            $appraise->uid=$taskinfo->taskerid;//威客id
            $appraise->douid=$taskinfo->publishid;//评价人id即商家id
            $appraise->status=$_POST['pjstatus'];//评价状态：0-差评，1-中评，2-好评
            $appraise->taskid=$_POST['taskid'];//任务id
            $appraise->time=time();//评价时间
            if($appraise->save())
                echo "SUCCESS";
            else
                echo "FAIL";  
        }
    }
    
    /*
        用户中心-淘宝大厅-淘宝绑定买号
    */
    public function actionTaobaoBindBuyer()
    {
        if(isset($_POST['bdmh']))
        {   
            $checkBlindwangwang=Blindwangwang::model()->find(array(
                'condition'=>"wangwang='".$_POST['bdmh']."' and statue <>3"
            ));
            
            
            if(count($checkBlindwangwang)>0)//买号已存在
            {
                $warning="该买号已经被绑定过";
            }
            else
            {
                $checkBlindwangwang=Blindwangwang::model()->find(array(
                    'condition'=>"wangwang='".$_POST['bdmh']."' and userid=".Yii::app()->user->getId().' and statue=3'
                ));
                if(count($checkBlindwangwang)>0)//如果该用户绑定号已经存在，只是被系统取消了，也就是statue状态为3的情况下
                {
                    $checkBlindwangwang->statue=1;
                    if($checkBlindwangwang->save())
                    {
                        $this->redirect('blindBuyCount');
                    }
                    Yii::app()->end();
                }
                
                
                $blindwangwang=new Blindwangwang;
                $blindwangwang->userid=Yii::app()->user->getId();//用户id
                $blindwangwang->wangwang=$_POST['bdmh'];//淘宝买家帐号
                $blindwangwang->wangwanginfo=$_POST['wangwanginfo'];//淘宝帐号等级信息
                $blindwangwang->taotaorz=$_POST['taotaorz'];//是否通过淘宝实名认证
                $blindwangwang->ip=XUtils::getClientIP();//操作ip
                $blindwangwang->blindtime=time();//绑定时间
                $blindwangwang->id_card = $_POST['id_card'];
                $blindwangwang->alipay_account = $_POST['alipay_account'];
                $blindwangwang->real_name = $_POST['real_name'];
                $blindwangwang->bank_no = $_POST['bank_no'];
                $blindwangwang->bank_name = $_POST['bank_name'];
                if($blindwangwang->save())
                    $warning="恭喜您，买号绑定成功！";
                else
                    $warning="买号绑定失败";
            }
            $criteria = new CDbCriteria;
            $criteria->order ="blindtime desc";
            $criteria->addCondition('userid='.Yii::app()->user->getId().' and catalog=1');//查询绑定的买号
        
            //分页开始
            $total = Blindwangwang::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=5;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Blindwangwang::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoBindBuyer',array(
                "warning"=>$warning,
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        
        $criteria = new CDbCriteria;
        $criteria->order ="blindtime desc";
        $criteria->addCondition('userid='.Yii::app()->user->getId().' and catalog=1');//查询绑定的买号
    
        //分页开始
        $total = Blindwangwang::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=5;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Blindwangwang::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoBindBuyer',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-淘宝大厅-删除买号
    */
    public function actionTaobaoBindBuyerDel()
    {
        if(isset($_GET['id']))
        {
            $blindInfo=Blindwangwang::model()->findByAttributes(array(
                'userid'=>Yii::app()->user->getId(),
                'id'=>$_GET['id']
            ));
            if($blindInfo)//检查要删除的买号是否属于当前登录用户
            {
                $blindInfo->delete();
                $this->redirect(array('user/taobaoBindBuyer'));
            }
            else
            {
                $this->redirect_message('您无权删除','success',3,$this->createUrl('user/taobaoBindBuyer'));
            }
        }
        else
            $this->redirect_message('您无权删除','success',3,$this->createUrl('user/taobaoBindBuyer'));
    }
    
     /*
        用户中心-淘宝大厅-修改买号信息
    */
    public function actionTaobaoBindBuyerChangeInfo()
    {
        if(isset($_POST['id']) || isset($_POST['value']) || isset($_POST['action']))
        {
            $blindInfo=Blindwangwang::model()->findByPk($_POST['id']);
            if($_POST['action']=="realnameOn")//修改淘宝实名认证信息
            {
                $blindInfo->taotaorz=$_POST['value'];
            }
            
            if($_POST['action']=="changeStatusOn")//修改是否启用买号
            {
                $statue=$_POST['value']==1?0:1;
                $blindInfo->statue=$statue;
            }
            
            if($_POST['action']=="taobaoScoreOn")//修改淘宝信誉
            {
                $blindInfo->wangwanginfo=$_POST['value'];
            }
            $blindInfo->is_check = 0;
            if($blindInfo->save())
                echo 1;
            else
                echo 0;
        }
    }
    
    
    
    /*
        用户中心-淘宝大厅-淘宝绑定掌柜
    */
    public function actionTaobaoBindSeller()
    {
        if(isset($_POST['bdmh']))
        {   
            $checkBlindwangwang=Blindwangwang::model()->find(array(
                'condition'=>"wangwang='".$_POST['bdmh']."' and statue <>3"
            ));
            
            
            if(count($checkBlindwangwang)>0)//买号已存在
            {
                $warning="该掌柜号已经被绑定过";
            }
            else
            {
                $checkBlindwangwang=Blindwangwang::model()->find(array(
                    'condition'=>"wangwang='".$_POST['bdmh']."' and userid=".Yii::app()->user->getId().' and statue=3'
                ));
                if(count($checkBlindwangwang)>0)//如果该用户绑定号已经存在，只是被系统取消了，也就是statue状态为3的情况下
                {
                    $checkBlindwangwang->statue=1;
                    if($checkBlindwangwang->save())
                    {
                        $this->redirect('blindBuyCount');
                    }
                    Yii::app()->end();
                }
                
                
                $blindwangwang=new Blindwangwang;
                $blindwangwang->userid=Yii::app()->user->getId();//用户id
                $blindwangwang->wangwang=$_POST['bdmh'];//淘宝掌柜帐号
                $blindwangwang->catalog=2;//帐号类型为2即掌柜帐号
                $blindwangwang->ip=XUtils::getClientIP();//操作ip
                $blindwangwang->blindtime=time();//绑定时间
                $blindwangwang->auth_url=$_POST['auth_url'];
                $blindwangwang->auth_price = $_POST['auth_price'];
                $blindwangwang->shop_url = $_POST['shop_url'];
                if($blindwangwang->save())
                    $warning="恭喜您，买号绑定成功！";
                else
                    $warning="买号绑定失败";
            }
            $criteria = new CDbCriteria;
            $criteria->order ="blindtime desc";
            $criteria->addCondition('userid='.Yii::app()->user->getId().' and catalog=2');//查询绑定的买号
        
            //分页开始
            $total = Blindwangwang::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=5;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Blindwangwang::model()->findAll($criteria);
            //分页结束
            
            $this->render('taobaoBindSeller',array(
                "warning"=>$warning,
                'proInfo' => $proreg,
                'pages' => $pages
            ));
            Yii::app()->end();
        }
        $criteria = new CDbCriteria;
        $criteria->order ="blindtime desc";
        $criteria->addCondition('userid='.Yii::app()->user->getId().' and catalog=2');//查询绑定的掌柜号
    
        //分页开始
        $total = Blindwangwang::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=5;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Blindwangwang::model()->findAll($criteria);
        //分页结束
        
        $this->render('taobaoBindSeller',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-淘宝大厅-删除掌柜号
    */
    public function actionTaobaoBindSellerDel()
    {
        if(isset($_GET['id']))
        {
            $blindInfo=Blindwangwang::model()->findByAttributes(array(
                'userid'=>Yii::app()->user->getId(),
                'id'=>$_GET['id']
            ));
            if($blindInfo)//检查要删除的买号是否属于当前登录用户
            {
                $blindInfo->delete();
                $this->redirect(array('user/taobaoBindSeller'));
            }
            else
            {
                $this->redirect_message('您无权删除','success',3,$this->createUrl('user/taobaoBindSeller'));
            }
        }
        else
            $this->redirect_message('您无权删除','success',3,$this->createUrl('user/taobaoBindSeller'));
    }
    
    /*
        用户中心-发布任务-ajax检查米粒
    */
    public function actiontaskCheckMinLi()
    {
        if(isset($_POST))
        {
            //米粒初始化
            $MinLi=0;
            
            //第一步：商品信息
            $MinLi=0;//$MinLi+$_POST['txtMinMPrice'];//增加基本米粒
            
            $_POST['ddlOKDay']!=0?$MinLi=$MinLi+($txtMinMPrice*1.5+($_POST['ddlOKDay']-1)):$MinLi=$MinLi+0;//根据确认时间增加对应米粒
            
            //第二步：增值服务
            $_POST['cbxIsWW']==1?$MinLi=$MinLi+1:$MinLi=$MinLi+0;//选中旺旺聊天米粒基数加1
            
            $_POST['shopcoller']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中购物收藏米粒基数加0.5
            
            //$_POST['isMobile']==1?$MinLi=$MinLi+2.0:$MinLi=$MinLi+0;//选中手机订单米粒基数加2.0
            
            //$_POST['cbxIsLHS']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中旺旺收货米粒基数加0.5
            
            //$_POST['isViewEnd']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中浏览到底米粒基数加0.5
            
            $_POST['pinimage']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中好评截图米粒基数加0.5
            
            if($_POST['stopDsTime']==1)//根据停留时间增加对应的米粒
            {
                switch($_POST['stopTime'])
                {
                    case 1://停留1分钟增加0.1米粒
                        $MinLi=$MinLi+0.1;
                        break;
                    case 2://停留2分钟增加0.3米粒
                        $MinLi=$MinLi+0.3; 
                        break;
                    case 3://停留3分钟增加0.5米粒
                        $MinLi=$MinLi+0.5;
                        break;
                }
            }
            $_POST['cbxIsMsg']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中好评内容米粒基数加0.5
            
            
            //第三步：筛选接手
            $_POST['cbxIsAudit']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中审核接手米粒基数加0.5
            
            $_POST['isReal']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中实名认证米粒基数加0.5
            
            $_POST['cbxIsSB']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中商保用户米粒基数加0.5
            
           
            
            $_POST['filtertasker']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中过滤接手米粒基数加0.5
            
            //第四步：快递空包
            $_POST['isSign']==1?$MinLi=$MinLi+2:$MinLi=$MinLi+0;//选中真实签收米粒基数加2
            
            $_POST['cbxIsAddress']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中收货地址米粒基数加0.5

            //echo $MinLi;
        }
    }
    
    /*
        用户中心-任务发布操作
    */
    public function actionTaskPublistHandle()
    {
        if(isset($_POST))
        {
            //echo "<pre/>";
            //var_dump($_POST);
            $task=new Companytasklist();
            $task->publishid=Yii::app()->user->getId();//发布人id
            $task->time=time();//发布任务时间
            $task->refresh_time=time();//发布任务时间
            $task->taskCatalog=$_POST['taskCatalog'];//任务类别（0-普通任务，1-来路来搜索任务）
            $task->platform=$_POST['platform'];//任务所属平台
            $task->payWay=$_POST['payWay'];//任务支付方式
            //判断是否为来路搜索任务
            if(isset($_POST['taskCatalog']))
            {
                $task->visitWay=$_POST['visitWay'];//搜索方式(1-搜商品,2-搜店铺,3- 直通车,4信用评价)
                //$task->divKey=$_POST['divKey'];//搜商品关键字
                $task->txtSearchDes=$_POST['txtSearchDes'];//商品搜索提示
                $task->goodsImgPosition=$_POST['goodsImgPosition'];//商品位置截图
            }
            if(isset($_POST['txtGoodsUrl']) && intval($_POST['txtGoodsUrl']) > 0)
            {
                $linkInfo = Goodsurl::model()->findByPk( intval($_POST['txtGoodsUrl']) );
                $task->txtGoodsUrl=$linkInfo->goods_url;//商品链接地址
                $task->goods_link_id = $linkInfo->id;
            }
            
            //米粒初始化
            $MinLi=0;
            $_POST['txtMinMPrice'] = 0; //重置基本米粒
            //第一步：商品信息
            $task->task_type=$_POST['task_type'];//任务类型
            $task->ddlZGAccount=$_POST['ddlZGAccount'];//淘宝掌柜名
            $task->ddlOKDay=$_POST['ddlOKDay'];//要求确认时间
            
            $task->txtPrice= isset($_POST['txtPrice']) ? $_POST['txtPrice'] : 0;//商品价格：(包含邮费)
            $txtMinMPrice=(float)$_POST['txtMinMPrice'];//基本米粒
                $MinLi=$MinLi+$txtMinMPrice;//增加基本米粒
                $_POST['ddlOKDay']!=0?$MinLi=$MinLi+($txtMinMPrice*1.5+($_POST['ddlOKDay']-1)):$MinLi=$MinLi+0;//根据确认时间增加对应米粒
            
            //第二步：增值服务
            $task->cbxIsWW=$_POST['cbxIsWW'];//是否选中旺旺聊天，1选中，0未选中
                $_POST['cbxIsWW']==1?$MinLi=$MinLi+1:$MinLi=$MinLi+0;//选中旺旺聊天米粒基数加1
                
            $task->shopcoller=$_POST['shopcoller'];//是否选中购物收藏，1选中，0未选中
                $_POST['shopcoller']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中购物收藏米粒基数加0.5
                
            //$task->isMobile=$_POST['isMobile'];//是否选中手机订单，1选中，0未选中
                //$_POST['isMobile']==1?$MinLi=$MinLi+2.0:$MinLi=$MinLi+0;//选中手机订单米粒基数加2.0
                
            //$task->cbxIsLHS=$_POST['cbxIsLHS'];//是否选中旺旺收货，1选中，0未选中
               // $_POST['cbxIsLHS']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中旺旺收货米粒基数加0.5
                
            ///$task->isViewEnd=$_POST['isViewEnd'];//是否选中浏览到底，1选中，0未选中
               // $_POST['isViewEnd']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中浏览到底米粒基数加0.5
                
            $task->pinimage=isset($_POST['pinimage']) ? $_POST['pinimage'] : 0;//是否选中好评截图，1选中，0未选中
                //$_POST['pinimage']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中好评截图米粒基数加0.5
                
            $task->stopDsTime=$_POST['stopDsTime'];//是否选中停留时间，1选中，0未选中
            //$task->stopTime=$_POST['stopTime'];//停留时间长度
              
            $task->cbxIsMsg= isset($_POST['cbxIsMsg']) ? $_POST['cbxIsMsg'] : 0;//是否选中好评内容，1选中，0未选中
            $task->txtMessage= isset($_POST['txtMessage']) ? $_POST['txtMessage'] : '';//好评内容
                //$_POST['cbxIsMsg']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中好评内容米粒基数加0.5
                
            $task->cbxIsTip=$_POST['cbxIsTip'];//是否选中留言提醒，1选中，0未选中
            $task->txtRemind=$_POST['txtRemind'];//留言提醒内容
            
            
            //第三步：筛选接手
            $task->cbxIsAudit=$_POST['cbxIsAudit'];//是否选中审核接手，1选中，0未选中
                $_POST['cbxIsAudit']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中审核接手米粒基数加0.5
                
            $task->isReal=$_POST['isReal'];//是否选中实名认证，1选中，0未选中
                $_POST['isReal']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中实名认证米粒基数加0.5
                
            $task->cbxIsSB=$_POST['cbxIsSB'];//是否选中商保用户，1选中，0未选中
                $_POST['cbxIsSB']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中商保用户米粒基数加0.5
                
            $task->cbxIsFMaxMCount=$_POST['cbxIsFMaxMCount'];//是否选中限制接手，1选中，0未选中
                if($_POST['cbxIsFMaxMCount']==1)//根据限制接手要求增加对应的米粒
                {
                    $fmaxmc = $_POST['fmaxmc_d'].'@'.$_POST['fmaxmc_w'].'@'.$_POST['fmaxmc_m'];
                    $task->fmaxmc=$fmaxmc;//限制的具体要求
                }
            
                
            $task->isLimitCity=$_POST['isLimitCity'];//是否选中指定区域，1选中，0未选中
                $_POST['isLimitCity']==1?$MinLi=$MinLi+2:$MinLi=$MinLi+0;//选中指定区域米粒基数加2
                
            $task->Province=$_POST['Province'];//限制接手所属区域
            $task->is_xzqx_type = isset($_POST['is_xzqx_type']) ? $_POST['is_xzqx_type'] : 0;
            
            $task->isBuyerFen=$_POST['isBuyerFen'];//是否选中限制等级，1选中，0未选中
                if($_POST['isBuyerFen']==1)//根据限制接手要求增加对应的米粒
                {
                    switch($_POST['BuyerJifen'])
                    {
                        case 1://一心及以上增加0.5米粒
                            $MinLi=$MinLi+0.5;
                            break;
                        case 2://二心及以上 增加1.0米粒
                            $MinLi=$MinLi+1; 
                            break;
                        case 3://三心及以上增加2.0米粒
                            $MinLi=$MinLi+2;
                            break;
                        case 4://四心及以上增加3.0米粒
                            $MinLi=$MinLi+3; 
                            break;
                        case 5://五心及以上增加4.0米粒
                            $MinLi=$MinLi+4;
                            break;
                        case 6://一钻及以上增加5.0米粒
                            $MinLi=$MinLi+5; 
                            break;
                        case 7://二钻及以上增加6.0米粒
                            $MinLi=$MinLi+6;
                            break;
                        case 8://三钻及以上增加7.0米粒
                            $MinLi=$MinLi+7; 
                            break;
                        case 9://四钻及以上增加8.0米粒
                            $MinLi=$MinLi+8;
                            break;
                        case 10://五钻及以上增加支付9.0米粒
                            $MinLi=$MinLi+9; 
                            break;
                    }
                }
            $task->BuyerJifen=$_POST['BuyerJifen'];//限制接手等级具体要求
            
            $task->filtertasker=$_POST['filtertasker'];//是否选中过滤接手，1选中，0未选中
                $_POST['filtertasker']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中过滤接手米粒基数加0.5
                
            $task->fmaxabc=isset($_POST['fmaxabc'])?$_POST['fmaxabc']:0;//好评率,0表示未选择
            
            $task->fmaxbbc=isset($_POST['fmaxbbc'])!=false?$_POST['fmaxbbc']:0;//接手被拉黑次数不大于,0表示未选择
            
            $task->fmaxbtsc=isset($_POST['fmaxbtsc'])!=false?$_POST['fmaxbtsc']:0;//接手被有效投诉次数不大于,0表示未选择
            
            //第四步：快递空包
            $task->isSign= isset($_POST['isSign']) ? 1 : 0;//是否选中真实签收，1选中，0未选中
                //$_POST['isSign']==1?$MinLi=$MinLi+2:$MinLi=$MinLi+0;//选中真实签收米粒基数加2
                
            $task->cbxIsAddress= isset($_POST['cbxIsAddress']) ? $_POST['cbxIsAddress'] : 0;//是否选中收货地址，1选中，0未选中
                //$_POST['cbxIsAddress']==1?$MinLi=$MinLi+0.5:$MinLi=$MinLi+0;//选中收货地址米粒基数加0.5
                
            //具体的收货地址信息
            $task->cbxIsAddressContent="".$_POST['cbxName'].'|'.$_POST['cbxMobile'].'|'.$_POST['cbxcode'].'|'.$_POST['cbxAddress']."";
            
            $task->isTpl=$_POST['isTpl'];////是否选中收货地址，1选中，0未选中
            $task->tplTo=$_POST['isTpl']==1?"".$_POST['tplTo']."":"0*#";//模板名称如果为0*#则为不保存模板
            $task->MinLi=0;//消耗总米粒-不消耗米粒了
            $MinLi=0;//重置米粒数量
            $taskPublistStatus=0;
            $task->yongjin_money = $_POST['yongjin_money'];
            $task->operate_pt = $_POST['operate_pt'];
            $taskmoreArr=$task->attributes;
            $plConfig = Config::model()->findByPk(1);
            $userinfoDone=User::model()->findByPk(Yii::app()->user->getId());
            //检查用户余额是否充足
            $payMoney = (intval($_POST['txtFCount'])*intval($plConfig->task_price));
            $task_typeUrl='taskPublishPT';
            switch($_POST['taskCatalog'])
            {
                case 0://普通任务
                    $task_typeUrl="taskPublishPT";
                    break;
                case 1://来路任务
                    $task_typeUrl="taskPublishLU";
                    break;
            }
            
            if($payMoney > $userinfoDone->Money)
            {
                $taskPublistStatus =2 ;
                //余额不足，请充值
                $this->redirect($this->createUrl('user/'.$task_typeUrl.'',array('taskPublistStatus'=>$taskPublistStatus,'task_typeUrl'=>$task_typeUrl)));
                exit;
            }
            if($_POST['txtFCount']<2)//单任务
            {
                //前提条件判断一.帐户余额充足，同时米粒充足
//                 if($_POST['txtPrice']>$userinfoDone->Money || $MinLi>$userinfoDone->MinLi)
//                 {
//                     $this->redirect_message('您的余额或者米粒不足','success',3,$this->createUrl('user/index'));
//                     Yii::app()->end();
//                 }
                
                //添加流水
                //1.保存金额流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=3;//发布任务使用金额
                $record->number=intval($plConfig->task_price);//$_POST['txtPrice'];//操作数量--扣除1元
                $record->tasknum=$_POST['txtFCount'];//1个任务
                $record->time=time();//操作时间
                $record->save();//保存金额流水
                
                //2.保存米粒流水
//                 $recordMinLi=new Recordlist();
//                 $recordMinLi->userid=Yii::app()->user->getId();//用户id
//                 $recordMinLi->catalog=4;//发布任务使用米粒
//                 $recordMinLi->number=$MinLi;//操作数量
//                 $recordMinLi->tasknum=$_POST['txtFCount'];//1个任务
//                 $recordMinLi->time=time();//操作时间
//                 $recordMinLi->save();//保存米粒流水
                
                //3.改变充值后帐户的余额
                $userinfoDone->Money=$userinfoDone->Money-1;//$_POST['txtPrice'];//在原有余额基本上减去任务需要的金额
                $userinfoDone->MinLi=$userinfoDone->MinLi-$MinLi;//在原有米粒基本上减去任务需要的米粒
                $userinfoDone->save();
                
                //4.发布任务
                if($task->save())//任务发布成功
                    $taskPublistStatus=1;
                else//任务发布失败
                    $taskPublistStatus=0;
            }else//批量发布任务
            {
                $userinfoDone=User::model()->findByPk(Yii::app()->user->getId());
                //前提条件判断一.帐户余额充足，同时米粒充足
                if((1*$_POST['txtFCount'])>$userinfoDone->Money)
                {
                    $this->redirect_message('您的余额不足','success',3,$this->createUrl('user/index'));
                    Yii::app()->end();
                }
                //添加流水
                //1.保存金额流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=3;//发布任务使用金额
                $record->number=(intval($plConfig->task_price)*$_POST['txtFCount']);//操作数量
                $record->tasknum=$_POST['txtFCount'];//1个任务
                $record->time=time();//操作时间
                $record->save();//保存金额流水
                
                //2.保存米粒流水
//                 $recordMinLi=new Recordlist();
//                 $recordMinLi->userid=Yii::app()->user->getId();//用户id
//                 $recordMinLi->catalog=4;//发布任务使用米粒
//                 $recordMinLi->number=$MinLi*$_POST['txtFCount'];//操作数量
//                 $recordMinLi->tasknum=$_POST['txtFCount'];//1个任务
//                 $recordMinLi->time=time();//操作时间
//                 $recordMinLi->save();//保存米粒流水
                
                //3.改变充值后帐户的余额
                $userinfoDone->Money=$userinfoDone->Money-(intval($plConfig->task_price)*$_POST['txtFCount']);//在原有余额基本上减去任务需要的金额
                //$userinfoDone->MinLi=$userinfoDone->MinLi-($MinLi*$_POST['txtFCount']);//在原有米粒基本上减去任务需要的米粒
                $userinfoDone->save();
                
                for($i=0;$i<$_POST['txtFCount'];$i++)
                {
                    $taskMore=new Companytasklist();
                    foreach($taskmoreArr as $k=>$v)
                    $taskMore->$k=$v;
                    $taskMore->save();
                }
                $taskPublistStatus=1;
            }
            
            $this->redirect($this->createUrl('user/'.$task_typeUrl.'',array('taskPublistStatus'=>$taskPublistStatus,'task_typeUrl'=>$task_typeUrl)));
            /*echo "<pre/>";
            var_dump($MinLi);*/
        }
    }
    
    /*
        用户中心-发布任务-普通任务
    */
    public function actionTaskPublishPT()
    {
        $areaList = Area::model()->findAll( array('condition'=>'parentid=0'));
        //获取掌柜号
        $sellerInfo=Blindwangwang::model()->findAll(array(
            'condition'=>'userid='.Yii::app()->user->getId().' and statue=1 and catalog=2 AND is_check=1',
            'select'=>'id,wangwang',
            'order'=>'id desc'
        ));
        //获取模板信息
        $tplList = Companytasklist::model()->findAllByAttributes( array('isTpl'=> 1, 'taskCatalog' => 0, 'publishid'=>Yii::app()->user->getId()) );
        //获取审核通过的商品连接信息
        $linkUrl = Goodsurl::model()->findAllByAttributes(array('uid' => Yii::app()->user->getid(), 'is_check'=>1));
        $this->render('taskPublishPT',array(
            'sellerInfo'=>$sellerInfo,
            'area' => $areaList,
            'tplList' => $tplList,
            'linkUrl' => $linkUrl
        ));
    }
    
    public function actionGetTaskDetail()
    {
        $taskId = intval($_POST['taskId']);
        $tplType = intval($_POST['tplType']);
        $tplName = array('userPtTask','userLlTask');
        $taskInfo = Companytasklist::model()->findByPk($taskId);
        if($taskInfo)
        {
            if($taskInfo->cbxIsFMaxMCount && !empty($taskInfo->fmaxmc))
            {
                $fmaxmc = explode('@', $taskInfo->fmaxmc);
            }else{
                $fmaxmc = false;
            }
            $zdAddress = explode('|', $taskInfo->cbxIsAddressContent);
        }
        //获取模板信息
        $tplList = Companytasklist::model()->findAllByAttributes( array('isTpl'=> 1, 'taskCatalog' => 0, 'publishid'=>Yii::app()->user->getId()) );
        
        //获取掌柜号
        $sellerInfo=Blindwangwang::model()->findAll(array(
            'condition'=>'userid='.Yii::app()->user->getId().' and statue=1 and catalog=2 AND is_check=1',
            'select'=>'id,wangwang',
            'order'=>'id desc'
        ));
        //获取审核通过的商品连接信息
        $linkUrl = Goodsurl::model()->findAllByAttributes(array('uid' => Yii::app()->user->getid(), 'is_check'=>1));        
        $areaList = Area::model()->findAll( array('condition'=>'parentid=0'));
        echo $this->renderPartial($tplName[$tplType], array('taskInfo' =>$taskInfo,'linkUrl' =>$linkUrl,'tplList' => $tplList,'area' => $areaList,'sellerInfo'=>$sellerInfo, 'fmaxmc' => $fmaxmc, 'zdAddress' => $zdAddress), true);
    }
    
    /*
        用户中心-发布任务-来路任务
    */
    public function actionTaskPublishLU()
    {
        $areaList = Area::model()->findAll(array('condition'=>'parentid=0'));
        $sellerInfo=Blindwangwang::model()->findAll(array(
            'condition'=>'userid='.Yii::app()->user->getId().' and statue=1 and catalog=2',
            'select'=>'id,wangwang',
            'order'=>'id desc'
        ));
        //获取模板信息
        $tplList = Companytasklist::model()->findAllByAttributes( array('isTpl'=> 1, 'taskCatalog' => 0, 'publishid'=>Yii::app()->user->getId()) );
        //获取模板信息
        $tplList = Companytasklist::model()->findAllByAttributes( array('isTpl'=> 1,'taskCatalog' => 1, 'publishid'=>Yii::app()->user->getId()) );
        //获取审核通过的商品连接信息
        $linkUrl = Goodsurl::model()->findAllByAttributes(array('uid' => Yii::app()->user->getid(), 'is_check'=>1));
        
        $this->render('taskPublishLU',array(
            'sellerInfo'=>$sellerInfo,
            'area' => $areaList,
            'tplList' => $tplList,
            'linkUrl' =>$linkUrl
        ));
    }
    
    
    /*
        用户中心-发布任务-套餐任务
    */
    public function actionTaskPublishTC()
    {
        $this->render('taskPublishTC');
    }
    
    
    /*
        用户中心-发布任务-购物车任务
    */
    public function actionTaskPublishGWC()
    {
        $this->render('taskPublishGWC');
    }
    
    /*
        用户中心-发布任务-任务模板
    */
    public function actionTaskPublishTemplete()
    {
        $this->render('taskPublishTemplete');
    }
    
    /*
                     用户中心-绑定商品链接
     */
    public function actionGoodsUrlManage()
    {
        //任务大厅
        $criteria = new CDbCriteria;
        $criteria->condition = 'uid='.Yii::app()->user->getId();
        $criteria->order ="create_time desc";
        $count = Goodsurl::model()->count($criteria);
        $pages = new CPagination($count);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        $linkList = Goodsurl::model()->findAll($criteria);
        
        //new
        $linkInof = array();
        $shopList = array();
        if(isset($_GET['id']) && intval($_GET['id']) > 0)
        {
            $linkInof =Goodsurl::model()->findByPk(intval($_GET['id']));
        }
        //获取掌柜号信息
        $shopList = Blindwangwang::model()->findAllByAttributes( array('userid' => Yii::app()->user->getId(), 'catalog'=>2, 'is_check' => 1));
        //$this->render('editGoodsUrl', array('linkInof' => $linkInof, 'shopList' => $shopList) );
    
        
        $this->render('goodsUrlManage', array('linkList' => $linkList,'pages' => $pages,'linkInof' => $linkInof, 'shopList' => $shopList));
    }
    
    public function actionDelGoodsUrl()
    {
        if(isset($_GET['id']) && $_GET['id'] > 0)
        {
            Goodsurl::model()->deleteAllByAttributes(array('uid' => Yii::app()->user->getId(),'id'=>intval($_GET['id']) ) );
        }
        $this->redirect( $this->createUrl('user/goodsUrlManage', array('delResult' => 200)));
    }
    
    /**
     * 编辑商品链接
     */
    public function actionEditGoodsUrl()
    {
        if(!isset($_POST['goods_name']))
        {
            $linkInof = array();
            $shopList = array();
            if(isset($_GET['id']) && intval($_GET['id']) > 0)
            {
                $linkInof =Goodsurl::model()->findByPk(intval($_GET['id']));
            }
            //获取掌柜号信息
            $shopList = Blindwangwang::model()->findAllByAttributes( array('userid' => Yii::app()->user->getId(), 'catalog'=>2, 'is_check' => 1));
            $this->render('editGoodsUrl', array('linkInof' => $linkInof, 'shopList' => $shopList) );
        }else{
            if(isset($_POST) && $_POST['id'] > 0)
            {
                $link = Goodsurl::model()->findByPk(intval($_POST['id']));
                if(isset($link))
                {
                    $link->goods_name = $_POST['goods_name'];
                    $link->shop_id = $_POST['shop_id'];
                    $link->goods_url = $_POST['goods_url'];
                    $link->is_check = 0;
                    $link->save();
                }
            }else{
                $url = new Goodsurl();
                $url->goods_name = $_POST['goods_name'];
                $url->shop_id = $_POST['shop_id'];
                $url->goods_url = $_POST['goods_url'];
                $url->is_check = 0;
                $url->uid = Yii::app()->user->getId();
                $url->create_time = time();
                $url->save();
            }
            $this->redirect($this->createUrl('user/goodsUrlManage', array('addResult' => 200)));
        }
    }
    
    
    /*
        用户中心-维护资料密码
    */
    public function actionUserAccountCenter()
    {
        if(isset($_POST['headImg']))
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            $userinfo->MyPhoto=$_POST['headImg'];//头像
            $userinfo->QQToken=$_POST['qq'];//QQ号码
            $userinfo->TrueName=$_POST['truename'];//真实姓名
            $userinfo->Sex=$_POST['sex'];//性别
            //$userinfo->PlaceOtherLogin=$_POST['PlaceOtherLogin'];//异地登录
            $userinfo->id_card = $_POST['id_card'];
            $userinfo->id_photo_front = $_POST['id_photo_front'];
            $userinfo->id_photo_rear = $_POST['id_photo_rear'];
            $userinfo->alipay_account = $_POST['alipay_account'];
            if($userinfo->save())
            {
                //更新收货地址
                $userAddressInfo = Useraddress::model()->find('uid='.Yii::app()->user->getId());
                if(!$userAddressInfo && isset($_POST['idProvince'])> 0 && isset($_POST['idCity']) > 0 && isset($_POST['idDistrict']) > 0 && !empty($_POST['addr']['mobile']) && !empty($_POST['addr']['address']) && !empty($_POST['addr']['user_name']))
                {
                    $userAddModel = new Useraddress();
                    $userAddModel->uid = Yii::app()->user->getId();
                    $userAddModel->sheng_id = intval($_POST['idProvince']);
                    $userAddModel->shi_id = intval($_POST['idCity']);
                    $userAddModel->qu_id = intval($_POST['idDistrict']);
                    $userAddModel->mobile = $_POST['addr']['mobile'];
                    $userAddModel->address = $_POST['addr']['address'];
                    $userAddModel->user_name = $_POST['addr']['user_name'];
                    $userAddModel->create_time = date('Y/m/d H:i:s');
                    $userAddModel->save();
                }
                //查询是否绑定了
//                 $otherAddr = Useraddress::model()->find('occupy_uid='.Yii::app()->user->getId());
//                 if(!$otherAddr)
//                 {
//                     //没有绑定就自动绑定一个
//                     $targetAddr = Useraddress::model()->find('occupy_uid=0 AND uid != '.Yii::app()->user->getId());
//                     if($targetAddr)
//                     {
//                         $targetAddr->occupy_uid = Yii::app()->user->getId();
//                         $targetAddr->save();
//                     }
//                 }
                echo "SUCCESS";
            }
            else
                echo "FAIL";
            Yii::app()->end();
        }
        else
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            //获取地址信息
            $query = Yii::app()->db->createCommand('select id,name from zxjy_area where parentid=0');
            $areaList = $query->queryAll();
            //获取用户的地址信息
            $userAddressInfo = Useraddress::model()->find('uid='.Yii::app()->user->getId());
            if($userAddressInfo)
            {
                $query = Yii::app()->db->createCommand('select id,name from zxjy_area where parentid='.$userAddressInfo->sheng_id);
                $shiList = $query->queryAll();
                $query = Yii::app()->db->createCommand('select id,name from zxjy_area where parentid='.$userAddressInfo->shi_id);
                $quList = $query->queryAll();
            }else{
                $shiList = $quList = array();
            }
            $this->render('userAccountCenter',array(
                'userinfo'=>$userinfo,
                'area' => $areaList,
                'address' => $userAddressInfo,
                'shi' => $shiList,
                'qu' => $quList
            ));
        }
    }
    
    public function actionGetAreaShiList()
    {
        
    }
    
    public function actionUpdateCities()
    {
        $query = Yii::app()->db->createCommand('select id,name from zxjy_area where parentid='.intval($_POST['idProvince']));
        $data = $query->queryAll();
        $data = CHtml::listData($data,'id','name');
        $dropDownCities = "<option value=''>选择城市</option>";
        foreach($data as $value=>$name)
            $dropDownCities .= CHtml::tag('option', array('value'=>$value),CHtml::encode($name),true);
        $dropDownDistricts = "<option value=''>选择区域</option>";
        echo CJSON::encode(array(
            'dropDownCities'=>$dropDownCities,
            'dropDownDistricts'=>$dropDownDistricts
        ));
    }
    
    public function actionUpdateDistricts()
    {
        $query = Yii::app()->db->createCommand('select id,name from zxjy_area where parentid='.intval($_POST['idCity']));
        $data = $query->queryAll();
        $data = CHtml::listData($data,'id','name');
        echo "<option value=''>选择区域</option>";
        foreach($data as $value=>$name)
            echo CHtml::tag('option', array('value'=>$value),CHtml::encode($name),true);
    }
    
    /*
        用户中心-修改密码
    */
    public function actionChangPassword()
    {
        if(isset($_POST['newpassword']) && $_POST['newpassword']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            $userinfo->PassWord=md5($_POST['newpassword']);//修改密码
            if($userinfo->save())
                echo "SUCCESS";
            else
                echo "FAIL";
        }
        else
            echo "FAIL";
        
    }
    
    
     /*
        用户中心-修改安全操作码
    */
    public function actionChangSafepwd()
    {
        if(isset($_POST['newSafepwd']) && $_POST['newSafepwd']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            $userinfo->SafePwd=md5($_POST['newSafepwd']);//修改安全操作码
            if($userinfo->save())
                echo "SUCCESS";
            else
                echo "FAIL";
        }
        else
            echo "FAIL";
        
    }
    
    /*
        用户找回安全操作码
    */
    public function actionUsergetBackSafePwd()
    {
        $this->renderPartial('usergetBackSafePwd');
    }
    
    /*
        用户中心-检测手机与接收到的验证码是否正确
    */
    public function actionUserCheckPhoneAndCode()
    {
        if(isset($_POST['phone']) && isset($_POST['phoneCode']))
        {
            if($_POST['phoneCode']==Yii::app()->session['code'])
            {
                if($_POST['phone']==Yii::app()->session['phone'])
                {
                    unset(Yii::app()->session['code']);//清除验证码
                    unset(Yii::app()->session['phone']);//清除手机号
                    echo "SUCCESS";
                }
                else
                {
                    echo "PHONEFAIL";
                }
            }else
            {
                echo "CODEFAIL";//验证码不正确
            }
        }
        else
        {
            echo "FAIL";
        }
    }
    
    
    /*
        用户中心-帐号充值
    */
    public function actionUserPayCenter()
    {
        if(isset($_POST['businessRecord']) && $_POST['businessRecord']!="")
        {
            /*echo $_POST['businessRecord'];//交易号
            exit;*/
            //根据交易号进行查找
            $kcbOrder=Kcborder::model()->findByAttributes(array("tno"=>$_POST['businessRecord']));
            if(count($kcbOrder)==1)//交易号存在则进行检查
            {
                if($kcbOrder->status==0 && $kcbOrder->uid==0)//如果存在符合条件
                {
                    $nowNumber=$kcbOrder->money;//本次操作的金额数量，即通过交易号查询出来的匹配金额
                    
                    $currentTime=time();//统一时间
                    
                    //改变当前交易号所在记录的状态
                    $kcbOrder->uid=Yii::app()->user->getId();//用户id
                    $kcbOrder->status=1;//是否完成支付，变状态为1
                    $kcbOrder->completetime=$currentTime;//完成充值时间
                    $kcbOrder->save();//更新记录信息
                    
                    //添加流水
                    $record=new Recordlist();
                    $record->userid=Yii::app()->user->getId();//用户id
                    $record->catalog=1;//充值类型
                    $record->number=$nowNumber;//操作数量
                    $record->time=$currentTime;//操作时间
                    $record->save();//保存流水
                    
                    //改变充值后帐户的余额
                    $userinfo=User::model()->findByPk(Yii::app()->user->getId());
                    $userinfo->Money=$userinfo->Money+$nowNumber;//在原有的基本上增加本次操作的金额数量
                    $userinfo->save();
                    
                    echo "SUCCESS";
                    Yii::app()->end();   
                }
                else//已经被使用过
                {
                    echo "EXIST";
                    Yii::app()->end();
                }
            }
            else//该交易号不存在
            {
                echo "NOPNO";
                Yii::app()->end();
            }
            
        }
        $this->render('userPayCenter');
    }
    
    
    /*
        用户中心-帐号充值明细
    */
    public function actionUserPayDetail()
    {
        //查询用户充值记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId().' and (catalog=1 or catalog=7)';
        $criteria->order ="id desc";
    
        //分页开始
        $total = Recordlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Recordlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userPayDetail',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    
    /*
        用户中心-米粒明细
    */
    public function actionUserPayDetailMinLi()
    {
        //查询用户充值记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId().' and (catalog=2 or catalog=9)';
        $criteria->order ="id desc";
    
        //分页开始
        $total = Recordlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Recordlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userPayDetailMinLi',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-任务明细
    */
    public function actionuserPayDetailTask()
    {
        //查询用户充值记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId().' and (catalog=3 or catalog=4 or catalog=5 or catalog=6)';
        $criteria->order ="id desc";
    
        //分页开始
        $total = Recordlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Recordlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userPayDetailTask',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-明细-提现明细
    */
    public function actionUserPayDetailTX()
    {
        //查询用户提现记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId().' and catalog=8';
        $criteria->order ="id desc";
    
        //分页开始
        $total = Recordlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Recordlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userPayDetailTX',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    /*
        用户中心-明细-登录明细
    */
    public function actionUserLoginDetail()
    {
        //查询用户提现记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId();
        $criteria->order ="id desc";
    
        //分页开始
        $total = Loginlog::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Loginlog::model()->findAll($criteria);
        //分页结束
        
        $this->render('userLoginDetail',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    
    /*
        用户中心-申请提现
    */
    public function actionUserCashToBank()
    {
        if(isset($_POST['txMoneyNum']) && $_POST['txMoneyNum']!="" && isset($_POST['bankid']) && $_POST['bankid']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($_POST['txMoneyNum']<$userinfo->Money)//提现金额正常
            {
                //添加流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=8;//提现类型
                $record->number=$_POST['txMoneyNum'];//提现的金额
                $record->time=time();//操作时间
                $record->bankid=$_POST['bankid'];//银行卡id
                $record->txStatus=1;//提现申请中
                $record->save();//保存流水
                
                $userinfo->Money=$userinfo->Money-$_POST['txMoneyNum'];//余额等于原有金额减去提现金额
                if($userinfo->save())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }
            else//提现金额大于余额
                echo "MONEYTOOMATCH";
        }
        else
            $this->render('userCashToBank');
    }
    
    /*
        用户中心-用户提现列表
    */
    public function actionUserTxList()
    {
        //查询用户提现记录
        $criteria = new CDbCriteria;
        $criteria->condition='userid='.Yii::app()->user->getId().' and catalog=8';
        $criteria->order ="id desc";
    
        //分页开始
        $total = Recordlist::model()->count($criteria);
        $pages = new CPagination($total);
        $pages->pageSize=10;//分页大小
        $pages->applyLimit($criteria);
        
        $proreg = Recordlist::model()->findAll($criteria);
        //分页结束
        
        $this->render('userTxList',array(
            'proInfo' => $proreg,
            'pages' => $pages
        ));
    }
    
    
    /*
        用户中心-米粒回收
    */
    public function actionUserMiliToCash()
    {
        if(isset($_POST['MinLi']) && $_POST['MinLi']!="")
        {
            $userinfo=User::model()->findByPk(Yii::app()->user->getId());
            if($userinfo || $userinfo->MinLi>$_POST['MinLi'])
            {
                switch($userinfo->VipLv)
                {
                    case 0:
                        $toMoneyRes=$_POST['MinLi']*0.42;//新手会员0.42元一个米粒
                        break;
                    case 1:
                        $toMoneyRes=$_POST['MinLi']*0.43;//vip1会员0.42元一个米粒
                        break;
                    case 2:
                        $toMoneyRes=$_POST['MinLi']*0.45;//vip2会员0.42元一个米粒
                        break;
                    case 3:
                        $toMoneyRes=$_POST['MinLi']*0.48;//vip3会员0.42元一个米粒
                        break;
                }
                
                //添加流水
                //1.保存米粒回收流水
                $record=new Recordlist();
                $record->userid=Yii::app()->user->getId();//用户id
                $record->catalog=7;//米粒回收类型
                $record->number=$toMoneyRes;//回收后的金额
                $record->MinLi=$_POST['MinLi'];//回收使用的米粒
                $record->time=time();//操作时间
                $record->save();//保存流水
                
                //2.改变回收米粒后帐户的余额与米粒数量
                $userinfo->Money=$userinfo->Money+$toMoneyRes;//在原有余额基本上加米粒回收转换成的金额
                $userinfo->MinLi=$userinfo->MinLi-$_POST['MinLi'];//在原有米粒基础上加上回收用掉的米粒
                $userinfo->save();
                
                echo "SUCCESS";
                Yii::app()->end();
                
            }else
            {
                echo "FAIL";
                Yii::app()->end();
            }
        }
        $this->render('userMiliToCash');
    }
    
    /*
        用户中心-黑名单
    */
    public function actionUserBlackAccountList()
    {
        if(isset($_POST['reason']) && $_POST['reason']!="" && isset($_POST['blackerusername']) && $_POST['blackerusername']!="")
        {
            $blackinfo=Myblackerlist::model()->findByAttributes(array('userid'=>Yii::app()->user->getId(),'blackerusername'=>$_POST['blackerusername']));
            if($blackinfo)//黑名单已存在
            {
                echo "EXIST";
            }
            else
            {
                $newBlack=new Myblackerlist();
                $newBlack->userid=Yii::app()->user->getId();
                $newBlack->blackerusername=$_POST['blackerusername'];
                $newBlack->reason=$_POST['reason'];
                $newBlack->time=time();
                if($newBlack->save())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }
        }
        else
        {
            $criteria = new CDbCriteria;
            $criteria->condition='userid='.Yii::app()->user->getId();
            $criteria->order ="id desc";
        
            //分页开始
            $total = Myblackerlist::model()->count($criteria);
            $pages = new CPagination($total);
            $pages->pageSize=10;//分页大小
            $pages->applyLimit($criteria);
            
            $proreg = Myblackerlist::model()->findAll($criteria);
            //分页结束
            
            $this->render('userBlackAccountList',array(
                'proInfo' => $proreg,
                'pages' => $pages
            ));
        }
    }
    
    /*
        用户中心-删除黑名单
    */
    public function actionUserBlackDel()
    {
        if(isset($_POST['blackid']) && $_POST['blackid']!="")
        {
            $blackInfo=Myblackerlist::model()->findByPk($_POST['blackid']);
            if($blackInfo->userid==Yii::app()->user->getId())
            {
                if($blackInfo->delete())
                    echo "SUCCESS";
                else
                    echo "FAIL";
            }else
            {
                echo "FAIL";
            }
        }
    }
    
    /*
        用户中心-站内提醒
    */
    public function actionUserMessage()
    {
        $this->render('userMessage');
    }
    
    
    
}