<?php
/*
 * windphp v1.0
 * https://github.com/lijinhuan
 *
 * Copyright 2015 (c) 543161409@qq.com
 * GNU LESSER GENERAL PUBLIC LICENSE Version 3
 * http://www.gnu.org/licenses/lgpl.html
 *
 */

if(!defined('FRAMEWORK_PATH')) {
	exit('access error !');
}


class DbMysqli implements DbInterface  {
	
	public $conf;
	
	
	public function __construct($conf){
		$this->conf = $conf;
	}
	
	public function __get($var){
		if($var=='mysqliLink'){
			if(isset($this->mysqliLink)){
				return $this->mysqliLink;
			}
			$this->mysqliLink = $this->connect($this->conf['host'], $this->conf['username'], $this->conf['password'], $this->conf['database'], $this->conf['_charset']);
			return $this->mysqliLink;
		}
	}
	
	
	public function getLink(){
		return $this->mysqliLink;
	}
	
	
	public function connect($host,$username,$password,$database,$_charset){
		$host  = explode(":", $host);
		$mysqliLink = mysqli_connect($host[0], $username, $password,$database,$host[1]);
		if ($mysqliLink->connect_error) {
			throw new Exception($this->conf['database'].' mysqli connect error '.$mysqliLink->connect_error);
		}
		$this->query("SET names $_charset", $mysqliLink);
		return $mysqliLink;
	}
	
	
	public function query($sql, $link = NULL) {
		empty($link) && $link = $this->mysqliLink;
		$store_sql = ((defined('DEBUG') && DEBUG) or (defined('TRACE') && TRACE)) && isset($_SERVER['sqls']) && count($_SERVER['sqls']) < 1000;
		if($store_sql){
			$start_time = microtime(true);	
		}
		$result = mysqli_query($link,$sql);
		if($store_sql){
			$_SERVER['sqls'][] = htmlspecialchars(stripslashes($sql)).'  (<font color="red">'.(microtime(true)-$start_time).' 秒</font>)';
		}
		if(!$result) {
			throw new Exception("Error:". mysqli_error($link). "   (<font color=blue>".$sql."</font>)");
		}
		return $result;
	}
	
	
	/**
	 * 获取一条数据返回数组
	 */
	public function fetchOne($table,$data=array()){
		$data['limit'] = 1;
		$sql = $this->__formatSql($table,$data);
		$re = $this->query($sql);
		$data = mysqli_fetch_assoc($re);
		if(!$data){
			return array();
		}
		return $data;
	}
	
	
	/**
	 * 获取所有结果集
	 */
	function fetchAll($table='',$data=array()){
		$sql = $this->__formatSql($table, $data);
		$res = $this->query($sql);
		if ($res !== false){
			$arr = array();
			while ($row = mysqli_fetch_assoc($res)){
				$arr[] = $row;
			}
			return $arr;
		}else{
			return array();
		}
	}
	
	
	
	public function update($table='',$data=array()){
		if(empty($data['where']) && empty($data['limit'])){
			throw new Exception("update all please use limit ！");
		}
		$sql = $this->__formatSql($table, $data,'UPDATE');
		$re =  $this->query($sql);
		if($re){
			$affected = mysqli_affected_rows($this->mysqliLink);
			if($affected>0){
				return $affected;
			}else{
				return true;
			}
		}else{
			return false;
		}
	}
	
	
	public function delete($table='',$data=array()){
		if(empty($data['where']) && empty($data['limit'])){
			throw new Exception("delete all please use limit ！");
		}
		$sql = $this->__formatSql($table, $data,'DELETE');
		return $this->query($sql);
	}
	
	
	
	public function insert($table='',$data=array()){
		$sql = $this->__formatSql($table, $data,'INSERT');
		$re = $this->query($sql);
		if($re){
			$lastid =  mysqli_insert_id($this->mysqliLink);
			if($lastid){
				return $lastid;
			}else{
				return $re;
			}
		}else{
			return false;
		}
	}
	
	
	public function replace($table='',$data=array()){
		$sql = $this->__formatSql($table, $data,'REPLACE');
		return $this->query($sql);
	}
	
	
	public function version(){
		return mysqli_get_client_info($this->mysqliLink);
	}
	
	public function close(){
		if(isset($this->mysqliLink)){
			mysqli_close($this->mysqliLink);
		}
	}
	
	public function __destruct(){
		$this->close();
	}
	
	private function __formatSql($table,$data,$type='SELECT'){
		if(empty($table)){
			throw new Exception("sql table empty ！");
		}
		$where = (isset($data['where']) and !empty($data['where']))?' WHERE '.$this->__formatWhere($data['where']):'';
		$set = (isset($data['set']) and !empty($data['set']))?' '.$this->__formatSet($data['set']):'';
		$limit = (isset($data['limit']) and !empty($data['limit']))?' LIMIT '.$data['limit']:'';
		$group = (isset($data['group']) and !empty($data['group']))?' GROUP BY `'.$data['group'].'`':'';
		$order = (isset($data['order']) and !empty($data['order']))?' ORDER BY '.$data['order']:'';
		$having = (($group && isset($data['having'])) and !empty($data['havaing']))?' HAVING '.$data['having']:'';
		$select = 	(isset($data['select']) and !empty($data['select']))?$data['select']:'*';	
		$sql = '';
		switch (strtoupper($type)){
			case 'SELECT':
				$sql = "SELECT $select FROM $table $where $group  $having $order $limit";
				break;
			case 'UPDATE':
				if(empty($set)){throw new Exception("update set error table:$table ！");} 
				$sql = "UPDATE $table  SET   $set $where  $limit";
				break;
			case 'DELETE':
				$sql = "DELETE FROM $table $where $limit";
				break;
			case 'INSERT':
				$sql = "INSERT INTO $table SET $set";
				break;
			case 'REPLACE':
				$sql = "REPLACE INTO $table SET $set";
				break;
		}
		if(empty($sql)){
			throw new Exception("sql $type not support ！");
		}
		return $sql;
	}
	
	
	private function __formatSet($keys){
		$set = '';
		$c = ' , ';
		foreach ($keys as $k=>$v){
			if(is_numeric($v)){
				$set .= ' `'.$k.'`='.intval($v).$c;
			}elseif (is_array($v)){
				if(empty($v)){
					$v = '';
					$set .= ' `'.$k.'`=""'.$c;
				}else{
					if(isset($v['count'])){
						if(strpos($v['count'], '-')){
							$count = '-'.rtrim($v['count'],'-');
						}else{
							$count = '+'.rtrim($v['count'],'+');
						}
						$set .= $k.'='.$k.$count.$c;
					}elseif(isset($v['b'])){
						$set .= $k."=b'".$v['b']."'".$c;
					}
				}
			}else{
				$set .= ' `'.$k.'`='."'" . addcslashes(str_replace("'", "''", $v), "\000\n\r\\\032") . "'".$c;
			}
		}
		return rtrim($set,$c);
	}
	
	
	private function __formatWhere($keys=array()){
		
		$where = '';
		$c = ' and ';
		foreach ($keys as $k=>$v){
			if(is_numeric($v)){
				$where .= ' `'.$k.'`='.intval($v).$c;
			}elseif(is_array($v)){
				if(empty($v)){
					$v = '';
					$where .= ' `'.$k.'`='."''".$c;
				}else{
					
					if(isset($v['in'])){
						$varr = array();
						foreach ($v['in'] as  $imval){
							$varr[] = addcslashes(str_replace("'", "''", $imval), "\000\n\r\\\032");
						}
						$v = "'".implode("','", $varr)."'";
						$where .= ' `'.$k.'` in('.$v.')'.$c;
					}else if(isset($v['like'])){
						$where .= ' `'.$k.'` like \''.addcslashes(str_replace("'", "''", $v['like']), "\000\n\r\\\032").'\'' .$c;
					}else if(isset($v['gt'])){
						$value = is_numeric($v['gt'])?$v['gt']:intval($v['gt']);
						$where .= ' `'.$k.'` > '.$value . $c;
					}else if(isset($v['gte'])){
						$value = is_numeric($v['gte'])?$v['gte']:intval($v['gte']);
						$where .= ' `'.$k.'` >= '.$value . $c;
					}else if(isset($v['lt'])){
						$value = is_numeric($v['lt'])?$v['lt']:intval($v['lt']);
						$where .= ' `'.$k.'` < '.$value . $c;
					}else if(isset($v['lte'])){
						$value = is_numeric($v['lte'])?$v['lte']:intval($v['lte']);
						$where .= ' `'.$k.'` <= '.$value . $c;
					}else if(isset($v['neq'])){
						if($v['neq']==''){
							$value = "''";
						}else{
							$value = is_numeric($v['neq'])?$v['neq']:intval($v['neq']);
						}
						
						$where .= ' `'.$k.'` != '.$value . $c;
					}else{
						$where .= ' 1 ' .$c;
					}
				}
			}else{
				$where .= ' `'.$k.'`='."'" . addcslashes(str_replace("'", "''", $v), "\000\n\r\\\032") . "'".$c;
			}
		}
		return rtrim($where,$c);
	}
	
	
	
	
	
}
?>