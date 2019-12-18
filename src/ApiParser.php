<?php

namespace bao\ApiHelper;

use DirectoryIterator;
use ReflectionClass;
use ReflectionException;
use think\facade\App;
use think\facade\Cache;
use think\facade\Config;
use think\helper\Str;

class ApiParser
{
	public $app;
	
	public function __construct()
	{
	}
	/**
	 * 读取API地图
	 * @param array $option ['allow'=>'允许的应用','deny'=>'禁止解析的应用']
	 * @return array
	 */
	public static function map( $option = [] )
	{
		$class_tags = [];
		//解析命名空间
		$apps = self::getApps( $option );
		
		//遍历命名空间
		if($apps) {
			foreach ($apps as $group => $namespaces) {
				
				foreach ($namespaces AS $namespace) {
					
					$class = $namespace[0];
					$uri = $namespace[1];
					if(class_exists($class)) {
						try {
							//解析文件
							$reflection = new ReflectionClass($class);
							$class_comment = $reflection->getDocComment();
							$class_tag = self::getCommentTag($class_comment);
							if(!empty($class_tag['ignore']))continue;
							$class_tag['uri'] = $uri;
							//方法
							$methods = $reflection->getMethods();
							foreach ($methods AS $method) {
								$method_tag = self::getCommentTag($method->getDocComment(), $option);
								if(empty($method_tag['api']) || !empty($method_tag['ignore']))continue;
								$method_name = $method->getName();
								$method_tag['uri'] = str_replace('\\','/', $uri) . '/' . $method_name;
								$class_tag['methods'][$method_name] = $method_tag;
							}
							
							$class_tags[$group][] = $class_tag;
							
						} catch (ReflectionException $e) {
						}
						
					}
				}
				
			}
		}
		
		return $class_tags;
	}
	
	/**
	 * 读取命名空间列表
	 * @param array $option
	 * @return array
	 */
	public static function getApps( $option = [] )
	{
		$app_path  = App::getBasePath();
		$app_namespace = Config::get('app.app_namespace','app')?:'app';
		
		$namespaces = [];
		$is_multi = false;
		//单应用判断
		if(file_exists($app_path.'controller/')) {
			$dirs = [$app_path.'controller/'];
		} else {
			$is_multi = true;
			$dirs = glob($app_path.'*/controller/',GLOB_ONLYDIR|GLOB_MARK|GLOB_NOESCAPE);
		}
		//遍历目录至二级
		if( $dirs ) {
			
			foreach($dirs AS $dir) {
				
				$current_namespace = $uri = [];
				
				//当前命名空间
				$current_namespace[] = $app_namespace;
				
				//多应用
				$app_name = 'only';
				if($is_multi) {
					$current_namespace[] = $uri[] = $app_name = basename(dirname($dir));#应用名
					$allow = $option['allow'] ?? [];
					$deny = $option['deny'] ?? [];
					
					if((!empty($allow) && !in_array($app_name, $allow)) || in_array($app_name,$deny)) {
						continue;
					}
				}
				$current_namespace[] = 'controller';
				//遍历
				$top_layer = new DirectoryIterator($dir);
				foreach ($top_layer AS $top) {
					if($top->isDot()) continue;
					//二级目录再遍历
					
					$current_class = $top->getBasename('.php');
					if($top->isDir()) {
						$uri[] = $current_class;#控制器二级
						$second_layer = new DirectoryIterator($top->getRealPath());
						foreach ( $second_layer AS $second ) {
							if($second->isDot()||$second->isDir()) continue;
							$namespaces[$app_name][] = [
								implode('\\',$current_namespace).'\\'.$current_class.'\\'.$second->getBasename('.php'),
								implode('\\',$uri).'.'.$second->getBasename('.php'),
							];
						}
					} else {
						$namespaces[$app_name][] = [
							implode('\\',$current_namespace).'\\'.$current_class,
							implode('\\',$uri).'\\'.$current_class,
						];
					}
				}
			}
		}
		
		return $namespaces;
	}
	
	/**
	 * 分析注释的TAG
	 * @param $comment
	 * @throws
	 * @return array
	 */
	protected static function getCommentTag( $comment, $option=[] )
	{
		$comment = preg_replace('/[ ]+/', ' ', $comment);
		preg_match_all('/\*[\s+]?@(.*?)\s(.*?)[\n|\r]/is', $comment, $matches);
		//dd
		if(!empty($option['dd'])) {
			
			$dd = Cache::remember('maunal:dd',function(){
				$dd = DataParser::map();
				return $dd;
			});
			$ignore = ['id','uid','delete_time','create_time','update_time'];
		}
		
		$tags = [];
		if( !empty($matches[1]) ) {
			foreach ($matches[1] AS $i => $tag_name) {
				$line = explode(' ',$matches[2][$i] ?? '');
				switch ($tag_name) {
					case 'title':
						$tags[$tag_name] = $line[0] ?? '';
						break;
					case 'author':
						$tags[$tag_name] = [
							'name' => $line[0] ?? '',
							'contact' => $line[1] ?? '-',
						];
						break;
					case 'api':
						$tags[$tag_name] = [
							'method' => $line[0] ?? 'POST',
							'uri' => $line[1] ?? '',
						];
						break;
					case 'param':
						$tags[$tag_name][] = [
							'type' => $line[0] ?? 'mixed',
							'name' => $line[1] ?? '-',
							'desc' => $line[2] ?? '-',
							'must' => $line[3] ?? 0,
                            'example' => $line[4] ?? '-',
						];
						break;
					case 'time':
						$tags[$tag_name][] = [
							'time' => $line[0] ?? '-',
							'desc' => $line[1] ?? '-',
						];
						break;
					case 'table':
						if($line[0]) {
							$tags[$tag_name][] = [
								'name' => $line[0] ?? '',
								'field' => $line[1] ?? '*',
							];
						}
						break;
					case 'ignore':
						$tags[$tag_name] = 1;
						break;
					
				}
			}
			//TABLE
			if(!empty($tags['table']) && !empty($option['dd'])) {
				foreach($tags['table'] AS $table) {
					//判断是否中
					if(strpos($table['name'],'.') !== false) {
						list($database,$table_name) = explode('.',$table['name']);
						$dd = self::getDD($database, $table_name);
						$columns = !empty($dd[$table_name]) ? $dd[$table_name]['columns'] : [];
					} else {
						$table_name = $table['name'];
					}
					$columns = !empty($dd[$table_name]) ? $dd[$table_name]['columns'] : [];
					if($columns) {
						foreach ($columns AS $column) {
							if(!in_array($column['name'], $ignore)) {
								$tags['param'][] = [
									'name' => $column['name'],
									'type' => $column['type'],
									'desc' => $column['comment'],
									'must' => !$column['nullable'] && !$column['default_value'] ? 1 : 0,
								];
							}
							
						}
					}
				}
			}
		}
		
		return $tags;
	}
	
	public static function getDD( $database = '', $table_name = '' )
	{
		$cache_name = $database ? 'maunal:dd:'.$database : 'maunal:dd';
//		$dd = Cache::remember($cache_name,function() use($database){
		$dd = DataParser::map(['database'=>$database,'allow' => [$table_name]]);
		return $dd;
	}
	
}