<?php namespace Modules\Vividcase\Http\Controllers;

use Pingpong\Modules\Routing\Controller;

class VividcaseController extends Controller {
	
	public function index()
	{
		return view('vividcase::index');
	}
	
}