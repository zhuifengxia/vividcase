<?php namespace Modules\System\Http\Controllers;

use Pingpong\Modules\Routing\Controller;

class SystemController extends Controller {
	
	public function index()
	{
		return view('system::index');
	}
	
}