<?php
/**
 *     GLFramework, small web application framework.
 *     Copyright (C) 2016.  Manuel Muñoz Rosa
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Created by PhpStorm.
 * User: manus
 * Date: 24/02/16
 * Time: 10:35
 */

namespace GLFramework\Database;

use DebugBar\DataCollector\PDO\TraceablePDOStatement;
use GLFramework\Events;
use TijsVerkoyen\CssToInlineStyles\Exception;

/**
 * Class MySQLConnection
 *
 * @package GLFramework\Database
 */
class MySQLConnection extends Connection
{

    /**
     * @var \mysqli
     */
    private $link;
    /**
     * @var \PDO
     */
    private $pdo;

    /**
     * @var Exception
     */
    private $lastError;
    /**
     * TODO
     *
     * @param $hostname
     * @param $username
     * @param $password
     * @return bool
     */
    public function connect($hostname, $username, $password)
    {
        try {
            $this->pdo = new \PDO('mysql:host=' . $hostname . ';', $username, $password);
            $result = Events::dispatch('onPDOCreated', array(&$this->pdo));
            if (intval($this->pdo->errorCode()) === 0) {
                $this->pdo->exec('SET NAMES utf8');
                $this->pdo->exec('SET sql_mode = \'\'');
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                return true;
            }
        } catch (\Exception $ex) {
//            print_debug($ex);
            Events::dispatch('onException', $ex);
            $this->lastError = $ex;
//            throw $ex;
        }
        return false;
    }

    /**
     * TODO
     *
     * @param $database
     * @return bool
     */
    public function select_database($database)
    {
        //        try{

        if ($this->pdo) {
            if ($this->pdo->exec('USE ' . $database) !== FALSE) {
                return true;
            }
        }
        //        }catch(\Exception $ex)
        //        {
        //            new Exception($this->getLastError());
        //        }
        return false;
    }

    /**
     * TODO
     *
     * @param $value
     * @return bool|string
     */
    public function escape_string($value)
    {
        if ($this->pdo) {
            return substr($this->pdo->quote($value), 1, -1);
        }
        return $value;
    }

    /**
     * TODO
     *
     * @param $query
     * @param array $args
     * @param bool $returnArray
     * @return array|bool
     * @throws \Exception
     */
    public function select($query, $args = array(), $returnArray = true)
    {
        if($this->pdo) {

            if (!defined('GL_INSTALL') || !GL_INSTALL) {
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            } else {
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
            }
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute($args);
            //$list = array();
            if ($result) {
                if ($returnArray) {
                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
                return true;
            } else {
                //            if($this->getLastError())
                throw new \Exception($query . "\n" . $this->getLastError());
            }
        } else {
            throw new \Exception("Error executing query: $query\nIt seems that the connection was not opened!");

        }
    }

    /**
     * TODO
     *
     * @return string
     */
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * TODO
     *
     * @return mixed
     */
    public function getLastError()
    {
        if($this->lastError) {
            return $this->lastError->getMessage();
        }
        $error = $this->pdo->errorInfo();
        if ($error[1]) {
            print_debug($error);
        }
        return $error[2];
    }

    /**
     * TODO
     *
     * @return \PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * TODO
     */
    public function disconnect()
    {
        // TODO: Implement disconnect() method.
        if ($this->pdo) {
            $this->pdo = null;
        }
    }
}
