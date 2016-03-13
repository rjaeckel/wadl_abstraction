<?php


/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 12.03.16
 * Time: 21:13
 */

trait collectionIterator {
	/**
	 * @param string   $property
	 * @param callable $cb
	 * @param array    ...$args
	 * @return $this
	 */
	function each(string $property,callable $cb,...$args) {
		#printf("%s :: %s ( %s ) #%d\n",__CLASS__,__FUNCTION__, $property, count($this->$property));
		$collection= $this->{$property}??$this->{lcfirst($property)}??[];
		foreach($collection as $k=>$item) {
			if($res = $cb($item,$k,...$args)) $this->$property[$k]=$res;
		}
		return $this;
	}

	function each_having(string $property,array $matches, callable &$cb,...$args) {
		$this->each($property,function($item,$idx)use($matches,&$cb,&$args){
			foreach($matches as $prop=>$value) {
				#echo "$prop: {$item->$prop}==$value\n";
				if(!($value==$item->$prop)) return;
			}
			$cb($item,$idx,...$args);
		});
		return $this;
	}
}