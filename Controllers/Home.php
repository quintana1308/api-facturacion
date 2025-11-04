<?php 


class Home extends Controllers{


	public function __construct()
	{
		parent::__construct();

	}


	public function home(){
		$data['page_id'] = 1;
		$data['page_tag'] = "Home";
		$data['page_title'] = "Página principal";
		$data['page_name'] = "home";
		$data['page_functions_js'] = "functions_admin.js";
		$this->views->getView($this,"home",$data);
	}


}
?>