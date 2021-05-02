<?php

namespace Corbinjurgens\QTrans;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Mail\Markdown;
use Closure;

class Html implements Htmlable
{
	/**
	 * @var null|string String used for this current html
	 */
    private $html_string;
	
	/**
	 * @var array List of processors to apply
	 */
	private $registered_processor = [];
	
	/**
	 * @var bool Whether the current registered processors were registered at instantiate
	 */
	private $registered_processor_array = false;
	
	/**
	 * @var null|string Fallback if the $html_string is null
	 */
	private $fallback;
	
	/**
	 * For when the $html_string may be present, but $fallback should still be returned
	 * Such as when translation returns key meaning it wasn't found
	 * @var bool
	 */
	public $force_fallback = false;
	
	/**
	 * @var array List of registered custom processors
	 */
	private static $custom_processors = [];
	
	/**
	 * Create new instance, passing $html_string is recommended, passing any $registered_processor here instead of
	 * calling one by one will allow users to then clear the processors given in favour of their own
	 * 
	 * @param string|null $html_string
	 * @param array|null $registered_processor
	 */
    public function __construct(string $html_string = null, array $registered_processor = null)
    {
       $this->set($html_string);
	   
	   // Registers processors, but does not check if they exist unlike calling them individually
	   $this->registerProcessorArray($registered_processor);
    }
	
	/**
	 * Create a custom processor
	 * 
	 * @param string $name
	 * @param Closure $closure
     * @return void
	 */
	public static function custom(string $name, Closure $closure){
		self::$custom_processors[$name] = $closure;
	}
	
	/**
	 * Register a procesor to be applied when retrieving string
	 * 
	 * @param string $name
	 * @param array $arguments
     * @return Corbinjurgens\QTrans\Html
	 * @throws \Exception
	 */
	public function __call($name, $arguments){
		if (self::processorExists($name)){
			
			// If a set of processors was already passed on class instantiate,
			// user is now manually calling processors, so processors should be 
			// first cleared, as we assume user wants different processors than 
			// class was created with, or may want a different process order
			if ($this->registered_processor_array === true){
				$this->registered_processor = [];
				$this->registered_processor_array = false;
			}
			
			$this->registered_processor[] = [$name, $arguments];
			return $this;
		}
		throw new \Exception("$name processor doesn't exist");
	}
	
	/**
	 * Check if the given processor name exists
	 * 
	 * @param string $name
     * @return bool
	 */
	public static function processorExists($name){
		return (method_exists(__CLASS__, "__$name") || isset(self::$custom_processors[$name]));
	}
	
	/**
	 * Set the target string for this class
	 * 
	 * @param string|null $html_string
     * @return Corbinjurgens\QTrans\Html
	 */
	public function set(string $html_string = null){
		 $this->html_string = $html_string;
		 return $this;
	}
	
	/**
	 * Apply all registered processors to the string and return
     * @return string
	 */
	public function __toString()
    {
		$html_string = $this->force_fallback
			? ($this->fallback ?? $this->html_string ?? '')
			: ($this->html_string ?? $this->fallback ?? '');
		
		foreach($this->registered_processor as $method){
			if (isset(self::$custom_processors[$method[0]])){
				$html_string = self::$custom_processors[$method[0]]($html_string, ...$method[1]);
			}else{
				$html_string = $this->{"__{$method[0]}"}($html_string, ...$method[1]);
			}
		}
		
        return !is_string($html_string) ? '' : $html_string;
    }
	
	/**
	 * Return string of this class
     * @return string
	 */
	public function toHtml()
    {
        return (string) $this;
    }
	
	/**
	 * Bulk register processors to use in this class directly from array
	 * such as from trans config. Setting via this funcion, and then later manually
	 * calling processors will clear all processors given here
	 * 
	 * @param array|null $processors
     * @return Corbinjurgens\QTrans\Html
	 */
	public function registerProcessorArray(array $processors = null){
		if ( is_array($processors) ){
			$this->registered_processor_array = true;
			$this->registered_processor = $processors;
		}
		return $this;
	}
	
	/**
	 * Clear all registered processors,
	 * useful for when default is set from qtrans()
	 * and you want to remove all processors, but
	 * don't have any processors you want to set
	 * ( normally manually setting any processor after
	 * defaut has been set will clear defaults )
	 * 
     * @return Corbinjurgens\QTrans\Html
	 */
	public function clear(){
		$this->registered_processor = [];
		return $this;
	}
	
	/**
	 * Set a fallback string to use if the current sting is null
	 * 
	 * @param string|null $fallback
     * @return Corbinjurgens\QTrans\Html
	 */
	public function fallback(string $fallback = null){
		if (is_string($fallback)){
			$this->fallback = $fallback;
		}
		return $this;
	}
	
	/**
	 * Processors
	 * ----------
	 */
	 
	 
	/**
	 * Apply usual template escaping
	 * When using this class, the blade function {!!  !!}
	 * is not necessary, it will always be not escaped unless this
	 * processor is used.
	 * 
     * @param  string  $string
     * @param  bool  $doubleEncode
     * @return string
	 */
	private function __escape($string, $doubleEncode = true){
		return e($string, $doubleEncode);
	}
	
	/**
	 * Use laravels built in Mail Markdown
	 * 
     * @param  string  $string
     * @return string
	 */
	private function __markdown($string){
		return Markdown::parse($string)->toHtml();
	}
	
	/**
	 * Replace new lines \n with html breaks
	 * be sure to call this BEFORE escaping
	 * 
     * @param  string  $string
     * @return string
	 */
	private function __br($string){
		return nl2br($string);
	}
}