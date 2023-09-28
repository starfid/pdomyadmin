<?php
	date_default_timezone_set('Asia/Jakarta');
	new PDOMyAdmin();

	class PDOMyAdmin {		
		private $param = Array(
				"host" => "127.0.0.1",
				"port" => "3306",
				"user" => "your username",
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
			elseif(isset($_GET['tg']) && in_array($_GET['tg'],$this->triggerList)){
				$this->query("select\n*\nfrom information_schema.triggers \nwhere \n\ttrigger_schema = '".$this->selectedDB."' and \n\ttrigger_name = '".$_GET['tg']."'");
				$this->columnList = $this->result;
			}
			elseif(isset($_GET['gt'])){
				$show = $_GET['gt']=='tg'?"show triggers":"select \n\ttable_name,\n\tview_definition \nfrom information_schema.views \nwhere \n\ttable_schema = '".$this->selectedDB."'";
				$this->query($show);
			}
			elseif(!empty($this->selectedDB)){
				if(!empty($this->selectedTable)){
					$show = "select \n* \nfrom ".$this->selectedTable." \nlimit 10";
				}
				else {
					$show = count($this->viewList)>0?"show full tables":"show tables";
				}
				$this->query($show);
			}
			echo $this->html();
		}
		
		private function DBTree() {
			$this->query("show databases");
			$this->dbList = $this->result;
			if(isset($_GET['db']) && !empty($_GET['db']) && in_array($_GET['db'],$this->dbList)){
				$this->selectedDB = $_GET['db'];
				$this->link->exec('use '.$this->selectedDB);

				$this->query("show triggers");
				$this->triggerList = $this->result;
				
				$this->query("show full tables where table_type = 'VIEW'");
				$this->viewList = $this->result;

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
				
				$sql = addcslashes($sql,'"');
				$query = $this->link->query($sql);
				$this->querySpeed = number_format((microtime(true)-$time_start),4);
				$this->placeholder = $sql;
				$this->affectedRows = $query->rowCount();
				
				$noOutput = array('insert','update','delete','drop','truncate','alter','create');				
				$this->command = trim(strtok(strtolower(preg_replace('/[^a-zA-Z0-9\s]/',' ', $sql)),' '));
				
				if(!$this->userSQL) $result = $query->fetchAll(PDO::FETCH_COLUMN);
				elseif(!in_array($this->command,$noOutput)) $result = $query->fetchAll(PDO::FETCH_ASSOC);
				
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
					<link rel="icon" href="data:,">
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
						#nav{box-sizing:border-box;width:auto;min-width:300px;border-right:1px solid #BFBFBF;border-bottom:1px solid #BFBFBF;}
						#nav, .float {background-color:#F3F3F3;}
						#scrollUl {overflow:scroll;scrollbar-width:none;} #scrollUl::-webkit-scrollbar {display: none;}
						ul {list-style:none;}
						#content{overflow:hidden;width:auto;}
						form {width:100%;}
						form, .float, th, td {border:solid #999999 1px;}
						textarea, #run {display:block;background-color:white;padding:10px;border:none;outline:none;-webkit-appearance: none;-moz-appearance: none;border-radius:0}
						textarea {resize:vertical;width:calc(100% - 20px);scrollbar-width:none;}
						#run {padding:10px;width:100%;border-top:solid #999999 1px;color:black}
						#run:focus {font-weight:bolder;}
						.float {margin:20px 0;width:100%;overflow:hidden} .left {float:left;width:auto} #save {cursor:pointer;float:right;width:auto;text-align:right}
						.float div {padding:10px;}
						table {border-collapse:collapse;font-size:20px}
						th {background-color:#E6E6E6;font-weight:normal;cursor:pointer}
						@media (prefers-color-scheme: dark) {
							body,#content {background-color:#353431}
							#nav, form, textarea, #run, .float, th,td {color:#ffffff;background-color:#3E3D39;border-color:#353431}
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
						var $ = (el) => {
							return document.querySelector(el);
						},
						tb = 0,
						reveal = () => {
							var nav = $('#nav').style,
							content = $('#content').style;
							nav.display = nav.display=='block'?'none':'block';
							content.display = nav.display=='block'?'none':'block';
						},
						sort = (tbl,col) => {
							$("#sql").value = "select \\n* \\nfrom "+tbl+" \\norder by "+col+" desc \\nlimit 10";
							encrypt();
						},
						describe = (tbl,db) => {
							var sql = "describe "+tbl;
							if(db!='') {
								sql = "select \\n\\tview_definition \\nfrom information_schema.views \\nwhere ";
								sql = sql + "\\n\\ttable_schema = '"+db+"' and \\n\\ttable_name = '"+tbl+"'";
							}
							$("#sql").value = sql;
							encrypt();
						},
						encrypt = () => {
							var str = $("#sql").value.replace(/[\u2018\u2019\u201C\u201D]/g, "\\\'");
							$("#sql").value = btoa(str.trim());
							$("form").submit();
						},
						tableView = () => {
							if(parseInt(tb.top)+parseInt(tb.height)>window.innerHeight){
								scrollTable.style.cssText = "overflow:scroll;height:"+(parseInt(window.innerHeight)-parseInt(tb.top)-10)+"px";
								$('#content').style.paddingBottom = 0;
							}
							else if((parseInt(tb.left)+parseInt(tb.width))>(parseInt(window.innerWidth)-parseInt(tb.left)-10)){
								scrollTable.style.overflowX = "scroll";
							}
						},
						download = () => {
							var rows = document.querySelectorAll('table#dat tr');
							var csv = [];
							for (var i = 0; i < rows.length; i++) {
								var row = [], cols = rows[i].querySelectorAll('td, th');
								for (var j = 0; j < cols.length; j++) {
									var data = cols[j].innerText.replace(/(\\r\\n|\\n|\\r)/gm, '').replace(/(\\s\\s)/gm, ' ');
									data = data.replace(/"/g, '""');
									row.push('"' + data + '"');
								}
								csv.push(row.join(','));
							}
							var csv_string = csv.join('\\n');
							var filename = 'export_' + new Date().toLocaleDateString() + '.csv';
							var link = document.createElement('a');
							link.style.display = 'none';
							link.setAttribute('target', '_blank');
							link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv_string));
							link.setAttribute('download', filename);
							document.body.appendChild(link);
							link.click();
							document.body.removeChild(link);
							window.event.preventDefault();
						},
						indent = (txt,evt) => {
							var evt = (evt) ? evt : ((event) ? event : null);
							if(evt.keyCode==9){
								evt.preventDefault();
								var startPos = txt.selectionStart;
								var endPos = txt.selectionEnd;
								txt.value = txt.value.substring(0, startPos) + '\\t' + txt.value.substring(endPos, txt.value.length);
								txt.selectionStart = startPos + 1;
								txt.selectionEnd = startPos + 1;
							}
						};
						window.onload = function(){
							if(!!$('#dat') && parseInt(screen.width)>1000){
								tb = $('#dat').getBoundingClientRect();
								$('#scrollTable') && tableView();
								$('#scrollUl').style.height = (parseInt(window.innerHeight)-106)+"px";
								$('#nav').style.height = (window.innerHeight-$('#header').getBoundingClientRect()['height'])+"px";
								var col = document.getElementById('collist');
								col && document.getElementById('scrollUl').scroll(0,col.previousSibling.offsetTop-82);
								if(parseInt(tb.width)<$('#scrollTable').getBoundingClientRect().width+10){
									$('#scrollTable').style.overflowX = "hidden";
								}

							}
							else {
								$('#nav').style.minHeight = (window.innerHeight-62) + "px";
							}
						};
					</script>
				</head>
				<body>
EOT;


			$html = preg_replace("/\s+/S", " ", $header);
			$html .= "<div id=\"header\"><h2>".($this->selectedDB?:"Database")." &gt; ".($this->selectedTable?:"Table")."</h2>";
			$html .= "<h3 onclick=\"reveal()\">&#8801;</h3></div>";

			$html .= "<div id=\"nav\"><ul id=\"scrollUl\">";
			foreach($this->dbList as $dbName){
				$html .= "<li><a href=\"?db=".$dbName."\">".$dbName."</a></li>";
				if($dbName == $this->selectedDB) {

					$html .= "<ul id=\"tblist\">";
					if(count($this->triggerList)>0){
						$html .= "<li><a href=\"?db=".$dbName."&gt=tg&r=".time()."\">&nbsp;&nbsp; &#9872; Triggers</a></li>";
						if(isset($_GET['gt']) && $_GET['gt']=='tg'){
							$html .= "<ul id=\"collist\">";
							foreach($this->triggerList as $triggerName){
								$html .= "<li><a href=\"?db=".$dbName."&gt=tg&tg=".$triggerName."&r=".time()."\">&nbsp; &nbsp; &nbsp;&nbsp; &#8627; ".$triggerName."</a></li>";	
							}
							$html .= "</ul>";
						}
					}					
					foreach($this->tableList as $tableName){
						$icon = in_array($tableName,$this->viewList)?"&#9880;":"&#8866;";
						$html .= "<li><a href=\"?db=".$dbName."&tb=".$tableName."&r=".time()."\">&nbsp;&nbsp; ".$icon." ".$tableName."</a></li>";
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
			$html .= "</ul></div>";
			
			$html .= "<div id=\"content\">";
			$html .= "<form action=\"?db=".$this->selectedDB."&tb=".$this->selectedTable."\" method=\"post\">";
			$html .= "<textarea onkeydown=\"indent(this)\" rows=\"8\" name=\"sql\" id=\"sql\" tabindex=\"1\" spellcheck=\"false\" autocapitalize=\"off\" autofocus>".htmlentities($this->placeholder)."</textarea>";
			$html .= "<input type=\"button\" id=\"run\" value=\"Run\" tabindex=\"2\" onclick=\"encrypt()\">";
			$html .= "</form>";
			
			$info = !empty($this->error)?$this->error:$this->affectedRows." rows in ".$this->querySpeed." seconds";
			$isView = isset($this->viewList) && in_array($this->selectedTable,$this->viewList)?$this->selectedDB:"";
			$describe = empty($this->selectedTable)?:" onclick=\"describe('".$this->selectedTable."','".$isView."')\"";
			$html .= "<div class=\"float\"><div class=\"left\" id=\"info\"".$describe.">".$info."</div><div onclick=\"download()\" title=\"Download Result\" id=\"save\">&#128427;</div></div>";

			if(is_array($this->result) && count($this->result) > 0) {
				$html .= "<div id=\"scrollTable\"><table cellpadding=\"10\" id=\"dat\">";
				
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
				$html .= "</table></div>";
			}
			
			$html .= "</div>";

			return $html;
		}
	}
?>
