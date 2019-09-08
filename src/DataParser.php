<?php
/**
 * @author 发仔 <i@fazi.me>
 * @date 2019-09-07 22:37:11
 */
namespace Fazi\ApiHelper;

use think\facade\Env;
use think\facade\Cache;

class DataParser
{
	public $app;
	
	/**
	 * 读取命名空间列表
	 * @return array
	 */
	public static function map()
	{
		//缓存
		$tables = Cache::remember('manual_tabels',function(){
			$dbserver   = Env::get('database.hostname');
			$dbusername = Env::get('database.username');
			$dbpassword = Env::get('database.password');
			$database   = Env::get('database.database');
			$con        = new \mysqli($dbserver, $dbusername, $dbpassword, $database);
			$rs         = $con->query('show tables');
			$tables     = [];
			$tablesKey = 'Tables_in_' . $database;
			foreach ($rs as $key => $value) {
				$temp = [];
				$sql = 'SELECT * FROM ';
				$sql .= 'INFORMATION_SCHEMA.TABLES ';
				$sql .= 'WHERE ';
				$sql .= "table_name = '" . $value[ $tablesKey ] . "'  AND table_schema = '$database'";
				$rs2 = $con->query($sql);
				foreach ($rs2 as $key2 => $value2) {
					$temp['func'] = $value2['TABLE_NAME'];
					$temp['title'] = $value2['TABLE_COMMENT'] ?: $temp['func'];
				}
				$sql = 'SELECT * FROM ';
				$sql .= 'INFORMATION_SCHEMA.COLUMNS ';
				$sql .= 'WHERE ';
				$sql .= "table_name = '" . $value[ $tablesKey ] . "' AND table_schema = '$database'";
				$rs2 = $con->query($sql);
				foreach ($rs2 as $key2 => $value2) {
					$temp['COLUMN'][] = $value2;
				}
				$tables[$temp['func']] = $temp;
			}
			return $tables;
		},3600*24*7);
		
		return $tables;
	}
	
}