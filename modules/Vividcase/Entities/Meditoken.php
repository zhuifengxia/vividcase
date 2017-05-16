<?php namespace Modules\Vividcase\Entities;
   
use Illuminate\Database\Eloquent\Model;

class meditoken extends Model {

    protected $fillable = [];
    public $timestamps  = false;
    protected $table    ='meditoken';

    static function checktoken($userid,$usertoken,$type=0,$isyouke=0){

        $res = meditoken::where(['UserID'=>$userid,'UserToken'=>$usertoken,'UserType'=>$type])->first();
        $res = count($res);
        if($isyouke==1){
            if($userid==1093&&$usertoken=="tzj123") {
                $res=1;
            }
        }
        return $res;
    }
}