<?php namespace Modules\Vividcase\Http\Controllers;
use Pingpong\Modules\Routing\Controller;
use DB;
use Response;
use GuzzleHttp\Client;
class BasedoctorController extends Controller {
	
	protected $_drugdb;
	protected $_medidisdb;
	protected $_db;
	public function __construct()
	{
		$this->_drugdb = DB::connection('drugs_mysql');
		$this->_medidisdb = DB::connection('medidis_mysql');
		$this->_db= DB::connection('mysql');
	}
	/**
	 * 科室列表以及对应的疾病信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json
	 */
	public function depdislst($userid,$usertoken)
	{
		try
		{
			$query = $this->_db->table('mediguidetype')
								->select('MediGuideTypeID AS DisID','MediGuideTypeName AS DisName','DiseaseID')
								->where('MediFatherID',[]);

			$dep = $query->setBindings([0])->get();
			$res = [];
			$arr = [];
			$query->where('IsShow',[]);
			foreach ($dep as $d)
			{
				$dis = $query->setBindings([$d->DisID,1])->get();
				if (count($dis) > 0)
				{
					$arr['DepID']=$d->DisID;
					$arr['DepName']=$d->DisName;
					$arr['DisLst']=$dis;
					foreach($arr['DisLst'] as $v)
					{
						$coll =$this->_db->table('medicollection')->where('DataID',$v->DisID)->where('DataType',3)->where('UserID',$userid)->pluck('DataID');
						$iscollect = count($coll)>0?1:0;
						$v->IsCollect=$iscollect;
					}
					$res[]=$arr;
				}
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 搜索疾病
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $disname 疾病关键词
	 * @return json
	 */
	public function dissearch($userid,$usertoken,$disname)
	{
        try
        {
            $query = $this->_db->table('mediguidetype')
                ->where('MediFatherID','<>',0)
                ->where('IsShow',1)
                ->where('DiseaseID','<>',0)
                ->where('MediGuideTypeName','like',"%$disname%");
            //去重
            $did = array_unique($query->pluck('MediGuideTypeID'));//疾病id
            $fid = array_unique($query->pluck('MediFatherID'));//科室id
            $res = [];
            foreach ($fid as $k=>$f)
            {
                $foo = $this->_db->table('mediguidetype')
                    ->select('MediGuideTypeID AS DisID', 'MediGuideTypeName AS DisName', 'DiseaseID')
                    ->where('MediFatherID', $f)
                    ->whereIn('MediGuideTypeID', $did)
                    ->get();
                $res[$k]['DepID'] = $f;
                $depname = $this->_db->table('mediguidetype')->where('MediGuideTypeID', $f)->first(['MediGuideTypeName']);
                //科室名
                $res[$k]['DepName'] = $depname->MediGuideTypeName;
                //疾病集合
                $res[$k]['DisLst'] = $foo;
                foreach ($res[$k]['DisLst'] as $b)
                {
                    //是否收藏
                    $coll =$this->_db->table('medicollection')->where('DataID',$b->DisID)->where('DataType',3)->where('UserID',$userid)->pluck('DataID');
                    $iscollect = count($coll)>0?1:0;
                    $b->IsCollect=$iscollect;
                }
            }
            $return=array(
                "status"=>0,
                "msg"=>"success",
                "data"=>$res
            );
        }
        catch(Exception $e)
        {
            $return  = array(
                "status"=>1,
                "msg"=>"failed"
            );
        }
		return Response::json($return);
	}

	/**
	 * 获取某个疾病相关的医学指南
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $diseaseid 疾病ID
	 * @param int $cpage 查询结果请求页码
	 * @return json 参考原来的接口数据返回
	 */
	public function disguidelst($userid,$usertoken,$diseaseid,$cpage)
	{
        try
        {
            $result = $this->guidelst($diseaseid,$cpage,10);
            $return=array(
                "status"=>0,
                "msg"=>"success",
                "data"=>$result
            );
        }
        catch(Exception $e)
        {
            $return  = array(
                "status"=>1,
                "msg"=>"failed"
            );
        }

        return Response::json($result);
	}
	/**
	 * 获取某个疾病相关的药品信息列表
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @param int $cpage 查询结果请求页码
	 * @return string  列表（西药+中成药+饮片）参考医药百科的搜索接口返回
	 */
	public function disdruglst($userid,$usertoken,$diseasename,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			//检索出符合条件适应症ID集合
			$codes = $this->_drugdb->table('diseasecode')
				->where('DiseaseName','like',"%$diseasename%")
				->orWhere('DiseaseMsg','like',"%$diseasename%")
				->orWhere('DiseaseCode','like',"%$diseasename%")
				->pluck('DiseaseID');
			if(!empty($codes))
			{
				$drugids = $this->_drugdb->table('drugusage')
					->where('DataType',2)
					->where('DataSource',1)
					->where(function($query) use ($diseasename,$codes){
						$query->where('OtherName','like',"%$diseasename%")
							->orWhereIn('DiseaseID',$codes);
					})
					->pluck('DrugID');
			}
			if(!empty($drugids))
			{
				// 根据以上获取到的查询条件（说明书ID集合）检索符合条件的说明书
				$drugmsg = $this->_drugdb->table('druginstruction')
					->select("InstructionID", "DrugName", "InstructionTitle","EnDrugName")
					->whereIn('InstructionID',$drugids)
					->skip($start)
					->take(10)
					->get();
				foreach($drugmsg as $d)
				{
					$comname = $this->_drugdb->table('drugcomname')
						->where('NameID',$d->DrugName)
						->first(['DrugName']);
					$d->DrugName2=$comname->DrugName;
					$d->DataType=1;//西药检索出来的数据
				}
			}
			//中药数据检索
			$zhong = $this->_drugdb->table('chinacommon')
				->where('DrugCommName','like',"%$diseasename%");
			$zcommarr = $zhong->pluck('DrugCommName','DrugCommID');
			$zhongcommids = $zhong->pluck('DrugCommID');

			if(!empty($zhongcommids))
			{
				// 根据以上获取到的查询条件（通用名ID集合）检索符合条件的说明书
				$zdrugmsg = $this->_drugdb->table('chinainstruction')
					->select("InstructionID","DrugName")
					->whereIn('DrugName',$zhongcommids)
					->skip($start)
					->take(10)
					->get();
				foreach($zdrugmsg as $z)
				{
					$z->DrugName2=$zcommarr[$z->DrugName];
					$z->DataType=2;//中成药检索出来的数据
				}
				//中药饮片数据检索
				$zydrugmsg = $this->_drugdb->table('chinayinpian')
					->select("InstructionID","DrugName")
					->whereIn('DrugName',$zhongcommids)
					->skip($start)
					->take(10)
					->get();
				foreach($zydrugmsg as $zy)
				{
					$zy->DrugName2=$zcommarr[$zy->DrugName];
					$zy->DataType = 3;//中药饮片检索出来的数据
				}

			}

			if(!empty($zdrugmsg))
			{
				//中成药和西药检索出来的数据合并
				$drugmsg=array_merge($drugmsg,$zdrugmsg);
			}
			if(!empty($zydrugmsg))
			{
				//中药饮片和西药以及中成药检索出来的数据合并
				$drugmsg=array_merge($drugmsg,$zydrugmsg);
			}

			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$drugmsg
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 获取某个疾病相关的病例信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @param int $cpage 查询结果请求页码
	 * @return json
	 */
	public function discaselst($userid,$usertoken,$diseasename,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			//病例id
			$projectid = $this->_db->table('goodcasedisease')->where('DiseaseName','like',"%$diseasename%")->pluck('ProjectID');
			//病例
			$project = $this->_db->table('projects')->select('ID','Guid','GetItTime','KeyWords','BarCode','GoodCaseDep')
				->where('IsLock','<>',2)
				->where('IsGoodCase',1)
				->whereIn('ID',$projectid)
				->skip($start)
				->take(10)
				->get();
			foreach ($project as $r)
			{
				//是否收藏
				$coll =$this->_db->table('medicollection')->where('DataID',$r->ID)->where('DataType',5)->where('UserID',$userid)->pluck('DataID');
				$iscollect = count($coll)>0?1:0;
				$r->IsCollect=$iscollect;
				//赞数
				$prisecount = $this->_db->table('medipraise')->where('IsGoodCase',1)->where('PostingID',$r->ID)->count('PraiseID');
//			var_dump($prisecount);exit();
				$r->prisecount=$prisecount;
				//病例代表图
				$picdata = $this->_db->table('pictures')->where('ProjectID',$r->Guid)->where('FileType',0)->first(['AccesseryName']);
				$r->picurl = "http://77g42t.com2.z0.glb.qiniucdn.com/".$picdata->AccesseryName."?imageView2/2/w/300/h/180/";
				//科室
				$foo = explode("_", $r->GoodCaseDep);
				$r->GoodCaseDep=$foo[0];
				//病例url
				$r->CaseURL="http://meditool.cn/Democase/goodcase?caseid=".$r->ID;
				unset($r->Guid);
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$project
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 获取某个疾病相关的临床路径信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @param int $cpage 页码
	 * @return json
	 */
	public function disclinicpathlst($userid,$usertoken,$diseasename,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			$res = $this->_db->table('clinicalpathway')->select('ClinicalID','Clinicalpathway','ClinicalUrl','FileSize','DownLoadNum')
				->where('Clinicalpathway','like',"%$diseasename%")
				->skip($cpage)
				->take(10)
				->get();
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 获取某个疾病相关的检验助手信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @param int $cpage 页码
	 * @return json
	 */
	public function dischecklst($userid,$usertoken,$diseasename,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			$res = $this->_db->table('inspectiontype')->select('InspectionID','TypeName','DataType','DepType')
				->where('DataType',2)
				->where('IsLower',1)
				->where('TypeName','like',"%$diseasename%")
				->skip($start)
				->take(10)
				->get();
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}

	/**
	 * 获取某个疾病的相关的图谱信息以及疾病概述
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $diseaseid 疾病编码ID
	 * @return json
	 */
	public function dismsg($userid,$usertoken,$diseaseid)
	{
		try
		{
			//获取当前疾病的信息地址
			$dismsg = $this->_db->table('mediguidetype')->where('MediFatherID','<>',0)
				->where('DiseaseID','<>',0)
				->where('IsShow',1)
				->where('DiseaseID',$diseaseid)
				->first(['MediGuideTypeID AS DisID','MediFatherID']);
			if(count($dismsg)>0)
			{
				if (!in_array($dismsg->MediFatherID, array(1,2,13)))
				{

					$dismsgid = $this->_medidisdb->table('disease')->where('Disease_CodeID',$diseaseid)->first(['Disease_ID']);
					if(count($dismsgid)>0)
					{
						$disurl="http://disease.meditool.cn/index/index?jbid=".$dismsgid->Disease_ID;
					}
				}
				else
				{
					switch ($dismsg->MediFatherID)
					{
						case 1: //皮肤科
							$url ="http://www.mediskin.cn/Swcf/Calculator.svc/Data/getdiseaseurl?diseaseid=".$diseaseid;
							break;
						case 2:  //骨科
							$url ="http://www.medibone.cn/Swcf/Calculator.svc/Data/getdiseaseurl?diseaseid=".$diseaseid;
							break;
						case 13: //内分泌
							$url ="http://mediendo.cn/apidisease/geturlbydiscode?diseaseid=".$diseaseid;
							break;
					}
					$bar= $this->request($url);
					$disurl=$bar['url'];

				}

				//此疾病相关的图谱信息
				$atlas = $this->_db->table('medibodytype')->select('MediID AS systemId','MediTypeName AS systemName')
					->where('MediPicUrl','like',"%$dismsg->DisID%")
					->get();
				if (count($atlas) > 0)
				{
					foreach ($atlas as $a)
					{
						$a->systemId=(int)$a->systemId;
						$a->url='http://meditool.cn/images/medidefaultatlas.png';
						$a->picId=0;
						$a->picName='';
					}
				}
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"disoverview"=>empty($disurl)?"":$disurl,
				"disatlas"=>$atlas
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}
	/**
	 * 获取某个疾病相关的文献/指南/临床路径各两条
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $diseaseid 疾病ID
	 * @return json
	 */
	public function guideclinicpathset($userid,$usertoken,$diseaseid)
	{
		try
		{
			//指南
			$guideinfo=$this->guidelst($diseaseid,1,2);
			//获取疾病名称
			$dis = $this->_db->table('mediguidetype')->where('MediGuideTypeID',$diseaseid)->first(['MediGuideTypeName']);
			$disname=$dis->MediGuideTypeName;
			if ($disname)
			{
				//临床路径
				$clinicpath = $this->_db->table('clinicalpathway')
					->select('ClinicalID','Clinicalpathway','ClinicalUrl','FileSize','DownLoadNum')
					->where('Clinicalpathway','like',"%$disname%")
					->orderBy('ClinicalID','desc')
					->take(2)
					->get();

				//文献
				//万方检索接口
				$uri = 'http://api.med.wanfangdata.com.cn/Article/Search';
				$query='cql.anywhere all "'.$disname.'"';
				$query.=" sortby date";
				$postVals = array(
					'form_params'=>array(
						'query' =>$query,
						'startIndex'=>1,
						'token'=>"Zhenlipai",
						'pageSize'=>2
					)
				);
				$client = new Client();
				$res=$client->request('POST',$uri,$postVals)->getBody()->getContents();
				$data=\GuzzleHttp\json_decode($res,true);
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"literature"=>$data,//文献2条
				"disguide"=>$guideinfo, //指南2条
				"disclinicpath"=>$clinicpath //临床路径2条
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 获取某个疾病的相关的用药/检验各三条
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @return json
	 */
	public function drugcheckset($userid,$usertoken,$diseasename)
	{
		try
		{
			//用药
			//检索出符合条件适应症ID集合
			$codes = $this->_drugdb->table('diseasecode')
				->where('DiseaseName','like',"%$diseasename%")
				->orWhere('DiseaseMsg','like',"%$diseasename%")
				->orWhere('DiseaseCode','like',"%$diseasename%")
				->pluck('DiseaseID');
			if(!empty($codes))
			{
				$drugids = $this->_drugdb->table('drugusage')
					->where('DataType',2)
					->where('DataSource',1)
					->where(function($query) use ($diseasename,$codes) {
						$query->where('OtherName','like',"%$diseasename%")
							->orWhereIn('DiseaseID',$codes);
					})
					->pluck('DrugID');
			}

			if(!empty($drugids))
			{
				// 根据以上获取到的查询条件（说明书ID集合）检索符合条件的说明书
				$drugmsg = $this->_drugdb->table('druginstruction')
					->select("InstructionID", "DrugName", "InstructionTitle","EnDrugName")
					->whereIn('InstructionID',$drugids)
					->orderBy('InstructionID','desc')
					->take(3)
					->get();
				foreach($drugmsg as $d)
				{
					$comname = $this->_drugdb->table('drugcomname')->where('NameID',$d->DrugName)->first(['DrugName']);
					$d->DrugName2 = $comname->DrugName;
					$d->DataType = 1;//西药检索出来的数据
				}
			}

			//中药数据检索
			$zhong = $this->_drugdb->table('chinacommon')->where('DrugCommName','like',"%$diseasename%");
			$zcommarr = $zhong->pluck('DrugCommName','DrugCommID');
			$zhongcommids = $zhong->pluck('DrugCommID');
			if(!empty($zhongcommids))
			{
				// 根据以上获取到的查询条件（通用名ID集合）检索符合条件的说明书
				$zdrugmsg = $this->_drugdb->table('chinainstruction')
					->select("InstructionID","DrugName")
					->whereIn('DrugName',$zhongcommids)
					->orderBy('InstructionID','desc')
					->take(3)
					->get();
				foreach ($zdrugmsg as $z)
				{
					$z->DrugName2 = $zcommarr[$z->DrugName];
					$z->DataType = 2;//中成药检索出来的数据
				}
				//中药饮片数据检索
				$zydrugmsg = $this->_drugdb->table('chinayinpian')->select("InstructionID","DrugName")
					->whereIn('DrugName',$zhongcommids)
					->orderBy('InstructionID','desc')
					->take(3)
					->get();
				foreach ($zydrugmsg as $zy)
				{
					$zy->DrugName2 = $zcommarr[$zy->DrugName];
					$zy->DataType = 3;//中药饮片检索出来的数据
				}
			}
			if(!empty($zdrugmsg))
			{
				//中成药和西药检索出来的数据合并
				$drugmsg=array_merge($drugmsg,$zdrugmsg);
			}
			if(!empty($zydrugmsg))
			{
				//中药饮片和西药以及中成药检索出来的数据合并
				$drugmsg=array_merge($drugmsg,$zydrugmsg);
			}
			//检验单
			$check = $this->_db->table('inspectiontype')->select('InspectionID','TypeName','DataType','DepType')
				->where('DataType',2)
				->where('IsLower',1)
				->where('TypeName','like',"%$diseasename%")
				->orderBy('InspectionID','desc')
				->take(3)
				->get();
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"drug"=>$drugmsg,
				"check"=>$check
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 *获取某个疾病相关的文献信息
	 *@param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param string $diseasename 疾病名称
	 * @param int $cpage 页码
	 * @return json
	 */
	public function literaturemore($userid,$usertoken,$diseasename,$cpage)
	{
		try
		{
			$uri = 'http://api.med.wanfangdata.com.cn/Article/Search';
			$query='Title="'.$diseasename.'"';
			$query.=" sortby date";
			$postVals = array(
				'form_params'=>array(
					'query' =>$query,
					'startIndex'=>($cpage-1)*20+1,
					'token'=>"Zhenlipai",
					'pageSize'=>20
				)

			);
			$client = new Client();
			$res=$client->request('POST',$uri,$postVals)->getBody()->getContents();
			$data=\GuzzleHttp\json_decode($res,true);
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$data,
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 获取某个疾病的视频信息
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $cpage 分页页码
	 * @param int $diseaseid 疾病ID
	 * @return json
	 */
	public function disvideo($userid,$usertoken,$diseaseid,$cpage)
	{
		try
		{
			//根据疾病ID查到对应疾病名称以及别名，
			$disnameres = $this->_db->table('mediguidetype')
				->select('MediGuideTypeName','MediGuideTypeRemark')
				->where('MediGuideTypeID',$diseaseid)
				->first();
			//首先截取疾病别名,然后根据别名和疾病名进行模糊匹配查询
			$nickname=explode(",",$disnameres->MediGuideTypeRemark);
			$query = $this->_db->table('medivideo')->select('VideoID','VideoName','VideoPicUrl','VideoUrl','VideoNum','VideoTypeID','VideoDepID','VideoTime')
				->where('VideoName','like',"%$disnameres->MediGuideTypeName%");
			foreach ($nickname as $nick)
			{
				if ($nick!=$disnameres->MediGuideTypeName)
				{
					$query->orWhere('VideoName','like',"%$nick%");
				}
			}
			$start=($cpage-1)*10;
			$query->where('VideoNum','<>',-1)->skip($start)->take(10);
			$videolst=$query->get();
			foreach ($videolst as $vlst)
			{
				//根据视频地址判断 0是医学视频，1是直播录完后的视频
				$vlst->type=0;//暂时全部返回0
				//循环获取视频来源
				if (!empty($vlst->VideoTypeID))
				{
					$vidtypename = $this->_db->table('medivideotype')->where('VideoTypeID',$vlst->VideoTypeID)->first(['VideoTypeName']);
					$vlst->VideoTypeName=$vidtypename->VideoTypeName;
				}
				//循环获取视频所在科室
				if (!empty($vlst->VideoDepID))
				{
					$viddepname = $this->_db->table('medivideotype')->where('VideoTypeID',$vlst->VideoDepID)->first(['VideoTypeName']);
					$vlst->VideoDepName=$viddepname->VideoTypeName;
				}
				//删除变量
				unset($vlst->VideoTypeID);
				unset($vlst->VideoDepID);
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$videolst
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 获取某个疾病的资讯
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @param int $diseaseid 疾病ID
	 * @param int $cpage 页码
	 * @return json
	 */
	public function disinformation($userid,$usertoken,$diseaseid,$cpage)
	{
		try
		{
			$start = ($cpage-1)*10;
			$zxdata = $this->_db->table('magazinearticle')
				->select('ArticleID AS ID', 'ArticleTitle AS MsgTitle','ArticleUrl AS MsgUrl','ArticlePicPath AS MsgPic','Article_PublishTime AS MsgTime','ArticleNum AS MsgNum','ArticleDis AS MsgDis')
				->where('PerID',37)
				->where('ArticleDis','<>',0)
				->where('ArticleDis',$diseaseid)
				->skip($start)
				->take(10)
				->get();

			foreach ($zxdata as $zx)
			{
				$disname = $this->_db->table('mediguidetype')->where('MediGuideTypeID',$zx->MsgDis)->first(['MediGuideTypeName']);
				$zx->MsgDis = $disname->MediGuideTypeName;
			}

			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$zxdata
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}
	/**
	 * 首页的最新资讯/病例版块
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json
	 */
	public function information($userid,$usertoken)
	{
		try
		{
			//随机获得2条资讯信息
			$zxcount = $this->_db->table('magazinearticle')->where('PerID',37)->where('ArticleDis','<>',0)->count('ArticleID');
			$zxrand=rand(0,$zxcount-2);
			$zxdata = $this->_db->table('magazinearticle')
				->select('ArticleID AS ID','ArticleTitle AS MsgTitle','ArticleUrl AS MsgUrl','ArticlePicPath AS MsgPic','Article_PublishTime AS MsgTime','ArticleNum AS MsgNum','ArticleDis AS MsgDis')
				->where('PerID',37)
				->where('ArticleDis','<>',0)
				->skip($zxrand)
				->take(10)
				->get();

			foreach ($zxdata as $zx)
			{
				$disname = $this->_db->table('mediguidetype')->where('MediGuideTypeID',$zx->MsgDis)->first(['MediGuideTypeName']);
				$zx->MsgDis = $disname->MediGuideTypeName;
			}

			//随机获得2条病例信息

			$casenum = $this->_db->table('projects')->where('IsGoodCase',1)->where('IsLock','<>',2)->count('ID');
			$caserand=rand(0,$casenum-2);
			$casedata = $this->_db->table('projects')->select('ID','Guid','KeyWords AS MsgTitle','GetItTime AS MsgTime','BarCode AS MsgNum')
				->where('IsGoodCase',1)
				->where('IsLock','<>',2)
				->skip($caserand)
				->take(2)
				->get();
			foreach($casedata as $c)
			{
				$c->MsgUrl="http://meditool.cn/Democase/goodcase?caseid=".$c->ID;
				$picdata = $this->_db->table('pictures')->where('ProjectID',$c->Guid)->first(['AccesseryName']);
				$c->MsgPic="";
				if(count($picdata)>0)
				{
					$c->MsgPic="http://77g42t.com2.z0.glb.qiniucdn.com/".$picdata->AccesseryName;
				}
				$dis = $this->_db->table('goodcasedisease')->where('ProjectID',$c->ID)->first(['DiseaseName']);
				$c->MsgDis=empty($dis->DiseaseName)?"":$dis->DiseaseName;
				unset($c->Guid);
			}
			//两组数据合并
			$infomation=array_merge($casedata,$zxdata);
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$infomation
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}
	/**
	 * 首页的讲座数据（视频）
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json
	 */
	public function homevideo($userid,$usertoken)
	{
		try
		{
			//视频类型：2白天使  28NPN Media 30医脉通 76医库 126霍普金斯医学课程 130医瘤助手
			//随机获得4条视频信息
			$vidcount = $this->_db->table('medivideo')->whereIn('VideoTypeID',[2,28,30,76,126,130])->where('VideoNum','<>',-1)->count('VideoID');
			$vidrand=rand(0,$vidcount-4);
			$viddata = $this->_db->table('medivideo')->select('VideoID','VideoName','VideoPicUrl','VideoUrl','VideoNum','VideoTypeID','VideoDepID','VideoTime')
				->whereIn('VideoTypeID',[2,28,30,76,126,130])
				->where('VideoNum','<>',-1)
				->skip($vidrand)
				->take(4)
				->get();
			foreach ($viddata as $vlst)
			{
				//循环获取视频来源
				if (!empty($vlst->VideoTypeID))
				{
					$vidtypename = $this->_db->table('medivideotype')->where('VideoTypeID',$vlst->VideoTypeID)->first(['VideoTypeName']);
					$vlst->VideoTypeName=$vidtypename->VideoTypeName;
				}
				//循环获取视频所在科室
				if (!empty($vlst->VideoDepID))
				{
					$viddepname = $this->_db->table('medivideotype')->where('VideoTypeID',$vlst->VideoDepID)->first(['VideoTypeName']);
					$vlst->VideoDepName=$viddepname->VideoTypeName;
				}
				//删除变量
				unset($vlst->VideoTypeID);
				unset($vlst->VideoDepID);
			}
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$viddata
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}
		return Response::json($return);
	}
	/**
	 * 基层医生首页的疾病学习版块
	 * @param int $userid  用户ID
	 * @param string $usertoken 用户token
	 * @return json
	 */
	public function disstudy($userid,$usertoken)
	{
		try
		{
			$discount = $this->_db->table('mediguidetype')->where('MediFatherID','<>',0)->where('IsShow',1)->where('DiseaseID','<>',0)->count('MediGuideTypeID');
			$start=rand(0,$discount-1);
			//随机返回一条
			$res = $this->_db->table('mediguidetype')->select('MediGuideTypeID AS DisID','MediGuideTypeName AS DisName','DiseaseID','MediFatherID')
				->where('MediFatherID','<>',0)
				->where('IsShow',1)
				->where('DiseaseID','<>',0)
				->skip($start)
				->take(1)
				->first();
			$res->Treatment="";
			//获取该疾病的概述信息
			if(!in_array($res->MediFatherID,array(1,2,13)))
			{
				$dismsgid = $this->_medidisdb->table('disease')->where('Disease_CodeID',$res->DiseaseID)->first(['Disease_ID']);
				if(count($dismsgid)>0)
				{
					$dismsg = $this->_medidisdb->table('disarticleinfo')->where('DisArticleInfo_ArticleTypeId',1)->where('DisArticleInfo_DiseaseId',$dismsgid->Disease_ID)->first(['DisArticleInfo_Info AS Treatment']);
					$treatment=strip_tags($dismsg->Treatment);
					$res->Treatment=str_replace("&nbsp;","",$treatment);
				}
			}
			else
			{
				switch ($res->MediFatherID)
				{
					case 1:
						$url ="http://www.mediskin.cn/Swcf/Calculator.svc/Data/getdiseaseurl?diseaseid=".$res->DiseaseID;
						break;
					case 2:
						$url ="http://www.medibone.cn/Swcf/Calculator.svc/Data/getdiseaseurl?diseaseid=".$res->DiseaseID;
						break;
					case 13:
						$url ="http://mediendo.cn/apidisease/geturlbydiscode?diseaseid=".$res->DiseaseID;
						break;
				}
				$treatment= $this->request($url);
				$treatment=strip_tags($treatment['gaishu']);
				$res->Treatment=str_replace("&nbsp;","",$treatment);
			}
			unset($res->MediFatherID);
			//当前疾病的视频信息（取一条）
			//视频小图地址；视频标题；视频点击量；视频来源（ps医脉通）；分类（ps内分泌科）

			$vidcount = $this->_db->table('medivideo')->where('VideoNum','<>',-1)->where('VideoName','like',"%$res->DisName%")->count('VideoID');
			$start1=rand(0,$vidcount-1);
			//随机返回一条视频信息
			$viddata = $this->_db->table('medivideo')->select('VideoID','VideoName','VideoPicUrl','VideoUrl','VideoNum','VideoTypeID','VideoDepID')
				->where('VideoNum','<>',-1)
				->where('VideoName','like',"%$res->DisName%")
				->skip($start1)
				->take(1)
				->first();
			//获取视频来源
			if (!empty($viddata->VideoTypeID))
			{
				$vidtypename = $this->_db->table('medivideotype')->where('VideoTypeID',$viddata->VideoTypeID)->first(['VideoTypeName']);
				$viddata->VideoTypeName=$vidtypename->VideoTypeName;
			}
			//获取视频所在科室
			if (!empty($viddata->VideoDepID))
			{

				$viddepname = $this->_db->table('medivideotype')->where('VideoTypeID',$viddata->VideoDepID)->first(['VideoTypeName']);
				$viddata->VideoDepName=$viddepname->VideoTypeName;
			}
			//删除变量
			unset($viddata->VideoTypeID);
			unset($viddata->VideoDepID);
			$return=array(
				"status"=>0,
				"msg"=>"success",
				"data"=>$res,
				"videodata"=>$viddata
			);
		}
		catch(Exception $e)
		{
			$return  = array(
				"status"=>1,
				"msg"=>"failed"
			);
		}

		return Response::json($return);
	}

	public function request($url)
	{
		$client = new Client();
		$token = md5('无锡医库软件科技有限公司');
		$uri=$url.'&token='.$token;
		$res = $client->request('GET',$uri)->getBody()->getContents();
		$resarr = \GuzzleHttp\json_decode($res,true);
		return $resarr;
	}
	public function guidelst($diseaseid,$cpage,$datanum)
	{
		$start = ($cpage - 1) * 10;
		$result = $this->_db->table('mediguide')
			->select("GuideID", "GuideName", "GuideClassify", "GuideAuthor", "GuideDerivation", "CreateTime", "GuidePress", "GuideUrl", "LoadNum", "GuidePaper", "GuideType", "FromType", "YmtKey", "GuideSize")
			->where('ReviewStatus', 1)
			->where('FromType', '<>', 2)
			->where('GuideSize', '<>', '')
			->whereNotNull('GuideSize')
			->where('DiseaseID', $diseaseid)
			->orderBy('GuideID', 'desc')
			->skip($start)
			->take($datanum)
			->get();
		foreach ($result as $r)
		{
			if ($r->FromType == 1) {
				if (strstr($r->GuideSize, "K")) {
					$arr = explode("K", $r->GuideSize);
					$r->GuideSize = $arr[0] * 1024;
				} else if (strstr($r->GuideSize, "M")) {
					$arr = explode("M", $r->GuideSize);
					$r->GuideSize = $arr[0] * 1024 * 1024;
				} else {
					$r->GuideSize = $r->GuideSize * 1024 * 1024;
				}
			}
			if ($r->GuideSize <= 0) {
				$r->GuideSize = 1024 * 500;
			}
			//获取科室信息
			$depdata = $this->_db->table('mediguidetype')->where('MediGuideTypeID', $r->GuideType)->first(['MediGuideTypeName']);
			$r->MediGuideTypeName = $depdata->MediGuideTypeName;
		}
		return $result;
	}

}