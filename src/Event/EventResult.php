<?php
/**
 * Created by PhpStorm.
 * User: mmuno
 * Date: 28/09/2016
 * Time: 12:33
 */

namespace GLFramework\Event;

/**
 * Class Event
 *
 * @package GLFramework
 */
class EventResult
{
    private $result = false;
    private $events = array();
    private $handlers = array();
    private $count = 0;

    /**
     * Event constructor.
     *
     * @param $result
     */
    public function __construct($result = null)
    {
        $this->result = $result;
    }

    /**
     * TODO
     *
     * @return bool
     */
    public function isTrue()
    {
        return $this->result === true;
    }

    /**
     * TODO
     *
     * @return bool
     */
    public function anyFalse()
    {
        if($this->count == 0) return false;
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item === false) {
                    return true;
                }
            }
        } else {
            return $this->result == false;
        }
        return false;
    }

    /**
     * TODO
     *
     * @return bool
     */
    public function allFalse()
    {
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item === true) {
                    return false;
                }
            }

            return true;
        }

        return $this->result === false;
    }

    /**
     * TODO
     *
     * @return bool
     */
    public function anyTrue()
    {
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item === true) {
                    return true;
                }
            }
        } else {
            return $this->result === true;
        }
        return false;
    }

    /**
     * TODO
     *
     * @return bool
     */
    public function allTrue()
    {
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item === false) {
                    return false;
                }
            }
            return true;
        }

        return $this->result === true;
    }

    public function any($type) {
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item == $type) {
                    return true;
                }
            }
            return false;
        }
        return $this->result == $type;
    }
    public function all($type) {
        if (is_array($this->result)) {
            foreach ($this->result as $item) {
                if ($item != $type) {
                    return false;
                }
            }
            return true;
        }
        return $this->result == $type;
    }

    /**
     * TODO
     *
     * @return string
     */
    public function getString()
    {
        return $this->__toString();
    }

    /**
     * TODO
     *
     * @return string
     */
    function __toString()
    {
        // TODO: Implement __toString() method.
        return implode('', $this->getArray());
    }

    /**
     * TODO
     *
     * @return array
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * TODO
     *
     * @param Event $handler
     */
    public function addHandler($handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * TODO
     *
     * @param $item
     * @param $handler
     */
    public function addResult($item, $handler)
    {
        $this->result[] = $item;
        $this->events[] = array($item, $handler);
        $this->count++;
    }

    /**
     * TODO
     *
     * @return array|bool|null
     */
    public function getArray()
    {
        if (!$this->result) {
            return array();
        } // No hay manipuladores
        if (!is_array($this->result)) {
            return array($this->result);
        } // El resultado es solo 1, generar un array
        return $this->result; //
    }

    /**
     * @return array
     */
    public  function getEvents()
    {
        return $this->events;
    }
}
