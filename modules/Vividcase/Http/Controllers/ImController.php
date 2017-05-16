<?php namespace Modules\Vividcase\Http\Controllers;


use GuzzleHttp\Client;
use Pingpong\Modules\Routing\Controller;
use DB;
use Response;
use Modules\Vividcase\Entities\meditoken;
/**
 * IM版块相关接口
 * Class ImController
 * @package Modules\Vividcase\Http\Controllers
 */
class ImController extends Controller
{

	protected $_imdb;
	protected $_db;

	public function __construct()
	{
		$this->_imdb = DB::connection('im_mysql');
		$this->_db = DB::connection('mysql');

	}

	/**
	 * 根据传过来很多userid得到每个用户的用户信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param json $useridjson 用户id列表信息
	 */
	public function getalluserdetail($userid,$usertoken,$userjsonid)
	{
		$userjsonid = json_decode($userjsonid,true);
		$query = $this->_imdb->table('medifriends')
			->select('MediMsg')
			->where('DataType',0)
			->where('MasterID',$userid)
			->where('FriendID',[]);
		foreach ($userjsonid as $u)
		{
			if(strpos($_SERVER['HTTP_USER_AGENT'],"iPhone"))
			{
				$queryuser = $this->_db->table('users')
					->select('UserID','UserName','NickName','UserImage','IsCertification')
					->where('UserID',$u);
				$msg = $query->setBindings($u)->first();
			}
			else
			{
				$version=explode("(",$_SERVER['HTTP_USER_AGENT']);
				$version=explode("/",$version[0]);
				if($version[1]>="3.8.5")
				{
					$queryuser = $this->_db->table('users')
						->select('UserID','UserName','NickName','UserImage','IsCertification')
						->where('UserID',$u);
					$msg = $query->setBindings($u)->first();
				}
				else
				{
					$queryuser = $this->_db->table('users')
						->select('UserID','UserName','NickName','UserImage','IsCertification')
						->where('UserID',$u['userid']);
					$msg = $query->setBindings($u['userid'])->first();
				}
			}
			$user = $queryuser->get();

			$user->MediMsg = $msg->MediMsg??'';
			$return=array(
				'status'=>0,
				'msg'=>'success',
				'userdata'=>$user
			);

			return Response::json($return);
		}

	}

	public function backdeluserbyim($userid,$usertoken)
	{

	}


	/**
	 * IM根据用户名得到用户ID
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $nickname 用户昵称
	 */
	public function getuseridbynickname($userid, $usertoken, $nickname)
	{
		$useridlst = $this->_db->table('users')
			->select('UserID','UserName','UserImage','NickName')
			->where('UserName',$nickname)
			->orWhere('NickName','like',"%$nickname%")
			->get();
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"userlst"=>$useridlst
		);
		return Response::json($return);
	}

	/**
	 * IM保存用户的经纬度信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $jingdu 用户经度
	 * @param string $weidu 用户纬度
	 */
	public function userlocation($userid,$usertoken,$jingdu,$weidu)
	{
		$this->_db->table('users')
			->where('UserID',$userid)
			->update(['LastGpsJing'=>$jingdu,'LastGpsWei'=>$weidu]);
		$return=array(
			"status"=>0,
			"msg"=>"success"
		);
		return Response::json($return);
	}

	/**
	 *计算某个经纬度的周围某段距离的正方形的四个点
	 *
	 *@param lng float 经度
	 *@param lat float 纬度
	 *@param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
	 *@return array 正方形的四个点的经纬度坐标
	 */
	function returnSquarePoint($lng, $lat,$distance = 0.5)
	{
		$earthdata=6371;//地球半径，平均半径为6371km
		$dlng =  2 * asin(sin($distance / (2 * $earthdata)) / cos(deg2rad($lat)));
		$dlng = rad2deg($dlng);

		$dlat = $distance/$earthdata;
		$dlat = rad2deg($dlat);
		$arr=array(
			'left-top'=>array('lat'=>$lat + $dlat,'lng'=>$lng-$dlng),
			'right-top'=>array('lat'=>$lat + $dlat, 'lng'=>$lng + $dlng),
			'left-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng - $dlng),
			'right-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng + $dlng)
		);
		return $arr;
	}

	public function getnearuser($userid,$usertoken,$jingdu,$weidu,$cpage=1)
	{
		$start = ($cpage-1)*10;
		//使用此函数计算得到结果后，带入sql查询。
		$squares = $this->returnSquarePoint($jingdu,$weidu,5);
		$userdata = $this->_db->table('users')
			->select('UserName','UserID','NickName','UserImage')
			->where('LastGpsWei','<>',0)
			->where('LastGpsWei','>',$squares["right-bottom"]["lat"])
			->where('LastGpsWei','<',$squares["right-bottom"]["lng"])
			->skip($start)
			->take(20)
			->get();
		//同时更新该用户的坐标位置
		$this->_db->table('users')
			->where('UserID',$userid)
			->update(['LastGpsJing'=>$jingdu,'LastGpsWei'=>$weidu]);
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"userdata"=>$userdata
		);
		return Response::json($return);
	}
	/**
	 * 根据科室查找好友
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $depname 科室名称
	 */
	public function getuserlstbydep($userid,$usertoken,$depname,$cpage)
	{
		$start=($cpage-1)*20;
		//根据科室进行模糊搜索查询符合条件的用户信息
		$userlst = $this->_db->table('users')
			->select('UserName','UserID','NickName','UserImage')
			->where('PartmentName','like',"%$depname%")
			->where('UserName','<>','admin')
			->skip($start)
			->take(20)
			->get();
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"userdata"=>$userlst
		);
		return Response::json($return);
	}

	/**
	 * 根据手机端传过来的用户通讯录手机号集合查出已经注册珍立拍的账号用户集合
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param json $lstphone 手机号集合
	 * @param json $lstname 手机号姓名集合
	 */
	public function addressbookusers($userid,$usertoken,$lstphone,$lstname)
	{
		$lstphone=json_decode($lstphone,true);
		$lstname=json_decode($lstname,true);
		foreach($lstphone as $k=>$p)
		{
			$sql="SELECT UserID,UserName,NickName,UserImage FROM users WHERE UserName='".$lstphone[$i]."' OR LinkPhone='".$lstphone[$i]."'";
			$phonedata=$db->query($sql)->fetchAll();
			$phonedata = $this->_db->table('users')
				->select('UserID','UserName','NickName','UserImage')
				->where('UserName',$p)
				->orWhere('LinkPhone',$p)
				->first();
			$userdata=array(
				'UserPhone'=>$p,
				'UserID'=>$phonedata->UserID,
				'UserName'=>$phonedata->UserName,
				'NickName'=>$phonedata->NickName,
				'UserImage'=>$phonedata->UserImage,
				'PhoneName'=>$lstname[$k]
			);

		}
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"userdata"=>$userdata
		);
		return Response::json($return);
	}

	/**
	 * 取得换信token
	 * @return mixed
	 */
	public function getHxToken()
	{
		$client = new Client();
		$uri = 'https://a1.easemob.com/medicool/medicool/token';
		$r = $client->request('GET',$uri,
			[
				'verify' => false,
				'query' =>[
					'grant_type'=>'client_credentials',
					'client_id'=>'YXA6KbK5oBB-EeSIru0FxQU0NA',
					'client_secret'=>'YXA6ClAk00YWbcahIreG_mZP6khCKpA']
			])->getBody()->getContents();
		$hxtokenarray = json_decode($r,TRUE);
		return $hxtokenarray['access_token'];
	}
	/**
	 * 获取好友列表接口
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 */
	public function getuserfriends($userid,$usertoken)
	{
		$client = new Client();
		$token = $this->getHxToken();
		$uri = "https://a1.easemob.com/medicool/medicool/users/".$userid."/contacts/users";
		$header = 'Bearer '.$token;
		$res = $client->request('GET',$uri,[
			'verify' => false,
			'headers'=>['Authorization'=>$header]])->getBody()->getContents();
		$friendsarray = json_decode($res,TRUE);
//		var_dump($friendsarray);

		if (count($friendsarray['data']) > 0)
		{
			$cfriends = [];//现在的所有好友id
			foreach($friendsarray['data'] as $k=>$f)
			{
				$cfriends[] = $f;
				//更新数据库
				$data = [
					'MasterID'=>$userid,
					'FriendID'=>$f,
					'DataType'=>0
				];
				$res = $this->_imdb->table('medifriends')
					->where('MasterID',$userid)
					->where('FriendID',$f)
					->first(['FriendID']);
				if (count($res)<=0)
				{
					$this->_imdb->table('medifriends')->insert($data);
				}
				else
				{
					$this->_imdb->table('medifriends')->update(['DataType'=>0]);
				}
				//取得好友信息
				$userdata = $this->_db->table('users')
					->select('UserID','UserName','NickName','UserImage','IsCertification')
					->where('UserID',$f)
					->first();
				if (count($userdata)>0)
				{
					$userdata->NickName = $userdata->NickName??'用户昵称';
					//取得好友备注
					$msg = $this->_imdb->table('medifriends')
						->where('DataType',0)
						->where('MasterID',$userid)
						->where('FriendID',$f)
						->first(['MediMsg']);
					$userdata->Msg = $msg->MediMsg;

				}

			}
			if (count($cfriends) > 0)
			{
				$this->_imdb->table('medifriends')
					->where('MasterID',$userid)
					->where('DataType',0)
					->whereNotIn('FriendID',$cfriends)
					->delete();
			}

		}
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"data"=>$userdata
		);
	}
	/**
	 * 用户查询一个用户id的详细信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $seluserid 要查询的用户ID
	 */
	public function getusermsg($userid,$usertoken,$seluserid)
	{
		$res = $this->_imdb->table('users')
			->select('UserID','UserName','NickName','UserType','PartmentName','LinkMail','LinkPhone','UserImage','WebChat','IsCertification','HospitalName','DoctorTitle','UserSign')
			->where('UserID',$seluserid)
			->first();
		$res->LinkPhone = substr_replace($res->LinkPhone,'****',3,4);
		$res->LinkMail = substr_replace($res->LinkMail,'****',3,4);
		$msg= $this->_imdb->table('medifriends')
			->where('DataType',0)
			->where('MasterID',$userid)
			->where('FriendID',$seluserid)
			->first(['MediMsg']);
		$res->MediMsg = $res->MediMsg??'';
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"data"=>$res
		);
	}
	/**
	 * 用户自定义好友备注名
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $friendid 好友ID
	 * @param string $remarkname 备注名
	 * @param int $datatype 0好友备注；1群名片修改；2自定义备注群组成员名
	 * @param int $groupid 群组id
	 */
	public function setremarkname($userid,$usertoken,$friendid,$remarkname,$datatype,$groupid)
	{
		$data = [
			'FriendID'=>$friendid,
			'MasterID'=>$userid,
			'MediMsg'=>$remarkname,
		];
		switch ($datatype)
		{
			case 0:
			case 2:
				$res = $this->_imdb->table('medifriends')
					->where('FriendID',$friendid)
					->where('MasterID',$userid)
					->count('MediMsg');
			//本人和群成员在本地数据库是否存在关系，不是就插入，是就更新
				if ($res<=0)
				{
					$data['DataType']=$datatype==0?0:1;
					$this->_imdb->table('medifriends')->insert($data);
				}
				else
				{
					$this->_imdb->table('medifriends')
						->where('FriendID',$friendid)
						->where('MasterID',$userid)
						->update(['MediMsg'=>$remarkname]);
				}
				break;

			case 1:
				$res = $this->_imdb->table('medigroupmember')
					->where('GroupNum',$groupid)
					->where('MemberID',$userid)
					->count('MediMsg');
				if ($res <= 0)
				{
					$data = [
						'MemberID'=>$friendid,
						'GroupNum'=>$groupid,
						'MediMsg'=>$remarkname,
					];
					$this->_imdb->table('medigroupmember')->insert($data);
				}
				else
				{
					$this->_imdb->table('medigroupmember')
						->where('GroupNum',$groupid)
						->where('MemberID',$userid)
						->update(['MediMsg'=>$remarkname]);
				}
				break;
		}
		$return=array(
			"status"=>0,
			"msg"=>"success",
		);
	}

	/**
	 * 获取群组列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json 返回当前用户参与的群组列表集合,以及成员列表信息
	 */
	public function getgrouplst($userid,$usertoken,$groupid)
	{
		$client = new Client();
		$token = $this->getHxToken();
		$uri = "https://a1.easemob.com/medicool/medicool/users/".$userid."/joined_chatgroups";
		$header = 'Bearer '.$token;
		$res = $client->request('GET',$uri,[
			'verify' => false,
			'headers'=>['Authorization'=>$header]])->getBody()->getContents();
		$groupsarray = json_decode($res,TRUE);
		$return = $groupsarray['data'];
		//遍历得到群组详细信息
		foreach ($return as $key=>$val)
		{
			$uri = "https://a1.easemob.com/medicool/medicool/chatgroups/".$val['groupid'];
			$res = $client->request('GET',$uri,[
				'verify' => false,
			])->getBody()->getContents();
			$resarray = json_decode($res,TRUE);
			$return[$key]['affiliations_count'] = $resarray['data'][0]['affiliations_count'];//群组人数
			$return[$key]['owner'] = array();//群主
			$return[$key]['member'] = array();//群组成员
			foreach ($resarray['data'][0]['affiliations'] as $k=>$value)
			{
				//找群组成员id
				if (array_key_exists('member', $value))
				{
					$mid = $value['member'];
				}
				elseif (array_key_exists('owner', $value))
				{
					//找群主id
					$mid = $value['owner'];//群主id
					$oarraykey = $k;//记录下群主数组的key
				}
				//组成员id
				$return[$key]['member'][$k]['memberid']=$mid;
				//用户昵称，头像
				$userdata = $this->_db->table('users')
					->where('UserID',$mid)
					->first(['NickName','UserImage']);
				$returngroup['member'][$k]['nick']=$userdata->NickName??'用户昵称';
				$returngroup['member'][$k]['UserImage']=$userdata->UserImage;
				$data = array(
					'MemberID'=>$mid,
					'GroupNum'=>$groupid
				);
				$res2 = $this->_imdb->table('medigroupmember')
					->where('MemberID',$mid)
					->where('GroupNum',$groupid)
					->count('MemberID');
				//有新增成员则更新到数据库中
				if ($res2<=0)
				{
					$this->_imdb->table('medigroupmember')->insert($data);
				}
				$res3 = $this->_imdb->table('medifriends')
					->where('FriendID',$mid)
					->where('MasterID',$userid)
					->first(['MediMsg']);
				$returngroup['member'][$k]['msg']='';//默认
				if (!is_null($res3->MediMsg))
				{
					$returngroup['member'][$k]['msg']=$res3->MediMsg;
				}
				else
				{
					$foo = $this->_imdb->table('medigroupmember')
						->where('MemberID',$mid)
						->where('GroupNum',$groupid)
						->first(['MediMsg']);
					if (!is_null($foo->MediMsg))
					{
						$returngroup['member'][$k]['msg']=$foo->MediMsg;
					}
				}
			}
			//提取群主信息
			$return[$key]['owner'] =$return[$key]['member'][$oarraykey]['memberid'];
			//更新群组表信息
			if (!is_null($return[$key]['owner']))
			{
				$data = [
					"GroupNum"=>$val['groupid'],//群组唯一标识
					"MasterID"=>$return[$key]['owner'],//群主ID
					"GroupName"=>$return[$key]['groupname'],//群组名称
					"GroupCount"=>$return[$key]['affiliations_count']//群组人数
				];
				//查询当前群组是否存在，存在则更新，否则新增
				$res1 = $this->_imdb->table('medigroups')
					->where('GroupNum',$val['groupid'])
					->count('GroupNum');
				if ($res1<=0)
				{
					$this->_imdb->table('medigroups')->insert($data);
				}
				else
				{
					$this->_imdb->table('medigroups')
						->where('GroupNum',$val['groupid'])
						->update($data);
				}

			}
		}
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"data"=>$return
		);

	}
	/**
	 * 获取群组详细信息
	 * @param int $userid 用户id
	 * @param string $usertoken 用户token
	 * @param int $groupid 群组id
	 * @return 群组信息
	 */
	public function getgroup($userid,$usertoken,$groupid)
	{
		$client = new Client();
		$token = $this->getHxToken();
		$uri = "https://a1.easemob.com/medicool/medicool/chatgroups/".$groupid;
		$res = $client->request('GET',$uri,[
			'verify' => false,
			'headers'=>['Authorization'=>$header]])->getBody()->getContents();
		$resarray = json_decode($res,TRUE);
		$returngroup = [];
		$resarray = json_decode($response->getBody(),TRUE);
		$returngroup['name']=$resarray['data'][0]['name'];//群组名称
		$returngroup['affiliations_count'] = $resarray['data'][0]['affiliations_count'];//群组人数
		$returngroup['owner'] = [];//群主
		$returngroup['member'] = [];//群组成员
		foreach ($resarray['data'][0]['affiliations'] as $k=>$value)
		{
			//找群组成员id
			if (array_key_exists('member', $value))
			{
				$mid = $value['member'];
			}
			elseif (array_key_exists('owner', $value))
			{
				//找群主id
				$mid = $value['owner'];//群主id
				$oarraykey = $k;//记录下群主数组的key
			}
			//组成员id
			$returngroup['member'][$k]['memberid']=$mid;
			//用户昵称，头像
			$userdata = $this->_db->table('users')
				->select('NickName','UserImage')
				->where('UserID',$mid)
				->first();
			$returngroup['member'][$k]['nick'] = $userdata->NickName??'用户昵称';
			$returngroup['member'][$k]['UserImage'] = $userdata->UserImage;
			$data1 = ['MemberID'=>$mid,'GroupNum'=>$groupid];
			$res2num = $this->_imdb->table('medigroupmember')
				->where('MemberID',$mid)
				->where('GroupNum',$groupid)
				->count('MemberID');
			if ($res2num <= 0)
			{
				$this->_imdb->table('medigroupmember')->insert($data1);
			}
			//返回手机端成员信息的同时，备注名称返回
			$res3 = $this->_imdb->table('medifriends')
				->where('FriendID',$mid)
				->where('MasterID',$userid)
				->first(['MediMsg']);
			if (!empty($res3->MediMsg))
			{
				$returngroup['member'][$k]['msg'] = $res3->MediMsg??'';
			}
			else
			{
				//没有备注名称时，就返回群名片
				$foo = $this->_imdb->table('medigroupmember')
					->where('MemberID',$mid)
					->where('GroupNum',$groupid)
					->first(['MediMsg']);
				$returngroup['member'][$k]['msg']=$foo->MediMsg??'';
			}

		}
		//提取群主信息
		$returngroup['owner'] =$returngroup['member'][$oarraykey]['memberid'];
		//更新群组表信息
		if (!empty($returngroup['owner']))
		{
			$data=array(
				"GroupNum"=>$groupid,//群组唯一标识
				"MasterID"=>$returngroup['owner'],//群主ID
				"GroupName"=>$returngroup['groupname'],//群组名称
				"GroupCount"=>$returngroup['affiliations_count']//群组人数
			);
			//查询当前群组是否存在，存在则更新，否则新增
			$res1num = $this->_imdb->table('medigroups')
				->where('GroupNum',$groupid)
				->count('GroupNum');
			if ($res1num <= 0)
			{
				$this->_imdb->table('medigroups')->insert($data);
			}
			else
			{
				$this->_imdb->table('medigroups')->where('GroupNum',$groupid)->update($data);
			}
		}
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"data"=>$returngroup
		);

	}
	/**
	 * 获取同医院同科室的人
	 * @param int $userid 用户id
	 * @param string $usertoken 用户token
	 */
	public function samehosdep($userid,$usertoken,$cpage)
	{
		$start = ($cpage-1)*10;
		//获取当前用户的所在医院和科室
		$userdata = $this->_db->table('users')
			->where('UserID',$userid)
			->first(['PartmentName',HospitalName]);
		//根据科室进行模糊搜索查询符合条件的用户信息
		$userlst = $this->_db->table('users')
			->select('UserName','UserID','NickName','UserImage','PartmentName','HospitalName')
			->where('HospitalName',$userdata->HospitalName)
			->where('PartmentName',$userdata->PartmentName)
			->where('UserName','<>','admin')
			->where('UserID','<>',$userid)
			->skip($start)
			->take(10);
		$userlst2 = $this->_db->table('users')
			->select('UserName','UserID','NickName','UserImage','PartmentName','HospitalName')
			->where('HospitalName',$userdata->HospitalName)
			->where('PartmentName','<>',$userdata->PartmentName)
			->where('UserName','<>','admin')
			->where('UserID','<>',$userid)
			->skip($start)
			->take(10);
		$userlst=array_merge($userlst,$userlst2);
		$return=array(
			"status"=>0,
			"msg"=>"success",
			"userdata"=>$userlst
		);
	}


}