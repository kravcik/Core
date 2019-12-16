<?php

namespace Core;

abstract class Entity extends \YetORM\Entity
{
    /**
     * Special behaviour for json
     * @see parent
     *
     * @param string $name
     *
     */
    public function & __get($name)
    {
        $value = parent::__get($name);

        $ref = static::getReflection();

        $property = $ref->getEntityProperty($name);

        if(!$property)
        {
            throw new \Exception('Property ' . $name . ' is not defined in ' . $ref->getName());
        }

        if($property->getDescription() == 'json')
        {
            if($value !== NULL)
            {
                $value = \Nette\Utils\Json::decode($value, \Nette\Utils\Json::FORCE_ARRAY);
            }
        }

        return $value;
    }

    /**
     * Special behaviour for json & \Nette\Utils\DateTime properties
     * @see parent
     *
     * @param string $name
     * @param mixed $value
     *
     */
    public function & __set($name, $value)
    {
        $ref = static::getReflection();

        $property = $ref->getEntityProperty($name);

        if(!$property)
        {
            throw new \Exception('Property ' . $name . ' is not defined in ' . $ref->getName());
        }

        if($property->getDescription() == 'json')
        {
            if(is_array($value))
            {
                $value = \Nette\Utils\Json::encode($value);
            }
        }

        if($property->getType() == 'Nette\Utils\DateTime' && !is_null($value))
        {
            $value = \Nette\Utils\DateTime::from($value);
        }

        $value = parent::__set($name, $value);

        return $value;
    }

    /**
     * Fill entity from array or ArrayHash
     *
     * @param array|\Nette\Utils\ArrayHash
     *
     * @return void
     */
    public function fillFromArray($values)
    {
        $ref = static::getReflection();

        foreach($ref->getEntityProperties() as $name => $property)
        {
            $functionName = 'set' . ucfirst($name);

            if(!$property->isReadonly())
            {
                /**
                 * Entity has special set function? Use it instead of simple set
                 */
                if(method_exists($this, $functionName))
                {
                    $this->$functionName($values[$name]);
                }
                else
                {
                    /**
                     * Set NULL for nullable properties without value
                     * Skip if property not set and is not nullable
                     */
                    if(!isset($values[$name]) && $property->isNullable() && empty($values[$name]))
                    {
                        $values[$name] = null;
                    }
                    elseif(!isset($values[$name]) && !$property->isNullable())
                    {
                        continue;
                    }

                    /**
                     * Convert bool to int
                     */
                    if($property->getType() == 'int' && is_bool($values[$name]))
                    {
                        $values[$name] = $values[$name] ? 1 : 0;
                    }

                    /**
                     * Convert strings to int
                     */
                    elseif($property->getType() == 'int' && !empty($values[$name]))
                    {
                        $values[$name] = intval($values[$name]);
                    }

                    /**
                     * Convert array to json
                     */
                    if($property->getDescription() == 'json' && is_array($values[$name]))
                    {
                        $this->$name = \Nette\Utils\Json::encode($values[$name]);
                    }
                    else
                    {
                        $this->$name = $values[$name];
                    }
                }
            }
        }
    }

    /**
     * Transform entity Array
     *
     * @param array $excludedProperties - If you don't want some keys (hide ID or password)
     *
     * @return Array
     */
    public function toArray($excludedProperties = [])
    {
        if(!$excludedProperties instanceof \Nette\Utils\ArrayHash && !is_array($excludedProperties))
        {
            throw new \Exception('Excluded properties should be Array or \Nette\Utils\ArrayHash');
        }

        $ref = static::getReflection();
	$values = [];

	foreach ($ref->getEntityProperties() as $name => $property)
        {
            if(array_search($name, $excludedProperties) === FALSE && $name != 'modifiedArray')
            {
                if($property instanceof \YetORM\Reflection\MethodProperty)
                {
                    $value = $this->{'get' . $name}();
                }
                else
                {
                    $value = (!empty($this->$name) || $this->$name === 0) ? $this->$name : NULL;
                }

                if(!($value instanceof \YetORM\EntityCollection || $value instanceof \YetORM\Entity))
                {
                    $values[$name] = $value;
                }
            }
	}

        return $values;
    }

    /**
     * Return array of modified values
     *
     * @return array
     */
    public function getModifiedArray()
    {
        return $this->record->getModified();
    }
}
