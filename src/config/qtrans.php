<?php

return [
	
	/** 
	 * Global trans defaults for ScopeTrans  ___()
	 */
	
	// If basic is set to true, translations are returned as plain escaped strings as is normal
	// leave basic false and be able to apply the html processors
	// This determines each instances $basic_setting upon new instance or app singleon resolve. 
	// You can manually change the app singleton or instances setting via $basic_setting property
    'basic' => false,
	
	// Only works when the instances $basic_setting is false
	// Determines the processors used by default
	// This can be changed by modifying app singleton after the fact or instances setting via $processor_setting property
	// Or processors can be applied manually per each translation
	'processor' => [
		// 0 name, 1 extra arguments (note first argument will always be input string)
		['escape', [] ], // TODO IF CREATING PACKAGE set here as on by default to prevent users from sending unescaped text, but the intension is users will be free to not escape
		//['br', [] ], // apply n12br and change new lines to breaks. Be careful of processor order, text should be escaped before using this
		//['markdown', [] ],
	]

];
