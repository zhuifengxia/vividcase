<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * App\User
 *
 * @property integer $UserID 用户ID
 * @property string $UserName 登陆名
 * @property string $UserPsw 密码
 * @property string $FullName 姓名
 * @property integer $PatientCount 患者总数
 * @property integer $ProjectCount 病历总数
 * @property boolean $UserLevel 用户级别
 * @property string $IDCard 第三方登陆的唯一认证ID
 * @property string $DrID 医师执照 现用作冻结账号理由
 * @property boolean $IsFrob 是否禁止登陆
 * @property string $RegTime 注册时间
 * @property string $LastTime 最后登陆时间
 * @property boolean $OrgCount 建立小组数量
 * @property string $LinkPhone 联系电话
 * @property string $LinkMail 邮箱
 * @property string $HospitalName 所属医院/学校名称
 * @property string $PartmentName 科室/专业
 * @property string $DoctorTitle 医生职称
 * @property boolean $Language 语言
 * @property boolean $RegLevel 会员类型（0为普通1为高级）
 * @property string $LinkAddress 联系地址
 * @property string $UserCity 所在城市
 * @property string $Interesting 感兴趣的专业
 * @property string $WebInviter 邀请码（别人邀请注册的）
 * @property boolean $IsLogin 是否已经登陆
 * @property boolean $Isprotect 是否加了密保
 * @property string $LastGpsJing 用户最后登录经度
 * @property string $LastGpsWei 最后登录所在维度
 * @property boolean $UserType 用户类型（0医生；1护士；2医药行业；3医学生；4赔付宝用户；5其他人群;6实习医生；7村医注册；8技师；9药师）
 * @property boolean $IsAnonymous 是否匿名0不是；1是
 * @property boolean $CanSearch 根据用户名搜索0可以；1不可以
 * @property boolean $FuzzySearch 是否允许模糊搜索，0可以；1不可以
 * @property string $UserImage 用户头像
 * @property string $UserSign 个性签名
 * @property boolean $LogicDel 该用户是否被逻辑删除；0没有；1被删除
 * @property string $gamelocation 用户打游戏的位置
 * @property integer $gamescore 游戏得分
 * @property string $NickName 用户昵称
 * @property integer $SingleID 关联singleuser 表的SingleID
 * @property string $SingleRemark 单病种用户昵称
 * @property string $PwdSha sha1加密
 * @property string $userclearpwd 用户明文密码
 * @property string $UserUpdateTime 用户信息修改时间
 * @property boolean $UserSource 0珍立拍用户；1睿医用户；2外部合作；3糖医生；4寻找失去的爱
 * @property string $WebChat 用户微信号
 * @property string $WebGuid web端用户token
 * @property string $EffectiveTime webtoken有效时间
 * @property string $CertificateFile 用户认证证件
 * @property boolean $IsCertification 是否实名认证（0未认证；1等待认证；2认证成功；3认证失败）
 * @property string $InviteCode 用户邀请码
 * @property string $ApproveNumber 医生职业证书编号
 * @property string $School 学校
 * @property string $Major 专业
 * @property string $Degree 学位
 * @property string $StartDate 入学日期
 * @property string $GraduateDate 毕业日期
 * @property string $Teacher 导师
 * @property string $StudentID 学生证编号
 * @property string $RuiYiPwd 睿医密码
 * @property string $RuiyiSalt 睿医加密随机数
 * @property boolean $UserSex 性别，0未选，1男，2女
 * @property boolean $RegType 0普通注册；1手机注册
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserID($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserPsw($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereFullName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User wherePatientCount($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereProjectCount($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserLevel($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIDCard($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereDrID($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIsFrob($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRegTime($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLastTime($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereOrgCount($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLinkPhone($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLinkMail($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereHospitalName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User wherePartmentName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereDoctorTitle($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLanguage($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRegLevel($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLinkAddress($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserCity($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereInteresting($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereWebInviter($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIsLogin($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIsprotect($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLastGpsJing($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLastGpsWei($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserType($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIsAnonymous($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereCanSearch($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereFuzzySearch($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserImage($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserSign($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereLogicDel($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereGamelocation($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereGamescore($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereNickName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereSingleID($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereSingleRemark($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User wherePwdSha($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserclearpwd($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserUpdateTime($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserSource($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereWebChat($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereWebGuid($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereEffectiveTime($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereCertificateFile($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereIsCertification($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereInviteCode($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereApproveNumber($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereSchool($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereMajor($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereDegree($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereStartDate($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereGraduateDate($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereTeacher($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereStudentID($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRuiYiPwd($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRuiyiSalt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereUserSex($value)
 * @method static \Illuminate\Database\Query\Builder|\App\User whereRegType($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
}
