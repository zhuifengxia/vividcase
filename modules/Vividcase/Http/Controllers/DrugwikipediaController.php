<?php namespace Modules\Vividcase\Http\Controllers;

use Pingpong\Modules\Routing\Controller;
use DB;
use Response;
use Exception;
use Cache;
use GuzzleHttp\Client;

class DrugwikipediaController extends Controller {

	protected $_drugdb;
	public function __construct()
	{
		$this->_drugdb=Db::connection('drugs_mysql');

	}

	/**
	* 医药百科数据分类列表（一级分类所有数据）
	* @param $username 用户名
	* @param $userpwd 密码
	* @return json
	*/
	public function onetypelst()
	{

		try 
		{
			if (Cache::store('memcached')->has('apidrugds_onetypelst')) {
				$return = Cache::store('memcached')->get('onetypelst');
			}
			else
			{
				//西药一级分类目录
				$westmedicine = $this->_drugdb->table('drugtypecatalog')
											->select('DrugID', 'DrugTypeName')
											->where('DrugFatherID', 1)
											->where('PeopleType', 'like', '%0%')
											->get();


				foreach ($westmedicine as $obj) {
					//告诉手机端该分类下还有下级目录
					$obj->IsLower = 0;
				}
				//中药一级分类目录
				$chinamedicine = $this->_drugdb->table('chinadrugtype')
										->select('DrugTypeID AS DrugID','DrugTypeName','IsLower')
										->where('DrugTypeFID', 0)
										->get();

				//用药人群一级分类目录
				$usecrowd = $this->_drugdb->table('drugpeopletype')
										->select('PeopleID AS DrugID','PeopleName AS DrugTypeName')
										->get();

				foreach ($usecrowd as $obj) {
					//告诉手机端该分类下还有下级目录
					$obj->IsLower = 0;
				}

				$data=array(
					"westmedicine"=>$westmedicine,//西药的一级分类数据
					"chinamedicine"=>$chinamedicine,//中药的一级分类数据
					"usecrowd"=>$usecrowd //用药人群的一级分类数据
				);
				$return = array(
					"status" => 0,
					"msg" => "success",
					"data"=>$data
				); 
				Cache::store('memcached')->put('apidrugds_onetypelst',$return,60);//60分钟

			}
	
				

		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}

		return Response::json($return);

	}
	/**
	* 医药百科下一级分类数据查询
	* @param int $userid 用户ID
	* @param string $usertoken 用户token
	* @param int $typetype 分类类型1西药数据分类;2中药分类;3用药人群分类
	* @param int $drugtypeid 上级分类ID
	*/
	public function lowertypelst($userid,$usertoken,$typetype,$drugtypeid)
	{
	
		try
		{
			if ($typetype != 3) {
				$key='apidrugs_lowertypelst_'.$typetype.'_'.$drugtypeid;
				if (Cache::store('memcached')->has($key)) {
					$return = Cache::store('memcached')->get($key);
				}
			}
			
			else
			{
				switch ($typetype) 
				{

					case 1://西药数据分类
						
						$query = $this->_drugdb->table('drugtypecatalog')
												->select('DrugID','DrugTypeName')
												->where('PeopleType','like','%0%')
												->where(function($query) use ($drugtypeid){
													$query->where('DrugFatherID','like',$drugtypeid)
															->orWhere('DrugFatherID','like','%,'.$drugtypeid)
															->orWhere('DrugFatherID','like',$drugtypeid.',%')
															->orWhere('DrugFatherID','like','%,'.$drugtypeid.',%');
														});
						
						break;

					case 2://中药数据分类
						$query = $this->_drugdb->table('chinadrugtype')
											->select('DrugTypeID AS DrugID','DrugTypeName','IsLower')
											->where('DrugTypeFID',$drugtypeid);
						break;

					case 3:
						if (strstr($drugtypeid, '-')) 
						{
							$drugtypeid=rtrim($drugtypeid,'-');
							$query = $this->_drugdb->table('drugtypecatalog')
													->where('PeopleType','like',"%$drugtypeid%")
													->where('DrugFatherID',$drugtypeid);
						}
						else
						{
							$query = $this->_drugdb->table('drugtypecatalog')
												->select('DrugID','DrugTypeName')
												->where('PeopleType','like','%0%')
												->where(function($query) use ($drugtypeid){
													$query->where('DrugFatherID','like',$drugtypeid)
															->orWhere('DrugFatherID','like','%,'.$drugtypeid)
															->orWhere('DrugFatherID','like',$drugtypeid.',%')
															->orWhere('DrugFatherID','like','%,'.$drugtypeid.',%');
												});
						}
						break;
				}

				$lowertypelst = $query->get();
				if($typetype!=2)
				{
					foreach ($lowertypelst as $key => $obj) 
					{
						$lowerdata = $this->_drugdb->table('drugtypecatalog')
										->select('DrugID','DrugTypeName')
										->where('PeopleType','like','%0%')
										->where(function($query) use ($drugtypeid){
											$query->where('DrugFatherID','like',$drugtypeid)
													->orWhere('DrugFatherID','like','%,'.$drugtypeid)
													->orWhere('DrugFatherID','like',$drugtypeid.',%')
													->orWhere('DrugFatherID','like','%,'.$drugtypeid.',%');
											})
										->get();
						if(count($lowerdata)>0)
						{
							$obj->IsLower=0;
						}
						else
						{
							$obj->IsLower=1;
						}
					}
				}
				$return = array(
					"status" => 0,
					"msg" => "success",
					"data"=>$lowertypelst
				);
				if($typetype!=3)
				{
					Cache::store('memcached')->put($key,$return,60);//60分钟
				}
			}	

		}
		catch(Exception $e)
		{
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
				);
		}
		return Response::json($return);

	}

	/**
	* 根据药品成分获取药品通用名集合
	* @param int $userid 用户ID
	* @param string $usertoken 用户token
	* @param int $ingredientid 药品成分ID
	* @param int $typetype 分类类型1西药数据分类；2中药分类；3用药人群
	* @param int $peopletypeid 上一个参数为3时接收此参数；用药人群分类ID
	*/
	public function commonamelst($userid,$usertoken,$ingredientid,$typetype,$peopletypeid=null)
	{

		try
		{
			$key='apidrugs_commonamelst'.$ingredientid."_".$typetype;
			if (Cache::store('memcached')->has($key)) {
				$return = Cache::store('memcached')->get($key);
			}
			else
			{
				var_dump($peopletypeid);exit();
				switch ($typetype) {
					case 1:
						$query = $this->_drugdb->table('drugcomname')
												->select('NameID','DrugName')
												->where('FatherID',0)
												->where('DrugRemark',$ingredientid);
						break;
					case 2:
						$query = $this->_drugdb->table('chinacommon')
												->select('DrugCommID AS NameID','DrugCommName AS DrugName','PicUrl')
												->where('DrugTypeID',$ingredientid);
						break;
					case 3:
						if($peopletypeid==1)
						{
							$query = $this->_drugdb->table('drugcomname')
													->select('NameID','DrugName')
													->where('FatherID',0)
													->where('DrugRemark',$ingredientid)
													->where('PeopleType','like',"%$peopletypeid%");
						}
						else
						{
							$query = $this->_drugdb->table('drugcomname')
													->select('NameID','DrugName')
													->where('FatherID',0)
													->where('DrugRemark',$ingredientid);
						}
						break;
				}
				$data = $query->get();
				$return = array(
					"status" => 0,
					"msg" => "success",
					"data" => $data
				);
				Cache::store('memcached')->put($key,$return,60);//60分钟
			}
		}
		catch(Exception $e)
		{
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}
	/**
	* 根据通用名ID得到通用名说明书名称以及商品名说明书列表
	* @param int $userid 用户ID
	* @param string $usertoken 用户token
	* @param int $commonid 通用名ID
	* @param int $typetype 分类类型1西药数据分类和用药人群；2中成药分类
	* @param int $cpage 请求页码
	*/
	public function tradenamelst($userid,$usertoken,$commonid,$typetype,$cpage)
	{
		try 
		{
			$key='apidrugs_tradenamelst_'.$typetype."_".$commonid."_".$cpage;
			if (Cache::store('memcached')->has($key)) {
				$return = Cache::store('memcached')->get($key);
			}
			else
			{

				$start = ($cpage-1)*10;
				if ($typetype == 1) 
				{
					$proinstruction = $this->_drugdb->table('druginfo')
													->select('DrugID','ProduceName','ProduceCompany')
													->where('GeneralName',$commonid)
													->where('IsFinish',1)
													->skip($start)
													->take(10)
													->get();
					if ($cpage == 1) 
					{
						$comminstruction = $this->_drugdb->table('druginstruction')
													->select('InstructionID AS DrugID','InstructionTitle AS ProduceName')
													->where('DrugName',$commonid)
													->first();

//						if ($comminstruction === null) {
//							$comminstruction = new \stdClass();
//							$comminstruction->ProduceName = "";
//						}
						$instrname=explode("说明书",$comminstruction->ProduceName??"");
						$comminstruction->ProduceName=$instrname[0];
						$comminstruction->ProduceCompany="通用说明书";		
						$proinstruction=array_merge(array(0=>(array)$comminstruction),(array)$proinstruction);
					}
				}
				else
				{
					$proinstruction = $this->_drugdb->table('chinadruginfo')
											->select('InstructionID AS DrugID','ProduceName','ProduceCompany') 
											->where('DrugName',$commonid)
											->where('IsFinish',1)
											->skip($start)
											->take(10)
											->get();
					if ($cpage == 1) 
					{
						$comminstruction = $this->_drugdb->table('chinainstruction')
											->select('InstructionID AS DrugID','DrugName AS ProduceName')
											->where('DrugName',$commonid)
											->first();

						$comname = $this->_drugdb->table('chinacommon')
											->where('DrugCommID',$commonid)
										->first();
						if ($comminstruction === null) {
							$comminstruction = new \stdClass();
						}
						if ($comname === null) {
							$comname = new \stdClass();
							$comname->DrugCommName="";
						}
						$comminstruction->ProduceName =$comname->DrugCommName;
						$comminstruction->ProduceCompany ='通用说明书';
						$proinstruction=array_merge((array)$comminstruction,(array)$proinstruction);
					}

				}
				$return=array(
						"status"=>0,
						"msg"=>"success",
						"data"=>$proinstruction
					);
				Cache::store('memcached')->put($key,$return,60);//60分钟
			}
		}
		catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}


 	/**
	 * 根据药品ID得到对应的说明书信息
     * @param int $userid  用户ID
     * @param string $usertoken  用户token
     * @param int $drugid   药品ID/通用名ID（typetype为3,4）
     * @param int $typetype   分类类型1西药数据分类和用药人群;2中成药分类;3中药饮片;4方剂
     * @param int $msgtype   0通用说明书；1商品说明书
	 */
	public function drugmsginfo($userid,$usertoken,$drugid,$typetype,$msgtype)
	{
		try {
			$key='apidrugs_drugmsginfo_'.$typetype."_".$msgtype."_".$drugid;
			if (Cache::store('memcached')->has($key)) {
				$drugmsginfo = Cache::store('memcached')->get($key);
			}
			else
			{
				$keydata=null;
				$commondata=null;
				$drugname=null;
				switch ($typetype) 
				{
					case 1:
						$seltable = $msgtype==1?'druginfo':'druginstruction';
						$seltableid = $msgtype==1?'DrugID':'InstructionID';
						$commid = $msgtype==1?'GeneralName':'DrugName';

						//根据药品ID获得说明书信息
						$instruction = $this->_drugdb->table($seltable)->where($seltableid,$drugid)->first();
						if (!empty($instruction)) 
						{
							//获取说明书单独保存出来的数据
							$drugdetailmsg = $this->_drugdb->table('drugdetailmsg')
															->where('MsgType',$msgtype)
															->where('DrugID',$drugid)
															->first();
							//说明书重点列表
							//得到药品成分名称
							//得到成分ID
							$elementid = $this->_drugdb->table('drugcomname')
														->where('NameID',$instruction->$commid)
														->first();

							//获取成分名称
							$elementname = $this->_drugdb->table('drugtypecatalog')
														->select('DrugTypeName')
														->where('DrugID',$elementid->DrugRemark)
														->first();
							$arr["title"]='药品成分';//根据通用名ID查询到对应的成分
							$arr["content"]=$elementname->DrugTypeName;
							$keydata[]=$arr;
							if(!empty($instruction->ChemicalComponent))
							{
								$arr["title"]="化学成分";
								$arr["content"]=$instruction->ChemicalComponent;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugCharacter))
							{
								$arr["title"]="性状";
								$arr["content"]=$drugdetailmsg->DrugCharacter;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugIndication))
							{
								$arr["title"]="适应症";
								$arr["content"]=$instruction->DrugIndication;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugUsage))
							{
								$arr["title"]="用法用量";
								$arr["content"]=$instruction->DrugUsage;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugAndFood))
							{
								$arr["title"]="服药与进食";
								$arr["content"]=$instruction->DrugAndFood;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->AdverseReaction))
							{
								$arr["title"]="不良反应";//详细信息里面
								$arr["content"]=$drugdetailmsg->AdverseReaction;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugTaboo))
							{
								$arr["title"]="禁忌";//详细信息里面
								$arr["content"]=$drugdetailmsg->DrugTaboo;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugWarning))
							{
								$arr["title"]="警告";
								$arr["content"]=$instruction->DrugWarning;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugMatters))
							{
								$arr["title"]="注意事项";//详细信息里面
								$arr["content"]=$drugdetailmsg->DrugMatters;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugInstruct))
							{
								$arr["title"]="用药须知";
								$arr["content"]=$instruction->DrugInstruct;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugOverdose))
							{
								$arr["title"]="药物过量";
								$arr["content"]=$instruction->DrugOverdose;
								$keydata[]=$arr;
							}
							//读取用药人群
							$usepersons = $this->_drugdb->table('drugusecrowd')
														->select('AdviceType','UseAdvice','CrowdType')
														->where('DataSource',$msgtype==0?1:0)
														->where('DrugID',$drugid)
														->get();
							if(count($usepersons)>0)
							{
								$arr["title"]="用药人群";
								$arr["content"]=$usepersons;
								$keydata[]=$arr;
							}
							
							if($instruction->FDALevel!=-1)
							{
								$fdarr=array(0=>"A级",1=>"B级",2=>"C级",3=>"D级",4=>"X级");
								$arr["title"]="FDA妊娠药物分级";
								$arr["content"]=$fdarr[$instruction->FDALevel]."：".$instruction->FDARemark;
								$keydata[]=$arr;
							}

							if(!empty($instruction->DrugInteraction))
							{
								$arr["title"]="药物相互作用";
								$arr["content"]=$instruction->DrugInteraction;
								$keydata[]=$arr;
							}
							if(!empty($instruction->ProduceCompany))
							{
								$arr["title"]="生产企业";
								$arr["content"]=$instruction->ProduceCompany;
								$keydata[]=$arr;
							}
							if(!empty($instruction->DrugPic))
							{
								$arr["title"]="药品图片";
								$arr["content"]=$instruction->DrugPic;
								$keydata[]=$arr;
							}
							if(!empty($instruction->StandardCode))
							{
								$arr["title"]="药品本位码";
								$arr["content"]=$instruction->StandardCode;
								$keydata[]=$arr;
							}
							
							//其他辅助信息
							if(!empty($drugdetailmsg->DrugEffects))
							{
								$arr["title"]="药理作用";
								$arr["content"]=$drugdetailmsg->DrugEffects;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DuliResearch))
							{
								$arr["title"]="毒理研究";
								$arr["content"]=$instruction->DuliResearch;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ClinicalShiyan))
							{
								$arr["title"]="临床试验";
								$arr["content"]=$instruction->ClinicalShiyan;
								$commondata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->Pharmacokinetic))
							{
								$arr["title"]="药代动力学";
								$arr["content"]=$drugdetailmsg->Pharmacokinetic;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugType))
							{
								$arr["title"]="剂型";
								$arr["content"]=$instruction->DrugType;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugModel))
							{
								$arr["title"]="规格";
								$arr["content"]=$instruction->DrugModel;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugPack))
							{
								$arr["title"]="包装";
								$arr["content"]=$instruction->DrugPack;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugStore))
							{
								$arr["title"]="储藏";
								$arr["content"]=$instruction->DrugStore;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->EffectDate))
							{
								$arr["title"]="有效期";
								$arr["content"]=$instruction->EffectDate;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ATCcode))
							{
								$arr["title"]="ATC编码";
								$arr["content"]=$instruction->ATCcode;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ApproveNumber))
							{
								$arr["title"]="批准文号";
								$arr["content"]=$instruction->ApproveNumber;
								$commondata[]=$arr;
							}
							//监管分级
							if($instruction->RegularLevel!=-1)
							{
								$regularr=array(0=>"国家基本药物目录",1=>"国家基本医疗保险和工伤保险药品",2=>"OTC（非处方药）",3=>"3精神药品和麻醉药品",4=>"运动员禁用的药物");
								$regule=explode(",",$instruction->RegularLevel);
								$arr["title"]="监管分级";
								for($i=0;$i<count($regule);$i++)
								{
									if($i==0)
									{
										$arr["content"]=$regularr[$regule[$i]];
									}
									else
									{
										$arr["content"].=";".$regularr[$regule[$i]];
									}
								}
								$commondata[]=$arr;
							}
							if(!empty($instruction->InstructionTitle)||!empty($instruction->ProduceName))
							{
								$drugname=array(
									"drugtitle"=>$msgtype==0?$instruction->InstructionTitle:$instruction->ProduceName."说明书",
									"drugenname"=>$instruction->EnDrugName
								);
							}

						}
						$drugmsginfo = array(
							"keydata" => $keydata,
							"commondata" => $commondata,
							"drugname"=>$drugname
						);
						break;
					
					case 2:
						$seltbale = $msgtype ==1?'chinadruginfo':'chinainstruction';
						//根据通用名ID获得说明书信息
						$instruction = $this->_drugdb->table($seltbale)
													->where('InstructionID',$drugid)
													->first();
						if(!empty($instruction))
						{
							//获取说明书单独保存出来的数据
							$drugdetailmsg = $this->_drugdb->table('drugdetailmsg')
															->where('MsgType',$msgtype==0?2:3)
															->where('DrugID',$drugid)
															->first();
							//说明书重点列表											
							if(!empty($instruction->DrugEffect))
							{
								$arr["title"]="功能主治";
								$arr["content"]=$instruction->DrugEffect;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugComponent))
							{
								$arr["title"]="药物组成";
								$arr["content"]=$instruction->DrugComponent;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugIndication))
							{
								$arr["title"]="临床应用";
								$arr["content"]=$instruction->DrugIndication;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugUsage))
							{
								$arr["title"]="用法用量";
								$arr["content"]=$instruction->DrugUsage;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->AdverseReaction))
							{
								$arr["title"]="不良反应";//详细信息里面
								$arr["content"]=$drugdetailmsg->AdverseReaction;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugMatters))
							{
								$arr["title"]="注意事项";//详细信息里面
								$arr["content"]=$drugdetailmsg->DrugMatters;
								$keydata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugTaboo))
							{
								$arr["title"]="禁忌";//详细信息里面
								$arr["content"]=$drugdetailmsg->DrugTaboo;
								$keydata[]=$arr;
							}
							
							//其他辅助信息
							if(!empty($instruction->DrugCharacter))
							{
								$arr["title"]="性状";
								$arr["content"]=$instruction->DrugCharacter;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->WestZhenduan))
							{
								$arr["title"]="西医诊断";
								$arr["content"]=$instruction->WestZhenduan;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugRecipe))
							{
								$arr["title"]="方解";
								$arr["content"]=$instruction->DrugRecipe;
								$commondata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->DrugEffects))
							{
								$arr["title"]="药理作用";
								$arr["content"]=$drugdetailmsg->DrugEffects;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DuliResearch))
							{
								$arr["title"]="毒理作用";
								$arr["content"]=$instruction->DuliResearch;
								$commondata[]=$arr;
							}
							
							if(!empty($drugdetailmsg->Pharmacokinetic))
							{
								$arr["title"]="药代动力学";
								$arr["content"]=$drugdetailmsg->Pharmacokinetic;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugStandard))
							{
								$arr["title"]="规格";
								$arr["content"]=$instruction->DrugStandard;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugPack))
							{
								$arr["title"]="包装";
								$arr["content"]=$instruction->DrugPack;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugStore))
							{
								$arr["title"]="储藏";
								$arr["content"]=$instruction->DrugStore;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->EffectDate))
							{
								$arr["title"]="有效期";
								$arr["content"]=$instruction->EffectDate;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ExecuteStandard))
							{
								$arr["title"]="执行标准";
								$arr["content"]=$instruction->ExecuteStandard;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ApproveNumber))
							{
								$arr["title"]="批准文号";
								$arr["content"]=$instruction->ApproveNumber;
								$commondata[]=$arr;
							}
							
							//监管分级
							if($instruction->RegularLevel!=-1)
							{
								$regularr=array(0=>"国家基本药物目录",1=>"国家基本医疗保险和工伤保险药品",2=>"OTC（非处方药）",3=>"3精神药品和麻醉药品",4=>"运动员禁用的药物");
								$regule=explode(",",$instruction->RegularLevel);
								$arr["title"]="监管分级";
								for($i=0;$i<count($regule);$i++)
								{
									if($i==0)
									{
										$arr["content"]=$regularr[$regule[$i]];
									}
									else
									{
										$arr["content"].=";".$regularr[$regule[$i]];
									}
								}
								$commondata[]=$arr;
							}
							$drugname=array(
								"drugtitle"=>$instruction->ProduceName,
								"drugenname"=>$instruction->DrugPinyin
							);
							if($msgtype==0)
							{
								$elementid = $this->_drugdb->table('chinacommon')
															->select('DrugTypeID','DrugCommName')
															->where('DrugCommID',$instruction->DrugName)
															->first();
								$drugname['drugtitle']=$elementid->ProduceName;
							}
						}
						$drugmsginfo = array(
							"keydata" => $keydata,
							"commondata" => $commondata,
							"drugname"=>$drugname
						);
						break;
					case 3:
						//根据药品ID获得说明书信息
						$instruction = $this->_drugdb->table('chinayinpian')->where('DrugName',$drugid)->first();
						if(!empty($instruction))
						{
							//获取说明书饮片图片信息
							$drugdetailmsg = $this->_drugdb->table('chinayinpiandetail')
															->select('DetailData')
															->where('DrugID',$instruction->InstructionID)
															->where('DataType',0)
															->first();
							//说明书重点列表
						
							$elementid = $this->_drugdb->table('chinacommon')->select('DrugCommName')->where('DrugCommID',$drugid)->first();
							$arr["title"]="通用名称";//根据通用名ID查询到对应的通用名称
							$arr["content"]=$elementid->DrugCommName;
							$keydata[]=$arr;
							
							if(count($drugdetailmsg)>0)
							{
								$arr["title"]="饮片图片";
								$arr["content"]=$drugdetailmsg;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->YinPianOther))
							{
								$arr["title"]="别名";
								$arr["content"]=$instruction->YinPianOther;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->ProcessMethod))
							{
								$arr["title"]="炮制方法";
								$arr["content"]=$instruction->ProcessMethod;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->FunctionAttending))
							{
								$arr["title"]="功能与主治";
								$arr["content"]=$instruction->FunctionAttending;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->YinPianUsage))
							{
								$arr["title"]="用法用量";
								$arr["content"]=$instruction->YinPianUsage;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->Coordinating))
							{
								$arr["title"]="配伍应用";
								$arr["content"]=$instruction->Coordinating;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugMatters))
							{
								$arr["title"]="注意事项";
								$arr["content"]=$instruction->DrugMatters;
								$keydata[]=$arr;
							}
							
							//其他辅助信息
							if(!empty($instruction->YinPianSource))
							{
								$arr["title"]="饮片来源";
								$arr["content"]=$instruction->YinPianSource;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->MainAddress))
							{
								$arr["title"]="饮片主产地";
								$arr["content"]=$instruction->MainAddress;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->YinPianCharacter))
							{
								$arr["title"]="性状";
								$arr["content"]=$instruction->YinPianCharacter;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ChemicalComponent))
							{
								$arr["title"]="化学成分";
								$arr["content"]=$instruction->ChemicalComponent;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->UtilityAnalysis))
							{
								$arr["title"]="效用分析";
								$arr["content"]=$instruction->UtilityAnalysis;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->MedicaDigest))
							{
								$arr["title"]="本草摘要";
								$arr["content"]=$instruction->MedicaDigest;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugEffects))
							{
								$arr["title"]="药理作用";
								$arr["content"]=$instruction->DrugEffects;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DuliResearch))
							{
								$arr["title"]="毒理作用";
								$arr["content"]=$instruction->DuliResearch;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ProcessBody))
							{
								$arr["title"]="体内过程";
								$arr["content"]=$instruction->ProcessBody;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugStore))
							{
								$arr["title"]="储藏";
								$arr["content"]=$instruction->DrugStore;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->AdverseReaction))
							{
								$arr["title"]="不良反应";
								$arr["content"]=$instruction->AdverseReaction;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugModel))
							{
								$arr["title"]="规格";
								$arr["content"]=$instruction->DrugModel;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->EffectDate))
							{
								$arr["title"]="有效期";
								$arr["content"]=$instruction->EffectDate;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->ApproveNumber))
							{
								$arr["title"]="批准文号";
								$arr["content"]=$instruction->ApproveNumber;
								$commondata[]=$arr;
							}
							$drugname=array(
								"drugtitle"=>$elementid->DrugCommName,
								"drugenname"=>$instruction->PinYin
							);
						}
						$drugmsginfo = array(
							"keydata" => $keydata,
							"commondata" => $commondata,
							"drugname"=>$drugname
						);
						break;
					case 4:
						 //根据通用名ID获得说明书信息
						$instruction = $this->_drugdb->table('chinafangji')->where('DrugName',$drugid)->first();
						// var_dump($instruction);exit();
						if(!empty($instruction))
						{
							//说明书重点列表
							$elementid = $this->_drugdb->table('chinacommon')->select('DrugCommName')->where('DrugCommID',$drugid)->first();
							$arr["title"]="通用名称";//根据通用名ID查询到对应的通用名称
							$arr["content"]=$elementid->DrugCommName;
							$keydata[]=$arr;
							
							if(!empty($instruction->OtherName))
							{
								$arr["title"]="别名";
								$arr["content"]=$instruction->OtherName;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugSource))
							{
								$arr["title"]="出处";
								$arr["content"]=$instruction->DrugSource;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugComponent))
							{
								$arr["title"]="组成";
								$arr["content"]=$instruction->DrugComponent;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugUsage))
							{
								$arr["title"]="用法";
								$arr["content"]=$instruction->DrugUsage;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugEffect))
							{
								$arr["title"]="功用";
								$arr["content"]=$instruction->DrugEffect;
								$keydata[]=$arr;
							}
							
							if(!empty($instruction->DrugIndication))
							{
								$arr["title"]="主治";
								$arr["content"]=$instruction->DrugIndication;
								$keydata[]=$arr;
							}
							
							//其他辅助信息
							if(!empty($instruction->DrugRecipe))
							{
								$arr["title"]="方解";
								$arr["content"]=$instruction->DrugRecipe;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugImportant))
							{
								$arr["title"]="辩证要点";
								$arr["content"]=$instruction->DrugImportant;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugAdd))
							{
								$arr["title"]="加减变化";
								$arr["content"]=$instruction->DrugAdd;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugModern))
							{
								$arr["title"]="现代运用";
								$arr["content"]=$instruction->DrugModern;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugMatter))
							{
								$arr["title"]="使用注意";
								$arr["content"]=$instruction->DrugMatter;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugAbstract))
							{
								$arr["title"]="文献摘要";
								$arr["content"]=$instruction->DrugAbstract;
								$commondata[]=$arr;
							}
							
							if(!empty($instruction->DrugLinchuang))
							{
								$arr["title"]="临床报道";
								$arr["content"]=$instruction->DrugLinchuang;
								$commondata[]=$arr;
							}
							$drugname=array(
								"drugtitle"=>$elementid->DrugCommName,
								"drugenname"=>$instruction->DrugPinyin
							);
						}

						$drugmsginfo = array(
							"keydata" => $keydata,
							"commondata" => $commondata,
							"drugname"=>$drugname
						);
						break;
				}
				
				Cache::store('memcached')->put($key,$drugmsginfo,60);//60分钟
			}
			
			$return = array(
					"status" => 0,
					"msg" => "success",
					"keydata" =>$drugmsginfo['keydata'],
					"commondata" =>$drugmsginfo['commondata'],
					"drugtitle"=>$drugmsginfo['drugname']['drugtitle'],
					"drugenname"=>$drugmsginfo['drugname']['drugenname']
				);

		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}

	/**
	 * 根据药品 或适应症或者相互作用获取数据信息（搜索）
     * @param int $userid  用户ID
     * @param string $usertoken  用户token
     * @param int $keywords   查询关键词
     * @param int $searchtype 查询类型1；药品；2适应症；3相互作用
     * @param int $cpage 查询结果请求页码
     * 查询类型1的时候：西药 中成药 中药饮片都可能会有数据；
     * 查询类型2的时候：西药 中成药都可能会有数据；
     * 查询类型3的时候：西药 中药饮片都可能会有数据；
	 */
	public function searchdrugmsg($userid,$usertoken,$keywords,$searchtype,$cpage)
	{
		try {
				$start=($cpage-1)*20;
				$drugmsg=array();
				//根据药品检索（检索关键词药品通用名/成分）
				if ($searchtype == 1)
				{
					//根据通用名获取通用名ID
					$query = $this->_drugdb->table('drugcomname')
											->where('FatherID',0)
											->where('DrugName','like',"%$keywords%");

					//符合条件的通用名ID集合
					$commarr = $query->pluck('DrugName','NameID');
					$comnameids = $query->pluck('NameID');
			
					// 检索出符合条件的成分以及包含这种成分的通用名ID
					$elemids = $this->_drugdb->table('drugtypecatalog')
											->where('DrugTypeName','like',"%$keywords%")
											->pluck('DrugID');
					if(!empty($elemids))
					{
						// 检索出符合条件的成分集合下的所有通用名ID
						$comname2 = $this->_drugdb->table('drugcomname')
												->where('FatherID',0)
												->whereIn('DrugRemark',$elemids)
												->pluck('DrugName','NameID');

						// 将这些通用名ID存入到通用名ID集合检索数组中
						foreach ($comname2 as $nid =>$dname)
						{
							$commarr[$nid] = $dname;
							$comnameids[] = $nid;
						}
					}
					//根据商品名
					$ids = $this->_drugdb->table('druginfo')
						->where('IsFinish',1)
						->where('ProduceName','like',"%$keywords%")
						->pluck('GeneralName');
					if (!empty($ids))
					{
						$comname3 = $this->_drugdb->table('drugcomname')
							->where('FatherID',0)
							->whereIn('NameID',$ids)
							->pluck('DrugName','NameID');
						foreach ($comname3 as $nid=>$dname)
						{
							$commarr[$nid] = $dname;
							$comnameids[] = $nid;
						}
					}
					if(!empty($comnameids))
					{
						// 根据以上获取到的查询条件（通用名ID集合）检索符合条件的说明书
						$drugmsg = $this->_drugdb->table('druginstruction')
												 ->select("InstructionID","DrugName","InstructionTitle","EnDrugName")
												 ->whereIn('DrugName',$comnameids)
												 ->orWhere('InstructionTitle','like',"%$keywords%")
												 ->skip($start)
												 ->take(20)
												 ->get();

						foreach ($drugmsg as $key => $drugmsgobj) {
//							try {
//								$drugmsgobj->DrugName2 = $commarr[$drugmsgobj->DrugName];
//							} catch (Exception $e) {
//								$drugmsgobj->DrugName2 = '';
//							}
							$drugmsgobj->DrugName2 = $commarr[$drugmsgobj->DrugName]??'';
							
							$drugmsgobj->DataType=1;//西药检索出来的数据e
						}
					}
					//中药数据检索
	
					$zhong = $this->_drugdb->table('chinacommon')->where('DrugCommName','like',"%$keywords%");
					$zcommarr = $zhong->pluck('DrugCommName','DrugCommID');
					
					$zhongcommids = $zhong->pluck('DrugCommID');
					if(!empty($zhongcommids))
					{
						// 根据以上获取到的查询条件（通用名ID集合）检索符合条件的说明书
						$zdrugmsg = $this->_drugdb->table('chinainstruction')
													->select('InstructionID','DrugName')
													->whereIn('DrugName',$zhongcommids)
													->skip($start)
													->take(20)
													->get();
			
						foreach ($zdrugmsg as $key => $zdrugmsgobj) {
//							try {
//								$zdrugmsgobj->DrugName2=$zcommarr[$zdrugmsgobj->DrugName];
//							} catch (Exception $e) {
//								$zdrugmsgobj->DrugName2='';
//							}
							$zdrugmsgobj->DrugName2=$zcommarr[$zdrugmsgobj->DrugName]??'';
							$zdrugmsgobj->DataType=2;//中成药检索出来的数据
						}
						//中药饮片数据检索
						$zydrugmsg = $this->_drugdb->table('chinayinpian')
													->select('InstructionID','DrugName')
													->whereIn('DrugName',$zhongcommids)
													->skip($start)
													->take(20)
													->get();
					
						foreach ($zydrugmsg as $key => $zydrugmsgobj) {
//							try {
//								$zydrugmsgobj->DrugName2=$zcommarr[$zydrugmsgobj->DrugName];
//							} catch (Exception $e) {
//								$zydrugmsgobj->DrugName2='';
//							}
							$zydrugmsgobj->DrugName2=$zcommarr[$zydrugmsgobj->DrugName]??'';
							$zydrugmsgobj->DataType=3;//中药饮片检索出来的数据
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
				
				}
					//根据适应症检索数据
				else if ($searchtype == 2)
				{
						//检索出符合条件适应症ID集合
						$codes = $this->_drugdb->table('diseasecode')
												->where('DiseaseName','like',"%$keywords%")
												->orWhere('DiseaseMsg','like',"%$keywords%")
												->orWhere('DiseaseCode','like',"%$keywords%")
												->pluck('DiseaseID');
						if(!empty($codes))
						{
				
							// 根据适应症集合以及别名检索出来符合条件的说明书ID集合
							$drugids = $this->_drugdb->table('drugusage')
														->where('DataType',2)
														->where('DataSource',1)
														->where(function($query) use ($keywords,$codes){
															$query->where('OtherName','like',"%$keywords%")
																  ->whereIn('DiseaseID',$codes);
														})
														->pluck('DrugID');
						}
						
						if(!empty($drugids))
						{
							// 根据以上获取到的查询条件（说明书ID集合）检索符合条件的说明书
							$drugmsg = $this->_drugdb->table('druginstruction')
													->select('InstructionID','DrugName','InstructionTitle','EnDrugName')
													->whereIn('InstructionID',$drugids)
													->skip($start)
													->take(20)
													->get();
							foreach ($drugmsg as $key => $drugmsgobj) {
								$comname = $this->_drugdb->table('drugcomname')
														 ->select('DrugName')
														 ->where('NameID',$drugmsgobj->DrugName)
														 ->first();
								$drugmsgobj->DrugName2=$comname->DrugName;
								$drugmsgobj->DataType=1;//西药检索出来的数据
							}
						}
					
						//中成药数据检索
						$zcodes = $this->_drugdb->table('chinadiscode')
													->where('DisCodeName','like',"%$keywords%")
													->orWhere('DisCode','like',"%$keywords%")
													->pluck('DisCodeID');
						if(!empty($zcodes))
						{
							// 根据适应症集合检索出来符合条件的说明书ID集合
							$zdrugids = $this->_drugdb->table('drugusage')
													->where('DataType',2)
													->where('DataSource',3)
													->orWhereIn('DiseaseID',$zcodes)
													->pluck('DrugID');
						}
						if(!empty($zdrugids))
						{
							// 根据中药检索出来的数据信息找出对应说明书
							$zdrugmsg = $this->_drugdb->table('chinainstruction')
														->select('InstructionID','DrugName')
														->whereIn('InstructionID',$zdrugids)
														->skip($start)
														->take(20)
														->get();

							foreach ($zdrugmsg as $zdrugmsgobj)
							{
								$comname = $this->_drugdb->table('chinacommon')
															->select('DrugCommName')
															->where('DrugCommID',$zdrugmsgobj->DrugName)
															->first();
								$zdrugmsgobj->DrugName2=$comname->DrugCommName;
								$zdrugmsgobj->DataType=2;//中药检索出来的数据
							}
						}
						if(!empty($zdrugmsg))
						{
							//中成药和西药检索出来的数据合并
							$drugmsg=array_merge($drugmsg,$zdrugmsg);
						}
				}
				else if($searchtype==3)//根据药物相互作用检索
				{
					// 按照成分检索
					$typeid = $this->_drugdb->table('drugtypecatalog')
											->select('DrugID','DrugTypeName')
											->where('DrugTypeName','like',"%$keywords%")
											->skip($start)
											->take(20)
											->get();
					if(!empty($typeid))
					{
						foreach($typeid as $t)
						{
							// 作为主成分的情况下
							// 找到通用名
							$comms=$this->_drugdb->table('drugcomname')
												->select('NameID','DrugName')
												->where('FatherID',0)
												->where('DrugRemark',$t->DrugID)
												->first();
							if(!empty($comms))
							{
								$comms->DrugTypeName=$t->DrugTypeName;
								$comms->DataType=1;
								$drugmsg[]=$comms;
							}
						}
					}
					
				   //中药数据检索
					$zhong = $this->_drugdb->table('chinacommon')
											->select('DrugCommID','DrugCommName')
											->where('DrugCommName','like',"%$keywords%")
											->get();
					if(!empty($zhong))
					{
						foreach($zhong as $e)
						{
							$zcommarr[$e->DrugCommID]=$e->DrugCommName;
							$zhongcommids[]=$e->DrugCommID;
						}
					}
					
					if(!empty($zhongcommids))
					{
						//中药饮片数据检索
						$zydrugmsg = $this->_drugdb->table('chinayinpian')
													->select('DrugName AS NameID')
													->whereIn('DrugName',$zhongcommids)
													->skip($start)
													->take(20)
													->get();
						
						foreach ($zydrugmsg as $key => $zydrugmsgobj) {
							$zydrugmsgobj->DrugName = $zcommarr[$zydrugmsgobj->DrugName];
							$zydrugmsgobj->DataType = 3;
						}
					}
					if(!empty($zydrugmsg))
					{
					    //中药饮片和西药以及中成药检索出来的数据合并
					    $drugmsg=array_merge($drugmsg,$zydrugmsg);
					}
					
				}
				switch ($searchtype)
				{
				    case 1:
				    case 2:
				        $url ='http://drugs.medlive.cn/api/drugNameList.do?Q='.$keywords.'&From=zhenlipai&Page='.$cpage.'&Num=10';
						$client = new Client();
						$ymtdata=$client->request('GET',$url)->getBody()->getContents();
				        break;
				}

				$return = array(
					"status" => 0,
					"msg" => "success",
					"data" => $drugmsg,
				    "ymt"=>$ymtdata
				);
			
		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}

	 /**
	 * 根据药品ID找出相互作用数据信息
     * @param int $userid  用户ID
     * @param string $usertoken  用户token
     * @param int $drugid 药品ID
     * @param int $typetype 查询类型0西药；1中药饮片
	 */
	public function effectdatav2($userid,$usertoken,$drugid,$typetype)
	{
		try {
				switch($typetype)
				{
					case 1://西药的相互作用

						$query = $this->_drugdb->table('drugelement')
												->select('ElementMsg','DrugContent','DataMsg')
												->where('DrugID',$drugid)
												->where('DataType',1)
												->where('DataSource',1);
						break;
						
					case 2://中药饮片的相互作用

						$query = $this->_drugdb->table('chinayinpiandetail')
											->select('DetailMsg AS ElementMsg','DataMsg FROM chinayinpiandetail')
											->where('DrugID',$drugid)
											->where('DataType',4);
						break;
				}	
				$effect=$query->get();
				$return = array(
					"status"=>0,
					"msg" =>"success",
					"data"=>$effect
				);
			
		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}

	/**
	  *热门搜索数据接口(药品显示的是：通用名。  适应症：显示的是疾病名。 相互作用显示成分 返回10条数据)
	  *@param int $userid  用户ID
	  *@param string $usertoken  用户token
	  *@param int $searchtype 查询类型1；药品，2适应症；3相互作用；
	  */
	public function hotsearch($userid,$usertoken,$searchtype)
	{
		try {
					//热搜成分数据信息
					$elementarr=array(
						array("DrugName"=>"头孢克肟"),
						array("DrugName"=>"氯霉素"),
						array("DrugName"=>"异烟肼"),
						array("DrugName"=>"氢氧化铝"),
						array("DrugName"=>"西咪替丁"),
						array("DrugName"=>"阿司匹林"),
						array("DrugName"=>"米非司酮"),
						array("DrugName"=>"布洛芬"),
						array("DrugName"=>"多潘立酮"),
						array("DrugName"=>"硝苯地平")
					);
	 				if ($searchtype == 1)
	 				{
	 					//随机返回5条通用名
	 					$datamsg = $this->_drugdb->table('drugcomname')
	 												->select('DrugName')
	 												->where('FatherID',0)
	 												->where('IsHot',1)
	 												->take(5)
	 												->get();

	 					//查询5条中药通用名
	 					$datamsg1 = $this->_drugdb->table('chinacommon')
	 												->select('DrugCommName AS DrugName')
	 												->where('IsHot',1)
	 												->take(5)
	 												->get();
						$elnew=array_slice($elementarr,0,5);
						$datamsg=array_merge($elnew,$datamsg,$datamsg1);

	 				}
	 				elseif ($searchtype==2)
	 				{	//随机返回10条疾病名称
	 					$datamsg = $this->_drugdb->table('diseasecode')
	 												->where('IsHot',1)
	 												->take(10)
	 												->get();
	 				}
	 				else
					{
						//随机返回成分名称
						$datamsg=$elementarr;
					}
	 				$return = array(
						"status" => 0,
						"msg" => "success",
						"data" => $datamsg
	 				);
			
		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}

	/**
	  *药品资讯数据信息接口
	  *@param int $userid  用户ID
	  *@param string $usertoken  用户token
	  *@param int $datatype 数据类型   0业内新闻；1最新进展；2药品专题
	  *@param int $cpage 请求页码
	  */
	public function drugmessage($userid,$usertoken,$datatype,$cpage)
	{
		try {
				$start=($cpage-1)*10;
				$data = Db::connection('mysql')->table('ymtinformation')
										->select('infoID','infoTitle','infoPicUrl','infoUrl','infoPublishTime','infoSource','infoRemark')
										->where('infoType',$datatype)
										->skip($start)
										->take(10)
										->get();
				$return=array(
					"status"=>0,
					"msg"=>"success",
					"data"=>$data
				);
			
		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}
	/**
	  *当前药品说明书关联的用药指南信息
	  *@param int $userid  用户ID
	  *@param string $usertoken  用户token
	  *@param int $drugid 说明书的通用名ID
	  *@param int $cpage 页码
	  *@return json 指南名称，出处，制定者，指南简介，年份，以及其他下载指南必要信息
	  */
	public function drugguidelst($userid,$usertoken,$drugid,$cpage)
	{
		try {
				if (Cache::store('file')->has('insdata')) {
					$insdataarray = Cache::store('file')->get('apidrugs_insdata');
				}
				else
				{

		            //抗微生物说明书id集合
		            $disonearray = $this->_drugdb->table('drugtypecatalog')->where('DrugFatherID',3)->pluck('DrugID');
		            $distwolist = $this->_drugdb->table('drugtypecatalog')
		              						 ->whereIn('DrugFatherID',$disonearray)
		              						 ->where('PeopleType','like','0%')
		              						 ->pluck('DrugID');
		            $insdataarray = $this->_drugdb->table('drugcomname')
		              								->where('FatherID',0)
		              								->whereIn('DrugRemark',$distwolist)
		              								->pluck('NameID');
		            Cache::store('file')->put('apidrug_insdata',$insdataarray,120);//120分钟  								
		        }
		        $data1=null;
       	       //查看该说明书是否属于抗微生物类型
              if (in_array($drugid, $insdataarray))
              {
	              //抗微生物类型指南
              	if (Cache::store('memcached')->has('api_drugs_antimicrobic')) {
					$data1 = Cache::store('memcached')->get('apidrugs_antimicrobic');
				}
				else
				{
	              	$data1 = DB::connection('mysql')->table('mediguide')
	              						->select('GuideID','GuideName','GuideClassify','GuideAuthor','GuidePress','GuideUrl','LoadNum','GuidePaper','FromType','YmtKey','GuideSize','GuideDerivation')
	              						->where('ReviewStatus',1)
	              						->where('DrugTypeID',3)
	              						->get();
	              	Cache::store('memcached')->put('apidrugs_antimicrobic',$data1,60);//120分钟
	             }
                
              }
			  //获取说明书ID
			  $newdrugid = $this->_drugdb->table('druginstruction')
			  							->select('InstructionID')
			  							->where('DrugName',$drugid)
			  							->first();
			  $newdrugid=$newdrugid->InstructionID;

			  if (!empty($newdrugid))
			  {
			      //根据说明书id得到疾病id集合
			      $disease = $this->_drugdb->table('drugusage')
			      						->where('DataType',2)
			      						->where('DataSource',1)
			      						->where('DrugID',$newdrugid)
			      						->pluck('DiseaseID');
			      if (!empty($disease))
			      {
			         
			          //根据疾病id得到指南对应的疾病id集合
			          $disguide = DB::connection('mysql')->table('mediguidetype')
			          							->whereIn('DiseaseID',$disease)
			          							->pluck('MediGuideTypeID');
			          if (!empty($disguide))
			          {
			              //根据指南对应的疾病id集合获取指南
			              $data2 = DB::connection('mysql')->table('mediguide')
			              						->select('GuideID','GuideName','GuideClassify','GuideAuthor','GuidePress','GuideUrl','LoadNum','GuidePaper','FromType','YmtKey','GuideSize','GuideDerivation')
			              						->where('GuideClassify',0)
			              						->where('ReviewStatus',1)
			              						->whereIn('DiseaseID',$disguide)
			              						->get();
			          }
			      }
			  }
              $data = array();
              //合并
              $data = array_merge((array)$data1,(array)$data2);
              //分割
              $start = ($cpage-1)*10;
              $data = array_slice($data,$start,10);
              
              $return=array(
				  "status"=>0,
				  "msg"=>"success",
				  "data"=>$data
              );
			
		} catch (Exception $e) {
			$return = array(
				"status" => 1,
				"msg" => "failed",
				"data" => ""
			);
		}
		return Response::json($return);
	}

}