<?php namespace Modules\Vividcase\Entities;
   
use Illuminate\Database\Eloquent\Model;

class users extends Model {

    protected $fillable = [];
    protected $primaryKey    =   'UserID';
    public $timestamps  = false;

    /**
     * 修改环信密码
     * @param $userid 用户ID
     * @param $userpwd 用户密码
     * @return int 是否修改成功
     */
    public function huanxinimupdate($userid,$userpwd){

        return 0;
    }

    /**
     * 新用户信息注册到环信
     * @param $userid 用户ID
     * @param $userpwd 用户密码
     * @return int 注册是否成功
     */
    public function huanxinimuser($userid,$userpwd){
        return 0;
    }

    /**
     *获得当前用户的地理位置
     */
    public function getuserlocation(){

    }

    /**
     * 给目标用户生成邀请码
     * @param $userid 用户ID
     */
    public function userinvitecode($userid){

    }

    /**
     * 将新用户同步到医学网站
     * @param $username  用户名
     * @param $userpwd   用户密码
     * @param $useremail 用户邮箱
     */
    public function skininsert($username,$userpwd,$useremail){

    }

    /**
     * 注册用户强制订阅的头条媒体类型
     * @param $userid 注册用户
     */
    public function mandatorysubscribe($userid){

    }
}