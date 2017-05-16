<?php namespace Modules\Vividcase\Http\Controllers;

use App\Http\Requests\Request;
use Pingpong\Modules\Facades\Module;
use Pingpong\Modules\Routing\Controller;
use Modules\Vividcase\Entities\Users;
use Modules\Vividcase\Entities\Meditoken;
use Modules\Vividcase\Entities\Usercertify;
use Response;
use Exception;
use DB;
use Mail;
/**
 * 医库用户版块相关接口
 * User: 刘单风
 * DateTime: 2016/5/26 14:39
 * CopyRight：医库软件PHP小组
 */
class UsersController extends Controller {
    /**
     * 医库账号登录接口
     * @param $username 用户名
     * @param $userpwd 密码
     * @return json
     */
	public function login( $username , $userpwd ){

        //验证该用户是否存在
        $user   =   users::where('UserName',$username)
                    ->orwhere('LinkPhone',$username)->first();
        if( count($user)>0  ){

            $userModel   =   new users();

            //睿医密码加密随机字符串
            $suijistr=$user['RuiyiSalt'];
            $newuserpwd=hash("sha256",$suijistr."@".$userpwd);//睿医密码的加密方式
            $user   =   users::where('UserID',$user['UserID'])
                ->where(function($query) use ($userpwd,$newuserpwd){
                    $query->where('UserPsw',md5($userpwd))
                        ->orwhere('RuiYiPwd',$newuserpwd);
                })->first();
            if(count($user)<=0){
                //医库数据库中没找到此账号；到睿医数据库检索
                if( is_numeric( $username ) ){ //手机号调用
                    $ruiyiurl="http://accounts.i-md.com/thirdparty/Mobile/$username/".$userpwd;
                }
                else {//用户名调用
                    $ruiyiurl="http://accounts.i-md.com/thirdparty/Username/$username/".$userpwd;
                }

                //睿医登陆校验
                $ch = curl_init($ruiyiurl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
                $res=curl_exec($ch);
                $ruiyidata=json_decode($res,true);

                if( empty($ruiyidata['id'])) {
                    $return  = array(
                        "status"=>1,
                        "msg"=>"登陆失败",
                        "data"=>""
                    );
                    return Response::json($return);
                } else{

                    try{
                        $userModel   =   new users();
                        //同时修改环信密码
                        $imresult=$userModel->huanxinimupdate($user['UserID'],$userpwd);
                    } catch (Exception $e) {}
                }
            }else{

                try{
                    //同时修改环信密码
                    $imresult=$userModel->huanxinimupdate($user['UserID'],$userpwd);
                } catch (Exception $e) {}
            }
            try {
                if((!empty($user['RuiYiPwd']) && empty($user['UserPsw']))||$user['UserSource']!=0) {
                    //说明是睿医账号/其他合作方登陆，需要同步到环信中
                    $userModel->huanxinimuser($user['UserID'],$userpwd);
                }
            } catch (Exception $e) {

            }

            //以前老用户可能没有昵称，就将用户名作为昵称同步过来
            if(empty($user['NickName'])) {
                $nickname =   $user['UserName'];
            } else {
                $nickname =   $user['NickName'];
            }
            $user->NickName =   $nickname;

            //之前没有保存用户密码
            if(empty($user['userclearpwd'])) {
                $user->userclearpwd =$userpwd;
            }

            //如果密码不相同，则需要修改环信密码（睿医用户）
            if($user['UserPsw']!=md5($userpwd)&&$user['UserSource']==1) {

                //密码为空，说明是睿医用户第一次登陆；保存密码
                if(empty($user['UserPsw'])) {
                    $user->UserPsw =md5($userpwd);
                }
                try {
                    //同时修改环信密码
                    $imresult=$userModel->huanxinimupdate($user['UserID'],$userpwd);
                } catch (Exception $e) {}
            }

            //如果用户所在城市数据没有，此次登陆进行采集
            if(empty($user['UserCity'])) {

                $iplocation =   $userModel->getuserlocation();
                $user->UserCity =$iplocation;
            }
            $user->LastTime         =date('Y-m-d H:i:s');
            $user->UserUpdateTime   =date('Y-m-d H:i:s');

            $user->save();

            //同时返回新的身份认证
            $usertoken =   meditoken::where(['UserID'=>$user['UserID'],'UserType'=>0])->first();

            //更新该用户颁发身份认证，并更新/存入数据库中,md5(用户名+id+当前时间)为身份认证号
            $usernewtoken=md5($user['UserName'].$user['UserID'].time());

            if(count($usertoken)>0) {
                $usertoken->UserToken   =   $usernewtoken;
                $usertoken->save();
            } else {
                $usertoken  =   new meditoken();
                $usertoken->setRawAttributes([
                   'UserID'    =>  $user['UserID'],
                   'UserToken' =>  $usernewtoken,
                   'UserType'  =>  0
                 ]);
                $usertoken->save();
            }
            if(empty($user['InviteCode'])) {
                //给用户生成邀请码
                $userModel->userinvitecode($user['UserID']);
            }

            $return = array(
                "status"=>0,
                "msg"=>"登陆成功",
                "data"=>array("user_id"=>$user['UserID'],"username"=>$user['UserName'],"usertoken"=>$usernewtoken,"nickname"=>$nickname)
            );

        }else{

            try {
                if(preg_match("^[0-9]*$", $username))//手机号调用
                {
                    $ruiyiurl="http://accounts.i-md.com/thirdparty/Mobile/$username/".$userpwd;
                }
                else//用户名调用
                {
                    $ruiyiurl="http://accounts.i-md.com/thirdparty/Username/$username/".$userpwd;
                }
                //睿医登陆校验
                $ch = curl_init($ruiyiurl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取数据返回
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 在启用 CURLOPT_RETURNTRANSFER 时候将获取数据返回
                $res=curl_exec($ch);
                $ruiyidata=json_decode($res,true);
                if(!empty($ruiyidata))//睿医数据库有该用户信息
                {
                    $usercer=0;
                    if($ruiyidata['userType'] == "Doctor" && $ruiyidata['userVerifyInfo']['baseInfoVerified']==true) {
                        $usercer=2;
                    }
                    $userModel  =   new users();
                    $iplocation=$userModel->getuserlocation();
                    $datetime=date('Y-m-d H:i:s');
                    $userModel->setRawAttributes([
                        'UserName'=>$ruiyidata['mobile'],
                        'FullName'=>$ruiyidata['realname'],
                        'NickName'=>$username,
                        'UserPsw'=>md5($userpwd),
                        'PwdSha'=>sha1(1),
                        'LinkPhone'=>$ruiyidata['mobile'],
                        'LinkMail'=>$ruiyidata['email'],
                        'RegTime'=>$datetime,
                        'LastTime'=>$datetime,
                        'PartmentName'=>'',
                        'UserType'=>$usercer==2?0:3,
                        'userclearpwd'=>$userpwd,
                        'UserUpdateTime'=>$datetime,
                        'UserCity'=>$iplocation,
                        'RegType'=>1,
                        'IsCertification'=>$usercer,
                        'UserSource'=>1
                    ]);

                    $userModel->save();
                    $uid = $userModel->UserID;
                    if($usercer==2) {
                        $cerdata = usercertify::where('UserID',$uid);
                        //睿医用户直接为已认证用户
                        if(count($cerdata)<=0){
                            $usercertifyModel = new usercertify();
                            $usercertifyModel->setRawAttributes([
                                'UserID'        =>$uid,
                                'CertifyTime'   =>$datetime,
                                'AgreedTime'    =>$datetime,
                                'AgreedMsg'     =>'恭喜您完成实名认证！',
                                'UserType'      =>0,
                                'IsCertification'=>2
                            ]);
                            $usercertifyModel->save();
                        }
                    }
                    //给用户生成邀请码
                    $userModel->userinvitecode($uid);
                    //同时分配用户认证token
                    $ustokModel=new  meditoken();
                    //给该用户颁发身份认证，并更新/存入数据库中,md5(用户名+id+当前时间)为身份认证号
                    $usernewtoken=md5($username.$uid.time());
                    $ustokModel->setRawAttributes([
                        'UserID'=>$uid,
                        'UserToken'=>$usernewtoken,
                        'UserType'=>0
                    ]);
                    $ustokModel->save();

                    $ustokModel->setRawAttributes([
                        'UserID'=>$uid,
                        'UserToken'=>$usernewtoken,
                        'UserType'=>0
                    ]);
                    $ustokModel->save();
                    //同时插入到皮肤科、骨科数据库中
                    $skinres=$userModel->skininsert($username,$userpwd,"");

                    //同时注册到环信
                    $imresult=$userModel->huanxinimuser($uid,$userpwd);

                    //注册用户强制订阅
                    $userModel->mandatorysubscribe($uid);

                    $return = array(
                        "status"=>0,
                        "msg"=>"登陆成功",
                        "data"=>array("user_id"=>$uid,"username"=>$username,"usertoken"=>$usetoke,"nickname"=>$username,"imresult"=>$imresult)
                    );
                }
                else {
                    //提示登录失败
                    $return = array(
                        "status"=>1,
                        "msg"=>"登陆失败",
                        "data"=>"youwenti"
                    );
                }
            } catch(Exception $e) {
                $return = array(
                    "status"=>1,
                    "msg"=>"failed",
                    "data"=>""
                );
            }
        }
        return Response::json($return);
	}

    /**
     * 检测手机号是否注册/绑定过
     * @param $userphone  手机号
     * @param int $userid 用户ID
     * @return json
     */
    public function isphonereg($userphone,$userid=0){

        try{
            //检索手机号是否存在
            $user = users::where(function($query) use($userphone){
                $query->where('UserName',$userphone)
                    ->orwhere('LinkPhone',$userphone);
                })
                ->where('UserID','!=',$userid)->first();

            if(count($user)<=0){
                $return=array(
                    "status"=>0,
                    "msg"=>"yes"
                );
            }else {//该手机号已经注册过，不能再使用
                $return=array(
                    "status"=>3,
                    "msg"=>"no"
                );
            }
        }catch (Exception $e){
            $return=array(
                "status"=>2,
                "msg"=>"filed"
            );
        }

        return Response::json($return);
    }


    /**
     * 手机注册
     * @param string $userphone 手机号
     * @param string $nickname 昵称
     * @param string $phonepwd 密码
     * @param int $usertype 用户类型(0医生；1护士；2医药从业人员；3学生；4赔付宝；5其他；7村医注册；8技师；9药师)
     * @param string $userdep 用户科室
     * @param string $webinviter 邀请码
     * @param string $useremail 用户邮箱
     * @param string $userinterest 感兴趣专业
     * @param string $userwebchat 用户微信号
     */
    public function phoneregister(){
        if( empty(post('userphone')) || empty(post('nickname')) || empty(post('phonepwd')) ){
            $return  = array(
                "status"=>1,
                "msg"=>"no parameter"
            );
            return Response::json($return);
        }
        //检测手机号是否存在
        $user = users::where('UserName',post('userphone'))
            ->orwhere('LinkPhone',post('userphone'))->first();

        if(count($user)<=0){
            $nowtime = date('Y-m-d H:i:s');
            $userModel = new users();
            //城市定位
            $iplocation=$userModel->getuserlocation();
            $insertdata=[
                'UserName'  =>post('userphone'),
                'FullName'  =>post('userphone'),
                'LinkPhone' =>post('userphone'),
                'UserPsw'   =>md5(post('phonepwd')),
                'RegTime'   =>$nowtime,
                'LastTime'  =>$nowtime,
                'IsFrob'    =>'0',
                'NickName'  =>empty(post('nickname'))?"用户昵称":post('nickname'),
                'userclearpwd'=>post('phonepwd'),
                'UserUpdateTime'=>$nowtime,
                'UserType'=>post('usertype'),
                'PartmentName'=>post('userdep'),
                'Interesting'=>post('userinterest'),
                'WebChat'=>post('userwebchat'),
                'WebInviter'=>post('webinviter'),
                "UserCity"=>$iplocation,
                "RegType"=>1
            ];

            if(!empty(post('useremail')))//邮箱不为空的情况下要判断邮箱是否重复
            {
                $emailuser = users::where('LinkMail',post('useremail'))->first();

                if(count($emailuser)<=0){ //说明邮箱没有重复
                    $insertdata["LinkMail"]=post('useremail');
                }
            }
            $userModel->setRawAttributes($insertdata);
            try{
                //数据插入到数据库中
                $userModel->save();
                $newuserid = $userModel->UserID;
                //给用户生成邀请码
                $userModel->userinvitecode($newuserid);

                //同时注册到环信
                $imresult=$userModel->huanxinimuser($newuserid,post('phonepwd'));

                //注册用户强制订阅
                $userModel->mandatorysubscribe($newuserid);

                $ustokModel=new  meditoken();
                //给该用户颁发身份认证，并更新/存入数据库中,md5(用户名+id+当前时间)为身份认证号
                $usernewtoken=md5(post('userphone').$newuserid.time());
                $ustokModel->setRawAttributes([
                    'UserID'=>$newuserid,
                    'UserToken'=>$usernewtoken,
                    'UserType'=>0
                ]);
                $ustokModel->save();

                //同时插入到皮肤科、骨科数据库中
                $skinres=$userModel->skininsert(post('userphone'),post('phonepwd'),"");

            }catch (Exception $e){

            }

            $return=array(
                "status"=>0,
                "msg"=>"insert success",
                "data"=>array("uid"=>$newuserid,"usertoken"=>$usernewtoken)
            );

        }else {
            $return=array(
                "status"=>2,
                "msg"=>"duplicate name"
            );
        }
        return Response::json($return);
    }

    /**
     *保存游戏分数
     * @param int $userid 用户ID
     * @param string $usertoken 用户token
     * @param string $userlocation 用户所在位置
     * @param string $userscord 游戏得分
     * @param int $usercorrect 游戏正确率
     * @param string $userdep 游戏科室
     */
    public function savegamescord(){
        //参数验证
        if(empty(post('userid'))||empty(post('usertoken'))||empty(post('userdep'))||empty('usercorrect')||empty('userscord'))
        {
            $return  = array(
                "status"=>1,
                "msg"=>"no parameter"
            );
            return Response::json($return);
        }
        try{
            $data = [
                'UserID'        =>post('userid'),
                'UserLocation'  =>post('userlocation'),
                'UserScore'     =>post('userscord'),
                'UserCorrect'   =>post('usercorrect'),
                'UserDep'       =>post('userdep')
            ];
            //查询当前用户的目标科室的成绩是否保存过
            $scoure = DB::table('usergamescore')->select('UserID')
                ->where(['UserID'=>post('userid'),'UserDep'=>post('userdep')])->first();

            if(count($scoure)<=0){
                //没有保存就insert
                DB::table('usergamescore')->insert($data);
            }else{
                //如果此次分数高于数据库保存，则覆盖保存成绩
                if(post('userscord')>$scoure['UserScore']){
                    DB::table('usergamescore')->where(['UserID'=>post('userid'),'UserDep'=>post('userdep')])
                        ->update($data);
                }
            }
            $return  = array(
                "status"=>0,
                "msg"=>"success"
            );
        }catch (Exception $e){
            $return  = array(
                "status"=>1,
                "msg"=>"failed"
            );
        }
        return Response::json($return);
    }


    /**
     * 获得游戏排名top10
     * @param $userid  用户ID
     * @param $usertoken 用户token
     * @return json
     */
    public function getgametop($userid,$usertoken){

        try{
            $return  = array(
                "status"=>1,
                "msg"=>"failed"
            );
        }catch (Exception $e){
            $return  = array(
                "status"=>1,
                "msg"=>"failed"
            );
        }
        return Response::json($return);
    }

    public function testemail()
    {
        $data = ['email'=>'2805597305@qq.com', 'name'=>'ldf', 'activationcode'=>'aaaaa'];
        Mail::send('emailview.mail', $data, function($message) use($data)
        {
            $message->to($data['email'], $data['name'])->subject('欢迎注册我们的网站，请激活您的账号！');
        });
        exit;
    }

}