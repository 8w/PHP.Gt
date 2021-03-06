<?php class ClassDependencies {

/**
 * This file allows the Autoloader to find class definitions that don't follow
 * the standard PHP.Gt file structre.
 */

public static $list = array(
	"Hybrid_Auth" => "Auth/hybridauth/Hybrid/Auth.php",
	"Hybrid_Endpoint" => "Auth/hybridauth/Hybrid/Endpoint.php",
	"Hybrid_User_Profile" => "Auth/hybridauth/Hybrid/User_Profile.php",

	"Logger" => "Log/Logger.class.php",
);

}#