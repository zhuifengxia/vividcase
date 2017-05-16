<?php namespace Modules\Vividcase\Http\Controllers;

use Pingpong\Modules\Routing\Controller;
use DB;
use Response;
use Exception;
use Cache;

/**
 * 3D人体图库相关数据接口
 * Class BodyatlasController
 * @package Modules\Vividcase\Http\Controllers
 */
class BodyatlasController extends Controller {
	/**
	 * @var \Illuminate\Database\Connection
     */
	protected $_db;

	/**
	 * BodyatlasController constructor.
     */
	public function __construct()
	{
		$this->_db = DB::connection('mysql');
	}

	/**
	 * 医学图谱分类信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return \Illuminate\Http\JsonResponse
     */
	public function bodylst($userid,$usertoken)
	{
		try
		{
			$typedata = $this->_db->table('medibodytype')
				->select('MediID','MediTypeName','MediEngName','MediPicUrl')
				->where('MediFatherID',0)
				->where('UseType',0)
				->orderBy('MediID','desc')
				->get();

			if(strpos($_SERVER['HTTP_USER_AGENT'],"Android"))
			{
				$version=explode("(",$_SERVER['HTTP_USER_AGENT']);
				$version=explode("/",$version[0]);
				if($version[1]>="4.3.1")
				{
					$typedata[]=(object)array("MediID"=>-1,"MediTypeName"=>"经络腧穴","MediEngName"=>"Meridian Points","MediPicUrl"=>"http://meditool.cn/bbslogo/tupujlxw.png");
				}
				if($version[1]>="4.5")
				{
					$typedata[]=(object)array("MediID"=>"-2","MediTypeName"=>"心电图","MediEngName"=>"Electrocardiogra","MediPicUrl"=>"http://meditool.cn/bbslogo/tupuxdt.png");
					$typedata[]=(object)array("MediID"=>"-3","MediTypeName"=>"3D图谱","MediEngName"=>"3D Atlas","MediPicUrl"=>"http://meditool.cn/bbslogo/tupusandi.png");
				}
			}
			else if(strpos($_SERVER['HTTP_USER_AGENT'],"iPhone")||strpos($_SERVER['HTTP_USER_AGENT'],"iPad"))
			{
				$version=explode("(",$_SERVER['HTTP_USER_AGENT']);
				$version=explode("/",$version[0]);
				if($version[1]>="4.5")
				{
					$typedata[]=(object)array("MediID"=>-1,"MediTypeName"=>"经络腧穴","MediEngName"=>"Meridian Points","MediPicUrl"=>"http://meditool.cn/bbslogo/tupujlxw.png");
					$typedata[]=(object)array("MediID"=>"-2","MediTypeName"=>"心电图","MediEngName"=>"Electrocardiogra","MediPicUrl"=>"http://meditool.cn/bbslogo/tupuxdt.png");
					$typedata[]=(object)array("MediID"=>"-3","MediTypeName"=>"3D图谱","MediEngName"=>"3D Atlas","MediPicUrl"=>"http://meditool.cn/bbslogo/tupusandi.png");
				}
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$typedata,
				"watermark"=>"http://meditool.cn/images/mediwatermark.png"
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}


	/**
	 *获取系统/局部解剖数据分类信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $typeid 数据分类ID
	 * @return json
     */
	public function bodytypelst($userid,$usertoken,$typeid)
	{
		try
		{
			$key="bodytypelst_".$typeid;
			if (Cache::store('memcached')->has($key))
			{
				$typedata = Cache::store('memcached')->get($key);
			}
			else
			{
				$typedata = $this->_db->table('medibodytype')
					->select('MediID','MediTypeName','MediIntegral','MediTypeX','MediTypeY','MediTypeMsgX','MediTypeMsgY')
					->where('UseType',0)
					->where('MediFatherID',$typeid)
					->orderBy('MediTypeMsgY')
					->get();
				foreach ($typedata as $t)
				{
					$t->isdownload=1;
				}
				Cache::store('memcached')->put($key,$typedata,60);//60分钟

			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$typedata
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 *
	 * 获取局部解剖数据分类信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return \Illuminate\Http\JsonResponse
     */
	public function localtypelst($userid,$usertoken)
	{
		try
		{
			if (Cache::store('memecache')->has('localtypelst'))
			{
				$typedata = Cache::store('memcached')->get('localtypelst');
			}
			else
			{
				$typedata = $this->_db->table('medibodytype')
					->select('MediID','MediTypeName','MediIntegral','MediTypeX','MediTypeY','MediTypeMsgX','MediTypeMsgY')
					->where('UseType',0)
					->where('MediFatherID',12)
					->orderBy('MediTypeMsgY')
					->get();
				foreach($typedata as $t)
				{
					$t->isdownload=1;
				}
				Cache::store('memcached')->put('localtypelst',$typedata,60);//60分钟
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$typedata
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}


		return Response::json($return);
	}

	/**
	 *  返回心电图分类信息以及对应分类下的数据信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return \Illuminate\Http\JsonResponse
     */
	public function ecgdatalst($userid,$usertoken)
	{
		try
		{
			if (Cache::store('memecache')->has('ecgdatalst'))
			{
				$ecgtypedata = Cache::store('memcached')->get('ecgdatalst');
			}
			else
			{
				$ecgtypedata = $this->_db->table('medicoolecgtype')
					->select('EcgID','EcgName','EcgX','EcgY')
					->get();
				foreach($ecgtypedata as $e)
				{
					$ecgdata = $this->_db->table('medicoolecg')
						->select('FileUrl','FileSize','EcgTitle','EcgMsg','EcgHR')
						->where('TypeID',$e->EcgID)
						->get();
					$e->datalst = $ecgdata;
					$e->EcgInfo = 'http://meditool.cn/Preview/medicalecg?msgid='.$e->EcgID;
				}
				Cache::store('memcached')->put('ecgdatalst',$ecgtypedata,60);//60分钟
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$ecgtypedata
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 根据分类ID获取当前分类下的所有的人体图信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $typeid 分类ID
	 * @return \Illuminate\Http\JsonResponse
     */
	public function bodyatlaslst($userid,$usertoken,$typeid)
	{
		try
		{
			$downnum = $this->_db->table('medidownload')
				->where('DataID',$typeid)
				->where('UserID',$userid)
				->where('DataType',0)
				->count('DownID');
			if ($downnum <= 0)
			{
				$data = ['DataID'=>$typeid,'UserID'=>$userid,'DataType'=>0,'DownTime'=>time()];
				$this->_db->table('medidownload')->insert($data);
				$return = $this->getbodyatlas($typeid);
			}
			else
			{
				$return = $this->getbodyatlas($typeid);
			}
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}

	/**
	 *  根据系统分类获取到该分类下的所有图片信息集合
	 * @param $typeid
	 * @return array
     */
	public function getbodyatlas($typeid)
	{
		$key="bodyatlaslst_".$typeid;
		if (Cache::store('memecache')->has($key))
		{
			$return = Cache::store('memcached')->get($key);
		}
		else
		{
			$bodydata = $this->_db->table('medibodyatlas')
				->select('BodyID','BodyTitle','BodyAtlasUrl','BodySort')
				->where('DataType',0)
				->where('UseType',0)
				->where('BodyTypeID',$typeid)
				->orderBy('BodySort')
				->get();
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$bodydata
			);
			Cache::store('memcached')->put($key,$return,60);//60分钟

		}
		return $return;
	}

	/**
	 * 根据人体图ID获取到该图上面的所有知识点信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $bodyatlasid 人体图ID
	 * @return \Illuminate\Http\JsonResponse
     */
	public function bodytaglst($userid,$usertoken,$bodyatlasid)
	{
		try
		{
			$key="bodytaglst_".$bodyatlasid;
			if (Cache::store('memecache')->has($key))
			{
				$return = Cache::store('memcached')->get($key);
			}
			else
			{
				$tagdata = $this->_db->table('medibodytagmsg')
					->select('TagID','TagName','TagEnName','TagX','TagY','TagDesc')
					->where('BodyID',$bodyatlasid)
					->orderBy('TagY')
					->get();
				$return=array(
					"status"=>0,
					"msg"=>"success",
					"data"=>$tagdata
				);
				Cache::store('memcached')->put($key,$return,60);//60分钟
			}
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 根据标签名称检索数据
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $keywords 搜索关键词
	 * @return \Illuminate\Http\JsonResponse
     */
	public function searchtags($userid,$usertoken,$keywords)
	{
		try
		{
			//检索出符合条件的数据信息
			$tags = $this->_db->table('medibodytagmsg')
				->select('TagID','TagName','TagEnName','TagX','TagY','TagDesc','BodyID')
				->where('TagName','like',"%$keywords%")
				->orWhere('TagEnName','like',"%$keywords%")
				->orWhere('TagDesc','like',"%$keywords%")
				->get();
			foreach ($tags as $t)
			{
				$typedata = $this->_db->table('medibodyatlas')
					->where('UseType',0)
					->where('BodyID',$t->BodyID)
					->first(['BodyTypeID']);
				$t->BodyTypeID = $typedata->BodyTypeID??null;
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$tags
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}

	/**
	 * 返回心电图分类信息数据
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json 分类ID；分类名称；坐标；分类详细信息地址（网页）http://meditool.cn/Preview/medicalecg?msgid=分类ID
     */
	public function ecgtypelst($userid,$usertoken)
	{
		try
		{
			$res = $this->_db->table('medicoolecgtype')->get();
			foreach ($res as $r)
			{
				$r->InfoURL = 'http://meditool.cn/Preview/medicalecg?msgid='.$r->EcgID;
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"filed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 返回心电图目标分类信息的病例内容
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $ecgtypeid 分类ID
	 * @param int $cpage 请求页码  20条/页
	 * @return json 病例文件地址；文件大小；病例标题；描述；病例HR
     */
	public function ecgtypecaselst($userid,$usertoken,$ecgtypeid,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			$res = $this->_db->table('medicoolecg')
				->select('FileUrl','FileSize','EcgTitle','EcgMsg','EcgHR','EcgImg')
				->where('TypeID',$ecgtypeid)
				->skip($start)
				->take(20)
				->get();
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res
			);
		}
		catch (Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"filed"
			);
		}
		return Response::json($return);
	}
}