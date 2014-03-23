<?php

/**
 *  A Array Linq for PHP -- plan to realize by using php extension
 *  模仿plinq,不使用SPL:RecursiveArrayIterator的linq实现
 *  @author onceme 
 */
class Alinq{

    const PLINQ_CLOSURE_RETURN_TYPE_BOOL = 'bool';
    const PLINQ_CLOSURE_RETURN_TYPE_OBJECT = 'object';
    const PLINQ_CLOSURE_RETURN_TYPE_ARRAY = 'array';
    const PLINQ_ORDER_ASC = 'asc';
    const PLINQ_ORDER_DESC = 'desc';
    
    const PLINQ_ORDER_TYPE_NUMERIC = 1;
    const PLINQ_ORDER_TYPE_ALPHANUMERIC = 2;
    const PLINQ_ORDER_TYPE_DATETIME = 3;
    private $dataSource;
	/**
	 *	初始化类时指定数组原[后期考虑缓存各个数组源，及其查询结果]
	 */
	public function __construct(Array &$dataSource = array())
    {
        $this->dataSource = $dataSource;     
    }

    /**
     * 以传入数组实例化一个新的linq对象
     */
    public static function Instance(Array &$newDataSource = array()){
    		return	new self($newDataSource);	//建立一个新的对象,便于于灵活的使用不同数组
    }



    /**
     * 返回符合 $closure(闭包) 条件的第一个结果
     * 
     * @param ObjectClosure $closure    a closure that returns boolean.
     * @return Plinq    The first item from this according $closure
     */
    public function Single($closure)
    {
        $applicables = $this->GetApplicables($closure, 1);
               
        return $applicables->ToArray();
    }  


    /**
     * 根据 $closure(闭包) 生成的key对数组进行分组
     * 
     * @param ObjectClosure $closure    a closure that returns an item as key, item can be any type.
     * @return Plinq
     */
    public function GroupBy($closure){

        foreach($this->dataSource as $key => $value)        
        {
            
            $result = call_user_func_array($closure, array($key, $value));
                
            $groups[$result][$key] = $value;
                
        }
        return self::Instance($groups);         
        /*  返回对象以便使用链表方式
            $p->GroupBy(function($k, $v){ 
                return (date('Y-m',$v['date']->getTimeStamp())); 
            })->Single(function($k, $v){ return $k > '2015-03'; });

         */
    }

    /**
     * 将给定的数组覆盖到数据源数组中
     * 
     * @param Array $array
     * @return Plinq
     */
    public function Concat(Array $array)
    {    
        $data = $this->dataSource;
        foreach ($array as $key => $value) { 
            
            $data[$key] = $value;            
        }
        
        return self::Instance($data);  //以结果集实例化新的对象返回，用于链表操作
    }


   /**
     * Creates a new Plinq object from items which are a form of Array according to $closure 
     * 打散二维数组  array("key"=>array("key_1"=>1,"key_2"=>2))  => array(0=>1,1=>2)
     * @param ObjectClosure $closure    a closure that returns an item that is a form of Array.
     * @return Plinq
     */
    public function SelectMany($closure)
    {
        $applicables = $this->GetApplicables($closure, 0, self::PLINQ_CLOSURE_RETURN_TYPE_OBJECT);
        $applicables = $applicables->ToArray();
        $many = array();
        
        foreach($applicables as $applicable)
        {
            if(!is_array($applicable))
                continue;
            
            foreach($applicable as $applicablePart)
                $many[] = $applicablePart;
        }
        
        return self::Instance($many);
    }

    /**
     * Creates a new Plinq object from items that are determined by $closure 
     * 
     * @param ObjectClosure $closure    a closure that returns an item to append, item can be any type.
     * @return Plinq
     */
    public function Select($closure)
    {
        return $this->GetApplicables($closure, 0, self::PLINQ_CLOSURE_RETURN_TYPE_OBJECT);
    }
     

     /**
     * Plinq::Where() 
     * Filters the Plinq object according to closure return result.
     * 
     * @param ObjectClosure $closure     a closure that returns boolean
     * @return Plinq    Filtered results according to $closure
     */
    public function Where($closure)
    {         
        return $this->GetApplicables($closure);
    }
    
    /**
     * Plinq::Skip()
     * Skips first $count item and returns remaining items
     * 
     * @param int $count    skip count
     * @return Plinq
     */
    public function Skip($count)
    {
        return self::Instance(array_slice($this->dataSource, $count, $this->count()));
    }
    
    /**
     * Plinq::Take()
     * Takes first $count item and returns them
     * 
     * @param int $count    take count
     * @return  Plinq
     */
    public function Take($count)
    {
        return $this->GetApplicables(function($k, $v){ return true; }, $count, self::PLINQ_CLOSURE_RETURN_TYPE_BOOL);
    }
    
    /**
     * Determines if all of the items in this object satisfies $closure
     * 
     * @param ObjectClosure $closure    a closure that returns boolean
     * @return bool
     */
    public function All($closure)
    {
        return ($this->count() == $this->GetApplicables($closure)->count());
    }
    
    /**
     * Determines if any of the items in this object satisfies $closure
     * 
     * @param ObjectClosure
     * @return bool
     */
    public function Any($closure)
    {
        return ($this->Single($closure) !== null);
    }
    
    /**
     * Computes the average of items in this object according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return double   Average of items
     */
    public function Average($closure)
    {
        $resulTotal = 0;
        $averagable = 0;
        
        foreach ($this->dataSource as $key => $value) {
            
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            $resulTotal += $result;
            $averagable++;            
        }        
        return (($averagable == 0)? 0 : ($resulTotal/$averagable)); 
    }
    
    private function Order($closure, $direction = self::PLINQ_ORDER_ASC)
    {
        $applicables = $this->GetApplicables($closure, 0, self::PLINQ_CLOSURE_RETURN_TYPE_OBJECT);

        $sortType = self::PLINQ_ORDER_TYPE_NUMERIC;
        if(is_a($applicables->ElementAt(0), 'DateTime'))
            $sortType = self::PLINQ_ORDER_TYPE_DATETIME;
        elseif(!is_numeric($applicables->ElementAt(0)))
            $sortType = self::PLINQ_ORDER_TYPE_ALPHANUMERIC;
        
        if($sortType == self::PLINQ_ORDER_TYPE_DATETIME)
        {
            $applicables = $applicables->Select(function($k, $v){ return $v->getTimeStamp(); });
            $sortType = self::PLINQ_ORDER_TYPE_NUMERIC;
        }            
        $applicables = $applicables->ToArray();


        if($direction == self::PLINQ_ORDER_ASC)
            asort($applicables, (($sortType == self::PLINQ_ORDER_TYPE_NUMERIC)? SORT_NUMERIC : SORT_LOCALE_STRING));
        else
            arsort($applicables, (($sortType == self::PLINQ_ORDER_TYPE_NUMERIC)? SORT_NUMERIC : SORT_LOCALE_STRING));

        $ordered = array();
        foreach($applicables as $key => $value)
            $ordered[$key] = $this->dataSource[$key];
            
        return self::Instance($ordered);
    }
    
    /**
     * Orders this objects items in ascending order according to the selected key in closure
     * 
     * @param ObjectClosure $closure    a closure that selects the order key, key can be anything
     * @return Plinq    Ordered items
     */
    public function OrderBy($closure)
    {
        return $this->Order($closure, self::PLINQ_ORDER_ASC);
    }
    
    /**
     * Orders this objects items in descending order according to the selected key in closure
     * 
     * @param ObjectClosure $closure    a closure that selects the order key, key can be anything
     * @return Plinq    Ordered items
     */
    public function OrderByDescending($closure)
    {
        return $this->Order($closure, self::PLINQ_ORDER_DESC);
    }    
    
    /**
     * Gets the maximimum item value according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return  numeric Maximum item value
     */
    public function Max($closure)
    {
        $max = null;    
        foreach ($this->dataSource as $key => $value) {   
                      
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            if(is_null($max))
                $max = $result;
            elseif($max < $result)
                $max = $result;                
            
        }
        
        return $max; 
    }   
    
 
     /**
     * Gets the minimum item value according to $closure
     * 
     * @param ObjectClosure $closure    a closure that returns any numeric type (int, float etc.)
     * @return  numeric Minimum item value
     */
    public function Min($closure){
        $min = null;    
        foreach ($this->dataSource as $key => $value) {   
              
            if(!is_numeric(($result = call_user_func_array($closure, array($key, $value)))))
                continue;
            
            if(is_null($min))
                $min = $result;
            elseif($min > $result)
                $min = $result;
        }
        
        return $min; 
    }      

    /**
     *
     *      count
     */
    public function count(){
        return count($this->dataSource);
    }
    
    /**
     * Returns distinct item values of this 
     * 
     * @return Plinq    Distinct item values of this 
     */
    public function Distinct()
    {
        return self::Instance(array_unique($this->dataSource));
    }
    
    /**
     * Intersects an Array with this
     * 
     * @param Array $array  Array to intersect
     * @return Plinq    intersected items
     */
    public function Intersect(Array $array)
    {
        $this->rewind();
        return self::Instance(array_intersect((Array)$this, $array));
    }    
    
    /**
     * Finds different items
     * 
     * @param Array $array
     * @return  Plinq   Returns different items of this and $array
     */
    public function Diff(Array $array)
    {
        $this->rewind();
        return self::Instance(array_diff($this->dataSource, $array));
    }
    
    /**
     * Plinq::ElementAt()
     * 
     * @param int $index
     * @return  Object  Item at $index
     */
    public function ElementAt($index)
    {
        $values = array_values($this->dataSource);        
        return $values[$index];
    }

    /**
     * Plinq::First()
     * 
     * @return  Object  Item at index 0
     */
    public function First()
    {
        return $this->ElementAt(0);
        // return array_slice($this->dataSource, 0, 1);
    }
    
    /**
     * Plinq::Last()
     * 
     * @return  Object  Last item in this
     */
    public function Last()
    {
        return $this->ElementAt((count($this->dataSource)-1));
    }    


    //根据条件筛选数组
	private function GetApplicables($closure, $count = 0, $closureReturnType = self::PLINQ_CLOSURE_RETURN_TYPE_BOOL)
    {
        $applicables = array();
        
        $totalApplicable = 0;
        foreach($this->dataSource as $storedKey => $stored)
        {            
            if($count > 0 && $totalApplicable >= $count)
                break;
            
            switch($closureReturnType)
            {   
                case self::PLINQ_CLOSURE_RETURN_TYPE_BOOL:                    
                    if(!is_bool(($returned = call_user_func_array($closure, array($storedKey, $stored)))) || !$returned)
                        continue;
                        
                    $applicables[$storedKey] = $stored;
                    $totalApplicable++;                                            
                break;
                case self::PLINQ_CLOSURE_RETURN_TYPE_OBJECT:
                    $applicables[$storedKey] = call_user_func_array($closure, array($storedKey, $stored));
                    $totalApplicable++;                        
                break;    
            }
        }          
      
        return self::Instance($applicables);        
    }





    /**
     * 返回结果集
     * 
     * @return Array    Plinq as Array
     */
    public function ToArray(){
        return $this->dataSource;       //返回结果集
    }








}