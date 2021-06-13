<?php
	date_default_timezone_set('Asia/Jakarta');
	new PDOMyAdmin();

	class PDOMyAdmin {		
		private $param = Array(
				"host" => "127.0.0.1",
				"port" => "3306",
				"user" => "root",
				"pass" => "your password"
			),
			$link, $placeholder, $benchmark,
			$dbList = Array(), $selectedDB, 
			$tableList = Array(), $selectedTable,
			$columnList = Array(), $columnName,
			$affectedRows, $querySpeed,
			$userSQL = false,
			$command, $result, $error;
		
		function __construct() {
			$this->connect();
			$this->DBTree();

			$this->userSQL = true;
			if(isset($_POST['sql']) && !empty($_POST['sql'])){
				$this->query(base64_decode($_POST['sql']));
			}
			elseif(!empty($this->selectedDB) && !empty($this->selectedTable)){
				$this->query("select \n* \nfrom ".$this->selectedTable." \nlimit 10");
			}

			echo $this->html();
		}
		
		private function DBTree() {
			$this->query("show databases");
			$this->dbList = $this->result;
			if(isset($_GET['db']) && !empty($_GET['db']) && in_array($_GET['db'],$this->dbList)){
				$this->selectedDB = $_GET['db'];
				$this->link->exec('use '.$this->selectedDB);
				$this->query("show tables");
				$this->tableList = $this->result;
				if(isset($_GET['tb']) && !empty($_GET['tb']) && in_array($_GET['tb'],$this->tableList)){
					$this->selectedTable = $_GET['tb'];
					$this->query("show columns \nfrom ".$this->selectedTable);
					$this->columnList = $this->result;
				}
			}
		}
		
		private function connect() {
			try {
				$this->link = new PDO("mysql:host=".$this->param['host'].";port=".$this->param['port'],$this->param['user'],$this->param['pass']);
				$this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch(PDOException $e) {
				die($e->getMessage());
			}
		}
		
		private function query($sql) {
			$result = Array();
			try {
				$time_start = microtime(true);
				
				$sql = addcslashes($sql,'"'); //csv enclosed sign
				$query = $this->link->query($sql);
				$this->querySpeed = number_format((microtime(true)-$time_start),4);
				$this->placeholder = $sql;
				$this->affectedRows = $query->rowCount();
				
				$noView = array('insert','update','delete','drop','truncate','alter','create');				
				$this->command = trim(strtok(strtolower(preg_replace('/[^a-zA-Z0-9\s]/',' ', $sql)),' '));
				
				if(!$this->userSQL) $result = $query->fetchAll(PDO::FETCH_COLUMN);
				elseif(!in_array($this->command,$noView)) $result = $query->fetchAll(PDO::FETCH_ASSOC);
				
				$this->columnName = Array();	
				if($query->columnCount() > 0) {
					foreach(range(0, $query->columnCount() - 1) as $columns) {
						$meta = $query->getColumnMeta($columns);
						$this->columnName[] = $meta['name'];
					}
				}
			
			}
			catch(PDOException $e) {
				$this->placeholder = $sql;
				$this->error = htmlentities($e->getMessage())."</div>";
			}
			$this->result = $result;
		}
		
		private function html(){
			$header = <<<EOT
			<!DOCTYPE html>
			<html lang="en">
				<head>
					<title>PDOMyAdmin</title>
					<meta content="width=device-width,initial-scale=1,shrink-to-fit=no" name="viewport" />
					<meta content="notranslate" name="google" />
					<meta content="telephone=no" name="format-detection" />
					<style>
						html,body{height:100%;width:100%}
						body,textarea,table,ul,a,h1,h2,h3,#run {margin:0;padding:0;font-family:arial;font-size:20px;font-weight:normal}
						#header {width:100%;overflow:hidden;background-color:#217346;color:white}
						h2,h3, #nav,#content {padding:20px}
						h2, #nav {float:left;}
						h3,a,#run,th{cursor:pointer}
						h3 {float:right;display:none;}
						a {text-decoration:none;}
						#nav a {color:#000000}
						#tblist a {color:#555555}
						#collist li {color:#888888}
						#nav{box-sizing:border-box;width:auto;min-width:300px;height:auto;min-height:100%;border-right:1px solid #BFBFBF;border-bottom:1px solid #BFBFBF;}
						#nav, #info {background-color:#F3F3F3;}
						ul {list-style:none;}
						#content{overflow-y:hidden;width:auto;}
						form {width:100%;}
						form, #info, th, td {border:solid #999999 1px;}
						textarea, #run {display:block;background-color:white;padding:10px;border:none;outline:none;-webkit-appearance: none;-moz-appearance: none;border-radius:0}
						textarea {resize:none;width:96%;}
						#run {padding:10px;width:100%;border-top:solid #999999 1px;}
						#run:focus {font-weight:bolder;}
						#info {margin:20px 0;padding:10px}
						table {border-collapse:collapse;font-size:20px}
						th {background-color:#E6E6E6;font-weight:normal;cursor:pointer}
						@media (prefers-color-scheme: dark) {
							body,#content {background-color:#353431}
							#nav, form, textarea, #run, #info, th,td {color:#ffffff;background-color:#3E3D39;border-color:#353431}
							#nav a {color:#E0DED9;}
							#nav {border:0}
							#tblist a {color:#C8C5BB}
							#collist li {color:#948D7C}
						}
						@media (max-width: 768px) {
							#nav {width:100%;min-width:0;float:none;display:none;height:auto;}
							form {width:100%}
							textarea {width:92%;}
							#content {overflow:auto;}
							h3 {display:block;padding-left:0;}
							h2 {width:82%;padding-right:5px}
						}
					</style>
					<script type="text/javascript">
						function reveal(){
							var nav = document.getElementById('nav').style,
							content = document.getElementById('content').style;
							nav.display = nav.display=='block'?'none':'block';
							content.display = nav.display=='block'?'none':'block';
						}
						function sort(tbl,col) {
							document.getElementById("sql").value = "select \\n* \\nfrom "+tbl+" \\norder by "+col+" desc \\nlimit 10";
							encrypt();
						}
						function describe(tbl){
							document.getElementById("sql").value = "describe "+tbl;
							encrypt();
						}
						function encrypt(){
							document.getElementById("sql").value = btoa(document.getElementById("sql").value);
							document.getElementsByTagName("form")[0].submit();
						}
					</script>
				</head>
				<body>
EOT;


			$html = preg_replace("/\s+/S", " ", $header);
			$html .= "<div id=\"header\"><h2>".($this->selectedDB?:"Database")." &gt; ".($this->selectedTable?:"Table")."</h2>";
			$html .= "<h3 onclick=\"reveal()\">&#8801;</h3></div>";

			$html .= "<ul id=\"nav\">";
			foreach($this->dbList as $dbName){
				$html .= "<li><a href=\"?db=".$dbName."\">".$dbName."</a></li>";
				if($dbName == $this->selectedDB) {

					$html .= "<ul id=\"tblist\">";
					foreach($this->tableList as $tableName){
						$html .= "<li><a href=\"?db=".$dbName."&tb=".$tableName."\">&nbsp;&nbsp; &#8866; ".$tableName."</a></li>";
						if($tableName == $this->selectedTable && $dbName == $this->selectedDB){
					
							$html .= "<ul id=\"collist\">";
							foreach($this->columnList as $columnName){
								$html .= "<li>&nbsp; &nbsp; &nbsp;&nbsp; &#8627; ".$columnName."</li>";
							}
							$html .= "</ul>";
					
						}
					}
					$html .= "</ul>";

				}
			}
			$html .= "</ul>";
			
			$html .= "<div id=\"content\">";
			$html .= "<form action=\"?db=".$this->selectedDB."&tb=".$this->selectedTable."\" method=\"post\">";
			$html .= "<textarea rows=\"10\" name=\"sql\" id=\"sql\" tabindex=\"1\" spellcheck=\"false\" autocapitalize=\"off\" autofocus>".htmlentities($this->placeholder)."</textarea>";
			$html .= "<input type=\"button\" id=\"run\" value=\"Run\" tabindex=\"2\" onclick=\"encrypt()\">";
			$html .= "</form>";
			
			$info = !empty($this->error)?$this->error:$this->affectedRows." rows in ".$this->querySpeed." seconds";
			$describe = empty($this->selectedTable)?:" onclick=\"describe('".$this->selectedTable."')\"";
			$html .= "<div id=\"info\"".$describe.">".$info."</div>";

			if(is_array($this->result) && count($this->result) > 0) {
				$html .= "<table cellpadding=\"10\">";
				
				foreach($this->columnName as $name) {
					if(strpos($name,' ')) $name = "`".$name."`";
					$sort = ($this->command=="select")?" onclick=\"sort('".$this->selectedTable."','".$name."')\"":"";
					$html .= "<th".$sort.">".$name."</th>";
				}
				
				foreach($this->result as $rows => $row) {
					$html .= "<tr>";
					if(is_array($row)) {
						foreach($row as $data) {
							$html .= "<td valign='top'>".htmlspecialchars($data)."</td>";
						}
					}
					else {
						$html .= "<td>".$row."</td>";
					}
					$html .= "</tr>";
				}
				$html .= "</table>";
			}
			
			$html .= "</div>";

			return $html;
		}
	}
?>
