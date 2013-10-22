<?php

/*
 * Info..
 */

class PPAC_PACK_INCLUDER {

	Public static $ver = "0.0.1";

	/*
	 * Who use the PPAC Main Script path.
	 * It's limit for the searching.
	 */
	Public static $scriptPath;

	/**
	 * Determined Module paths are caching here.
	 */
	Public static $modulePathCache = array();

	/**
	 * Inluded files.
	 */
	public static $includedFiles = array();

	/**
	 * Configrations.
	 */
	public static $config = array();

	/**
	 * Setup!
	 */
	public static function setup() {

		//	Getting Script Path for Module Folder Searching.
		self::$scriptPath = str_replace("/", DS, dirname( $_SERVER["SCRIPT_FILENAME"] ) . DS );

		/*
		 * PPAC_CONF is loading.
		 */
		require_once PPAC_CORE . "ppac_conf.php";
		PPAC_CONF::setup();

		if (self::config("php_modules.common.module.auto_load_class")) {
			spl_autoload_register('PPAC_PACK_INCLUDER::add');
		}
	}

	public static function config() {
		$args = func_get_args();

		//	If Args count = 1, return config key by arg.
		if (count( $args ) == 1) {
			return PPAC_CONF::get( $args[0] );
		}
		//	Else this is config setting, return new config val.
		else
		{
			return PPAC_CONF::set( $args[0], $args[1] );
		}
	}

	public static function add( $paths, $opt = array() ) {

		$opt = array_merge( array(
			"reloadable" => self::config( "php_modules.common.module.reloadable" ),
			"return_require" => self::config( "php_modules.common.module.return_require" )
			),
			$opt
		);

		/*
		 * Determination for base search folder.
		 */
		$funcCallers = debug_backtrace();

		//	'\\' marks are cleaning.
		$paths = trim( $paths, "\\" );

		//	Path is splitting.
		$paths = explode("\\", $paths);

		//	Getting Module name.
		$module = array_shift($paths);

		/*
		 * Decide to which file call this function.
		 * That file folder is using for searching and
		 * that folder is base limit for searching.
		 */
		foreach ($funcCallers as $funcCaller) {
			if (isset( $funcCaller["file"] )) {
				$searchFromFolder = dirname( $funcCaller["file"] ) . DS;
				break;
			}
		}

		/*
		 * If Module Path isn't cache AND
		 * Module Path didn't find..
		 */
		if (false === $modulePath = self::getModulePath( $module, $searchFromFolder)) {
			trigger_error( "Module didn't find: $module" );
			return;
		}

		// Get Module JSON file.
		$moduleJSON = self::getModuleJSON( $module );

		/*
		 * If way is only one,
		 * This is adding module procedure,
		 * define file from module settings file.
		 */
		if ( $paths == array() ) {
			$file = $moduleJSON["main"];
		} else {
			$file = array_pop( $paths ) . ".php";
		}

		if ( $paths != array() ) {
			$folder = implode(DS, $paths) . DS;
		} else {
			$folder = "";
		}

		$realPath = realpath( $modulePath . $folder . $file );

		/*
		 * If this file didn't added or reloadable is active, then require it!
		 */
		if ( !isset(self::$includedFiles[ $realPath ]) || $opt["reloadable"] ) {
			/*
			 * Save file name to key, Because this is faster for find which files loaded.
			 */
			self::$includedFiles[ $realPath ] = true;

			/*
			 * If return require's return is active, return it!
			 */
			if ($opt["return_require"]) {
				return require $realPath;
			}
			else
			{
				require $realPath;
				return true;
			}
		}
		else
		{
			return false;
		}

	}

	/**
	 * Module searcher.
	 */
	private static function getModulePath( $moduleName, $start = null, $finish = null ) {

		/*
		 * If this module is been cache.
		 */
		if ( isset( self::$modulePathCache[ $moduleName ] ) ) {
			return self::$modulePathCache[ $moduleName ];
		} elseif ($start == null) {
			return false;
		}

		if ( $finish == null ) {
			$finish = self::$scriptPath;
		}

		$intermediateDirs = substr( $start , strlen($finish) );

		$privatePhpModulesFolderName = self::config( "php_modules.private.folder_name" );

		if ( $intermediateDirs != false ) {
			do {

				$targetDirs[] = $finish . $intermediateDirs . $privatePhpModulesFolderName . DS;

				$intermediateDirs = dirname( $intermediateDirs ) . DS;

			} while( $intermediateDirs != "." . DS );
		}

		$targetDirs[] = $finish . $privatePhpModulesFolderName . DS;

		/*
		 * Set first seach area, is public folder first or last?
		 */
		if (self::config("php_modules.public.add_first")) {
			array_unshift($targetDirs, self::config("php_modules.public.path")); 
		}
		else
		{
			array_push($targetDirs, self::config("php_modules.public.path")); 
		}
		
		foreach ($targetDirs as $target) {
			if (is_dir( $target . $moduleName . DS )) {
				return
					self::$modulePathCache[ $moduleName ]
						=
					$target . $moduleName . DS;
			}
		}

		return false;
	}

	private static function getModuleJSON( $module ) {

		$modulePath = self::getModulePath( $module );

		if ( file_exists( $modulePath . self::config("php_modules.common.module.settings_file_name") ) )
			return json_decode(file_get_contents( $modulePath . self::config("php_modules.common.module.settings_file_name") ), true);

		trigger_error( "Module JSON didn't find. Module: $module. Path: $modulePath" );

		return false;
	}

}