<?php 
	
	class Controllers
	{	

		private $conectEnterprise;

		public function __construct($conectEnterprise)
		{	
			$this->conectEnterprise = $conectEnterprise;
			//$this->views = new Views();
			$this->loadModel();
		}

		public function loadModel()
		{
			//HomeModel.php
			$model = get_class($this)."Model";
			$routClass = "Models/".$model.".php";
			if(file_exists($routClass)){
				require_once($routClass);
				$this->model = new $model($this->conectEnterprise);
			}
		}
	}

 ?>


 <?php

