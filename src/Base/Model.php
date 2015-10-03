<?php
/**
 * Created by PhpStorm.
 * @author: Verem Dugeri
 * Date: 9/25/15
 * Time: 10:22 PM
 */

namespace Verem\persistence\Base;

use PDO;
use PDOException;
use ReflectionClass;
use Verem\persistence\Connector;
use Verem\Persistence\Exceptions\DatabaseException;

abstract class Model extends Connector
{
    protected $primaryKey = 'id';
    protected static $tableName;
    protected $properties = [];

    /**
     * @return string
     *
     * get the name of the class. This will determine
     * the name of the table
     */
    protected function getClassName()
    {
        return substr(strrchr(static::getClass(), '\\'), 1);
    }

	protected function getClass()
	{
		return get_called_class();
	}


    /**
     * return all records in the database
     */
    public static function all()
    {
        $table = static::getTable();
        $result = null;
        try {
            $connection = static::createConnection();
            $result = $connection->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_CLASS, get_called_class());
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        } finally {
            $connection = null;
        }
        return $result;
    }

    /**
     * @return array
     */
    public function save()
    {
        $id = '';
        if ($this->exists()) {
            $this->performUpdate();
        } else {
            $id =  $this->performInsert();
        }
        return $id;
    }

    /**
     * @param $id
     * @return array
     *
     * Fetches a model, from the database that
     * matches the specified $id.
     */

    public static function find($id)
    {
        $table= static::getTable();
        $result = null;
        $connection = null;

		//Try to get a connection to db. Throw error if connection is
		//not successful
        try {
            $connection = static::createConnection();
        } catch (PDOException $e) {
            return $e->getMessage();
        }

		//try to create a statement. Throw exception if error exists.
        try {
            $statement = $connection->prepare("SELECT * FROM {$table} WHERE id = ?");
            if ($statement) {
                $statement->bindParam(1, $id);
                $statement->execute();
                $result   =  $statement->fetchAll(PDO::FETCH_CLASS);
            }
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        } finally {
            $statement   = null;
            $connection  = null;
        }
        return $result;
    }


    /**
     * @param $column
     * @param $value
     * @return array
     *
     * find all records where column matches value
     */
    public static function where($column, $value)
    {
        $table = static::getTable();
        $statement  = null;
        $connection = null;
        $result     = null;

        try {
            $connection = static::createConnection();
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }

        try {
            $statement = $connection->prepare("SELECT * FROM {$table} WHERE {$column} = ?");

            if ($statement) {
                $statement->bindParam(1, $value);
                $statement->execute();
                $result = $statement->fetchAll(PDO::FETCH_CLASS);
            }
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        } finally {
            $statement    = null;
            $connection   = null;
        }
        return $result;
    }

    /**
     * Update a row in the db with a matching id
     *
     * @return int
     */
    public function performUpdate()
    {
        try {
            $connection = static::createConnection();
        } catch (PDOException $e) {
            return $e->getMessage();
        }
        try {
            $count = 0;
            $sql = "UPDATE ".self::$tableName." SET ";
            $insertColumns = "";
            $insertValues = [];
            foreach ($this->getProperties() as $key => $value) {
                $count++;
                if ($key ===$this->primaryKey) {
                    $insertValues[":".$key] = $value;
                    continue;
                }
                if (isset($value)) {
                    $insertColumns .=  $key . " = :".$key;
                    $insertValues[":".$key] = $value;
                }
                if ($count < count($this->getProperties())) {
                    if (isset($value)) {
                        $insertColumns .= ", ";
                    }
                }
            }
            $sql .= $insertColumns . " WHERE " . $this->primaryKey. " = :". $this->primaryKey;

            $statement = $connection->prepare($sql);
            $result = $statement->execute($insertValues);
        } catch (PDOException $e) {
            return $e->getMessage();
        }
        return $result;
    }

    /**
     * @param $id
     * @return int
     *
     * Delete a record from database
     */
    public static function destroy($id)
    {
        $table = self::$tableName;
        $rowCount = 0;

        try {
            $connection = static::createConnection();
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }

        try {
            $statement = $connection->prepare("DELETE FROM {$table} WHERE ID = ?");
            if ($statement) {
                $statement->bindParam(1, $id);
                $statement->execute();
                $rowCount = $statement->rowCount();
            }
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        } finally {
            $statement    = null;
            $connection = null;
        }
        return $rowCount;
    }


    /**
     * Split the string at each occurrence of a camel
     * case, add underscores to separate individual
     * words and pluralize the word to agree with
     * the database schema
     *
     * @return mixed
     */
    public function getTable()
    {
        $splitter = new Splitter($this->getClassName());

        $splittedString = $splitter->format();

     	return Inflect::pluralize($splittedString);
    }

    /**
     * @param $property
     * @param $value
     *
     * Magic method for setting the value of
     * a property conjured out of thin air.
     */
    public function __set($property, $value)
    {
        $this->properties[$property] = $value;
    }

    /**
     * @param $property
     * @return mixed
     *
     * Magic method for getting the value of a property
     * whose name was just concocted from nowhere.
     */
    public function __get($property)
    {
        return $this->properties[$property];
    }

	/**
	 * @return array
	 */
    private function getProperties()
    {
        return $this->properties;
    }

	/**
	 * @return bool|null|string
	 */
    public function performInsert()
    {
        $table   =  $this->getTable();
        $result = null;
        try {
            $connection =  static::createConnection();
        } catch (PDOException $e) {
            throw new DatabaseException($e);
        }

        try {
            $keys = array_keys($this->properties);
            $insertColumns = implode(', ', $keys);
			$placeholders = [];
            foreach ($keys as $key){
				$placeholders[$key] = '?';
			}

			$placeholders = implode(', ', $placeholders);

            $sql = "INSERT INTO $table ($insertColumns) VALUES ($placeholders)";
            $statement = $connection->prepare($sql);

			$count = 0;
            foreach ($this->properties as $key => $value) {
				++$count;
                $statement->bindValue($count, $value);
            }

			$result = $statement->execute();


        } catch (PDOException $e) {
            return $e->getMessage();
        }

        return $result;
    }

	/**
	 * @return bool
	 */
    public function exists()
    {
        if (isset($this->properties) && isset($this->id)
                && is_numeric($this->id)) {
            return true;
        } else {
            return false;
        }
    }
}
