<?php namespace Modules\Vividcase\Http\Middleware; 

use Closure;
use Modules\Vividcase\Entities\meditoken;
use Response;
class Authenticate {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next,$isyouke)
    {
        $userid = empty($request->userid)?post('userid'):$request->userid;
        $usertoken = empty($request->usertoken)?post('usertoken'):$request->usertoken;
        $usertoken = meditoken::checktoken($userid,$usertoken,0,$isyouke);
        if($usertoken<=0) {
            $return = array(
                "status" => 2,
                "msg" => "the token is out of date" //token过期
            );
            return Response::json($return);
        }else{
            return $next($request);
        }
    }
    
}
