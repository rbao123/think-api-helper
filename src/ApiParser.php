<?php
/**
 * @author 发仔 <i@fazi.me>
 * @date 2019-09-07 22:37:11
 */
namespace Fazi\ApiHelper;

use DirectoryIterator;
use think\App;

class ApiParser
{
	public $app;
	
	/**
	 * 读取API地图
	 * @return array
	 */
	public static function map()
	{
		$class_tags = [];
		//解析命名空间
		$apps = self::getApps();
		
		//遍历命名空间
		if($apps) {
			foreach ($apps as $group => $namespaces) {
				
				foreach ($namespaces AS $namespace) {
					
					$class = $namespace[0];
					$uri = $namespace[1];
					
					if(class_exists($class)) {
						try {
							//解析文件
							$reflection = new \ReflectionClass($class);
							$class_comment = $reflection->getDocComment();
							$class_tag = self::getCommentTag($class_comment);
							$class_tag['uri'] = $uri;
							//方法
							$methods = $reflection->getMethods();
							foreach ($methods AS $method) {
								$method_tag = self::getCommentTag($method->getDocComment());
								$method_name = $method->getName();
								$method_tag['uri'] = $uri . '/' . $method_name;
								$class_tag['methods'][$method_name] = $method_tag;
							}
							
							$class_tags[$group][] = $class_tag;
							
						} catch (\ReflectionException $e) {
						}
						
					}
				}
				
			}
		}
		
		return $class_tags;
	}
	
	/**
	 * 读取命名空间列表
	 * @return array
	 */
	public static function getApps()
	{
		$app = (new App);
		$app_namespace = $app->getNamespace();
		$app_path  = $app->getBasePath();
		
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
				}
				$current_namespace[] = 'controller';
				//遍历
				$top_layer = new DirectoryIterator($dir);
				foreach ($top_layer AS $top) {
					if($top->isDot()) continue;
					//二级目录再遍历
					
					$current_namespace[] = $uri[] = $top->getBasename('.php');
					if($top->isDir()) {
						$second_layer = new DirectoryIterator($top->getRealPath());
						foreach ( $second_layer AS $second ) {
							if($second->isDot()||$second->isDir()) continue;
							$namespaces[$app_name][] = [
								implode('\\',$current_namespace).'\\'.$second->getBasename('.php'),
								implode('\\',$uri).'.'.$second->getBasename('.php'),
							];
						}
					} else {
						$namespaces[$app_name][] = [
							implode('\\',$current_namespace),
							implode('\\',$uri),
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
	 * @return array
	 */
	protected static function getCommentTag( $comment )
	{
		$comment = preg_replace('/[ ]+/', ' ', $comment);
		preg_match_all('/\*[\s+]?@(.*?)\s(.*?)[\n|\r]/is', $comment, $matches);
		
		$tags = [];
		if(!empty($matches[1]) && !in_array('ignore',$matches[1])) {
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
						];
				}
			}
		}
		
		return $tags;
	}
	
	
	
}