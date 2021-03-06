<?php
/**
 * Created by PhpStorm.
 * @author: Verem Dugeri
 * Date: 9/25/15
 * Time: 10:26 PM
 */

namespace Verem\Persistence;

use PDO;
use PDOException;
use Verem\EnvReader;

 abstract class Connector
 {
	/**
	 * @var $dsn
	 * The data-source name for the connection
	 */
     private static $dsn;

	 /**
	 * @var $user
	 * The username for the connection.
	 */
	 private static $username;

	 /**
	 * @var $password
	 * The database password for the connection.
	 */
	 private static $password;

	 /**
	 * @var array $options Database connection configs
	 */
	 private static $options;

	 /**
	 * @var $dotEnv
	 *  The environment variables
	 */
	 private static $dotEnv;

	 /**
	 * Create a connection to the database
	 *
	 * @return PDO|string
	 */
	 public static function createConnection()
	 {
		static::initConfig();
		try{
			return new PDO(self::$dsn, self::$username, self::$password,self::$options );
		} catch (PDOException $e) {
			return $e->getMessage();
		}
	 }

	 /**
	 * Initialize database configurations. Read from
	 * the environment variables
	 */
	 public function initConfig()
	 {
		self::$dotEnv = new EnvReader();
		self::$dotEnv->loadEnv();

		self::$password = getenv('DB_PASSWORD');
		self::$username = getenv('DB_USERNAME');
		self::$dsn 		= getenv('DB_CONNECTION').":host=".getenv('DB_HOST').";dbname=".getenv('DATABASE_NAME');

		self::$options = [
		  PDO::ATTR_PERSISTENT => true,
		  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		];
	 }

 }
