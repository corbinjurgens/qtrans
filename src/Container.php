<?php

namespace Corbinjurgens\QTrans;

use Illuminate\Contracts\Support\Htmlable;

class Container implements Htmlable
{
   /**
	 * @param string|array|null $base Starting base
	 * @param bool|null $basic_setting Set or use default in trans config file
	 * @param array|null $processor_setting Set or use default in trans config file
	 */
	public function __construct($base = null, bool $basic_setting = null, array $processor_setting = null){
		$this->base($base);
		$this->basic_setting = $basic_setting ?? config('qtrans.basic', false);
		$this->processor_setting = $processor_setting ?? config('qtrans.processor', []);
	}
	
	/**
	 * @param bool Whether to return trans string as is (true) or allow more customizable text processors (false)
	 */
	public $basic_setting;
	
	/**
	 * @param array Processors to apply to trans result 
	 */
	public $processor_setting = [];
	
	/**
	 * If you want to use a separate instance tied to a variable
	 * 
	 * @param string|array|null $base
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function new($base = null){
		return new self($base);
	}
	/**
	 * Used when looking for the same given string in entirely different file
	 * eg add '_missing' to root and it will look there (warning will include file name string)
	 * 
	 * @var array
	 */
    private $root = [
		''
	];
	
	/**
	 * @var array Array of base key sets pointing to translation
	 */
    private $base = [];
	
	/**
	 * Whether the current base should be prepended by the direct previous
	 * ie, when shifting. First in array should always be false as set by base()
	 * 
	 * @var array Array of booleans
	 */
    private $prepend = [];
	
	/**
	 * @var array Array of cache of prepeared base so as not to prepare base each time
	 */
    private $base_cache = [];
	
	/**
	 * When you have a priority base key. 
	 * This can be the same as one in base array
	 * 
	 * @var array Array of null or string
	 */
    private $first = [];
	
	/**
	 * Temporarily turn off the scoped base keys, and get translation plainly
	 * 
	 * @var int
	 */
    private $halt = 0;
	
	/**
	 * If halting should be automatically switched off
	 * 
	 * @var bool
	 */
    private $switch_back = false;
	
	/**
	 * A string or array of string base keys
	 * which point to where to look for the key used in get()
	 * Does not need trailing dot. 
	 * 
	 * @param string|array|null $base
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function base($base = null){
		$this->reset();
		
		$this->shift($base);
		
		return $this;
	}
	
	/**
	 * If you want to give a priority for one of the items in current base, set it here
	 * otherwise it will search in order given in base
	 * 
	 * @param string|null $priority
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function priority(string $priority = null){
		$this->first[array_key_last($this->first)] = $priority;
		return $this;
	}
	
	/**
	 * Change current base, with the intention of stepping back to the previous base after
	 * Can be an extension of previous bases by setting $prepend to true, 
	 * otherwise will be a fresh base. If you dont intend to come back
	 * May be used in a similar way to pause, ie to switch to a different base, or clear base,
	 * except with the ability to return later
	 * 
	 * @param string|array|null $base
	 * @param bool $prepend
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function shift($base = null, $prepend = false){
		$base = (array) $base;
		$this->base[] = $base;
		$this->first[] = null;
		$this->prepend[] = ($prepend && count($this->base) > 1);
		$this->base_cache[] = null;
		return $this;
	}
	
	/**
	 * Return to previous step by clearing current step
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function back(){
		array_pop($this->base);
		array_pop($this->first);
		array_pop($this->prepend);
		array_pop($this->base_cache);
		return $this;
	}
	
	/**
	 * Clear any currently scoped bases
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function reset(){
		$this->base = [];
		$this->first = [];
		$this->prepend = [];
		$this->base_cache = [];
		
		$this->halt = 0;
		$this->switch_back = false;
		return $this;
	}
	
	
	/**
	 * Temporarily behave as normal translation, without
	 * any scoping to base keys. Good if in between your translations
	 * you need to include a template which has other translations, but remember
	 * your base and go back quickly
	 *
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function pause(){
		$this->halt = 1;
		$this->switch_back = false;
		return $this;
	}
	
	/**
	 * Same as pause, but only has effect for the next translation (or a specified amount of translation), then
	 * is automatically changed back
	 * 
	 * @param int $times
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function skip(int $times = 1){
		$this->halt = $times;
		$this->switch_back = true;
		return $this;
		
	}
	
	/**
	 * After calling pause(), you must manually call this to go back to 
	 * base you were using before pause
	 *
	 * @return \Corbinjurgens\QTrans\Container
	 */
	public function resume(){
		$this->halt = 0;
		$this->switch_back = false;
		return $this;
	}
	
	/**
	 * Get full prepared base ready to append key to
	 * 
	 * @param string|null $key
	 * @return \Corbinjurgens\QTrans\Container
	 */
	private function __getBase($key = null){
		if ($this->halt > 0){
			return $this->__prependRoot(['']);
		}
		
		$key = $this->__resolveKey($key);
		
		if (!isset($this->base_cache[$key])){
			$base = $this->__getBaseByKey($key);
			$base =  $this->__processBase($base ?: ['']);
			$this->base_cache[$key] = $base;
		}
		return $this->base_cache[$key];
	}
	
	/**
	 * Specify a base level, otherwise get latest
	 * 
	 * @param string|int $key
	 * @return null|int
	 */
	private function __resolveKey($key = null){
		if ($key === null){
			$key = array_key_last($this->base);
		}
		return $key;
	}
	
	/**
	 * Get and prepare a specific base set
	 * by default get the latest
	 * 
	 * @param int $key Array index pointer
	 * @return array
	 */
	private function __getBaseByKey($key){
		
		$base = !empty($this->base[$key]) ? $this->base[$key] : [];
		$priority = $this->first[$key] ?? null;
		if ($priority){
			$base = array_diff($base, [$priority]);
			array_unshift($base, $priority);
		}
		return $base;
		
	}
	
	/**
	 * Process the given base by applying all preceeding bases previous
	 * to the the given key while prepend mode is true, and also prepend root
	 * 
	 * @param array $base
	 * @param string|int $key
	 * @return array
	 */
	private function __processBase($base, $key = null){
		$results = [];
		$prepared_prepend_bases = [''];
		
		// Check if the current base level should
		// be prepended by previous and get all
		// until no longer prepend
		$key = $this->__resolveKey($key);
		if (!is_null($key)){
			$prepared_prepend_bases = $this->__buildPrepend($key);
		}
		
		// Create base keys
		foreach($prepared_prepend_bases as $prepend){
			foreach($base as $key){
				$processed_key = join('.', array_filter([$prepend, $key]));
				$results[] = $processed_key;
			}
		}
		
		$results = $this->__prependRoot($results);
		
		return $results;
	}
	
	/**
	 * Based on specified level, prepare prepend bases up until that point of specified key
	 * otherwise return single empty prepend
	 * 
	 * @param int $key 
	 * @return array
	 */
	private function __buildPrepend($key){
		$prepared_prepend_bases = [''];
		$prepend_base = [];
		$prepend = array_reverse(array_slice($this->prepend, 0, $key + 1, true), true);
		foreach($prepend as $key => $prepend_mode){
			if ($prepend_mode === true){
				$prepend_base[] = $this->__getBaseByKey($key - 1);
				continue;
			}
			break;
		}
		
		// Get all iterations of previous prepends
		// ready to simply iterate over when creating
		// base keys
		foreach($prepend_base as $index => $prepend_bases){
			$previous = $prepared_prepend_bases;
			$prepared_prepend_bases = [];
			foreach($prepend_bases ?: [''] as $p){
				foreach($previous as $i => $b){
					$prepared_prepend_bases[] = join('.', array_filter([$p, $b]));
				}
			}
		}
		
		return $prepared_prepend_bases;
	}
	
	/**
	 * Prepend the root to the given base
	 * 
	 * @param array $base
	 * @return array
	 */
	private function __prependRoot($base){
		if ($this->root === ['']){
			return $base;
		}
		
		$results = [];
		foreach($this->root as $root){
			foreach($base as $key){
				$processed_key = join('.', array_filter([$root, $key]));
				$results[] = $processed_key;
			}
		}
		return $results;
	}
	
	/**
	 * Get translation by key, looking to the current bases to find it
	 * 
	 * @param string|null $key 
	 * @param array $replace
	 * @param string|null $locale 
	 * @return string|Corbinjurgens\QTrans\Html|array
	 */
	public function get($key = '', array $replace = [], $locale = null){
		$bases = $this->__getBase();
		foreach($bases as $base){
			$processed_key = join('.', array_filter([$base, $key]));
			if ( ( $trans = __($processed_key, $replace, $locale) ) !== $processed_key){
				$this->__processHalt();
				return $this->__display($trans);
			}
		}
		$this->__processHalt();
		return $this->__display(
			join('.', array_filter([$bases[0], $key])),
			true
		);
	}
	
	/**
	 * If currently skipping, set it back until its over
	 * @return void
	 */
	private function __processHalt(){
		if (!$this->switch_back){
			return;
		}
		if ($this->halt > 0){
			$this->halt -= 1;
		}
		if ($this->halt <= 0){
			$this->switch_back = false;
		}
	}
	
	/**
	 * Debug, get current base keys in use
	 * @return array
	 */
	public function current(){
		return $this->__getBase();
	}
	
	/**
	 * Return translation, either as is or with additional functions applied
	 *
	 * @param string $result
	 * @return \Corbinjurgens\QTrans\Html|string|array
	 */
	private function __display($result, $force_fallback = false){
		if ($this->basic_setting || is_array($result)){
			return $result;
		}
		
		$html = new Html($result, $this->processor_setting);
		$html->force_fallback = $force_fallback;
		return $html;
		
	}
	
	
	/**
	 * If a user calls a known Html processor on the container, we will assume
	 * Its a mistake, and pass an empty html and allow them to continue as is
     * @return \Corbinjurgens\QTrans\Html
	 * @throws \Exception
	 */
	public function __call($name, $arguments){
		if (Html::processorExists($name)){
			return (new Html(''))->$name(...$arguments);
		}
		throw new \Exception("Function $name not found in " . __CLASS__);
	}
	
	/**
	 * Incase users pass a variable to ___() or trans() expecting it to be a key
	 * but variable is null, then they accidentaly send this class to the template
	 * Return string of this class
     * @return string
	 */
	public function toHtml()
    {
        return '';
    }
}
