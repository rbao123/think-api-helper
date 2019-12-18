<?php

namespace bao\ApiHelper;

use think\facade\Db;
use think\facade\Env;

class DataParser
{
	public $app;
	
	public function __construct()
	{
	}
	/**
	 * 读取数据库表
	 * @param array $option ['allow'=>'允许解析的表','deny'=>'禁止解析的表']
	 * @throws
	 * @return array
	 */
	public static function map( $option = [] )
	{
		//数据库
		$database   = $option['database'] ?? Env::get('database.database');
		$show   = Db::query('SHOW TABLES FROM '.$database);
		
		$dd     = [];
		$tablesKey = 'Tables_in_' . $database;
		foreach ($show as $key => $value) {
			
			$allow = $option['allow'] ?? [];
			$deny = $option['deny'] ?? [];
			
			if( (!empty($allow) && !in_array($value[ $tablesKey ], $allow)) || in_array($value[ $tablesKey ], $deny)) {
				continue;
			}
			//表信息
			$table = Db::table('INFORMATION_SCHEMA.TABLES')
				->where('table_name',$value[ $tablesKey ])
				->where('table_schema', $database)
				->field('table_name,table_comment')->find();
			//字段信息
			$columns = Db::table('INFORMATION_SCHEMA.COLUMNS')
				->where('table_name',$value[ $tablesKey ])
				->where('table_schema', $database)
				->field('COLUMN_NAME AS name, COLUMN_DEFAULT AS default_value,IS_NULLABLE AS nullable,DATA_TYPE AS type,CHARACTER_OCTET_LENGTH AS max_len,NUMERIC_PRECISION AS numeric_precision,NUMERIC_SCALE AS numeric_scale,COLUMN_COMMENT AS comment')
				->select();
			foreach ($columns as $column) {
				//字段属性
				$table['columns'][] = $column;
			}
			$dd[$table['table_name']] = $table;
		}
		
		return $dd;
	}
	
}