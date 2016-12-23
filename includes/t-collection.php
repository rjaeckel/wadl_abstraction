<?php


/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 12.03.16
 * Time: 21:13
 */

trait collection {
	/**
	 * @param string   $name
	 * @param callable $cb
	 * @param array    ...$args
	 * @return $this
	 */
	protected function each(string $name, callable $cb, ...$args) {
		#printf("%s :: %s ( %s ) #%d\n",__CLASS__,__FUNCTION__, $property, count($this->$property));
        return $this->collection($name)->apply($cb,...$args);
	}

	protected function apply(callable $cb , ...$args) {
        foreach ($this->collection() as &$item) {
            if(null!==$res=$cb($item,...$args)) $item=$res;
        }
        return $this;
    }
    protected function &collection(string$name=null) {
        static $target;
        if(!$name) {
            if ($target) return $this->getCollection($target);
            return [];
        } else $target=$name;
        return $this;
    }
    private function &getCollection(string $name, $setTarget=false) {
        if (!$name)return false;
        if (($this->$name??($this->$name=[]))&&$setTarget)
            return $this->collection($name);
        return $this->$name;
    }
    protected function append($item,$key=null) {
        $this->collection()[$key]=$item;
        return $this;
    }
    protected function remove(callable $cb=null) {
        throw new Error();
    }
    protected function filter(callable $cb=null) {
        throw new Error();
    }
    /*
    protected function each_having(string $property,array $matches, callable &$cb,...$args) {
        $this->collection($property)->apply(function($item)use($matches,&$cb,&$args){
            foreach($matches as $prop=>$value) if(!($value==$item->$prop)) return;
            return $cb($item,...$args);
        });
        return $this;
    }
    */
}

